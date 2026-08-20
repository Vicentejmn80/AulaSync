<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Course;
use App\Models\Grade;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Motor de consultas analíticas seguras para el chatbot del director (Nova).
 *
 * Reglas de arquitectura:
 * - La IA NUNCA genera SQL. Solo elige query_type + filtros; aquí se ejecutan
 *   consultas Eloquent fijas y parametrizadas.
 * - TODA consulta filtra por colegio_id del director autenticado.
 * - Cero alucinación: si no hay datos, el mensaje lo dice explícitamente.
 * - Salida en Markdown (tablas, rankings) para dar valor visual al director.
 */
class DirectorAnalyticsQueryService
{
    /** Promedio porcentual seguro en SQLite/Postgres/MySQL (100.0 fuerza float). */
    private const AVG_PCT = 'AVG(grades.score * 100.0 / NULLIF(activities.max_score, 0))';

    public function __construct(
        private PersonNameMatcher $matcher,
    ) {}

    // ─── Listas base (get_students / get_teachers / get_courses) ────────────

    public function getStudents(int $colegioId, ?string $grade = null, ?string $section = null): array
    {
        $students = Student::query()
            ->where('colegio_id', $colegioId)
            ->when($grade, fn ($q) => $q->whereRaw('LOWER(grade) = ?', [$this->key($grade)]))
            ->when($section, fn ($q) => $q->whereRaw('LOWER(COALESCE(section, ?)) = ?', ['', $this->key($section)]))
            ->orderBy('grade')
            ->orderBy('name')
            ->limit(80)
            ->get(['name', 'grade', 'section']);

        $scope = trim(($grade ?? '').($section ? ' / '.$section : '')) ?: 'el colegio';
        if ($students->isEmpty()) {
            return [
                'message' => "No hay alumnos registrados en {$scope}.",
                'data' => ['students' => []],
            ];
        }

        $lines = $students->map(fn ($s) => '- '.$s->name.' ('.trim($s->grade.($s->section ? ' / '.$s->section : '')).')');

        return [
            'message' => "Hay {$students->count()} alumno(s) en {$scope}:\n".$lines->implode("\n"),
            'data' => ['students' => $students, 'count' => $students->count()],
        ];
    }

    public function getTeachers(int $colegioId): array
    {
        $teachers = User::query()
            ->where('colegio_id', $colegioId)
            ->where('role', 'profesor')
            ->withCount(['courses' => fn ($q) => $q->where('colegio_id', $colegioId)])
            ->orderBy('name')
            ->limit(80)
            ->get(['id', 'name']);

        if ($teachers->isEmpty()) {
            return [
                'message' => 'No hay profesores registrados en el colegio todavía.',
                'data' => ['teachers' => []],
            ];
        }

        $lines = $teachers->map(fn ($t) => '- '.$t->name." ({$t->courses_count} curso(s))");

        return [
            'message' => "Hay {$teachers->count()} profesor(es):\n".$lines->implode("\n"),
            'data' => ['teachers' => $teachers, 'count' => $teachers->count()],
        ];
    }

    public function getCourses(int $colegioId, ?string $grade = null, ?string $section = null, ?string $subject = null): array
    {
        $courses = Course::query()
            ->where('colegio_id', $colegioId)
            ->when($grade, fn ($q) => $q->whereRaw('LOWER(grade) = ?', [$this->key($grade)]))
            ->when($section, fn ($q) => $q->whereRaw('LOWER(COALESCE(section, ?)) = ?', ['', $this->key($section)]))
            ->when($subject, fn ($q) => $q->whereRaw('LOWER(subject_name) like ?', ['%'.$this->key($subject).'%']))
            ->withCount('students')
            ->orderBy('grade')
            ->orderBy('subject_name')
            ->limit(120)
            ->get(['id', 'subject_name', 'grade', 'section', 'teacher_id']);

        $scope = trim(($subject ?? '').' '.($grade ?? '').($section ? ' / '.$section : '')) ?: 'el colegio';
        if ($courses->isEmpty()) {
            return [
                'message' => "No hay cursos registrados en {$scope}.",
                'data' => ['courses' => []],
            ];
        }

        $lines = $courses->map(fn ($c) => '- '.$c->subject_name.' '.trim($c->grade.($c->section ? ' / '.$c->section : ''))." ({$c->students_count} alumno(s))");

        return [
            'message' => "Cursos en {$scope}:\n".$lines->implode("\n"),
            'data' => ['courses' => $courses, 'count' => $courses->count()],
        ];
    }

    // ─── Rendimiento por clase (get_class_performance) ──────────────────────

    public function getClassPerformance(int $colegioId, string $grade, ?string $section = null, ?string $subject = null): array
    {
        $label = $this->gradeLabel($grade, $section, $subject);

        $students = Student::query()
            ->where('colegio_id', $colegioId)
            ->whereRaw('LOWER(grade) = ?', [$this->key($grade)])
            ->when($section, fn ($q) => $q->whereRaw('LOWER(COALESCE(section, ?)) = ?', ['', $this->key($section)]))
            ->orderBy('name')
            ->get(['id', 'name']);

        if ($students->isEmpty()) {
            return [
                'message' => "No hay alumnos registrados en {$label}.",
                'data' => ['students' => []],
            ];
        }

        $courseIds = $this->courseIdsFor($colegioId, $grade, $section, $subject);
        if ($courseIds !== null && $courseIds->isEmpty()) {
            return [
                'message' => "No hay cursos que coincidan con {$label}; no puedo calcular rendimiento sin inventar datos.",
                'data' => ['students' => [], 'course_matches' => false],
            ];
        }

        $rows = Grade::query()
            ->join('activities', 'grades.activity_id', '=', 'activities.id')
            ->join('students', 'grades.student_id', '=', 'students.id')
            ->where('grades.colegio_id', $colegioId)
            ->whereIn('grades.student_id', $students->pluck('id')->all())
            ->when($courseIds !== null, fn ($q) => $q->whereIn('activities.course_id', $courseIds->all()))
            ->whereNotNull('grades.score')
            ->groupBy('students.id', 'students.name')
            ->selectRaw('students.id, students.name, '.self::AVG_PCT.' as avg_pct, COUNT(grades.id) as grade_count')
            ->orderByDesc('avg_pct')
            ->get();

        if ($rows->isEmpty()) {
            return [
                'message' => "Hay {$students->count()} alumno(s) en {$label}, pero todavía no hay calificaciones registradas para calcular el rendimiento. Puedo crear una evaluación cuando quieras.",
                'data' => ['students' => [], 'students_without_grades' => $students->pluck('name')],
            ];
        }

        $classAvg = round($rows->avg('avg_pct'), 1);
        $table = $this->markdownTable(
            ['#', 'Alumno', 'Promedio', 'Evaluaciones'],
            $rows->values()->map(fn ($r, $i) => [
                $this->ordinal($i + 1),
                $r->name,
                round($r->avg_pct, 1).'%',
                (string) $r->grade_count,
            ])->all()
        );

        return [
            'message' => "Rendimiento de {$label} ({$rows->count()} alumno(s) con notas, promedio general {$classAvg}%):\n".$table,
            'data' => ['students' => $rows, 'class_avg_pct' => $classAvg],
        ];
    }

    // ─── Rendimiento de un alumno (get_student_performance) ─────────────────

    public function getStudentPerformance(int $colegioId, string $studentName): array
    {
        $match = $this->matcher->resolveStudent($colegioId, $studentName);
        if (! $match->isUnique()) {
            return [
                'message' => $match->message ?? "No encontré al alumno {$studentName} en este colegio.",
                'data' => ['students' => []],
            ];
        }

        /** @var Student $student */
        $student = $match->model;

        $rows = Grade::query()
            ->join('activities', 'grades.activity_id', '=', 'activities.id')
            ->join('courses', 'activities.course_id', '=', 'courses.id')
            ->where('grades.colegio_id', $colegioId)
            ->where('grades.student_id', $student->id)
            ->whereNotNull('grades.score')
            ->groupBy('courses.subject_name')
            ->selectRaw('courses.subject_name, '.self::AVG_PCT.' as avg_pct, COUNT(grades.id) as grade_count')
            ->orderByDesc('avg_pct')
            ->get();

        if ($rows->isEmpty()) {
            return [
                'message' => "{$student->name} está registrado en {$student->grade}, pero todavía no tiene calificaciones publicadas. No puedo inventar su rendimiento; puedo ayudarte a registrar evaluaciones.",
                'data' => ['student' => $student->name, 'subjects' => []],
            ];
        }

        $overall = round($rows->avg('avg_pct'), 1);
        $table = $this->markdownTable(
            ['Materia', 'Promedio', 'Evaluaciones'],
            $rows->map(fn ($r) => [
                $r->subject_name,
                round($r->avg_pct, 1).'%',
                (string) $r->grade_count,
            ])->all()
        );

        return [
            'message' => "Rendimiento de {$student->name} ({$student->grade}) — promedio general {$overall}%:\n".$table,
            'data' => ['student' => $student->name, 'subjects' => $rows, 'overall_avg_pct' => $overall],
        ];
    }

    // ─── Asistencia (get_attendance) ────────────────────────────────────────

    public function getAttendance(int $colegioId, ?string $grade = null, ?string $section = null, ?string $studentName = null, int $days = 30): array
    {
        $days = max(1, min($days, 180));
        $since = now()->subDays($days)->toDateString();

        $query = Attendance::query()
            ->join('students', 'attendances.student_id', '=', 'students.id')
            ->where('attendances.colegio_id', $colegioId)
            ->where('attendances.attended_on', '>=', $since)
            ->when($grade, fn ($q) => $q->whereRaw('LOWER(students.grade) = ?', [$this->key($grade)]))
            ->when($section, fn ($q) => $q->whereRaw('LOWER(COALESCE(students.section, ?)) = ?', ['', $this->key($section)]));

        if ($studentName !== null && trim($studentName) !== '') {
            $match = $this->matcher->resolveStudent($colegioId, $studentName);
            if (! $match->isUnique()) {
                return [
                    'message' => $match->message ?? "No encontré al alumno {$studentName} en este colegio.",
                    'data' => ['students' => []],
                ];
            }
            $query->where('attendances.student_id', $match->model->id);
        }

        $rows = $query
            ->groupBy('students.id', 'students.name')
            ->selectRaw("students.id, students.name,
                SUM(CASE WHEN attendances.status = 'absent' THEN 1 ELSE 0 END) as absences,
                SUM(CASE WHEN attendances.status = 'tardy' THEN 1 ELSE 0 END) as tardies,
                COUNT(attendances.id) as records")
            ->orderByDesc('absences')
            ->limit(20)
            ->get();

        $scope = trim(($grade ?? '').($section ? ' / '.$section : '').($studentName ? ' · '.$studentName : '')) ?: 'el colegio';
        if ($rows->isEmpty()) {
            return [
                'message' => "No hay registros de asistencia en los últimos {$days} días para {$scope}.",
                'data' => ['students' => [], 'days' => $days],
            ];
        }

        $table = $this->markdownTable(
            ['Alumno', 'Faltas', 'Tardanzas', 'Registros'],
            $rows->map(fn ($r) => [$r->name, (string) $r->absences, (string) $r->tardies, (string) $r->records])->all()
        );

        return [
            'message' => "Asistencia de {$scope} (últimos {$days} días):\n".$table,
            'data' => ['students' => $rows, 'days' => $days],
        ];
    }

    // ─── Rankings (get_rankings) ────────────────────────────────────────────

    public function getRankings(int $colegioId, string $metric = 'average', ?string $grade = null, ?string $section = null, ?string $subject = null, int $limit = 5): array
    {
        $limit = max(1, min($limit, 20));
        $metric = in_array($metric, ['average', 'absences'], true) ? $metric : 'average';

        return $metric === 'absences'
            ? $this->rankingByAbsences($colegioId, $grade, $section, $subject, $limit)
            : $this->rankingByAverage($colegioId, $grade, $section, $subject, $limit);
    }

    private function rankingByAverage(int $colegioId, ?string $grade, ?string $section, ?string $subject, int $limit): array
    {
        $courseIds = $this->courseIdsFor($colegioId, $grade, $section, $subject);
        $scope = $this->gradeLabel($grade, $section, $subject);

        $rows = Grade::query()
            ->join('activities', 'grades.activity_id', '=', 'activities.id')
            ->join('students', 'grades.student_id', '=', 'students.id')
            ->where('grades.colegio_id', $colegioId)
            ->when($courseIds !== null, fn ($q) => $q->whereIn('activities.course_id', $courseIds->all()))
            ->when($grade, fn ($q) => $q->whereRaw('LOWER(students.grade) = ?', [$this->key($grade)]))
            ->when($section, fn ($q) => $q->whereRaw('LOWER(COALESCE(students.section, ?)) = ?', ['', $this->key($section)]))
            ->whereNotNull('grades.score')
            ->groupBy('students.id', 'students.name', 'students.grade')
            ->selectRaw('students.name, students.grade, '.self::AVG_PCT.' as avg_pct, COUNT(grades.id) as grade_count')
            ->orderByDesc('avg_pct')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return [
                'message' => "No hay calificaciones registradas en {$scope} para armar el ranking. No voy a inventar posiciones; puedo ayudarte a registrar evaluaciones.",
                'data' => ['ranking' => []],
            ];
        }

        $table = $this->markdownTable(
            ['Puesto', 'Alumno', 'Grado', 'Promedio'],
            $rows->values()->map(fn ($r, $i) => [
                $this->ordinal($i + 1),
                $r->name,
                $r->grade,
                round($r->avg_pct, 1).'%',
            ])->all()
        );

        return [
            'message' => "Ranking por promedio en {$scope}:\n".$table,
            'data' => ['ranking' => $rows, 'metric' => 'average'],
        ];
    }

    private function rankingByAbsences(int $colegioId, ?string $grade, ?string $section, ?string $subject, int $limit): array
    {
        $courseIds = $this->courseIdsFor($colegioId, $grade, $section, $subject);
        $scope = $this->gradeLabel($grade, $section, $subject);

        $rows = Attendance::query()
            ->join('students', 'attendances.student_id', '=', 'students.id')
            ->where('attendances.colegio_id', $colegioId)
            ->where('attendances.status', Attendance::STATUS_ABSENT)
            ->when($courseIds !== null, fn ($q) => $q->whereIn('attendances.course_id', $courseIds->all()))
            ->when($grade, fn ($q) => $q->whereRaw('LOWER(students.grade) = ?', [$this->key($grade)]))
            ->when($section, fn ($q) => $q->whereRaw('LOWER(COALESCE(students.section, ?)) = ?', ['', $this->key($section)]))
            ->groupBy('students.id', 'students.name', 'students.grade')
            ->selectRaw('students.name, students.grade, COUNT(attendances.id) as absences')
            ->orderByDesc('absences')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return [
                'message' => "No hay faltas registradas en {$scope}. Nadie figura en el ranking de inasistencias.",
                'data' => ['ranking' => []],
            ];
        }

        $table = $this->markdownTable(
            ['Puesto', 'Alumno', 'Grado', 'Faltas'],
            $rows->values()->map(fn ($r, $i) => [
                $this->ordinal($i + 1),
                $r->name,
                $r->grade,
                (string) $r->absences,
            ])->all()
        );

        return [
            'message' => "Ranking de faltas en {$scope}:\n".$table,
            'data' => ['ranking' => $rows, 'metric' => 'absences'],
        ];
    }

    // ─── Tendencias semanales (get_trends) ──────────────────────────────────

    public function getTrends(int $colegioId, string $metric = 'average', int $weeks = 4): array
    {
        $weeks = max(2, min($weeks, 12));
        $metric = in_array($metric, ['average', 'absences'], true) ? $metric : 'average';
        $since = now()->subWeeks($weeks)->startOfWeek();

        if ($metric === 'absences') {
            $weekSql = $this->weekBucketSql('attended_on');
            $rows = Attendance::query()
                ->where('colegio_id', $colegioId)
                ->where('status', Attendance::STATUS_ABSENT)
                ->where('attended_on', '>=', $since->toDateString())
                ->selectRaw("{$weekSql} as week, COUNT(*) as total")
                ->groupByRaw($weekSql)
                ->orderBy('week')
                ->get();

            if ($rows->isEmpty()) {
                return [
                    'message' => "No hay faltas registradas en las últimas {$weeks} semanas; no puedo mostrar una tendencia.",
                    'data' => ['trend' => []],
                ];
            }

            $table = $this->markdownTable(
                ['Semana', 'Faltas'],
                $rows->map(fn ($r) => [$this->weekLabel($r->week), (string) $r->total])->all()
            );

            return [
                'message' => "Tendencia de faltas (últimas {$weeks} semanas):\n".$table,
                'data' => ['trend' => $rows, 'metric' => 'absences'],
            ];
        }

        $weekSql = $this->weekBucketSql('grades.created_at');
        $rows = Grade::query()
            ->join('activities', 'grades.activity_id', '=', 'activities.id')
            ->where('grades.colegio_id', $colegioId)
            ->whereNotNull('grades.score')
            ->where('grades.created_at', '>=', $since)
            ->selectRaw("{$weekSql} as week, ".self::AVG_PCT.' as avg_pct, COUNT(grades.id) as total')
            ->groupByRaw($weekSql)
            ->orderBy('week')
            ->get();

        if ($rows->isEmpty()) {
            return [
                'message' => "No hay calificaciones registradas en las últimas {$weeks} semanas; no puedo mostrar una tendencia de promedios.",
                'data' => ['trend' => []],
            ];
        }

        $table = $this->markdownTable(
            ['Semana', 'Promedio', 'Notas'],
            $rows->map(fn ($r) => [$this->weekLabel($r->week), round($r->avg_pct, 1).'%', (string) $r->total])->all()
        );

        return [
            'message' => "Tendencia de promedios (últimas {$weeks} semanas):\n".$table,
            'data' => ['trend' => $rows, 'metric' => 'average'],
        ];
    }

    // ─── Comparación de grados (compare_grades) ─────────────────────────────

    public function compareGrades(int $colegioId, string $gradeA, string $gradeB, ?string $subject = null): array
    {
        $statsA = $this->gradeStats($colegioId, $gradeA, $subject);
        $statsB = $this->gradeStats($colegioId, $gradeB, $subject);
        $scope = $subject ? " en {$subject}" : '';

        if ($statsA['avg_pct'] === null && $statsB['avg_pct'] === null) {
            return [
                'message' => "No hay calificaciones registradas ni en {$gradeA} ni en {$gradeB}{$scope}, así que no puedo compararlos sin inventar datos.",
                'data' => ['comparison' => []],
            ];
        }

        $table = $this->markdownTable(
            ['Indicador', $gradeA, $gradeB],
            [
                ['Alumnos', (string) $statsA['students'], (string) $statsB['students']],
                ['Cursos', (string) $statsA['courses'], (string) $statsB['courses']],
                ['Promedio', $statsA['avg_pct'] !== null ? $statsA['avg_pct'].'%' : 'sin datos', $statsB['avg_pct'] !== null ? $statsB['avg_pct'].'%' : 'sin datos'],
                ['Faltas (30 días)', (string) $statsA['absences'], (string) $statsB['absences']],
            ]
        );

        $verdict = null;
        if ($statsA['avg_pct'] !== null && $statsB['avg_pct'] !== null) {
            $leader = $statsA['avg_pct'] >= $statsB['avg_pct'] ? $gradeA : $gradeB;
            $diff = round(abs($statsA['avg_pct'] - $statsB['avg_pct']), 1);
            $verdict = "Lidera {$leader} por {$diff} puntos porcentuales.";
        }

        return [
            'message' => "Comparación {$gradeA} vs {$gradeB}{$scope}:\n".$table.($verdict ? "\n".$verdict : ''),
            'data' => ['comparison' => ['a' => $statsA, 'b' => $statsB], 'verdict' => $verdict],
        ];
    }

    // ─── Helpers internos ───────────────────────────────────────────────────

    /**
     * IDs de cursos del colegio filtrados; null = sin filtro de curso.
     */
    private function courseIdsFor(int $colegioId, ?string $grade, ?string $section, ?string $subject): ?Collection
    {
        if (! $grade && ! $section && ! $subject) {
            return null;
        }

        return Course::query()
            ->where('colegio_id', $colegioId)
            ->when($grade, fn ($q) => $q->whereRaw('LOWER(grade) = ?', [$this->key($grade)]))
            ->when($section, fn ($q) => $q->whereRaw('LOWER(COALESCE(section, ?)) = ?', ['', $this->key($section)]))
            ->when($subject, fn ($q) => $q->whereRaw('LOWER(subject_name) like ?', ['%'.$this->key($subject).'%']))
            ->pluck('id');
    }

    private function gradeStats(int $colegioId, string $grade, ?string $subject): array
    {
        $courseIds = Course::query()
            ->where('colegio_id', $colegioId)
            ->whereRaw('LOWER(grade) = ?', [$this->key($grade)])
            ->when($subject, fn ($q) => $q->whereRaw('LOWER(subject_name) like ?', ['%'.$this->key($subject).'%']))
            ->pluck('id');

        $students = Student::query()
            ->where('colegio_id', $colegioId)
            ->whereRaw('LOWER(grade) = ?', [$this->key($grade)])
            ->count();

        $avg = $courseIds->isEmpty()
            ? null
            : Grade::query()
                ->join('activities', 'grades.activity_id', '=', 'activities.id')
                ->where('grades.colegio_id', $colegioId)
                ->whereIn('activities.course_id', $courseIds->all())
                ->whereNotNull('grades.score')
                ->selectRaw(self::AVG_PCT.' as avg_pct')
                ->value('avg_pct');

        $absences = Attendance::query()
            ->join('students', 'attendances.student_id', '=', 'students.id')
            ->where('attendances.colegio_id', $colegioId)
            ->where('attendances.status', Attendance::STATUS_ABSENT)
            ->where('attendances.attended_on', '>=', now()->subDays(30)->toDateString())
            ->whereRaw('LOWER(students.grade) = ?', [$this->key($grade)])
            ->count();

        return [
            'grade' => $grade,
            'students' => $students,
            'courses' => $courseIds->count(),
            'avg_pct' => $avg !== null ? round($avg, 1) : null,
            'absences' => $absences,
        ];
    }

    private function key(?string $value): string
    {
        $value = mb_strtolower(trim((string) $value));

        return strtr($value, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
        ]);
    }

    private function gradeLabel(?string $grade, ?string $section, ?string $subject): string
    {
        $parts = [];
        if ($grade) {
            $parts[] = $grade;
        }
        if ($section) {
            $parts[] = 'sección '.$section;
        }
        if ($subject) {
            $parts[] = $subject;
        }

        return $parts !== [] ? implode(' ', $parts) : 'el colegio';
    }

    private function ordinal(int $position): string
    {
        return $position.'º';
    }

    private function weekLabel(?string $week): string
    {
        return $week !== null && $week !== '' ? 'Semana '.$week : 'Semana sin fecha';
    }

    /**
     * Bucket semanal portable (SQLite en tests, Postgres en producción).
     * Solo se interpolan nombres de columna internos, nunca input del usuario.
     */
    private function weekBucketSql(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'pgsql' => "to_char({$column}::timestamp, 'IYYY-IW')",
            'mysql' => "DATE_FORMAT({$column}, '%Y-%u')",
            default => "strftime('%Y-%W', {$column})",
        };
    }

    /**
     * @param  array<int,string>  $headers
     * @param  array<int,array<int,string>>  $rows
     */
    private function markdownTable(array $headers, array $rows): string
    {
        $headerLine = '| '.implode(' | ', $headers).' |';
        $separator = '|'.str_repeat('---|', count($headers));
        $body = collect($rows)
            ->map(fn ($row) => '| '.implode(' | ', $row).' |')
            ->implode("\n");

        return "\n".$headerLine."\n".$separator."\n".$body;
    }
}

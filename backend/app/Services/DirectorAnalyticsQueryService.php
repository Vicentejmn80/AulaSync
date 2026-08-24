<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Attendance;
use App\Models\Colegio;
use App\Models\Course;
use App\Models\Evaluation;
use App\Models\Grade;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
            ->with(['courses' => fn ($q) => $q->where('colegio_id', $colegioId)->orderBy('grade')->orderBy('subject_name')->select('id', 'teacher_id', 'subject_name', 'grade', 'section')])
            ->orderBy('name')
            ->limit(80)
            ->get(['id', 'name']);

        if ($teachers->isEmpty()) {
            return [
                'message' => 'No hay profesores registrados en el colegio todavía.',
                'data' => ['teachers' => []],
            ];
        }

        $teachers->each(function ($teacher) {
            $teacher->course_names = $teacher->courses
                ->map(fn ($c) => trim($c->subject_name.' '.$c->grade.($c->section ? ' '.$c->section : '')))
                ->filter()
                ->unique()
                ->values()
                ->implode('; ') ?: 'sin cursos asignados';
        });

        $lines = $teachers->map(fn ($t) => '- '.$t->name.': '.$t->course_names);

        $count = $teachers->count();

        return [
            'message' => "Hay {$count} profesor(es):\n".$lines->implode("\n"),
            'data' => [
                'teachers' => $teachers,
                'count' => $count,
                'teachers_count' => $count,
            ],
        ];
    }

    public function getStudent(int $colegioId, string $studentName): array
    {
        $match = $this->matcher->resolveStudent($colegioId, $studentName);
        if (! $match->isUnique()) {
            return [
                'message' => $match->message ?? "No encontré al alumno {$studentName} en este colegio.",
                'data' => ['student' => null],
            ];
        }

        /** @var Student $student */
        $student = $match->model;
        $teacherNames = $student->courses()->with('teacher:id,name')->limit(3)->get()->pluck('teacher.name')->filter()->unique()->values();
        // Fallback: si no está matriculado, buscar profesor del curso de su grado/sección
        if ($teacherNames->isEmpty() && $student->grade) {
            $teacherNames = Course::where('colegio_id', $colegioId)
                ->whereRaw('LOWER(grade) = ?', [mb_strtolower($student->grade)])
                ->when($student->section, fn($q) => $q->whereRaw('LOWER(COALESCE(section, ?)) = ?', ['', mb_strtolower($student->section)]))
                ->with('teacher:id,name')
                ->get()
                ->pluck('teacher.name')
                ->filter()
                ->unique()
                ->values();
        }
        $teacherText = $teacherNames->isNotEmpty() ? ' Su profesor es '.$teacherNames->implode(', ').'.' : '';

        return [
            'message' => "{$student->name} está en {$student->grade}".($student->section ? ' / '.$student->section : '').'.'.$teacherText,
            'data' => [
                'student' => $student->only(['id', 'name', 'grade', 'section']),
                'teachers' => $teacherNames->all(),
            ],
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

    public function getSchoolInfo(int $colegioId): array
    {
        $colegio = Colegio::query()->find($colegioId);
        if (! $colegio) {
            return [
                'message' => 'Tu usuario de director no está vinculado a un colegio registrado.',
                'data' => [],
            ];
        }

        return [
            'message' => "Tu colegio se llama {$colegio->name}.",
            'data' => ['school_name' => $colegio->name],
        ];
    }

    public function getMostAdvancedCourse(int $colegioId): array
    {
        $courses = Course::query()
            ->where('colegio_id', $colegioId)
            ->orderBy('grade')
            ->orderBy('subject_name')
            ->get(['subject_name', 'grade', 'section']);

        if ($courses->isEmpty()) {
            return [
                'message' => 'Todavía no hay cursos registrados en el colegio.',
                'data' => ['courses' => []],
            ];
        }

        $topGrade = $courses->sortByDesc(fn ($course) => $this->gradeNumber((string) $course->grade))->first()?->grade;
        if (! is_string($topGrade) || $topGrade === '') {
            return [
                'message' => 'Hay cursos registrados, pero no pude determinar el grado más avanzado.',
                'data' => ['courses' => $courses],
            ];
        }

        $topCourses = $courses->filter(fn ($course) => $this->key((string) $course->grade) === $this->key($topGrade))->values();
        $labels = $topCourses
            ->map(fn ($course) => trim($course->subject_name.' '.$course->grade.($course->section ? ' '.$course->section : '')))
            ->unique()
            ->implode(', ');

        return [
            'message' => "El grado más avanzado registrado es {$topGrade}. Cursos: {$labels}.",
            'data' => [
                'top_grade' => $topGrade,
                'courses' => $topCourses,
            ],
        ];
    }

    public function getSectionCounts(int $colegioId): array
    {
        $rows = Student::query()
            ->where('colegio_id', $colegioId)
            ->selectRaw('grade, section, COUNT(*) as total')
            ->groupBy('grade', 'section')
            ->orderBy('grade')
            ->orderBy('section')
            ->get();

        if ($rows->isEmpty()) {
            return [
                'message' => 'No hay alumnos registrados todavía, así que no puedo contar por sección.',
                'data' => ['sections' => []],
            ];
        }

        $table = $this->markdownTable(
            ['Grado', 'Sección', 'Alumnos'],
            $rows->map(fn ($r) => [$r->grade, $r->section ?: 's/sec', (string) $r->total])->all()
        );

        $total = (int) $rows->sum('total');

        return [
            'message' => "Alumnos por grado y sección ({$total} en total):\n".$table,
            'data' => ['sections' => $rows, 'total' => $total],
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
                'data' => ['students' => [], 'grade' => $grade, 'section' => $section, 'students_count' => 0],
            ];
        }

        $courseIds = $this->courseIdsFor($colegioId, $grade, $section, $subject);
        if ($courseIds !== null && $courseIds->isEmpty()) {
            return [
                'message' => "No hay cursos que coincidan con {$label}; no puedo calcular rendimiento sin inventar datos.",
                'data' => ['students' => [], 'course_matches' => false, 'grade' => $grade, 'section' => $section, 'students_count' => $students->count()],
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
                'data' => [
                    'students' => [],
                    'students_without_grades' => $students->pluck('name'),
                    'grade' => $grade,
                    'section' => $section,
                    'students_count' => $students->count(),
                ],
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
            'data' => [
                'students' => $rows,
                'class_avg_pct' => $classAvg,
                'grade' => $grade,
                'section' => $section,
                'students_count' => $students->count(),
            ],
        ];
    }

    // ─── Rendimiento de un alumno (get_student_performance) ─────────────────

    public function getStudentPerformance(int $colegioId, string $studentName, ?string $subject = null): array
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

        if ($subject) {
            $needle = $this->key($subject);
            $rows = $rows->filter(fn ($row) => str_contains($this->key((string) $row->subject_name), $needle))->values();
        }

        if ($rows->isEmpty()) {
            return [
                'message' => $subject
                    ? "{$student->name} está registrado en {$student->grade}, pero no tiene calificaciones publicadas en {$subject}."
                    : "{$student->name} está registrado en {$student->grade}, pero todavía no tiene calificaciones publicadas. No puedo inventar su rendimiento; puedo ayudarte a registrar evaluaciones.",
                'data' => ['student' => $student->name, 'subjects' => []],
            ];
        }

        $overall = round($rows->avg('avg_pct'), 1);
        $teachers = Course::query()
            ->where('colegio_id', $colegioId)
            ->with('teacher')
            ->get(['id', 'teacher_id', 'subject_name', 'grade', 'section']);
        $gradeKey = $this->key((string) $student->grade);
        $sectionKey = $this->key((string) ($student->section ?? ''));
        $teacherBySubject = [];
        foreach ($teachers as $course) {
            if ($this->key((string) $course->grade) !== $gradeKey) {
                continue;
            }
            if ($sectionKey !== '' && $this->key((string) ($course->section ?? '')) !== $sectionKey) {
                continue;
            }
            $key = $this->key((string) $course->subject_name);
            if (! isset($teacherBySubject[$key]) && $course->teacher) {
                $teacherBySubject[$key] = $course->teacher->name;
            }
        }
        $rows->each(function ($row) use ($teacherBySubject) {
            $row->teacher_name = $teacherBySubject[$this->key((string) $row->subject_name)] ?? null;
        });
        $worst = $rows->sortBy('avg_pct')->first();
        $fallbackTeacher = $student->teacher_id
            ? User::query()->whereKey($student->teacher_id)->value('name')
            : ($teachers->first()?->teacher?->name);
        $table = $this->markdownTable(
            ['Materia', 'Promedio', 'Evaluaciones'],
            $rows->map(fn ($r) => [
                $r->subject_name,
                round($r->avg_pct, 1).'%',
                (string) $r->grade_count,
            ])->all()
        );
        $scope = $subject ? " en {$subject}" : '';

        return [
            'message' => "Rendimiento de {$student->name} ({$student->grade}){$scope} — promedio general {$overall}%:\n".$table,
            'data' => [
                'student' => $student->name,
                'grade' => $student->grade,
                'section' => $student->section,
                'subjects' => $rows,
                'overall_avg_pct' => $overall,
                'worst_subject' => $worst->subject_name ?? null,
                'worst_teacher' => $worst->teacher_name ?? $fallbackTeacher,
            ],
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

    public function getCoursePerformance(int $colegioId, string $grade, ?string $section = null, ?string $subject = null): array
    {
        return $this->getClassPerformance($colegioId, $grade, $section, $subject);
    }

    public function getAcademicTrends(int $colegioId, string $metric = 'average', int $weeks = 4): array
    {
        return $this->getTrends($colegioId, $metric, $weeks);
    }

    public function getGrades(int $colegioId, ?string $grade = null, ?string $section = null, ?string $studentName = null, ?string $subject = null, int $limit = 30): array
    {
        $limit = max(1, min($limit, 80));
        $courseIds = $this->courseIdsFor($colegioId, $grade, $section, $subject);

        $query = Grade::query()
            ->join('activities', 'grades.activity_id', '=', 'activities.id')
            ->join('students', 'grades.student_id', '=', 'students.id')
            ->join('courses', 'activities.course_id', '=', 'courses.id')
            ->where('grades.colegio_id', $colegioId)
            ->whereNotNull('grades.score')
            ->when($courseIds !== null, fn ($q) => $q->whereIn('activities.course_id', $courseIds->all()))
            ->when($grade, fn ($q) => $q->whereRaw('LOWER(students.grade) = ?', [$this->key($grade)]))
            ->when($section, fn ($q) => $q->whereRaw('LOWER(COALESCE(students.section, ?)) = ?', ['', $this->key($section)]));

        if ($studentName !== null && trim($studentName) !== '') {
            $match = $this->matcher->resolveStudent($colegioId, $studentName);
            if (! $match->isUnique()) {
                return [
                    'message' => $match->message ?? "No encontré al alumno {$studentName} en este colegio.",
                    'data' => ['grades' => []],
                ];
            }
            $query->where('grades.student_id', $match->model->id);
        }

        $rows = $query
            ->orderByDesc('grades.created_at')
            ->limit($limit)
            ->get([
                'students.name as student_name',
                'courses.subject_name',
                'courses.grade',
                'activities.title as activity_title',
                'grades.score',
                'activities.max_score',
            ]);

        $scope = $this->gradeLabel($grade, $section, $subject);
        if ($rows->isEmpty()) {
            return [
                'message' => "No hay calificaciones registradas en {$scope}.",
                'data' => ['grades' => []],
            ];
        }

        $table = $this->markdownTable(
            ['Alumno', 'Materia', 'Actividad', 'Nota'],
            $rows->map(fn ($r) => [
                $r->student_name,
                $r->subject_name,
                $r->activity_title ?: 'Sin título',
                $this->scoreLabel($r->score, $r->max_score),
            ])->all()
        );

        return [
            'message' => "Calificaciones recientes en {$scope}:\n".$table,
            'data' => ['grades' => $rows, 'count' => $rows->count()],
        ];
    }

    public function getEvaluations(int $colegioId, ?string $grade = null, ?string $section = null, ?string $subject = null): array
    {
        if (! Schema::hasTable('evaluations')) {
            return [
                'message' => 'Todavía no hay un módulo de evaluaciones disponible para consultar.',
                'data' => ['evaluations' => []],
            ];
        }

        $courseIds = $this->courseIdsFor($colegioId, $grade, $section, $subject);
        $rows = Evaluation::query()
            ->leftJoin('courses', 'evaluations.course_id', '=', 'courses.id')
            ->where('evaluations.colegio_id', $colegioId)
            ->when($courseIds !== null, fn ($q) => $q->whereIn('evaluations.course_id', $courseIds->all()))
            ->orderByDesc('evaluations.scheduled_at')
            ->orderByDesc('evaluations.id')
            ->limit(40)
            ->get([
                'evaluations.title',
                'evaluations.status',
                'evaluations.scheduled_at',
                'courses.subject_name',
                'courses.grade',
                'courses.section',
            ]);

        $scope = $this->gradeLabel($grade, $section, $subject);
        if ($rows->isEmpty()) {
            return [
                'message' => "No hay evaluaciones registradas en {$scope}.",
                'data' => ['evaluations' => []],
            ];
        }

        $table = $this->markdownTable(
            ['Evaluación', 'Curso', 'Estado'],
            $rows->map(fn ($r) => [
                $r->title,
                trim(($r->subject_name ?? '').' '.($r->grade ?? '').($r->section ? ' / '.$r->section : '')) ?: 'sin curso',
                $r->status ?: 'sin estado',
            ])->all()
        );

        return [
            'message' => "Evaluaciones en {$scope}:\n".$table,
            'data' => ['evaluations' => $rows, 'count' => $rows->count()],
        ];
    }

    public function getAssignments(int $colegioId, ?string $grade = null, ?string $section = null, ?string $subject = null, bool $pendingOnly = false): array
    {
        $courseIds = $this->courseIdsFor($colegioId, $grade, $section, $subject);
        $rows = Activity::query()
            ->join('courses', 'activities.course_id', '=', 'courses.id')
            ->where('courses.colegio_id', $colegioId)
            ->where(function ($q) {
                $q->where('activities.type', Activity::TYPE_TAREA)
                    ->orWhere('activities.is_homework', 1);
            })
            ->when($courseIds !== null, fn ($q) => $q->whereIn('activities.course_id', $courseIds->all()))
            ->when($pendingOnly, fn ($q) => $q->where(function ($pending) {
                $pending->whereNull('activities.due_date')
                    ->orWhereDate('activities.due_date', '>=', now()->toDateString());
            }))
            ->orderByDesc('activities.due_date')
            ->orderByDesc('activities.id')
            ->limit(40)
            ->get([
                'activities.title',
                'activities.due_date',
                'courses.subject_name',
                'courses.grade',
                'courses.section',
            ]);

        $scope = $this->gradeLabel($grade, $section, $subject);
        if ($rows->isEmpty()) {
            return [
                'message' => "No hay tareas registradas en {$scope}.",
                'data' => ['assignments' => []],
            ];
        }

        $table = $this->markdownTable(
            ['Tarea', 'Curso', 'Entrega'],
            $rows->map(fn ($r) => [
                $r->title,
                trim($r->subject_name.' '.$r->grade.($r->section ? ' / '.$r->section : '')),
                $r->due_date ? (string) $r->due_date : 'sin fecha',
            ])->all()
        );

        return [
            'message' => "Tareas en {$scope}:\n".$table,
            'data' => ['assignments' => $rows, 'count' => $rows->count()],
        ];
    }

    public function getAtRiskStudents(int $colegioId, ?string $grade = null, ?string $section = null, ?string $subject = null, float $threshold = 60): array
    {
        $threshold = max(1, min(100, $threshold));
        $courseIds = $this->courseIdsFor($colegioId, $grade, $section, $subject);
        $rows = Grade::query()
            ->join('activities', 'grades.activity_id', '=', 'activities.id')
            ->join('students', 'grades.student_id', '=', 'students.id')
            ->join('courses', 'activities.course_id', '=', 'courses.id')
            ->where('grades.colegio_id', $colegioId)
            ->whereNotNull('grades.score')
            ->when($courseIds !== null, fn ($q) => $q->whereIn('activities.course_id', $courseIds->all()))
            ->when($grade, fn ($q) => $q->whereRaw('LOWER(students.grade) = ?', [$this->key($grade)]))
            ->when($section, fn ($q) => $q->whereRaw('LOWER(COALESCE(students.section, ?)) = ?', ['', $this->key($section)]))
            ->groupBy('students.id', 'students.name', 'students.grade', 'courses.subject_name')
            ->selectRaw('students.name, students.grade, courses.subject_name, '.self::AVG_PCT.' as avg_pct, COUNT(grades.id) as grade_count')
            ->having('avg_pct', '<', $threshold)
            ->orderBy('avg_pct')
            ->limit(12)
            ->get()
            ->filter(fn ($row) => (float) $row->avg_pct < $threshold)
            ->values();

        $scope = $this->gradeLabel($grade, $section, $subject);
        if ($rows->isEmpty()) {
            return [
                'message' => "Ningún alumno de {$scope} está por debajo de {$threshold}% con las calificaciones registradas.",
                'data' => ['students' => [], 'threshold' => $threshold],
            ];
        }

        $table = $this->markdownTable(
            ['Alumno', 'Grado', 'Materia', 'Promedio'],
            $rows->map(fn ($r) => [
                $r->name,
                $r->grade,
                $r->subject_name,
                round($r->avg_pct, 1).'%',
            ])->all()
        );

        return [
            'message' => "Alumnos con menor rendimiento registrado en {$scope} (por debajo de {$threshold}%):\n".$table,
            'data' => ['students' => $rows, 'threshold' => $threshold],
        ];
    }

    public function getDecliningStudents(int $colegioId, ?string $grade = null, ?string $section = null): array
    {
        $rows = Grade::query()
            ->join('activities', 'grades.activity_id', '=', 'activities.id')
            ->join('students', 'grades.student_id', '=', 'students.id')
            ->where('grades.colegio_id', $colegioId)
            ->whereNotNull('grades.score')
            ->when($grade, fn ($q) => $q->whereRaw('LOWER(students.grade) = ?', [$this->key($grade)]))
            ->when($section, fn ($q) => $q->whereRaw('LOWER(COALESCE(students.section, ?)) = ?', ['', $this->key($section)]))
            ->orderBy('grades.student_id')
            ->orderBy('grades.created_at')
            ->get([
                'students.id as student_id',
                'students.name',
                'students.grade',
                'grades.score',
                'activities.max_score',
                'grades.created_at',
            ]);

        $declines = [];
        foreach ($rows->groupBy('student_id') as $group) {
            if ($group->count() < 2) {
                continue;
            }
            $mid = (int) floor($group->count() / 2);
            $older = $group->take($mid);
            $newer = $group->slice($mid);
            $oldAvg = $this->avgPercent($older);
            $newAvg = $this->avgPercent($newer);
            if ($oldAvg === null || $newAvg === null) {
                continue;
            }
            $drop = round($oldAvg - $newAvg, 1);
            if ($drop < 5) {
                continue;
            }
            $first = $group->first();
            $declines[] = [
                'name' => $first->name,
                'grade' => $first->grade,
                'previous_avg' => $oldAvg,
                'recent_avg' => $newAvg,
                'drop' => $drop,
            ];
        }

        usort($declines, fn ($a, $b) => $b['drop'] <=> $a['drop']);
        $declines = array_slice($declines, 0, 12);
        $scope = $this->gradeLabel($grade, $section, null);

        if ($declines === []) {
            return [
                'message' => "No hay suficientes calificaciones sucesivas para detectar bajadas de promedio en {$scope}.",
                'data' => ['students' => []],
            ];
        }

        $table = $this->markdownTable(
            ['Alumno', 'Antes', 'Ahora', 'Bajó'],
            collect($declines)->map(fn ($r) => [
                $r['name'],
                $r['previous_avg'].'%',
                $r['recent_avg'].'%',
                $r['drop'].' pts',
            ])->all()
        );

        return [
            'message' => "Alumnos que bajaron su promedio en {$scope}:\n".$table,
            'data' => ['students' => $declines],
        ];
    }

    // ─── Comparación de grados (compare_grades) ─────────────────────────────

    public function compareGrades(int $colegioId, string $gradeA, string $gradeB, ?string $subject = null): array
    {
        return $this->compareCourses($colegioId, $gradeA, $gradeB, null, null, $subject);
    }

    public function compareCourses(
        int $colegioId,
        string $gradeA,
        string $gradeB,
        ?string $sectionA = null,
        ?string $sectionB = null,
        ?string $subject = null,
    ): array {
        $statsA = $this->gradeStats($colegioId, $gradeA, $subject, $sectionA);
        $statsB = $this->gradeStats($colegioId, $gradeB, $subject, $sectionB);
        $labelA = trim($gradeA.($sectionA ? ' '.$sectionA : ''));
        $labelB = trim($gradeB.($sectionB ? ' '.$sectionB : ''));
        $scope = $subject ? " en {$subject}" : '';

        if ($statsA['avg_pct'] === null && $statsB['avg_pct'] === null) {
            return [
                'message' => "No hay calificaciones registradas ni en {$labelA} ni en {$labelB}{$scope}, así que no puedo compararlos sin inventar datos.",
                'data' => ['comparison' => []],
            ];
        }

        $table = $this->markdownTable(
            ['Indicador', $labelA, $labelB],
            [
                ['Alumnos', (string) $statsA['students'], (string) $statsB['students']],
                ['Cursos', (string) $statsA['courses'], (string) $statsB['courses']],
                ['Promedio', $statsA['avg_pct'] !== null ? $statsA['avg_pct'].'%' : 'sin datos', $statsB['avg_pct'] !== null ? $statsB['avg_pct'].'%' : 'sin datos'],
                ['Faltas (30 días)', (string) $statsA['absences'], (string) $statsB['absences']],
            ]
        );

        $verdict = null;
        if ($statsA['avg_pct'] !== null && $statsB['avg_pct'] !== null) {
            $leader = $statsA['avg_pct'] >= $statsB['avg_pct'] ? $labelA : $labelB;
            $diff = round(abs($statsA['avg_pct'] - $statsB['avg_pct']), 1);
            $verdict = "Lidera {$leader} por {$diff} puntos porcentuales.";
        }

        return [
            'message' => "Comparación {$labelA} vs {$labelB}{$scope}:\n".$table.($verdict ? "\n".$verdict : ''),
            'data' => ['comparison' => ['a' => $statsA, 'b' => $statsB], 'verdict' => $verdict],
        ];
    }

    public function generateSchoolReport(int $colegioId, ?string $grade = null, ?string $section = null): array
    {
        $studentCount = Student::query()
            ->where('colegio_id', $colegioId)
            ->when($grade, fn ($q) => $q->whereRaw('LOWER(grade) = ?', [$this->key($grade)]))
            ->when($section, fn ($q) => $q->whereRaw('LOWER(COALESCE(section, ?)) = ?', ['', $this->key($section)]))
            ->count();
        $courseCount = Course::query()
            ->where('colegio_id', $colegioId)
            ->when($grade, fn ($q) => $q->whereRaw('LOWER(grade) = ?', [$this->key($grade)]))
            ->when($section, fn ($q) => $q->whereRaw('LOWER(COALESCE(section, ?)) = ?', ['', $this->key($section)]))
            ->count();
        $teacherCount = $grade ? 0 : User::query()
            ->where('colegio_id', $colegioId)
            ->where('role', 'profesor')
            ->count();

        $atRisk = $this->getAtRiskStudents($colegioId, $grade, $section);
        $attendance = $this->getAttendance($colegioId, $grade, $section);
        $performance = $grade
            ? $this->getClassPerformance($colegioId, $grade, $section)
            : $this->getRankings($colegioId, 'average', null, null, null, 5);
        $trends = $grade
            ? ['message' => null, 'data' => ['trend' => []]]
            : $this->getTrends($colegioId, 'average', 4);

        $scope = $this->gradeLabel($grade, $section, null);
        $riskRows = collect($atRisk['data']['students'] ?? []);
        $riskCount = $riskRows->map(fn ($row) => is_array($row) ? ($row['name'] ?? '') : ($row->name ?? ''))->filter()->unique()->count();
        $absenceRows = collect($attendance['data']['students'] ?? []);
        $absenceTotal = (int) $absenceRows->sum(fn ($row) => (int) (is_array($row) ? ($row['absences'] ?? 0) : ($row->absences ?? 0)));
        $criticalSubject = $riskRows
            ->map(fn ($row) => is_array($row) ? ($row['subject_name'] ?? null) : ($row->subject_name ?? null))
            ->filter()
            ->countBy()
            ->sortDesc()
            ->keys()
            ->first();
        $priorityScope = $riskRows
            ->map(function ($row) {
                $g = is_array($row) ? ($row['grade'] ?? '') : ($row->grade ?? '');
                $s = is_array($row) ? ($row['section'] ?? '') : ($row->section ?? '');

                return trim($g.' '.$s);
            })
            ->filter()
            ->countBy()
            ->sortDesc()
            ->keys()
            ->first();

        $courseIds = $this->courseIdsFor($colegioId, $grade, $section, null);
        $schoolAvg = Grade::query()
            ->join('activities', 'grades.activity_id', '=', 'activities.id')
            ->where('grades.colegio_id', $colegioId)
            ->whereNotNull('grades.score')
            ->when($courseIds !== null, fn ($q) => $q->whereIn('activities.course_id', $courseIds->all()))
            ->selectRaw(self::AVG_PCT.' as avg_pct')
            ->value('avg_pct');
        $schoolAvg = $schoolAvg !== null ? round((float) $schoolAvg, 1) : ($performance['data']['class_avg_pct'] ?? null);

        $parts = [
            $studentCount === 0
                ? "No hay alumnos registrados en {$scope}."
                : "{$studentCount} alumno(s) y {$courseCount} curso(s) en {$scope}.",
            $grade ? null : ($teacherCount === 0 ? 'No hay profesores registrados.' : "{$teacherCount} profesor(es) activos."),
            $schoolAvg !== null ? "Promedio general {$schoolAvg}%." : null,
            $this->withoutReportTable((string) ($performance['message'] ?? '')),
            $riskCount === 0
                ? 'Ningún alumno figura por debajo de 60%.'
                : "{$riskCount} alumno(s) por debajo de 60%.",
            $absenceRows->isEmpty()
                ? 'Sin registros de asistencia en los últimos 30 días.'
                : "{$absenceTotal} falta(s) registradas en los últimos 30 días.",
            $trends['message'] ? $this->withoutReportTable((string) $trends['message']) : null,
        ];

        return [
            'message' => "Informe académico de {$scope}:\n\n".collect($parts)->filter()->implode("\n"),
            'data' => [
                'students' => ['count' => $studentCount],
                'teachers' => $grade ? [] : ['count' => $teacherCount],
                'courses' => ['count' => $courseCount],
                'performance' => $performance['data'] ?? [],
                'at_risk' => $atRisk['data'] ?? [],
                'attendance' => $attendance['data'] ?? [],
                'trends' => $trends['data'] ?? [],
                'school_avg_pct' => $schoolAvg,
                'critical_subject' => $criticalSubject,
                'priority_scope' => $priorityScope ?: $scope,
                'risk_count' => $riskCount,
            ],
        ];
    }

    private function withoutReportTable(string $text): string
    {
        $cut = preg_split('/\n(?=\|)/', $text, 2);

        return trim((string) ($cut[0] ?? $text));
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

        $ids = Course::query()
            ->where('colegio_id', $colegioId)
            ->when($grade, fn ($q) => $q->whereRaw('LOWER(grade) = ?', [$this->key($grade)]))
            ->when($section, fn ($q) => $q->whereRaw('LOWER(COALESCE(section, ?)) = ?', ['', $this->key($section)]))
            ->get(['id', 'subject_name']);

        if ($subject) {
            $needle = $this->key($subject);
            $ids = $ids->filter(fn ($course) => str_contains($this->key((string) $course->subject_name), $needle));
        }

        return $ids->pluck('id');
    }

    private function gradeStats(int $colegioId, string $grade, ?string $subject, ?string $section = null): array
    {
        $courseIds = $this->courseIdsFor($colegioId, $grade, $section, $subject) ?? collect();

        $students = Student::query()
            ->where('colegio_id', $colegioId)
            ->whereRaw('LOWER(grade) = ?', [$this->key($grade)])
            ->when($section, fn ($q) => $q->whereRaw('LOWER(COALESCE(section, ?)) = ?', ['', $this->key($section)]))
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
            ->when($section, fn ($q) => $q->whereRaw('LOWER(COALESCE(students.section, ?)) = ?', ['', $this->key($section)]))
            ->count();

        return [
            'grade' => $grade,
            'section' => $section,
            'students' => $students,
            'courses' => $courseIds->count(),
            'avg_pct' => $avg !== null ? round((float) $avg, 1) : null,
            'absences' => $absences,
        ];
    }

    private function avgPercent(Collection $rows): ?float
    {
        $values = $rows->map(function ($row) {
            $max = (float) ($row->max_score ?? 0);
            if ($max <= 0) {
                return null;
            }

            return ((float) $row->score) * 100 / $max;
        })->filter(fn ($v) => $v !== null);

        return $values->isEmpty() ? null : round((float) $values->avg(), 1);
    }

    private function scoreLabel(mixed $score, mixed $maxScore): string
    {
        $max = (float) $maxScore;
        if ($max <= 0) {
            return (string) $score;
        }

        return round(((float) $score) * 100 / $max, 1).'%';
    }

    private function key(?string $value): string
    {
        $value = mb_strtolower(trim((string) $value));

        return strtr($value, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
        ]);
    }

    private function gradeNumber(?string $grade): int
    {
        if ($grade === null || trim($grade) === '') {
            return 0;
        }

        $value = $this->key($grade);
        if (preg_match('/^(\d)/', $value, $match)) {
            return (int) $match[1];
        }

        return match (true) {
            str_contains($value, 'sexto') || str_contains($value, '6to') => 6,
            str_contains($value, 'quinto') || str_contains($value, '5to') => 5,
            str_contains($value, 'cuarto') || str_contains($value, '4to') => 4,
            str_contains($value, 'tercer') || str_contains($value, '3ro') => 3,
            str_contains($value, 'segundo') || str_contains($value, '2do') => 2,
            str_contains($value, 'primer') || str_contains($value, '1ro') => 1,
            default => 0,
        };
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

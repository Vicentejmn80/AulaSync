<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Attendance;
use App\Models\Course;
use App\Models\Grade;
use App\Models\IntelligenceDocument;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Motor analítico de "Inteligencia AulaSync" para profesores: consultas
 * Eloquent fijas y parametrizadas, siempre acotadas al profesor autenticado.
 * Cero alucinación: si no hay datos, se reporta honestamente. Todos los
 * promedios se calculan en porcentaje sobre la escala real de cada actividad.
 */
class IntelligenceAnalyticsService
{
    private const AVG_PCT = 'AVG(grades.score * 100.0 / NULLIF(activities.max_score, 0))';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function courses(User $teacher): array
    {
        return Course::where('teacher_id', $teacher->id)
            ->when($teacher->colegio_id, fn ($query) => $query->where('colegio_id', $teacher->colegio_id))
            ->withCount('students')
            ->orderBy('subject_name')
            ->get()
            ->map(function ($course) {
                $avg = Grade::query()
                    ->join('activities', 'activities.id', '=', 'grades.activity_id')
                    ->where('activities.teacher_id', $course->teacher_id)
                    ->where('activities.course_id', $course->id)
                    ->whereNotNull('activities.max_score')
                    ->where('activities.max_score', '>', 0)
                    ->selectRaw(self::AVG_PCT.' as avg_pct')
                    ->value('avg_pct');

                $absences = Attendance::where('course_id', $course->id)
                    ->where('status', 'absent')
                    ->where('attended_on', '>=', now()->subDays(30)->format('Y-m-d'))
                    ->count();

                return [
                    'id' => (int) $course->id,
                    'label' => trim($course->subject_name.' · '.$course->grade.($course->section ? ' / '.$course->section : '')),
                    'subject' => $course->subject_name,
                    'grade' => $course->grade,
                    'section' => $course->section,
                    'students_count' => (int) $course->students_count,
                    'activities_count' => (int) $course->activities()->count(),
                    'avg_pct' => $avg !== null ? round((float) $avg, 1) : null,
                    'absences_30d' => $absences,
                ];
            })->values()->all();
    }

    /**
     * Resumen accionable del grupo: rendimiento, tendencias, asistencia,
     * estudiantes que requieren atención, áreas con dificultades,
     * información detectada en documentos y recomendaciones.
     *
     * @return array<string, mixed>
     */
    public function groupSummary(User $teacher, ?int $courseId = null): array
    {
        $courses = $this->scopedCourses($teacher, $courseId);

        if ($courses->isEmpty()) {
            return $this->emptySummary('Todavía no tienes cursos con datos. Crea tu curso e importa tus documentos para empezar.');
        }

        $courseIds = $courses->pluck('id')->all();
        $label = $this->groupLabel($courses);

        $performance = $this->performance($teacher, $courseIds);
        $trend = $this->trend($teacher, $courseIds, 6);
        $attendance = $this->attendanceBlock($teacher, $courseIds, 30);
        $difficulty = $this->difficultyAreas($teacher, $courseIds);
        $attention = $this->atRisk($teacher, $courseId);
        $detected = $this->detectedInfo($teacher);
        $upcoming = $this->upcoming($teacher, $courseIds);

        return [
            'label' => $label,
            'has_data' => $performance['graded_students'] > 0,
            'message' => $performance['graded_students'] > 0
                ? null
                : "Todavía no hay calificaciones registradas para {$label}. Importa tus notas o registra calificaciones para ver el análisis del grupo.",
            'performance' => $performance,
            'trend' => $trend,
            'attendance' => $attendance,
            'difficulty' => $difficulty,
            'attention' => $attention,
            'detected' => $detected,
            'upcoming' => $upcoming,
            'recommendations' => $this->recommendations($performance, $trend, $attendance, $difficulty, $attention),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function studentSummary(User $teacher, ?int $courseId, string $studentName): array
    {
        $colegioId = $teacher->colegio_id ? (int) $teacher->colegio_id : 0;
        $match = $colegioId > 0 ? app(PersonNameMatcher::class)->resolveStudent($colegioId, $studentName) : null;

        if (! $match || ! $match->isUnique() || ! $match->model) {
            return [
                'found' => false,
                'ambiguous' => $match && $match->isAmbiguous(),
                'message' => $match && $match->isAmbiguous()
                    ? $match->message
                    : "No encontré a ningún alumno llamado «{$studentName}» en tu colegio.",
            ];
        }

        $student = Student::where('id', $match->model->id)
            ->where('colegio_id', $colegioId)
            ->first();

        if (! $student) {
            return ['found' => false, 'ambiguous' => false, 'message' => "No encontré a «{$studentName}» en tu colegio."];
        }

        $courses = $this->scopedCourses($teacher, $courseId);
        $courseIds = $courses->pluck('id')->all();

        $enrolled = DB::table('course_student')
            ->where('student_id', $student->id)
            ->whereIn('course_id', $courseIds)
            ->exists();

        if (! $enrolled) {
            return ['found' => false, 'ambiguous' => false, 'message' => "«{$student->name}» no está inscrito en tus cursos."];
        }

        $grades = Grade::query()
            ->join('activities', 'activities.id', '=', 'grades.activity_id')
            ->where('grades.student_id', $student->id)
            ->where('activities.teacher_id', $teacher->id)
            ->whereIn('activities.course_id', $courseIds)
            ->orderByDesc('activities.due_date')
            ->orderByDesc('activities.id')
            ->get([
                'activities.id as activity_id',
                'activities.title as activity_title',
                'activities.due_date',
                'activities.max_score',
                'grades.score',
            ]);

        $avgPct = null;
        if ($grades->isNotEmpty()) {
            $sums = $grades->filter(fn ($grade) => (int) $grade->max_score > 0);
            if ($sums->isNotEmpty()) {
                $avgPct = round((float) $sums->avg(fn ($grade) => $grade->score * 100 / $grade->max_score), 1);
            }
        }

        $absences = Attendance::where('student_id', $student->id)
            ->whereIn('course_id', $courseIds)
            ->where('status', 'absent')
            ->where('attended_on', '>=', now()->subDays(30)->format('Y-m-d'))
            ->count();

        $lastGrades = $grades->take(2);
        $declining = $lastGrades->count() === 2 && $lastGrades->every(fn ($grade) => (int) $grade->max_score > 0 && ($grade->score * 100 / $grade->max_score) < 50);

        return [
            'found' => true,
            'student' => [
                'id' => (int) $student->id,
                'name' => (string) $student->name,
                'grade' => $student->grade,
                'section' => $student->section,
            ],
            'avg_pct' => $avgPct,
            'grades_count' => $grades->count(),
            'absences_30d' => $absences,
            'recent_grades' => $grades->take(5)->map(fn ($grade) => [
                'activity' => $grade->activity_title,
                'date' => $grade->due_date,
                'score' => (float) $grade->score,
                'max_score' => (int) $grade->max_score,
                'pct' => (int) $grade->max_score > 0 ? round($grade->score * 100 / $grade->max_score, 1) : null,
            ])->values()->all(),
            'attention_reasons' => array_values(array_filter([
                $avgPct !== null && $avgPct < 50 ? 'Rendimiento bajo ('.$avgPct.'%)' : null,
                $declining ? 'Descenso en las últimas actividades' : null,
                $absences >= 3 ? "Inasistencias frecuentes ({$absences} en 30 días)" : null,
                $grades->isEmpty() ? 'Sin calificaciones registradas' : null,
            ])),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function atRisk(User $teacher, ?int $courseId = null): array
    {
        $courses = $this->scopedCourses($teacher, $courseId);

        if ($courses->isEmpty()) {
            return [];
        }

        $courseIds = $courses->pluck('id')->all();
        $thresholdDate = now()->subDays(30)->format('Y-m-d');

        $students = Student::whereHas('courses', fn ($query) => $query->whereIn('courses.id', $courseIds))
            ->where('students.colegio_id', $teacher->colegio_id)
            ->with(['courses' => fn ($query) => $query->whereIn('courses.id', $courseIds)])
            ->get();

        $result = [];

        foreach ($students as $student) {
            $stats = Grade::query()
                ->join('activities', 'activities.id', '=', 'grades.activity_id')
                ->where('grades.student_id', $student->id)
                ->where('activities.teacher_id', $teacher->id)
                ->whereIn('activities.course_id', $courseIds)
                ->where('activities.max_score', '>', 0)
                ->selectRaw(self::AVG_PCT.' as avg_pct')
                ->selectRaw('COUNT(*) as graded')
                ->first();

            $absences = Attendance::where('student_id', $student->id)
                ->whereIn('course_id', $courseIds)
                ->where('status', 'absent')
                ->where('attended_on', '>=', $thresholdDate)
                ->count();

            $avgPct = $stats && $stats->avg_pct !== null ? round((float) $stats->avg_pct, 1) : null;
            $reasons = [];

            if ($avgPct !== null && $avgPct < 50) {
                $reasons[] = "Rendimiento bajo ({$avgPct}%)";
            }
            if ($absences >= 3) {
                $reasons[] = "Inasistencias frecuentes ({$absences} en 30 días)";
            }
            if ((int) ($stats->graded ?? 0) === 0) {
                $reasons[] = 'Sin calificaciones registradas';
            }

            if ($reasons === []) {
                continue;
            }

            $result[] = [
                'student_id' => (int) $student->id,
                'name' => (string) $student->name,
                'grade' => $student->grade,
                'section' => $student->section,
                'courses' => $student->courses->pluck('subject_name')->unique()->values()->all(),
                'avg_pct' => $avgPct,
                'absences_30d' => $absences,
                'reasons' => $reasons,
            ];
        }

        usort($result, fn ($a, $b) => ($a['avg_pct'] ?? 101) <=> ($b['avg_pct'] ?? 101));

        return array_values($result);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function bestPerformers(User $teacher, ?int $courseId = null, int $limit = 5): array
    {
        $courses = $this->scopedCourses($teacher, $courseId);

        if ($courses->isEmpty()) {
            return [];
        }

        $courseIds = $courses->pluck('id')->all();

        return Grade::query()
            ->join('activities', 'activities.id', '=', 'grades.activity_id')
            ->join('students', 'students.id', '=', 'grades.student_id')
            ->where('activities.teacher_id', $teacher->id)
            ->whereIn('activities.course_id', $courseIds)
            ->where('activities.max_score', '>', 0)
            ->groupBy('students.id', 'students.name')
            ->selectRaw('students.id as student_id')
            ->selectRaw('students.name as student_name')
            ->selectRaw(self::AVG_PCT.' as avg_pct')
            ->selectRaw('COUNT(*) as graded')
            ->havingRaw('COUNT(*) >= 1')
            ->orderByDesc('avg_pct')
            ->limit(max(1, min($limit, 20)))
            ->get()
            ->map(fn ($row) => [
                'student_id' => (int) $row->student_id,
                'name' => (string) $row->student_name,
                'avg_pct' => round((float) $row->avg_pct, 1),
                'graded' => (int) $row->graded,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function difficultyAreas(User $teacher, ?int $courseId = null): array
    {
        $courses = $this->scopedCourses($teacher, $courseId);

        if ($courses->isEmpty()) {
            return [];
        }

        $courseIds = $courses->pluck('id')->all();

        return Grade::query()
            ->join('activities', 'activities.id', '=', 'grades.activity_id')
            ->where('activities.teacher_id', $teacher->id)
            ->whereIn('activities.course_id', $courseIds)
            ->where('activities.max_score', '>', 0)
            ->groupBy('activities.id', 'activities.title', 'courses.subject_name')
            ->join('courses', 'courses.id', '=', 'activities.course_id')
            ->selectRaw('activities.id as activity_id')
            ->selectRaw('activities.title as activity_title')
            ->selectRaw('courses.subject_name')
            ->selectRaw(self::AVG_PCT.' as avg_pct')
            ->selectRaw('COUNT(*) as graded')
            ->havingRaw('COUNT(*) >= 3')
            ->orderByRaw(self::AVG_PCT.' ASC')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'activity_id' => (int) $row->activity_id,
                'title' => (string) $row->activity_title,
                'subject' => (string) $row->subject_name,
                'avg_pct' => round((float) $row->avg_pct, 1),
                'graded' => (int) $row->graded,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function attendanceBlock(User $teacher, ?int $courseId = null, int $days = 30): array
    {
        $courses = $this->scopedCourses($teacher, $courseId);

        if ($courses->isEmpty()) {
            return ['rate' => null, 'total' => 0, 'absent' => 0, 'top_absentees' => []];
        }

        $courseIds = $courses->pluck('id')->all();
        $days = max(1, min($days, 180));
        $since = now()->subDays($days)->format('Y-m-d');

        $total = Attendance::whereIn('course_id', $courseIds)
            ->where('attended_on', '>=', $since)
            ->count();

        $absent = Attendance::whereIn('course_id', $courseIds)
            ->where('attended_on', '>=', $since)
            ->where('status', 'absent')
            ->count();

        $topAbsentees = Attendance::query()
            ->join('students', 'students.id', '=', 'attendances.student_id')
            ->whereIn('attendances.course_id', $courseIds)
            ->where('attendances.attended_on', '>=', $since)
            ->where('attendances.status', 'absent')
            ->groupBy('students.id', 'students.name')
            ->selectRaw('students.id as student_id')
            ->selectRaw('students.name as student_name')
            ->selectRaw('COUNT(*) as absences')
            ->orderByDesc('absences')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'student_id' => (int) $row->student_id,
                'name' => (string) $row->student_name,
                'absences' => (int) $row->absences,
            ])
            ->all();

        return [
            'days' => $days,
            'total' => $total,
            'absent' => $absent,
            'rate' => $total > 0 ? round(100 * ($total - $absent) / $total, 1) : null,
            'top_absentees' => $topAbsentees,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function performance(User $teacher, array $courseIds): array
    {
        $rows = Grade::query()
            ->join('activities', 'activities.id', '=', 'grades.activity_id')
            ->join('students', 'students.id', '=', 'grades.student_id')
            ->where('activities.teacher_id', $teacher->id)
            ->whereIn('activities.course_id', $courseIds)
            ->where('activities.max_score', '>', 0)
            ->groupBy('students.id', 'students.name')
            ->selectRaw('students.id as student_id')
            ->selectRaw('students.name as student_name')
            ->selectRaw(self::AVG_PCT.' as avg_pct')
            ->orderByDesc('avg_pct')
            ->get();

        $gradedStudents = $rows->count();
        $overall = $gradedStudents > 0 ? round((float) $rows->avg('avg_pct'), 1) : null;

        $low = $rows->filter(fn ($row) => (float) $row->avg_pct < 50)->count();
        $mid = $rows->filter(fn ($row) => (float) $row->avg_pct >= 50 && (float) $row->avg_pct < 70)->count();
        $high = $rows->filter(fn ($row) => (float) $row->avg_pct >= 70)->count();

        return [
            'avg_pct' => $overall,
            'graded_students' => $gradedStudents,
            'distribution' => ['low' => $low, 'mid' => $mid, 'high' => $high],
            'top' => $rows->take(3)->map(fn ($row) => [
                'name' => (string) $row->student_name,
                'avg_pct' => round((float) $row->avg_pct, 1),
            ])->values()->all(),
            'struggling' => $rows->reverse()->take(3)->map(fn ($row) => [
                'name' => (string) $row->student_name,
                'avg_pct' => round((float) $row->avg_pct, 1),
            ])->values()->all(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function trend(User $teacher, array $courseIds, int $weeks): array
    {
        $weeks = max(2, min($weeks, 12));
        $since = now()->subWeeks($weeks)->startOfWeek()->format('Y-m-d');
        $bucket = $this->weekBucket('activities.due_date');

        $rows = Grade::query()
            ->join('activities', 'activities.id', '=', 'grades.activity_id')
            ->where('activities.teacher_id', $teacher->id)
            ->whereIn('activities.course_id', $courseIds)
            ->where('activities.max_score', '>', 0)
            ->whereDate('activities.due_date', '>=', $since)
            ->groupBy(DB::raw($bucket))
            ->orderBy(DB::raw($bucket))
            ->selectRaw("{$bucket} as week")
            ->selectRaw(self::AVG_PCT.' as avg_pct')
            ->selectRaw('COUNT(*) as graded')
            ->get();

        return $rows->map(fn ($row) => [
            'week' => (string) $row->week,
            'avg_pct' => round((float) $row->avg_pct, 1),
            'graded' => (int) $row->graded,
        ])->values()->all();
    }

    /**
     * @return array<int, string>
     */
    private function detectedInfo(User $teacher): array
    {
        return IntelligenceDocument::where('teacher_id', $teacher->id)
            ->where('status', IntelligenceDocument::STATUS_APPLIED)
            ->orderByDesc('applied_at')
            ->limit(5)
            ->get()
            ->flatMap(fn ($document) => (array) data_get($document->extraction, 'observations', []))
            ->map(fn ($observation) => mb_substr((string) $observation, 0, 300))
            ->unique()
            ->values()
            ->take(5)
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function upcoming(User $teacher, array $courseIds): array
    {
        return Activity::where('teacher_id', $teacher->id)
            ->whereIn('course_id', $courseIds)
            ->whereDate('due_date', '>=', now()->format('Y-m-d'))
            ->orderBy('due_date')
            ->limit(5)
            ->get(['id', 'title', 'type', 'due_date'])
            ->map(fn ($activity) => [
                'id' => (int) $activity->id,
                'title' => (string) $activity->title,
                'type' => (string) $activity->type,
                'date' => optional($activity->due_date)->format('Y-m-d'),
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function recommendations(array $performance, array $trend, array $attendance, array $difficulty, array $attention): array
    {
        $tips = [];

        if ($performance['graded_students'] === 0) {
            return ['Registra calificaciones (o importa tus notas) para desbloquear recomendaciones basadas en datos.'];
        }

        $struggling = $performance['struggling'];
        if (! empty($struggling) && (float) $struggling[0]['avg_pct'] < 50) {
            $names = collect($struggling)->take(3)->pluck('name')->implode(', ');
            $tips[] = "Prioriza refuerzo para {$names}: están por debajo del 50% del promedio.";
        }

        if (! empty($difficulty)) {
            $worst = $difficulty[0];
            $tips[] = "La actividad «{$worst['title']}» ({$worst['subject']}) tiene el promedio más bajo ({$worst['avg_pct']}%): dedica una sesión de repaso.";
        }

        if (count($trend) >= 2) {
            $first = (float) $trend[0]['avg_pct'];
            $last = (float) $trend[count($trend) - 1]['avg_pct'];
            if ($last < $first - 10) {
                $tips[] = 'La tendencia semanal va a la baja ('.$first.'% → '.$last.'%): revisa el ritmo o el nivel de las últimas actividades.';
            }
        }

        $topAbsentee = $attendance['top_absentees'][0] ?? null;
        if ($topAbsentee && $topAbsentee['absences'] >= 3) {
            $tips[] = "Contacta a la familia de {$topAbsentee['name']}: {$topAbsentee['absences']} inasistencias en los últimos {$attendance['days']} días.";
        }

        if ($tips === []) {
            $tips[] = 'El grupo va bien en general: mantén el ritmo y sigue registrando calificaciones y asistencia.';
        }

        return $tips;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptySummary(string $message): array
    {
        return [
            'label' => null,
            'has_data' => false,
            'message' => $message,
            'performance' => ['avg_pct' => null, 'graded_students' => 0, 'distribution' => ['low' => 0, 'mid' => 0, 'high' => 0], 'top' => [], 'struggling' => []],
            'trend' => [],
            'attendance' => ['days' => 30, 'total' => 0, 'absent' => 0, 'rate' => null, 'top_absentees' => []],
            'difficulty' => [],
            'attention' => [],
            'detected' => [],
            'upcoming' => [],
            'recommendations' => [],
        ];
    }

    private function scopedCourses(User $teacher, ?int $courseId): \Illuminate\Support\Collection
    {
        return Course::where('teacher_id', $teacher->id)
            ->when($teacher->colegio_id, fn ($query) => $query->where('colegio_id', $teacher->colegio_id))
            ->when($courseId, fn ($query) => $query->where('id', $courseId))
            ->orderBy('subject_name')
            ->get();
    }

    private function groupLabel(\Illuminate\Support\Collection $courses): string
    {
        if ($courses->count() === 1) {
            $course = $courses->first();

            return trim($course->subject_name.' · '.$course->grade.($course->section ? ' / '.$course->section : ''));
        }

        return 'todos mis cursos ('.$courses->count().')';
    }

    private function weekBucket(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'pgsql' => "to_char({$column}::timestamp, 'IYYY-IW')",
            'mysql' => "DATE_FORMAT({$column}, '%x-%v')",
            default => "strftime('%Y-%W', {$column})",
        };
    }
}

<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Attendance;
use App\Models\AbsenceRequest;
use App\Models\Course;
use App\Models\Grade;
use App\Models\Notification;
use App\Models\Student;
use App\Services\StudentGradeAccumulationService;
use App\Support\GradingScale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class HubController extends Controller
{
    public function __construct(private StudentGradeAccumulationService $accumulation)
    {
    }

    // ─── Main hub view ───────────────────────────────────────────────────────

    public function index(Request $request): View
    {
        $teacher = auth()->user();
        $teacher->loadMissing('settings');

        $quotes = [
            'La educación es el arma más poderosa que puedes usar para cambiar el mundo. — Nelson Mandela',
            'Enseñar no es transferir conocimiento, sino crear posibilidades para su producción. — Paulo Freire',
            'La mejor manera de predecir el futuro es crearlo. — Peter Drucker',
            'El aprendizaje nunca agota la mente. — Leonardo da Vinci',
            'Un maestro afecta la eternidad; nunca puede saber dónde termina su influencia. — Henry Adams',
            'Educar es encender una llama, no llenar un recipiente. — Sócrates',
            'El éxito del alumno es el éxito del maestro.',
            'La creatividad es la inteligencia divirtiéndose. — Albert Einstein',
            'Aprender es descubrir que algo es posible. — Fritz Perls',
            'Cada alumno que iluminas es un futuro que transformas.',
        
        ];
        $dailyQuote = $quotes[abs(crc32(now()->toDateString())) % count($quotes)];

        // ── Optional: load from historial with plan_block filter ──
        $initialCourseId  = $request->query('course');
        $initialPlanBlock = $request->query('plan_block');

        return view('teacher.hub', compact('teacher', 'dailyQuote', 'initialCourseId', 'initialPlanBlock'));
    }

    // ─── Canvas API — Stats ──────────────────────────────────────────────────

    public function apiStats(): JsonResponse
    {
        $teacher = auth()->user();

        $courseIds = Course::where('teacher_id', $teacher->id)->pluck('id');
        $activityIds = Activity::whereIn('course_id', $courseIds)->pluck('id');

        $totalStudents = Course::whereIn('id', $courseIds)
            ->with('students')
            ->get()
            ->flatMap(fn ($c) => $c->students->pluck('id'))
            ->unique()
            ->count();

        $totalCourses    = $courseIds->count();
        $totalActivities = $activityIds->count();

        $avgGrade = null;
        if ($activityIds->isNotEmpty()) {
            $avgGrade = Grade::whereIn('activity_id', $activityIds)->avg('score');
        }

        // Activities due this week
        $activitiesThisWeek = Activity::whereIn('course_id', $courseIds)
            ->whereBetween('due_date', [now()->startOfWeek(), now()->endOfWeek()])
            ->count();

        // Next upcoming activity
        $nextActivity = Activity::whereIn('course_id', $courseIds)
            ->where('due_date', '>=', now()->toDateString())
            ->orderBy('due_date')
            ->with('course:id,subject_name,grade,section')
            ->first(['id', 'title', 'due_date', 'course_id']);

        // Climate: computed from recent grade average
        $climate = $this->computeClimate($avgGrade);

        // Grade trend: current week vs previous week average, only if both have data.
        $gradeTrend = null;
        if ($activityIds->isNotEmpty()) {
            $currentWeekAvg = Grade::whereIn('activity_id', $activityIds)
                ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
                ->avg('score');
            $previousWeekAvg = Grade::whereIn('activity_id', $activityIds)
                ->whereBetween('created_at', [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()])
                ->avg('score');

            if ($currentWeekAvg !== null && $previousWeekAvg !== null) {
                $gradeTrend = [
                    'delta'             => round($currentWeekAvg - $previousWeekAvg, 1),
                    'current_week_avg'  => round($currentWeekAvg, 1),
                    'previous_week_avg' => round($previousWeekAvg, 1),
                ];
            }
        }

        return response()->json([
            'total_courses'        => $totalCourses,
            'total_students'       => $totalStudents,
            'total_activities'     => $totalActivities,
            'activities_this_week' => $activitiesThisWeek,
            'avg_grade'            => $avgGrade ? round($avgGrade, 1) : null,
            'climate'              => $climate,
            'grade_trend'          => $gradeTrend,
            'next_activity'        => $nextActivity ? [
                'title'       => $nextActivity->title,
                'due_date'    => $nextActivity->due_date,
                'course_name' => optional($nextActivity->course)->subject_name . ' ' . optional($nextActivity->course)->grade,
            ] : null,
            'attendance'           => $this->attendanceSnapshot($teacher->id, $courseIds),
        ]);
    }

    private function attendanceSnapshot(int $teacherId, $courseIds): ?array
    {
        if (! Schema::hasTable('attendances') || $courseIds->isEmpty()) {
            return null;
        }

        $today = now()->toDateString();
        $base = Attendance::where('teacher_id', $teacherId)->whereDate('attended_on', $today);

        $absentToday = (clone $base)->where('status', Attendance::STATUS_ABSENT)->count();
        $tardyToday = (clone $base)->where('status', Attendance::STATUS_TARDY)->count();
        $takenCourses = (clone $base)->distinct()->pluck('course_id')->count();
        $pendingCourses = max(0, $courseIds->count() - $takenCourses);

        $alertMessage = null;
        if (Schema::hasTable('notifications')) {
            $alertMessage = Notification::where('user_id', $teacherId)
                ->where('title', 'Notificación de ausencia enviada')
                ->latest()
                ->value('message');
        }

        $familyReports = 0;
        if (Schema::hasTable('absence_requests')) {
            $studentIds = DB::table('course_student')
                ->whereIn('course_id', $courseIds)
                ->pluck('student_id')
                ->unique();
            $familyReports = $studentIds->isEmpty()
                ? 0
                : AbsenceRequest::whereIn('student_id', $studentIds)
                    ->where('status', 'pending')
                    ->whereDate('end_date', '>=', $today)
                    ->count();
        }

        return [
            'absent_today' => $absentToday,
            'tardy_today' => $tardyToday,
            'pending_courses' => $pendingCourses,
            'taken_courses' => $takenCourses,
            'family_reports' => $familyReports,
            'last_alert' => $alertMessage,
        ];
    }

    // ─── Canvas API — Courses list ───────────────────────────────────────────

    public function apiCourses(): JsonResponse
    {
        $courses = Course::where('teacher_id', auth()->id())
            ->withCount(['students', 'activities'])
            ->with(['activities' => fn ($q) => $q
                ->where('type', '!=', 'clase')
                ->withCount('grades')
                ->withAvg('grades', 'score')])
            ->latest()
            ->get()
            ->map(function ($c) {
                $gradableActivities = $c->activities;
                $studentsCount = $c->students_count;

                $avgScores = $gradableActivities->pluck('grades_avg_score')->filter(fn ($v) => $v !== null);
                $avgScore = $avgScores->isNotEmpty() ? round((float) $avgScores->avg(), 1) : null;

                // "Pendiente de calificar": actividades evaluables con menos calificaciones que alumnos inscritos.
                $pendingGradingCount = $studentsCount > 0
                    ? $gradableActivities->filter(fn ($a) => (int) $a->grades_count < $studentsCount)->count()
                    : 0;

                return [
                    'id'                     => $c->id,
                    'subject_name'           => $c->subject_name,
                    'grade'                  => $c->grade,
                    'section'                => $c->section,
                    'school_year'            => $c->school_year,
                    'name'                   => $c->subject_name . ' · ' . $c->grade . ($c->section ? ' / ' . $c->section : ''),
                    'students_count'         => $c->students_count,
                    'activities_count'       => $c->activities_count,
                    'avg_score'              => $avgScore,
                    'pending_grading_count'  => $pendingGradingCount,
                ];
            });

        return response()->json($courses);
    }

    // ─── Canvas API — Single course detail ───────────────────────────────────

    public function apiCourse(Course $course): JsonResponse
    {
        if ($course->teacher_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado.'], 403);
        }

        $course->load([
            'students' => fn ($q) => $q
                ->select('students.id', 'students.name', 'students.grade', 'students.section', 'students.document_id', 'students.family_code')
                ->orderBy('students.name'),
            'activities' => fn ($q) => $q
                ->with('tareas')
                ->withCount('grades')
                ->withAvg('grades', 'score')
                ->orderBy('due_date'),
        ]);

        $totalStudents = $course->students->count();
        $accumulatedByStudent = $this->accumulation->calculateForCourse((int) $course->id);

        return response()->json([
            'id'           => $course->id,
            'subject_name' => $course->subject_name,
            'grade'        => $course->grade,
            'section'      => $course->section,
            'school_year'  => $course->school_year,
            'grading_scale' => GradingScale::normalize($course->grading_scale ?? null),
            'grading_scale_max' => GradingScale::maxFor($course->grading_scale ?? null),
            'grading_scale_label' => GradingScale::label($course->grading_scale ?? null),
            'name'         => $course->subject_name . ' · ' . $course->grade . ($course->section ? ' / ' . $course->section : ''),
            'students'     => $course->students->map(function ($s) use ($accumulatedByStudent) {
                $liveAccumulated = $accumulatedByStudent[$s->id] ?? null;
                $pivotAccumulated = $s->pivot?->promedio_acumulado !== null
                    ? round((float) $s->pivot->promedio_acumulado, 2)
                    : ($s->pivot?->nota_actual !== null ? round((float) $s->pivot->nota_actual, 2) : null);
                $accumulated = $liveAccumulated ?? $pivotAccumulated;

                return [
                    'id'   => $s->id,
                    'name' => $s->name,
                    'grade' => $s->grade,
                    'section' => $s->section,
                    'document_id' => $s->document_id,
                    'has_family_code' => filled($s->family_code),
                    'avg_score' => $accumulated,
                    'nota_actual' => $accumulated,
                    'promedio_acumulado' => $accumulated,
                ];
            }),
            'activities'   => $course->activities->map(fn ($a) => [
                'id'                => $a->id,
                'type'              => $a->type ?? 'actividad',
                'is_homework'       => (bool) $a->is_homework,
                'title'             => $a->title,
                'description'       => $a->description ?? '',
                'max_score'         => $a->max_score,
                'weight_percentage' => $a->weight_percentage,
                'due_date'          => $a->due_date instanceof \Carbon\Carbon
                                       ? $a->due_date->format('Y-m-d')
                                       : (string) $a->due_date,
                'nee_type'          => $a->nee_type,
                'nee_adaptation'    => $a->nee_adaptation,
                'avg_score'         => $a->grades_avg_score !== null ? round((float) $a->grades_avg_score, 1) : null,
                'graded_count'      => (int) ($a->grades_count ?? 0),
                'total_students'    => $totalStudents,
                'grades_url'        => route('teacher.grades.create', $a->id),
                'tareas'            => $a->tareas->map(fn ($t) => [
                    'id' => $t->id,
                    'titulo' => $t->titulo,
                    'descripcion' => $t->descripcion,
                    'fecha_entrega' => $t->fecha_entrega?->format('Y-m-d'),
                    'puntos' => $t->puntos,
                    'calificacion' => $t->calificacion,
                    'feedback' => $t->feedback,
                ])->values(),
            ]),
        ]);
    }

    public function apiCourseStudentGrades(Course $course, Student $student): JsonResponse
    {
        try {
            if ((int) $course->teacher_id !== (int) auth()->id()) {
                return response()->json(['success' => false, 'error' => 'No autorizado.'], 403);
            }

            $isEnrolled = $course->students()->where('students.id', $student->id)->exists();
            if (! $isEnrolled) {
                return response()->json(['success' => false, 'error' => 'El alumno no pertenece a este curso.'], 404);
            }

            $activities = $course->activities()
                ->where(function ($q) {
                    $q->whereNull('type')->orWhere('type', '!=', 'clase');
                })
                ->orderBy('due_date')
                ->get(['id', 'title', 'type', 'weight_percentage', 'due_date', 'max_score']);

            $gradesByActivity = Grade::where('student_id', $student->id)
                ->whereIn('activity_id', $activities->pluck('id')->filter()->values())
                ->get(['activity_id', 'score'])
                ->keyBy('activity_id');

            $rows = $activities->map(function ($activity) use ($gradesByActivity) {
                $grade = $gradesByActivity->get($activity->id);
                $score = $grade?->score !== null ? (float) $grade->score : null;
                $weight = (float) ($activity->weight_percentage ?? 0);
                $contribution = $score !== null ? round(($score * $weight) / 100, 2) : null;
                $dueDate = $activity->due_date;

                return [
                    'activity_id' => $activity->id,
                    'title' => $activity->title,
                    'type' => $activity->type,
                    'weight_percentage' => $weight,
                    'score' => $score,
                    'due_date' => $dueDate instanceof \Carbon\Carbon
                        ? $dueDate->format('Y-m-d')
                        : ($dueDate ? (string) $dueDate : null),
                    'contribution' => $contribution,
                ];
            })->values();

            $accumulated = round((float) $rows->sum(fn ($row) => $row['contribution'] ?? 0), 2);
            $pivot = $course->students()->where('students.id', $student->id)->first()?->pivot;

            return response()->json([
                'success' => true,
                'student' => [
                    'id' => $student->id,
                    'name' => $student->name,
                    'grade' => $student->grade,
                    'section' => $student->section,
                    'document_id' => $student->document_id,
                    'has_family_code' => filled($student->family_code),
                    'promedio_acumulado' => $accumulated,
                    'nota_actual' => $pivot?->nota_actual !== null ? (float) $pivot->nota_actual : null,
                ],
                'course' => [
                    'id' => $course->id,
                    'name' => $course->subject_name.' · '.$course->grade.($course->section ? ' / '.$course->section : ''),
                    'grading_scale' => GradingScale::normalize($course->grading_scale ?? null),
                    'grading_scale_max' => GradingScale::maxFor($course->grading_scale ?? null),
                    'grading_scale_label' => GradingScale::label($course->grading_scale ?? null),
                ],
                'activities' => $rows,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'error' => 'No se pudo cargar el detalle del alumno.',
                'student' => [
                    'id' => $student->id,
                    'name' => $student->name,
                    'grade' => $student->grade,
                    'section' => $student->section,
                    'document_id' => $student->document_id ?? null,
                    'has_family_code' => filled($student->family_code ?? null),
                    'promedio_acumulado' => null,
                ],
                'course' => [
                    'id' => $course->id,
                    'name' => trim(($course->subject_name ?? '').' · '.($course->grade ?? '')),
                ],
                'activities' => [],
            ], 500);
        }
    }

    public function updateGradingScale(Request $request, Course $course): JsonResponse
    {
        if ((int) $course->teacher_id !== (int) auth()->id()) {
            return response()->json(['success' => false, 'error' => 'No autorizado.'], 403);
        }

        $data = $request->validate([
            'grading_scale' => ['required', 'in:1-5,1-10,1-20'],
        ]);

        $course->update([
            'grading_scale' => GradingScale::normalize($data['grading_scale']),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Escala de calificación actualizada.',
            'grading_scale' => $course->grading_scale,
            'grading_scale_max' => GradingScale::maxFor($course->grading_scale),
            'grading_scale_label' => GradingScale::label($course->grading_scale),
        ]);
    }

    // ─── Canvas API — Calendar ───────────────────────────────────────────────

    public function apiCalendar(Request $request): JsonResponse
    {
        $teacher     = auth()->user();
        $monthStr    = $request->query('month', now()->format('Y-m'));

        try {
            $start = Carbon::parse($monthStr . '-01')->startOfMonth();
        } catch (\Exception) {
            $start = now()->startOfMonth();
        }
        $end = $start->copy()->endOfMonth();

        $courseIds = Course::where('teacher_id', $teacher->id)->pluck('id');

        // Course color palette (cycles)
        $palette = ['#7c3aed','#2563eb','#059669','#d97706','#dc2626','#0891b2','#7c3aed'];
        $courseColors = Course::whereIn('id', $courseIds)
            ->get(['id'])
            ->pluck('id')
            ->values()
            ->mapWithKeys(fn ($id, $idx) => [$id => $palette[$idx % count($palette)]])
            ->toArray();

        // Load all activities for this teacher's courses in the month range
        $query = Activity::whereIn('course_id', $courseIds)
            ->whereBetween('due_date', [$start->toDateString(), $end->toDateString()])
            ->with(['course:id,subject_name,grade,section', 'tareas']);

        $activitiesByDay = $query->get()
            ->groupBy(fn ($a) => Carbon::parse($a->due_date)->format('Y-m-d'))
            ->map(fn ($group) => $group->map(fn ($a) => [
                'id'            => $a->id,
                'type'          => $a->type ?? 'actividad',
                'title'         => $a->title,
                'description'   => $a->description ?? '',
                'course_name'   => optional($a->course)->subject_name . ' ' . optional($a->course)->grade,
                'course_id'     => $a->course_id,
                'grade'         => optional($a->course)->grade,
                'section'       => optional($a->course)->section,
                'color'         => $a->is_homework ? '#0ea5e9' : ($courseColors[$a->course_id] ?? '#7c3aed'),
                'due_date'      => $a->due_date instanceof \Carbon\Carbon
                                   ? $a->due_date->format('Y-m-d')
                                   : (string) $a->due_date,
                'max_score'     => $a->max_score,
                'plan_block_id' => $a->plan_block_id,
                'grades_url'    => route('teacher.grades.create', $a->id),
                'is_homework'   => (bool) $a->is_homework,
                'director_notes'=> $a->director_notes,
                'nee_type'      => $a->nee_type,
                'nee_adaptation'=> $a->nee_adaptation,
                'tareas'        => $a->tareas->map(fn ($t) => [
                    'id' => $t->id,
                    'titulo' => $t->titulo,
                    'descripcion' => $t->descripcion,
                    'fecha_entrega' => $t->fecha_entrega?->format('Y-m-d'),
                    'puntos' => $t->puntos,
                    'calificacion' => $t->calificacion,
                    'feedback' => $t->feedback,
                ])->values(),
            ]));

        return response()->json([
            'month'             => $start->format('Y-m'),
            'month_name'        => $this->monthNameEs($start->month) . ' ' . $start->year,
            'days_in_month'     => $start->daysInMonth,
            'first_weekday'     => (int) $start->format('w'), // 0=Sun … 6=Sat
            'activities_by_day' => $activitiesByDay,
            'total_activities'  => $activitiesByDay->flatten(1)->count(),
        ]);
    }

    /**
     * Detalle de una actividad del docente (para deep-link desde notificaciones).
     */
    public function apiActivity(Activity $activity): JsonResponse
    {
        abort_unless($activity->teacher_id === auth()->id(), 403);

        $activity->load([
            'course:id,subject_name,grade,section',
            'tareas:id,actividad_id,titulo,descripcion,fecha_entrega,puntos,calificacion,feedback',
        ]);

        $course = $activity->course;

        return response()->json([
            'success' => true,
            'activity' => [
                'id' => $activity->id,
                'type' => $activity->type ?? 'actividad',
                'title' => $activity->title,
                'description' => $activity->description ?? '',
                'course_name' => trim(($course?->subject_name ?? '') . ' ' . ($course?->grade ?? '')),
                'course_id' => $activity->course_id,
                'grade' => $course?->grade,
                'section' => $course?->section,
                'due_date' => $activity->due_date instanceof \Carbon\Carbon
                    ? $activity->due_date->format('Y-m-d')
                    : (string) $activity->due_date,
                'max_score' => $activity->max_score,
                'weight_percentage' => $activity->weight_percentage,
                'plan_block_id' => $activity->plan_block_id,
                'is_homework' => (bool) $activity->is_homework,
                'director_notes' => $activity->director_notes,
                'nee_type' => $activity->nee_type,
                'nee_adaptation' => $activity->nee_adaptation,
                'tareas' => $activity->tareas->map(fn ($t) => [
                    'id' => $t->id,
                    'titulo' => $t->titulo,
                    'descripcion' => $t->descripcion,
                    'fecha_entrega' => $t->fecha_entrega?->format('Y-m-d'),
                    'puntos' => $t->puntos,
                    'calificacion' => $t->calificacion,
                    'feedback' => $t->feedback,
                ])->values(),
            ],
        ]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function monthNameEs(int $month): string
    {
        $names = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio',
                  'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
        return $names[$month] ?? '';
    }

    private function computeClimate(?float $avg): array
    {
        if ($avg === null) {
            return ['label' => 'Sin datos', 'color' => 'slate', 'icon' => '📊', 'pct' => null];
        }
        return match (true) {
            $avg >= 17 => ['label' => 'Excelente',   'color' => 'emerald', 'icon' => '🌟', 'pct' => round(($avg / 20) * 100)],
            $avg >= 13 => ['label' => 'Bueno',        'color' => 'blue',    'icon' => '✅', 'pct' => round(($avg / 20) * 100)],
            $avg >= 10 => ['label' => 'Atención',     'color' => 'amber',   'icon' => '⚠️', 'pct' => round(($avg / 20) * 100)],
            default    => ['label' => 'Intervención', 'color' => 'red',     'icon' => '🚨', 'pct' => round(($avg / 20) * 100)],
        };
    }
}

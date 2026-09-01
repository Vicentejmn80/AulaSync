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
use App\Services\AttendanceSummaryService;
use App\Services\StudentEnrollmentService;
use App\Services\StudentGradeAccumulationService;
use App\Services\TeacherInviteClaimService;
use App\Support\GradeLabel;
use App\Support\GradingScale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class HubController extends Controller
{
    public function __construct(
        private StudentGradeAccumulationService $accumulation,
        private AttendanceSummaryService $attendanceSummary,
        private TeacherInviteClaimService $inviteClaim,
        private StudentEnrollmentService $enrollment,
    ) {
    }

    // ─── Main hub view ───────────────────────────────────────────────────────

    public function index(Request $request): View
    {
        $teacher = auth()->user();
        $teacher->loadMissing('settings');

        // Reclama invitaciones DOC- pendientes (p.ej. si el onboarding no vinculó cursos)
        $this->inviteClaim->claimForUser($teacher->fresh());
        $teacher->refresh();
        $this->enrollment->syncTeacherCourses($teacher);

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

        $attendanceReasons = collect();
        if (Schema::hasTable('attendance_reasons')) {
            $attendanceReasons = \App\Models\AttendanceReason::query()
                ->where(function ($q) use ($teacher) {
                    $q->whereNull('colegio_id');
                    if ($teacher->colegio_id) {
                        $q->orWhere('colegio_id', $teacher->colegio_id);
                    }
                })
                ->orderBy('sort_order')
                ->get(['id', 'code', 'label', 'category', 'requires_comment']);
        }

        return view('teacher.hub', compact('teacher', 'dailyQuote', 'initialCourseId', 'initialPlanBlock', 'attendanceReasons'));
    }

    // ─── Canvas API — Stats ──────────────────────────────────────────────────

    public function apiStats(): JsonResponse
    {
        $teacher = auth()->user();
        $this->enrollment->syncTeacherCourses($teacher);

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

        $upcomingColumns = ['id', 'title', 'due_date', 'course_id', 'type', 'is_homework', 'evaluation_id'];
        if (Schema::hasColumn('activities', 'scheduled_time')) {
            $upcomingColumns[] = 'scheduled_time';
        }

        $upcomingRaw = Activity::whereIn('course_id', $courseIds)
            ->where('due_date', '>=', now()->toDateString())
            ->orderBy('due_date')
            ->orderBy('id')
            ->limit(12)
            ->with('course:id,subject_name,grade,section')
            ->get($upcomingColumns);

        $upcomingQueue = $this->serializeUpcomingQueue($upcomingRaw)
            ->sortBy(fn ($item) => ($item['due_date'] ?? '').'|'.($item['time_label'] ?? '99:99').'|'.($item['id'] ?? 0))
            ->take(6)
            ->values();
        $upcomingActivities = $upcomingQueue->take(5)->values();
        $nextActivity = $upcomingQueue->first();

        $todayActivities = Activity::whereIn('course_id', $courseIds)
            ->whereDate('due_date', now()->toDateString())
            ->with('course:id,subject_name,grade,section')
            ->orderBy('course_id')
            ->orderBy('title')
            ->get();

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
            'next_activity'        => $nextActivity,
            'upcoming_activities'  => $upcomingActivities,
            'upcoming_queue'       => $upcomingQueue->take(5)->values(),
            'today_grade_list'     => $this->buildTodayGradeList($todayActivities),
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
        $this->enrollment->syncTeacherCourses(auth()->user());

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
                    'grade_color'            => $this->gradeColor($c->grade),
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

        $this->enrollment->syncCourseWithGradeStudents($course, null);

        $course->load([
            'students' => fn ($q) => $q
                ->select('students.id', 'students.name', 'students.grade', 'students.section', 'students.document_id', 'students.family_code')
                ->orderBy('students.name'),
            'activities' => fn ($q) => $q
                ->with(['tareas', 'evaluation:id,title,mode,question_count,topic,instructions', 'neeStudent:id,name'])
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
            'students_count' => $totalStudents,
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
            'activities'   => $course->activities->map(fn ($a) => $this->serializeHubActivity($a, $totalStudents)),
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
            $attendance = $this->attendanceSummary->percentForStudentInCourse($student, $course);

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
                    'attendance_percentage' => $attendance['percentage'],
                    'attendance_present' => $attendance['present'],
                    'attendance_absent' => $attendance['absent'],
                    'attendance_tardy' => $attendance['tardy'],
                    'attendance_total' => $attendance['total'],
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
            'grading_scale' => ['required', 'in:1-5,1-10,1-20,A-F'],
        ]);

        $course->update([
            'grading_scale' => GradingScale::normalize($data['grading_scale']),
        ]);

        $scaleMax = GradingScale::maxFor($course->grading_scale);
        Activity::query()
            ->where('course_id', $course->id)
            ->update(['max_score' => $scaleMax]);

        return response()->json([
            'success' => true,
            'message' => 'Escala de calificación actualizada.',
            'grading_scale' => $course->grading_scale,
            'grading_scale_max' => $scaleMax,
            'grading_scale_label' => GradingScale::label($course->grading_scale),
        ]);
    }

    // ─── Canvas API — Calendar ───────────────────────────────────────────────

    public function apiCalendar(Request $request): JsonResponse
    {
        $teacher     = auth()->user();
        $monthStr    = $request->query('month', now()->format('Y-m'));
        $requestedGrade = trim((string) $request->query('grade', 'all'));
        $selectedGrade = $requestedGrade !== '' ? $requestedGrade : 'all';

        try {
            $start = Carbon::parse($monthStr . '-01')->startOfMonth();
        } catch (\Exception) {
            $start = now()->startOfMonth();
        }
        $end = $start->copy()->endOfMonth();

        $teacherCourses = Course::where('teacher_id', $teacher->id)
            ->get(['id', 'grade']);
        $gradeOptions = $teacherCourses->pluck('grade')
            ->filter()
            ->map(fn ($grade) => GradeLabel::canonical((string) $grade) ?? trim((string) $grade))
            ->unique()
            ->values()
            ->all();
        $courseIds = $teacherCourses
            ->filter(function (Course $course) use ($selectedGrade) {
                if ($selectedGrade === 'all') {
                    return true;
                }

                $courseGrade = GradeLabel::canonical((string) $course->grade) ?? trim((string) $course->grade);
                $filterGrade = GradeLabel::canonical($selectedGrade) ?? trim($selectedGrade);

                return $courseGrade !== '' && $courseGrade === $filterGrade;
            })
            ->pluck('id')
            ->values();

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
            ->with(['course:id,subject_name,grade,section', 'tareas', 'evaluation:id,title,mode,question_count,topic,instructions', 'neeStudent:id,name']);

        $activitiesByDay = $query->get()
            ->groupBy(fn ($a) => Carbon::parse($a->due_date)->format('Y-m-d'))
            ->map(function ($group) use ($courseColors) {
                $ordered = $group->sortBy(function ($activity) {
                    $course = $activity->relationLoaded('course') ? $activity->course : null;
                    $grade = GradeLabel::canonical((string) ($course?->grade ?? '')) ?? trim((string) ($course?->grade ?? ''));
                    $name = mb_strtolower(trim((string) ($course?->subject_name ?? '')));
                    $title = mb_strtolower(trim((string) $activity->title));

                    return sprintf('%s|%s|%s', $grade, $name, $title);
                })->values();

                return $this->decorateActivitiesWithTimeSlots($ordered)->map(function ($item, $index) use ($courseColors) {
                    /** @var Activity $activity */
                    $activity = $item['activity'];
                    $slot = $item['slot'];
                    $payload = $this->serializeHubActivity($activity, null, $courseColors);
                    $payload['time_label'] = $slot['start'];
                    $payload['time_range'] = $slot['start'].'-'.$slot['end'];
                    $payload['slot_index'] = (int) $index;
                    $payload['scheduled'] = $slot['scheduled'];

                    return $payload;
                })->values();
            });

        return response()->json([
            'month'             => $start->format('Y-m'),
            'month_name'        => $this->monthNameEs($start->month) . ' ' . $start->year,
            'days_in_month'     => $start->daysInMonth,
            'first_weekday'     => (int) $start->format('w'), // 0=Sun … 6=Sat
            'selected_grade'    => $selectedGrade,
            'grade_options'     => $gradeOptions,
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
            'evaluation:id,title,mode,question_count,topic,instructions',
            'neeStudent:id,name',
        ]);

        return response()->json([
            'success' => true,
            'activity' => $this->serializeHubActivity($activity),
        ]);
    }

    public function updateActivitySchedule(Request $request, Activity $activity): JsonResponse
    {
        abort_unless($activity->teacher_id === auth()->id(), 403);

        if (! Schema::hasColumn('activities', 'scheduled_time')) {
            return response()->json(['error' => 'El horario aún no está disponible.'], 422);
        }

        $data = $request->validate([
            'time' => ['required', 'regex:/^(?:[01]\d|2[0-3]):[0-5]\d$/'],
        ]);

        $hour = (int) substr($data['time'], 0, 2);
        $minute = (int) substr($data['time'], 3, 2);
        if ($hour < 7 || $hour > 19 || $minute !== 0) {
            return response()->json(['error' => 'Elige una hora entre 07:00 y 19:00.'], 422);
        }

        $activity->scheduled_time = sprintf('%02d:%02d:00', $hour, $minute);
        $activity->save();

        $slot = $this->timeSlotFromStart(sprintf('%02d:%02d', $hour, $minute));
        $payload = $this->serializeHubActivity($activity->fresh()->load('course:id,subject_name,grade,section'));
        $payload['time_label'] = $slot['start'];
        $payload['time_range'] = $slot['start'].'-'.$slot['end'];
        $payload['scheduled'] = true;

        return response()->json([
            'success' => true,
            'activity' => $payload,
        ]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function serializeHubActivity(Activity $activity, ?int $totalStudents = null, array $courseColors = []): array
    {
        $course = $activity->relationLoaded('course') ? $activity->course : $activity->course()->first();
        $evaluation = $activity->relationLoaded('evaluation') ? $activity->evaluation : null;
        $dueDate = $activity->due_date instanceof Carbon
            ? $activity->due_date->format('Y-m-d')
            : (string) $activity->due_date;

        $payload = [
            'id' => $activity->id,
            'course_id' => $activity->course_id,
            'type' => $activity->type ?? 'actividad',
            'is_homework' => (bool) $activity->is_homework,
            'title' => $activity->title,
            'description' => $activity->description ?? '',
            'notes' => $activity->notes,
            'max_score' => $activity->max_score,
            'weight_percentage' => $activity->weight_percentage,
            'due_date' => $dueDate,
            'course_name' => trim(($course?->subject_name ?? '').' '.($course?->grade ?? '')),
            'subject_name' => $course?->subject_name,
            'grade' => GradeLabel::canonical((string) ($course?->grade ?? '')) ?? trim((string) ($course?->grade ?? '')),
            'section' => $course?->section,
            'grade_color' => $this->gradeColor($course?->grade),
            'type_label' => $this->activityTypeLabel($activity),
            'color' => $this->gradeColor($course?->grade) ?: ($activity->is_homework ? '#0ea5e9' : ($courseColors[$activity->course_id] ?? '#7c3aed')),
            'plan_block_id' => $activity->plan_block_id,
            'grades_url' => route('teacher.grades.create', $activity->id),
            'director_notes' => $activity->director_notes,
            'nee_type' => $activity->nee_type,
            'nee_adaptation' => $activity->nee_adaptation,
            'nee_student_id' => $activity->nee_student_id,
            'nee_student_name' => $activity->neeStudent?->name,
            'evaluation_id' => $activity->evaluation_id ?: $evaluation?->id,
            'evaluation_mode' => $evaluation?->mode,
            'evaluation_topic' => $evaluation?->topic,
            'evaluation_question_count' => $evaluation?->question_count,
            'scheduled_time' => $this->scheduledTimeLabel($activity->scheduled_time ?? null),
            'tareas' => ($activity->tareas ?? collect())->map(fn ($t) => [
                'id' => $t->id,
                'titulo' => $t->titulo,
                'descripcion' => $t->descripcion,
                'fecha_entrega' => $t->fecha_entrega?->format('Y-m-d'),
                'puntos' => $t->puntos,
                'calificacion' => $t->calificacion,
                'feedback' => $t->feedback,
            ])->values(),
        ];

        if ($totalStudents !== null) {
            $payload['avg_score'] = $activity->grades_avg_score !== null ? round((float) $activity->grades_avg_score, 1) : null;
            $payload['graded_count'] = (int) ($activity->grades_count ?? 0);
            $payload['total_students'] = $totalStudents;
        }

        return $payload;
    }

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

    private function buildTodayGradeList($activities): array
    {
        if ($activities->isEmpty()) {
            return [];
        }

        $indexed = $this->decorateActivitiesWithTimeSlots($activities)->map(function ($item, $index) {
            /** @var Activity $activity */
            $activity = $item['activity'];
            $slot = $item['slot'];
            $course = $activity->relationLoaded('course') ? $activity->course : null;
            $grade = GradeLabel::canonical((string) ($course?->grade ?? '')) ?? trim((string) ($course?->grade ?? ''));
            $courseLabel = trim(($course?->subject_name ?? '').' '.($course?->grade ?? '').(($course?->section ?? '') !== '' ? ' / '.$course->section : ''));

            return [
                'id' => (int) $activity->id,
                'title' => (string) $activity->title,
                'type' => (string) ($activity->type ?? 'actividad'),
                'is_homework' => (bool) $activity->is_homework,
                'grade' => $grade,
                'grade_color' => $this->gradeColor($grade),
                'course_name' => $courseLabel,
                'time_label' => $slot['start'],
                'time_range' => $slot['start'].'-'.$slot['end'],
                'slot_index' => (int) $index,
                'scheduled' => $slot['scheduled'],
            ];
        });

        return $indexed
            ->groupBy('grade')
            ->map(function ($group, $grade) {
                return [
                    'grade' => $grade,
                    'count' => $group->count(),
                    'items' => $group->sortBy('time_label')->values()->all(),
                ];
            })
            ->sortBy('grade')
            ->values()
            ->all();
    }

    /**
     * @return array{start:string,end:string}
     */
    private function calendarTimeSlotForIndex(int $index): array
    {
        $safeIndex = max(0, $index);
        $startMinutes = (7 * 60) + ($safeIndex * 60);
        $endMinutes = $startMinutes + 50;

        $startHour = intdiv($startMinutes, 60) % 24;
        $startMin = $startMinutes % 60;
        $endHour = intdiv($endMinutes, 60) % 24;
        $endMin = $endMinutes % 60;

        return [
            'start' => sprintf('%02d:%02d', $startHour, $startMin),
            'end' => sprintf('%02d:%02d', $endHour, $endMin),
        ];
    }

    private function serializeUpcomingQueue($activities)
    {
        $grouped = $activities->groupBy(function ($activity) {
            $due = $activity->due_date;
            if ($due instanceof Carbon) {
                return $due->toDateString();
            }

            return (string) $due;
        });

        $slotsById = [];
        foreach ($grouped as $group) {
            foreach ($this->decorateActivitiesWithTimeSlots($group) as $item) {
                $slotsById[(int) $item['activity']->id] = $item['slot'];
            }
        }

        return $activities->values()->map(function ($activity) use ($slotsById) {
            $due = $activity->due_date instanceof Carbon
                ? $activity->due_date->toDateString()
                : (string) $activity->due_date;
            $slot = $slotsById[(int) $activity->id] ?? $this->calendarTimeSlotForIndex(0);
            $course = $activity->relationLoaded('course') ? $activity->course : null;
            $grade = GradeLabel::canonical((string) ($course?->grade ?? '')) ?? trim((string) ($course?->grade ?? ''));

            return [
                'id' => (int) $activity->id,
                'title' => (string) $activity->title,
                'due_date' => $due,
                'type' => (string) ($activity->type ?? 'actividad'),
                'type_label' => $this->activityTypeLabel($activity),
                'is_homework' => (bool) $activity->is_homework,
                'grade' => $grade,
                'grade_color' => $this->gradeColor($grade),
                'subject_name' => (string) ($course?->subject_name ?? ''),
                'course_name' => trim(($course?->subject_name ?? '').' '.($grade).(($course?->section ?? '') !== '' ? ' / '.$course->section : '')),
                'time_label' => $slot['start'],
                'time_range' => $slot['start'].'-'.$slot['end'],
                'scheduled' => (bool) ($slot['scheduled'] ?? false),
            ];
        })->values();
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{activity: Activity, slot: array{start: string, end: string, scheduled: bool}}>
     */
    private function decorateActivitiesWithTimeSlots($activities)
    {
        $list = collect($activities)->values();
        $hasColumn = Schema::hasColumn('activities', 'scheduled_time');
        $reserved = [];

        foreach ($list as $activity) {
            $label = $hasColumn ? $this->scheduledTimeLabel($activity->scheduled_time ?? null) : null;
            if ($label) {
                $reserved[$label] = true;
            }
        }

        $fallbackIndex = 0;

        return $list->map(function ($activity) use ($hasColumn, $reserved, &$fallbackIndex) {
            $label = $hasColumn ? $this->scheduledTimeLabel($activity->scheduled_time ?? null) : null;
            if ($label) {
                $slot = $this->timeSlotFromStart($label);
                $slot['scheduled'] = true;
            } else {
                do {
                    $slot = $this->calendarTimeSlotForIndex($fallbackIndex);
                    $fallbackIndex++;
                } while (isset($reserved[$slot['start']]) && $fallbackIndex < 20);
                $slot['scheduled'] = false;
            }

            return [
                'activity' => $activity,
                'slot' => $slot,
            ];
        });
    }

    private function scheduledTimeLabel(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->format('H:i');
        }

        $raw = substr((string) $value, 0, 5);

        return preg_match('/^\d{2}:\d{2}$/', $raw) === 1 ? $raw : null;
    }

    /**
     * @return array{start: string, end: string}
     */
    private function timeSlotFromStart(string $start): array
    {
        [$hour, $minute] = array_map('intval', explode(':', $start));
        $endMinutes = ($hour * 60 + $minute) + 50;

        return [
            'start' => sprintf('%02d:%02d', $hour, $minute),
            'end' => sprintf('%02d:%02d', intdiv($endMinutes, 60) % 24, $endMinutes % 60),
        ];
    }

    private function activityTypeLabel(Activity $activity): string
    {
        if ($activity->evaluation_id) {
            return 'Evaluación';
        }
        if ((bool) $activity->is_homework || ($activity->type ?? '') === Activity::TYPE_TAREA) {
            return 'Tarea';
        }
        if (($activity->type ?? '') === Activity::TYPE_CLASE) {
            return 'Clase';
        }

        return 'Actividad';
    }

    private function gradeColor(?string $grade): string
    {
        return match (GradeLabel::number($grade)) {
            1 => '#2563EB',
            2 => '#059669',
            3 => '#7C3AED',
            4 => '#D97706',
            5 => '#DB2777',
            6 => '#0891B2',
            default => '#64748B',
        };
    }
}

<?php

namespace App\Services;

use App\Models\AbsenceRequest;
use App\Models\Activity;
use App\Models\Attendance;
use App\Models\AttendanceReason;
use App\Models\CommunicationAnnouncement;
use App\Models\CommunicationAnnouncementRead;
use App\Models\CommunicationMessage;
use App\Models\CommunicationThread;
use App\Models\Course;
use App\Models\CourseEvaluationPlan;
use App\Models\CourseEvaluationPlanItem;
use App\Models\Evaluation;
use App\Models\Grade;
use App\Models\Notification;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RepresentanteDashboardService
{
    public function __construct(private AttendanceSummaryService $attendanceSummary)
    {
    }

    public function linkedStudents(User $parent): Collection
    {
        $students = collect();

        if (Schema::hasTable('guardian_student')) {
            $students = $parent->representedStudents()
                ->with(['colegio:id,name', 'courses.teacher:id,name'])
                ->get();
        }

        if ($students->isEmpty() && $parent->family_code) {
            $students = Student::query()
                ->where('family_code', $parent->family_code)
                ->when($parent->colegio_id, fn ($q) => $q->where('colegio_id', $parent->colegio_id))
                ->with(['colegio:id,name', 'courses.teacher:id,name'])
                ->orderBy('name')
                ->get();
        }

        return $students;
    }

    public function authorizeStudent(User $parent, int $studentId): Student
    {
        $student = $this->linkedStudents($parent)->firstWhere('id', $studentId);

        if (! $student) {
            throw new HttpException(403, 'No autorizado para este estudiante.');
        }

        $student->loadMissing(['colegio:id,name', 'courses.teacher:id,name']);

        return $student;
    }

    public function studentPayload(Student $student): array
    {
        return [
            'id' => $student->id,
            'name' => $student->name,
            'grade' => $student->grade,
            'section' => $student->section,
            'document_id' => $student->document_id,
            'initials' => mb_strtoupper(mb_substr($student->name, 0, 1)),
            'school' => $student->colegio?->name,
            'courses_count' => $student->courses?->count() ?? 0,
        ];
    }

    public function summary(Student $student): array
    {
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();
        $courseIds = $student->courses->pluck('id');

        $attendancePct = null;
        $attendanceLabel = 'Sin registros aún';
        $monthAbsences = 0;
        $monthTardies = 0;

        if (Schema::hasTable('attendances')) {
            $marks = Attendance::query()
                ->where('student_id', $student->id)
                ->whereBetween('attended_on', [$monthStart, $monthEnd])
                ->get(['status']);

            $total = $marks->count();
            $present = $marks->where('status', Attendance::STATUS_PRESENT)->count();
            $monthAbsences = $marks->where('status', Attendance::STATUS_ABSENT)->count();
            $monthTardies = $marks->where('status', Attendance::STATUS_TARDY)->count();

            if ($total > 0) {
                $attendancePct = round(($present / $total) * 100);
                $attendanceLabel = $attendancePct >= 90
                    ? 'Excelente asistencia'
                    : ($attendancePct >= 80 ? 'Buen progreso' : 'Requiere atención');
            }
        }

        $average = $this->globalAverage($student);
        $pending = $this->pendingTasks($student, $courseIds);
        $upcomingEvals = $this->upcomingEvaluations($courseIds);
        $absenceRequests = $this->absenceHistory($student);
        $attendanceByCourse = $this->attendanceByCourse($student);

        return [
            'attendance' => [
                'percent' => $attendancePct,
                'label' => $attendanceLabel,
                'absences' => $monthAbsences,
                'tardies' => $monthTardies,
                'by_course' => $attendanceByCourse,
            ],
            'average' => [
                'value' => $average,
                'label' => $average === null
                    ? 'Aún sin notas publicadas'
                    : ($average >= 16 ? 'Rendimiento destacado' : ($average >= 12 ? 'En buen camino' : 'Necesita apoyo')),
            ],
            'pending_tasks' => [
                'count' => $pending->count(),
                'next_date' => optional($pending->first())['due_date'] ?? null,
                'next_title' => optional($pending->first())['title'] ?? null,
                'items' => $pending->take(8)->values(),
            ],
            'evaluations' => [
                'count' => $upcomingEvals->count(),
                'next_date' => optional($upcomingEvals->first())['date'] ?? null,
                'next_title' => optional($upcomingEvals->first())['title'] ?? null,
                'items' => $upcomingEvals->take(8)->values(),
            ],
            'absence_requests' => $absenceRequests,
            'courses_count' => $student->courses->count(),
        ];
    }

    public function calendar(Student $student, ?string $month = null): array
    {
        try {
            $start = Carbon::parse(($month ?: now()->format('Y-m')).'-01')->startOfMonth();
        } catch (\Throwable) {
            $start = now()->startOfMonth();
        }
        $end = $start->copy()->endOfMonth();
        $courseIds = $student->courses->pluck('id');

        $events = collect();

        if (Schema::hasTable('activities') && $courseIds->isNotEmpty()) {
            Activity::query()
                ->whereIn('course_id', $courseIds)
                ->whereBetween('due_date', [$start->toDateString(), $end->toDateString()])
                ->with(['course:id,subject_name,teacher_id', 'course.teacher:id,name', 'teacher:id,name'])
                ->get()
                ->each(fn (Activity $activity) => $events->push($this->activityCalendarEvent($activity)));
        }

        if (Schema::hasTable('evaluations') && $courseIds->isNotEmpty()) {
            Evaluation::query()
                ->whereIn('course_id', $courseIds)
                ->whereIn('status', ['published', 'scheduled', 'graded'])
                ->whereBetween('scheduled_at', [$start, $end])
                ->with([
                    'course:id,subject_name,teacher_id',
                    'course.teacher:id,name',
                    'teacher:id,name',
                    'activity:id,weight_percentage,max_score',
                ])
                ->get()
                ->each(fn (Evaluation $evaluation) => $events->push($this->evaluationCalendarEvent($evaluation)));
        }

        if (Schema::hasTable('course_evaluation_plan_items') && $courseIds->isNotEmpty()) {
            CourseEvaluationPlanItem::query()
                ->whereHas('plan', fn ($q) => $q->whereIn('course_id', $courseIds))
                ->whereBetween('due_date', [$start->toDateString(), $end->toDateString()])
                ->with(['plan.course:id,subject_name,teacher_id', 'plan.course.teacher:id,name'])
                ->get()
                ->each(fn (CourseEvaluationPlanItem $item) => $events->push($this->planItemCalendarEvent($item)));
        }

        if (Schema::hasTable('attendances')) {
            Attendance::query()
                ->where('student_id', $student->id)
                ->whereBetween('attended_on', [$start->toDateString(), $end->toDateString()])
                ->whereIn('status', [Attendance::STATUS_ABSENT, Attendance::STATUS_TARDY])
                ->with(['course:id,subject_name,teacher_id', 'course.teacher:id,name'])
                ->get()
                ->each(fn (Attendance $row) => $events->push($this->attendanceCalendarEvent($row)));
        }

        $dated = $events->filter(fn ($e) => ! empty($e['date']))->values();
        $byDay = $dated
            ->groupBy('date')
            ->map(fn ($group) => $group->values())
            ->toArray();

        return [
            'month' => $start->format('Y-m'),
            'label' => $start->locale('es')->translatedFormat('F Y'),
            'total_events' => $dated->count(),
            'events' => $byDay,
        ];
    }

    private function activityCalendarEvent(Activity $activity): array
    {
        $type = $activity->type === Activity::TYPE_CLASE
            ? 'class'
            : ($activity->type === Activity::TYPE_TAREA || $activity->is_homework ? 'task' : 'activity');

        return $this->calendarEventPayload(
            id: 'act-'.$activity->id,
            type: $type,
            typeLabel: $type === 'class' ? 'Clase' : ($type === 'task' ? 'Tarea' : 'Actividad'),
            title: (string) $activity->title,
            course: $activity->course?->subject_name,
            teacher: $activity->course?->teacher?->name ?? $activity->teacher?->name,
            description: $this->eventDescription($activity->description, $activity->notes, $activity->director_notes),
            date: $activity->due_date?->format('Y-m-d'),
            timeLabel: $this->formatTimeLabel($activity->scheduled_time),
            topic: null,
            weight: $activity->weight_percentage,
            maxScore: $activity->max_score,
            extra: [
                'course_id' => $activity->course_id,
                'source_id' => $activity->id,
                'notes' => $this->nullableText($activity->notes),
                'director_notes' => $this->nullableText($activity->director_notes),
                'body' => $this->fullEventBody($activity->description, $activity->notes, $activity->director_notes),
            ],
        );
    }

    private function evaluationCalendarEvent(Evaluation $evaluation): array
    {
        $topic = trim((string) $evaluation->topic);
        $description = $this->eventDescription(
            $evaluation->description,
            $evaluation->instructions,
            $topic !== '' ? 'Tema: '.$topic : null,
            'Evaluación planificada. Revisa el tema, la ponderación y los puntos.'
        );

        return $this->calendarEventPayload(
            id: 'eval-'.$evaluation->id,
            type: 'evaluation',
            typeLabel: 'Evaluación',
            title: (string) $evaluation->title,
            course: $evaluation->course?->subject_name,
            teacher: $evaluation->course?->teacher?->name ?? $evaluation->teacher?->name,
            description: $description,
            date: optional($evaluation->scheduled_at)?->format('Y-m-d'),
            timeLabel: optional($evaluation->scheduled_at)?->format('H:i'),
            topic: $topic !== '' ? $topic : null,
            weight: $evaluation->activity?->weight_percentage,
            maxScore: $evaluation->total_points ?? $evaluation->activity?->max_score,
            extra: [
                'course_id' => $evaluation->course_id,
                'source_id' => $evaluation->id,
                'total_points' => $evaluation->total_points,
                'passing_score' => $evaluation->passing_score,
                'difficulty' => $evaluation->difficulty,
                'instructions' => $this->nullableText($evaluation->instructions),
                'body' => $this->fullEventBody($evaluation->description, $evaluation->instructions),
            ],
        );
    }

    private function planItemCalendarEvent(CourseEvaluationPlanItem $item): array
    {
        $course = $item->plan?->course;
        $category = $item->category === 'formative' ? 'Formativa' : 'Sumativa';

        return $this->calendarEventPayload(
            id: 'plan-'.$item->id,
            type: 'plan',
            typeLabel: 'Unidad evaluativa',
            title: (string) $item->unit_name,
            course: $course?->subject_name,
            teacher: $course?->teacher?->name,
            description: $this->eventDescription(
                $item->notes,
                $item->learning_outcome,
                trim(($item->assessment_type ?: 'Evaluación').' · '.$category)
            ),
            date: optional($item->due_date)?->format('Y-m-d'),
            timeLabel: null,
            topic: $item->assessment_type,
            weight: $item->weight_percentage,
            extra: [
                'course_id' => $course?->id,
                'source_id' => $item->id,
                'assessment_type' => $item->assessment_type,
                'category' => $item->category,
                'notes' => $this->nullableText($item->notes),
                'learning_outcome' => $this->nullableText($item->learning_outcome),
                'body' => $this->fullEventBody($item->notes, $item->learning_outcome),
            ],
        );
    }

    private function attendanceCalendarEvent(Attendance $row): array
    {
        $tardy = $row->status === Attendance::STATUS_TARDY;

        return $this->calendarEventPayload(
            id: 'att-'.$row->id,
            type: $tardy ? 'tardy' : 'absence',
            typeLabel: $tardy ? 'Retraso' : 'Ausencia',
            title: $tardy ? 'Retraso' : 'Ausencia',
            course: $row->course?->subject_name,
            teacher: $row->course?->teacher?->name,
            description: $tardy ? 'Se registró llegada tarde este día.' : 'Se registró ausencia este día.',
            date: $row->attended_on?->format('Y-m-d'),
            extra: [
                'course_id' => $row->course_id,
            ],
        );
    }

    private function calendarEventPayload(
        string $id,
        string $type,
        string $typeLabel,
        string $title,
        ?string $course,
        ?string $teacher,
        string $description,
        ?string $date,
        ?string $timeLabel = null,
        ?string $topic = null,
        mixed $weight = null,
        mixed $maxScore = null,
        array $extra = [],
    ): array {
        return array_filter([
            'id' => $id,
            'type' => $type,
            'type_label' => $typeLabel,
            'title' => $title,
            'course' => $course,
            'teacher' => $teacher,
            'description' => $description,
            'date' => $date,
            'time_label' => $timeLabel,
            'topic' => $topic,
            'weight_percentage' => $weight !== null ? (float) $weight : null,
            'max_score' => $maxScore !== null ? (float) $maxScore : null,
            'color' => $this->eventColor($type),
            ...$extra,
        ], fn ($value) => $value !== null && $value !== '');
    }

    private function eventColor(string $type): string
    {
        return match ($type) {
            'class' => '#2563EB',
            'task' => '#F59E0B',
            'activity' => '#7C3AED',
            'evaluation' => '#DC2626',
            'plan' => '#EC4899',
            'absence' => '#FB7185',
            'tardy' => '#F97316',
            default => '#7C3AED',
        };
    }

    private function formatTimeLabel(mixed $value): ?string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        return substr($text, 0, 5);
    }

    private function eventDescription(?string ...$candidates): string
    {
        foreach ($candidates as $candidate) {
            $text = trim((string) $candidate);
            if ($text !== '') {
                return $text;
            }
        }

        return 'Sin descripción detallada aún. El docente puede ampliarla en el plan de clase.';
    }

    private function fullEventBody(?string ...$parts): ?string
    {
        $body = collect($parts)
            ->map(fn ($part) => trim((string) $part))
            ->filter()
            ->unique()
            ->implode("\n\n");

        return $body !== '' ? $body : null;
    }

    private function nullableText(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text !== '' ? $text : null;
    }

    public function subjects(Student $student): array
    {
        return $student->courses->map(function (Course $course) use ($student) {
            $detail = $this->subjectMetrics($student, $course);

            return [
                'id' => $course->id,
                'name' => $course->subject_name,
                'grade' => $course->grade,
                'section' => $course->section,
                'teacher' => $course->teacher?->name ?? 'Docente',
                'average' => $detail['average'],
                'trend' => $detail['trend'],
                'last_evaluation' => $detail['last_evaluation'],
                'next_activity' => $detail['next_activity'],
            ];
        })->values()->all();
    }

    public function subjectDetail(Student $student, Course $course): array
    {
        abort_unless($student->courses->contains('id', $course->id), 404);

        $metrics = $this->subjectMetrics($student, $course);
        $items = $this->gradedItems($student, $course);

        $history = $items
            ->filter(fn ($row) => $row['score'] !== null)
            ->map(fn ($row) => [
                'label' => $row['title'],
                'score' => $row['score'],
                'max_score' => $row['max_score'],
                'date' => $row['date'],
            ])
            ->values();

        return [
            'id' => $course->id,
            'name' => $course->subject_name,
            'teacher' => $course->teacher?->name ?? 'Docente',
            'average' => $metrics['average'],
            'trend' => $metrics['trend'],
            'history' => $history,
            'items' => $items->values(),
            'evaluation_plan' => $this->evaluationPlanItems($course),
            'attendance' => $this->attendanceSummary->percentForStudentInCourse($student, $course),
        ];
    }

    public function announcements(User $parent, Student $student): array
    {
        if (! Schema::hasTable('communication_announcements')) {
            return [];
        }

        $courseIds = $student->courses->pluck('id')->all();
        $query = CommunicationAnnouncement::query()
            ->with('teacher:id,name')
            ->where('status', 'sent')
            ->when($student->colegio_id, fn ($q) => $q->where('colegio_id', $student->colegio_id))
            ->latest('sent_at')
            ->limit(40);

        $rows = $query->get()->filter(function (CommunicationAnnouncement $announcement) use ($courseIds) {
            $targeting = $announcement->targeting ?? [];
            $audience = $targeting['audience_type'] ?? 'students';
            if (! in_array($audience, ['students', 'parents', 'representantes', 'all', 'families', null, ''], true)) {
                return false;
            }
            $courseId = $targeting['course_id'] ?? null;

            return empty($courseId) || in_array((int) $courseId, $courseIds, true);
        })->values();

        $readIds = Schema::hasTable('communication_announcement_reads')
            ? CommunicationAnnouncementRead::query()
                ->whereIn('announcement_id', $rows->pluck('id'))
                ->where(function ($q) use ($parent, $student) {
                    $q->where('student_id', $student->id);
                    if (Schema::hasColumn('communication_announcement_reads', 'user_id')) {
                        $q->orWhere('user_id', $parent->id);
                    }
                })
                ->whereNotNull('read_at')
                ->pluck('announcement_id')
                ->all()
            : [];

        return $rows->map(function (CommunicationAnnouncement $announcement) use ($readIds) {
            return [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'body' => $announcement->body,
                'date' => optional($announcement->sent_at ?? $announcement->created_at)?->toIso8601String(),
                'author' => $announcement->teacher?->name ?? 'Colegio',
                'attachments' => $announcement->attachments ?? [],
                'read' => in_array($announcement->id, $readIds, true),
                'official' => in_array(($announcement->targeting['audience_type'] ?? ''), ['all', 'families', 'representantes'], true)
                    || empty($announcement->targeting['course_id'] ?? null),
            ];
        })->values()->all();
    }

    public function markAnnouncementRead(User $parent, Student $student, int $announcementId): void
    {
        if (! Schema::hasTable('communication_announcement_reads')) {
            return;
        }

        $payload = [
            'announcement_id' => $announcementId,
            'student_id' => $student->id,
            'recipient_name' => $parent->name,
            'recipient_type' => 'representante',
            'read_at' => now(),
        ];

        $query = CommunicationAnnouncementRead::query()
            ->where('announcement_id', $announcementId)
            ->where('student_id', $student->id);

        $row = $query->first();
        if ($row) {
            if (! $row->read_at) {
                $row->update(['read_at' => now(), 'recipient_type' => 'representante']);
            }

            return;
        }

        CommunicationAnnouncementRead::create($payload);
    }

    public function threads(User $parent, Student $student): array
    {
        if (! Schema::hasTable('communication_threads')) {
            return [];
        }

        return CommunicationThread::query()
            ->where('student_id', $student->id)
            ->with(['teacher:id,name', 'messages' => fn ($q) => $q->latest()->limit(1)])
            ->orderByDesc('last_message_at')
            ->get()
            ->map(function (CommunicationThread $thread) use ($student) {
                $unread = $thread->messages()
                    ->whereNull('read_at')
                    ->where('sender_role', '!=', 'representante')
                    ->where('sender_role', '!=', 'parent')
                    ->count();
                $course = $student->courses->firstWhere('teacher_id', $thread->teacher_id);

                return [
                    'id' => $thread->id,
                    'teacher' => $thread->teacher?->name ?? $thread->contact_name ?? 'Docente',
                    'teacher_id' => $thread->teacher_id,
                    'course_id' => $course?->id,
                    'course' => $course?->subject_name,
                    'preview' => $thread->last_message_preview,
                    'last_at' => optional($thread->last_message_at)?->toIso8601String(),
                    'unread' => $unread,
                ];
            })
            ->values()
            ->all();
    }

    public function threadMessages(User $parent, Student $student, CommunicationThread $thread): array
    {
        abort_unless((int) $thread->student_id === (int) $student->id, 403);

        $thread->messages()
            ->whereNull('read_at')
            ->whereNotIn('sender_role', ['representante', 'parent'])
            ->update(['read_at' => now()]);

        return [
            'id' => $thread->id,
            'teacher' => $thread->teacher?->name ?? $thread->contact_name ?? 'Docente',
            'teacher_id' => $thread->teacher_id,
            'course_id' => $student->courses->firstWhere('teacher_id', $thread->teacher_id)?->id,
            'messages' => $thread->messages()->orderBy('created_at')->get()->map(fn (CommunicationMessage $m) => [
                'id' => $m->id,
                'body' => $m->body,
                'mine' => in_array($m->sender_role, ['representante', 'parent'], true),
                'role' => $m->sender_role,
                'at' => optional($m->created_at)?->toIso8601String(),
            ])->values(),
        ];
    }

    public function sendMessage(User $parent, Student $student, CommunicationThread $thread, string $body): CommunicationMessage
    {
        abort_unless((int) $thread->student_id === (int) $student->id, 403);

        $message = $thread->messages()->create([
            'sender_role' => 'representante',
            'body' => $body,
        ]);

        $thread->update([
            'last_message_preview' => mb_substr($body, 0, 160),
            'last_message_at' => now(),
            'contact_name' => $parent->name,
            'contact_role' => 'representante',
        ]);

        if (Schema::hasTable('notifications') && $thread->teacher_id) {
            Notification::create([
                'user_id' => $thread->teacher_id,
                'colegio_id' => $student->colegio_id,
                'title' => 'Mensaje de la familia',
                'message' => $parent->name.' escribió sobre '.$student->name.': '.mb_substr($body, 0, 120),
                'link' => route('teacher.communication.index'),
            ]);
        }

        return $message;
    }

    public function startThread(User $parent, Student $student, int $courseId, string $body): CommunicationThread
    {
        $course = $student->courses->firstWhere('id', $courseId);
        abort_unless($course, 404);

        $thread = CommunicationThread::query()->firstOrCreate(
            [
                'teacher_id' => $course->teacher_id,
                'student_id' => $student->id,
            ],
            [
                'contact_name' => $parent->name,
                'contact_role' => 'representante',
            ]
        );

        $thread->fill([
            'contact_name' => $parent->name,
            'contact_role' => 'representante',
        ])->save();

        $this->sendMessage($parent, $student, $thread, $body);

        return $thread->fresh();
    }

    public function reasons(User $parent): Collection
    {
        if (! Schema::hasTable('attendance_reasons')) {
            return collect();
        }

        return AttendanceReason::query()
            ->where(function ($q) use ($parent) {
                $q->whereNull('colegio_id');
                if ($parent->colegio_id) {
                    $q->orWhere('colegio_id', $parent->colegio_id);
                }
            })
            ->whereIn('category', ['excused', 'unexcused', 'tardy'])
            ->orderBy('sort_order')
            ->get(['id', 'code', 'label', 'category', 'requires_comment']);
    }

    public function notifications(User $parent): array
    {
        if (! Schema::hasTable('notifications')) {
            return ['unread' => 0, 'items' => []];
        }

        $items = Notification::where('user_id', $parent->id)
            ->latest()
            ->limit(20)
            ->get(['id', 'title', 'message', 'link', 'created_at', 'read_at']);

        return [
            'unread' => $items->whereNull('read_at')->count(),
            'items' => $items->map(fn (Notification $n) => [
                'id' => $n->id,
                'title' => $n->title,
                'message' => $n->message,
                'link' => $n->link,
                'read' => (bool) $n->read_at,
                'at' => optional($n->created_at)?->toIso8601String(),
            ])->values(),
        ];
    }

    public function markNotificationsRead(User $parent): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        Notification::where('user_id', $parent->id)->whereNull('read_at')->update(['read_at' => now()]);
    }

    public function reportCardData(Student $student): array
    {
        return app(ReportCardService::class)->build($student, publishedOnly: true);
    }

    private function recordedGrades(Student $student, Collection $activityIds): Collection
    {
        if ($activityIds->isEmpty() || ! Schema::hasTable('grades')) {
            return collect();
        }

        return Grade::query()
            ->where('student_id', $student->id)
            ->whereIn('activity_id', $activityIds)
            ->whereNotNull('score')
            ->when(Schema::hasColumn('grades', 'status'), fn ($q) => $q->where('status', 'published'))
            ->get();
    }

    private function absenceHistory(Student $student): array
    {
        if (! Schema::hasTable('absence_requests')) {
            return [];
        }

        return AbsenceRequest::query()
            ->where('student_id', $student->id)
            ->with('reason:id,label')
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (AbsenceRequest $row) => [
                'id' => $row->id,
                'kind' => $row->kind,
                'status' => $row->status,
                'reason' => $row->reason?->label,
                'start' => optional($row->start_date)?->format('Y-m-d'),
                'end' => optional($row->end_date)?->format('Y-m-d'),
                'comment' => $row->comment,
            ])
            ->values()
            ->all();
    }

    private function globalAverage(Student $student): ?float
    {
        $averages = $student->courses
            ->map(fn (Course $course) => $this->courseAverage($student, $course))
            ->filter(fn ($v) => $v !== null);

        if ($averages->isEmpty()) {
            return null;
        }

        return round((float) $averages->avg(), 1);
    }

    private function courseAverage(Student $student, Course $course): ?float
    {
        $activities = $course->activities()
            ->where(fn ($q) => $q->whereNull('type')->orWhere('type', '!=', 'clase'))
            ->get(['id', 'weight_percentage', 'max_score']);

        $grades = $this->recordedGrades($student, $activities->pluck('id'))->keyBy('activity_id');
        if ($grades->isEmpty()) {
            $pivot = $course->pivot->promedio_acumulado ?? $course->pivot->nota_actual ?? null;
            if ($pivot === null || (float) $pivot <= 0) {
                return null;
            }

            return round((float) $pivot, 1);
        }

        $weighted = 0.0;
        $weightSum = 0.0;
        foreach ($activities as $activity) {
            $grade = $grades->get($activity->id);
            if (! $grade || $grade->score === null) {
                continue;
            }
            $weight = (float) ($activity->weight_percentage ?? 0);
            if ($weight > 0) {
                $weighted += ((float) $grade->score * $weight) / 100;
                $weightSum += $weight;
            }
        }

        if ($weightSum > 0) {
            return round($weighted, 1);
        }

        return round((float) $grades->avg('score'), 1);
    }

    private function subjectMetrics(Student $student, Course $course): array
    {
        $items = $this->gradedItems($student, $course);
        $scored = $items->filter(fn ($row) => $row['score'] !== null)->values();
        $recent = $scored->take(-3)->avg('score');
        $previous = $scored->slice(-6, 3)->avg('score');
        $trend = 'flat';
        if ($recent !== null && $previous !== null) {
            if ($recent - $previous >= 0.8) {
                $trend = 'up';
            } elseif ($previous - $recent >= 0.8) {
                $trend = 'down';
            }
        }

        $lastEval = $items->first(fn ($row) => $row['type'] === 'evaluation' || $row['score'] !== null);
        $next = $items->first(fn ($row) => $row['score'] === null && $row['date'] && $row['date'] >= now()->toDateString());

        return [
            'average' => $this->courseAverage($student, $course),
            'trend' => $trend,
            'last_evaluation' => $lastEval ? [
                'title' => $lastEval['title'],
                'date' => $lastEval['date'],
            ] : null,
            'next_activity' => $next ? [
                'title' => $next['title'],
                'date' => $next['date'],
            ] : null,
        ];
    }

    /**
     * Evaluation Plan items for a course, so the family can see upcoming units/weights
     * exactly as configured by the teacher (same source as the teacher and Director views).
     */
    private function evaluationPlanItems(Course $course): array
    {
        if (! Schema::hasTable('course_evaluation_plans')) {
            return [];
        }

        $plan = CourseEvaluationPlan::where('course_id', $course->id)
            ->where('teacher_id', $course->teacher_id)
            ->with('items')
            ->first();

        if (! $plan) {
            return [];
        }

        return $plan->items
            ->sortBy('due_date')
            ->map(fn ($item) => [
                'unit_name' => $item->unit_name,
                'assessment_type' => $item->assessment_type,
                'category' => $item->category,
                'weight_percentage' => (float) $item->weight_percentage,
                'due_date' => optional($item->due_date)->format('Y-m-d'),
            ])
            ->values()
            ->all();
    }

    private function attendanceByCourse(Student $student): array
    {
        return $student->courses->map(function (Course $course) use ($student) {
            $stats = $this->attendanceSummary->percentForStudentInCourse($student, $course);

            return [
                'course_id' => $course->id,
                'course' => $course->subject_name,
                'percentage' => $stats['percentage'],
                'present' => $stats['present'],
                'absent' => $stats['absent'],
                'tardy' => $stats['tardy'],
            ];
        })->values()->all();
    }

    private function gradedItems(Student $student, Course $course): Collection
    {
        $activities = $course->activities()
            ->where(fn ($q) => $q->whereNull('type')->orWhere('type', '!=', 'clase'))
            ->orderBy('due_date')
            ->get(['id', 'title', 'type', 'due_date', 'max_score', 'weight_percentage', 'director_notes']);

        $grades = $this->recordedGrades($student, $activities->pluck('id'))->keyBy('activity_id');

        return $activities->map(function (Activity $activity) use ($grades) {
            $grade = $grades->get($activity->id);

            return [
                'id' => $activity->id,
                'title' => $activity->title,
                'type' => $activity->type,
                'date' => $activity->due_date?->format('Y-m-d'),
                'score' => $grade?->score !== null ? (float) $grade->score : null,
                'max_score' => (float) ($activity->max_score ?? 20),
                'weight' => (float) ($activity->weight_percentage ?? 0),
                'feedback' => $grade?->feedback_text,
            ];
        });
    }

    private function pendingTasks(Student $student, Collection $courseIds): Collection
    {
        if ($courseIds->isEmpty() || ! Schema::hasTable('activities')) {
            return collect();
        }

        $activities = Activity::query()
            ->whereIn('course_id', $courseIds)
            ->where(function ($q) {
                $q->where('type', Activity::TYPE_TAREA)->orWhere('is_homework', true);
            })
            ->whereDate('due_date', '>=', now()->toDateString())
            ->orderBy('due_date')
            ->with(['course:id,subject_name,teacher_id', 'course.teacher:id,name'])
            ->get();

        $gradedIds = $this->recordedGrades($student, $activities->pluck('id'))->pluck('activity_id');

        return $activities
            ->reject(fn (Activity $a) => $gradedIds->contains($a->id))
            ->map(fn (Activity $a) => [
                'id' => 'act-'.$a->id,
                'type' => 'task',
                'type_label' => 'Tarea',
                'title' => $a->title,
                'due_date' => $a->due_date?->format('Y-m-d'),
                'date' => $a->due_date?->format('Y-m-d'),
                'course' => $a->course?->subject_name,
                'course_id' => $a->course_id,
                'teacher' => $a->course?->teacher?->name,
                'weight_percentage' => $a->weight_percentage !== null ? (float) $a->weight_percentage : null,
                'max_score' => $a->max_score !== null ? (float) $a->max_score : null,
                'description' => $this->eventDescription($a->description, $a->notes),
                'notes' => $this->nullableText($a->notes),
                'body' => $this->fullEventBody($a->description, $a->notes),
                'color' => $this->eventColor('task'),
            ])
            ->values();
    }

    private function upcomingEvaluations(Collection $courseIds): Collection
    {
        if ($courseIds->isEmpty() || ! Schema::hasTable('evaluations')) {
            return collect();
        }

        return Evaluation::query()
            ->whereIn('course_id', $courseIds)
            ->whereIn('status', ['published', 'scheduled'])
            ->where('scheduled_at', '>=', now())
            ->orderBy('scheduled_at')
            ->with([
                'course:id,subject_name,teacher_id',
                'course.teacher:id,name',
                'activity:id,weight_percentage,max_score',
            ])
            ->get()
            ->map(fn (Evaluation $e) => [
                'id' => 'eval-'.$e->id,
                'type' => 'evaluation',
                'type_label' => 'Evaluación',
                'title' => $e->title,
                'date' => optional($e->scheduled_at)?->format('Y-m-d'),
                'course' => $e->course?->subject_name,
                'course_id' => $e->course_id,
                'teacher' => $e->course?->teacher?->name,
                'topic' => $e->topic,
                'description' => $this->eventDescription($e->description, $e->instructions, $e->topic),
                'instructions' => $this->nullableText($e->instructions),
                'body' => $this->fullEventBody($e->description, $e->instructions),
                'weight_percentage' => $e->activity?->weight_percentage !== null
                    ? (float) $e->activity->weight_percentage
                    : null,
                'max_score' => $e->total_points !== null ? (float) $e->total_points : null,
                'color' => $this->eventColor('evaluation'),
            ])
            ->values();
    }
}

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

        return [
            'attendance' => [
                'percent' => $attendancePct,
                'label' => $attendanceLabel,
                'absences' => $monthAbsences,
                'tardies' => $monthTardies,
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
            ],
            'evaluations' => [
                'count' => $upcomingEvals->count(),
                'next_date' => optional($upcomingEvals->first())['date'] ?? null,
                'next_title' => optional($upcomingEvals->first())['title'] ?? null,
            ],
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
                ->with('course:id,subject_name')
                ->get()
                ->each(function (Activity $activity) use ($events) {
                    $type = $activity->type === Activity::TYPE_CLASE
                        ? 'class'
                        : ($activity->type === Activity::TYPE_TAREA || $activity->is_homework ? 'task' : 'activity');

                    $events->push([
                        'id' => 'act-'.$activity->id,
                        'type' => $type,
                        'title' => $activity->title,
                        'course' => $activity->course?->subject_name,
                        'date' => $activity->due_date?->format('Y-m-d'),
                    ]);
                });
        }

        if (Schema::hasTable('evaluations') && $courseIds->isNotEmpty()) {
            Evaluation::query()
                ->whereIn('course_id', $courseIds)
                ->whereIn('status', ['published', 'scheduled', 'graded'])
                ->whereBetween('scheduled_at', [$start, $end])
                ->with('course:id,subject_name')
                ->get()
                ->each(function (Evaluation $evaluation) use ($events) {
                    $events->push([
                        'id' => 'eval-'.$evaluation->id,
                        'type' => 'evaluation',
                        'title' => $evaluation->title,
                        'course' => $evaluation->course?->subject_name,
                        'date' => optional($evaluation->scheduled_at)?->format('Y-m-d'),
                    ]);
                });
        }

        if (Schema::hasTable('attendances')) {
            Attendance::query()
                ->where('student_id', $student->id)
                ->whereBetween('attended_on', [$start->toDateString(), $end->toDateString()])
                ->whereIn('status', [Attendance::STATUS_ABSENT, Attendance::STATUS_TARDY])
                ->with('course:id,subject_name')
                ->get()
                ->each(function (Attendance $row) use ($events) {
                    $events->push([
                        'id' => 'att-'.$row->id,
                        'type' => $row->status === Attendance::STATUS_TARDY ? 'tardy' : 'absence',
                        'title' => $row->status === Attendance::STATUS_TARDY ? 'Retraso' : 'Ausencia',
                        'course' => $row->course?->subject_name,
                        'date' => $row->attended_on?->format('Y-m-d'),
                    ]);
                });
        }

        $byDay = $events
            ->filter(fn ($e) => ! empty($e['date']))
            ->groupBy('date')
            ->map(fn ($group) => $group->values())
            ->toArray();

        return [
            'month' => $start->format('Y-m'),
            'label' => $start->locale('es')->translatedFormat('F Y'),
            'events' => $byDay,
        ];
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
            ->map(function (CommunicationThread $thread) {
                $unread = $thread->messages()
                    ->whereNull('read_at')
                    ->where('sender_role', '!=', 'representante')
                    ->where('sender_role', '!=', 'parent')
                    ->count();

                return [
                    'id' => $thread->id,
                    'teacher' => $thread->teacher?->name ?? $thread->contact_name ?? 'Docente',
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
        ]);

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
        $courseData = $student->courses->map(function (Course $course) use ($student) {
            $items = $this->gradedItems($student, $course);
            $average = $this->courseAverage($student, $course);

            return [
                'course_name' => $course->subject_name.' '.$course->grade.($course->section ? ' / '.$course->section : ''),
                'teacher_name' => $course->teacher?->name ?? '—',
                'promedio' => $average !== null ? round(($average / 20) * 100, 1) : 0,
                'activities' => $items->map(fn ($row) => [
                    'title' => $row['title'],
                    'type' => $row['type'],
                    'score' => $row['score'] ?? 0,
                    'max_score' => $row['max_score'] ?? 20,
                    'percentage' => ($row['max_score'] ?? 0) > 0 && $row['score'] !== null
                        ? round(($row['score'] / $row['max_score']) * 100, 1)
                        : 0,
                    'due_date' => $row['date'] ? Carbon::parse($row['date'])->format('d/m/Y') : null,
                ]),
            ];
        });

        return [
            'courseData' => $courseData,
            'globalAverage' => $courseData->avg('promedio'),
        ];
    }

    private function publishedGrades(Student $student, Collection $activityIds): Collection
    {
        if ($activityIds->isEmpty() || ! Schema::hasTable('grades')) {
            return collect();
        }

        $query = Grade::query()
            ->where('student_id', $student->id)
            ->whereIn('activity_id', $activityIds);

        if (Schema::hasColumn('grades', 'status')) {
            $query->where(function ($q) {
                $q->where('status', 'published')->orWhereNotNull('published_at');
            });
        } elseif (Schema::hasColumn('grades', 'published_at')) {
            $query->whereNotNull('published_at');
        }

        return $query->get();
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

        $grades = $this->publishedGrades($student, $activities->pluck('id'))->keyBy('activity_id');
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

    private function gradedItems(Student $student, Course $course): Collection
    {
        $activities = $course->activities()
            ->where(fn ($q) => $q->whereNull('type')->orWhere('type', '!=', 'clase'))
            ->orderBy('due_date')
            ->get(['id', 'title', 'type', 'due_date', 'max_score', 'weight_percentage', 'director_notes']);

        $grades = $this->publishedGrades($student, $activities->pluck('id'))->keyBy('activity_id');

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
            ->with('course:id,subject_name')
            ->get();

        $gradedIds = $this->publishedGrades($student, $activities->pluck('id'))->pluck('activity_id');

        return $activities
            ->reject(fn (Activity $a) => $gradedIds->contains($a->id))
            ->map(fn (Activity $a) => [
                'title' => $a->title,
                'due_date' => $a->due_date?->format('Y-m-d'),
                'course' => $a->course?->subject_name,
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
            ->with('course:id,subject_name')
            ->get()
            ->map(fn (Evaluation $e) => [
                'title' => $e->title,
                'date' => optional($e->scheduled_at)?->format('Y-m-d'),
                'course' => $e->course?->subject_name,
            ])
            ->values();
    }
}

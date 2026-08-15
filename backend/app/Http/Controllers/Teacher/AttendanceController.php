<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\AbsenceRequest;
use App\Models\Attendance;
use App\Models\AttendanceReason;
use App\Models\Course;
use App\Models\Notification;
use App\Models\Student;
use App\Services\AttendanceAlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function __construct(private AttendanceAlertService $alerts)
    {
    }

    public function index(): View
    {
        $teacher = auth()->user();
        $courses = Course::where('teacher_id', $teacher->id)
            ->withCount('students')
            ->orderBy('subject_name')
            ->get(['id', 'subject_name', 'grade', 'section']);

        $reasons = $this->reasons($teacher->colegio_id);
        $alerts = collect();
        if (Schema::hasTable('notifications')) {
            $alerts = Notification::where('user_id', $teacher->id)
                ->where(function ($q) {
                    $q->where('title', 'like', '%ausencia%')
                        ->orWhere('title', 'like', '%asistencia%')
                        ->orWhere('title', 'like', '%Familia reportó%');
                })
                ->latest()
                ->limit(8)
                ->get(['id', 'title', 'message', 'created_at']);
        }

        return view('teacher.attendance.index', compact('teacher', 'courses', 'reasons', 'alerts'));
    }

    public function roster(Request $request): JsonResponse
    {
        if (! Schema::hasTable('attendances')) {
            return response()->json(['success' => false, 'error' => 'El módulo de asistencia aún no está disponible.'], 503);
        }

        $data = $request->validate([
            'course_id' => 'required|integer',
            'date' => 'required|date',
        ]);

        $teacher = auth()->user();
        $course = Course::where('id', $data['course_id'])
            ->where('teacher_id', $teacher->id)
            ->firstOrFail();

        $students = $course->students()->orderBy('name')->get(['students.id', 'students.name', 'students.family_code']);
        $date = $data['date'];

        $existing = Attendance::where('course_id', $course->id)
            ->whereDate('attended_on', $date)
            ->get()
            ->keyBy('student_id');

        $requests = AbsenceRequest::whereIn('student_id', $students->pluck('id'))
            ->where('status', 'pending')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->with('reason:id,label,code')
            ->get()
            ->groupBy('student_id');

        $roster = $students->map(function (Student $student) use ($existing, $requests) {
            $row = $existing->get($student->id);
            $familyRequest = optional($requests->get($student->id))->first();

            return [
                'student_id' => $student->id,
                'name' => $student->name,
                'status' => $row?->status ?? ($familyRequest ? ($familyRequest->kind === 'tardy' ? 'tardy' : 'absent') : 'present'),
                'reason_id' => $row?->reason_id ?? $familyRequest?->reason_id,
                'note' => $row?->note ?? $familyRequest?->comment,
                'notified_at' => optional($row?->notified_at)->toDateTimeString(),
                'source' => $row?->source,
                'client_uuid' => $row?->client_uuid,
                'family_request' => $familyRequest ? [
                    'id' => $familyRequest->id,
                    'kind' => $familyRequest->kind,
                    'label' => $familyRequest->reason?->label ?? 'Reportado por la familia',
                    'comment' => $familyRequest->comment,
                ] : null,
            ];
        })->values();

        $taken = $existing->isNotEmpty();

        return response()->json([
            'success' => true,
            'course' => [
                'id' => $course->id,
                'name' => $course->subject_name.' · '.$course->grade.($course->section ? ' / '.$course->section : ''),
            ],
            'date' => $date,
            'taken' => $taken,
            'roster' => $roster,
        ]);
    }

    public function save(Request $request): JsonResponse
    {
        if (! Schema::hasTable('attendances')) {
            return response()->json(['success' => false, 'error' => 'El módulo de asistencia aún no está disponible.'], 503);
        }

        $data = $request->validate([
            'course_id' => 'required|integer',
            'date' => 'required|date',
            'entries' => 'required|array|min:1',
            'entries.*.student_id' => 'required|integer',
            'entries.*.status' => 'required|in:present,absent,tardy',
            'entries.*.reason_id' => 'nullable|integer',
            'entries.*.note' => 'nullable|string|max:500',
            'entries.*.client_uuid' => 'nullable|uuid',
        ]);

        $teacher = auth()->user();
        $course = Course::where('id', $data['course_id'])
            ->where('teacher_id', $teacher->id)
            ->firstOrFail();

        $enrolledIds = $course->students()->pluck('students.id')->all();
        $alerts = [];

        foreach ($data['entries'] as $entry) {
            if (! in_array((int) $entry['student_id'], $enrolledIds, true)) {
                continue;
            }

            $previous = null;
            if (! empty($entry['client_uuid'])) {
                $previous = Attendance::where('client_uuid', $entry['client_uuid'])->first();
            }
            if (! $previous) {
                $previous = Attendance::where('course_id', $course->id)
                    ->where('student_id', $entry['student_id'])
                    ->whereDate('attended_on', $data['date'])
                    ->first();
            }

            $payload = [
                'colegio_id' => $teacher->colegio_id,
                'course_id' => $course->id,
                'student_id' => $entry['student_id'],
                'teacher_id' => $teacher->id,
                'attended_on' => $data['date'],
                'status' => $entry['status'],
                'reason_id' => $entry['reason_id'] ?? null,
                'note' => $entry['note'] ?? null,
                'source' => 'teacher',
            ];

            if (! empty($entry['client_uuid'])) {
                $payload['client_uuid'] = $entry['client_uuid'];
            } elseif ($previous?->client_uuid) {
                $payload['client_uuid'] = $previous->client_uuid;
            }

            if ($entry['status'] === Attendance::STATUS_PRESENT) {
                $payload['notified_at'] = null;
                $payload['reason_id'] = null;
            }

            if ($previous) {
                $previous->fill($payload);
                $previous->save();
                $attendance = $previous->fresh(['student', 'course', 'reason']);
            } else {
                $attendance = Attendance::create($payload)->fresh(['student', 'course', 'reason']);
            }

            if (in_array($entry['status'], [Attendance::STATUS_ABSENT, Attendance::STATUS_TARDY], true)
                && ! $attendance->notified_at) {
                try {
                    $result = $this->alerts->notifyAbsence($attendance);
                    if (($result['sent'] ?? 0) > 0) {
                        $alerts[] = [
                            'student' => $attendance->student?->name,
                            'parents' => $result['parents'],
                        ];
                    }
                } catch (\Throwable) {
                    // La marca se conserva aunque el aviso no se pueda emitir.
                }
            }

            if (in_array($entry['status'], [Attendance::STATUS_ABSENT, Attendance::STATUS_TARDY], true)) {
                AbsenceRequest::where('student_id', $entry['student_id'])
                    ->where('status', 'pending')
                    ->whereDate('start_date', '<=', $data['date'])
                    ->whereDate('end_date', '>=', $data['date'])
                    ->update([
                        'status' => 'approved',
                        'reviewed_by' => $teacher->id,
                        'reviewed_at' => now(),
                    ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Asistencia guardada.',
            'alerts' => $alerts,
            'synced_at' => now()->toDateTimeString(),
        ]);
    }

    public function history(Student $student): JsonResponse
    {
        abort_unless(
            $student->teacher_id === auth()->id()
            || $student->courses()->where('teacher_id', auth()->id())->exists(),
            403
        );

        $rows = Attendance::where('student_id', $student->id)
            ->with(['course:id,subject_name,grade,section', 'reason:id,label'])
            ->latest('attended_on')
            ->limit(40)
            ->get()
            ->map(fn (Attendance $row) => [
                'date' => $row->attended_on->toDateString(),
                'status' => $row->status,
                'course' => $row->course?->subject_name,
                'reason' => $row->reason?->label,
                'note' => $row->note,
                'notified_at' => optional($row->notified_at)->toDateTimeString(),
            ]);

        $requests = Schema::hasTable('absence_requests')
            ? AbsenceRequest::where('student_id', $student->id)
                ->with(['reason:id,label', 'parent:id,name'])
                ->latest()
                ->limit(20)
                ->get()
                ->map(fn (AbsenceRequest $row) => [
                    'kind' => $row->kind,
                    'status' => $row->status,
                    'reason' => $row->reason?->label,
                    'range' => $row->start_date->format('d/m/Y')
                        .($row->end_date->toDateString() !== $row->start_date->toDateString()
                            ? ' – '.$row->end_date->format('d/m/Y')
                            : ''),
                    'comment' => $row->comment,
                    'parent' => $row->parent?->name,
                ])
            : collect();

        $comms = collect();
        if (Schema::hasTable('notifications') && $student->name) {
            $comms = Notification::where('message', 'like', '%'.$student->name.'%')
                ->where(function ($q) use ($student) {
                    $q->where('user_id', auth()->id());
                    if ($student->family_code) {
                        $parentIds = \App\Models\User::where('role', 'representante')
                            ->where('family_code', $student->family_code)
                            ->pluck('id');
                        $q->orWhereIn('user_id', $parentIds);
                    }
                })
                ->latest()
                ->limit(20)
                ->get(['id', 'title', 'message', 'created_at']);
        }

        return response()->json([
            'success' => true,
            'student' => $student->name,
            'history' => $rows,
            'requests' => $requests,
            'communications' => $comms,
        ]);
    }

    private function reasons(?int $colegioId)
    {
        if (! Schema::hasTable('attendance_reasons')) {
            return collect();
        }

        return AttendanceReason::query()
            ->where(function ($q) use ($colegioId) {
                $q->whereNull('colegio_id');
                if ($colegioId) {
                    $q->orWhere('colegio_id', $colegioId);
                }
            })
            ->orderBy('sort_order')
            ->get(['id', 'code', 'label', 'category', 'requires_comment']);
    }
}

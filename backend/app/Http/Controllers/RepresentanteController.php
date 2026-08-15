<?php

namespace App\Http\Controllers;

use App\Models\AbsenceRequest;
use App\Models\Attendance;
use App\Models\AttendanceReason;
use App\Models\Notification;
use App\Models\Student;
use App\Services\AttendanceAlertService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class RepresentanteController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();
        $familyCode = $user->family_code;

        $students = collect();
        $school = null;
        $reasons = collect();
        $attendance = [];
        $alerts = collect();

        if (Schema::hasTable('guardian_student')) {
            $students = $user->representedStudents()->with('colegio', 'courses')->get();
        }

        if ($students->isEmpty() && $familyCode) {
            $students = Student::where('family_code', $familyCode)
                ->when($user->colegio_id, fn ($q) => $q->where('colegio_id', $user->colegio_id))
                ->with('colegio', 'courses')
                ->get();
        }

        $firstStudent = $students->first();
        if ($firstStudent && $firstStudent->colegio) {
            $school = $firstStudent->colegio;
        }

        if (Schema::hasTable('attendance_reasons')) {
            $reasons = AttendanceReason::query()
                ->where(function ($q) use ($user) {
                    $q->whereNull('colegio_id');
                    if ($user->colegio_id) {
                        $q->orWhere('colegio_id', $user->colegio_id);
                    }
                })
                ->whereIn('category', ['excused', 'unexcused', 'tardy'])
                ->orderBy('sort_order')
                ->get(['id', 'code', 'label', 'category', 'requires_comment']);
        }

        if (Schema::hasTable('attendances') && $students->isNotEmpty()) {
            $studentIds = $students->pluck('id');
            $monthStart = now()->startOfMonth()->toDateString();
            $monthEnd = now()->endOfMonth()->toDateString();

            $absenceCounts = Attendance::query()
                ->whereIn('student_id', $studentIds)
                ->where('status', Attendance::STATUS_ABSENT)
                ->whereBetween('attended_on', [$monthStart, $monthEnd])
                ->selectRaw('student_id, COUNT(DISTINCT attended_on) as absences')
                ->groupBy('student_id')
                ->pluck('absences', 'student_id');

            $tardyCounts = Attendance::query()
                ->whereIn('student_id', $studentIds)
                ->where('status', Attendance::STATUS_TARDY)
                ->whereBetween('attended_on', [$monthStart, $monthEnd])
                ->selectRaw('student_id, COUNT(DISTINCT attended_on) as tardies')
                ->groupBy('student_id')
                ->pluck('tardies', 'student_id');

            $histories = Attendance::query()
                ->whereIn('student_id', $studentIds)
                ->with(['course:id,subject_name', 'reason:id,label'])
                ->latest('attended_on')
                ->limit(80)
                ->get()
                ->groupBy('student_id');

            $requests = Schema::hasTable('absence_requests')
                ? AbsenceRequest::query()
                    ->whereIn('student_id', $studentIds)
                    ->with('reason:id,label')
                    ->latest()
                    ->limit(40)
                    ->get()
                    ->groupBy('student_id')
                : collect();

            foreach ($students as $student) {
                $attendance[$student->id] = [
                    'month_absences' => (int) ($absenceCounts[$student->id] ?? 0),
                    'month_tardies' => (int) ($tardyCounts[$student->id] ?? 0),
                    'history' => ($histories->get($student->id) ?? collect())->take(8)->values(),
                    'requests' => ($requests->get($student->id) ?? collect())->take(5)->values(),
                ];
            }
        }

        if (Schema::hasTable('notifications')) {
            $alerts = Notification::where('user_id', $user->id)
                ->where(function ($q) {
                    $q->where('title', 'like', '%asistencia%')
                        ->orWhere('title', 'like', '%ausencia%');
                })
                ->latest()
                ->limit(6)
                ->get(['id', 'title', 'message', 'created_at', 'read_at']);
        }

        return view('representante.dashboard', compact(
            'students',
            'school',
            'familyCode',
            'reasons',
            'attendance',
            'alerts'
        ));
    }

    public function storeAbsence(Request $request, AttendanceAlertService $alerts): RedirectResponse
    {
        abort_unless(Schema::hasTable('absence_requests'), 503);

        $data = $request->validate([
            'student_id' => 'required|integer',
            'kind' => 'required|in:absence,tardy',
            'reason_id' => 'required|integer|exists:attendance_reasons,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'comment' => 'nullable|string|max:500',
        ]);

        $parent = $request->user();
        $student = Student::where('id', $data['student_id'])
            ->where('family_code', $parent->family_code)
            ->firstOrFail();

        $reason = AttendanceReason::findOrFail($data['reason_id']);
        if ($reason->requires_comment && blank($data['comment'] ?? null)) {
            return back()
                ->withErrors(['comment' => 'Este motivo requiere un comentario.'])
                ->withInput();
        }

        $row = AbsenceRequest::create([
            'colegio_id' => $student->colegio_id ?? $parent->colegio_id,
            'student_id' => $student->id,
            'parent_id' => $parent->id,
            'kind' => $data['kind'],
            'reason_id' => $reason->id,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'comment' => $data['comment'] ?? null,
            'status' => 'pending',
        ]);

        $range = $row->start_date->format('d/m/Y');
        if ($row->end_date->toDateString() !== $row->start_date->toDateString()) {
            $range .= ' – '.$row->end_date->format('d/m/Y');
        }

        $alerts->notifyParentRequest($student, $parent, $row->kind, $range);

        return back()->with('status', 'Ausencia reportada. El colegio ya fue notificado.');
    }
}

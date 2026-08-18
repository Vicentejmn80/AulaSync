<?php

namespace App\Http\Controllers\Director;

use App\Helpers\InviteCodeHelper;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Student;
use App\Models\TeacherInvite;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CourseController extends Controller
{
    public function index(Request $request): View
    {
        $colegioId = $request->user()->colegio_id;

        $courses = Course::where('colegio_id', $colegioId)
            ->with([
                'teacher:id,name,email',
                'pendingInvite:id,name,invite_code,claimed_at,claimed_by',
            ])
            ->withCount('students')
            ->orderBy('grade')
            ->orderBy('subject_name')
            ->get();

        foreach ($courses as $course) {
            if (! $course->invite_code) {
                $course->invite_code = InviteCodeHelper::generateCourseCode(
                    $course->subject_name,
                    $course->grade,
                    $course->section
                );
                $course->save();
            }
        }

        $teachers = User::where('colegio_id', $colegioId)
            ->where('role', 'profesor')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $pendingInvites = TeacherInvite::where('colegio_id', $colegioId)
            ->whereNull('claimed_by')
            ->whereNull('claimed_at')
            ->latest()
            ->get(['id', 'name', 'email', 'invite_code', 'subject_name', 'grade']);

        $grades = Student::where('colegio_id', $colegioId)
            ->whereNotNull('grade')
            ->distinct()
            ->orderBy('grade')
            ->pluck('grade');

        return view('director.courses', compact('courses', 'teachers', 'pendingInvites', 'grades'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'assignee' => ['required', 'string', 'max:40'],
            'subject_name' => ['required', 'string', 'max:120'],
            'grade' => ['required', 'string', 'max:60'],
            'section' => ['nullable', 'string', 'max:10'],
            'school_year' => ['nullable', 'string', 'max:9'],
            'enroll_roster' => ['nullable', 'boolean'],
        ]);

        $director = $request->user();
        [$teacherId, $inviteId, $assigneeLabel] = $this->resolveAssignee(
            $director->colegio_id,
            $data['assignee']
        );

        $course = Course::create([
            'teacher_id' => $teacherId,
            'teacher_invite_id' => $inviteId,
            'colegio_id' => $director->colegio_id,
            'subject_name' => $data['subject_name'],
            'grade' => $data['grade'],
            'section' => $data['section'] ?: null,
            'school_year' => $data['school_year'] ?: (date('Y').'-'.(date('Y') + 1)),
            'invite_code' => InviteCodeHelper::generateCourseCode(
                $data['subject_name'],
                $data['grade'],
                $data['section'] ?? null
            ),
        ]);

        if ($inviteId) {
            $invite = TeacherInvite::find($inviteId);
            if ($invite) {
                $ids = collect($invite->course_ids ?? [])->push($course->id)->unique()->values()->all();
                $invite->update(['course_ids' => $ids]);
            }
        }

        $enrolled = 0;
        if ($request->boolean('enroll_roster')) {
            $enrolled = $this->attachRoster($course, $director->colegio_id);
        }

        $suffix = $enrolled > 0 ? " Se inscribieron {$enrolled} alumno(s) del grado." : '';

        return redirect()->route('director.courses')
            ->with('success', "Curso creado y asignado a {$assigneeLabel}.{$suffix}");
    }

    public function destroy(Request $request, Course $course): RedirectResponse
    {
        abort_unless((int) $course->colegio_id === (int) $request->user()->colegio_id, 403);
        $course->delete();

        return redirect()->route('director.courses')->with('success', 'Curso eliminado.');
    }

    public function assign(Request $request, Course $course): RedirectResponse
    {
        abort_unless((int) $course->colegio_id === (int) $request->user()->colegio_id, 403);

        $data = $request->validate([
            'assignee' => ['required', 'string', 'max:40'],
        ]);

        [$teacherId, $inviteId, $assigneeLabel] = $this->resolveAssignee(
            $request->user()->colegio_id,
            $data['assignee']
        );

        $course->update([
            'teacher_id' => $teacherId,
            'teacher_invite_id' => $inviteId,
        ]);

        if ($inviteId) {
            $invite = TeacherInvite::find($inviteId);
            if ($invite) {
                $ids = collect($invite->course_ids ?? [])->push($course->id)->unique()->values()->all();
                $invite->update(['course_ids' => $ids]);
            }
        }

        return back()->with('success', "{$course->subject_name} quedó asignado a {$assigneeLabel}.");
    }

    public function enrollByRoster(Request $request, Course $course): RedirectResponse
    {
        abort_unless((int) $course->colegio_id === (int) $request->user()->colegio_id, 403);

        $attached = $this->attachRoster($course, $request->user()->colegio_id);

        return back()->with('success', "{$attached} alumno(s) de {$course->grade} inscritos en {$course->subject_name} ({$course->invite_code}).");
    }

    /**
     * @return array{0: int|null, 1: int|null, 2: string}
     */
    private function resolveAssignee(int $colegioId, string $assignee): array
    {
        if (str_starts_with($assignee, 'invite:')) {
            $invite = TeacherInvite::where('colegio_id', $colegioId)
                ->where('id', (int) substr($assignee, 7))
                ->whereNull('claimed_by')
                ->firstOrFail();

            return [null, $invite->id, "{$invite->name} ({$invite->invite_code}, pendiente)"];
        }

        $teacherId = str_starts_with($assignee, 'teacher:')
            ? (int) substr($assignee, 8)
            : (int) $assignee;

        $teacher = User::where('id', $teacherId)
            ->where('colegio_id', $colegioId)
            ->where('role', 'profesor')
            ->firstOrFail();

        return [$teacher->id, null, $teacher->name];
    }

    private function attachRoster(Course $course, int $colegioId): int
    {
        $query = Student::where('colegio_id', $colegioId)
            ->where('grade', $course->grade);

        if ($course->section) {
            $query->where(function ($q) use ($course) {
                $q->where('section', $course->section)->orWhereNull('section');
            });
        }

        $studentIds = $query->pluck('id');
        $attached = 0;
        foreach ($studentIds as $studentId) {
            if (! $course->students()->where('student_id', $studentId)->exists()) {
                $course->students()->attach($studentId);
                $attached++;
            }
        }

        return $attached;
    }
}

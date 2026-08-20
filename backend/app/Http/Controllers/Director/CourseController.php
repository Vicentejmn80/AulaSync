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
            ->whereNull('revoked_at')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
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
            'section' => ($data['section'] ?? null) ?: null,
            'school_year' => ($data['school_year'] ?? null) ?: (date('Y').'-'.(date('Y') + 1)),
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

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $courses = Course::query()
            ->where('colegio_id', $request->user()->colegio_id)
            ->whereIn('id', $data['ids'])
            ->get();

        $count = $courses->count();
        foreach ($courses as $course) {
            $course->delete();
        }

        return redirect()->route('director.courses')
            ->with('success', "Se eliminaron {$count} curso(s).");
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

        if ($attached === 0) {
            return back()->with('warning', "No se inscribieron alumnos. Revisa que existan estudiantes con grado parecido a \"{$course->grade}\"" . ($course->section ? " / {$course->section}" : '') . ' en Alumnos.');
        }

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
                ->whereNull('revoked_at')
                ->where(function ($query) {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->firstOrFail();

            return [null, $invite->id, "{$invite->display_name} ({$invite->invite_code}, pendiente)"];
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
        $students = Student::where('colegio_id', $colegioId)->get(['id', 'grade', 'section']);
        $courseGradeKey = $this->normalizeGradeKey($course->grade);
        $courseSection = $course->section ? mb_strtolower(trim($course->section)) : null;

        $attached = 0;
        foreach ($students as $student) {
            if ($this->normalizeGradeKey($student->grade) !== $courseGradeKey) {
                continue;
            }

            if ($courseSection) {
                $studentSection = $student->section ? mb_strtolower(trim($student->section)) : null;
                if ($studentSection && $studentSection !== $courseSection) {
                    continue;
                }
            }

            if (! $course->students()->where('student_id', $student->id)->exists()) {
                $course->students()->attach($student->id, ['enrolled_at' => now()]);
                $attached++;
            }
        }

        return $attached;
    }

    private function normalizeGradeKey(?string $grade): string
    {
        $raw = mb_strtolower(trim((string) $grade));
        $raw = strtr($raw, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
        ]);
        $raw = preg_replace('/\s+/', '', $raw) ?? '';
        $raw = str_replace(['primero', '1ero', '1ro', '1°', '1º'], '1', $raw);
        $raw = str_replace(['segundo', '2do', '2do.', '2°', '2º'], '2', $raw);
        $raw = str_replace(['tercero', '3ro', '3ero', '3°', '3º'], '3', $raw);
        $raw = str_replace(['cuarto', '4to', '4to.', '4°', '4º'], '4', $raw);
        $raw = str_replace(['quinto', '5to', '5to.', '5°', '5º'], '5', $raw);
        $raw = str_replace(['sexto', '6to', '6to.', '6°', '6º'], '6', $raw);

        return preg_replace('/[^a-z0-9]/', '', $raw) ?? '';
    }
}

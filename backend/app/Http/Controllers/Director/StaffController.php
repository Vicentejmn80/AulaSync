<?php

namespace App\Http\Controllers\Director;

use App\Helpers\InviteCodeHelper;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Invitation;
use App\Models\TeacherInvite;
use App\Models\User;
use App\Services\DirectorActionService;
use App\Services\InvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function __construct(
        private DirectorActionService $actionService,
        private InvitationService $invitations,
    ) {}
    public function index(Request $request): View
    {
        $colegioId = $request->user()->colegio_id;

        $teachers = User::query()
            ->where('colegio_id', $colegioId)
            ->where('role', 'profesor')
            ->with(['courses' => function ($query) {
                $query->withCount('students')->orderBy('subject_name')->orderBy('grade')->orderBy('section');
            }])
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'colegio_id']);

        $invites = TeacherInvite::where('colegio_id', $colegioId)
            ->whereNull('claimed_by')
            ->whereNull('claimed_at')
            ->whereNull('revoked_at')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->with(['courses' => function ($query) {
                $query->withCount('students')->orderBy('subject_name')->orderBy('grade');
            }])
            ->latest()
            ->limit(40)
            ->get();

        $emailInvites = Invitation::query()
            ->where('colegio_id', $colegioId)
            ->where('role', Invitation::ROLE_DOCENTE)
            ->whereNull('accepted_at')
            ->latest()
            ->limit(40)
            ->get();

        $courses = Course::where('colegio_id', $colegioId)
            ->orderBy('subject_name')
            ->orderBy('grade')
            ->get(['id', 'subject_name', 'grade', 'section', 'teacher_id']);

        return view('director.profesores', compact('teachers', 'invites', 'emailInvites', 'courses'));
    }

    public function invite(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'email' => ['nullable', 'email', 'max:180'],
            'course_ids' => ['nullable', 'array'],
            'course_ids.*' => ['integer'],
            'subject_name' => ['nullable', 'string', 'max:120'],
            'grade' => ['nullable', 'string', 'max:60'],
            'section' => ['nullable', 'string', 'max:10'],
        ]);

        $director = $request->user();
        $courseIds = collect($data['course_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($courseIds) {
            $owned = Course::where('colegio_id', $director->colegio_id)
                ->whereIn('id', $courseIds)
                ->pluck('id')
                ->all();
            $courseIds = array_values(array_intersect($courseIds, $owned));
        }

        $invite = TeacherInvite::create([
            'colegio_id' => $director->colegio_id,
            'created_by' => $director->id,
            'name' => app(\App\Services\PersonNameSanitizer::class)->displayName($data['name']) ?: $data['name'],
            'email' => $data['email'] ?? null,
            'invite_code' => InviteCodeHelper::generateTeacherInvite(),
            'course_ids' => $courseIds ?: null,
            'subject_name' => $data['subject_name'] ?: null,
            'grade' => $data['grade'] ?: null,
            'section' => $data['section'] ?: null,
        ]);

        if ($courseIds) {
            Course::where('colegio_id', $director->colegio_id)
                ->whereIn('id', $courseIds)
                ->update(['teacher_invite_id' => $invite->id]);
        }

        if ($invite->subject_name && $invite->grade) {
            $course = Course::create([
                'teacher_id' => null,
                'teacher_invite_id' => $invite->id,
                'colegio_id' => $director->colegio_id,
                'subject_name' => $invite->subject_name,
                'grade' => $invite->grade,
                'section' => $invite->section,
                'school_year' => date('Y').'-'.(date('Y') + 1),
                'invite_code' => InviteCodeHelper::generateCourseCode(
                    $invite->subject_name,
                    $invite->grade,
                    $invite->section
                ),
            ]);
            $invite->update([
                'course_ids' => collect($invite->course_ids ?? [])->push($course->id)->unique()->values()->all(),
            ]);
        }

        return redirect()->route('director.profesores')
            ->with('success', "Invitación lista. Comparte el código {$invite->invite_code} con {$invite->display_name}. El curso y los alumnos que prepares quedan vinculados a ese código.");
    }

    public function inviteLink(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:180'],
        ]);

        $director = $request->user();
        abort_unless((bool) $director->colegio_id, 403);

        $invitation = $this->invitations->issue([
            'email' => $data['email'],
            'role' => Invitation::ROLE_DOCENTE,
            'colegio_id' => $director->colegio_id,
        ], $director);

        return redirect()->route('director.profesores')
            ->with('success', 'Enlace mágico listo. El docente crea su cuenta y luego entra por /login.')
            ->with('invitation_url', $invitation->acceptUrl());
    }

    public function destroyTeacher(Request $request, User $teacher): RedirectResponse
    {
        abort_unless(
            $teacher->role === 'profesor'
            && (int) $teacher->colegio_id === (int) $request->user()->colegio_id,
            404
        );

        $name = $teacher->name;
        $this->actionService->deleteTeacher($request->user(), [
            'teacher_name' => $name,
        ]);

        return redirect()->route('director.profesores')
            ->with('success', "Se eliminó a {$name} del plantel docente.");
    }

    public function bulkDestroyTeachers(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $teachers = User::query()
            ->where('colegio_id', $request->user()->colegio_id)
            ->where('role', 'profesor')
            ->whereIn('id', $data['ids'])
            ->get();

        $deleted = [];
        foreach ($teachers as $teacher) {
            $this->actionService->deleteTeacher($request->user(), [
                'teacher_name' => $teacher->name,
            ]);
            $deleted[] = $teacher->name;
        }

        return redirect()->route('director.profesores')
            ->with('success', 'Se eliminaron '.count($deleted).' docente(s).');
    }

    public function destroyInvite(Request $request, TeacherInvite $invite): RedirectResponse
    {
        abort_unless((int) $invite->colegio_id === (int) $request->user()->colegio_id, 404);

        $name = $invite->display_name;

        Course::query()
            ->where('colegio_id', $invite->colegio_id)
            ->where('teacher_invite_id', $invite->id)
            ->update(['teacher_invite_id' => null]);

        $invite->delete();

        return redirect()->route('director.profesores')
            ->with('success', "Se eliminó la invitación pendiente de {$name}.");
    }
}

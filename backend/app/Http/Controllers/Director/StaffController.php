<?php

namespace App\Http\Controllers\Director;

use App\Helpers\InviteCodeHelper;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\TeacherInvite;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(Request $request): View
    {
        $colegioId = $request->user()->colegio_id;

        $teachers = User::query()
            ->where('colegio_id', $colegioId)
            ->where('role', 'profesor')
            ->with(['courses' => function ($query) {
                $query->orderBy('subject_name')->orderBy('grade')->orderBy('section');
            }])
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'colegio_id']);

        $invites = TeacherInvite::where('colegio_id', $colegioId)
            ->latest()
            ->limit(40)
            ->get();

        $courses = Course::where('colegio_id', $colegioId)
            ->orderBy('subject_name')
            ->orderBy('grade')
            ->get(['id', 'subject_name', 'grade', 'section', 'teacher_id']);

        return view('director.profesores', compact('teachers', 'invites', 'courses'));
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
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'invite_code' => InviteCodeHelper::generateTeacherInvite(),
            'course_ids' => $courseIds ?: null,
            'subject_name' => $data['subject_name'] ?: null,
            'grade' => $data['grade'] ?: null,
            'section' => $data['section'] ?: null,
        ]);

        return redirect()->route('director.profesores')
            ->with('success', "Invitación lista. Comparte el código {$invite->invite_code} con {$invite->name}.");
    }
}

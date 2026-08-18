<?php

namespace App\Http\Controllers\Director;

use App\Helpers\InviteCodeHelper;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Student;
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
            ->with('teacher:id,name,email')
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

        $grades = Student::where('colegio_id', $colegioId)
            ->whereNotNull('grade')
            ->distinct()
            ->orderBy('grade')
            ->pluck('grade');

        return view('director.courses', compact('courses', 'teachers', 'grades'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'teacher_id' => ['required', 'integer'],
            'subject_name' => ['required', 'string', 'max:120'],
            'grade' => ['required', 'string', 'max:60'],
            'section' => ['nullable', 'string', 'max:10'],
            'school_year' => ['nullable', 'string', 'max:9'],
        ]);

        $director = $request->user();
        $teacher = User::where('id', $data['teacher_id'])
            ->where('colegio_id', $director->colegio_id)
            ->where('role', 'profesor')
            ->firstOrFail();

        Course::create([
            'teacher_id' => $teacher->id,
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

        return redirect()->route('director.courses')
            ->with('success', 'Curso creado y asignado al docente.');
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
            'teacher_id' => ['required', 'integer'],
        ]);

        $teacher = User::where('id', $data['teacher_id'])
            ->where('colegio_id', $request->user()->colegio_id)
            ->where('role', 'profesor')
            ->firstOrFail();

        $course->update(['teacher_id' => $teacher->id]);

        return back()->with('success', "{$course->subject_name} quedó asignado a {$teacher->name}.");
    }

    public function enrollByRoster(Request $request, Course $course): RedirectResponse
    {
        abort_unless((int) $course->colegio_id === (int) $request->user()->colegio_id, 403);

        $query = Student::where('colegio_id', $request->user()->colegio_id)
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

        return back()->with('success', "{$attached} alumno(s) de {$course->grade} inscritos en {$course->subject_name} ({$course->invite_code}).");
    }
}

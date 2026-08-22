<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CoursesController extends Controller
{
    public function index(): View
    {
        $courses = Course::withCount('students')
            ->with(['activities', 'students' => fn ($q) => $q->orderBy('name')])
            ->where('teacher_id', auth()->id())
            ->latest()
            ->get();

        return view('teacher.courses.index', compact('courses'));
    }

    public function store(Request $request): RedirectResponse
    {
        return redirect()->route('teacher.courses.index')
            ->with('error', 'Solo el director puede crear cursos, grados o secciones. Pídele que te asigne la materia.');
    }

    public function destroy(Course $course): RedirectResponse
    {
        abort_unless($course->teacher_id === auth()->id(), 403);

        return redirect()->route('teacher.courses.index')
            ->with('error', 'Solo el director puede eliminar cursos. Si necesitas desvincularte, contacta a dirección.');
    }

    /**
     * El docente no puede importar ni matricular alumnos. Eso lo hace el director.
     */
    public function importStudents(Request $request, Course $course): JsonResponse
    {
        abort_unless($course->teacher_id === auth()->id(), 403);
        abort_unless((int) $course->colegio_id === (int) $request->user()->colegio_id, 403);

        return response()->json([
            'created' => 0,
            'enrolled' => 0,
            'missing' => [],
            'error' => 'Solo el director puede importar o matricular alumnos. Revisa la lista y envíasela a dirección.',
        ], 403);
    }

    /**
     * Remove a student from this course (does NOT delete the student globally).
     */
    public function removeStudent(Course $course, Student $student): JsonResponse
    {
        abort_unless($course->teacher_id === auth()->id(), 403);
        abort_unless((int) $course->colegio_id === (int) auth()->user()->colegio_id, 403);
        abort_unless((int) $student->colegio_id === (int) auth()->user()->colegio_id, 403);

        $course->students()->detach($student->id);

        return response()->json(['ok' => true]);
    }
}

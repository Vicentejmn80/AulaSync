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
     * Inscribe alumnos ya matriculados en el colegio. Nunca crea registros nuevos.
     */
    public function importStudents(Request $request, Course $course): JsonResponse
    {
        abort_unless($course->teacher_id === auth()->id(), 403);
        abort_unless((int) $course->colegio_id === (int) auth()->user()->colegio_id, 403);

        $request->validate([
            'names' => ['required', 'string'],
        ]);

        $lines = preg_split('/\r\n|\r|\n/', trim($request->input('names')));
        $lines = array_filter(array_map('trim', $lines));

        if (empty($lines)) {
            return response()->json(['error' => 'La lista de nombres está vacía.'], 422);
        }

        $enrolled = 0;
        $missing = [];
        $colegioId = (int) auth()->user()->colegio_id;

        foreach ($lines as $name) {
            if (mb_strlen($name) < 2) {
                continue;
            }

            $student = Student::where('colegio_id', $colegioId)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->first();

            if (! $student) {
                $student = Student::where('colegio_id', $colegioId)
                    ->where('name', 'like', $name.'%')
                    ->orderBy('name')
                    ->first();
            }

            if (! $student) {
                $missing[] = $name;
                continue;
            }

            if (! $course->students()->where('student_id', $student->id)->exists()) {
                $course->students()->attach($student->id, ['enrolled_at' => now()]);
                $enrolled++;
            }
        }

        $message = "{$enrolled} alumno(s) inscritos desde la nómina del colegio.";
        if ($missing) {
            $message .= ' No encontrados (pide al director que los matricule): '.implode(', ', $missing).'.';
        }

        return response()->json([
            'created' => 0,
            'enrolled' => $enrolled,
            'missing' => $missing,
            'total' => count($lines),
            'message' => $message,
        ]);
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

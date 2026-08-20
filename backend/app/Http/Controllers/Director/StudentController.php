<?php

namespace App\Http\Controllers\Director;

use App\Http\Controllers\Controller;
use App\Models\Colegio;
use App\Models\Course;
use App\Models\Student;
use App\Services\DirectorActionService;
use App\Services\StudentEnrollmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentController extends Controller
{
    public function __construct(private StudentEnrollmentService $enrollment)
    {
    }

    public function index(Request $request): View
    {
        $colegioId = auth()->user()->colegio_id;
        $query = Student::where('colegio_id', $colegioId);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('grade', 'like', "%{$search}%")
                  ->orWhere('family_code', 'like', "%{$search}%")
                  ->orWhere('document_id', 'like', "%{$search}%");
            });
        }

        if ($request->filled('grade')) {
            $query->where('grade', $request->grade);
        }

        $students = $query->withCount('courses')
            ->orderBy('name')
            ->paginate(30);

        $grades = Student::select('grade')
            ->where('colegio_id', $colegioId)
            ->distinct()
            ->orderBy('grade')
            ->pluck('grade');

        $courses = Course::where('colegio_id', $colegioId)
            ->orderBy('grade')
            ->orderBy('subject_name')
            ->get(['id', 'subject_name', 'grade', 'section', 'school_year']);

        $households = Student::where('colegio_id', $colegioId)
            ->whereNotNull('family_code')
            ->orderBy('name')
            ->get(['id', 'name', 'grade', 'section', 'family_code']);

        $colegio = Colegio::find($colegioId);

        return view('director.students', compact('students', 'grades', 'courses', 'households', 'colegio'));
    }

    public function store(Request $request)
    {
        $request->merge([
            'course_id' => $request->filled('course_id') ? $request->course_id : null,
            'sibling_student_id' => $request->filled('sibling_student_id') ? $request->sibling_student_id : null,
            'document_id' => $request->document_id ?: null,
            'birthdate' => $request->birthdate ?: null,
            'grade' => $request->grade ?: null,
            'section' => $request->section ?: null,
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'grade' => ['nullable', 'string', 'max:60'],
            'section' => ['nullable', 'string', 'max:10'],
            'document_id' => ['nullable', 'string', 'max:40'],
            'birthdate' => ['nullable', 'date'],
            'course_id' => ['nullable', 'integer'],
            'family_mode' => ['nullable', 'in:new,existing'],
            'family_code' => ['nullable', 'string', 'max:20'],
            'sibling_student_id' => ['nullable', 'integer'],
        ]);

        $user = $request->user();
        $course = null;
        if (! empty($data['course_id'])) {
            $course = Course::where('id', $data['course_id'])
                ->where('colegio_id', $user->colegio_id)
                ->firstOrFail();
            $data['grade'] = $data['grade'] ?: $course->grade;
            $data['section'] = $data['section'] ?: $course->section;
        }

        if (($data['family_mode'] ?? 'new') === 'new') {
            unset($data['family_code'], $data['sibling_student_id']);
        }

        $student = $this->enrollment->enroll($user, $data, $course);

        return redirect()->route('director.students')
            ->with('success', "Alumno «{$student->name}» matriculado. Código familiar: {$student->family_code}. Entrégaselo al representante.");
    }

    public function destroy(Request $request, Student $student, DirectorActionService $actions): RedirectResponse
    {
        abort_unless(
            (int) $student->colegio_id === (int) $request->user()->colegio_id,
            404
        );

        $name = $student->name;
        $actions->deleteStudent($request->user(), [
            'student_name' => $name,
            'student_id' => $student->id,
        ]);

        return redirect()->route('director.students')
            ->with('success', "Se eliminó al alumno «{$name}».");
    }

    public function bulkDestroy(Request $request, DirectorActionService $actions): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $students = Student::query()
            ->where('colegio_id', $request->user()->colegio_id)
            ->whereIn('id', $data['ids'])
            ->get();

        $deleted = [];
        foreach ($students as $student) {
            $actions->deleteStudent($request->user(), [
                'student_name' => $student->name,
                'student_id' => $student->id,
            ]);
            $deleted[] = $student->name;
        }

        return redirect()->route('director.students')
            ->with('success', 'Se eliminaron '.count($deleted).' alumno(s): '.implode(', ', $deleted).'.');
    }

    public function search(Request $request)
    {
        $colegioId = auth()->user()->colegio_id;
        $search = $request->get('q', '');
        $students = Student::where('colegio_id', $colegioId)
            ->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('family_code', 'like', "%{$search}%");
            })
            ->limit(20)
            ->get(['id', 'name', 'grade', 'section', 'family_code']);

        return response()->json($students);
    }
}

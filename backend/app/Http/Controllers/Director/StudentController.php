<?php

namespace App\Http\Controllers\Director;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentController extends Controller
{
    public function index(Request $request): View
    {
        $colegioId = auth()->user()->colegio_id;
        $query = Student::where('colegio_id', $colegioId);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('grade', 'like', "%{$search}%");
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

        return view('director.students', compact('students', 'grades', 'courses'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'      => ['required', 'string', 'max:180'],
            'course_id' => ['required', 'integer'],
        ]);

        $user      = auth()->user();
        $colegioId = $user->colegio_id;

        $course = Course::where('id', $request->course_id)
            ->where('colegio_id', $colegioId)
            ->firstOrFail();

        $student = Student::create([
            'name'      => trim($request->name),
            'grade'     => $course->grade,
            'section'   => $course->section,
            'colegio_id' => $colegioId,
            'teacher_id' => $course->teacher_id,
        ]);

        $student->courses()->attach($course->id, ['enrolled_at' => now()]);

        return redirect()->route('director.students')
            ->with('success', "Alumno «{$student->name}» matriculado con código {$student->family_code}.");
    }

    public function search(Request $request)
    {
        $colegioId = auth()->user()->colegio_id;
        $search = $request->get('q', '');
        $students = Student::where('colegio_id', $colegioId)
            ->where('name', 'like', "%{$search}%")
            ->limit(20)
            ->get(['id', 'name', 'grade', 'section']);

        return response()->json($students);
    }
}

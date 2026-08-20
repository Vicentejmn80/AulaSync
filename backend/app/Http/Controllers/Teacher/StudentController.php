<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Student;
use App\Services\StudentEnrollmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    public function __construct(private StudentEnrollmentService $enrollment) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'course_id' => ['required', 'integer'],
        ]);

        $course = Course::query()
            ->where('id', $data['course_id'])
            ->where('teacher_id', $request->user()->id)
            ->firstOrFail();

        $matcher = app(\App\Services\PersonNameMatcher::class);
        $match = $matcher->resolveStudent((int) $request->user()->colegio_id, $data['name']);
        if (! $match->isUnique()) {
            return response()->json([
                'success' => false,
                'error' => $match->message ?? 'Solo puedes inscribir alumnos que el director ya matriculó. Búscalos en la nómina o pídele al director que los cree.',
            ], 422);
        }

        $student = $this->enrollment->attachExisting($course, $match->model, $request->user());

        return response()->json([
            'success' => true,
            'student' => $student,
            'students_count' => $course->students()->count(),
            'message' => "{$student->name} quedó inscrito en el curso. Ahora hay ".$course->students()->count().' alumno(s).',
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $teacher = $request->user();
        $q = trim((string) $request->get('q', ''));

        $students = Student::where('colegio_id', $teacher->colegio_id)
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('name', 'like', "%{$q}%")
                        ->orWhere('family_code', 'like', "%{$q}%")
                        ->orWhere('document_id', 'like', "%{$q}%");
                });
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'grade', 'section', 'family_code', 'document_id']);

        return response()->json(['success' => true, 'students' => $students]);
    }

    public function enrollExisting(Request $request, Course $course): JsonResponse
    {
        abort_unless($course->teacher_id === auth()->id(), 403);

        $data = $request->validate([
            'student_id' => 'required|integer',
        ]);

        $student = Student::where('id', $data['student_id'])
            ->where('colegio_id', auth()->user()->colegio_id)
            ->firstOrFail();

        $this->enrollment->attachExisting($course, $student, $request->user());
        $count = $course->students()->count();

        return response()->json([
            'success' => true,
            'student' => $student,
            'students_count' => $count,
            'message' => "{$student->name} quedó inscrito en el curso. Ahora hay {$count} alumno(s).",
        ]);
    }
}

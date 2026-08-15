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
    public function __construct(private StudentEnrollmentService $enrollment)
    {
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:180',
            'grade' => 'nullable|string|max:60',
            'section' => 'nullable|string|max:10',
            'document_id' => 'nullable|string|max:40',
            'birthdate' => 'nullable|date',
            'family_code' => 'nullable|string|max:20',
            'sibling_student_id' => 'nullable|integer',
            'course_id' => 'nullable|integer',
        ]);

        $teacher = $request->user();
        abort_unless($teacher?->role === 'profesor' && $teacher->colegio_id, 403);

        $course = null;
        if (! empty($data['course_id'])) {
            $course = Course::where('id', $data['course_id'])
                ->where('teacher_id', $teacher->id)
                ->where('colegio_id', $teacher->colegio_id)
                ->firstOrFail();
            $data['grade'] = $data['grade'] ?? $course->grade;
            $data['section'] = $data['section'] ?? $course->section;
        }

        $student = $this->enrollment->enroll($teacher, $data, $course);

        return response()->json([
            'success' => true,
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'grade' => $student->grade,
                'section' => $student->section,
                'family_code' => $student->family_code,
                'document_id' => $student->document_id,
            ],
            'message' => "Alumno matriculado. Comparte el código familiar {$student->family_code} con el representante.",
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

        $this->enrollment->attachExisting($course, $student);

        return response()->json([
            'success' => true,
            'student' => $student,
            'message' => "{$student->name} quedó inscrito en el curso.",
        ]);
    }
}

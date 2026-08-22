<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentController extends Controller
{

    public function store(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => 'Solo el director puede matricular alumnos. Revisa la información y envíasela a dirección.',
        ], 403);
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
        abort_unless((int) $course->colegio_id === (int) $request->user()->colegio_id, 403);

        return response()->json([
            'success' => false,
            'error' => 'Solo el director puede matricular alumnos en un curso. Revisa la información y envíasela a dirección.',
        ], 403);
    }
}

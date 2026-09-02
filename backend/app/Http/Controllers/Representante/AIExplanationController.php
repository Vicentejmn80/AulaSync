<?php

namespace App\Http\Controllers\Representante;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Course;
use App\Models\Evaluation;
use App\Services\RepresentanteAIExplanationService;
use App\Services\RepresentanteDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AIExplanationController extends Controller
{
    public function __construct(
        private RepresentanteDashboardService $dashboard,
        private RepresentanteAIExplanationService $ai,
    ) {
    }

    public function explainActivity(Request $request, int $activity): JsonResponse
    {
        $data = $request->validate([
            'estudiante_id' => 'required|integer',
        ]);

        $student = $this->dashboard->authorizeStudent(auth()->user(), (int) $data['estudiante_id']);
        $act = Activity::with('course.teacher', 'teacher')->findOrFail($activity);

        abort_unless($student->courses->contains('id', $act->course_id), 404, 'Actividad no pertenece a este estudiante.');

        return response()->json($this->ai->explainActivity($student, $act));
    }

    public function explainEvaluation(Request $request, int $evaluation): JsonResponse
    {
        $data = $request->validate([
            'estudiante_id' => 'required|integer',
        ]);

        $student = $this->dashboard->authorizeStudent(auth()->user(), (int) $data['estudiante_id']);
        $eval = Evaluation::with('course.teacher', 'teacher')->findOrFail($evaluation);

        abort_unless($student->courses->contains('id', $eval->course_id), 404, 'Evaluación no pertenece a este estudiante.');

        return response()->json($this->ai->explainEvaluation($student, $eval));
    }

    public function summarizeWeek(Request $request): JsonResponse
    {
        $data = $request->validate([
            'estudiante_id' => 'required|integer',
            'month' => 'nullable|string|regex:/^\d{4}-\d{2}$/',
        ]);

        $student = $this->dashboard->authorizeStudent(auth()->user(), (int) $data['estudiante_id']);

        return response()->json($this->ai->summarizeWeek($student, $data['month'] ?? null));
    }

    public function explainGrades(Request $request): JsonResponse
    {
        $data = $request->validate([
            'estudiante_id' => 'required|integer',
            'materia_id' => 'nullable|integer',
        ]);

        $student = $this->dashboard->authorizeStudent(auth()->user(), (int) $data['estudiante_id']);
        $course = null;
        if (! empty($data['materia_id'])) {
            $course = Course::with('teacher')->findOrFail((int) $data['materia_id']);
            abort_unless($student->courses->contains('id', $course->id), 404, 'Materia no pertenece a este estudiante.');
        }

        return response()->json($this->ai->explainGrades($student, $course));
    }

    public function explainAttendance(Request $request): JsonResponse
    {
        $data = $request->validate([
            'estudiante_id' => 'required|integer',
            'materia_id' => 'nullable|integer',
        ]);

        $student = $this->dashboard->authorizeStudent(auth()->user(), (int) $data['estudiante_id']);
        $course = null;
        if (! empty($data['materia_id'])) {
            $course = Course::with('teacher')->findOrFail((int) $data['materia_id']);
            abort_unless($student->courses->contains('id', $course->id), 404, 'Materia no pertenece a este estudiante.');
        }

        return response()->json($this->ai->explainAttendance($student, $course));
    }
}

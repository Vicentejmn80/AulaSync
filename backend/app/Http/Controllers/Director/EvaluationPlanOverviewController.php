<?php

namespace App\Http\Controllers\Director;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseEvaluationPlan;
use App\Models\User;
use App\Services\EvaluationPlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class EvaluationPlanOverviewController extends Controller
{
    public function __construct(private EvaluationPlanService $planService)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $colegioId = $user->colegio_id;
        $gradeFilter = $request->string('grade')->toString();
        $teacherFilter = $request->integer('teacher_id') ?: null;

        $plans = collect();

        if (Schema::hasTable('course_evaluation_plans')) {
            $plans = $this->fetchPlans($colegioId, $gradeFilter, $teacherFilter);
        }

        $teachers = User::where('colegio_id', $colegioId)
            ->where('role', 'profesor')
            ->orderBy('name')
            ->get(['id', 'name']);

        $grades = Course::where('colegio_id', $colegioId)
            ->whereNotNull('grade')
            ->distinct()
            ->orderBy('grade')
            ->pluck('grade');

        return view('director.evaluation-plans', compact('plans', 'teachers', 'grades', 'gradeFilter', 'teacherFilter'));
    }

    /**
     * JSON variant of the same overview, for embedding/filtering without a full page reload.
     */
    public function api(Request $request): JsonResponse
    {
        $user = $request->user();
        $colegioId = $user->colegio_id;
        $gradeFilter = $request->string('grade')->toString();
        $teacherFilter = $request->integer('teacher_id') ?: null;

        if (! Schema::hasTable('course_evaluation_plans')) {
            return response()->json(['success' => true, 'plans' => []]);
        }

        $plans = $this->fetchPlans($colegioId, $gradeFilter, $teacherFilter);

        return response()->json([
            'success' => true,
            'plans' => $plans,
        ]);
    }

    private function fetchPlans(?int $colegioId, string $gradeFilter, ?int $teacherFilter): \Illuminate\Support\Collection
    {
        $query = CourseEvaluationPlan::query()
            ->whereHas('course', function ($q) use ($colegioId, $gradeFilter) {
                $q->where('colegio_id', $colegioId);
                if ($gradeFilter !== '') {
                    $q->where('grade', $gradeFilter);
                }
            })
            ->with([
                'course:id,subject_name,grade,section,colegio_id',
                'teacher:id,name',
                'items.evaluation:id,title,status,scheduled_at',
            ])
            ->when($teacherFilter, fn ($q) => $q->where('teacher_id', $teacherFilter))
            ->orderByDesc('created_at');

        return $query->get()->map(function (CourseEvaluationPlan $plan) {
            $summary = $this->planService->planSummary($plan);

            return [
                'id' => $plan->id,
                'title' => $plan->title,
                'summary' => $plan->summary,
                'status' => $plan->status,
                'course' => $plan->course ? [
                    'id' => $plan->course->id,
                    'subject_name' => $plan->course->subject_name,
                    'grade' => $plan->course->grade,
                    'section' => $plan->course->section,
                ] : null,
                'teacher' => $plan->teacher ? [
                    'id' => $plan->teacher->id,
                    'name' => $plan->teacher->name,
                ] : null,
                'items' => $plan->items->map(fn ($item) => [
                    'id' => $item->id,
                    'unit_name' => $item->unit_name,
                    'assessment_type' => $item->assessment_type,
                    'category' => $item->category,
                    'weight_percentage' => (float) $item->weight_percentage,
                    'due_date' => optional($item->due_date)->toDateString(),
                    'evaluation_title' => $item->evaluation?->title,
                ])->values(),
                'total_weight' => $summary['total_weight'],
                'formative_weight' => $summary['formative_weight'],
                'summative_weight' => $summary['summative_weight'],
                'items_count' => $summary['items_count'],
                'is_balanced' => $summary['is_balanced'],
            ];
        })->values();
    }
}

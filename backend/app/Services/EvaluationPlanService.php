<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseEvaluationPlan;
use App\Models\CourseEvaluationPlanItem;
use App\Models\Evaluation;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * Single source of truth for linking Evaluations to a course's Evaluation Plan.
 * Every plan is unique per (teacher_id, course_id); items are keyed by evaluation_id.
 */
class EvaluationPlanService
{
    public function resolvePlanForCourse(User $teacher, Course|int $course): CourseEvaluationPlan
    {
        $courseId = $course instanceof Course ? $course->id : (int) $course;
        $courseModel = $course instanceof Course ? $course : Course::find($courseId);

        return CourseEvaluationPlan::firstOrCreate(
            [
                'teacher_id' => $teacher->id,
                'course_id' => $courseId,
            ],
            $this->onlyExistingColumns('course_evaluation_plans', [
                'title' => 'Plan de evaluación · '.($courseModel?->subject_name ?? 'Curso'),
                'summary' => 'Plan sincronizado automáticamente desde AulaSync.',
                'status' => 'draft',
            ])
        );
    }

    /**
     * Create or update the plan item linked to an evaluation (matched by evaluation_id).
     *
     * Supported $opts keys: plan_id, unit_name, assessment_type, category,
     * weight_percentage, due_date, notes, learning_outcome.
     */
    public function syncItemForEvaluation(Evaluation $evaluation, array $opts = []): ?CourseEvaluationPlanItem
    {
        if (! Schema::hasTable('course_evaluation_plans') || ! $evaluation->course_id) {
            return null;
        }

        $plan = null;
        if (! empty($opts['plan_id'])) {
            $plan = CourseEvaluationPlan::where('id', $opts['plan_id'])
                ->where('teacher_id', $evaluation->teacher_id)
                ->first();
        }

        if (! $plan) {
            $teacher = $evaluation->teacher ?: User::find($evaluation->teacher_id);
            if (! $teacher) {
                return null;
            }
            $plan = $this->resolvePlanForCourse($teacher, $evaluation->course_id);
        }

        $weight = (float) ($opts['weight_percentage'] ?? 10);
        $weight = max(1, min(100, $weight));
        $category = in_array(($opts['category'] ?? 'summative'), ['formative', 'summative'], true)
            ? $opts['category']
            : 'summative';
        $unitName = trim((string) ($opts['unit_name'] ?? $evaluation->topic ?? 'Unidad'));
        $unitName = $unitName !== '' ? $unitName : 'Unidad';
        $dueDate = $opts['due_date'] ?? optional($evaluation->scheduled_at)->toDateString();

        $item = CourseEvaluationPlanItem::where('plan_id', $plan->id)
            ->where('evaluation_id', $evaluation->id)
            ->first();

        $attrs = $this->onlyExistingColumns('course_evaluation_plan_items', [
            'unit_name' => $unitName,
            'assessment_type' => $opts['assessment_type'] ?? $evaluation->title,
            'category' => $category,
            'weight_percentage' => $weight,
            'due_date' => $dueDate,
            'notes' => $opts['notes'] ?? 'Sincronizado automáticamente desde AulaSync.',
            'learning_outcome' => $opts['learning_outcome'] ?? null,
        ]);

        if ($item) {
            $item->fill($attrs);
            $item->save();

            return $item;
        }

        $attrs['plan_id'] = $plan->id;
        $attrs['evaluation_id'] = $evaluation->id;

        return CourseEvaluationPlanItem::create($attrs);
    }

    /**
     * Aggregate weight totals for a plan so the UI/chatbot can show live progress toward 100%.
     */
    public function planSummary(CourseEvaluationPlan $plan): array
    {
        $items = $plan->relationLoaded('items') ? $plan->items : $plan->items()->get();

        $total = round((float) $items->sum('weight_percentage'), 2);
        $formative = round((float) $items->where('category', 'formative')->sum('weight_percentage'), 2);
        $summative = round((float) $items->where('category', 'summative')->sum('weight_percentage'), 2);

        return [
            'plan_id' => $plan->id,
            'total_weight' => $total,
            'formative_weight' => $formative,
            'summative_weight' => $summative,
            'items_count' => $items->count(),
            'is_balanced' => abs($total - 100) <= 0.5,
        ];
    }

    private function onlyExistingColumns(string $table, array $data): array
    {
        return array_filter($data, fn ($key) => Schema::hasColumn($table, $key), ARRAY_FILTER_USE_KEY);
    }
}

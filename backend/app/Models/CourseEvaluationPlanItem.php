<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseEvaluationPlanItem extends Model
{
    protected $fillable = [
        'plan_id',
        'evaluation_id',
        'unit_name',
        'assessment_type',
        'category',
        'weight_percentage',
        'due_date',
        'notes',
        'learning_outcome',
    ];

    protected function casts(): array
    {
        return [
            'weight_percentage' => 'float',
            'due_date' => 'date',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(CourseEvaluationPlan::class, 'plan_id');
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }
}

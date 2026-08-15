<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseEvaluationPlanItem extends Model
{
    protected $fillable = [
        'plan_id',
        'unit_name',
        'assessment_type',
        'weight_percentage',
        'due_date',
        'notes',
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
}


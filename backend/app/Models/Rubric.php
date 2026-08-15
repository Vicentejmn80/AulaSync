<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rubric extends Model
{
    protected $fillable = [
        'teacher_id',
        'course_id',
        'evaluation_id',
        'title',
        'description',
        'task_type',
        'type',
        'levels',
        'total_points',
        'generated_by_ai',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'levels' => 'array',
            'generated_by_ai' => 'boolean',
            'total_points' => 'integer',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }

    public function criteria(): HasMany
    {
        return $this->hasMany(RubricCriterion::class)->orderBy('sort_order');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Evaluation extends Model
{
    protected $fillable = [
        'teacher_id',
        'course_id',
        'activity_id',
        'colegio_id',
        'title',
        'description',
        'topic',
        'mode',
        'status',
        'difficulty',
        'question_mix',
        'question_count',
        'generated_by_ai',
        'instructions',
        'scheduled_at',
        'total_points',
        'passing_score',
        'rubric',
        'physical_format',
        'large_print',
        'public_token',
    ];

    protected function casts(): array
    {
        return [
            'generated_by_ai' => 'boolean',
            'large_print' => 'boolean',
            'scheduled_at' => 'datetime',
            'rubric' => 'array',
            'physical_format' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $evaluation) {
            if (! $evaluation->public_token) {
                $evaluation->public_token = Str::random(40);
            }
        });
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(EvaluationQuestion::class)->orderBy('sort_order');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(EvaluationAttempt::class);
    }

    public function planItem(): HasOne
    {
        return $this->hasOne(CourseEvaluationPlanItem::class, 'evaluation_id');
    }
}

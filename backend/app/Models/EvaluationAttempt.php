<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvaluationAttempt extends Model
{
    protected $fillable = [
        'evaluation_id',
        'student_id',
        'student_name',
        'answers',
        'score',
        'status',
        'ai_feedback',
    ];

    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'ai_feedback' => 'array',
            'score' => 'float',
        ];
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}

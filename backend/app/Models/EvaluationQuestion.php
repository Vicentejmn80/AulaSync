<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvaluationQuestion extends Model
{
    protected $fillable = [
        'evaluation_id',
        'sort_order',
        'type',
        'text',
        'options',
        'correct_answer',
        'points',
        'topic',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'points' => 'integer',
        ];
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }
}

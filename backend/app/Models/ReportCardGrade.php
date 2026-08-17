<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportCardGrade extends Model
{
    protected $fillable = [
        'report_card_id',
        'course_id',
        'course_name',
        'grade',
        'letter_grade',
        'teacher_observations',
        'is_manual',
    ];

    protected function casts(): array
    {
        return [
            'grade'     => 'decimal:2',
            'is_manual' => 'boolean',
        ];
    }

    public function reportCard(): BelongsTo
    {
        return $this->belongsTo(ReportCard::class);
    }
}

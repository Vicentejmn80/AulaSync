<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Grade extends Model
{
    protected $fillable = ['activity_id', 'student_id', 'colegio_id', 'score', 'status', 'published_at', 'feedback_text'];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'published_at' => 'datetime',
        ];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}

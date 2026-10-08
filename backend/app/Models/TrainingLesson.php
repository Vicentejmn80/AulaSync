<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingLesson extends Model
{
    protected $fillable = [
        'training_program_id',
        'title',
        'description',
        'video_url',
        'duration_minutes',
        'position',
        'status',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(TrainingProgram::class, 'training_program_id');
    }

    public function progress(): HasMany
    {
        return $this->hasMany(TrainingProgress::class);
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }
}

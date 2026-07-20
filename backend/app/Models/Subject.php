<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subject extends Model
{
    protected $fillable = [
        'course_id',
        'name',
        'color',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}

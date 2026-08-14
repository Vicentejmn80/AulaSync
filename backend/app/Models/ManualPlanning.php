<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ManualPlanning extends Model
{
    use HasFactory;

    protected $fillable = [
        'teacher_id',
        'course_id',
        'subject_id',
        'month',
        'year',
        'sessions',
    ];

    protected $casts = [
        'sessions' => 'array',
    ];
}
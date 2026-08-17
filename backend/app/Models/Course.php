<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Subject;
use App\Support\GradingScale;

class Course extends Model
{
    protected $fillable = [
        'teacher_id',
        'colegio_id',
        'subject_name',
        'grade',
        'section',
        'school_year',
        'grading_scale',
    ];

    protected $attributes = [
        'grading_scale' => GradingScale::SCALE_1_20,
    ];

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'course_student')
                    ->withPivot('enrolled_at', 'nota_actual', 'promedio_acumulado')
                    ->orderBy('name');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }
}
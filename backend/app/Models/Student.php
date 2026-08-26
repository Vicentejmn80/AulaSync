<?php

namespace App\Models;

use App\Support\GradeLabel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    protected $fillable = [
        'teacher_id',
        'colegio_id',
        'name',
        'grade',
        'section',
        'document_id',
        'birthdate',
        'family_code',
    ];

    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Student $student) {
            $canonical = GradeLabel::canonical($student->grade);
            if ($canonical) {
                $student->grade = $canonical;
            }
        });
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function colegio(): BelongsTo
    {
        return $this->belongsTo(Colegio::class);
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_student')
                    ->withPivot('enrolled_at', 'nota_actual', 'promedio_acumulado');
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function absenceRequests(): HasMany
    {
        return $this->hasMany(AbsenceRequest::class);
    }

    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'guardian_student')
            ->withPivot('relationship')
            ->withTimestamps();
    }
}

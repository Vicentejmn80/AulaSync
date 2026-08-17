<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportCard extends Model
{
    protected $fillable = [
        'student_id',
        'academic_period_id',
        'colegio_id',
        'status',
        'observations',
        'generated_at',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class, 'academic_period_id');
    }

    public function colegio(): BelongsTo
    {
        return $this->belongsTo(Colegio::class);
    }

    public function grades(): HasMany
    {
        return $this->hasMany(ReportCardGrade::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(GradeAuditLog::class);
    }

    public function globalAverage(): float
    {
        $grades = $this->grades;
        if ($grades->isEmpty()) {
            return 0.0;
        }

        return round((float) $grades->avg('grade'), 1);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'published' => 'Publicada',
            default     => 'Borrador',
        };
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'published' => 'green',
            default     => 'slate',
        };
    }
}

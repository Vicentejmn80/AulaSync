<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    public const STATUS_PRESENT = 'present';
    public const STATUS_ABSENT = 'absent';
    public const STATUS_TARDY = 'tardy';

    protected $fillable = [
        'colegio_id',
        'course_id',
        'student_id',
        'teacher_id',
        'attended_on',
        'status',
        'reason_id',
        'note',
        'source',
        'client_uuid',
        'notified_at',
    ];

    protected function casts(): array
    {
        return [
            'attended_on' => 'date',
            'notified_at' => 'datetime',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function reason(): BelongsTo
    {
        return $this->belongsTo(AttendanceReason::class, 'reason_id');
    }

    public function isAbsent(): bool
    {
        return $this->status === self::STATUS_ABSENT;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceReason extends Model
{
    protected $fillable = [
        'colegio_id',
        'code',
        'label',
        'category',
        'requires_comment',
        'is_system',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'requires_comment' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'reason_id');
    }
}

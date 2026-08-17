<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GradeAuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'report_card_id',
        'user_id',
        'action',
        'field_changed',
        'old_value',
        'new_value',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function reportCard(): BelongsTo
    {
        return $this->belongsTo(ReportCard::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

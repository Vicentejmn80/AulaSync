<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunicationAnnouncementRead extends Model
{
    protected $fillable = [
        'announcement_id',
        'student_id',
        'recipient_name',
        'recipient_type',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(CommunicationAnnouncement::class, 'announcement_id');
    }
}


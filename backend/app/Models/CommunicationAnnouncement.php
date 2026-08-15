<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunicationAnnouncement extends Model
{
    protected $fillable = [
        'teacher_id',
        'colegio_id',
        'title',
        'body',
        'targeting',
        'attachments',
        'scheduled_at',
        'sent_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'targeting' => 'array',
            'attachments' => 'array',
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function reads(): HasMany
    {
        return $this->hasMany(CommunicationAnnouncementRead::class, 'announcement_id');
    }
}


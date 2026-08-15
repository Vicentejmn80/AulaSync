<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunicationMessage extends Model
{
    protected $fillable = [
        'thread_id',
        'sender_role',
        'body',
        'ai_suggested',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'ai_suggested' => 'boolean',
            'read_at' => 'datetime',
        ];
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(CommunicationThread::class, 'thread_id');
    }
}


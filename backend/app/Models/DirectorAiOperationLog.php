<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DirectorAiOperationLog extends Model
{
    protected $fillable = [
        'director_user_id',
        'colegio_id',
        'intent',
        'status',
        'input_payload',
        'result_payload',
        'error_payload',
        'confirmed_at',
        'executed_at',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'input_payload' => 'array',
            'result_payload' => 'array',
            'error_payload' => 'array',
            'confirmed_at' => 'datetime',
            'executed_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function director(): BelongsTo
    {
        return $this->belongsTo(User::class, 'director_user_id');
    }
}

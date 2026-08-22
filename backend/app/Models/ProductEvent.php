<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'colegio_id',
        'role',
        'source',
        'event',
        'action',
        'category',
        'status',
        'duration_ms',
        'error_code',
        'prompt_tokens',
        'completion_tokens',
        'estimated_cost_usd',
        'meta',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'created_at' => 'datetime',
            'estimated_cost_usd' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function colegio(): BelongsTo
    {
        return $this->belongsTo(Colegio::class);
    }
}

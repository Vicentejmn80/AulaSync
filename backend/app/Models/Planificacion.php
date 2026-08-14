<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Planificacion extends Model
{
    protected $fillable = [
        'user_id',
        'tema',
        'objetivo',
        'slug',
        'payload',
        'status',
        'colegio_id',
    ];

    protected $casts = [
        'payload' => 'array',
        'status' => 'string',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class, 'plan_block_id');
    }
}
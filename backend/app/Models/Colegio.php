<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Colegio extends Model
{
    protected $fillable = [
        'name',
        'invite_code',
        'director_user_id',
    ];

    public function director(): BelongsTo
    {
        return $this->belongsTo(User::class, 'director_user_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'colegio_id');
    }
}

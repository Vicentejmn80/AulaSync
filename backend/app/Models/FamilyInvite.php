<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FamilyInvite extends Model
{
    protected $fillable = [
        'colegio_id',
        'created_by',
        'family_code',
        'invite_code',
        'revoked_at',
        'revoked_by',
    ];

    protected function casts(): array
    {
        return [
            'revoked_at' => 'datetime',
        ];
    }

    public function colegio(): BelongsTo
    {
        return $this->belongsTo(Colegio::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class, 'family_code', 'family_code');
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isActive(): bool
    {
        return ! $this->isRevoked();
    }

    public function registrationUrl(): string
    {
        $this->loadMissing('colegio');

        return url('/familia/unirse?'.http_build_query(array_filter([
            'school' => $this->colegio?->invite_code,
            'code' => $this->invite_code,
        ])));
    }
}

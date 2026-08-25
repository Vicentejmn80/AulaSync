<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Invitation extends Model
{
    public const ROLE_DIRECTOR = 'director';
    public const ROLE_DOCENTE = 'profesor';
    public const ROLE_REPRESENTANTE = 'representante';

    protected $fillable = [
        'email',
        'role',
        'colegio_id',
        'student_id',
        'invited_by',
        'token',
        'expires_at',
        'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public static function normalizeRole(string $role): string
    {
        $role = strtolower(trim($role));

        return match ($role) {
            'docente', 'profesor', 'teacher' => self::ROLE_DOCENTE,
            'parent', 'guardian', 'representante' => self::ROLE_REPRESENTANTE,
            default => self::ROLE_DIRECTOR,
        };
    }

    public static function makeToken(): string
    {
        do {
            $token = Str::random(40);
        } while (static::query()->where('token', $token)->exists());

        return $token;
    }

    public function colegio(): BelongsTo
    {
        return $this->belongsTo(Colegio::class, 'colegio_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null && $this->expires_at?->isFuture();
    }

    public function isExpired(): bool
    {
        return $this->accepted_at === null && $this->expires_at?->isPast();
    }

    public function acceptUrl(): string
    {
        return url('/accept-invitation/'.$this->token);
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            self::ROLE_DOCENTE => 'Docente',
            self::ROLE_REPRESENTANTE => 'Representante',
            default => 'Director',
        };
    }
}

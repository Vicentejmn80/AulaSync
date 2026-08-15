<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;

class Colegio extends Model
{
    protected $fillable = [
        'name',
        'invite_code',
        'codes_pin',
        'director_user_id',
    ];

    protected $hidden = [
        'codes_pin',
    ];

    public function director(): BelongsTo
    {
        return $this->belongsTo(User::class, 'director_user_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'colegio_id');
    }

    public static function defaultPinFromInvite(?string $inviteCode): string
    {
        $digits = preg_replace('/\D+/', '', (string) $inviteCode) ?: '0000';
        $pin = substr($digits, -4);

        return strlen($pin) < 4 ? str_pad($pin, 4, '0', STR_PAD_LEFT) : $pin;
    }

    public static function hashPinFromInvite(?string $inviteCode): string
    {
        return Hash::make(self::defaultPinFromInvite($inviteCode));
    }
}

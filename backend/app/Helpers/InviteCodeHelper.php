<?php

namespace App\Helpers;

use App\Models\Colegio;
use Illuminate\Support\Str;

class InviteCodeHelper
{
    public static function normalize(string $value): string
    {
        $normalized = strtoupper(trim($value));

        return preg_replace('/[^A-Z0-9\-]/', '', $normalized) ?? '';
    }

    public static function makePrefix(string $schoolName): string
    {
        $parts = preg_split('/\s+/', trim($schoolName)) ?: [];
        $initials = collect($parts)
            ->filter()
            ->map(fn (string $part) => strtoupper(substr($part, 0, 1)))
            ->implode('');

        if ($initials === '') {
            $initials = strtoupper(substr(Str::slug($schoolName, ''), 0, 3));
        }

        return str_pad(substr($initials, 0, 3), 3, 'X');
    }

    public static function generateUnique(string $schoolName): string
    {
        $prefix = self::makePrefix($schoolName);

        do {
            $candidate = $prefix . '-' . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (Colegio::where('invite_code', $candidate)->exists());

        return $candidate;
    }
}

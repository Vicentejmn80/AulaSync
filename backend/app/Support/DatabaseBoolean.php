<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * PostgreSQL no acepta boolean = 1. SQLite/MySQL sí usan 0/1.
 */
class DatabaseBoolean
{
    public static function equals(string $column, bool $value, ?string $driver = null): string
    {
        $driver ??= DB::connection()->getDriverName();
        $literal = $driver === 'pgsql'
            ? ($value ? 'true' : 'false')
            : ($value ? '1' : '0');

        return $column.' = '.$literal;
    }

    public static function bind(bool $value, ?string $driver = null): mixed
    {
        $driver ??= DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            return DB::raw($value ? 'true' : 'false');
        }

        return $value;
    }
}

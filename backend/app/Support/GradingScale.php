<?php

namespace App\Support;

class GradingScale
{
    public const SCALE_1_5 = '1-5';

    public const SCALE_1_10 = '1-10';

    public const SCALE_1_20 = '1-20';

    /** @var list<string> */
    public const VALID = [
        self::SCALE_1_5,
        self::SCALE_1_10,
        self::SCALE_1_20,
    ];

    public static function normalize(?string $value): string
    {
        $value = trim((string) $value);

        return in_array($value, self::VALID, true) ? $value : self::SCALE_1_20;
    }

    public static function maxFor(?string $scale): int
    {
        return match (self::normalize($scale)) {
            self::SCALE_1_5 => 5,
            self::SCALE_1_10 => 10,
            default => 20,
        };
    }

    public static function label(?string $scale): string
    {
        return match (self::normalize($scale)) {
            self::SCALE_1_5 => '1 al 5',
            self::SCALE_1_10 => '1 al 10',
            default => '1 al 20',
        };
    }

    public static function shortLabel(?string $scale): string
    {
        return self::normalize($scale);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::SCALE_1_5 => '1 al 5',
            self::SCALE_1_10 => '1 al 10',
            self::SCALE_1_20 => '1 al 20',
        ];
    }

    public static function effectiveMax(?string $scale, ?int $activityMaxScore): int
    {
        $scaleMax = self::maxFor($scale);
        $activityMax = max(1, (int) ($activityMaxScore ?: $scaleMax));

        return min($scaleMax, $activityMax);
    }

    public static function isValidScore(?string $scale, mixed $score, ?int $activityMaxScore = null): bool
    {
        if (! is_numeric($score)) {
            return false;
        }

        $value = (float) $score;
        $max = self::effectiveMax($scale, $activityMaxScore);

        return $value >= 0 && $value <= $max;
    }

    public static function clampScore(?string $scale, mixed $score, ?int $activityMaxScore = null): float
    {
        $max = self::effectiveMax($scale, $activityMaxScore);

        return max(0, min($max, (float) $score));
    }
}

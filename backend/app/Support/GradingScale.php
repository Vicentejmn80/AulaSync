<?php

namespace App\Support;

class GradingScale
{
    public const SCALE_1_5 = '1-5';

    public const SCALE_1_10 = '1-10';

    public const SCALE_1_20 = '1-20';

    public const SCALE_LETTER = 'A-F';

    /** @var list<string> */
    public const VALID = [
        self::SCALE_1_5,
        self::SCALE_1_10,
        self::SCALE_1_20,
        self::SCALE_LETTER,
    ];

    /**
     * Letras guardadas como número en escala 0-20 para poder promediar.
     *
     * @return array<string, float>
     */
    public static function letterValues(): array
    {
        return [
            'A' => 20.0,
            'B' => 16.0,
            'C' => 12.0,
            'D' => 8.0,
            'E' => 4.0,
            'F' => 0.0,
        ];
    }

    public static function normalize(?string $value): string
    {
        $value = trim((string) $value);

        return in_array($value, self::VALID, true) ? $value : self::SCALE_1_20;
    }

    public static function isLetter(?string $scale): bool
    {
        return self::normalize($scale) === self::SCALE_LETTER;
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
            self::SCALE_1_5 => '0 a 5',
            self::SCALE_1_10 => '0 a 10',
            self::SCALE_LETTER => 'Letras A–F',
            default => '0 a 20',
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
            self::SCALE_1_20 => '0 a 20',
            self::SCALE_1_10 => '0 a 10',
            self::SCALE_1_5 => '0 a 5',
            self::SCALE_LETTER => 'Letras A, B, C, D, E, F',
        ];
    }

    /**
     * La escala del curso manda. El max_score de la actividad no puede
     * recortar 0, 10 o 20 (muchas clases quedan con 0/1/9 de arrastre).
     */
    public static function effectiveMax(?string $scale, ?int $activityMaxScore = null): int
    {
        return self::maxFor($scale);
    }

    public static function parseInput(?string $scale, mixed $raw): ?float
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $text = strtoupper(trim((string) $raw));
        $letters = self::letterValues();
        if (isset($letters[$text])) {
            $points = $letters[$text];
            if (self::isLetter($scale)) {
                return $points;
            }

            return round(($points / 20) * self::maxFor($scale), 2);
        }

        if (! is_numeric($raw)) {
            return null;
        }

        return (float) $raw;
    }

    public static function letterForScore(mixed $score): string
    {
        $value = (float) $score;
        $best = 'F';
        $bestDiff = PHP_FLOAT_MAX;
        foreach (self::letterValues() as $letter => $points) {
            $diff = abs($value - $points);
            if ($diff < $bestDiff) {
                $bestDiff = $diff;
                $best = $letter;
            }
        }

        return $best;
    }

    public static function display(?string $scale, mixed $score): string
    {
        if ($score === null || $score === '') {
            return '';
        }

        if (self::isLetter($scale)) {
            return self::letterForScore($score);
        }

        $number = (float) $score;
        if (floor($number) == $number) {
            return (string) (int) $number;
        }

        return rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
    }

    public static function isValidScore(?string $scale, mixed $score, ?int $activityMaxScore = null): bool
    {
        $value = self::parseInput($scale, $score);
        if ($value === null) {
            return false;
        }

        $max = self::effectiveMax($scale, $activityMaxScore);

        return $value >= 0 && $value <= $max;
    }

    public static function clampScore(?string $scale, mixed $score, ?int $activityMaxScore = null): float
    {
        $max = self::effectiveMax($scale, $activityMaxScore);
        $value = self::parseInput($scale, $score) ?? 0.0;

        return max(0, min($max, $value));
    }
}

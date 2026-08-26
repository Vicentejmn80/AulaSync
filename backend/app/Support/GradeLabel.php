<?php

namespace App\Support;

class GradeLabel
{
    public static function number(?string $grade): ?int
    {
        $raw = mb_strtolower(trim((string) $grade));
        if ($raw === '') {
            return null;
        }
        $raw = strtr($raw, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
        ]);

        $patterns = [
            1 => '/1ero|1er|1ro|primero|primer|1[°º]/u',
            2 => '/2do|segundo|2[°º]/u',
            3 => '/3ero|3er|3ro|tercero|tercer|3[°º]/u',
            4 => '/4to|cuarto|4[°º]/u',
            5 => '/5to|quinto|5[°º]/u',
            6 => '/6to|sexto|6[°º]/u',
        ];
        foreach ($patterns as $number => $pattern) {
            if (preg_match($pattern, $raw)) {
                return $number;
            }
        }

        if (preg_match('/[1-6]/', $raw, $match)) {
            return (int) $match[0];
        }

        return null;
    }

    public static function key(?string $grade): string
    {
        $number = self::number($grade);

        return $number ? (string) $number : '';
    }

    public static function canonical(?string $grade): ?string
    {
        return match (self::number($grade)) {
            1 => '1ro',
            2 => '2do',
            3 => '3ro',
            4 => '4to',
            5 => '5to',
            6 => '6to',
            default => ($grade !== null && trim($grade) !== '') ? trim($grade) : null,
        };
    }
}

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

    /**
     * @return array<int,string>
     */
    public static function range(int $from, int $to): array
    {
        $start = min($from, $to);
        $end = max($from, $to);
        if ($start < 1 || $end > 6) {
            return [];
        }

        $grades = [];
        for ($i = $start; $i <= $end; $i++) {
            $grades[] = self::canonical((string) $i);
        }

        return array_values(array_filter($grades));
    }

    /**
     * Expande "de 2do grado a 6to grado", "desde segundo hasta sexto", "1ro a 6to".
     * No trata "2do y 6to" como rango: eso son grados sueltos.
     *
     * @return array<int,string>
     */
    public static function expandRangeFromText(string $text): array
    {
        $value = mb_strtolower($text);
        $value = strtr($value, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
        ]);

        $token = '(?:primer[oa]?|segund[oa]|tercer[oa]?|cuart[oa]|quint[oa]|sext[oa]|[1-6](?:ro|ero|er|do|to|[°º])?)';
        $grado = '(?:\s+grados?)?';
        $connector = '(?:hasta|a(?:l)?)';
        $patterns = [
            '/(?:desde|de)\s+('.$token.')'.$grado.'\s+'.$connector.'\s+('.$token.')'.$grado.'/u',
            '/\b('.$token.')'.$grado.'\s+(?:hasta|a(?:l)?|-|–|—)\s+('.$token.')'.$grado.'/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value, $match)) {
                $from = self::number($match[1]);
                $to = self::number($match[2]);
                if ($from !== null && $to !== null) {
                    return self::range($from, $to);
                }
            }
        }

        return [];
    }

    /**
     * Si el texto pide un rango, gana el rango completo. Evita que el LLM o el
     * extractor se queden solo con los extremos ("2do" y "6to").
     *
     * @param  array<int,mixed>  $grades
     * @return array<int,string>
     */
    public static function preferExpandedRange(array $grades, string $text): array
    {
        $range = self::expandRangeFromText($text);
        $canonical = [];
        foreach ($grades as $grade) {
            $value = self::canonical(is_string($grade) || is_numeric($grade) ? (string) $grade : null);
            if ($value !== null && $value !== '') {
                $canonical[] = $value;
            }
        }
        $canonical = array_values(array_unique($canonical));

        if ($range === []) {
            return $canonical;
        }

        if ($canonical === [] || count($canonical) < count($range)) {
            return $range;
        }

        $missing = array_diff($range, $canonical);
        if ($missing !== []) {
            return $range;
        }

        return $canonical;
    }
}

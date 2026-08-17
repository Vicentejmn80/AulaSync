<?php

namespace App\Support;

class LessonTemplate
{
    public const CLASSIC = 'clasica';
    public const DIRECT = 'directa';
    public const CONSTRUCTIVIST = 'constructivista';

    public static function normalize(string $id): string
    {
        return self::fromAny($id) ?? self::CLASSIC;
    }

    /**
     * Interpreta ids cortos, etiquetas de UI y valores legacy de perfil.
     */
    public static function fromAny(?string $raw): ?string
    {
        $value = mb_strtolower(trim((string) $raw));
        if ($value === '') {
            return null;
        }

        if (in_array($value, [self::DIRECT, 'direct', 'directa', 'instruccion directa', 'instrucción directa'], true)
            || str_contains($value, 'instrucci')) {
            return self::DIRECT;
        }

        if (in_array($value, [self::CONSTRUCTIVIST, '5e', 'modelo 5e', 'constructivista', 'constructivism'], true)
            || str_contains($value, 'constructiv')
            || str_contains($value, '5e')) {
            return self::CONSTRUCTIVIST;
        }

        if (in_array($value, [self::CLASSIC, 'clasica', 'clásica', 'classic', 'tradicional'], true)
            || str_contains($value, 'clasic')) {
            return self::CLASSIC;
        }

        return null;
    }

    /**
     * Plantilla pedagógica preferida del docente (perfil / user_settings).
     */
    public static function forUser(?\App\Models\User $user): string
    {
        if (! $user) {
            return self::CLASSIC;
        }

        $settings = $user->relationLoaded('settings')
            ? $user->settings
            : $user->settings()->first();

        foreach ([
            $settings?->lesson_template,
            $settings?->modelo_pedagogico,
            $settings?->estilo_pedagogico,
        ] as $candidate) {
            $normalized = self::fromAny(is_scalar($candidate) ? (string) $candidate : null);
            if ($normalized) {
                return $normalized;
            }
        }

        return self::CLASSIC;
    }

    public static function label(string $id): string
    {
        return match (self::normalize($id)) {
            self::DIRECT => 'Instrucción Directa',
            self::CONSTRUCTIVIST => 'Modelo 5E',
            default => 'Clásica',
        };
    }

    public static function sections(string $id): array
    {
        return array_column(self::phaseDefs($id), 'header');
    }

    /**
     * @return array<int, array{key:string,header:string,label:string,color:string,icon:string,placeholder:string}>
     */
    public static function phaseDefs(string $id): array
    {
        return match (self::normalize($id)) {
            self::DIRECT => [
                ['key' => 'motivacion', 'header' => 'MOTIVACIÓN', 'label' => 'Motivación', 'color' => '#F59E0B', 'icon' => 'fa-solid fa-bolt', 'placeholder' => 'Enlace con la experiencia previa y propósito de la clase…'],
                ['key' => 'presentacion', 'header' => 'PRESENTACIÓN', 'label' => 'Presentación', 'color' => '#7C3AED', 'icon' => 'fa-solid fa-chalkboard-user', 'placeholder' => 'El docente modela el contenido paso a paso…'],
                ['key' => 'practica', 'header' => 'PRÁCTICA GUIADA', 'label' => 'Práctica guiada', 'color' => '#06B6D4', 'icon' => 'fa-solid fa-people-group', 'placeholder' => 'El alumno practica con apoyo y corrección…'],
                ['key' => 'cierre_reflexivo', 'header' => 'CIERRE REFLEXIVO', 'label' => 'Cierre reflexivo', 'color' => '#22C55E', 'icon' => 'fa-solid fa-flag-checkered', 'placeholder' => 'Reflexión, aplicación autónoma y autoevaluación…'],
            ],
            self::CONSTRUCTIVIST => [
                ['key' => 'activacion', 'header' => 'ACTIVACIÓN', 'label' => 'Activación', 'color' => '#EF4444', 'icon' => 'fa-solid fa-lightbulb', 'placeholder' => 'Pregunta provocadora o situación problemática…'],
                ['key' => 'exploracion', 'header' => 'EXPLORACIÓN', 'label' => 'Exploración', 'color' => '#F59E0B', 'icon' => 'fa-solid fa-magnifying-glass', 'placeholder' => 'Los alumnos exploran el fenómeno o concepto…'],
                ['key' => 'explicacion', 'header' => 'EXPLICACIÓN', 'label' => 'Explicación', 'color' => '#7C3AED', 'icon' => 'fa-solid fa-book-open', 'placeholder' => 'Se formaliza el concepto con lenguaje disciplinar…'],
                ['key' => 'aplicacion', 'header' => 'APLICACIÓN', 'label' => 'Aplicación', 'color' => '#06B6D4', 'icon' => 'fa-solid fa-puzzle-piece', 'placeholder' => 'Transferencia a situaciones nuevas…'],
                ['key' => 'evaluacion', 'header' => 'EVALUACIÓN', 'label' => 'Evaluación', 'color' => '#22C55E', 'icon' => 'fa-solid fa-clipboard-check', 'placeholder' => 'Verificación del aprendizaje logrado…'],
            ],
            default => [
                ['key' => 'inicio', 'header' => 'INICIO', 'label' => 'Inicio', 'color' => '#7C3AED', 'icon' => 'fa-solid fa-play', 'placeholder' => 'Motivación y activación de saberes previos…'],
                ['key' => 'desarrollo', 'header' => 'DESARROLLO', 'label' => 'Desarrollo', 'color' => '#06B6D4', 'icon' => 'fa-solid fa-layer-group', 'placeholder' => 'Actividades principales y práctica guiada…'],
                ['key' => 'cierre', 'header' => 'CIERRE', 'label' => 'Cierre', 'color' => '#22C55E', 'icon' => 'fa-solid fa-flag-checkered', 'placeholder' => 'Síntesis, evaluación formativa, tarea…'],
            ],
        };
    }

    public static function promptLine(string $id): string
    {
        return collect(self::sections($id))
            ->map(fn ($s) => '**'.$s.'**')
            ->implode(' → ');
    }

    public static function allSectionNames(): array
    {
        return array_values(array_unique(array_merge(
            self::sections(self::CLASSIC),
            self::sections(self::DIRECT),
            self::sections(self::CONSTRUCTIVIST),
        )));
    }

    public static function detect(string $text): string
    {
        $best = self::CLASSIC;
        $bestCount = 0;
        foreach ([self::CLASSIC, self::DIRECT, self::CONSTRUCTIVIST] as $id) {
            $count = 0;
            foreach (self::sections($id) as $section) {
                if (preg_match('/\*\*\s*'.preg_quote($section, '/').'\s*\*\*/u', $text)) {
                    $count++;
                }
            }
            if ($count > $bestCount) {
                $bestCount = $count;
                $best = $id;
            }
        }

        return $best;
    }

    public static function hasRequiredHeaders(string $text, string $id): bool
    {
        foreach (self::sections($id) as $section) {
            if (! preg_match('/\*\*\s*'.preg_quote($section, '/').'\s*\*\*/u', $text)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, string> key => content
     */
    public static function parse(string $text, ?string $id = null): array
    {
        $id = $id ? self::normalize($id) : self::detect($text);
        $defs = self::phaseDefs($id);
        $headers = array_column($defs, 'header');
        $out = [];
        foreach ($defs as $def) {
            $out[$def['key']] = '';
        }

        for ($i = 0; $i < count($headers); $i++) {
            if (! preg_match('/\*\*\s*'.preg_quote($headers[$i], '/').'\s*\*\*/u', $text, $m, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            $start = $m[0][1] + strlen($m[0][0]);
            $end = strlen($text);
            for ($j = $i + 1; $j < count($headers); $j++) {
                if (preg_match('/\*\*\s*'.preg_quote($headers[$j], '/').'\s*\*\*/u', $text, $next, PREG_OFFSET_CAPTURE, $start)) {
                    $end = min($end, $next[0][1]);
                    break;
                }
            }
            $out[$defs[$i]['key']] = trim(substr($text, $start, $end - $start));
        }

        if (! implode('', $out) && trim($text) !== '') {
            $fallbackKey = $defs[min(1, count($defs) - 1)]['key'];
            $out[$fallbackKey] = trim($text);
        }

        return $out;
    }

    public static function build(array $values, string $id): string
    {
        $parts = [];
        foreach (self::phaseDefs($id) as $def) {
            $content = trim((string) ($values[$def['key']] ?? ''));
            if ($content === '') {
                continue;
            }
            $parts[] = '**'.$def['header']."**\n".$content;
        }

        return implode("\n\n", $parts);
    }

    /**
     * Re-mapea el markdown de una plantilla a otra conservando el contenido.
     */
    public static function rewrite(string $text, string $targetId): string
    {
        $targetId = self::normalize($targetId);
        if (self::hasRequiredHeaders($text, $targetId)) {
            return $text;
        }
        $sourceId = self::detect($text);
        $sourceValues = array_values(self::parse($text, $sourceId));
        $targetDefs = self::phaseDefs($targetId);
        $mapped = [];
        $n = count($targetDefs);
        $m = max(1, count($sourceValues));

        foreach ($targetDefs as $i => $def) {
            $srcIndex = (int) floor($i * $m / $n);
            $srcIndex = min($srcIndex, $m - 1);
            $mapped[$def['key']] = trim((string) ($sourceValues[$srcIndex] ?? ''));
        }

        // Si varias fases destino cayeron en el mismo bloque origen, deja el texto
        // en la primera y un puente breve en las siguientes vacías de más.
        $seen = [];
        foreach ($mapped as $key => $content) {
            if ($content === '') {
                continue;
            }
            if (isset($seen[$content])) {
                $mapped[$key] = '';
            } else {
                $seen[$content] = $key;
            }
        }

        $built = self::build($mapped, $targetId);
        if ($built !== '') {
            return $built;
        }

        $fallback = [];
        foreach ($targetDefs as $i => $def) {
            $fallback[$def['key']] = $i === min(1, $n - 1) ? trim($text) : '';
        }

        return self::build($fallback, $targetId);
    }
}

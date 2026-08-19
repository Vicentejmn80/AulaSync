<?php

namespace App\Services;

class PersonNameSanitizer
{
    private const SUBJECT_ALIASES = 'matem[aá]ticas?|ingl[eé]s|lengua|ciencias?|historia|geograf[ií]a|f[ií]sica|qu[ií]mica|biolog[ií]a|educaci[oó]n f[ií]sica';

    /**
     * Extrae un nombre de persona limpio, sin preposiciones, grado, curso ni conectores.
     */
    public function clean(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
        $name = trim($name, " \t\n\r\0\x0B,.;:");

        if (preg_match('/llamad[oa]\s+(.+)$/iu', $name, $called)) {
            $name = trim((string) $called[1]);
        }

        $name = preg_replace('/^(?:al|a la|el|la|los|las)\s+/iu', '', $name) ?? $name;
        $name = preg_replace('/^(?:alumno|alumna|estudiante)s?\s+/iu', '', $name) ?? $name;
        $name = preg_replace('/^(?:profesor(?:a)?|docente)\s+/iu', '', $name) ?? $name;
        $name = preg_replace('/^(?:de\s+(?:la\s+|el\s+)?)(?:'.self::SUBJECT_ALIASES.')\s+/iu', '', $name) ?? $name;
        $name = preg_replace('/\s+de\s+(?:la\s+|el\s+)?(?:'.self::SUBJECT_ALIASES.')\b.*$/iu', '', $name) ?? $name;

        $cutPattern = '/\s+(?:'
            .'(?:y\s+)?(?:asigna(?:lo|le|r|les)?|inscribe(?:lo|le|r)?|matricula(?:lo|le|r)?|agrega(?:lo|le)?|añade|anade)'
            .'|en\s+el|en\s+la|en\s+los|en\s+las'
            .'|al\s+curso|a\s+el\s+curso|del\s+curso|de\s+el\s+curso'
            .'|(?:en|de|del|para)\s+(?:el\s+|la\s+)?(?:primer|primero|segundo|tercer|tercero|cuarto|quinto|sexto|[1-6](?:ro|ero|er|do|to|°|º)?)'
            .'|(?:en|de|del|para)\s+(?:el\s+|la\s+)?(?:curso|grado|materia|asignatura|seccion|sección)'
            .'|con\s+(?:el\s+|la\s+)?(?:profesor|profesora|docente)'
            .').*$/iu';
        $name = preg_replace($cutPattern, '', $name) ?? $name;

        $name = preg_replace(
            '/\s+(?:en el|en la|en los|en las|del|de|en|al|a la|para|por)$/iu',
            '',
            $name
        ) ?? $name;

        $name = trim($name, " \t\n\r\0\x0B,.;:");
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);

        if ($name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 80) {
            return null;
        }

        if (preg_match('/^(?:en el|en la|en los|en las|en|de|del|el|la|los|las|al|a la|para|curso|grado|alumno|estudiante|profesor|profesora|docente|llamado|llamada)$/iu', $name)) {
            return null;
        }

        if (preg_match('/^(?:'.self::SUBJECT_ALIASES.')$/iu', $name)) {
            return null;
        }

        return $name;
    }

    public function cleanTeacher(?string $name): ?string
    {
        return $this->clean($name);
    }

    public function displayName(?string $name): string
    {
        $clean = $this->clean($name);
        if ($clean === null) {
            return trim((string) $name);
        }

        return $this->titleCase($clean);
    }

    public function titleCase(string $name): string
    {
        return mb_convert_case(trim($name), MB_CASE_TITLE, 'UTF-8');
    }

    public function searchNeedle(string $name): string
    {
        $clean = $this->clean($name) ?? $name;

        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $clean) ?? $clean));
    }
}

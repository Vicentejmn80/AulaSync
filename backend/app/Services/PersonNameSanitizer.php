<?php

namespace App\Services;

class PersonNameSanitizer
{
    private const SUBJECT_ALIASES = 'matem[aá]ticas?|ingl[eé]s|lenguaje|lengua|ciencias?|historia|geograf[ií]a|f[ií]sica|qu[ií]mica|biolog[ií]a|educaci[oó]n f[ií]sica|robotica|rob[oó]tica|computaci[oó]n|religi[oó]n';

    /**
     * Extrae un nombre de persona limpio, sin preposiciones, grado, curso ni conectores.
     */
    public function clean(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
        $name = preg_replace('/\s*\([^)]*\)\s*/u', ' ', $name) ?? $name;

        // Un nombre nunca cruza un punto final u otro cierre de oración: todo lo que
        // venga después pertenece a la siguiente instrucción ("...Jorge Luis. Él va a
        // dar Biología" no debe arrastrar "Él" ni nada de la segunda oración).
        $name = preg_replace('/[.!?¡¿].*/su', '', $name) ?? $name;

        $name = trim($name, " \t\n\r\0\x0B,.;:");

        if (preg_match('/llamad[oa]\s+(.+)$/iu', $name, $called)) {
            $name = trim((string) $called[1]);
        }

        $name = $this->stripFillers($name);

        $name = preg_replace('/^(?:a|al|a la|el|la|los|las)\s+/iu', '', $name) ?? $name;
        $name = preg_replace('/^(?:alumno|alumna|estudiante)s?\s+/iu', '', $name) ?? $name;
        $name = preg_replace('/^(?:profesor(?:a)?|docente)\s+/iu', '', $name) ?? $name;
        $name = preg_replace('/^(?:de\s+(?:la\s+|el\s+)?)(?:'.self::SUBJECT_ALIASES.')\s+/iu', '', $name) ?? $name;
        $name = preg_replace('/\s+de\s+(?:la\s+|el\s+)?(?:'.self::SUBJECT_ALIASES.')\b.*$/iu', '', $name) ?? $name;
        $name = preg_replace('/\s+(?:'.self::SUBJECT_ALIASES.')\b.*$/iu', '', $name) ?? $name;

        $cutPattern = '/\s+(?:'
            .'(?:y\s+)?(?:as[ií]gna(?:le|lo|r|les)?|inscr[ií]be(?:le|lo|r|les)?|matr[ií]cula(?:le|lo|r|les)?|agr[eé]ga(?:le|lo|les)?|añad(?:e|ele|eles)?|anad(?:e|ele|eles)?|cre[aá](?:r|me|le|les)?)'
            .'|en\s+el|en\s+la|en\s+los|en\s+las'
            .'|al\s+curso|a\s+el\s+curso|del\s+curso|de\s+el\s+curso'
            .'|que\s+va(?:\s+a(?:\s+dar)?)?|va\s+a\s+dar'
            .'|(?:en|de|del|para|a)\s+(?:el\s+|la\s+)?(?:primer|primero|segundo|tercer|tercero|cuarto|quinto|sexto|[1-6](?:ro|ero|er|do|to|°|º)?)'
            .'|(?:en|de|del|para)\s+(?:el\s+|la\s+)?(?:curso|grado|materia|asignatura|seccion|sección)'
            .'|con\s+(?:el\s+|la\s+)?(?:profesor|profesora|docente)'
            .'|(?:y\s+|,\s*)?(?:a\s+)?(?:al\s+|el\s+|la\s+)?(?:profesor(?:a)?|docente|maestr[oa])'
            .'|(?:tambien|también|ademas|además)'
            .').*$/iu';
        $name = preg_replace($cutPattern, '', $name) ?? $name;

        $name = preg_replace(
            '/\s+(?:en el|en la|en los|en las|del|de|en|al|a la|para|por)$/iu',
            '',
            $name
        ) ?? $name;

        // Pronombres/conectores arrastrados al final del nombre desde la
        // siguiente instrucción ("Jorge Luis El" tras "...Jorge Luis. Él va a
        // dar Biología" cuando el separador de oración no llegó a este punto).
        // Se repite porque puede haber más de uno pegado ("Jorge Luis El Que").
        do {
            $before = $name;
            $name = preg_replace(
                '/\s+(?:el|ella|ellos|ellas|ello|que|quien|quienes|cual|cuales|este|esta|esto|estos|estas|ese|esa|eso|esos|esas|ambos|ambas|mismo|misma)$/iu',
                '',
                $name
            ) ?? $name;
        } while ($name !== $before);

        $name = trim($name, " \t\n\r\0\x0B,.;:");
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);

        if ($name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 80) {
            return null;
        }

        if (preg_match('/^(?:en el|en la|en los|en las|en|de|del|el|la|los|las|al|a la|para|curso|grado|alumno|estudiante|profesor|profesora|docente|llamado|llamada|tambien|también|ademas|además|seccion|sección|siguientes?)$/iu', $name)) {
            return null;
        }

        // Un verbo imperativo suelto (arrastrado de la siguiente instrucción) nunca
        // es un nombre válido, aunque esté capitalizado al inicio de oración.
        if (preg_match('/^(?:agr[eé]ga(?:le|les|lo)?|as[ií]gna(?:le|les|lo)?|cre[aá](?:r|me|le|les)?|inscr[ií]be(?:le|les|lo)?|matr[ií]cula(?:le|les|lo)?|invita(?:r)?|añad(?:e|ele|eles)?|anad(?:e|ele|eles)?)$/iu', $name)) {
            return null;
        }

        if ($this->isStopwordOnlyName($name)) {
            return null;
        }

        if (preg_match('/^(?:'.self::SUBJECT_ALIASES.')$/iu', $name)) {
            return null;
        }

        return $name;
    }

    /**
     * Quita muletillas coloquiales que el director pega al nombre propio.
     */
    private function stripFillers(string $name): string
    {
        $name = preg_replace(
            '/\s+(?:tambien|también|ademas|además|por favor|ok|vale|el que te|que te|de la materia).*$/iu',
            '',
            $name
        ) ?? $name;
        $name = preg_replace(
            '/\b(?:que te (?:dije|dijeron|mencion[eé]|habl[eé]|coment[eé])(?:\s+antes)?|el que te (?:dije|mencion[eé]|habl[eé])(?:\s+antes)?|de la materia|tambien crea(?:r)? a|también crea(?:r)? a)\b.*$/iu',
            '',
            $name
        ) ?? $name;
        $name = preg_replace('/\b(?:tambien|también|ademas|además)\b/iu', ' ', $name) ?? $name;

        return trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
    }

    private function isStopwordOnlyName(string $name): bool
    {
        $stop = 'a|al|el|la|los|las|en|de|del|para|por|con|un|una|curso|cursos|grado|grados|materia|materias|asignatura|seccion|sección|alumno|alumna|alumnos|estudiante|estudiantes|siguientes?|computaci[oó]n|profesor|profesora|docente';
        $tokens = preg_split('/\s+/u', mb_strtolower(trim($name))) ?: [];
        if ($tokens === [] || $tokens === ['']) {
            return true;
        }
        foreach ($tokens as $token) {
            if (! preg_match('/^(?:'.$stop.')$/u', $token)) {
                return false;
            }
        }

        return true;
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

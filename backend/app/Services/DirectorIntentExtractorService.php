<?php

namespace App\Services;

/**
 * Extractor determinista de múltiples intenciones a partir de un mensaje del director.
 *
 * Cuando el LLM confunde acciones (por ejemplo, matricular alumnos como si fueran
 * profesores), este extractor local segmenta la frase en órdenes independientes y
 * devuelve la estructura normalizada. Sirve como fallback y como validador del
 * resultado del intérprete principal.
 */
class DirectorIntentExtractorService
{
    private const SUBJECT_PATTERN = 'matem[aá]ticas?|ingl[eé]s|lenguaje|lengua|ciencias?\s*naturales?|ciencias?\s*sociales?|historia|geograf[ií]a|f[ií]sica|qu[ií]mica|biolog[ií]a|educaci[oó]n\s*f[ií]sica|robotica|rob[oó]tica|computaci[oó]n|religi[oó]n|arte|m[uú]sica|pl[aá]stica';

    private const GRADE_PATTERN = '(?:primer|primero|segundo|tercer|tercero|cuarto|quinto|sexto|[1-6](?:ro|ero|er|do|to|°|º)?)';

    public function __construct(
        private PersonNameSanitizer $nameSanitizer,
    ) {}

    /**
     * Segmenta un texto en acciones individuales con sus datos extraídos.
     *
     * @return array<int,array{intent:string,data:array<string,mixed>}>
     */
    public function extractMultipleIntentions(string $text): array
    {
        $text = $this->normalizeWhitespace($text);
        if ($text === '') {
            return [];
        }

        $segments = $this->segment($text);
        $actions = [];
        $context = [];

        foreach ($segments as $segment) {
            $action = $this->parseSegment($segment, $context);
            if ($action === null) {
                // Algunos segmentos solo aportan contexto (materia, grados).
                // Si acabamos de crear una acción, rellenamos sus campos vacíos
                // con este nuevo contexto (por ejemplo, "Crea al profe... Él dará de 1ro a 6to").
                $this->applyContextToLastAction($segment, $actions, $context);
                $this->updateContext($segment, $context);

                continue;
            }

            // Si el segmento no trae materia/grados pero hay contexto previo, herédalo.
            $action = $this->mergeWithContext($action, $context);
            $actions[] = $action;

            // La última acción completa se convierte en contexto para pronombres/referencias.
            $context['last_action'] = $action;
            $this->updateContext($segment, $context);
        }

        return $this->finalize($actions);
    }

    private function normalizeWhitespace(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /**
     * Divide el texto en cláusulas ejecutables. Respeta listas de nombres y
     * no corta por comas que estén dentro de una enumeración de alumnos.
     *
     * @return array<int,string>
     */
    private function segment(string $text): array
    {
        // Primero separamos por oraciones.
        $sentences = preg_split('/(?<=[.!?])\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [$text];
        $sentences = array_map(fn ($s) => trim((string) $s), $sentences);

        $segments = [];
        foreach ($sentences as $sentence) {
            if ($sentence === '') {
                continue;
            }

            // Si una oración contiene dos verbos de acción distintos, la dividimos.
            $parts = $this->splitByActionVerbs($sentence);

            // Si la oración lista varios profesores, la fragmentamos en una acción por persona.
            $parts = $this->splitTeacherList($parts);

            foreach ($parts as $part) {
                $part = trim((string) $part);
                if ($part !== '' && ! $this->isOnlyFiller($part)) {
                    $segments[] = $part;
                }
            }
        }

        return array_values($segments);
    }

    /**
     * Detecta listas de profesores del tipo:
     * "Crea a los siguientes profesores: N1 (materia grados), N2 (materia grados)."
     * y devuelve un segmento por profesor.
     *
     * @param  array<int,string>  $parts
     * @return array<int,string>
     */
    private function splitTeacherList(array $parts): array
    {
        $result = [];
        foreach ($parts as $part) {
            if (! preg_match('/\bprofesores\b/iu', $part) || ! preg_match('/[,:]\s*.+\(/u', $part)) {
                $result[] = $part;

                continue;
            }

            // Removemos el encabezado "Crea a los siguientes profesores:".
            $body = preg_replace('/^.+?\bprofesores\b\s*[:,-]\s*/iu', '', $part) ?? $part;

            // Separamos por comas o "y" que estén fuera de paréntesis.
            $items = $this->splitPreservingParentheses($body);
            foreach ($items as $item) {
                $item = trim((string) $item, " \t\n\r\0\x0B,.;:");
                if ($item === '') {
                    continue;
                }
                // Reconstruimos una oración autocontenida para que el parser la entienda.
                $result[] = "Crea al profesor {$item}.";
            }
        }

        return $result === [] ? $parts : $result;
    }

    /**
     * @return array<int,string>
     */
    private function splitPreservingParentheses(string $text): array
    {
        $parts = [];
        $current = '';
        $depth = 0;
        $length = mb_strlen($text);
        for ($i = 0; $i < $length; $i++) {
            $char = mb_substr($text, $i, 1);
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
            }

            if ($depth === 0 && $char === ',') {
                if ($current !== '') {
                    $parts[] = $current;
                    $current = '';
                }

                continue;
            }

            $current .= $char;
        }
        if ($current !== '') {
            $parts[] = $current;
        }

        return $parts;
    }

    /**
     * @return array<int,string>
     */
    private function splitByActionVerbs(string $sentence): array
    {
        // Evitamos cortar listas de alumnos como "Carlos, Juan y Pedro".
        if (preg_match('/\b(?:alumnos?|estudiantes?)\b/iu', $sentence)) {
            return [$sentence];
        }

        $verbPattern = '\b(?:crea(?:r|me|s|n)?|crees|cree|agrega(?:r|le|lo|s|n)?|agregues?|inscribe(?:r|s|n)?|inscribes?|matricula(?:r|s|n)?|matricules?|asigna(?:r|le|lo|s|n)?|asignes?|invita(?:r|s|n)?|invites?|modifica(?:r|s|n)?|modifiques?|elimina(?:r|s|n)?|elimines?|borra(?:r|s)?|mueve(?:r|s)?|cambia(?:r|s)?|actualiza(?:r)?)\b';

        if (! preg_match_all('/'.$verbPattern.'/iu', $sentence, $matches, PREG_OFFSET_CAPTURE)) {
            return [$sentence];
        }

        $verbs = $matches[0];
        if (count($verbs) <= 1) {
            return [$sentence];
        }

        $parts = [];
        for ($i = 0; $i < count($verbs); $i++) {
            $start = (int) $verbs[$i][1];
            $end = isset($verbs[$i + 1]) ? (int) $verbs[$i + 1][1] : mb_strlen($sentence);
            $part = trim(mb_substr($sentence, $start, $end - $start));
            if ($part !== '') {
                $parts[] = $part;
            }
        }

        return $parts;
    }

    private function isOnlyFiller(string $text): bool
    {
        $clean = preg_replace('/\W+/u', '', mb_strtolower($text)) ?? '';

        return $clean === '' || in_array($clean, ['el', 'la', 'los', 'las', 'y', 'o'], true);
    }

    /**
     * Determina la intención de un segmento y extrae sus datos.
     *
     * @param  array<string,mixed>  $context
     * @return array{intent:string,data:array<string,mixed>}|null
     */
    private function parseSegment(string $segment, array $context): ?array
    {
        $lower = mb_strtolower($segment);

        if ($this->looksLikeTeacherCreation($segment)) {
            return [
                'intent' => 'create_teacher',
                'data' => $this->extractTeacherData($segment, $context),
            ];
        }

        if ($this->looksLikeStudentEnrollment($segment)) {
            return [
                'intent' => 'enroll_students',
                'data' => $this->extractStudentEnrollmentData($segment, $context),
            ];
        }

        if ($this->looksLikeStudentCreation($segment)) {
            return [
                'intent' => 'create_students_batch',
                'data' => $this->extractStudentCreationData($segment, $context),
            ];
        }

        return null;
    }

    private function looksLikeTeacherCreation(string $segment): bool
    {
        $lower = mb_strtolower($segment);

        return (bool) preg_match('/\b(?:crea(?:r|me|s|n)?|crees|cree|invita(?:r|s|n)?|invites?)\b/iu', $segment)
            && preg_match('/\b(?:profesor(?:a)?|docente|maestr[oa])\b/iu', $segment);
    }

    private function looksLikeStudentEnrollment(string $segment): bool
    {
        $lower = mb_strtolower($segment);

        $enrollmentVerbs = (bool) preg_match('/\b(?:agrega(?:r|le|lo|s|n)?|agregues?|inscribe(?:r|s|n)?|inscribes?|matricula(?:r|s|n)?|matricules?)\b/iu', $segment);
        $studentMention = (bool) preg_match('/\b(?:alumnos?|estudiantes?)\b/iu', $segment);
        $existingStudents = (bool) preg_match('/\b(?:a\s+su\s+materia|a\s+la\s+materia|a\s+la\s+clase|en\s+(?:el|la)\s+(?:materia|clase|asignatura))\b/iu', $segment);

        return $enrollmentVerbs && ($studentMention || $existingStudents);
    }

    private function looksLikeStudentCreation(string $segment): bool
    {
        $lower = mb_strtolower($segment);

        return (bool) preg_match('/\b(?:crea(?:r|me|s|n)?|crees|cree)\b/iu', $segment)
            && preg_match('/\b(?:alumnos?|estudiantes?)\b/iu', $segment);
    }

    /**
     * @param  array<string,mixed>  $context
     */
    private function extractTeacherData(string $segment, array $context): array
    {
        $data = [
            'teacher_name' => null,
            'subject_name' => null,
            'grades' => [],
        ];

        // Nombre: buscamos después de "profesor(a)" o "llamado(a)".
        $name = $this->extractTeacherName($segment);
        if ($name === null && isset($context['last_action']['data']['teacher_name'])) {
            $name = $context['last_action']['data']['teacher_name'];
        }
        if ($name !== null) {
            $data['teacher_name'] = $this->nameSanitizer->displayName($name);
        }

        $data['subject_name'] = $this->extractSubject($segment) ?? $context['subject_name'] ?? null;
        $data['grades'] = $this->extractGrades($segment) ?: (array) ($context['grades'] ?? []);

        return $data;
    }

    private function extractTeacherName(string $segment): ?string
    {
        // Patrones ordenados por especificidad.
        $patterns = [
            // "profesor de matemáticas llamado Vicente José"
            '/profesor(?:a)?\s+(?:de\s+(?:la\s+|el\s+)?)?(?:'.self::SUBJECT_PATTERN.')\s+(?:llamad[oa]\s+)?(.+?)(?:\s+(?:desde|de\s+\d|que\s+va|va\s+a|asigna|materia|grado|curso|\(|,\s*(?:desde|que|y\s+(?:es|va)))|$)/iu',
            // "profesor llamado Vicente José"
            '/profesor(?:a)?\s+(?:llamad[oa]\s+)?(.+?)(?:\s+(?:de\s+(?:la\s+|el\s+)?(?:'.self::SUBJECT_PATTERN.')|desde|que\s+va|va\s+a|asigna|materia|grado|curso|\(|,\s*(?:desde|que|y\s+(?:es|va)))|$)/iu',
            // "crea al profesor Vicente José"
            '/(?:crea(?:r|me|s)?|crees|cree|invita(?:r|s)?|invites?)\s+(?:a\s+)?(?:el\s+|la\s+)?profesor(?:a)?\s+(.+?)(?:\s+(?:de\s+(?:la\s+|el\s+)?(?:'.self::SUBJECT_PATTERN.')|desde|que\s+va|va\s+a|asigna|materia|grado|curso|\(|,\s*(?:desde|que|y\s+(?:es|va)))|$)/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $segment, $match)) {
                $candidate = $this->nameSanitizer->clean($match[1]);
                if ($candidate !== null) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string,mixed>  $context
     */
    private function extractStudentEnrollmentData(string $segment, array $context): array
    {
        $data = [
            'names' => [],
            'subject_name' => null,
            'grade' => null,
            'teacher_name' => null,
        ];

        $data['names'] = $this->extractStudentNames($segment);
        $data['subject_name'] = $this->extractSubject($segment) ?? $context['subject_name'] ?? null;
        $data['grade'] = $this->extractSingleGrade($segment) ?? $context['grade'] ?? null;
        $data['teacher_name'] = $context['teacher_name'] ?? null;

        return $data;
    }

    /**
     * @param  array<string,mixed>  $context
     */
    private function extractStudentCreationData(string $segment, array $context): array
    {
        $data = $this->extractStudentEnrollmentData($segment, $context);

        return [
            'names' => $data['names'],
            'grade' => $data['grade'],
            'subject_name' => $data['subject_name'],
            'teacher_name' => $data['teacher_name'],
        ];
    }

    /**
     * @return array<int,string>
     */
    private function extractStudentNames(string $segment): array
    {
        $endOfNames = '(?:a\s+(?:su|la|el)\s+(?:materia|clase|asignatura)|en\s+(?:el|la|los|las)\s+(?:materia|clase|asignatura|curso|grado)|en\s+(?:\d|primer|primero|segundo|tercer|tercero|cuarto|quinto|sexto)|que\s+(?:ambos|son|est[áa]n)|,\s*que|\(|$)';
        $patterns = [
            // "alumnos Carlos Gutiérrez y Salvador Pérez a su materia..."
            '/(?:alumnos?|estudiantes?)\s+(.+?)(?:\s+'.$endOfNames.')/iu',
            // "agrega a Carlos Gutiérrez y Salvador Pérez"
            '/(?:agrega(?:r|le|lo|s)?|agregues?|a[nñ]ade)\s+(?:a\s+)?(.+?)(?:\s+'.$endOfNames.')/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $segment, $match)) {
                $raw = $match[1];
                $names = $this->splitNameList($raw);
                if ($names !== []) {
                    return $names;
                }
            }
        }

        return [];
    }

    /**
     * @return array<int,string>
     */
    private function splitNameList(string $raw): array
    {
        // Separamos por " y " y comas, pero cuidando "Gutiérrez, Salvador" no se corte mal.
        // Primero normalizamos "X, Y y Z" → array.
        $parts = preg_split('/\s*y\s+/iu', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [$raw];

        $names = [];
        foreach ($parts as $part) {
            foreach (preg_split('/\s*,\s*/u', $part, -1, PREG_SPLIT_NO_EMPTY) ?: [$part] as $candidate) {
                $candidate = $this->nameSanitizer->clean($candidate);
                if ($candidate !== null) {
                    $names[] = $this->nameSanitizer->titleCase($candidate);
                }
            }
        }

        return array_values(array_unique(array_filter($names)));
    }

    private function extractSubject(string $segment): ?string
    {
        if (preg_match('/\b('.self::SUBJECT_PATTERN.')\b/iu', $segment, $match)) {
            $subject = mb_strtolower(trim($match[1]));
            $subject = strtr($subject, [
                'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
                'ã' => 'a', 'õ' => 'o', 'ñ' => 'n',
            ]);

            return match ($subject) {
                'matematica', 'matematicas' => 'Matemática',
                'ingles' => 'Inglés',
                'lengua', 'lenguaje' => 'Lenguaje',
                'computacion' => 'Computación',
                'robotica' => 'Robótica',
                'educacion fisica' => 'Educación Física',
                'religion' => 'Religión',
                'geografia' => 'Geografía',
                'historia' => 'Historia',
                'fisica' => 'Física',
                'quimica' => 'Química',
                'biologia' => 'Biología',
                'musica' => 'Música',
                'plastica' => 'Plástica',
                'arte' => 'Arte',
                'ciencias naturales', 'ciencias naturale' => 'Ciencias Naturales',
                'ciencias sociales', 'ciencias sociale' => 'Ciencias Sociales',
                default => $this->nameSanitizer->titleCase($subject),
            };
        }

        return null;
    }

    /**
     * @return array<int,string>
     */
    private function extractGrades(string $segment): array
    {
        // Rango: "desde 1ro hasta 6to", "de 1ro a 6to", "1ro a 6to"
        if (preg_match('/(?:desde|de)\s+('.self::GRADE_PATTERN.')\s+(?:hasta|a)\s+('.self::GRADE_PATTERN.')/iu', $segment, $match)) {
            return $this->expandGradeRange($match[1], $match[2]);
        }

        if (preg_match('/\b('.self::GRADE_PATTERN.')\s+(?:a|hasta)\s+('.self::GRADE_PATTERN.')\b/iu', $segment, $match)) {
            return $this->expandGradeRange($match[1], $match[2]);
        }

        // Grados sueltos.
        if (preg_match_all('/\b('.self::GRADE_PATTERN.')\b/iu', $segment, $matches)) {
            $grades = array_map(fn ($g) => $this->normalizeGrade($g), $matches[1]);

            return array_values(array_unique(array_filter($grades)));
        }

        return [];
    }

    private function extractSingleGrade(string $segment): ?string
    {
        $grades = $this->extractGrades($segment);

        return $grades[0] ?? null;
    }

    /**
     * @return array<int,string>
     */
    private function expandGradeRange(string $from, string $to): array
    {
        $fromNum = $this->gradeToNumber($from);
        $toNum = $this->gradeToNumber($to);
        if ($fromNum === null || $toNum === null) {
            return array_values(array_filter([$this->normalizeGrade($from), $this->normalizeGrade($to)]));
        }

        $start = min($fromNum, $toNum);
        $end = max($fromNum, $toNum);
        $grades = [];
        for ($i = $start; $i <= $end; $i++) {
            $grades[] = $this->numberToGrade($i);
        }

        return $grades;
    }

    private function gradeToNumber(string $grade): ?int
    {
        $value = mb_strtolower(trim($grade));
        $value = strtr($value, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u']);
        $aliases = [
            1 => ['primer', 'primero', '1', '1ro', '1ero', '1er', '1°', '1º'],
            2 => ['segundo', '2', '2do', '2°', '2º'],
            3 => ['tercer', 'tercero', '3', '3ro', '3ero', '3er', '3°', '3º'],
            4 => ['cuarto', '4', '4to', '4°', '4º'],
            5 => ['quinto', '5', '5to', '5°', '5º'],
            6 => ['sexto', '6', '6to', '6°', '6º'],
        ];
        foreach ($aliases as $number => $values) {
            if (in_array($value, $values, true)) {
                return $number;
            }
        }

        return null;
    }

    private function numberToGrade(int $number): string
    {
        return match ($number) {
            1 => '1ro', 2 => '2do', 3 => '3ro', 4 => '4to', 5 => '5to', 6 => '6to',
            default => (string) $number,
        };
    }

    private function normalizeGrade(string $grade): string
    {
        $number = $this->gradeToNumber($grade);

        return $number !== null ? $this->numberToGrade($number) : trim($grade);
    }

    /**
     * Actualiza el contexto compartido entre segmentos (materia, grados, profesor).
     *
     * @param  array<string,mixed>  $context
     */
    private function updateContext(string $segment, array &$context): void
    {
        $subject = $this->extractSubject($segment);
        if ($subject !== null) {
            $context['subject_name'] = $subject;
        }

        $grades = $this->extractGrades($segment);
        if ($grades !== []) {
            $context['grades'] = $grades;
        }

        $singleGrade = $this->extractSingleGrade($segment);
        if ($singleGrade !== null) {
            $context['grade'] = $singleGrade;
        }

        if (isset($context['last_action']['data']['teacher_name'])) {
            $context['teacher_name'] = $context['last_action']['data']['teacher_name'];
        }
    }

    /**
     * @param  array{intent:string,data:array<string,mixed>}  $action
     * @param  array<string,mixed>  $context
     * @return array{intent:string,data:array<string,mixed>}
     */
    private function mergeWithContext(array $action, array $context): array
    {
        if ($action['intent'] === 'create_teacher') {
            if (empty($action['data']['subject_name']) && ! empty($context['subject_name'])) {
                $action['data']['subject_name'] = $context['subject_name'];
            }
            if (empty($action['data']['grades']) && ! empty($context['grades'])) {
                $action['data']['grades'] = $context['grades'];
            }
        }

        if (in_array($action['intent'], ['enroll_students', 'create_students_batch'], true)) {
            if (empty($action['data']['subject_name']) && ! empty($context['subject_name'])) {
                $action['data']['subject_name'] = $context['subject_name'];
            }
            if (empty($action['data']['grade']) && ! empty($context['grade'])) {
                $action['data']['grade'] = $context['grade'];
            }
            if (empty($action['data']['teacher_name']) && ! empty($context['teacher_name'])) {
                $action['data']['teacher_name'] = $context['teacher_name'];
            }
        }

        return $action;
    }

    /**
     * Cuando un segmento no genera una acción pero aporta contexto, aplica ese
     * contexto a la última acción generada si aún le faltan esos campos.
     *
     * @param  array<int,array{intent:string,data:array<string,mixed>}>  $actions
     * @param  array<string,mixed>  $context
     */
    private function applyContextToLastAction(string $segment, array &$actions, array &$context): void
    {
        if ($actions === []) {
            return;
        }

        $lastIndex = array_key_last($actions);
        $action = &$actions[$lastIndex];

        $subject = $this->extractSubject($segment) ?? $context['subject_name'] ?? null;
        $grades = $this->extractGrades($segment);
        $singleGrade = $this->extractSingleGrade($segment);

        if ($action['intent'] === 'create_teacher') {
            if (empty($action['data']['subject_name']) && $subject !== null) {
                $action['data']['subject_name'] = $subject;
            }
            if (empty($action['data']['grades']) && $grades !== []) {
                $action['data']['grades'] = $grades;
            }
        }

        if (in_array($action['intent'], ['enroll_students', 'create_students_batch'], true)) {
            if (empty($action['data']['subject_name']) && $subject !== null) {
                $action['data']['subject_name'] = $subject;
            }
            if (empty($action['data']['grade']) && $singleGrade !== null) {
                $action['data']['grade'] = $singleGrade;
            }
        }
    }

    /**
     * Limpia y ordena las acciones finales.
     *
     * @param  array<int,array{intent:string,data:array<string,mixed>}>  $actions
     * @return array<int,array{intent:string,data:array<string,mixed>}>
     */
    private function finalize(array $actions): array
    {
        $result = [];
        foreach ($actions as $action) {
            $data = $this->cleanData($action['data']);
            if ($this->isActionValid($action['intent'], $data)) {
                $result[] = [
                    'intent' => $action['intent'],
                    'data' => $data,
                ];
            }
        }

        return $result;
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    private function cleanData(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = trim($value);
            }
            if (is_array($value)) {
                $data[$key] = array_values(array_filter($value, fn ($v) => $v !== null && $v !== ''));
            }
        }

        return $data;
    }

    /**
     * @param  array<string,mixed>  $data
     */
    private function isActionValid(string $intent, array $data): bool
    {
        return match ($intent) {
            'create_teacher' => ! empty($data['teacher_name']),
            'enroll_students', 'create_students_batch' => ! empty($data['names']) && ! empty($data['grade']),
            default => false,
        };
    }
}

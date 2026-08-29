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
    private const GRADE_WORD_PATTERN_EXTENDED = '(?:primer|primero|segundo|tercer|tercero|cuarto|quinto|sexto|septim[oa]|octav[oa]|noven[oa]|[1-9](?:ro|ero|er|do|to|mo|vo|no|°|º)?)';

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

            // Segmento de matrícula sin nombres propios ("y los agregas a su curso
            // de biología"): no es una acción independiente, es la matrícula de los
            // alumnos que acabamos de crear. Antes se registraba como acción vacía,
            // se descartaba en finalize() y además robaba el contexto de grado a la
            // creación de alumnos: el plan de 3 acciones colapsaba a 1.
            if ($action['intent'] === 'enroll_students' && empty($action['data']['names'])) {
                $lastIndex = array_key_last($actions);
                if ($lastIndex !== null && $actions[$lastIndex]['intent'] === 'create_students_batch') {
                    $actions[$lastIndex]['data']['_needs_enrollment'] = true;
                    if (empty($actions[$lastIndex]['data']['subject_name']) && ! empty($action['data']['subject_name'])) {
                        $actions[$lastIndex]['data']['subject_name'] = $action['data']['subject_name'];
                    }
                    $this->updateContext($segment, $context);

                    continue;
                }
            }

            // Si el segmento no trae materia/grados pero hay contexto previo, herédalo.
            $action = $this->mergeWithContext($action, $context);
            if ($action['intent'] === 'create_students_batch' && $this->looksLikeStudentEnrollment($segment)) {
                $action['data']['_needs_enrollment'] = true;
            }
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
            // PREG_OFFSET_CAPTURE devuelve offsets en BYTES: hay que cortar con
            // substr, no con mb_substr. Mezclarlos partía palabras a la mitad en
            // frases con acentos ("...y los a" / "gregas a su curso de biología")
            // y con ello se perdían las acciones de alumnos.
            $start = (int) $verbs[$i][1];
            $end = isset($verbs[$i + 1]) ? (int) $verbs[$i + 1][1] : strlen($sentence);
            $part = trim(substr($sentence, $start, $end - $start));
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

        if ($this->looksLikeStudentCreation($segment)) {
            return [
                'intent' => 'create_students_batch',
                'data' => $this->extractStudentCreationData($segment, $context),
            ];
        }

        if ($this->looksLikeStudentEnrollment($segment)) {
            return [
                'intent' => 'enroll_students',
                'data' => $this->extractStudentEnrollmentData($segment, $context),
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
        $existingStudents = (bool) preg_match('/\b(?:a\s+su\s+(?:materia|curso)|a\s+la\s+(?:materia|clase)|en\s+(?:el|la)\s+(?:materia|clase|asignatura|curso))\b/iu', $segment);

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
        $gradeStop = '(?:de|del|para|en)\s+(?:el\s+|la\s+)?(?:'.self::GRADE_WORD_PATTERN_EXTENDED.')(?:\s+(?:grado|curso|secci[oó]n))?';
        $tailStop = '(?:como\s+profesor|desde|'.$gradeStop.'|de\s+(?:la\s+|el\s+)?(?:'.self::SUBJECT_PATTERN.')|que\s+va|va\s+a|asigna|materia|grado|curso|\(|,\s*(?:desde|que|y\s+(?:es|va)))';

        // Patrones ordenados por especificidad.
        $patterns = [
            // "profesor de matemáticas llamado Vicente José"
            '/profesor(?:a)?\s+(?:de\s+(?:la\s+|el\s+)?)?(?:'.self::SUBJECT_PATTERN.')\s+(?:llamad[oa]\s+)?([^.!?¡¿]+?)(?:\s+'.$tailStop.'|[.!?¡¿]|$)/iu',
            // "profesor llamado Vicente José"
            '/profesor(?:a)?\s+(?:llamad[oa]\s+)?([^.!?¡¿]+?)(?:\s+'.$tailStop.'|[.!?¡¿]|$)/iu',
            // "crea al profesor Vicente José"
            '/(?:crea(?:r|me|s)?|crees|cree|invita(?:r|s)?|invites?)\s+(?:a\s+)?(?:el\s+|la\s+)?profesor(?:a)?\s+([^.!?¡¿]+?)(?:\s+'.$tailStop.'|[.!?¡¿]|$)/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $segment, $match)) {
                $candidate = $this->nameSanitizer->clean($this->stripStopWords($match[1]));
                if ($candidate !== null) {
                    $candidate = $this->stripStopWords($candidate);
                }
                if ($candidate !== null && $candidate !== '') {
                    return $candidate;
                }
            }
        }

        return null;
    }

    /**
     * Palabras que nunca forman parte de un nombre propio: pronombres y
     * conectores que el regex arrastra al capturar ".+?" ("Vicente José él
     * dará Biología" → "Vicente José").
     *
     * Ojo: no se incluyen "de/la/los/del" porque sí aparecen dentro de
     * apellidos reales ("María de la Cruz").
     */
    private const NAME_STOP_WORDS = [
        'el', 'ella', 'ellos', 'ellas', 'ello',
        'que', 'quien', 'quienes', 'cual', 'cuales',
        'ademas', 'tambien', 'asimismo', 'luego', 'ahora', 'entonces',
        'este', 'esta', 'esto', 'estos', 'estas',
        'ese', 'esa', 'eso', 'esos', 'esas',
        'ambos', 'ambas', 'mismo', 'misma', 'dicho', 'dicha',
        'y', 'o', 'pero', 'para', 'con', 'su', 'sus', 'al', 'a', 'en', 'como',
        // Verbos imperativos que el director usa para arrancar la siguiente
        // instrucción y que el regex ".+?" arrastra al nombre si queda
        // pegado ("Jorge Luis Agrega a Vicente..." -> "Jorge Luis").
        'crea', 'crear', 'creame', 'creale', 'creales',
        'agrega', 'agregale', 'agregales', 'agreguen',
        'asigna', 'asignale', 'asignales', 'asignele', 'asignenle',
        'invita', 'invitale',
        'matricula', 'matriculale', 'matriculales',
        'inscribe', 'inscribele', 'inscribeles',
        'anade', 'anadele', 'anadeles',
    ];

    /**
     * Artículos y pronombres que pueden aparecer DENTRO de un apellido real
     * ("María de la Cruz"), así que solo se descartan cuando son el candidato
     * completo ("y los" → "los").
     */
    private const NAME_ONLY_ARTICLES = [
        'el', 'la', 'los', 'las', 'lo', 'le', 'les', 'de', 'del',
        'su', 'sus', 'un', 'una', 'unos', 'unas',
    ];

    /**
     * Elimina pronombres y conectores del inicio del candidato y corta el
     * nombre en el primer stop word que aparece después de un token válido.
     * Insensible a mayúsculas y acentos ("Él", "el", "ÉL" → stop word).
     */
    private function stripStopWords(string $candidate): string
    {
        $tokens = preg_split('/\s+/u', trim($candidate), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $kept = [];

        foreach ($tokens as $token) {
            $isStop = in_array($this->foldToken((string) $token), self::NAME_STOP_WORDS, true);

            if ($isStop) {
                if ($kept === []) {
                    continue; // stop word inicial: se descarta.
                }

                break; // stop word tras el nombre: aquí termina el nombre.
            }

            $kept[] = trim((string) $token, " \t\n\r\0\x0B,;:");
        }

        $kept = array_values(array_filter($kept));

        // Candidato compuesto solo por artículos/pronombres: no es un nombre.
        $allArticles = $kept !== [] && array_reduce(
            $kept,
            fn ($carry, $token) => $carry && in_array($this->foldToken((string) $token), self::NAME_ONLY_ARTICLES, true),
            true
        );

        return $allArticles ? '' : trim(implode(' ', $kept));
    }

    private function foldToken(string $token): string
    {
        $value = mb_strtolower(trim($token, " \t\n\r\0\x0B.,;:()¿?¡!\"'"));

        return strtr($value, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
            'â' => 'a', 'ê' => 'e', 'î' => 'i', 'ô' => 'o', 'û' => 'u',
        ]);
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
        $endOfNames = '(?:'
            .'a\s+(?:su|la|el)\s+(?:materia|clase|asignatura|curso)'
            .'|en\s+(?:el|la|los|las)\s+(?:materia|clase|asignatura|curso|grado)'
            .'|(?:en|de|del|para)\s+(?:el\s+|la\s+)?(?:'.self::GRADE_WORD_PATTERN_EXTENDED.')(?:\s+grado)?'
            .'|que\s+(?:ambos|son|est[áa]n)'
            .'|y\s+los\s+(?:agrega(?:r|s)?|agregues?|inscribe(?:r|s)?|matricula(?:r|s)?)'
            .'|,\s*que|\(|[.!?¡¿]|$'
            .')';
        $patterns = [
            // "alumnos Carlos Gutiérrez y Salvador Pérez a su materia..."
            '/(?:alumnos?|estudiantes?)\s+([^.!?¡¿]+?)(?:\s+'.$endOfNames.'|\s*$)/iu',
            // "agrega a Carlos Gutiérrez y Salvador Pérez"
            '/(?:agrega(?:r|le|lo|s)?|agregues?|a[nñ]ade)\s+(?:a\s+)?([^.!?¡¿]+?)(?:\s+'.$endOfNames.'|\s*$)/iu',
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
                $candidate = $this->nameSanitizer->clean($this->stripStopWords($candidate));
                if ($candidate !== null) {
                    $candidate = $this->stripStopWords($candidate);
                }
                if ($candidate !== null && $candidate !== '') {
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
            $needsEnrollment = (bool) ($data['_needs_enrollment'] ?? false);
            unset($data['_needs_enrollment']);
            if ($this->isActionValid($action['intent'], $data)) {
                $result[] = [
                    'intent' => $action['intent'],
                    'data' => $data,
                ];
                if (
                    $needsEnrollment
                    && $action['intent'] === 'create_students_batch'
                    && ! empty($data['names'])
                    && ! empty($data['subject_name'])
                    && ! empty($data['grade'])
                ) {
                    $result[] = [
                        'intent' => 'enroll_students',
                        'data' => [
                            'names' => (array) ($data['names'] ?? []),
                            'subject_name' => $data['subject_name'] ?? null,
                            'grade' => $data['grade'] ?? null,
                            'teacher_name' => $data['teacher_name'] ?? null,
                        ],
                    ];
                }
            }
        }

        return $this->deduplicateActions($result);
    }

    /**
     * Deduplica entidades repetidas dentro del mismo plan (correcciones a media frase).
     * Ej: "a Vicente José, al alumno Vicente José y a la alumna Gabriela Pernal" → 2 alumnos únicos.
     *
     * @param  array<int,array{intent:string,data:array<string,mixed>}>  $actions
     * @return array<int,array{intent:string,data:array<string,mixed>}>
     */
    private function deduplicateActions(array $actions): array
    {
        $globalNames = [];
        $filtered = [];
        foreach ($actions as $action) {
            if (in_array($action['intent'], ['create_students_batch', 'enroll_students'], true)) {
                // La deduplicación es POR INTENCIÓN, no global: crear a un alumno y
                // matricularlo son dos acciones legítimas con el mismo nombre. Antes,
                // el registro global borraba la matrícula y colapsaba el plan
                // multi-acción (3 acciones → 1).
                $scope = $action['intent'].':';
                $uniqueInAction = [];
                foreach ((array) ($action['data']['names'] ?? []) as $orig) {
                    $key = $scope.mb_strtolower(trim((string) $orig));
                    if (trim((string) $orig) === '' || isset($globalNames[$key])) {
                        continue;
                    }
                    $globalNames[$key] = true;
                    $uniqueInAction[] = $orig;
                }
                if ($uniqueInAction === [] && ($action['data']['names'] ?? []) !== []) {
                    // Todo era duplicado, omitir esta acción redundante.
                    continue;
                }
                if ($uniqueInAction !== ($action['data']['names'] ?? [])) {
                    $action['data']['names'] = array_values($uniqueInAction);
                }
            }
            if ($action['intent'] === 'create_teacher') {
                $key = mb_strtolower(trim((string) ($action['data']['teacher_name'] ?? '')));
                if ($key !== '' && isset($globalNames['teacher:'.$key])) {
                    continue;
                }
                if ($key !== '') {
                    $globalNames['teacher:'.$key] = true;
                }
            }
            $filtered[] = $action;
        }

        return $filtered;
    }

    /**
     * Detecta si el texto es complejo y la extracción probablemente está incompleta.
     * Si true, el caller debe pedir aclaración en vez de presentar plan adivinado.
     */
    public function isUncertainExtraction(string $text, array $actions): bool
    {
        $lower = mb_strtolower($text);
        $hasComplexConnector = (bool) preg_match('/\b(?:adicional|adema?s|tambien|también)\b/u', $lower);
        $verbCount = preg_match_all('/\b(?:crea(?:r|me|s|n)?|agrega(?:r|le|lo|s)?|inscribe|matricula|asigna|invita|modifica|elimina|mueve|cambia)\b/iu', $text);
        $segmentCount = count($this->segment($text));
        $entityMentions = preg_match_all('/\b(?:profesor(?:a)?|alumn[oa]|estudiante)s?\b/iu', $text);

        if ($hasComplexConnector && $verbCount >= 2 && count($actions) < $verbCount) {
            return true;
        }
        if ($entityMentions >= 3 && $verbCount >= 2 && count($actions) <= 1) {
            return true;
        }
        // Si se detectaron múltiples segmentos pero solo 1 acción válida, probablemente se perdió algo.
        if ($segmentCount >= 3 && count($actions) <= 1 && $verbCount >= 2) {
            return true;
        }

        return false;
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

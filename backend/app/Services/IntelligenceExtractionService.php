<?php

namespace App\Services;

use App\Models\Course;
use App\Models\IntelligenceDocument;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Motor de extracción de "Inteligencia AulaSync": envía el contenido del
 * documento al modelo con visión y devuelve datos estructurados con niveles
 * de confianza. Regla absoluta: la IA solo extrae lo visible, nunca completa
 * ni interpreta; lo dudoso se marca como incierto.
 */
class IntelligenceExtractionService
{
    private const KINDS = [
        IntelligenceDocument::KIND_PLANIFICACION,
        IntelligenceDocument::KIND_LISTA_ALUMNOS,
        IntelligenceDocument::KIND_NOTAS,
        IntelligenceDocument::KIND_ASISTENCIA,
        IntelligenceDocument::KIND_EVALUACION,
        IntelligenceDocument::KIND_INFORME,
        IntelligenceDocument::KIND_OTRO,
    ];

    public function __construct(
        private DocumentTextExtractor $extractor,
        private IntelligenceEntityMatcher $matcher,
    ) {}

    /**
     * Analiza el documento, guarda la extracción normalizada y la revisión
     * con entidades relacionadas. Devuelve el documento actualizado.
     */
    public function extract(IntelligenceDocument $document, User $teacher): IntelligenceDocument
    {
        $document->status = IntelligenceDocument::STATUS_PROCESSING;
        $document->save();

        try {
            $representation = $this->extractor->extract(
                $document->disk_path,
                (string) $document->mime_type,
                $document->original_name
            );

            $raw = $this->callModel($representation, $teacher);
            $extraction = $this->normalize($raw, $representation['notes'] ?? []);
            $review = $this->matcher->buildReview($extraction, $teacher);

            $document->extraction = $extraction;
            $document->review = $review;
            $document->kind = $extraction['document_type'];
            $document->confidence = $extraction['confidence'];
            $document->status = IntelligenceDocument::STATUS_EXTRACTED;
            $document->error = null;
            $document->save();

            return $document;
        } catch (\Throwable $e) {
            Log::error('Intelligence extraction failed', [
                'document_id' => $document->id,
                'teacher_id' => $teacher->id,
                'error' => $e->getMessage(),
            ]);

            $document->status = IntelligenceDocument::STATUS_FAILED;
            $document->error = 'No pude analizar este archivo automáticamente. Verifica que sea un documento legible (PDF, DOCX, XLSX, CSV o imagen) e inténtalo de nuevo.';
            $document->save();

            return $document;
        }
    }

    public function enabled(): bool
    {
        if (! config('services.openai.intelligence_enabled', true)) {
            return false;
        }

        if (app()->environment('testing') && ! config('services.openai.intelligence_test_enabled', false)) {
            return false;
        }

        $key = trim((string) config('services.openai.key'));

        return $key !== '' && ! str_contains($key, 'your_openai');
    }

    /**
     * @param  array{text: string, mode: string, data_uri: ?string, notes: array}  $representation
     * @return array<string, mixed>
     */
    private function callModel(array $representation, User $teacher): array
    {
        if (! $this->enabled()) {
            throw new RuntimeException('La extracción inteligente no está disponible en este momento.');
        }

        $userContent = $this->userPrompt($representation, $teacher);
        $model = (string) config('services.openai.intelligence_model', 'gpt-4o-mini');

        $message = ['role' => 'user', 'content' => $userContent];

        if ($representation['mode'] === 'vision' && $representation['data_uri']) {
            $message['content'] = [
                ['type' => 'text', 'text' => $userContent],
                ['type' => 'image_url', 'image_url' => ['url' => $representation['data_uri']]],
            ];
        } elseif ($representation['mode'] === 'pdf' && $representation['data_uri']) {
            $message['content'] = [
                ['type' => 'text', 'text' => $userContent],
                [
                    'type' => 'file',
                    'file' => [
                        'filename' => 'documento.pdf',
                        'file_data' => $representation['data_uri'],
                    ],
                ],
            ];
        }

        $response = Http::timeout(90)
            ->withToken((string) config('services.openai.key'))
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'temperature' => 0,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => $this->systemPrompt()],
                    $message,
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('El modelo rechazó el documento.');
        }

        $content = (string) $response->json('choices.0.message.content', '');
        $data = json_decode($content, true);

        if (! is_array($data)) {
            throw new RuntimeException('El modelo no devolvió un formato válido.');
        }

        return $data;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
Eres el motor de extracción de "Inteligencia AulaSync", una herramienta para profesores.
Recibes el contenido de un documento escolar (planificación, lista de alumnos, notas,
asistencia, evaluación, informe u otro) y debes extraer SOLO la información que contiene.

REGLAS ABSOLUTAS (anti-invención):
1. Solo incluye datos visibles o deducibles con certeza del documento. NUNCA inventes nombres, fechas ni notas.
2. Cada dato lleva "confidence" entre 0 y 1. Usa 0.5 o menos cuando haya ambigüedad.
3. Si un valor no es legible o es ambiguo, omítelo y explica la duda en "uncertain".
4. Las fechas SIEMPRE en formato YYYY-MM-DD. Si el documento no indica el año, usa null y explícalo en "uncertain".
5. Si el documento no contiene algún tipo de dato, devuelve lista vacía. No fuerces contenido.
6. No interpretes, no recomiendes, no resumas: solo extrae.
7. En "observations" incluye frases textuales relevantes del documento (avisos, notas del profesor, acuerdos).

Responde EXCLUSIVAMENTE con un objeto JSON con esta estructura exacta:
{
  "document_type": "planificacion|lista_alumnos|notas|asistencia|evaluacion|informe|otro",
  "confidence": 0.9,
  "context": {"subject": null, "grade": null, "section": null, "period": null},
  "students": [{"name": "Nombre Apellido", "grade": null, "section": null, "confidence": 0.9}],
  "activities": [{"title": "Título o tema", "date": null, "type": "clase|actividad|tarea", "description": null, "max_score": null, "confidence": 0.8}],
  "grades": [{"student": "Nombre", "activity_title": "Título de la actividad o evaluación", "score": 18.5, "max_score": 20, "confidence": 0.9}],
  "attendance": [{"student": "Nombre", "date": null, "status": "present|absent|tardy", "confidence": 0.8}],
  "observations": ["..."],
  "uncertain": ["..."]
}
PROMPT;
    }

    /**
     * @param  array{text: string, mode: string, notes: array}  $representation
     */
    private function userPrompt(array $representation, User $teacher): string
    {
        $courseLines = Course::where('teacher_id', $teacher->id)
            ->orderBy('subject_name')
            ->get(['subject_name', 'grade', 'section'])
            ->map(fn ($course) => '- '.trim($course->subject_name.' · '.$course->grade.($course->section ? ' / '.$course->section : '')))
            ->implode("\n");

        $parts = [
            'Analiza el siguiente documento y extrae su información según las reglas.',
            '',
            'Cursos del profesor (SOLO para interpretar columnas; no los uses para inventar datos):',
            $courseLines !== '' ? $courseLines : '- (sin cursos)',
        ];

        if ($representation['mode'] === 'text') {
            $parts[] = '';
            $parts[] = 'Contenido del documento (columnas separadas por tabulador):';
            $parts[] = $representation['text'];
        } else {
            $parts[] = '';
            $parts[] = 'El documento se adjunta como archivo. Extrae su contenido de la imagen/archivo adjunto.';
        }

        if (! empty($representation['notes'])) {
            $parts[] = '';
            $parts[] = 'Notas del sistema de lectura:';
            foreach ($representation['notes'] as $key => $note) {
                $parts[] = '- ['.$key.'] '.$note;
            }
        }

        return implode("\n", $parts);
    }

    /**
     * Normaliza y valida la salida del modelo: límites estrictos, tipos
     * correctos y descarte de entradas claramente inválidas.
     *
     * @param  array<string, mixed>  $raw
     * @param  array<int, string>  $notes
     * @return array<string, mixed>
     */
    private function normalize(array $raw, array $notes): array
    {
        $uncertain = array_values(array_filter(array_map(
            fn ($item) => is_string($item) ? mb_substr(trim($item), 0, 500) : null,
            (array) ($raw['uncertain'] ?? [])
        )));

        $kind = (string) ($raw['document_type'] ?? 'otro');
        if (! in_array($kind, self::KINDS, true)) {
            $kind = IntelligenceDocument::KIND_OTRO;
            $uncertain[] = 'No pude clasificar el tipo de documento con certeza.';
        }

        $students = [];
        $seen = [];
        foreach (array_slice((array) ($raw['students'] ?? []), 0, 300) as $student) {
            $name = $this->cleanText((string) ($student['name'] ?? ''), 120);
            if ($name === null || mb_strlen($name) < 2 || isset($seen[$this->key($name)])) {
                continue;
            }
            $seen[$this->key($name)] = true;
            $students[] = [
                'name' => $name,
                'grade' => $this->cleanText((string) ($student['grade'] ?? ''), 20),
                'section' => $this->cleanText((string) ($student['section'] ?? ''), 10),
                'confidence' => $this->confidence($student['confidence'] ?? null),
            ];
        }

        $activities = [];
        $seenActivities = [];
        foreach (array_slice((array) ($raw['activities'] ?? []), 0, 200) as $activity) {
            $title = $this->cleanText((string) ($activity['title'] ?? ''), 180);
            if ($title === null) {
                continue;
            }
            $date = $this->normalizeDate($activity['date'] ?? null);
            $type = in_array($activity['type'] ?? '', ['clase', 'actividad', 'tarea'], true)
                ? $activity['type']
                : ($kind === IntelligenceDocument::KIND_PLANIFICACION ? 'clase' : 'actividad');
            $key = $this->key($title).'|'.(string) $date;
            if (isset($seenActivities[$key])) {
                continue;
            }
            $seenActivities[$key] = true;

            $activities[] = [
                'title' => $title,
                'date' => $date,
                'type' => $type,
                'description' => $this->cleanText((string) ($activity['description'] ?? ''), 2000),
                'max_score' => $this->normalizeScore($activity['max_score'] ?? null),
                'confidence' => $this->confidence($activity['confidence'] ?? null),
            ];
        }

        $grades = [];
        foreach (array_slice((array) ($raw['grades'] ?? []), 0, 800) as $grade) {
            $student = $this->cleanText((string) ($grade['student'] ?? ''), 120);
            $activityTitle = $this->cleanText((string) ($grade['activity_title'] ?? ''), 180);
            if ($student === null || $activityTitle === null || ! is_numeric($grade['score'] ?? null)) {
                continue;
            }

            $grades[] = [
                'student' => $student,
                'activity_title' => $activityTitle,
                'score' => (float) $grade['score'],
                'max_score' => $this->normalizeScore($grade['max_score'] ?? null),
                'confidence' => $this->confidence($grade['confidence'] ?? null),
            ];
        }

        $attendance = [];
        foreach (array_slice((array) ($raw['attendance'] ?? []), 0, 800) as $row) {
            $student = $this->cleanText((string) ($row['student'] ?? ''), 120);
            $date = $this->normalizeDate($row['date'] ?? null);
            $status = in_array($row['status'] ?? '', ['present', 'absent', 'tardy'], true) ? $row['status'] : null;
            if ($student === null || $date === null || $status === null) {
                if ($student !== null) {
                    $uncertain[] = "Registro de asistencia incompleto para {$student}: falta fecha o estado válido.";
                }
                continue;
            }

            $attendance[] = [
                'student' => $student,
                'date' => $date,
                'status' => $status,
                'confidence' => $this->confidence($row['confidence'] ?? null),
            ];
        }

        if ($students === [] && $activities === [] && $grades === [] && $attendance === []) {
            $uncertain[] = 'No detecté información estructurable en este documento.';
        }

        return [
            'document_type' => $kind,
            'confidence' => $this->confidence($raw['confidence'] ?? null) ?? 0.5,
            'context' => [
                'subject' => $this->cleanText((string) (data_get($raw, 'context.subject') ?? ''), 80),
                'grade' => $this->cleanText((string) (data_get($raw, 'context.grade') ?? ''), 20),
                'section' => $this->cleanText((string) (data_get($raw, 'context.section') ?? ''), 10),
                'period' => $this->cleanText((string) (data_get($raw, 'context.period') ?? ''), 60),
            ],
            'students' => $students,
            'activities' => $activities,
            'grades' => $grades,
            'attendance' => $attendance,
            'observations' => array_slice(array_values(array_filter(array_map(
                fn ($item) => $this->cleanText((string) $item, 500),
                (array) ($raw['observations'] ?? [])
            ))), 0, 20),
            'uncertain' => array_slice($uncertain, 0, 25),
            'reader_notes' => array_slice($notes, 0, 25),
        ];
    }

    private function cleanText(string $value, int $max): ?string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);

        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $max);
    }

    private function confidence(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        return round(max(0, min(1, (float) $value)), 2);
    }

    private function normalizeScore(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        $score = (float) $value;

        return ($score > 0 && $score <= 100) ? $score : null;
    }

    private function normalizeDate(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd/m/y', 'Y/m/d', 'd.m.Y'] as $format) {
            $date = \DateTime::createFromFormat('!'.$format, $value);
            if ($date !== false && $date->format($format) === $value) {
                return $date->format('Y-m-d');
            }
        }

        $months = 'enero|febrero|marzo|abril|mayo|junio|julio|agosto|septiembre|setiembre|octubre|noviembre|diciembre';
        if (preg_match('/(\d{1,2})\s+de\s+('.$months.')\s+de\s+(\d{4})/iu', $value, $matches)) {
            $map = ['enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4, 'mayo' => 5, 'junio' => 6,
                'julio' => 7, 'agosto' => 8, 'septiembre' => 9, 'setiembre' => 9, 'octubre' => 10, 'noviembre' => 11, 'diciembre' => 12];
            $month = $map[mb_strtolower($matches[2])] ?? null;
            if ($month !== null) {
                $padded = sprintf('%04d-%02d-%02d', (int) $matches[3], $month, (int) $matches[1]);

                return checkdate($month, (int) $matches[1], (int) $matches[3]) ? $padded : null;
            }
        }

        return null;
    }

    private function key(string $value): string
    {
        return strtr(mb_strtolower(trim($value)), [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);
    }
}

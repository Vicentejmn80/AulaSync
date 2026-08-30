<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Planificador de acciones multi-intención usando OpenAI Structured Outputs.
 *
 * Recibe una orden en lenguaje natural y devuelve un ActionPlan con todas las
 * acciones identificadas, sus parámetros, slots pendientes y dependencias.
 * Si OpenAI no está disponible, cae al extractor determinista existente.
 */
class DirectorActionPlannerService
{
    private const GENERIC_SUBJECT_PATTERN = '/^(?:curso|cursos|clase|clases|materia|materias|asignatura|asignaturas)$/u';

    public function __construct(
        private SchoolRosterContextService $rosterContext,
        private DirectorIntentExtractorService $intentExtractor,
    ) {}

    /**
     * Planifica las acciones a partir del texto del director.
     *
     * @param  array<string,mixed>  $context
     * @return array<string,mixed> ActionPlan array
     */
    public function plan(User $director, string $text, array $context = []): array
    {
        if (! $this->enabled()) {
            return $this->fromFallback($text, 'fallback_disabled');
        }

        try {
            $director->loadMissing('colegio');

            $messages = [
                ['role' => 'system', 'content' => $this->systemPrompt($director, $context)],
                ['role' => 'user', 'content' => $text],
            ];

            $response = $this->sendWithRetry($director, $messages);

            if ($response === null || $response->failed()) {
                Log::warning('Director action planner unavailable after retries', [
                    'director_id' => $director->id,
                    'status' => $response?->status(),
                ]);

                return $this->fromFallback($text, 'fallback_http_failed');
            }

            $raw = (string) $response->json('choices.0.message.content', '');
            $plan = json_decode($raw, true);

            if (! is_array($plan) || ! isset($plan['actions'])) {
                // Self-repair: una segunda llamada pidiendo explícitamente corregir
                // el plan inválido antes de degradar al extractor legado.
                $plan = $this->selfRepair($director, $text, $raw, $context);
                if ($plan === null) {
                    return $this->fromFallback($text, 'fallback_invalid_json');
                }
                $plan['planner_source'] = 'llm_structured_repaired';
            }

            $normalized = $this->normalizePlan($plan, $text);
            $normalized['planner_source'] = $plan['planner_source'] ?? 'llm_structured';

            Log::info('director.ai.planner_source', [
                'director_id' => $director->id,
                'planner_source' => $normalized['planner_source'],
                'actions_count' => count($normalized['actions'] ?? []),
                'prompt_preview' => mb_substr($text, 0, 120),
            ]);

            return $normalized;
        } catch (\Throwable $e) {
            Log::warning('Director action planner exception fallback', [
                'director_id' => $director->id,
                'error' => $e->getMessage(),
            ]);

            return $this->fromFallback($text, 'fallback_exception');
        }
    }

    /**
     * Envía la petición al planificador con reintentos y backoff exponencial
     * ante errores transitorios (timeouts, rate limits, SSL, 5xx).
     *
     * @param  array<int,array<string,string>>  $messages
     */
    private function sendWithRetry(User $director, array $messages, int $maxAttempts = 3): ?\Illuminate\Http\Client\Response
    {
        $attempt = 0;
        $delayMs = 500;
        $lastException = null;

        while ($attempt < $maxAttempts) {
            $attempt++;
            try {
                $response = Http::timeout(45)
                    ->withToken((string) config('services.openai.key'))
                    ->post('https://api.openai.com/v1/chat/completions', [
                        'model' => (string) config('services.openai.director_model', 'gpt-4o-mini'),
                        'temperature' => 0.2,
                        'top_p' => 0.9,
                        'response_format' => [
                            'type' => 'json_schema',
                            'json_schema' => [
                                'name' => 'action_plan',
                                'strict' => true,
                                'schema' => $this->actionPlanSchema(),
                            ],
                        ],
                        'messages' => $messages,
                    ]);

                if ($response->successful()) {
                    return $response;
                }

                // 4xx (excepto 429) son errores definitivos: no reintentar.
                if (! in_array($response->status(), [429, 500, 502, 503, 504], true)) {
                    return $response;
                }

                Log::warning('Director action planner transient HTTP error; retrying', [
                    'director_id' => $director->id,
                    'status' => $response->status(),
                    'attempt' => $attempt,
                ]);
            } catch (\Throwable $e) {
                // ConnectionException cubre timeouts y errores SSL transitorios.
                $lastException = $e;
                Log::warning('Director action planner connection issue; retrying', [
                    'director_id' => $director->id,
                    'attempt' => $attempt,
                    'message' => $e->getMessage(),
                ]);
            }

            if ($attempt < $maxAttempts) {
                usleep($delayMs * 1000);
                $delayMs *= 2;
            }
        }

        // Un fallo de transporte (ConnectionException, timeout, SSL) no es lo mismo
        // que una respuesta HTTP de error: al tragarlo y devolver null se reportaba
        // 'fallback_http_failed' y se perdía la causa real. Se propaga para que
        // plan() lo registre como 'fallback_exception'.
        if ($lastException !== null) {
            throw $lastException;
        }

        return null;
    }

    /**
     * Segunda llamada al LLM pidiendo corregir un plan inválido.
     *
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>|null
     */
    private function selfRepair(User $director, string $text, string $invalidRaw, array $context): ?array
    {
        $reason = json_decode($invalidRaw, true) === null
            ? 'no es JSON válido'
            : 'le falta la clave requerida "actions"';

        Log::warning('Director action planner invalid plan; attempting self-repair', [
            'director_id' => $director->id,
            'reason' => $reason,
        ]);

        $messages = [
            ['role' => 'system', 'content' => $this->systemPrompt($director, $context)],
            ['role' => 'user', 'content' => $text],
            ['role' => 'assistant', 'content' => mb_substr($invalidRaw, 0, 4000)],
            [
                'role' => 'user',
                'content' => "Tu respuesta anterior no fue un ActionPlan válido ({$reason}). "
                    .'Corrígela y devuelve SOLO el JSON válido que cumpla el schema action_plan. '
                    .'No agregues explicaciones ni texto fuera del JSON.',
            ],
        ];

        $response = $this->sendWithRetry($director, $messages, 2);

        if ($response === null || $response->failed()) {
            return null;
        }

        $plan = json_decode((string) $response->json('choices.0.message.content', ''), true);

        return is_array($plan) && isset($plan['actions']) ? $plan : null;
    }

    public function enabled(): bool
    {
        if (! config('services.openai.director_enabled', true)) {
            return false;
        }
        if (app()->environment('testing') && ! config('services.openai.director_test_enabled', false)) {
            return false;
        }

        $key = trim((string) config('services.openai.key'));

        return $key !== '' && ! str_contains($key, 'your_openai');
    }

    /**
     * Devuelve el JSON Schema estricto para el ActionPlan.
     *
     * @return array<string,mixed>
     */
    private function actionPlanSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'status' => [
                    'type' => 'string',
                    'enum' => ['pending', 'needs_info', 'confirmed', 'executed', 'failed', 'cancelled'],
                ],
                'actions' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'string'],
                            'type' => ['type' => 'string'],
                            'entity' => ['type' => 'string'],
                            'params' => [
                                'type' => 'object',
                                'properties' => [
                                    'teacher_name' => ['type' => ['string', 'null']],
                                    'student_name' => ['type' => ['string', 'null']],
                                    'names' => [
                                        'type' => ['array', 'null'],
                                        'items' => ['type' => 'string'],
                                    ],
                                    'grade' => ['type' => ['string', 'null']],
                                    'grades' => [
                                        'type' => ['array', 'null'],
                                        'items' => ['type' => 'string'],
                                    ],
                                    'courses_data' => [
                                        'type' => ['array', 'null'],
                                        'items' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'subject_name' => ['type' => 'string'],
                                                'grades' => [
                                                    'type' => 'array',
                                                    'items' => ['type' => 'string'],
                                                ],
                                                'section' => ['type' => ['string', 'null']],
                                                'teacher_name' => ['type' => ['string', 'null']],
                                            ],
                                            'required' => ['subject_name', 'grades', 'section', 'teacher_name'],
                                            'additionalProperties' => false,
                                        ],
                                    ],
                                    'students_data' => [
                                        'type' => ['array', 'null'],
                                        'items' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'name' => ['type' => 'string'],
                                                'grade' => ['type' => 'string'],
                                                'section' => ['type' => ['string', 'null']],
                                                'subject_name' => ['type' => ['string', 'null']],
                                                'teacher_name' => ['type' => ['string', 'null']],
                                            ],
                                            'required' => ['name', 'grade', 'section', 'subject_name', 'teacher_name'],
                                            'additionalProperties' => false,
                                        ],
                                    ],
                                    'section' => ['type' => ['string', 'null']],
                                    'subject_name' => ['type' => ['string', 'null']],
                                    'new_grade' => ['type' => ['string', 'null']],
                                    'new_section' => ['type' => ['string', 'null']],
                                    'new_name' => ['type' => ['string', 'null']],
                                    'operation' => ['type' => ['string', 'null']],
                                    'all_in_grade' => ['type' => ['boolean', 'null']],
                                    'invite_code' => ['type' => ['string', 'null']],
                                ],
                                'required' => [
                                    'teacher_name', 'student_name', 'names', 'grade', 'grades',
                                    'courses_data', 'students_data', 'section',
                                    'subject_name', 'new_grade', 'new_section', 'new_name',
                                    'operation', 'all_in_grade', 'invite_code',
                                ],
                                'additionalProperties' => false,
                            ],
                            'status' => [
                                'type' => 'string',
                                'enum' => ['pending', 'needs_info', 'confirmed', 'executed', 'failed', 'skipped'],
                            ],
                            'missing_slots' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'name' => ['type' => 'string'],
                                        'description' => ['type' => 'string'],
                                        'required' => ['type' => 'boolean'],
                                        'value' => [],
                                        'source' => [
                                            'type' => 'string',
                                            'enum' => ['user', 'context', 'llm', 'default'],
                                        ],
                                    ],
                                    'required' => ['name', 'description', 'required', 'value', 'source'],
                                    'additionalProperties' => false,
                                ],
                            ],
                            'depends_on' => [
                                'type' => 'array',
                                'items' => ['type' => 'string'],
                            ],
                            'confirmation_required' => ['type' => 'boolean'],
                        ],
                        'required' => [
                            'id', 'type', 'entity', 'params', 'status',
                            'missing_slots', 'depends_on', 'confirmation_required',
                        ],
                        'additionalProperties' => false,
                    ],
                ],
                'summary' => ['type' => 'string'],
                'requires_confirmation' => ['type' => 'boolean'],
                'all_or_nothing' => ['type' => 'boolean'],
            ],
            'required' => ['status', 'actions', 'summary', 'requires_confirmation', 'all_or_nothing'],
            'additionalProperties' => false,
        ];
    }

    /**
     * @param  array<string,mixed>  $context
     */
    private function systemPrompt(User $director, array $context): string
    {
        $roster = $this->rosterContext->markdownForDirector($director);
        $tools = collect(DirectorUnifiedAgentService::TOOLS)->implode(', ');

        return <<<PROMPT
Eres el planificador de acciones de AulaSync. Tu trabajo es analizar la orden del director y devolver un ActionPlan JSON con TODAS las acciones necesarias.

REGLAS CRÍTICAS:
1. MULTI-ACCIÓN OBLIGATORIA: Identifica CADA acción en la frase. Nunca te quedes solo con la primera. Frases con "y además", "adicional", "también" contienen 2-4 acciones separadas.
2. Usa los nombres exactos de herramientas del catálogo: {$tools}.
3. Para acciones de escritura (crear, modificar, eliminar, matricular), confirmation_required = true. Para lectura (get_*), false.
4. Si falta algún dato obligatorio, crea un slot en missing_slots con una pregunta clara.
5. NOMBRES: params.teacher_name / student_name / names son SOLO el nombre propio (ej. "Mariano", "Vicente José") sin "también", "llamado", "el que te dije", "profesor de...", "alumno". DEDUPLICA: si el usuario repite el mismo nombre ("a Vicente José, al alumno Vicente José y a Gabriela Pernal" → Vicente José una sola vez, Gabriela Pernal una vez), cada persona aparece UNA sola vez en el plan.
6. GRADOS — RANGOS EXPANDIDOS: Los grados válidos son 1ro, 2do, 3ro, 4to, 5to, 6to. Un rango como "de 1ro a 6to", "desde 1ro hasta 6to", "desde 1ro a 6to", "1ro a 6to" DEBE expandirse al array completo: ["1ro","2do","3ro","4to","5to","6to"]. Para create_teacher/create_course/assign_teacher usa el array grades completo. Para enroll_students_course usa grade individual o el grado relevante. NUNCA dejes un rango sin expandir ni como texto libre ni como grade=null.
7. Genera un resumen natural en "summary" numerando las acciones, usando nombres limpios y rangos expandidos (ej. "1ro a 6to").
8. Si no hay acciones, devuelve actions=[] y summary="No entendí ninguna acción. ¿Puedes reformular?".
9. CURSOS — SIEMPRE EN LOTE: para crear materias/cursos usa SIEMPRE create_courses_batch, incluso si es una sola materia en un solo grado. NUNCA uses create_course. params.courses_data es un array con UN item por materia y cada item lleva su propio array grades con todos los grados pedidos. Ejemplo — "crea matemática, lenguaje y biología para 3ro y 4to" es UNA sola acción: courses_data=[{"subject_name":"matemática","grades":["3ro","4to"],"section":null,"teacher_name":null},{"subject_name":"lenguaje","grades":["3ro","4to"],"section":null,"teacher_name":null},{"subject_name":"biología","grades":["3ro","4to"],"section":null,"teacher_name":null}]. No la partas en tres acciones.
10. DOCENTE OPCIONAL EN CURSOS: teacher_name es opcional dentro de courses_data. Si el director no menciona profesor, deja teacher_name=null y NO crees ningún missing_slot pidiéndolo: el curso se crea sin docente y se asigna después.
11. ALUMNOS — SIEMPRE EN LOTE CON GRADO POR ALUMNO: para create_students_batch usa SIEMPRE params.students_data, incluso si es un solo alumno. students_data es un array con UN item por alumno: {"name","grade","section","subject_name","teacher_name"}. Cada alumno lleva su propio grade, así que un mensaje como "crea a Juan Pérez en 1ro y a María Gómez en 3ro con Matemática" es UNA sola acción: students_data=[{"name":"Juan Pérez","grade":"1ro","section":null,"subject_name":null,"teacher_name":null},{"name":"María Gómez","grade":"3ro","section":null,"subject_name":"Matemática","teacher_name":null}]. No la partas en dos acciones ni fuerces un grade común para todos.
12. MATERIA OBLIGATORIA EN CURSOS/ASIGNACIONES: para create_course/create_courses_batch/assign_teacher, subject_name es obligatorio y debe ser una materia concreta. Si el director dice "crea los cursos de 1ro a 6to" o "asigna a José a 3ro A" sin materia, NO inventes ni asumas "Los Cursos", "Curso", "Clase" o similares. En esos casos coloca subject_name=null y agrega missing_slots con una pregunta para que el director indique la materia.
13. SINCRONIZACIÓN MASIVA: si el director pide "sincroniza las matrículas", "agrega a todos los alumnos a los cursos disponibles" o equivalentes, usa UNA sola acción sync_all_enrollments. No inventes un profesor ni partas la orden en enroll_students_course por alumno.

MODO DE RESPUESTA CONDICIONAL (para el summary, no para tools de analítica):
- factual_lookup_mode: summary en 1-2 oraciones, solo lo que se va a ejecutar. Sin recomendaciones.
- strategic_advisory_mode: solo si pide análisis, diagnóstico, informe, recomendaciones o la salud del colegio.

MANY-SHOT OBLIGATORIO (usar este patrón):
Entrada:
"Crea al profesor Junior Vázquez como profesor de biología de 1ro a 6to. Adicional, crea a los alumnos Jason David y Vicente José y agrégalos al curso de biología de 3ro."

Salida esperada (resumen de estructura):
{
  "status": "pending",
  "actions": [
    {
      "type": "create_courses_batch",
      "entity": "course",
      "params": {
        "courses_data": [
          {
            "subject_name": "Biología",
            "grades": ["1ro","2do","3ro","4to","5to","6to"],
            "section": null,
            "teacher_name": "Junior Vázquez"
          }
        ]
      }
    },
    {
      "type": "create_students_batch",
      "entity": "student",
      "params": {
        "students_data": [
          {"name":"Jason David","grade":"3ro","section":null,"subject_name":"Biología","teacher_name":"Junior Vázquez"},
          {"name":"Vicente José","grade":"3ro","section":null,"subject_name":"Biología","teacher_name":"Junior Vázquez"}
        ]
      }
    }
  ]
}
Nunca conviertas "de segundo/de tercero/para cuarto" en nombres de persona y no partas "Jason David y Vicente José" en acciones separadas.

Datos del colegio:
{$roster}
PROMPT;
    }

    /**
     * @return array<string,mixed>
     */
    private function fromFallback(string $text, string $source = 'fallback_unknown'): array
    {
        $actions = $this->intentExtractor->extractMultipleIntentions($text);

        $plan = $this->normalizePlan([
            'status' => 'pending',
            'actions' => collect($actions)->map(function (array $action) {
                $intent = $action['intent'];
                if ($intent === 'enroll_students') {
                    $intent = 'enroll_students_course';
                }

                return [
                    'id' => (string) Str::uuid(),
                    'type' => $intent,
                    'entity' => $this->entityForIntent($intent),
                    'params' => $action['data'],
                    'status' => 'pending',
                    'missing_slots' => [],
                    'depends_on' => [],
                    'confirmation_required' => DirectorUnifiedAgentService::isWriteTool($intent),
                ];
            })->all(),
            'summary' => $this->buildSummary($actions),
            'requires_confirmation' => collect($actions)->contains(
                fn (array $action) => DirectorUnifiedAgentService::isWriteTool($action['intent'])
            ),
            'all_or_nothing' => false,
        ], $text);
        $plan['planner_source'] = $source;

        return $plan;
    }

    /**
     * @param  array<string,mixed>  $plan
     * @return array<string,mixed>
     */
    private function normalizePlan(array $plan, string $text): array
    {
        $plan['id'] = (string) Str::uuid();
        $plan['created_at'] = now()->toIso8601String();
        $plan['updated_at'] = now()->toIso8601String();

        $plan['actions'] = collect($plan['actions'] ?? [])->map(function (array $action) {
            $action['params'] = $this->cleanParams($action['params'] ?? []);
            // Deduplica nombres dentro de cada acción y normaliza grades
            if (isset($action['params']['names']) && is_array($action['params']['names'])) {
                $action['params']['names'] = array_values(array_unique(array_filter(array_map(fn ($n) => trim((string) $n), $action['params']['names']))));
            }
            if (isset($action['params']['grades']) && is_array($action['params']['grades'])) {
                $action['params']['grades'] = array_values(array_unique(array_filter(array_map(fn ($g) => trim((string) $g), $action['params']['grades']))));
            }
            // Normaliza students_data: recorta strings, descarta items sin name/grade
            // y elimina duplicados exactos (mismo alumno, grado y sección).
            if (isset($action['params']['students_data']) && is_array($action['params']['students_data'])) {
                $action['params']['students_data'] = collect($action['params']['students_data'])
                    ->filter(fn ($item) => is_array($item))
                    ->map(function (array $item) {
                        $section = trim((string) ($item['section'] ?? ''));
                        $subject = trim((string) ($item['subject_name'] ?? ''));
                        $teacher = trim((string) ($item['teacher_name'] ?? ''));

                        return [
                            'name' => trim((string) ($item['name'] ?? '')),
                            'grade' => trim((string) ($item['grade'] ?? '')),
                            'section' => $section !== '' ? $section : null,
                            'subject_name' => $subject !== '' ? $subject : null,
                            'teacher_name' => $teacher !== '' ? $teacher : null,
                        ];
                    })
                    ->filter(fn (array $item) => $item['name'] !== '' && $item['grade'] !== '')
                    ->unique(fn (array $item) => mb_strtolower($item['name']).'|'.mb_strtolower($item['grade']).'|'.mb_strtolower((string) $item['section']))
                    ->values()
                    ->all();
            }
            $action['missing_slots'] = $this->normalizeSlots($action['missing_slots'] ?? []);
            $action['depends_on'] = array_values(array_filter((array) ($action['depends_on'] ?? [])));
            $action['confirmation_required'] = (bool) ($action['confirmation_required'] ?? DirectorUnifiedAgentService::isWriteTool($action['type'] ?? ''));

            if ($action['missing_slots'] !== []) {
                $action['status'] = 'needs_info';
            }

            return $action;
        })->all();

        // Deduplicación global: misma persona no debe aparecer dos veces en el plan.
        $plan['actions'] = $this->deduplicatePlanActions($plan['actions']);

        // Batch como único camino para cursos, independientemente de lo que emitió el LLM.
        $plan['actions'] = $this->canonicalizeCourseActions($plan['actions']);

        $plan['actions'] = $this->enforceRequiredSubjects($plan['actions']);

        if (collect($plan['actions'])->contains(fn (array $a) => $a['status'] === 'needs_info')) {
            $plan['status'] = 'needs_info';
        }

        if (empty($plan['summary'])) {
            $plan['summary'] = $this->buildSummary($plan['actions']);
        }

        return $plan;
    }

    /**
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     */
    private function cleanParams(array $params): array
    {
        $clean = [];
        foreach ($params as $key => $value) {
            if ($value === null) {
                continue;
            }
            if (is_string($value)) {
                $value = trim($value);
                if ($value === '') {
                    continue;
                }
            }
            if (is_array($value)) {
                $value = array_values(array_filter($value, fn ($v) => $v !== null && $v !== ''));
                if ($value === []) {
                    continue;
                }
            }
            if ($key === 'subject_name' && is_string($value)) {
                $value = $this->normalizeSubjectName($value);
                if ($value === null) {
                    continue;
                }
            }
            $clean[$key] = $value;
        }

        return $clean;
    }

    private function normalizeSubjectName(?string $subject): ?string
    {
        $value = trim((string) $subject);
        if ($value === '') {
            return null;
        }

        $folded = mb_strtolower($value);
        $folded = strtr($folded, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u']);
        if (preg_match(self::GENERIC_SUBJECT_PATTERN, $folded)) {
            return null;
        }

        return $value;
    }

    /**
     * @param  array<int,array<string,mixed>>  $actions
     * @return array<int,array<string,mixed>>
     */
    private function enforceRequiredSubjects(array $actions): array
    {
        return array_map(function (array $action) {
            $type = (string) ($action['type'] ?? '');
            $params = (array) ($action['params'] ?? []);
            $slots = (array) ($action['missing_slots'] ?? []);
            $needsSubject = in_array($type, ['assign_teacher', 'create_course', 'create_courses_batch'], true);
            if (! $needsSubject) {
                return $action;
            }

            if ($type === 'create_courses_batch') {
                $items = array_values(array_filter((array) ($params['courses_data'] ?? []), 'is_array'));
                $hasMissing = false;
                foreach ($items as $i => $item) {
                    $subject = $this->normalizeSubjectName(isset($item['subject_name']) ? (string) $item['subject_name'] : null);
                    $items[$i]['subject_name'] = $subject;
                    if ($subject === null) {
                        $hasMissing = true;
                    }
                }
                $params['courses_data'] = $items;
                if ($hasMissing) {
                    $slots[] = [
                        'name' => 'subject_name',
                        'description' => '¿Qué materia específica debo usar para crear esos cursos?',
                        'required' => true,
                        'value' => null,
                        'source' => 'user',
                    ];
                }
            } else {
                $subject = $this->normalizeSubjectName(isset($params['subject_name']) ? (string) $params['subject_name'] : null);
                $params['subject_name'] = $subject;
                if ($subject === null) {
                    $slots[] = [
                        'name' => 'subject_name',
                        'description' => $type === 'assign_teacher'
                            ? '¿Qué materia deseas asignar a ese profesor?'
                            : '¿Qué materia debo crear para ese curso?',
                        'required' => true,
                        'value' => null,
                        'source' => 'user',
                    ];
                }
            }

            $action['params'] = $params;
            $action['missing_slots'] = $this->normalizeSlots($slots);
            if ($action['missing_slots'] !== []) {
                $action['status'] = 'needs_info';
                $action['confirmation_required'] = true;
            }

            return $action;
        }, $actions);
    }

    /**
     * @param  array<int,array<string,mixed>>  $slots
     * @return array<int,array<string,mixed>>
     */
    private function normalizeSlots(array $slots): array
    {
        return collect($slots)->map(function (array $slot) {
            return [
                'name' => (string) ($slot['name'] ?? ''),
                'description' => (string) ($slot['description'] ?? ''),
                'required' => (bool) ($slot['required'] ?? true),
                'value' => $slot['value'] ?? null,
                'source' => in_array($slot['source'] ?? '', ['user', 'context', 'llm', 'default'], true)
                    ? $slot['source']
                    : 'user',
            ];
        })->filter(fn (array $slot) => $slot['name'] !== '' && $slot['description'] !== '')->values()->all();
    }

    private function entityForIntent(string $intent): string
    {
        return match (true) {
            str_contains($intent, 'teacher') || str_contains($intent, 'course') => 'teacher',
            str_contains($intent, 'student') || str_contains($intent, 'enroll') => 'student',
            str_starts_with($intent, 'get_') => 'analytics',
            default => 'general',
        };
    }

    /**
     * Deduplica personas repetidas en el plan (corrección a media frase).
     *
     * @param  array<int,array<string,mixed>>  $actions
     * @return array<int,array<string,mixed>>
     */
    private function deduplicatePlanActions(array $actions): array
    {
        $seen = [];
        $filtered = [];
        foreach ($actions as $action) {
            $type = $action['type'] ?? '';
            if ($type === 'create_students_batch' && isset($action['params']['students_data'])) {
                $items = (array) ($action['params']['students_data'] ?? []);
                $unique = [];
                foreach ($items as $item) {
                    $key = mb_strtolower(trim((string) ($item['name'] ?? '')));
                    if ($key === '' || isset($seen['student:'.$key])) {
                        continue;
                    }
                    $seen['student:'.$key] = true;
                    $unique[] = $item;
                }
                if ($unique === [] && $items !== []) {
                    continue;
                }
                $action['params']['students_data'] = array_values($unique);
            } elseif (in_array($type, ['create_students_batch', 'enroll_students_course'], true)) {
                $names = (array) ($action['params']['names'] ?? []);
                $unique = [];
                foreach ($names as $n) {
                    $key = mb_strtolower(trim((string) $n));
                    if ($key === '' || isset($seen['student:'.$key])) {
                        continue;
                    }
                    $seen['student:'.$key] = true;
                    $unique[] = $n;
                }
                if ($unique === [] && $names !== []) {
                    continue;
                }
                $action['params']['names'] = array_values($unique);
            } elseif ($type === 'create_teacher' && isset($action['params']['teacher_name'])) {
                $key = mb_strtolower(trim((string) $action['params']['teacher_name']));
                if ($key !== '' && isset($seen['teacher:'.$key])) {
                    continue;
                }
                $seen['teacher:'.$key] = true;
            }
            $filtered[] = $action;
        }

        return $filtered;
    }

    /**
     * Batch como único camino: toda acción de creación de cursos —venga del LLM
     * o del extractor de fallback— se reescribe a create_courses_batch, y las
     * acciones de curso consecutivas se fusionan en una sola. Así "matemática,
     * lenguaje y biología para 3ro y 4to" pide UNA confirmación en vez de tres,
     * y no depende de que el prompt se respete al pie de la letra.
     *
     * @param  array<int,array<string,mixed>>  $actions
     * @return array<int,array<string,mixed>>
     */
    private function canonicalizeCourseActions(array $actions): array
    {
        $result = [];

        foreach ($actions as $action) {
            $type = (string) ($action['type'] ?? '');

            if (! in_array($type, ['create_course', 'create_courses_batch'], true)) {
                $result[] = $action;

                continue;
            }

            $params = (array) ($action['params'] ?? []);
            $items = $type === 'create_courses_batch'
                ? array_values(array_filter((array) ($params['courses_data'] ?? []), 'is_array'))
                : [[
                    'subject_name' => $params['subject_name'] ?? null,
                    'grades' => (array) ($params['grades'] ?? array_filter([$params['grade'] ?? null])),
                    'section' => $params['section'] ?? null,
                    'teacher_name' => $params['teacher_name'] ?? null,
                ]];

            $items = array_values(array_filter(
                $items,
                fn (array $item) => trim((string) ($item['subject_name'] ?? '')) !== ''
            ));

            if ($items === []) {
                // Sin materia utilizable: se conserva la acción tal cual para que
                // el gate de parámetros obligatorios le pida el dato al director.
                $result[] = $action;

                continue;
            }

            $previous = array_key_last($result);
            $mergeable = $previous !== null
                && ($result[$previous]['type'] ?? '') === 'create_courses_batch'
                && ($result[$previous]['status'] ?? 'pending') !== 'needs_info'
                && ($action['status'] ?? 'pending') !== 'needs_info';

            if ($mergeable) {
                $result[$previous]['params']['courses_data'] = array_merge(
                    (array) $result[$previous]['params']['courses_data'],
                    $items
                );

                continue;
            }

            unset(
                $params['subject_name'],
                $params['grades'],
                $params['grade'],
                $params['section'],
                $params['teacher_name'],
            );
            $params['courses_data'] = $items;

            $action['type'] = 'create_courses_batch';
            $action['params'] = $params;
            $action['confirmation_required'] = true;
            $result[] = $action;
        }

        return $result;
    }

    /**
     * @param  array<int,array<string,mixed>>  $actions
     */
    private function buildSummary(array $actions): string
    {
        if ($actions === []) {
            return 'No identifiqué acciones en tu mensaje.';
        }

        $lines = collect($actions)->map(function (array $action, int $index) {
            $desc = match ($action['intent'] ?? $action['type'] ?? '') {
                'create_teacher' => 'Crear profesor '.($action['data']['teacher_name'] ?? ''),
                'create_course', 'create_courses_batch' => $this->summarizeCourseAction($action),
                'assign_teacher' => 'Asignar materia a '.($action['data']['teacher_name'] ?? ''),
                'create_students_batch' => $this->summarizeStudentsAction($action),
                'enroll_students_course', 'enroll_students' => 'Matricular alumnos '.implode(', ', $action['data']['names'] ?? []),
                'sync_all_enrollments' => 'Sincronizar matrículas de alumnos con los cursos de su grado',
                'update_student' => 'Cambiar a '.($action['data']['student_name'] ?? ''),
                'delete_teacher' => 'Eliminar profesor '.($action['data']['teacher_name'] ?? ''),
                'delete_student' => 'Eliminar alumno '.($action['data']['student_name'] ?? ''),
                default => 'Ejecutar '.($action['intent'] ?? $action['type'] ?? 'acción'),
            };

            return ($index + 1).'. '.$desc;
        });

        return "Voy a hacer:\n".$lines->implode("\n");
    }

    /**
     * Resumen legible de una acción de cursos. Acepta las dos formas que circulan
     * en el proyecto: `data` (fallback/legado) y `params` (ActionPlan).
     *
     * @param  array<string,mixed>  $action
     */
    private function summarizeCourseAction(array $action): string
    {
        $payload = (array) ($action['data'] ?? $action['params'] ?? []);
        $items = array_values(array_filter((array) ($payload['courses_data'] ?? []), 'is_array'));

        if ($items === []) {
            $items = [[
                'subject_name' => $payload['subject_name'] ?? '',
                'grades' => (array) ($payload['grades'] ?? array_filter([$payload['grade'] ?? null])),
            ]];
        }

        $parts = collect($items)
            ->map(function (array $item) {
                $subject = trim((string) ($item['subject_name'] ?? ''));
                $grades = implode(', ', array_filter((array) ($item['grades'] ?? [])));

                return $grades !== '' ? "{$subject} ({$grades})" : $subject;
            })
            ->filter(fn (string $part) => $part !== '')
            ->implode('; ');

        return $parts !== '' ? 'Crear cursos: '.$parts : 'Crear cursos';
    }

    /**
     * Resumen legible de una acción de alumnos. Acepta las dos formas que circulan
     * en el proyecto: `students_data` (formato canónico) y `names`+`grade` (legado).
     *
     * @param  array<string,mixed>  $action
     */
    private function summarizeStudentsAction(array $action): string
    {
        $payload = (array) ($action['data'] ?? $action['params'] ?? []);
        $items = array_values(array_filter((array) ($payload['students_data'] ?? []), 'is_array'));

        if ($items === []) {
            $grade = trim((string) ($payload['grade'] ?? ''));
            $items = collect((array) ($payload['names'] ?? []))
                ->map(fn ($name) => ['name' => $name, 'grade' => $grade])
                ->all();
        }

        $parts = collect($items)
            ->map(function (array $item) {
                $name = trim((string) ($item['name'] ?? ''));
                $grade = trim((string) ($item['grade'] ?? ''));

                return $grade !== '' ? "{$name} ({$grade})" : $name;
            })
            ->filter(fn (string $part) => $part !== '')
            ->implode(', ');

        return $parts !== '' ? 'Crear alumnos: '.$parts : 'Crear alumnos';
    }
}

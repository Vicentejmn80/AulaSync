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
                                    'teacher_name', 'student_name', 'names', 'grade', 'section',
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
6. GRADOS — RANGOS EXPANDIDOS: Los grados válidos son 1ro, 2do, 3ro, 4to, 5to, 6to. Un rango como "de 1ro a 6to", "desde 1ro hasta 6to", "desde 1ro a 6to", "1ro a 6to" DEBE expandirse al array completo: ["1ro","2do","3ro","4to","5to","6to"]. Para create_teacher/create_course/assign_teacher usa el array grades completo. Para create_students_batch/enroll usa grade individual o el grado relevante. NUNCA dejes un rango sin expandir ni como texto libre ni como grade=null.
7. Genera un resumen natural en "summary" numerando las acciones, usando nombres limpios y rangos expandidos (ej. "1ro a 6to").
8. Si no hay acciones, devuelve actions=[] y summary="No entendí ninguna acción. ¿Puedes reformular?".

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
            $clean[$key] = $value;
        }

        return $clean;
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
            if (in_array($type, ['create_students_batch', 'enroll_students_course'], true)) {
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
                'assign_teacher' => 'Asignar materia a '.($action['data']['teacher_name'] ?? ''),
                'create_students_batch' => 'Crear alumnos '.implode(', ', $action['data']['names'] ?? []),
                'enroll_students_course', 'enroll_students' => 'Matricular alumnos '.implode(', ', $action['data']['names'] ?? []),
                'update_student' => 'Cambiar a '.($action['data']['student_name'] ?? ''),
                'delete_teacher' => 'Eliminar profesor '.($action['data']['teacher_name'] ?? ''),
                'delete_student' => 'Eliminar alumno '.($action['data']['student_name'] ?? ''),
                default => 'Ejecutar '.($action['intent'] ?? $action['type'] ?? 'acción'),
            };

            return ($index + 1).'. '.$desc;
        });

        return "Voy a hacer:\n".$lines->implode("\n");
    }
}

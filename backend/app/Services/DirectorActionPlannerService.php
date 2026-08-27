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
            return $this->fromFallback($text);
        }

        $director->loadMissing('colegio');

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
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => $this->systemPrompt($director, $context),
                        ],
                        [
                            'role' => 'user',
                            'content' => $text,
                        ],
                    ],
                ]);

            if ($response->failed()) {
                Log::warning('Director action planner unavailable', [
                    'director_id' => $director->id,
                    'status' => $response->status(),
                ]);

                return $this->fromFallback($text);
            }

            $raw = (string) $response->json('choices.0.message.content', '');
            $plan = json_decode($raw, true);

            if (! is_array($plan)) {
                return $this->fromFallback($text);
            }

            return $this->normalizePlan($plan, $text);
        } catch (\Throwable $e) {
            Log::warning('Director action planner failed; using fallback', [
                'director_id' => $director->id,
                'message' => $e->getMessage(),
            ]);

            return $this->fromFallback($text);
        }
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

REGLAS:
1. Identifica CADA acción en la frase. Nunca te quedes solo con la primera.
2. Usa los nombres exactos de herramientas del catálogo: {$tools}.
3. Para acciones de escritura (crear, modificar, eliminar, matricular), confirmation_required = true.
4. Para acciones de lectura (get_*), confirmation_required = false.
5. Si falta algún dato obligatorio, crea un slot en missing_slots con una pregunta clara para el director.
6. Los nombres propios van en params tal cual los dice el usuario (sin conectores como "también", "llamado", "el que te dije").
7. Los grados: 1ro, 2do, 3ro, 4to, 5to, 6to. Los rangos "de 1ro a 6to" se representan como grade=null y el rango en missing_slots, o si la tool lo soporta, incluye el grado inicial.
8. Genera un resumen natural en "summary" numerando las acciones.
9. Si no hay acciones, devuelve actions=[] y summary="No entendí ninguna acción. ¿Puedes reformular?".

Datos del colegio:
{$roster}
PROMPT;
    }

    /**
     * @return array<string,mixed>
     */
    private function fromFallback(string $text): array
    {
        $actions = $this->intentExtractor->extractMultipleIntentions($text);

        return $this->normalizePlan([
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
            $action['missing_slots'] = $this->normalizeSlots($action['missing_slots'] ?? []);
            $action['depends_on'] = array_values(array_filter((array) ($action['depends_on'] ?? [])));
            $action['confirmation_required'] = (bool) ($action['confirmation_required'] ?? DirectorUnifiedAgentService::isWriteTool($action['type'] ?? ''));

            if ($action['missing_slots'] !== []) {
                $action['status'] = 'needs_info';
            }

            return $action;
        })->all();

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

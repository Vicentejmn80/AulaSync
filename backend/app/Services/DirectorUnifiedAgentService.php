<?php

namespace App\Services;

use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Punto único de catálogo y ejecución de herramientas del agente del director.
 *
 * Sprint 1 de la reingeniería del agente: unifica el catálogo de herramientas
 * de lectura (antes solo en DirectorDataAgentService) y de escritura (antes
 * solo en DirectorAIInterpreterService) en UN solo array, y expone UN solo
 * método execute() que despacha a los servicios especializados existentes
 * (DirectorDataAgentService para lectura, DirectorActionService para
 * escritura) según corresponda.
 *
 * Esto elimina la duplicación de `toolDefinitions()` entre los dos agentes
 * (antes el intérprete construía su propio array de mutaciones y además
 * pedía las tools de datos vía `app(DirectorDataAgentService::class)`).
 *
 * Los siguientes sprints (clasificación de intención por LLM, slots de
 * confirmación, transaccionalidad global) construirán sobre este catálogo
 * y este dispatcher únicos.
 */
class DirectorUnifiedAgentService
{
    /**
     * Herramientas de escritura (mutaciones). Se ejecutan a través de
     * DirectorActionService y, salvo excepciones, requieren confirmación
     * del director antes de aplicarse.
     */
    public const WRITE_TOOLS = [
        'create_teacher',
        'create_course',
        'assign_teacher',
        'create_students_batch',
        'enroll_students_course',
        'unenroll_students_course',
        'unassign_teacher',
        'update_course',
        'update_student',
        'delete_teacher',
        'delete_teacher_invite',
        'delete_all_teachers',
        'delete_course',
        'delete_all_courses',
        'delete_student',
        'manage_invite_code',
    ];

    /**
     * Catálogo completo: lectura (DirectorDataAgentService::TOOLS) + escritura.
     * Única fuente de verdad para "qué herramientas conoce el agente".
     */
    public const TOOLS = [
        // Lectura (DirectorDataAgentService::TOOLS)
        'get_students', 'get_student', 'get_courses', 'get_teachers', 'get_teacher_invite_code',
        'verify_teacher', 'verify_student', 'get_grades', 'get_attendance',
        'get_evaluations', 'get_assignments', 'get_student_performance',
        'get_course_performance', 'compare_courses', 'get_school_health',
        'get_trend_analysis', 'get_risk_analysis', 'get_cause_analysis',
        'get_smart_recommendations', 'get_at_risk_students',
        'get_declining_students', 'get_academic_trends', 'generate_school_report',
        'get_rankings', 'get_section_counts', 'query_academic',
        // Escritura
        'create_teacher', 'create_course', 'assign_teacher',
        'create_students_batch', 'enroll_students_course', 'unenroll_students_course',
        'unassign_teacher', 'update_course', 'update_student',
        'delete_teacher', 'delete_teacher_invite', 'delete_all_teachers',
        'delete_course', 'delete_all_courses', 'delete_student',
        'manage_invite_code',
    ];

    public function __construct(
        private DirectorDataAgentService $dataAgent,
        private DirectorActionService $actionService,
        private DirectorConversationContextService $conversationContext,
    ) {}

    public static function isReadTool(string $tool): bool
    {
        return DirectorDataAgentService::isDataTool($tool);
    }

    public static function isWriteTool(string $tool): bool
    {
        return in_array($tool, self::WRITE_TOOLS, true);
    }

    /**
     * Catálogo único de herramientas en formato OpenAI function-calling.
     * Combina las tools de lectura (analítica) y de escritura (CRUD) en un
     * solo array, sin duplicación ni llamadas a service locator.
     *
     * @return array<int,array{type:string,function:array}>
     */
    public function toolDefinitions(): array
    {
        return array_merge(
            $this->dataAgent->toolDefinitions(),
            [$this->sectionCountsToolDefinition()],
            $this->writeToolDefinitions(),
        );
    }

    /**
     * `get_section_counts` está en DirectorDataAgentService::TOOLS y en su
     * execute(), pero nunca se anunció al LLM vía toolDefinitions(). Se
     * completa aquí para que el catálogo unificado no tenga huecos.
     */
    private function sectionCountsToolDefinition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'get_section_counts',
                'description' => 'Conteo de alumnos por grado y sección del colegio. Nunca inventes datos. colegio_id lo pone el backend.',
                'strict' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [],
                    'required' => [],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    /**
     * Definiciones de las herramientas de escritura (antes vivían dentro de
     * DirectorAIInterpreterService::toolDefinitions()).
     *
     * @return array<int,array{type:string,function:array}>
     */
    private function writeToolDefinitions(): array
    {
        $defs = [
            'create_teacher' => [
                'description' => 'Crear/invitar profesor y opcionalmente asignarle una materia en varios grados. teacher_name SOLO el nombre propio (sin "también", "que te dije", "llamado" ni la materia). Si el mensaje lista VARIOS profesores, llama esta tool UNA vez por cada nombre. Nunca te quedes con uno solo.',
                'properties' => [
                    'teacher_name' => ['type' => 'string'],
                    'subject_name' => ['type' => ['string', 'null']],
                    'grades' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'section' => ['type' => ['string', 'null']],
                ],
                'required' => ['teacher_name'],
            ],
            'create_course' => [
                'description' => 'Crear uno o varios cursos/materias, opcionalmente asignados a un profesor o invitación.',
                'properties' => [
                    'subject_name' => ['type' => 'string'],
                    'grades' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'section' => ['type' => ['string', 'null']],
                    'teacher_name' => ['type' => ['string', 'null']],
                ],
                'required' => ['subject_name', 'grades'],
            ],
            'assign_teacher' => [
                'description' => 'Asignar una materia y grados a un profesor registrado o invitación pendiente.',
                'properties' => [
                    'teacher_name' => ['type' => 'string'],
                    'subject_name' => ['type' => 'string'],
                    'grades' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'section' => ['type' => ['string', 'null']],
                ],
                'required' => ['teacher_name', 'subject_name', 'grades'],
            ],
            'create_students_batch' => [
                'description' => 'Crear uno o varios alumnos (o alumnas) y, si hay materia/profesor, matricularlos en course_student. names es un ARRAY de nombres propios completos. Incluye subject_name y teacher_name cuando el director mencione el curso o el docente.',
                'properties' => [
                    'names' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'grade' => ['type' => 'string'],
                    'section' => ['type' => ['string', 'null']],
                    'subject_name' => ['type' => ['string', 'null']],
                    'teacher_name' => ['type' => ['string', 'null']],
                ],
                'required' => ['names', 'grade'],
            ],
            'enroll_students_course' => [
                'description' => 'Matricular alumnos existentes en un curso. Usa all_in_grade=true para inscribir a todo un grado (ej. "los alumnos de 1ro").',
                'properties' => [
                    'names' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'subject_name' => ['type' => 'string'],
                    'grade' => ['type' => 'string'],
                    'section' => ['type' => ['string', 'null']],
                    'teacher_name' => ['type' => ['string', 'null']],
                    'all_in_grade' => ['type' => ['boolean', 'null']],
                ],
                'required' => ['subject_name', 'grade'],
            ],
            'unenroll_students_course' => [
                'description' => 'Desmatricular alumnos de un curso sin eliminarlos del colegio.',
                'properties' => [
                    'names' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'subject_name' => ['type' => 'string'],
                    'grade' => ['type' => 'string'],
                    'section' => ['type' => ['string', 'null']],
                ],
                'required' => ['names', 'subject_name', 'grade'],
            ],
            'unassign_teacher' => [
                'description' => 'Desasignar cursos de un profesor o invitación sin eliminar el profesor ni los cursos.',
                'properties' => [
                    'teacher_name' => ['type' => 'string'],
                    'subject_name' => ['type' => ['string', 'null']],
                    'grades' => ['type' => 'array', 'items' => ['type' => 'string']],
                ],
                'required' => ['teacher_name'],
            ],
            'update_course' => [
                'description' => 'Modificar nombre de materia, grado o sección de un curso existente.',
                'properties' => [
                    'subject_name' => ['type' => 'string'],
                    'grade' => ['type' => 'string'],
                    'section' => ['type' => ['string', 'null']],
                    'new_subject_name' => ['type' => ['string', 'null']],
                    'new_grade' => ['type' => ['string', 'null']],
                    'new_section' => ['type' => ['string', 'null']],
                ],
                'required' => ['subject_name', 'grade'],
            ],
            'update_student' => [
                'description' => 'Modificar nombre, grado o sección de un alumno; también sirve para moverlo de grado. Al cambiar de grado se re-matricula en los cursos de destino.',
                'properties' => [
                    'student_name' => ['type' => 'string'],
                    'new_name' => ['type' => ['string', 'null']],
                    'new_grade' => ['type' => ['string', 'null']],
                    'new_section' => ['type' => ['string', 'null']],
                ],
                'required' => ['student_name'],
            ],
            'delete_teacher' => [
                'description' => 'Eliminar un profesor registrado específico. No usar para cancelar invitaciones; para eso usa delete_teacher_invite.',
                'properties' => ['teacher_name' => ['type' => 'string']],
                'required' => ['teacher_name'],
            ],
            'delete_teacher_invite' => [
                'description' => 'Cancelar/revocar una invitación DOC- pendiente de un profesor. NO elimina el profesor registrado. teacher_name SOLO el nombre propio.',
                'properties' => ['teacher_name' => ['type' => 'string']],
                'required' => ['teacher_name'],
            ],
            'delete_all_teachers' => [
                'description' => 'Eliminar todos los profesores del colegio.',
                'properties' => [],
                'required' => [],
            ],
            'delete_course' => [
                'description' => 'Eliminar cursos de una materia, opcionalmente grado y sección.',
                'properties' => [
                    'subject_name' => ['type' => 'string'],
                    'grade' => ['type' => ['string', 'null']],
                    'section' => ['type' => ['string', 'null']],
                ],
                'required' => ['subject_name'],
            ],
            'delete_all_courses' => [
                'description' => 'Eliminar todos los cursos del colegio.',
                'properties' => [],
                'required' => [],
            ],
            'delete_student' => [
                'description' => 'Eliminar uno o varios alumnos de la nómina. Usa names para lotes.',
                'properties' => [
                    'student_name' => ['type' => ['string', 'null']],
                    'names' => ['type' => ['array', 'null'], 'items' => ['type' => 'string']],
                ],
                'required' => [],
            ],
            'manage_invite_code' => [
                'description' => 'Consultar el código de invitación DOC- de un profesor. Úsalo cuando el director pida "el código de [profesor]", "código de invitación de [profesor]" o dé un código DOC- concreto para verificarlo. operation siempre "query" (esta versión solo soporta consulta).',
                'properties' => [
                    'operation' => ['type' => 'string', 'enum' => ['query']],
                    'teacher_name' => ['type' => ['string', 'null']],
                    'invite_code' => ['type' => ['string', 'null']],
                ],
                'required' => ['operation'],
            ],
        ];

        return collect($defs)->map(function ($definition, $name) {
            return [
                'type' => 'function',
                'function' => [
                    'name' => $name,
                    'description' => $definition['description'],
                    'strict' => false,
                    'parameters' => [
                        'type' => 'object',
                        'properties' => $definition['properties'],
                        'required' => $definition['required'],
                        'additionalProperties' => false,
                    ],
                ],
            ];
        })->values()->all();
    }

    /**
     * Único punto de ejecución de herramientas del agente del director.
     * Despacha a DirectorActionService para escritura y a
     * DirectorDataAgentService para lectura (incluida `query_academic`,
     * cuyo callback legado sigue siendo responsabilidad del controlador).
     *
     * @return array<string,mixed>
     */
    public function execute(User $director, string $tool, array $args, ?callable $legacyQuery = null): array
    {
        if (self::isWriteTool($tool)) {
            return $this->executeWrite($director, $tool, $args);
        }

        if (self::isReadTool($tool)) {
            return $this->dataAgent->execute($director, $tool, $args, $legacyQuery);
        }

        throw ValidationException::withMessages([
            'intent' => "Herramienta '{$tool}' no reconocida por el agente del director.",
        ]);
    }

    /**
     * Sprint 8: Orquestador de consulta inteligente.
     *
     * Flujo:
     * 1) Construye contexto completo.
     * 2) LLM propone plan de tools (fallback a plan determinista actual).
     * 3) Ejecuta tools vía DataAgent answer.
     * 4) LLM analiza resultados en conjunto.
     * 5) LLM genera respuesta conversacional y recomendaciones.
     *
     * @param  array<string,mixed>  $screenContext
     * @param  array<int,array{intent:string,data:array}>|null  $preplanned
     * @return array<string,mixed>
     */
    public function handleIntelligentQuery(
        User $director,
        string $text,
        array $screenContext = [],
        ?array $preplanned = null,
        ?callable $legacyQuery = null,
        array $memory = [],
    ): array {
        $context = $this->buildIntelligentContext($director, $text, $screenContext, $memory);
        $basePlan = $this->dataAgent->plan($text, $screenContext, $preplanned, $memory);
        $plan = $this->planIntelligentQuery($director, $text, $context, $basePlan) ?? $basePlan;
        $answer = $this->dataAgent->answer($director, $text, $plan, $legacyQuery);

        if (! empty($answer['needs_clarification'])) {
            $answer['intelligent_query'] = [
                'context' => $context,
                'plan' => $plan,
                'analysis' => null,
                'recommendations' => [],
            ];

            return $answer;
        }

        $analysis = $this->analyzeResults($director, $text, (array) ($answer['actions'] ?? []), $context, $plan);
        $generated = $this->generateResponse($director, $text, $analysis, $context, (string) ($answer['message'] ?? ''));

        if ($generated !== null && trim((string) ($generated['message'] ?? '')) !== '') {
            $answer['message'] = $generated['message'];
            $answer['recommendations'] = $generated['recommendations'] ?? ($analysis['recommendations'] ?? []);
        } else {
            $answer['recommendations'] = $analysis['recommendations'] ?? [];
        }

        $answer['analysis'] = $analysis;
        $answer['intelligent_query'] = [
            'context' => $context,
            'plan' => $plan,
            'analysis' => $analysis,
            'recommendations' => $answer['recommendations'] ?? [],
        ];

        return $answer;
    }

    /**
     * @param  array<string,mixed>  $screenContext
     * @param  array<string,mixed>  $memory
     * @return array<string,mixed>
     */
    private function buildIntelligentContext(User $director, string $text, array $screenContext, array $memory): array
    {
        $snapshot = $this->conversationContext->snapshot($memory !== [] ? $memory : null);
        $history = array_slice((array) ($snapshot['history'] ?? []), -10);
        $schoolHealth = $this->safeToolData($director, 'get_school_health', []);
        $academicYear = Course::query()
            ->where('colegio_id', $director->colegio_id)
            ->whereNotNull('school_year')
            ->orderByDesc('id')
            ->value('school_year');
        if (! is_string($academicYear) || trim($academicYear) === '') {
            $year = (int) now()->format('Y');
            $academicYear = $year.'-'.($year + 1);
        }

        return [
            'system' => $this->intelligentSystemPrompt(),
            'prompt' => $text,
            'conversation_history' => $history,
            'school_data' => $schoolHealth,
            'screen_context' => $screenContext,
            'memory' => [
                'last_student' => $snapshot['last_student'] ?? null,
                'last_grade' => $snapshot['last_grade'] ?? null,
                'last_section' => $snapshot['last_section'] ?? null,
                'last_subject' => $snapshot['last_subject'] ?? null,
                'last_teacher' => $snapshot['last_teacher'] ?? null,
                'chat_mode' => $snapshot['chat_mode'] ?? 'main_menu',
                'chat_subject' => $snapshot['chat_subject'] ?? null,
            ],
            'current_user' => [
                'id' => $director->id,
                'name' => $director->name,
                'role' => (string) $director->role,
            ],
            'current_time' => now()->toDateTimeString(),
            'academic_year' => $academicYear,
        ];
    }

    /**
     * @param  array<string,mixed>  $context
     * @param  array{tools:array<int,array{tool:string,args:array}>,intent:string,clarification:?string,wants_opinion:bool}  $fallbackPlan
     * @return array{tools:array<int,array{tool:string,args:array}>,intent:string,clarification:?string,wants_opinion:bool}|null
     */
    private function planIntelligentQuery(User $director, string $text, array $context, array $fallbackPlan): ?array
    {
        if (app()->runningUnitTests()) {
            return null;
        }
        $key = trim((string) config('services.openai.key'));
        if ($key === '' || str_contains($key, 'your_openai')) {
            return null;
        }

        try {
            $tools = array_values(array_filter(
                $this->dataAgent->toolDefinitions(),
                fn (array $def) => isset($def['function']['name']) && self::isReadTool((string) $def['function']['name'])
            ));

            $response = Http::timeout(14)
                ->withToken($key)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => (string) config('services.openai.director_model', 'gpt-4o-mini'),
                    'temperature' => 0.2,
                    'top_p' => 0.95,
                    'tool_choice' => 'auto',
                    'parallel_tool_calls' => true,
                    'tools' => $tools,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => "Eres el planificador estratégico del modo consulta inteligente de AulaSync.\n"
                                ."Selecciona SOLO herramientas de lectura necesarias para responder la consulta.\n"
                                ."No ejecutes herramientas redundantes.\n"
                                ."Si el usuario pide recomendaciones, incluye get_smart_recommendations.\n"
                                ."Si pregunta por riesgo, incluye get_risk_analysis.\n"
                                ."Si pregunta por causas, incluye get_trend_analysis y get_cause_analysis.\n"
                                ."Resuelve referencias con conversation_history.\n"
                                ."Nunca inventes parámetros fuera de contexto.",
                        ],
                        [
                            'role' => 'user',
                            'content' => json_encode([
                                'question' => $text,
                                'context' => $context,
                                'fallback_plan' => $fallbackPlan,
                            ], JSON_UNESCAPED_UNICODE),
                        ],
                    ],
                ]);

            if ($response->failed()) {
                return null;
            }

            $calls = (array) $response->json('choices.0.message.tool_calls', []);
            $toolsPlan = [];
            foreach ($calls as $call) {
                $name = (string) ($call['function']['name'] ?? '');
                if ($name === '' || ! self::isReadTool($name)) {
                    continue;
                }
                $rawArgs = $call['function']['arguments'] ?? '{}';
                $args = is_string($rawArgs) ? json_decode($rawArgs, true) : (is_array($rawArgs) ? $rawArgs : []);
                if (! is_array($args)) {
                    $args = [];
                }
                $toolsPlan[] = [
                    'tool' => $name,
                    'args' => $this->dataAgent->sanitizeArgs($args),
                ];
            }

            if ($toolsPlan === []) {
                return null;
            }

            return [
                'tools' => array_slice($toolsPlan, 0, 4),
                'intent' => (string) ($toolsPlan[0]['tool'] ?? 'intelligent_query'),
                'clarification' => null,
                'wants_opinion' => true,
            ];
        } catch (\Throwable $e) {
            Log::warning('Unified intelligent planner failed', [
                'director_id' => $director->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  array<int,array<string,mixed>>  $actions
     * @param  array<string,mixed>  $context
     * @param  array<string,mixed>  $plan
     * @return array<string,mixed>
     */
    private function analyzeResults(User $director, string $text, array $actions, array $context, array $plan): array
    {
        $fallback = $this->deterministicAnalysis($actions, $text);
        if (app()->runningUnitTests()) {
            return $fallback;
        }
        $key = trim((string) config('services.openai.key'));
        if ($key === '' || str_contains($key, 'your_openai')) {
            return $fallback;
        }

        try {
            $response = Http::timeout(14)
                ->withToken($key)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => (string) config('services.openai.director_model', 'gpt-4o-mini'),
                    'temperature' => 0.2,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => "Analiza resultados académicos para un director.\n"
                                ."Devuelve JSON con: summary, key_patterns[], risks[], opportunities[], recommendations[{priority,action,reason}], follow_up_question.\n"
                                ."No inventes hechos. Distingue observación de hipótesis.",
                        ],
                        [
                            'role' => 'user',
                            'content' => json_encode([
                                'question' => $text,
                                'plan' => $plan,
                                'context' => $context,
                                'actions' => $actions,
                            ], JSON_UNESCAPED_UNICODE),
                        ],
                    ],
                ]);

            if ($response->failed()) {
                return $fallback;
            }

            $raw = (string) $response->json('choices.0.message.content', '');
            $decoded = json_decode($raw, true);
            if (! is_array($decoded)) {
                return $fallback;
            }

            return array_merge($fallback, [
                'summary' => is_string($decoded['summary'] ?? null) ? $decoded['summary'] : ($fallback['summary'] ?? ''),
                'key_patterns' => is_array($decoded['key_patterns'] ?? null) ? array_values($decoded['key_patterns']) : ($fallback['key_patterns'] ?? []),
                'risks' => is_array($decoded['risks'] ?? null) ? array_values($decoded['risks']) : ($fallback['risks'] ?? []),
                'opportunities' => is_array($decoded['opportunities'] ?? null) ? array_values($decoded['opportunities']) : ($fallback['opportunities'] ?? []),
                'recommendations' => is_array($decoded['recommendations'] ?? null) ? array_values($decoded['recommendations']) : ($fallback['recommendations'] ?? []),
                'follow_up_question' => is_string($decoded['follow_up_question'] ?? null) ? $decoded['follow_up_question'] : ($fallback['follow_up_question'] ?? null),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Unified intelligent analysis failed', [
                'director_id' => $director->id,
                'error' => $e->getMessage(),
            ]);

            return $fallback;
        }
    }

    /**
     * @param  array<string,mixed>  $analysis
     * @param  array<string,mixed>  $context
     * @return array{message:string,recommendations:array<int,mixed>}|null
     */
    private function generateResponse(User $director, string $text, array $analysis, array $context, string $fallbackMessage): ?array
    {
        if (app()->runningUnitTests()) {
            return null;
        }
        $key = trim((string) config('services.openai.key'));
        if ($key === '' || str_contains($key, 'your_openai')) {
            return null;
        }

        try {
            $response = Http::timeout(14)
                ->withToken($key)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => (string) config('services.openai.director_model', 'gpt-4o-mini'),
                    'temperature' => 0.35,
                    'messages' => [
                        ['role' => 'system', 'content' => $this->intelligentSystemPrompt()],
                        [
                            'role' => 'user',
                            'content' => json_encode([
                                'question' => $text,
                                'analysis' => $analysis,
                                'context' => $context,
                                'fallback' => $fallbackMessage,
                            ], JSON_UNESCAPED_UNICODE),
                        ],
                    ],
                ]);

            if ($response->failed()) {
                return null;
            }

            $content = trim((string) $response->json('choices.0.message.content', ''));
            if ($content === '') {
                return null;
            }

            return [
                'message' => $content,
                'recommendations' => is_array($analysis['recommendations'] ?? null)
                    ? array_values($analysis['recommendations'])
                    : [],
            ];
        } catch (\Throwable $e) {
            Log::warning('Unified intelligent generation failed', [
                'director_id' => $director->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function intelligentSystemPrompt(): string
    {
        return <<<PROMPT
Eres "AulaSync", asistente estratégico del director del colegio.

Piensa y responde en este orden:
1) Entiende la intención real de la pregunta.
2) Interpreta los datos y explica su impacto.
3) Conecta patrones (rendimiento, asistencia, tendencia, riesgo).
4) Prioriza acciones concretas con razón.
5) Conversa de forma natural y cierra con una pregunta breve cuando ayude.

Reglas:
- Nunca inventes alumnos, cursos, promedios, asistencia ni causas no respaldadas por datos.
- Si planteas una causa, declárala como hipótesis.
- Diferencia claramente entre dato observado y recomendación.
- No menciones tools, SQL, backend ni arquitectura.
- Mantén tono ejecutivo, cercano y accionable.
PROMPT;
    }

    /**
     * @param  array<int,array<string,mixed>>  $actions
     * @return array<string,mixed>
     */
    private function deterministicAnalysis(array $actions, string $text): array
    {
        $messages = collect($actions)
            ->pluck('message')
            ->filter(fn ($msg) => is_string($msg) && trim($msg) !== '')
            ->values()
            ->all();
        $riskCount = collect($actions)->sum(function ($action) {
            $data = (array) ($action['data'] ?? []);

            return (int) ($data['at_risk_count'] ?? 0);
        });

        $recommendations = [];
        if ($riskCount > 0) {
            $recommendations[] = [
                'priority' => 'high',
                'action' => 'Programar seguimiento con docentes y familias de casos críticos.',
                'reason' => "Se detectan {$riskCount} alumno(s) en riesgo en el análisis actual.",
            ];
        } elseif ($this->looksLikeActionRequest($text)) {
            $recommendations[] = [
                'priority' => 'medium',
                'action' => 'Definir un monitoreo semanal de notas y asistencia por curso.',
                'reason' => 'No hay alertas críticas, pero el seguimiento continuo evita rezagos.',
            ];
        }

        return [
            'summary' => $messages[0] ?? 'No hay suficientes datos para un análisis estratégico completo.',
            'key_patterns' => [],
            'risks' => $riskCount > 0 ? ["{$riskCount} alumno(s) con señales de riesgo académico."] : [],
            'opportunities' => $riskCount === 0 ? ['Escenario estable para fortalecer prevención y seguimiento.'] : [],
            'recommendations' => $recommendations,
            'follow_up_question' => $riskCount > 0
                ? '¿Quieres que te ordene los casos por urgencia y te proponga un plan semanal?'
                : '¿Quieres que profundice en un grado o sección específica?',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function safeToolData(User $director, string $tool, array $args): array
    {
        try {
            $result = $this->execute($director, $tool, $args);

            return (array) ($result['data'] ?? []);
        } catch (\Throwable) {
            return [];
        }
    }

    private function looksLikeActionRequest(string $text): bool
    {
        return (bool) preg_match('/\b(?:que debo hacer|priorizar|prioridad|recomienda|recomendacion)\b/u', mb_strtolower($text));
    }

    /**
     * @return array<string,mixed>
     */
    private function executeWrite(User $director, string $tool, array $data): array
    {
        return match ($tool) {
            'create_teacher' => $this->actionService->createTeacherInviteWithAssignments($director, $data),
            'create_course' => count($data['grades'] ?? []) > 1
                ? $this->actionService->createCourses($director, $data)
                : $this->actionService->createCourse($director, $data),
            'assign_teacher' => $this->actionService->assignTeacherToGradesSubject($director, $data),
            'create_students_batch' => $this->actionService->createStudentsBatch($director, $data),
            'enroll_students_course' => $this->actionService->enrollStudentsToCourse($director, $data),
            'unenroll_students_course' => $this->actionService->unenrollStudentsFromCourse($director, $data),
            'unassign_teacher' => $this->actionService->unassignTeacher($director, $data),
            'update_course' => $this->actionService->updateCourse($director, $data),
            'update_student' => $this->actionService->updateStudent($director, $data),
            'manage_invite_code' => $this->actionService->manageInviteCode($director, $data),
            'delete_teacher' => $this->actionService->deleteTeacher($director, $data),
            'delete_teacher_invite' => $this->actionService->deleteTeacherInvite($director, $data),
            'delete_all_teachers' => $this->actionService->deleteAllTeachers($director, $data),
            'delete_course' => $this->actionService->deleteCourse($director, $data),
            'delete_all_courses' => $this->actionService->deleteAllCourses($director, $data),
            'delete_student' => $this->actionService->deleteStudent($director, $data),
            default => throw ValidationException::withMessages([
                'intent' => 'Intent no soportado para Director AI.',
            ]),
        };
    }
}

<?php

namespace App\Http\Controllers\Director;

use App\Helpers\InviteCodeHelper;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Course;
use App\Models\DirectorAiOperationLog;
use App\Models\Evaluation;
use App\Models\Grade;
use App\Models\Student;
use App\Models\TeacherInvite;
use App\Models\User;
use App\Services\DirectorActionPlannerService;
use App\Services\DirectorActionService;
use App\Services\DirectorAIInterpreterService;
use App\Services\DirectorAnalyticsQueryService;
use App\Services\DirectorCommandFocusService;
use App\Services\DirectorConfirmationManager;
use App\Services\DirectorConversationContextService;
use App\Services\DirectorDataAgentService;
use App\Services\DirectorIntentExtractorService;
use App\Services\DirectorUnifiedAgentService;
use App\Services\PersonNameMatcher;
use App\Services\PersonNameSanitizer;
use App\Services\ProductTelemetry;
use App\Services\SpeechToTextService;
use App\Support\GradeLabel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AICommandController extends Controller
{
    private const GENERIC_SUBJECT_PATTERN = '/^(?:curso|cursos|clase|clases|materia|materias|asignatura|asignaturas)$/u';

    private const PENDING_SESSION_KEY = 'director_ai_pending_actions';

    private const PENDING_BATCH_SESSION_KEY = 'chat_pending_batch';

    private const PENDING_META_SESSION_KEY = 'director_ai_pending_meta';

    private const BATCH_QUEUE_KEY = 'director_ai_batch_queue';

    private const CHAT_MODE_KEY = 'chat_mode';

    private const CHAT_SUBJECT_KEY = 'chat_subject';

    private const CHAT_HISTORY_KEY = 'chat_history';

    /**
     * Correlación por request: enlaza director.ai.routing, director.ai.command_focus,
     * director.ai.planner_source y director.ai.handle_failed de un mismo mensaje.
     */
    private ?string $requestTraceId = null;

    /**
     * planner_source del request en curso, para persistirlo en el audit log
     * (hasta ahora solo existía en storage/logs y en algunas respuestas HTTP).
     */
    private ?string $requestPlannerSource = null;

    public function __construct(
        private DirectorActionService $actionService,
        private DirectorAIInterpreterService $interpreter,
        private DirectorConversationContextService $conversationContext,
        private DirectorConfirmationManager $confirmationManager,
        private DirectorAnalyticsQueryService $analytics,
        private DirectorDataAgentService $dataAgent,
        private SpeechToTextService $speechToText,
        private DirectorCommandFocusService $commandFocus,
        private DirectorUnifiedAgentService $unifiedAgent,
        private DirectorIntentExtractorService $intentExtractor,
        private DirectorActionPlannerService $actionPlanner,
    ) {}

    public function transcribe(Request $request): JsonResponse
    {
        $director = $request->user();
        if (! $director || $director->role !== 'director') {
            return response()->json([
                'success' => false,
                'error' => 'No autorizado.',
                'message' => 'Solo directores pueden enviar notas de voz.',
            ], 403);
        }

        $request->validate([
            'audio' => ['required', 'file', 'max:51200'],
            'process_command' => ['sometimes', 'boolean'],
            'conversation' => ['sometimes', 'array', 'max:40'],
            'conversation.*.role' => ['required_with:conversation', 'in:user,assistant'],
            'conversation.*.content' => ['required_with:conversation', 'string', 'max:12000'],
            'screen_context' => ['sometimes', 'nullable', 'array'],
        ]);

        $transcript = $this->speechToText->transcribe($request->file('audio'));

        if (! $request->boolean('process_command')) {
            return response()->json([
                'success' => true,
                'transcript' => $transcript,
            ]);
        }

        $proxy = Request::create('/director/ai/command', 'POST', [
            'prompt' => $transcript,
            'conversation' => (array) $request->input('conversation', []),
            'screen_context' => (array) $request->input('screen_context', []),
            'is_voice' => true,
        ]);
        $proxy->setUserResolver(fn () => $director);
        $proxy->setLaravelSession($request->session());

        $response = $this->handle($proxy);
        $payload = $response->getData(true);
        if (! is_array($payload)) {
            $payload = ['success' => false, 'message' => 'No se pudo procesar el comando transcrito.'];
        }
        $payload['transcript'] = $transcript;
        $payload['processed'] = true;

        return response()->json([
            ...$payload,
        ], $response->getStatusCode());
    }

    public function handle(Request $request): JsonResponse
    {
        $director = $request->user();
        if (! $director || $director->role !== 'director') {
            return response()->json([
                'success' => false,
                'error' => 'No autorizado.',
                'message' => 'Solo directores pueden usar este asistente.',
            ], 403);
        }

        $payload = $request->validate([
            'prompt' => ['nullable', 'string', 'max:8000'],
            'message' => ['nullable', 'string', 'max:8000'],
            'confirmed' => ['sometimes', 'boolean'],
            'pending_actions' => ['sometimes', 'array'],
            'pending_actions.*.intent' => ['required_with:pending_actions', 'string'],
            'pending_actions.*.data' => ['required_with:pending_actions', 'array'],
            'pending_actions.*.audit_log_id' => ['nullable', 'integer'],
            'conversation' => ['sometimes', 'array', 'max:40'],
            'conversation.*.role' => ['required_with:conversation', 'in:user,assistant'],
            'conversation.*.content' => ['required_with:conversation', 'string', 'max:12000'],
            'screen_context' => ['sometimes', 'nullable', 'array'],
            'payload' => ['sometimes', 'nullable', 'array'],
        ]);
        unset($payload['colegio_id'], $payload['school_id']);
        $screenContext = $this->dataAgent->sanitizeContext((array) (
            $payload['screen_context']
            ?? data_get($payload, 'payload.contexto')
            ?? []
        ));

        if ($request->boolean('confirmed')) {
            return $this->executePending($request, $director);
        }

        $this->requestTraceId = (string) Str::uuid();
        $this->requestPlannerSource = null;

        $text = trim((string) ($payload['prompt'] ?? $payload['message'] ?? ''));
        if ($text === '') {
            return response()->json([
                'success' => false,
                'message' => 'Escribe una instrucción. Ejemplo: "Crea al profesor Vicente Maduro y asígnale Inglés de 1ro a 6to".',
            ], 422);
        }

        // ── FASE HÍBRIDA: estados de sesión ──
        $buttonAction = $request->input('button_action');
        // Compatibilidad: algunos clientes envían buttonAction
        if (! $buttonAction && $request->has('buttonAction')) {
            $buttonAction = $request->input('buttonAction');
        }
        $chatMode = session()->get(self::CHAT_MODE_KEY, 'main_menu');
        $chatSubject = session()->get(self::CHAT_SUBJECT_KEY);
        $hasPendingPlan = $this->confirmationManager->hasPendingPlan();
        $hasPending = session()->has(self::PENDING_SESSION_KEY)
            || session()->has('chat_pending')
            || session()->has(self::PENDING_BATCH_SESSION_KEY);

        // PRIORIDAD 0: Plan pendiente con slots (antes de la confirmación final)
        if ($hasPendingPlan) {
            $slotReply = $this->handlePendingPlanSlots($director, $text);
            if ($slotReply !== null) {
                return $slotReply;
            }
        }

        // PRIORIDAD 1: Mutación pendiente (confirmación)
        if ($hasPending && $this->wantsOneByOneReview($text) && count(session(self::PENDING_SESSION_KEY, [])) > 1) {
            return $this->startOneByOneReview();
        }
        if ($hasPending && $this->isAffirmativeText($text)) {
            return $this->executePendingBatch($request, $director);
        }
        if ($hasPending && $this->isNegativeText($text)) {
            $this->forgetPendingActions();
            $this->conversationContext->clearPendingReferences();
            session()->put(self::CHAT_MODE_KEY, 'main_menu');
            session()->forget(self::CHAT_SUBJECT_KEY);

            return response()->json([
                'success' => true,
                'cancelled' => true,
                'message' => 'Operación cancelada. No hice cambios.',
                'buttons' => $this->mainMenuButtons(),
                'mode' => 'main_menu',
            ]);
        }

        // PRIORIDAD 2: Botón explícito
        if ($buttonAction && is_string($buttonAction) && $buttonAction !== '') {
            return $this->handleButton($buttonAction, $text, $director, $screenContext);
        }

        // PRIORIDAD 3: Texto libre + MODO específico (consulting/creating/etc.)
        if ($chatMode !== 'main_menu' && $chatSubject !== null) {
            $inModeResult = $this->handleInMode($text, $chatMode, $chatSubject, $director, $screenContext);
            if ($inModeResult !== null) {
                return $inModeResult;
            }
        }

        // PRIORIDAD 4: Texto libre en menú principal (detectar intención)
        $detected = $this->detectHybridIntent($text);
        if ($detected !== 'unknown' && $chatMode === 'main_menu') {
            session()->put(self::CHAT_MODE_KEY, $detected);
            // Si el texto ya es una consulta completa con datos, ir directo al handler sin pedir subject
            // Para consulting, cualquier pregunta específica con datos es completa
            $isCompleteQuery = mb_strlen(trim($text)) > 8;
            if ($detected === 'consulting' && $isCompleteQuery) {
                // Dejar que caiga al flujo normal (data agent)
            } elseif ($detected === 'reporting') {
                $plan = $this->dataAgent->plan($text, $screenContext);
                if (! empty($plan['tools'])) {
                    return $this->respondWithDataAgent($director, $text, $screenContext, null);
                }

                return $this->askForSubject($detected);
            } elseif ($detected !== 'unknown') {
                // Para creating/deleting/modifying con texto corto, pedir subject
                if (in_array($detected, ['creating', 'deleting', 'modifying'], true) && mb_strlen(trim($text)) < 20) {
                    return $this->askForSubject($detected);
                }
                // Para consulting con texto muy corto y sin datos, pedir subject
                if ($detected === 'consulting' && mb_strlen(trim($text)) < 15) {
                    return $this->askForSubject($detected);
                }
            }
        }

        $routeDecision = $this->dataAgent->routeDecision($text);
        Log::info('director.ai.routing', [
            'trace_id' => $this->requestTraceId,
            'planner_enabled' => $this->actionPlanner->enabled(),
            'path' => $request->path(),
            'method' => $request->method(),
            'route_name' => $request->route()?->getName(),
            'director_id' => $director->id,
            'colegio_id' => $director->colegio_id,
            'prompt' => mb_substr($text, 0, 240),
            'intention' => $routeDecision['reason'],
            'intent' => $routeDecision['intent'] ?? $routeDecision['reason'],
            'agent' => $routeDecision['agent'],
            'use_data_agent' => $routeDecision['use_data_agent'],
            'mutation' => $routeDecision['mutation'],
            'extracted_grade' => $routeDecision['extracted_grade'],
            'extracted_section' => $routeDecision['extracted_section'],
        ]);

        // Respuestas cortas de confirmación ("sí", "sí, créalos", "confirmo")
        // completan la acción pendiente guardada en sesión, sin bucle sin contexto.
        if ($this->isAffirmativeText($text) && session()->has(self::PENDING_SESSION_KEY)) {
            return $this->executePendingBatch($request, $director);
        }

        if ($this->isNegativeText($text) && session()->has(self::PENDING_SESSION_KEY)) {
            $this->forgetPendingActions();
            $this->conversationContext->clearPendingReferences();

            return response()->json([
                'success' => true,
                'cancelled' => true,
                'message' => 'Operación cancelada. No hice cambios.',
            ]);
        }

        // Consultas y follow-ups van SIEMPRE al data agent, salvo sincronización
        // masiva de matrículas (parece consulta de alumnos/cursos pero es mutación).
        if (
            ! $this->looksLikeEnrollmentSync($this->normalizedText($text))
            && ($this->dataAgent->shouldUseDataAgent($text) || $this->dataAgent->isOutOfScope($text))
        ) {
            return $this->respondWithDataAgent($director, $text, $screenContext, null);
        }

        try {
            $focus = $this->commandFocus->extract($text);
            $workingText = $focus['working'];

            // Instrumentación: el texto que llega al LLM es $workingText, no $text.
            // Si commandFocus recorta, las acciones anteriores al "cue" se pierden
            // antes de que el planificador vea la frase.
            $wasTruncated = $workingText !== $text;
            Log::info('director.ai.command_focus', [
                'trace_id' => $this->requestTraceId,
                'director_id' => $director->id,
                'truncated' => $wasTruncated,
                'original_len' => mb_strlen($text),
                'working_len' => mb_strlen($workingText),
                'dropped_chars' => max(0, mb_strlen($text) - mb_strlen($workingText)),
                'dropped_prefix' => $wasTruncated && str_ends_with($text, $workingText)
                    ? mb_substr($text, 0, mb_strlen($text) - mb_strlen($workingText))
                    : null,
                'original' => mb_substr($text, 0, 240),
                'working' => mb_substr($workingText, 0, 240),
            ]);

            $llmReply = '';
            $actions = [];
            $planSummary = null;
            $actionPlan = null;
            // Ambas variables se leen más abajo fuera de su rama de origen
            // ($interpreted en el fallback CRUD, $plan en prepareActions).
            // Sin inicializar, la rama del planificador provocaba un 500
            // ("Ocurrió un error al preparar la operación").
            $interpreted = null;
            $plan = null;

            // NUEVO: planificador multi-intención con Structured Outputs (solo si OpenAI está habilitado).
            if ($this->actionPlanner->enabled()) {
                $plan = $this->actionPlanner->plan(
                    $director,
                    $workingText,
                    [
                        'conversation' => (array) ($payload['conversation'] ?? []),
                        'memory' => $this->conversationContext->current(),
                        'screen_context' => $screenContext,
                        'raw_text' => $text,
                        'trace_id' => $this->requestTraceId,
                    ],
                );

                $this->requestPlannerSource = $plan['planner_source'] ?? null;

                // Si faltan slots, guardar plan y preguntar por el siguiente.
                if (($plan['status'] ?? '') === 'needs_info' || $this->planHasPendingSlots($plan)) {
                    $this->confirmationManager->startPlan($plan);

                    return $this->pendingSlotsResponse($plan);
                }

                // Convertir ActionPlan al formato de acciones existente.
                $actions = $this->planToActions($plan);
                $this->logSkippedActions($director, $plan, $text);
                $planSummary = $plan['summary'] ?? null;
                $actionPlan = $plan;

                // Honestidad del fallback: si el LLM falló y el extractor está
                // incierto ante texto complejo (múltiples verbos/entidades, conectores
                // como "adicional"/"además"), no presentar un plan adivinado con
                // confianza — pedir aclaración.
                // Solo pedimos aclaración cuando el plan quedó en UNA sola acción:
                // si ya hay 2+ acciones, colapsarlas a una pregunta genérica era
                // exactamente el bug de multi-acción (3 → 1). Con 2+ acciones
                // mostramos el plan y el director confirma.
                $isFallback = str_starts_with((string) ($plan['planner_source'] ?? ''), 'fallback_');
                if ($isFallback && count($actions) === 1 && $this->intentExtractor->isUncertainExtraction($text, collect($actions)->map(fn ($a) => ['intent' => $a['intent'], 'data' => $a['data']])->all())) {
                    return $this->forgetPendingAndClarify(
                        'Tu orden parece tener varias acciones y no estoy seguro de haber entendido todas correctamente ('.count($actions).' detectada(s)). ¿Podrías confirmar separando cada acción? Ejemplo: "1. Crea al profesor X de Biología de 1ro a 6to. 2. Agrega a Vicente José y Gabriela Pernal a 3ro."',
                        [
                            'planner_source' => $plan['planner_source'],
                            'action_plan' => $plan,
                        ],
                    );
                }

                // Fallback legado solo si el planificador no devolvió nada.
                if ($actions === []) {
                    $composite = $this->detectMultiIntentActions($director, $text);
                    if ($composite !== []) {
                        $actions = $this->enrichActionsFromText($composite, $text);
                    }
                    // Si el fallback sigue incierto tras el segundo intento, pedir aclaración.
                    // <= 1: cubre también el caso de 0 acciones utilizables para no dejar
                    // que un fallback posterior arme una confirmación con datos corruptos.
                    if (count($actions) <= 1 && $this->intentExtractor->isUncertainExtraction($text, $actions)) {
                        return $this->forgetPendingAndClarify(
                            'Detecté una orden con múltiples acciones pero no estoy seguro de la segmentación. ¿Podrías escribir cada acción en una línea separada? Ejemplo:\n1. Crea al profesor Vicente José de Biología de 1ro a 6to\n2. Agrega a Vicente José y Gabriela Pernal a 3ro',
                            [
                                'planner_source' => $plan['planner_source'] ?? 'fallback_uncertain',
                                'action_plan' => $plan,
                            ],
                        );
                    }
                }
            } else {
                // Flujo legado para entornos sin OpenAI (tests locales).
                // OJO: esta rama es la que ejecutan los tests; producción ejecuta la de arriba.
                $this->requestPlannerSource = 'legacy_interpreter';
                Log::info('director.ai.planner_source', [
                    'trace_id' => $this->requestTraceId,
                    'director_id' => $director->id,
                    'planner_source' => 'legacy_interpreter',
                    'reason' => 'planner_disabled_in_controller',
                    'environment' => app()->environment(),
                    'prompt_preview' => mb_substr($text, 0, 120),
                ]);

                $interpreted = $this->interpreter->interpret(
                    $director,
                    $focus['for_model'],
                    (array) ($payload['conversation'] ?? []),
                    $this->conversationContext->current(),
                );

                $actions = $this->enrichActionsFromText((array) ($interpreted['actions'] ?? []), $text);
                $localBatch = $this->extractMultipleActions($director, $text);
                $actions = $this->preferLocalTeacherBatch($actions, $localBatch, $text);
                // Si el LLM no devolvió nada pero el extractor local sí encontró
                // exactamente una acción clara, úsala: exigir >=2 para "preferir" el
                // batch local descartaba en silencio la única acción detectada
                // (0 acciones utilizables) cuando antes ese hueco quedaba
                // enmascarado por nombres fantasma que inflaban el conteo a 2.
                if ($actions === [] && count($localBatch) === 1) {
                    $actions = $localBatch;
                }

                $localIntentions = $this->mapLocalIntentions(
                    $this->intentExtractor->extractMultipleIntentions($text)
                );
                $actions = $this->preferLocalMultipleIntentions($actions, $localIntentions);
                // Único caso seguro para adoptar una sola intención local suelta:
                // creación de profesor sin ninguna mención de alumno/estudiante en el
                // texto. Con alumnos presentes, looksLikeTeacherCreation() puede
                // confundir "profesor" mencionado como referencia ("...con el
                // profesor X") con el sujeto a crear, así que en ese caso dejamos
                // que los fallbacks más específicos de más abajo (buildOperationData,
                // detectMultiIntentActions) resuelvan la intención real.
                if ($actions === [] && count($localIntentions) === 1) {
                    $onlyIntent = $localIntentions[0]['intent'] ?? null;
                    if ($onlyIntent === 'sync_all_enrollments') {
                        $actions = $localIntentions;
                    } elseif (
                        $onlyIntent === 'create_teacher'
                        && ! preg_match('/\b(?:alumn[oa]s?|estudiantes?)\b/iu', $text)
                    ) {
                        $actions = $localIntentions;
                    }
                }

                if ($actions !== [] && count($localBatch) < 2) {
                    $actions = $this->mergeMissingIntentsFromText($director, $actions, $text);
                }

                $llmReply = is_array($interpreted)
                    ? trim((string) ($interpreted['message'] ?? $interpreted['clarification'] ?? ''))
                    : '';

                // Honestidad en flujo legado sin LLM: si complejo e incierto, pedir aclaración.
                // <= 1: igual que en el planificador, cubre también 0 acciones utilizables
                // para no presentar luego una confirmación sobre un payload vacío/corrupto.
                if (count($actions) <= 1 && $this->intentExtractor->isUncertainExtraction($text, $actions)) {
                    return $this->forgetPendingAndClarify(
                        'Tu orden tiene varias acciones y no estoy seguro de haber captado todas ('.count($actions).' detectada(s)). ¿Podrías separar cada pedido en una línea? Ejemplo:\n1. Crea al profesor X de Biología de 1ro a 6to\n2. Agrega a Vicente José y Gabriela Pernal a 3ro',
                        ['planner_source' => 'fallback_uncertain'],
                    );
                }
            }

            if ($actions === []) {
                $contextualAction = $this->contextualFallbackAction($text);
                if ($contextualAction !== null) {
                    $actions = [$contextualAction];
                }
            }

            if ($actions === []) {
                $composite = $this->detectMultiIntentActions($director, $text);
                if ($composite !== []) {
                    $actions = $this->enrichActionsFromText($composite, $text);
                }
            }

            if ($actions === [] && $llmReply !== '') {
                $intentGuess = $this->detectIntent($text);
                $looksLikeWorkOrder = $this->dataAgent->looksLikeMutation($text)
                    || $this->looksLikeStaffingList($text)
                    || $this->looksLikeTeacherStaffing($text)
                    || $this->looksLikeCapabilityMenu($llmReply);
                // Si el director dio una orden, no devolver prosa de "qué puedo hacer".
                $trustLlmText = ! $looksLikeWorkOrder
                    && ! $this->looksLikeCapabilityMenu($llmReply)
                    && $intentGuess !== 'query_academic'
                    && ! $this->intentRequiresConfirmation((string) $intentGuess);
                if ($trustLlmText) {
                    return response()->json([
                        'success' => true,
                        'message' => $llmReply,
                    ]);
                }
                if ($intentGuess !== null && $intentGuess !== 'query_academic' && ! $looksLikeWorkOrder) {
                    $this->conversationContext->rememberError($llmReply);

                    return response()->json([
                        'success' => false,
                        'needs_clarification' => true,
                        'message' => $llmReply,
                    ], 422);
                }
            }

            if ($actions === []) {
                $intent = $this->detectIntent($text);
                if ($intent) {
                    [$operationData, $missingDataMessage] = $this->buildOperationData($director, $intent, $text);
                    if ($missingDataMessage) {
                        $slotResponse = $this->startPendingPlanFromMissingData($director, $intent, $text, $operationData, $missingDataMessage);
                        if ($slotResponse !== null) {
                            return $slotResponse;
                        }

                        return $this->forgetPendingAndClarify($missingDataMessage);
                    }
                    $actions = $this->enrichActionsFromText([['intent' => $intent, 'data' => $operationData]], $text);
                }
            }

            if ($actions === []) {
                if ($this->looksLikeTeacherStaffing($text)) {
                    [$staffingData, $staffingMsg] = $this->parseCreateTeacher($director, $text);
                    if (! $staffingMsg && ! empty($staffingData['teacher_name'])) {
                        $actions = $this->enrichActionsFromText([[
                            'intent' => 'create_teacher',
                            'data' => $staffingData,
                        ]], $text);
                    } elseif ($staffingMsg) {
                        return $this->forgetPendingAndClarify($staffingMsg);
                    }
                }
            }

            if ($actions === []) {
                // Red de seguridad: si el intérprete no armó mutación pero la
                // frase es una consulta académica, no devolver el menú CRUD.
                if ($this->dataAgent->looksLikeAcademicInquiry($text)) {
                    Log::warning('director.ai.routing_fallback_to_data_agent', [
                        'prompt' => mb_substr($text, 0, 240),
                        'decision' => $this->dataAgent->routeDecision($text),
                    ]);

                    return $this->respondWithDataAgent($director, $text, $screenContext, null);
                }

                $clarification = is_array($interpreted) ? ($interpreted['clarification'] ?? null) : null;
                if ($this->looksLikeCapabilityMenu(is_string($clarification) ? $clarification : null)) {
                    $clarification = null;
                }

                Log::warning('director.ai.crud_menu_fallback', [
                    'prompt' => mb_substr($text, 0, 240),
                    'decision' => $this->dataAgent->routeDecision($text),
                ]);

                return $this->forgetPendingAndClarify(
                    $clarification
                        ?: 'No entendí esa orden. Prueba con: "Crea al profesor Vicente y asígnale Matemática de 1ro a 6to".',
                    ['routing' => $this->dataAgent->routeDecision($text)],
                    200,
                );
            }

            if ($this->dataAgent->areExclusiveDataActions($actions)) {
                return $this->respondWithDataAgent($director, $text, $screenContext, $actions);
            }

            return $this->prepareActions(
                $director,
                $actions,
                $text,
                $this->usableConfirmationSummary($planSummary, $actions),
                $plan['all_or_nothing'] ?? false,
                $actionPlan
            );
        } catch (ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first() ?: 'No se pudo procesar la instrucción.';
            $this->conversationContext->rememberError($msg);

            return $this->forgetPendingAndClarify($msg, [], 422);
        } catch (\Throwable $e) {
            Log::error('Director AI handle failed', [
                'director_id' => $director->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al preparar la operación.',
            ], 500);
        }
    }

    /**
     * @param  array<int,array{intent:string,data:array}>|null  $preplanned
     */
    private function respondWithDataAgent(User $director, string $text, array $screenContext, ?array $preplanned = null): JsonResponse
    {
        try {
            $plan = $this->dataAgent->plan($text, $screenContext, $preplanned, $this->conversationContext->current());
            Log::info('director.ai.routing_executed', [
                'path' => request()->path(),
                'method' => request()->method(),
                'prompt' => mb_substr($text, 0, 240),
                'agent' => 'director_data',
                'intention' => $plan['intent'] ?? null,
                'tools' => array_column($plan['tools'] ?? [], 'tool'),
                'decision' => $this->dataAgent->routeDecision($text),
            ]);
            $result = $this->unifiedAgent->handleIntelligentQuery(
                $director,
                $text,
                $screenContext,
                $preplanned,
                fn (array $data) => $this->queryAcademic($director, $data),
                $this->conversationContext->current(),
            );
        } catch (\Throwable $e) {
            Log::error('Director data agent failed', [
                'director_id' => $director->id,
                'colegio_id' => $director->colegio_id,
                'error' => $e->getMessage(),
            ]);

            $message = $this->friendlyDataAgentFailure($e);
            $this->conversationContext->addTurn($text, $message, []);

            return response()->json([
                'success' => true,
                'any_success' => true,
                'actions' => [],
                'message' => $message,
                'agent' => 'director_data',
                'tools' => [],
            ]);
        }

        if (! empty($result['needs_clarification'])) {
            $this->conversationContext->rememberError((string) $result['message']);
            $this->conversationContext->addTurn($text, (string) $result['message'], []);

            return response()->json([
                'success' => false,
                'needs_clarification' => true,
                'message' => $result['message'],
                'duration_ms' => $result['duration_ms'] ?? null,
                'trace' => $result['trace'] ?? [],
                'timeline' => $result['timeline'] ?? [],
                'routing' => $this->dataAgent->routeDecision($text),
                'agent' => 'director_data',
            ]);
        }

        $this->conversationContext->rememberPlan(
            collect($result['actions'])->map(fn ($action) => [
                'intent' => $action['action_type'] ?? 'query_academic',
                'data' => $action['data'] ?? [],
            ])->all(),
            $text,
        );
        foreach ($result['actions'] as $action) {
            $this->conversationContext->rememberResult(
                (string) ($action['action_type'] ?? 'query_academic'),
                (array) ($action['data'] ?? []),
                $action,
            );
        }
        $this->conversationContext->rememberFocus((array) ($result['focus'] ?? []));
        $this->conversationContext->addTurn($text, (string) ($result['message'] ?? ''), (array) ($result['actions'] ?? []));

        return response()->json([
            'success' => true,
            'any_success' => true,
            'actions' => $result['actions'],
            'message' => $result['message'],
            'agent' => 'director_data',
            'tools' => $result['tools'],
            'duration_ms' => $result['duration_ms'] ?? null,
            'report_ready' => $result['report_ready'] ?? false,
            'trace' => $result['trace'] ?? [],
            'timeline' => $result['timeline'] ?? [],
            'routing' => $this->dataAgent->routeDecision($text),
        ]);
    }

    private function friendlyDataAgentFailure(\Throwable $e): string
    {
        $raw = mb_strtolower($e->getMessage());
        if (str_contains($raw, 'timeout') || str_contains($raw, 'timed out') || str_contains($raw, 'curl error 28')) {
            return 'La consulta tardó más de lo esperado. Inténtalo de nuevo en un momento.';
        }

        return 'No pude completar esa consulta ahora. Inténtalo de nuevo o formula la pregunta con un curso o un alumno concreto.';
    }

    /**
     * @param  array<int,array{intent:string,data:array}>  $actions
     */
    private function prepareActions(User $director, array $actions, string $rawText, ?string $summary = null, bool $allOrNothing = true, ?array $actionPlan = null): JsonResponse
    {
        $actions = $this->enrichActionsFromText($actions, $rawText);
        $hasStudentAction = collect($actions)->contains(
            fn ($action) => in_array($action['intent'] ?? '', ['create_students_batch', 'enroll_students_course'], true)
        );
        $prepared = [];
        foreach ($actions as $action) {
            $intent = (string) ($action['intent'] ?? '');
            $data = $this->hydrateActionData($director, $intent, (array) ($action['data'] ?? []));
            if ($intent === 'create_teacher'
                && $this->teacherClauseMentionsCourses($rawText)
                && ! $hasStudentAction
                && empty($data['subject_name'])) {
                throw ValidationException::withMessages([
                    'prompt' => 'Entendí que también quieres crear o asignar cursos. Dime la materia y los grados, por ejemplo: "asígnale Inglés de 1ro a 6to".',
                ]);
            }
            $data = $this->resolveDeleteTarget($director, $intent, $data);
            $this->validateActionReferences($director, $intent, $data);
            $prepared[] = [
                'intent' => $intent,
                'data' => $data,
                'action_plan_id' => $action['action_plan_id'] ?? null,
                'action_id' => $action['action_id'] ?? null,
                'planner_source' => $action['planner_source'] ?? null,
            ];
        }

        $this->conversationContext->rememberPlan($prepared, $rawText);
        $requiresConfirmation = collect($prepared)
            ->contains(fn ($action) => $this->intentRequiresConfirmation($action['intent']));

        if (! $requiresConfirmation) {
            $results = [];
            foreach ($prepared as $action) {
                $intent = $action['intent'];
                $data = $action['data'];
                $log = $this->createAuditLog(
                    $director,
                    $intent,
                    'received',
                    $rawText,
                    $data,
                    $action['action_plan_id'] ?? null,
                    $action['action_id'] ?? null,
                );
                $result = $this->runIntent($director, $intent, $data);
                $summary = $this->verifyResult($director, $intent, $result);
                $log->update([
                    'status' => 'verified',
                    'executed_at' => now(),
                    'verified_at' => now(),
                    'result_payload' => $summary,
                ]);
                $this->conversationContext->rememberResult($intent, $data, $summary);
                $results[] = [
                    'success' => true,
                    'action_type' => $intent,
                    'message' => $summary['message'] ?? 'Consulta completada.',
                    'data' => $summary['data'] ?? [],
                ];
            }

            $narrative = $this->interpreter->narrate($results);
            $this->conversationContext->addTurn($rawText, $narrative, $results);

            return response()->json([
                'success' => true,
                'any_success' => true,
                'actions' => $results,
                'message' => $narrative,
                'timeline' => $this->mutationTimeline('completed', 'Cambios aplicados correctamente.'),
            ]);
        }

        $pending = [];
        foreach ($prepared as $action) {
            $log = $this->createAuditLog(
                $director,
                $action['intent'],
                'pending_confirmation',
                $rawText,
                $action['data'],
                $action['action_plan_id'] ?? null,
                $action['action_id'] ?? null,
            );
            $pending[] = [
                'intent' => $action['intent'],
                'data' => $action['data'],
                'action_plan_id' => $action['action_plan_id'] ?? null,
                'action_id' => $action['action_id'] ?? null,
                'planner_source' => $action['planner_source'] ?? null,
                'audit_log_id' => $log->id,
            ];
        }

        $this->rememberPendingActions($pending, $allOrNothing);
        $usableSummary = $this->usableConfirmationSummary($summary, $pending);
        $confirmationMessage = $usableSummary ?? $this->formatPendingConfirmation($pending);
        $this->conversationContext->addTurn($rawText, $confirmationMessage, []);

        return $this->pendingConfirmationResponse($pending, null, $usableSummary, $actionPlan);
    }

    /**
     * @param  array<string,mixed>  $plan
     */
    private function logSkippedActions(User $director, array $plan, string $rawText): void
    {
        $planId = $plan['id'] ?? null;
        foreach ($plan['actions'] ?? [] as $action) {
            if (($action['status'] ?? '') !== 'skipped') {
                continue;
            }

            $this->createAuditLog(
                $director,
                (string) ($action['type'] ?? 'unknown'),
                'skipped',
                $rawText,
                (array) ($action['params'] ?? []),
                $planId,
                $action['id'] ?? null,
            );
        }
    }

    private function createAuditLog(
        User $director,
        string $intent,
        string $status,
        string $rawText,
        array $data,
        ?string $actionPlanId = null,
        ?string $actionId = null,
    ): DirectorAiOperationLog {
        Log::info('nova_ai_write', [
            'user_id' => $director->id,
            'school_id' => $director->colegio_id,
            'action' => $intent,
            'status' => $status,
            'timestamp' => now()->toIso8601String(),
        ]);

        app(ProductTelemetry::class)->record([
            'user' => $director,
            'source' => 'director_ai',
            'event' => 'ai_action',
            'action' => $intent,
            'status' => match ($status) {
                'failed' => 'failed',
                'verified', 'confirmed' => 'success',
                'pending_confirmation', 'received' => 'unresolved',
                default => $status,
            },
            'error_code' => $status === 'failed' ? $intent.'_failed' : null,
        ]);

        return DirectorAiOperationLog::create([
            'director_user_id' => $director->id,
            'colegio_id' => $director->colegio_id,
            'action_plan_id' => $actionPlanId,
            'action_id' => $actionId,
            'intent' => $intent,
            'status' => $status,
            'input_payload' => [
                'raw_text' => $rawText,
                'data' => $data,
            ],
        ]);
    }

    private function hydrateActionData(User $director, string $intent, array $data): array
    {
        if (in_array($intent, ['create_teacher', 'create_course', 'assign_teacher', 'unassign_teacher'], true)) {
            $data['grades'] = array_values(array_filter((array) ($data['grades'] ?? [])));
            if ($intent === 'create_course' && $data['grades'] !== []) {
                $data['grade'] = $data['grade'] ?? $data['grades'][0];
            }
            $data['missing_grades'] = $this->missingGradesFor($director, $data['grades']);
        }
        if ($intent === 'create_courses_batch') {
            $data['courses_data'] = $this->actionService->normalizeCoursesData($data['courses_data'] ?? []);
            $data['grades'] = collect($data['courses_data'])
                ->flatMap(fn (array $item) => $item['grades'])
                ->unique()
                ->values()
                ->all();
            $data['missing_grades'] = $this->missingGradesFor($director, $data['grades']);
        }
        if ($intent === 'create_teacher') {
            $data['subject_name'] = $data['subject_name'] ?? null;
            $data['expires_in_days'] = 30;
        }
        if ($intent === 'delete_all_teachers') {
            $data['count'] = User::query()
                ->where('colegio_id', $director->colegio_id)
                ->where('role', 'profesor')
                ->count();
        }
        if ($intent === 'delete_all_courses') {
            $data['count'] = Course::query()->where('colegio_id', $director->colegio_id)->count();
        }
        if ($intent === 'delete_course') {
            $subject = trim((string) ($data['subject_name'] ?? ''));
            $query = Course::query()
                ->where('colegio_id', $director->colegio_id)
                ->whereRaw('LOWER(subject_name) like ?', ['%'.rtrim(mb_strtolower($subject), 's').'%']);
            if (! empty($data['grade'])) {
                $query->whereRaw('LOWER(grade) = ?', [mb_strtolower((string) $data['grade'])]);
            }
            $data['match_count'] = $query->count();
        }

        return $data;
    }

    private function validateActionReferences(User $director, string $intent, array $data): void
    {
        if (in_array($intent, ['create_course', 'assign_teacher', 'unassign_teacher'], true) && ! empty($data['teacher_name'])) {
            $this->actionService->resolveAssigneeForDirector($director, (string) $data['teacher_name']);
        }

        if ($intent === 'create_courses_batch') {
            foreach ((array) ($data['courses_data'] ?? []) as $item) {
                $teacherName = is_array($item) ? trim((string) ($item['teacher_name'] ?? '')) : '';
                if ($teacherName !== '') {
                    $this->actionService->resolveAssigneeForDirector($director, $teacherName);
                }
            }
        }
    }

    /**
     * Resuelve el objetivo de eliminación antes de pedir confirmación.
     * Si hay ambigüedad, lanza ValidationException para que AulaSync pida aclaración.
     */
    private function resolveDeleteTarget(User $director, string $intent, array $data): array
    {
        $colegioId = (int) $director->colegio_id;
        $matcher = app(PersonNameMatcher::class);

        if ($intent === 'delete_teacher' && ! empty($data['teacher_name'])) {
            $name = (string) $data['teacher_name'];
            $match = $matcher->resolveTeacher($colegioId, $name);
            if ($match->isUnique()) {
                $data['teacher_name'] = $match->model->name;

                return $data;
            }
            if ($match->isNone()) {
                $inviteMatch = $matcher->resolveInvite($colegioId, $name);
                if ($inviteMatch->isUnique()) {
                    throw ValidationException::withMessages([
                        'teacher' => "No encontré a \"{$name}\" como profesor registrado. Sí hay una invitación pendiente: {$inviteMatch->label}. Para cancelarla escribe: \"Cancela la invitación del profesor {$inviteMatch->model->name}\".",
                    ]);
                }
                if ($inviteMatch->isAmbiguous()) {
                    throw ValidationException::withMessages([
                        'teacher' => "No encontré un profesor registrado con \"{$name}\", pero hay varias invitaciones pendientes.\n{$inviteMatch->message}",
                    ]);
                }
            }
            throw ValidationException::withMessages([
                'teacher' => $match->message ?? 'No encontré al profesor indicado en este colegio.',
            ]);
        }

        if ($intent === 'delete_teacher_invite' && ! empty($data['teacher_name'])) {
            $name = (string) $data['teacher_name'];
            $inviteMatch = $matcher->resolveInvite($colegioId, $name);
            if ($inviteMatch->isUnique()) {
                $data['teacher_name'] = $inviteMatch->model->name;
                $data['invite_id'] = $inviteMatch->model->id;
                $data['invite_code'] = $inviteMatch->model->invite_code;

                return $data;
            }
            if ($inviteMatch->isAmbiguous()) {
                throw ValidationException::withMessages([
                    'teacher' => $inviteMatch->message,
                ]);
            }

            $teacherMatch = $matcher->resolveTeacher($colegioId, $name);
            if ($teacherMatch->isUnique()) {
                throw ValidationException::withMessages([
                    'teacher' => "No hay una invitación pendiente para \"{$name}\" en este colegio. Encontré al profesor registrado {$teacherMatch->label}. Para eliminarlo escribe: \"Elimina al profesor {$teacherMatch->label}\".",
                ]);
            }
            if ($teacherMatch->isAmbiguous()) {
                throw ValidationException::withMessages([
                    'teacher' => "No hay una invitación pendiente para \"{$name}\" en este colegio, pero hay varios profesores registrados.\n{$teacherMatch->message}",
                ]);
            }

            throw ValidationException::withMessages([
                'teacher' => "No encontré una invitación pendiente ni un profesor registrado con \"{$name}\" en este colegio.",
            ]);
        }

        if ($intent === 'delete_student') {
            $batchNames = collect($data['names'] ?? [])
                ->map(fn ($name) => trim((string) $name))
                ->filter()
                ->unique()
                ->values();
            if ($batchNames->count() > 1) {
                unset($data['student_id']);
                $data['names'] = $batchNames->all();

                return $data;
            }

            $name = (string) ($data['student_name'] ?? $batchNames->first() ?? '');
            if ($name === '') {
                return $data;
            }
            $match = $matcher->resolveStudent($colegioId, $name);
            if (! $match->isUnique()) {
                throw ValidationException::withMessages([
                    'student' => $match->message ?? 'No encontré al alumno indicado en este colegio.',
                ]);
            }
            $data['student_name'] = $match->model->name;
            $data['student_id'] = $match->model->id;
        }

        return $data;
    }

    private function executePendingBatch(Request $request, User $director): JsonResponse
    {
        return $this->executePending($request, $director);
    }

    private function executePending(Request $request, User $director): JsonResponse
    {
        // The client copy is display-only. Execute only the canonical server-side plan.
        $actions = collect(session(self::PENDING_SESSION_KEY, []))
            ->filter(fn ($action) => is_array($action) && trim((string) ($action['intent'] ?? '')) !== '')
            ->values();
        if ($actions->isEmpty()) {
            $this->forgetPendingActions();

            return response()->json([
                'success' => false,
                'message' => 'No hay acciones pendientes por confirmar.',
            ], 422);
        }

        $meta = (array) session(self::PENDING_META_SESSION_KEY, []);
        $allOrNothing = (bool) ($meta['all_or_nothing'] ?? true);

        $logMap = [];
        foreach ($actions as $idx => $action) {
            $logId = Arr::get($action, 'audit_log_id');
            $log = $logId
                ? DirectorAiOperationLog::query()
                    ->where('id', $logId)
                    ->where('director_user_id', $director->id)
                    ->where('colegio_id', $director->colegio_id)
                    ->first()
                : null;
            if ($logId && ! $log) {
                throw ValidationException::withMessages([
                    'pending_actions' => 'La acción pendiente no pertenece a este director o colegio.',
                ]);
            }
            $logMap[(int) $idx] = $log ?? $this->createAuditLog(
                $director,
                (string) Arr::get($action, 'intent', 'unknown'),
                'pending_confirmation',
                (string) ($this->conversationContext->current()['last_user_text'] ?? ''),
                (array) Arr::get($action, 'data', []),
                $action['action_plan_id'] ?? null,
                $action['action_id'] ?? null,
            );
        }

        if ($allOrNothing) {
            return $this->executePendingAllOrNothing($actions, $logMap, $director);
        }

        return $this->executePendingBestEffort($actions, $logMap, $director);
    }

    /**
     * Ejecuta todas las acciones dentro de una sola transacción. Si una falla,
     * se revierte todo y se reporta el error.
     *
     * @param  Collection<int,array<string,mixed>>  $actions
     * @param  array<int,DirectorAiOperationLog|null>  $logMap
     */
    private function executePendingAllOrNothing(Collection $actions, array $logMap, User $director): JsonResponse
    {
        $results = [];

        try {
            DB::transaction(function () use ($actions, &$results, $logMap, $director): void {
                foreach ($actions as $idx => $action) {
                    $results[] = $this->runPendingAction($director, $action, $logMap[(int) $idx] ?? null);
                }
            });
        } catch (ValidationException $e) {
            return $this->handleExecutionFailure($actions, $logMap, $director, $e, 'validation');
        } catch (\Throwable $e) {
            return $this->handleExecutionFailure($actions, $logMap, $director, $e, 'exception');
        }

        return $this->finalizePendingExecution($results, $director);
    }

    /**
     * Ejecuta cada acción por separado y continúa aunque una falle. Las acciones
     * exitosas quedan confirmadas; las fallidas se reportan individualmente.
     *
     * @param  Collection<int,array<string,mixed>>  $actions
     * @param  array<int,DirectorAiOperationLog|null>  $logMap
     */
    private function executePendingBestEffort(Collection $actions, array $logMap, User $director): JsonResponse
    {
        $results = [];

        foreach ($actions as $idx => $action) {
            try {
                $results[] = $this->runPendingAction($director, $action, $logMap[(int) $idx] ?? null);
            } catch (ValidationException $e) {
                $msg = collect($e->errors())->flatten()->first() ?: 'Error de validación.';
                $results[] = $this->markActionFailed($action, $logMap[(int) $idx] ?? null, $msg);
            } catch (\Throwable $e) {
                Log::error('Director AI action failed (best-effort)', [
                    'director_id' => $director->id,
                    'intent' => $action['intent'] ?? '',
                    'message' => $e->getMessage(),
                ]);
                $results[] = $this->markActionFailed($action, $logMap[(int) $idx] ?? null, $e->getMessage());
            }
        }

        return $this->finalizePendingExecution($results, $director);
    }

    /**
     * @param  array<string,mixed>  $action
     */
    private function runPendingAction(User $director, array $action, ?DirectorAiOperationLog $log): array
    {
        $intent = (string) Arr::get($action, 'intent', '');
        $data = (array) Arr::get($action, 'data', []);

        if ($log) {
            $log->update([
                'status' => 'confirmed',
                'confirmed_at' => now(),
            ]);
        }

        $result = $this->runIntent($director, $intent, $data);
        $summary = $this->verifyResult($director, $intent, $result);

        if ($log) {
            $log->update([
                'status' => 'verified',
                'executed_at' => now(),
                'verified_at' => now(),
                'result_payload' => $summary,
            ]);
        }

        return [
            'success' => true,
            'action_type' => $intent,
            'message' => $summary['message'] ?? 'Operación ejecutada.',
            'data' => $summary['data'] ?? [],
        ];
    }

    /**
     * @param  array<string,mixed>  $action
     */
    private function markActionFailed(array $action, ?DirectorAiOperationLog $log, string $message): array
    {
        if ($log) {
            $log->update([
                'status' => 'failed',
                'executed_at' => now(),
                'error_payload' => ['message' => $message],
            ]);
        }

        return [
            'success' => false,
            'action_type' => $action['intent'] ?? 'unknown',
            'message' => $message,
            'data' => [],
        ];
    }

    /**
     * @param  Collection<int,array<string,mixed>>  $actions
     * @param  array<int,DirectorAiOperationLog|null>  $logMap
     */
    private function handleExecutionFailure(Collection $actions, array $logMap, User $director, \Throwable $e, string $type): JsonResponse
    {
        $isValidation = $type === 'validation';
        $msg = $isValidation
            ? (collect($e instanceof ValidationException ? $e->errors() : [])->flatten()->first() ?: 'Error de validación.')
            : $e->getMessage();

        foreach ($logMap as $log) {
            if ($log) {
                $log->update([
                    'status' => 'failed',
                    'executed_at' => now(),
                    'error_payload' => ['message' => $msg],
                ]);
            }
        }

        $this->conversationContext->rememberError($msg);
        session([self::PENDING_SESSION_KEY => $actions->all()]);

        $rollbackMessage = 'La operación falló y se revirtieron todos los cambios.'.($isValidation ? ' '.$msg : '');
        $this->conversationContext->addTurn(
            (string) ($this->conversationContext->current()['last_user_text'] ?? ''),
            $rollbackMessage,
            []
        );

        return response()->json([
            'success' => false,
            'any_success' => false,
            'requires_clarification' => true,
            'pending_actions' => $actions->all(),
            'actions' => [[
                'success' => false,
                'message' => $rollbackMessage,
                'action_type' => 'batch',
            ]],
            'message' => $rollbackMessage,
            'timeline' => $this->mutationTimeline('rollback', $rollbackMessage),
            'buttons' => $this->confirmationButtons($actions->count()),
        ], $isValidation ? 422 : 500);
    }

    /**
     * @param  array<int,array<string,mixed>>  $results
     */
    private function finalizePendingExecution(array $results, User $director): JsonResponse
    {
        foreach ($results as $row) {
            $this->conversationContext->rememberResult(
                (string) ($row['action_type'] ?? ''),
                (array) ($row['data'] ?? []),
                $row
            );
        }

        $anySuccess = collect($results)->contains(fn ($row) => ($row['success'] ?? false) === true);

        if ($results !== []) {
            $this->conversationContext->addTurn(
                (string) ($this->conversationContext->current()['last_user_text'] ?? ''),
                $this->formatBatchResults($results) ?? $this->interpreter->narrate($results),
                $results
            );
        }

        $queued = array_values(session(self::BATCH_QUEUE_KEY, []));
        if ($queued !== []) {
            $next = array_shift($queued);
            session([self::BATCH_QUEUE_KEY => $queued]);
            $this->rememberPendingActions([$next]);
            $remaining = count($queued) + 1;

            return $this->pendingConfirmationResponse(
                [$next],
                "Listo el paso anterior. Siguiente acción (queda {$remaining}):"
            );
        }
        $this->forgetPendingActions();

        $batchMessage = $this->formatBatchResults($results);

        return response()->json([
            'success' => $anySuccess,
            'any_success' => $anySuccess,
            'requires_clarification' => false,
            'pending_actions' => null,
            'actions' => $results,
            'message' => $batchMessage ?? $this->interpreter->narrate($results),
            'timeline' => $this->mutationTimeline(
                $anySuccess ? 'completed' : 'rollback',
                $anySuccess ? 'Cambios aplicados correctamente.' : 'No se pudo aplicar ningún cambio.'
            ),
            'buttons' => [['id' => 'menu_main', 'label' => '🏠 Menú principal']],
        ]);
    }

    /**
     * Único punto de ejecución de intents del Director AI: delega en
     * DirectorUnifiedAgentService, que a su vez despacha a
     * DirectorActionService (escritura) o DirectorDataAgentService
     * (lectura, incluida query_academic vía el callback legado).
     */
    /**
     * Único punto de ejecución de intents del Director AI: delega en
     * DirectorUnifiedAgentService, que a su vez despacha a
     * DirectorActionService (escritura) o DirectorDataAgentService
     * (lectura, incluida query_academic vía el callback legado).
     */
    private function runIntent(User $director, string $intent, array $data): array
    {
        if ($intent === 'create_subject') {
            return $this->actionService->createSubject($director, $data);
        }

        return $this->unifiedAgent->execute(
            $director,
            $intent,
            $data,
            fn (array $args) => $this->queryAcademic($director, $args),
        );
    }

    private function verifyResult(User $director, string $intent, array $result): array
    {
        return match ($intent) {
            'create_teacher' => $this->verifyCreateTeacher($director, $result),
            'create_subject' => [
                'message' => $result['message'] ?? 'Materia lista en el catálogo.',
                'data' => [
                    'subject_name' => $result['materia']->name ?? null,
                    'created' => (bool) ($result['created'] ?? false),
                ],
            ],
            'create_course' => isset($result['courses'])
                ? $this->verifyCreateCourses($director, $result)
                : $this->verifyCreateCourse($director, $result),
            'create_courses_batch' => $this->verifyCreateCoursesBatch($director, $result),
            'assign_teacher' => $this->verifyAssignTeacher($director, $result),
            'create_students_batch' => $this->verifyCreateStudentsBatch($director, $result),
            'enroll_students_course' => $this->verifyEnrollStudentsToCourse($director, $result),
            'unenroll_students_course' => $this->verifyUnenrollStudentsFromCourse($director, $result),
            'unassign_teacher', 'update_course', 'update_student' => $this->verifyGenericMutation($result),
            'get_teacher_invite_code' => $this->verifyGetTeacherInviteCode($result),
            'manage_invite_code' => $this->verifyManageInviteCode($director, $result),
            'sync_all_enrollments' => $this->verifySyncAllEnrollments($result),
            'query_academic' => $this->verifyAcademicQueryResult($result),
            'delete_teacher' => $this->verifyDeletePeople($director, $result, 'profesor'),
            'delete_teacher_invite' => $this->verifyDeleteInvite($result),
            'delete_all_teachers' => $this->verifyDeletePeople($director, $result, 'profesor'),
            'delete_course' => $this->verifyDeleteCourses($director, $result),
            'delete_all_courses' => $this->verifyDeleteCourses($director, $result),
            'delete_student' => $this->verifyDeletePeople($director, $result, 'alumno'),
            default => throw ValidationException::withMessages([
                'intent' => 'No se pudo verificar el resultado.',
            ]),
        };
    }

    private function verifyCreateCourses(User $director, array $result): array
    {
        /** @var Collection<int,Course> $courses */
        $courses = $result['courses'];
        foreach ($courses as $course) {
            if ((int) $course->colegio_id !== (int) $director->colegio_id) {
                throw ValidationException::withMessages([
                    'course' => 'Un curso creado no pertenece al colegio del director.',
                ]);
            }
        }

        $labels = $courses->map(fn ($c) => "{$c->subject_name} {$c->grade}".($c->section ? " sección {$c->section}" : ''))->implode(', ');
        $created = (int) ($result['created_count'] ?? 0);
        $existing = (int) ($result['existing_count'] ?? 0);
        $parts = [];
        if ($created > 0) {
            $parts[] = "{$created} curso(s) creado(s)";
        }
        if ($existing > 0) {
            $parts[] = "{$existing} ya existente(s) y actualizado(s)";
        }
        $teacherText = $result['teacher_label'] ? " asignado a {$result['teacher_label']}" : '';

        return [
            'message' => 'Cursos listos: '.$labels.'.'.($parts !== [] ? ' '.implode(', ', $parts).'.' : '').$teacherText.'.',
            'data' => [
                'courses' => $courses->map(fn ($c) => [
                    'course_id' => $c->id,
                    'subject_name' => $c->subject_name,
                    'grade' => $c->grade,
                    'section' => $c->section,
                    'students_count' => $c->students_count,
                ])->values()->all(),
                'created_count' => $created,
                'existing_count' => $existing,
            ],
        ];
    }

    /**
     * @param  array<string,mixed>  $result
     * @return array{message:string,data:array<string,mixed>}
     */
    private function verifyCreateCoursesBatch(User $director, array $result): array
    {
        /** @var Collection<int,Course> $courses */
        $courses = $result['courses'];
        foreach ($courses as $course) {
            if ((int) $course->colegio_id !== (int) $director->colegio_id) {
                throw ValidationException::withMessages([
                    'course' => 'Un curso creado no pertenece al colegio del director.',
                ]);
            }
        }

        $bySubject = $courses->groupBy('subject_name')
            ->map(fn ($group, $subject) => $subject.' ('.$group->pluck('grade')->unique()->values()->implode(', ').')')
            ->values()
            ->implode('; ');

        $created = (int) ($result['created_count'] ?? 0);
        $existing = (int) ($result['existing_count'] ?? 0);
        $labels = array_values((array) ($result['teacher_labels'] ?? []));

        $parts = [];
        if ($created > 0) {
            $parts[] = "{$created} creado(s)";
        }
        if ($existing > 0) {
            $parts[] = "{$existing} ya existía(n) y quedó(aron) actualizado(s)";
        }

        $message = 'Cursos listos: '.$bySubject.'.'
            .($parts !== [] ? ' '.implode(', ', $parts).'.' : '')
            .($labels !== []
                ? ' Docente(s): '.implode(', ', $labels).'.'
                : ' Quedaron sin docente asignado; puedes asignarlo cuando quieras.');

        return [
            'message' => $message,
            'data' => [
                'courses' => $courses->map(fn (Course $course) => [
                    'course_id' => $course->id,
                    'subject_name' => $course->subject_name,
                    'grade' => $course->grade,
                    'section' => $course->section,
                    'teacher_id' => $course->teacher_id,
                    'students_count' => $course->students_count,
                ])->values()->all(),
                'created_count' => $created,
                'existing_count' => $existing,
                'subjects_count' => (int) ($result['subjects_count'] ?? 0),
                'teacher_labels' => $labels,
            ],
        ];
    }

    private function verifyCreateTeacher(User $director, array $result): array
    {
        /** @var TeacherInvite $invite */
        $invite = $result['invite'];
        if ((int) $invite->colegio_id !== (int) $director->colegio_id) {
            throw ValidationException::withMessages([
                'invite' => 'La invitación creada no pertenece al colegio del director.',
            ]);
        }

        /** @var Collection<int,Course> $courses */
        $courses = $result['courses'];
        $invitation = $result['invitation'] ?? null;
        $invite->loadMissing('colegio');
        $link = $invitation?->acceptUrl() ?: $invite->shareableLink();
        $mailSent = (bool) ($result['mail_sent'] ?? false);
        $message = "Profesor {$invite->name} creado exitosamente. Código {$invite->invite_code}.";
        if ($mailSent && $invite->email) {
            $message .= " Se envió un email de invitación a {$invite->email}.";
        }
        if ($link) {
            $message .= " Link: {$link}";
        }
        if (! empty($result['invitation_warning'])) {
            $message .= ' '.$result['invitation_warning'];
        }

        return [
            'message' => $message,
            'data' => [
                'invite_code' => $invite->invite_code,
                'teacher_name' => $invite->name,
                'invite_id' => $invite->id,
                'email' => $invite->email,
                'invitation_link' => $link,
                'mail_sent' => $mailSent,
                'status' => 'invitado',
                'subject_name' => $courses->first()?->subject_name,
                'grades' => $courses->pluck('grade')->unique()->values()->all(),
                'courses' => $courses->map(fn ($course) => [
                    'course_id' => $course->id,
                    'subject_name' => $course->subject_name,
                    'grade' => $course->grade,
                    'section' => $course->section,
                    'students_count' => $course->students_count,
                ])->values()->all(),
            ],
        ];
    }

    private function verifyCreateCourse(User $director, array $result): array
    {
        /** @var Course $course */
        $course = $result['course'];
        if ((int) $course->colegio_id !== (int) $director->colegio_id) {
            throw ValidationException::withMessages([
                'course' => 'El curso creado no pertenece al colegio del director.',
            ]);
        }

        $action = $result['was_existing'] ? 'Curso actualizado' : 'Curso creado';
        $teacherText = $result['teacher_label'] ? " asignado a {$result['teacher_label']}" : '';

        return [
            'message' => "{$action}: {$course->subject_name} {$course->grade}".($course->section ? " sección {$course->section}" : '')."{$teacherText}.",
            'data' => [
                'course_id' => $course->id,
                'subject_name' => $course->subject_name,
                'grade' => $course->grade,
                'section' => $course->section,
                'invite_code' => $course->invite_code,
                'students_count' => $course->students_count,
            ],
        ];
    }

    private function verifyAssignTeacher(User $director, array $result): array
    {
        /** @var Collection<int,Course> $courses */
        $courses = $result['courses'];
        foreach ($courses as $course) {
            if ((int) $course->colegio_id !== (int) $director->colegio_id) {
                throw ValidationException::withMessages([
                    'assignment' => 'Una asignación quedó fuera del colegio del director.',
                ]);
            }
        }

        return [
            'message' => "Asignación completada para {$result['teacher_label']}.",
            'data' => [
                'teacher' => $result['teacher_label'],
                'courses' => $courses->map(fn ($course) => [
                    'course_id' => $course->id,
                    'subject_name' => $course->subject_name,
                    'grade' => $course->grade,
                    'section' => $course->section,
                    'students_count' => $course->students_count,
                ])->values()->all(),
            ],
        ];
    }

    private function verifyCreateStudentsBatch(User $director, array $result): array
    {
        /** @var Collection<int,Student> $created */
        $created = $result['created'];
        foreach ($created as $student) {
            if ((int) $student->colegio_id !== (int) $director->colegio_id) {
                throw ValidationException::withMessages([
                    'students' => 'Un estudiante creado no pertenece al colegio del director.',
                ]);
            }
        }

        $courseGroups = (array) ($result['courses'] ?? []);
        foreach ($courseGroups as $group) {
            $course = $group['course'] ?? null;
            if ($course && (int) $course->colegio_id !== (int) $director->colegio_id) {
                throw ValidationException::withMessages([
                    'students' => 'Un curso de matrícula no pertenece al colegio del director.',
                ]);
            }
        }

        return [
            'message' => $this->composeCreateStudentsVerifiedMessage($created, $result),
            'data' => [
                'students' => $created->map(fn ($student) => [
                    'student_id' => $student->id,
                    'name' => $student->name,
                    'grade' => $student->grade,
                    'section' => $student->section,
                    'family_code' => $student->family_code,
                ])->values()->all(),
                'duplicates' => $result['duplicates'],
                'courses' => collect($courseGroups)->map(fn (array $group) => [
                    'grade' => $group['grade'],
                    'section' => $group['section'],
                    'course_id' => $group['course']->id ?? null,
                    'enrolled_count' => (int) ($group['enrolled_count'] ?? 0),
                    'course_created' => (bool) ($group['course_created'] ?? false),
                ])->values()->all(),
            ],
        ];
    }

    private function composeCreateStudentsVerifiedMessage(Collection $created, array $result): string
    {
        $count = $created->count();
        $message = "Creé {$count} estudiante(s) correctamente.";

        $courseGroups = collect((array) ($result['courses'] ?? []));
        $withCourse = $courseGroups->filter(fn (array $group) => ($group['course'] ?? null) !== null);

        if ($withCourse->isNotEmpty()) {
            $parts = $withCourse->map(function (array $group) {
                /** @var Course $course */
                $course = $group['course'];
                $teacher = $course->teacher?->name;
                $place = $course->subject_name.' '.$course->grade.($course->section ? ' / '.$course->section : '');
                $enrolled = (int) ($group['enrolled_count'] ?? 0);
                $total = (int) $course->students()->count();
                $created = ! empty($group['course_created']) ? ' (curso nuevo)' : '';

                return $enrolled > 0
                    ? "quedó(aron) matriculado(s) en {$place}{$created}".($teacher ? " con {$teacher}" : '').". El curso tiene {$total} alumno(s)"
                    : "todavía no pude matricularlos en {$place}{$created}";
            });
            $message .= ' '.$parts->implode('; ').'.';
        } else {
            $notes = $courseGroups->pluck('placement_note')->filter()->unique()->implode(' ');
            if ($notes !== '') {
                $message .= ' '.$notes;
            }
        }

        return $message;
    }

    private function verifyEnrollStudentsToCourse(User $director, array $result): array
    {
        /** @var Course $course */
        $course = $result['course'];
        if ((int) $course->colegio_id !== (int) $director->colegio_id) {
            throw ValidationException::withMessages([
                'enroll' => 'La inscripción resultó fuera del colegio del director.',
            ]);
        }

        return [
            'message' => 'Inscripción verificada en '.$course->subject_name.' '.$course->grade.'.',
            'data' => [
                'course_id' => $course->id,
                'course' => $course->subject_name.' '.$course->grade.($course->section ? ' sección '.$course->section : ''),
                'enrolled' => $result['enrolled'],
                'already_enrolled' => $result['already_enrolled'],
                'missing_students' => $result['missing_students'],
                'total_students_in_course' => $result['total_students_in_course'],
            ],
        ];
    }

    private function verifyUnenrollStudentsFromCourse(User $director, array $result): array
    {
        /** @var Course $course */
        $course = $result['course'];
        if ((int) $course->colegio_id !== (int) $director->colegio_id) {
            throw ValidationException::withMessages([
                'unenroll' => 'La desmatriculación resultó fuera del colegio del director.',
            ]);
        }

        return [
            'message' => 'Desmatriculé '.count($result['unenrolled']).' alumno(s) de '.$course->subject_name.' '.$course->grade.'.',
            'data' => [
                'course_id' => $course->id,
                'unenrolled' => $result['unenrolled'],
                'missing_students' => $result['missing_students'],
                'total_students_in_course' => $result['total_students_in_course'],
            ],
        ];
    }

    private function verifySyncAllEnrollments(array $result): array
    {
        $created = (int) ($result['links_created'] ?? 0);
        $already = (int) ($result['already_synced'] ?? 0);
        $students = (int) ($result['students_count'] ?? 0);
        $courses = (int) ($result['courses_count'] ?? 0);

        $message = $created > 0
            ? "Sincronicé {$created} matrícula(s) nueva(s) entre {$students} alumno(s) y {$courses} curso(s)."
            : "Las matrículas ya estaban al día ({$students} alumno(s), {$courses} curso(s)).";
        if ($already > 0 && $created > 0) {
            $message .= " {$already} vínculo(s) ya existían.";
        }

        return [
            'message' => $message,
            'data' => [
                'links_created' => $created,
                'already_synced' => $already,
                'students_count' => $students,
                'courses_count' => $courses,
            ],
        ];
    }

    private function verifyManageInviteCode(User $director, array $result): array
    {
        /** @var TeacherInvite $invite */
        $invite = $result['invite'];
        if ((int) $invite->colegio_id !== (int) $director->colegio_id) {
            throw ValidationException::withMessages([
                'invite' => 'La invitación no pertenece al colegio del director.',
            ]);
        }

        $message = "Código DOC- activo: {$invite->invite_code}.";

        return [
            'message' => $message,
            'data' => [
                'invite_code' => $invite->invite_code,
                'teacher_name' => $invite->name,
                'revoked_at' => optional($invite->revoked_at)->toISOString(),
                'expires_at' => optional($invite->expires_at)->toISOString(),
                'claimed_at' => optional($invite->claimed_at)->toISOString(),
            ],
        ];
    }

    private function verifyGetTeacherInviteCode(array $result): array
    {
        return [
            'message' => (string) ($result['message'] ?? 'No pude obtener el código de invitación.'),
            'data' => (array) ($result['data'] ?? []),
        ];
    }

    private function verifyAcademicQueryResult(array $result): array
    {
        return [
            'message' => $result['message'] ?? 'Consulta completada.',
            'data' => $result['data'] ?? [],
        ];
    }

    private function verifyGenericMutation(array $result): array
    {
        return [
            'message' => $result['message'] ?? 'Cambio aplicado correctamente.',
            'data' => $result['data'] ?? [],
        ];
    }

    /**
     * @param  array{deleted_count?:int, deleted_names?:array<int,string>}  $result
     */
    private function verifyDeletePeople(User $director, array $result, string $label): array
    {
        $count = (int) ($result['deleted_count'] ?? 0);
        $names = collect($result['deleted_names'] ?? [])->filter()->values();
        $suffix = $names->isNotEmpty() ? ' '.$names->implode(', ').'.' : '.';

        return [
            'message' => $count === 1
                ? "Eliminé 1 {$label} correctamente.".$suffix
                : "Eliminé {$count} {$label}(es) correctamente.".$suffix,
            'data' => [
                'deleted_count' => $count,
                'deleted_names' => $names->all(),
                'colegio_id' => $director->colegio_id,
            ],
        ];
    }

    /**
     * @param  array{deleted_count?:int, deleted_invites?:int, invite_label?:string, invite_code?:string}  $result
     */
    private function verifyDeleteInvite(array $result): array
    {
        $label = trim((string) ($result['invite_label'] ?? ''));
        $code = trim((string) ($result['invite_code'] ?? ''));
        $message = $label !== ''
            ? "Cancelé la invitación pendiente de {$label}."
            : 'Cancelé la invitación pendiente.';
        if ($code !== '') {
            $message .= " El código DOC- {$code} ya no es válido.";
        }
        $message .= ' No se eliminó ningún profesor registrado.';

        return [
            'message' => $message,
            'data' => [
                'invite_name' => $label,
                'invite_code' => $code,
                'deleted_count' => (int) ($result['deleted_count'] ?? 1),
                'deleted_invites' => (int) ($result['deleted_invites'] ?? 1),
            ],
        ];
    }

    /**
     * @param  array{deleted_count?:int, deleted_courses?:array<int,array>}  $result
     */
    private function verifyDeleteCourses(User $director, array $result): array
    {
        $count = (int) ($result['deleted_count'] ?? 0);
        $courses = collect($result['deleted_courses'] ?? []);
        $labels = $courses->map(function ($course) {
            $section = ! empty($course['section']) ? ' sección '.$course['section'] : '';

            return trim(($course['subject_name'] ?? '').' '.($course['grade'] ?? '').$section);
        })->filter()->implode(', ');

        return [
            'message' => $count === 0
                ? 'No había cursos para eliminar.'
                : "Eliminé {$count} curso(s)".($labels !== '' ? ': '.$labels : '').'.',
            'data' => [
                'deleted_count' => $count,
                'deleted_courses' => $courses->all(),
                'colegio_id' => $director->colegio_id,
            ],
        ];
    }

    /**
     * @return array{0:array,1:?string}
     */
    private function buildOperationData(User $director, string $intent, string $text): array
    {
        return match ($intent) {
            'create_teacher' => $this->parseCreateTeacher($director, $text),
            'create_subject' => $this->parseCreateSubject($text),
            'create_course' => $this->parseCreateCourse($director, $text),
            'assign_teacher' => $this->parseAssignTeacher($director, $text),
            'create_students_batch' => $this->parseCreateStudentsBatch($director, $text),
            'enroll_students_course' => $this->parseEnrollStudentsCourse($director, $text),
            'unenroll_students_course' => $this->parseEnrollStudentsCourse($director, $text),
            'update_student' => $this->parseUpdateStudent($director, $text),
            'get_teacher_invite_code' => $this->parseGetTeacherInviteCode($text),
            'manage_invite_code' => $this->parseManageInviteCode($text),
            'query_academic' => $this->parseQueryAcademic($text),
            'delete_teacher' => $this->parseDeleteTeacher($director, $text),
            'delete_teacher_invite' => $this->parseDeleteInvite($director, $text),
            'delete_all_teachers' => $this->parseDeleteAllTeachers($director),
            'delete_course' => $this->parseDeleteCourse($director, $text),
            'delete_all_courses' => $this->parseDeleteAllCourses($director),
            'delete_student' => $this->parseDeleteStudent($director, $text),
            'sync_all_enrollments' => [[], null],
            default => [[], 'No pude convertir tu solicitud en una operación segura.'],
        };
    }

    private function confirmationMessageFor(string $intent, array $data): string
    {
        $createdGrades = $this->mentionMissingGrades($intent, $data);
        $createdGrades = $createdGrades !== '' ? ' '.$createdGrades : '';

        $message = match ($intent) {
            'create_teacher' => $this->summarizeCreateTeacher($data),
            'create_subject' => 'Agregar al catálogo la materia '.($data['subject_name'] ?? '').'.',
            'create_course' => count($data['grades'] ?? []) > 1
                ? 'Crear los cursos de '.$data['subject_name'].' para '.implode(', ', $data['grades']).(($data['section'] ?? null) ? " sección {$data['section']}" : '').'.'
                : "Crear el curso {$data['subject_name']} para {$data['grade']}".(($data['section'] ?? null) ? " sección {$data['section']}" : '').'.',
            'create_courses_batch' => $this->summarizeCreateCoursesBatch($data),
            'assign_teacher' => "Asignar a {$data['teacher_name']} la materia {$data['subject_name']} en ".$this->formatGradeSpan((array) ($data['grades'] ?? [])).'.',
            'create_students_batch' => $this->summarizeCreateStudents($data),
            'enroll_students_course' => ! empty($data['all_in_grade'])
                ? 'Inscribir a los alumnos de '.$data['grade'].(($data['section'] ?? null) ? " sección {$data['section']}" : '')." en {$data['subject_name']}".(! empty($data['teacher_name']) ? " con {$data['teacher_name']}" : '').'.'
                : 'Inscribir '.implode(', ', (array) ($data['names'] ?? []))." en {$data['subject_name']} {$data['grade']}".(($data['section'] ?? null) ? " sección {$data['section']}" : '').'.',
            'unenroll_students_course' => 'Desmatricular '.count($data['names'] ?? [])." alumno(s) de {$data['subject_name']} {$data['grade']}".(($data['section'] ?? null) ? " sección {$data['section']}" : '').'.',
            'unassign_teacher' => 'Desasignar los cursos indicados de '.($data['teacher_name'] ?? 'ese profesor').'.',
            'update_course' => 'Modificar el curso '.($data['subject_name'] ?? '').' '.($data['grade'] ?? '').'.',
            'update_student' => 'Mover / actualizar a '.($data['student_name'] ?? 'ese alumno')
                .(! empty($data['new_grade']) ? ' a '.$data['new_grade'] : '')
                .(! empty($data['new_section']) ? ' sección '.$data['new_section'] : '').'.',
            'manage_invite_code' => 'Consultar el estado del código DOC-.',
            'sync_all_enrollments' => 'Sincronizar las matrículas: agregar a cada alumno a los cursos disponibles de su mismo grado.',
            'delete_teacher' => 'Eliminar al profesor '.($data['teacher_name'] ?? '').'. Los cursos se desasignarán, no se borrarán.',
            'delete_teacher_invite' => 'Cancelar la invitación pendiente de '.($data['teacher_name'] ?? '').
                (($data['invite_code'] ?? null) ? ' (código DOC- '.$data['invite_code'].')' : '').
                '. No se eliminará ningún profesor registrado.',
            'delete_all_teachers' => 'Eliminar a '.((int) ($data['count'] ?? 0)).' profesor(es) de tu colegio. Los cursos se desasignarán, no se borrarán.',
            'delete_course' => 'Eliminar '.((int) ($data['match_count'] ?? 0)).' curso(s) de '.($data['subject_name'] ?? 'la asignatura indicada').
                (($data['grade'] ?? null) ? ' '.$data['grade'] : '').'.',
            'delete_all_courses' => 'Eliminar todos los cursos del colegio ('.((int) ($data['count'] ?? 0)).').',
            'delete_student' => ! empty($data['names']) && count((array) $data['names']) > 1
                ? 'Eliminar a '.count((array) $data['names']).' alumnos: '.implode(', ', (array) $data['names']).'.'
                : 'Eliminar al alumno '.($data['student_name'] ?? implode(', ', (array) ($data['names'] ?? []))).'.',
            default => 'Confirmar la operación.',
        };

        return $message.$createdGrades;
    }

    /**
     * Resumen de confirmación para create_courses_batch: una línea por materia
     * y una nota explícita cuando ningún curso lleva docente.
     *
     * @param  array<string,mixed>  $data
     */
    private function summarizeCreateCoursesBatch(array $data): string
    {
        $items = array_values(array_filter((array) ($data['courses_data'] ?? []), 'is_array'));
        $total = 0;
        $lines = [];
        $assigned = [];

        foreach ($items as $item) {
            $grades = array_values(array_filter((array) ($item['grades'] ?? [])));
            $total += count($grades);
            $subject = trim((string) ($item['subject_name'] ?? ''));
            $section = trim((string) ($item['section'] ?? ''));
            $teacher = trim((string) ($item['teacher_name'] ?? ''));

            if ($teacher !== '') {
                $assigned[$teacher] = true;
            }

            $lines[] = '- '.$subject.': '.implode(', ', $grades)
                .($section !== '' ? ' (sección '.$section.')' : '')
                .($teacher !== '' ? ' — docente '.$teacher : '');
        }

        if ($lines === []) {
            return 'Crear los cursos indicados.';
        }

        return 'Voy a crear '.$total.' curso(s):'."\n".implode("\n", $lines)
            .($assigned === [] ? "\nSin docente asignado (puedes asignarlo después)." : '');
    }

    /**
     * @param  array<int,array{intent:string,data:array,audit_log_id?:int}>  $pending
     */
    private function pendingConfirmationResponse(array $pending, ?string $prefix = null, ?string $message = null, ?array $actionPlan = null): JsonResponse
    {
        if ($pending === []) {
            return $this->forgetPendingAndClarify('No identifiqué acciones en tu mensaje.');
        }

        $message = $this->usableConfirmationSummary($message, $pending);
        if ($message !== null && $message !== '') {
            if (! str_contains($message, '¿Confirmas')) {
                $message .= "\n\n¿Confirmas que quieres ejecutar estas acciones?";
            }
        } else {
            $message = $this->formatPendingConfirmation($pending);
            if ($prefix !== null && $prefix !== '') {
                $message = $prefix."\n\n".$message;
            }
        }

        $queued = count(session(self::BATCH_QUEUE_KEY, []));
        $response = [
            'success' => true,
            'requires_confirmation' => true,
            'message' => $message,
            'pending_actions' => $pending,
            'timeline' => $this->mutationTimeline('confirming', 'Esperando tu confirmación para ejecutar.'),
            'buttons' => $this->confirmationButtons(count($pending) + $queued),
        ];

        if ($actionPlan !== null) {
            $response['action_plan'] = $actionPlan;
            $response['planner_source'] = $actionPlan['planner_source'] ?? null;
        }

        return response()->json($response);
    }

    /**
     * Un resumen del planner tipo "no identifiqué acciones" no puede confirmar
     * un plan que sí tiene acciones (el fallback ya las armó).
     *
     * @param  array<int,mixed>  $items
     */
    private function usableConfirmationSummary(?string $summary, array $items): ?string
    {
        $value = trim((string) $summary);
        if ($items === [] || $value === '') {
            return null;
        }

        if (preg_match('/no identifiqu[eé] acciones|no entend[ií] (?:esa orden|ninguna acci[oó]n)|no hay acciones/iu', $value)) {
            return null;
        }

        return $value;
    }

    /**
     * @param  array<int,array{intent:string,data:array,audit_log_id?:int}>  $pending
     */
    private function formatPendingConfirmation(array $pending): string
    {
        $n = count($pending);
        $allTeachers = $n > 0 && collect($pending)->every(
            fn ($action) => ($action['intent'] ?? '') === 'create_teacher'
        );

        if ($allTeachers && $n >= 2) {
            $lines = ["Perfecto. He identificado {$n} profesores para crear:", ''];
            $i = 1;
            foreach ($pending as $action) {
                $data = (array) ($action['data'] ?? []);
                $name = trim((string) ($data['teacher_name'] ?? 'Profesor'));
                $subject = trim((string) ($data['subject_name'] ?? ''));
                $span = $this->formatGradeSpan((array) ($data['grades'] ?? []));
                $detail = '';
                if ($subject !== '' && $span !== '') {
                    $detail = " → {$subject} ({$span})";
                } elseif ($subject !== '') {
                    $detail = " → {$subject}";
                }
                $lines[] = "{$i}. 👨‍🏫 {$name}{$detail}";
                $i++;
            }
            $lines[] = '';
            $lines[] = "¿Confirmas que quieres crear estos {$n} profesores?";
            if ($n > 5) {
                $lines[] = "¿Quieres confirmar las {$n} acciones o quieres que las revise una por una?";
            }
            $lines[] = "Responde 'sí' para confirmar.";

            return implode("\n", $lines);
        }

        if ($n >= 2) {
            $lines = ["Perfecto. He identificado {$n} acciones:", ''];
            $i = 1;
            foreach ($pending as $action) {
                $lines[] = $i.'. '.$this->confirmationMessageFor(
                    (string) ($action['intent'] ?? ''),
                    (array) ($action['data'] ?? [])
                );
                $i++;
            }
            $lines[] = '';
            $lines[] = "¿Confirmas que quieres ejecutar estas {$n} acciones?";
            if ($n > 5) {
                $lines[] = "¿Quieres confirmar las {$n} acciones o quieres que las revise una por una?";
            }
            $lines[] = "Responde 'sí' para confirmar.";

            return implode("\n", $lines);
        }

        $body = $n === 1
            ? $this->confirmationMessageFor((string) ($pending[0]['intent'] ?? ''), (array) ($pending[0]['data'] ?? []))
            : 'Confirmar la operación.';

        return "✨ {$body}\nResponde 'sí' para confirmar.";
    }

    /**
     * @param  array<int,array{success?:bool,action_type?:string,message?:string,data?:array}>  $results
     */
    private function formatBatchResults(array $results): ?string
    {
        $all = collect($results);
        $teachers = $all->filter(fn ($row) => ($row['action_type'] ?? '') === 'create_teacher');
        if ($teachers->count() < 2 || $teachers->count() !== $all->count()) {
            return null;
        }

        $ok = $teachers->filter(fn ($row) => ($row['success'] ?? true) !== false)->values();
        $fail = $teachers->filter(fn ($row) => ($row['success'] ?? true) === false)->values();
        $lines = [];
        if ($ok->isNotEmpty()) {
            $lines[] = '✅ Profesores creados exitosamente:';
            foreach ($ok as $i => $row) {
                $data = (array) ($row['data'] ?? []);
                $name = trim((string) ($data['teacher_name'] ?? 'Profesor'));
                $code = trim((string) ($data['invite_code'] ?? ''));
                $subject = trim((string) ($data['subject_name'] ?? ''));
                if ($subject === '') {
                    $subject = trim((string) ($data['courses'][0]['subject_name'] ?? ''));
                }
                $grades = (array) ($data['grades'] ?? []);
                if ($grades === []) {
                    $grades = collect($data['courses'] ?? [])->pluck('grade')->unique()->values()->all();
                }
                $span = $this->formatGradeSpan($grades);
                $detail = '';
                if ($subject !== '' && $span !== '') {
                    $detail = " ({$subject}, {$span})";
                } elseif ($subject !== '') {
                    $detail = " ({$subject})";
                }
                $codePart = $code !== '' ? " - Código: {$code}" : '';
                $lines[] = ($i + 1).". {$name}{$detail}{$codePart}";
            }
        }
        if ($fail->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'Quedó pendiente:';
            foreach ($fail as $row) {
                $lines[] = '- '.trim((string) ($row['message'] ?? 'No se pudo crear.'));
            }
        }
        if ($ok->isNotEmpty() && $fail->isEmpty()) {
            $lines[] = '';
            $lines[] = 'Todos han recibido sus códigos de invitación. ¿Necesitas algo más?';
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<int,array{intent:string,data:array,audit_log_id?:int}>  $pending
     */
    private function rememberPendingActions(array $pending, bool $allOrNothing = false): void
    {
        $items = collect($pending)->map(function ($action) {
            $data = (array) ($action['data'] ?? []);

            return [
                'intent' => $action['intent'] ?? '',
                'name' => $data['teacher_name'] ?? ($data['names'][0] ?? null),
                'subject' => $data['subject_name'] ?? null,
                'grades' => $data['grades'] ?? null,
            ];
        })->all();

        $payload = [
            'action' => count($pending) > 1 ? 'batch_create_teachers' : 'pending_action',
            'type' => count($pending) > 1 ? 'multiple_confirmation' : 'confirmation',
            'items' => $items,
            'total' => count($pending),
        ];

        session([
            self::PENDING_SESSION_KEY => $pending,
            self::PENDING_BATCH_SESSION_KEY => $payload,
            self::PENDING_META_SESSION_KEY => ['all_or_nothing' => $allOrNothing],
            'chat_pending' => $payload,
        ]);
    }

    private function forgetPendingActions(): void
    {
        $this->confirmationManager->clear();
        session()->forget(self::PENDING_SESSION_KEY);
        session()->forget(self::PENDING_BATCH_SESSION_KEY);
        session()->forget(self::PENDING_META_SESSION_KEY);
        session()->forget(self::BATCH_QUEUE_KEY);
        session()->forget('chat_pending');
    }

    /**
     * Limpia cualquier plan residual antes de devolver una aclaración.
     * Evita que un "Sí" posterior ejecute una acción fantasma de turnos anteriores.
     *
     * @param  array<string,mixed>  $extra
     */
    private function forgetPendingAndClarify(string $message, array $extra = [], int $status = 422): JsonResponse
    {
        $this->forgetPendingActions();
        $this->conversationContext->clearPendingReferences();

        return response()->json([
            'success' => false,
            'needs_clarification' => true,
            'requires_confirmation' => false,
            'pending_actions' => null,
            'buttons' => [],
            'timeline' => $this->conversationalTimeline($message),
            'message' => $message,
            ...$extra,
        ], $status);
    }

    /**
     * @return array<int,array{status:string,message:string}>
     */
    private function conversationalTimeline(string $finalMessage): array
    {
        return [
            ['status' => 'analyzing', 'message' => 'Analizando tu mensaje...'],
            ['status' => 'conversation', 'message' => $finalMessage],
        ];
    }

    /**
     * @param  array<string,mixed>  $partialData
     */
    private function startPendingPlanFromMissingData(
        User $director,
        string $intent,
        string $rawText,
        array $partialData,
        string $fallbackMessage
    ): ?JsonResponse {
        if ($intent !== 'create_students_batch') {
            return null;
        }

        $names = $this->extractStudentNames($rawText);
        if ($names === []) {
            return null;
        }

        $grade = $partialData['grade'] ?? $this->extractTargetGrade($rawText);
        $section = $partialData['section'] ?? $this->extractSection($rawText);
        $subject = $partialData['subject_name']
            ?? $this->extractKnownSubject($rawText)
            ?? $this->extractSubjectFromCoursePrompt($rawText)
            ?? $this->extractSubject($rawText);
        $teacherName = $partialData['teacher_name'] ?? $this->sanitizePersonName($this->extractTeacherName($rawText));

        $slots = [
            'grade' => $grade ?: null,
            // En flujo por slots pedimos sección explícita para no perder contexto.
            'section' => $section ?: null,
        ];

        $this->confirmationManager->startPlan([
            'intent' => $intent,
            'data' => [
                'names' => $names,
                'grade' => $grade ?: null,
                'section' => $section ?: null,
                'subject_name' => $subject,
                'teacher_name' => $teacherName,
                'missing_grades' => [],
                'raw_text' => $rawText,
            ],
        ], $slots);

        $nextQuestion = $this->nextSlotQuestion($this->confirmationManager->missingSlots());

        return response()->json([
            'success' => false,
            'needs_clarification' => true,
            'pending_plan' => $this->confirmationManager->getPlan(),
            'message' => $nextQuestion ?: $fallbackMessage,
        ]);
    }

    private function handlePendingPlanSlots(User $director, string $text): ?JsonResponse
    {
        if (! $this->confirmationManager->hasPendingPlan()) {
            return null;
        }

        if ($this->isNegativeText($text)) {
            $this->confirmationManager->clear();

            return response()->json([
                'success' => true,
                'cancelled' => true,
                'message' => 'Operación cancelada. No hice cambios.',
                'buttons' => $this->mainMenuButtons(),
                'mode' => 'main_menu',
            ]);
        }

        $plan = $this->confirmationManager->getPlan();
        if (! $plan) {
            $this->confirmationManager->clear();

            return null;
        }

        // Compatibilidad con planes antiguos de una sola acción (intent/data/slots).
        $isLegacy = isset($plan['intent']) && ! isset($plan['actions']);
        if ($isLegacy) {
            $plan = $this->legacyPlanToActionPlan($plan);
            $this->confirmationManager->startPlan($plan);
        }

        $pendingBefore = $this->confirmationManager->getPendingSlots();
        foreach ($pendingBefore as $item) {
            $value = $this->extractSlotValue((string) $item['slot'], $text);
            if ($value === null || $value === '') {
                continue;
            }
            $this->confirmationManager->fillSlot($item['action_id'], $item['slot'], $value);
        }

        $plan = $this->confirmationManager->getPlan() ?? $plan;
        $this->confirmationManager->applySlots($plan);
        $this->confirmationManager->startPlan($plan);

        if (! $this->confirmationManager->isComplete()) {
            $plan = $this->confirmationManager->getPlan() ?? $plan;

            return $this->pendingSlotsResponse($plan, $isLegacy);
        }

        $completed = $this->confirmationManager->getPlan();
        $this->confirmationManager->clear();

        if (! $completed) {
            return null;
        }

        return $this->prepareActions(
            $director,
            $this->planToActions($completed),
            $text,
            $completed['summary'] ?? null,
            $completed['all_or_nothing'] ?? false,
            $completed
        );
    }

    /**
     * @param  array<string,mixed>  $legacy
     * @return array<string,mixed>
     */
    private function legacyPlanToActionPlan(array $legacy): array
    {
        $data = (array) ($legacy['data'] ?? []);
        $slots = (array) ($legacy['slots'] ?? []);
        $missing = [];
        foreach ($slots as $name => $value) {
            $missing[] = [
                'name' => (string) $name,
                'description' => $this->slotDescription((string) $name),
                'required' => true,
                'value' => $value,
                'source' => 'user',
            ];
        }

        return [
            'id' => (string) Str::uuid(),
            'status' => 'needs_info',
            'actions' => [
                [
                    'id' => 'legacy',
                    'type' => (string) ($legacy['intent'] ?? ''),
                    'entity' => $this->entityForIntent((string) ($legacy['intent'] ?? '')),
                    'params' => $data,
                    'status' => 'needs_info',
                    'missing_slots' => $missing,
                    'depends_on' => [],
                    'confirmation_required' => DirectorUnifiedAgentService::isWriteTool((string) ($legacy['intent'] ?? '')),
                ],
            ],
            'summary' => 'Plan pendiente de una sola acción.',
            'requires_confirmation' => true,
            'all_or_nothing' => false,
        ];
    }

    private function slotDescription(string $slot): string
    {
        return match ($slot) {
            'grade' => '¿En qué grado?',
            'section' => '¿En qué sección?',
            'subject_name' => '¿De qué materia se trata?',
            default => "¿Cuál es el valor de {$slot}?",
        };
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
     * @param  array<int,string>  $missingSlots
     */
    private function nextSlotQuestion(array $missingSlots): ?string
    {
        if (in_array('grade', $missingSlots, true)) {
            return '¿En qué grado debo crear al alumno?';
        }
        if (in_array('section', $missingSlots, true)) {
            return '¿En qué sección?';
        }

        return null;
    }

    private function extractSlotValue(string $slot, string $text): ?string
    {
        return match ($slot) {
            'grade' => $this->extractTargetGrade($text),
            'section' => $this->extractSection($text) ?? $this->extractLooseSectionToken($text),
            'subject_name' => $this->extractKnownSubject($text),
            default => null,
        };
    }

    private function extractLooseSectionToken(string $text): ?string
    {
        $trimmed = trim($text);
        if (! preg_match('/^[A-Za-z]{1,2}$/u', $trimmed)) {
            return null;
        }
        $normalized = mb_strtolower($trimmed);
        if (in_array($normalized, ['si', 'sí', 'no', 'ok', 'dale'], true)) {
            return null;
        }

        return mb_strtoupper($trimmed);
    }

    private function startOneByOneReview(): JsonResponse
    {
        $pending = array_values(session(self::PENDING_SESSION_KEY, []));
        if (count($pending) < 2) {
            return $this->pendingConfirmationResponse($pending);
        }

        $first = $pending[0];
        $rest = array_slice($pending, 1);
        session([self::BATCH_QUEUE_KEY => $rest]);
        $this->rememberPendingActions([$first]);

        return $this->pendingConfirmationResponse(
            [$first],
            'Vamos uno por uno. Primera acción (1 de '.(count($rest) + 1).'):'
        );
    }

    /**
     * @return array<int,array{id:string,label:string,color?:string}>
     */
    private function confirmationButtons(int $count): array
    {
        $yesLabel = $count > 1 ? '✅ Sí, crear todos' : '✅ Sí';
        $noLabel = $count > 1 ? '❌ Cancelar todo' : '❌ No';
        $buttons = [
            ['id' => 'confirm_yes', 'label' => $yesLabel, 'color' => 'green'],
            ['id' => 'confirm_no', 'label' => $noLabel, 'color' => 'red'],
        ];
        if ($count > 5) {
            array_splice($buttons, 1, 0, [[
                'id' => 'confirm_one_by_one',
                'label' => '🔎 Revisar uno por uno',
                'color' => 'orange',
            ]]);
        }

        return $buttons;
    }

    private function wantsOneByOneReview(string $text): bool
    {
        $value = $this->normalizedText($text);

        return (bool) preg_match('/\b(?:uno por uno|una por una|revis(?:a|ar)\s+(?:las\s+)?una)\b/u', $value);
    }

    /**
     * @param  array{teacher_name?:string,subject_name?:string|null,grades?:array}  $data
     */
    private function summarizeCreateTeacher(array $data): string
    {
        $name = trim((string) ($data['teacher_name'] ?? 'el profesor'));
        $subject = trim((string) ($data['subject_name'] ?? ''));
        $span = $this->formatGradeSpan((array) ($data['grades'] ?? []));
        if ($subject !== '' && $span !== '') {
            return "Crear al Profesor {$name} ({$subject} - {$span}).";
        }
        if ($subject !== '') {
            return "Crear al Profesor {$name} ({$subject}).";
        }

        return "Crear la invitación para {$name} sin materias iniciales.";
    }

    /**
     * @param  array{names?:array,grade?:string,section?:string|null,subject_name?:string|null}  $data
     */
    private function summarizeCreateStudents(array $data): string
    {
        $names = array_filter((array) ($data['names'] ?? []));
        $grade = trim((string) ($data['grade'] ?? ''));
        $section = trim((string) ($data['section'] ?? ''));
        $subject = trim((string) ($data['subject_name'] ?? ''));
        $place = $grade !== '' ? "{$grade} Grado" : 'el grado indicado';
        if ($section !== '') {
            $place .= " / {$section}";
        }

        if (count($names) > 1) {
            $lines = [];
            $idx = 1;
            foreach ($names as $name) {
                $lines[] = "{$idx}. {$name}";
                $idx++;
            }
            $subjectPart = $subject !== '' ? " ({$subject})" : '';

            return 'Crear '.count($names)." estudiantes en {$place}{$subjectPart}:\n".implode("\n", $lines).'.';
        }

        $namesStr = implode(', ', $names) ?: 'alumno(s)';
        $teacher = trim((string) ($data['teacher_name'] ?? ''));
        $extra = '';
        if ($subject !== '') {
            $extra .= " ({$subject})";
        }
        if ($teacher !== '') {
            $extra .= " con {$teacher}";
        }
        if ($extra !== '') {
            return "Crear al Alumno {$namesStr} en {$place}{$extra} y matricularlo en el curso.";
        }

        return "Crear al Alumno {$namesStr} en {$place}.";
    }

    /**
     * @param  array<int,string>  $grades
     */
    private function formatGradeSpan(array $grades): string
    {
        $grades = array_values(array_filter($grades, fn ($grade) => trim((string) $grade) !== ''));
        if ($grades === []) {
            return '';
        }
        if (count($grades) === 1) {
            return $grades[0];
        }

        return $grades[0].' a '.$grades[array_key_last($grades)];
    }

    /**
     * Cuando faltan grados en el colegio, el flujo los crea automáticamente al ejecutar,
     * así que el mensaje lo aclara para que el director confirme con "sí"/"confirmo".
     */
    private function mentionMissingGrades(string $intent, array $data): string
    {
        $missing = (array) ($data['missing_grades'] ?? []);
        $missing = array_values(array_filter($missing, fn ($g) => (string) $g !== ''));

        if ($missing === []) {
            return '';
        }

        return 'También crearé automáticamente los grados que faltan: '.implode(', ', $missing).'.';
    }

    /**
     * Resolve terse follow-ups when the external interpreter is unavailable.
     *
     * @return array{intent:string,data:array}|null
     */
    private function contextualFallbackAction(string $text): ?array
    {
        $value = $this->normalizedText($text);
        $context = $this->conversationContext->current();
        $teacher = trim((string) ($context['teacher_name'] ?? ''));
        $subject = trim((string) ($context['subject_name'] ?? ''));
        $grades = array_values(array_filter((array) ($context['grades'] ?? [])));

        if (preg_match('/^(?:crealo|crear?lo|hazlo)$/', $value) && $teacher !== '') {
            return [
                'intent' => 'create_teacher',
                'data' => [
                    'teacher_name' => $teacher,
                    'subject_name' => $subject !== '' ? $subject : null,
                    'grades' => $grades,
                    'expires_in_days' => 30,
                ],
            ];
        }

        if ((str_contains($value, 'agregale') || str_contains($value, 'asignale') || str_contains($value, 'los cursos que dijimos'))
            && $teacher !== '') {
            $parsedSubject = $this->extractSubject($text)
                ?? $this->extractSubjectFromDeletePrompt($text)
                ?? ($subject !== '' ? $subject : null);
            $parsedGrades = $this->extractGrades($text);
            if ($parsedGrades === []) {
                $parsedGrades = $grades;
            }
            if ($parsedSubject && $parsedGrades !== []) {
                return [
                    'intent' => 'assign_teacher',
                    'data' => [
                        'teacher_name' => $this->extractTeacherName($text) ?? $teacher,
                        'subject_name' => $parsedSubject,
                        'grades' => $parsedGrades,
                    ],
                ];
            }
        }

        return null;
    }

    private function detectIntent(string $text): ?string
    {
        $value = $this->normalizedText($text);

        if ($this->looksLikeEnrollmentSync($value)) {
            return 'sync_all_enrollments';
        }

        // Eliminar / borrar / quitar / remover / limpiar / cancelar (antes de crear, para no confundir verbos).
        if ($this->hasDeleteVerb($value)) {
            if (preg_match('/\b(?:invitacion|invitaci[oó]n|invitaciones|invite|invites)\b/', $value)) {
                if (preg_match('/\b(?:profesor(?:a)?|docente)\b/', $value) || preg_match('/\b(?:de|del)\s+[a-z]+\s+profesor\b/', $value)) {
                    return 'delete_teacher_invite';
                }
                if (! preg_match('/\b(?:alumn[oa]|estudiante|curso|materia|asignatura)s?\b/', $value)) {
                    return 'delete_teacher_invite';
                }
            }
            if ($this->isMassPeopleTarget($value) && preg_match('/\b(?:profesores|profesoras|docentes)\b/', $value)) {
                return 'delete_all_teachers';
            }
            if ($this->isMassCourseTarget($value)) {
                return 'delete_all_courses';
            }
            if (preg_match('/\b(?:profesor(?:a)?|docente)\b/', $value)) {
                return 'delete_teacher';
            }
            if (preg_match('/\b(?:alumn[oa]|estudiante)s?\b/', $value) && ! preg_match('/\b(?:profesores|docentes|cursos)\b/', $value)) {
                return 'delete_student';
            }
            if (preg_match('/\b(?:curso|asignatura|materia)s?\b/', $value)) {
                return 'delete_course';
            }
        }

        // A teacher creation may also mention courses/subjects. It must win over create_course.
        if ($this->wantsCreateTeacherPhrase($value)) {
            return 'create_teacher';
        }

        if (preg_match('/\bcrea(?:r|me)?\b/', $value)
            && preg_match('/\bmateria\b/', $value)
            && ! preg_match('/\bcurso/', $value)
            && ! preg_match('/\b[1-6](?:ro|do|to|er|ero)?\b/', $value)) {
            return 'create_subject';
        }

        // Mover / cambiar de grado o sección.
        if (preg_match('/\b(?:mueve|mover|traslada|trasladar|pasa(?:r)?|cambi(?:a|ar))\b/', $value)
            && preg_match('/\b(?:alumn[oa]|estudiante)s?\b/', $value)
            && (str_contains($value, 'grado') || str_contains($value, 'seccion') || str_contains($value, 'curso'))) {
            return 'update_student';
        }

        // Student creation/enrollment must win over create_course when the user mentions alumnos (o alumnas).
        if (preg_match('/\b(?:alumn[oa]|estudiante)s?\b/', $value)
            && (preg_match('/\b(?:cre(?:a|ar|es|e|o)|creame|agrega|matricula|inscribe)\b/', $value)
                || preg_match('/\b(?:asigna|inscribe|matricula|agregalo|añade)\b/', $value))) {
            $looksLikeEnroll = (str_contains($value, 'curso') || str_contains($value, 'materia') || str_contains($value, 'asignatura'))
                && preg_match('/\b(?:asigna(?:lo|le|r|les)?|inscribe(?:lo|le|r|les)?|matricula(?:lo|le|r|les)?|agregalo|añade|anade)\b/', $value);
            $enrollWithoutCreate = preg_match('/\b(?:inscribe|asigna(?:lo|le)?|matricula(?:lo|le)?)\b/', $value)
                && ! preg_match('/\b(?:cre(?:a|ar|es|e|o)|creame)\b/', $value);
            if ($looksLikeEnroll || $enrollWithoutCreate) {
                return 'enroll_students_course';
            }

            return 'create_students_batch';
        }

        if (preg_match('/\b(?:cre(?:a|ar|es|e|o)|creame|agrega|matricula|inscribe)\b/', $value)
            && ! preg_match('/\b(?:profesor(?:a)?|docente|maestr[oa])\b/', $value)
            && $this->extractStudentNames($text) !== []) {
            return 'create_students_batch';
        }

        if (preg_match('/\b(?:matricula(?:r|lo|le)?|inscribe(?:r|lo|le)?)\b/', $value)
            && $this->extractKnownSubject($text)
            && ($this->extractGrades($text) !== [] || $this->extractTargetGrade($text))) {
            return 'enroll_students_course';
        }

        // Crear uno o varios cursos: "crea el curso de X", "crees los cursos de: 1ero..6to de ingles",
        // "Crea Matemática para 4.º, 5.º y 6.º."
        if (! preg_match('/\b(?:alumn[oa]|estudiante)s?\b/', $value)
            && (preg_match('/\bcre(?:a|ar|es|e|o)\b/', $value) || str_contains($value, 'crea') || str_contains($value, 'crear') || str_contains($value, 'crees'))
            && (str_contains($value, 'curso') || str_contains($value, 'cursso') || str_contains($value, 'asignatura') || str_contains($value, 'materia')
                || preg_match('/\b(?:para|en|de|del)\s+(?:el\s+)?[1-6](?:ro|er|do|to|°|º|ero)?\s*(?:grado\b|[,.]|(?:y|e)\b|$)/', $value))) {
            return 'create_course';
        }
        if ((str_contains($value, 'dara') || str_contains($value, 'va a dar') || str_contains($value, 'asigna') || str_contains($value, 'agregale') || str_contains($value, 'asignale'))
            && (str_contains($value, 'grado') || str_contains($value, 'curso') || str_contains($value, 'materia') || preg_match('/\b[1-6](ro|do|to|er)?\b/', $value))) {
            return 'assign_teacher';
        }
        if ((str_contains($value, 'desmatricula') || str_contains($value, 'retira') || str_contains($value, 'saca'))
            && str_contains($value, 'curso')
            && (preg_match('/\balumn[oa]s?\b/', $value) || str_contains($value, 'estudiante') || preg_match('/\sa\s+[a-z]/', $value))) {
            return 'unenroll_students_course';
        }
        if ((str_contains($value, 'inscribe') || preg_match('/\basigna(?:lo|le|r|les)?\b/', $value))
            && str_contains($value, 'curso')
            && (preg_match('/\balumn[oa]s?\b/', $value) || str_contains($value, 'estudiante') || preg_match('/\sa\s+[a-z]/', $value))) {
            return 'enroll_students_course';
        }
        if ((str_contains($value, 'agrega') || str_contains($value, 'matricula') || str_contains($value, 'crear') || str_contains($value, 'inscribe') || str_contains($value, 'crea a') || str_contains($value, 'crear a'))
            && (preg_match('/\balumn[oa]s?\b/', $value) || str_contains($value, 'estudiante'))) {
            return 'create_students_batch';
        }
        if ((str_contains($value, 'agrega') || str_contains($value, 'matricula') || str_contains($value, 'crea') || str_contains($value, 'crear'))
            && preg_match('/\b[1-6](ro|do|to|er)?\b.*grado/', $value)) {
            return 'create_students_batch';
        }
        if ((str_contains($value, 'doc-') || str_contains($value, 'codigo') || str_contains($value, 'invitacion'))
            && (str_contains($value, 'profesor') || str_contains($value, 'docente') || preg_match('/\b(?:de|del)\s+[a-záéíóúñ]+(?:\s+[a-záéíóúñ]+){0,2}\b/u', $value))) {
            return 'get_teacher_invite_code';
        }
        if ((str_contains($value, 'doc-') || str_contains($value, 'codigo'))
            && (str_contains($value, 'consulta') || str_contains($value, 'estado') || str_contains($value, 'mostrar') || str_contains($value, 'tiene') || str_contains($value, 'dame'))) {
            return 'manage_invite_code';
        }
        if (
            str_contains($value, 'como va')
            || str_contains($value, 'como van')
            || str_contains($value, 'como estan')
            || str_contains($value, 'que alumnos tiene')
            || str_contains($value, 'que alumnos hay')
            || str_contains($value, 'que cursos tiene')
            || str_contains($value, 'cuantas faltas')
            || str_contains($value, 'como estan sus evaluaciones')
            || str_contains($value, 'como estan las evaluaciones')
            || str_contains($value, 'cuantos alumnos')
            || str_contains($value, 'cuantos profesores')
            || str_contains($value, 'cuantos cursos')
            || str_contains($value, 'que profesores')
            || str_contains($value, 'que cursos')
            || str_contains($value, 'quien ha faltado')
            || str_contains($value, 'quienes han faltado')
            || str_contains($value, 'quien esta faltando')
            || str_contains($value, 'problemas en')
            || str_contains($value, 'bajo rendimiento')
            || str_contains($value, 'como esta')
            // Analítica en tiempo real.
            || str_contains($value, 'cada seccion')
            || str_contains($value, 'por seccion')
            || str_contains($value, 'mas destacado')
            || str_contains($value, 'el destacado')
            || str_contains($value, 'mejor alumno')
            || str_contains($value, 'mejor promedio')
            || str_contains($value, 'mejores promedios')
            || str_contains($value, 'mas faltas')
            || str_contains($value, 'compara')
            || str_contains($value, 'tendencia')
            || str_contains($value, 'evolucion')
            || str_contains($value, 'ranking')
            || preg_match('/\btop\s+\d/', $value)
            || preg_match('/\bquien(?:es)?\s+tiene(?:n)?\b/', $value)
            || str_contains($value, 'informe')
            || str_contains($value, 'resumen')
            || str_contains($value, 'resume')
            || str_contains($value, 'estado academico')
            || str_contains($value, 'preocup')
            || str_contains($value, 'necesitan atencion')
            || str_contains($value, 'bajado')
            || str_contains($value, 'asistencia')
            || str_contains($value, 'mejor rendimiento')
            || ((str_contains($value, 'consulta') || str_contains($value, 'muestrame') || str_contains($value, 'mostrar') || str_contains($value, 'estado') || str_contains($value, 'dame'))
                && (str_contains($value, 'profesor') || str_contains($value, 'estudiante') || str_contains($value, 'alumno') || str_contains($value, 'curso')))
        ) {
            return 'query_academic';
        }

        return null;
    }

    private function looksLikeEnrollmentSync(string $value): bool
    {
        return (bool) preg_match('/\b(?:sincroniza(?:r)?|sync)\b.+\b(?:matricul|cursos?|alumn|estudiante)/u', $value)
            || (bool) preg_match('/\b(?:agrega|matricula|inscribe)\b.+\btodos\b.+\b(?:alumn|estudiante).+\b(?:cursos?\s+disponibles|todos\s+los\s+cursos)/u', $value);
    }

    /**
     * Detecta varias intenciones en un mismo mensaje (profesor + cursos + alumno + matrícula).
     *
     * @return array<int,array{intent:string,data:array}>
     */
    private function detectMultiIntentActions(User $director, string $text): array
    {
        if ($this->looksLikeEnrollmentSync($this->normalizedText($text))) {
            return [['intent' => 'sync_all_enrollments', 'data' => []]];
        }

        $actions = [];

        // Nuevo extractor determinista: si detecta varias intenciones claras,
        // las usa directamente. Esto evita que el LLM confunda alumnos con profesores.
        $localIntentions = $this->mapLocalIntentions(
            $this->intentExtractor->extractMultipleIntentions($text)
        );
        if (count($localIntentions) >= 2) {
            return $this->dedupeDetectedActions($localIntentions);
        }

        $batch = $this->extractMultipleActions($director, $text);
        if (count($batch) >= 2) {
            $actions = $batch;
        }

        $clauses = $this->splitIntentClauses($text);
        if ($clauses === []) {
            $clauses = [$text];
        }

        foreach ($clauses as $clause) {
            $value = $this->normalizedText($clause);
            $wantsTeacher = $this->wantsCreateTeacherPhrase($value);
            $wantsAssignTeacher = (bool) preg_match(
                '/\b(?:as[ií]gna(?:le)?|dara|dará|agrega(?:le)?|va a dar|imparte|enseña)\b/',
                $value
            );
            $wantsStudent = (bool) preg_match('/\b(?:alumn[oa]|estudiante)s?\b/', $value)
                && (bool) preg_match('/\b(?:cre(?:a|ar|es|e|o)|creame|agrega|matricula)\b/', $value);
            $wantsEnroll = $wantsStudent && (
                str_contains($value, 'curso') || str_contains($value, 'materia') || str_contains($value, 'asignatura')
            ) && (bool) preg_match('/\b(?:asigna(?:lo|le|r|les)?|inscribe(?:lo|le|r|les)?|matricula(?:lo|le|r|les)?|agregalo|añade|anade)\b/', $value);
            $wantsEnrollOnly = ! $wantsStudent
                && (bool) preg_match('/\b(?:matricula(?:r|lo|le)?|inscribe(?:r|lo|le)?)\b/', $value)
                && $this->extractKnownSubjects($clause) !== [];

            if ($wantsTeacher && count($batch) < 2) {
                $names = $this->extractTeacherNames($clause);
                if ($names === []) {
                    [$data, $msg] = $this->parseCreateTeacher($director, $clause);
                    if (! $msg && ! empty($data['teacher_name'])) {
                        $names = [$data['teacher_name']];
                    }
                }
                if (count($names) > 20) {
                    throw ValidationException::withMessages([
                        'teacher' => 'Veo más de 20 profesores en tu mensaje. Parte la lista en dos envíos.',
                    ]);
                }
                if ($names !== []) {
                    [$sharedData, $msg] = $this->parseCreateTeacher($director, $clause);
                    foreach ($names as $name) {
                        $data = $sharedData;
                        $data['teacher_name'] = $name;
                        $actions[] = ['intent' => 'create_teacher', 'data' => $data];
                    }
                }
            }

            if ($wantsAssignTeacher && count($batch) < 2) {
                $createAlreadyHasCourses = collect($actions)->contains(
                    fn ($action) => ($action['intent'] ?? '') === 'create_teacher'
                        && ! empty($action['data']['subject_name'])
                        && ! empty($action['data']['grades'])
                );
                if (! $createAlreadyHasCourses) {
                    $assignSegments = preg_split(
                        '/\s+y\s+(?=a\s+[A-Za-zÁÉÍÓÚáéíóúÑñ]|as[ií]gna(?:le)?\s+|dara\s+|dará\s+|agrega(?:le)?\s+)/iu',
                        $clause,
                        -1,
                        PREG_SPLIT_NO_EMPTY
                    );
                    if ($assignSegments === [] || count($assignSegments) === 1) {
                        [$data, $msg] = $this->parseAssignTeacher($director, $clause);
                        if (! $msg && ! empty($data['teacher_name']) && ! empty($data['subject_name']) && $data['grades'] !== []) {
                            $actions[] = ['intent' => 'assign_teacher', 'data' => $data];
                        }
                    } else {
                        foreach ($assignSegments as $segment) {
                            $segment = trim((string) $segment);
                            if ($segment === '') {
                                continue;
                            }
                            [$data, $msg] = $this->parseAssignTeacher($director, $segment);
                            if (! $msg && ! empty($data['teacher_name']) && ! empty($data['subject_name']) && $data['grades'] !== []) {
                                $actions[] = ['intent' => 'assign_teacher', 'data' => $data];
                            }
                        }
                    }
                }
            }

            if ($wantsStudent) {
                [$data, $msg] = $this->parseCreateStudentsBatch($director, $clause);
                if (! $msg && ! empty($data['names'])) {
                    $actions[] = ['intent' => 'create_students_batch', 'data' => $data];
                }
                if ($wantsEnroll) {
                    [$enroll, $enrollMsg] = $this->parseEnrollStudentsCourse($director, $clause);
                    if (! $enrollMsg && ! empty($enroll['names'])) {
                        $actions[] = ['intent' => 'enroll_students_course', 'data' => $enroll];
                    }
                }
            } elseif ($wantsEnrollOnly) {
                [$enroll, $enrollMsg] = $this->parseEnrollStudentsCourse($director, $clause);
                if (! $enrollMsg && ! empty($enroll['names'])) {
                    $actions[] = ['intent' => 'enroll_students_course', 'data' => $enroll];
                }
            }
        }

        return $this->dedupeDetectedActions($actions);
    }

    /**
     * Mapea los nombres de intención del extractor local al catálogo de tools.
     *
     * @param  array<int,array{intent:string,data:array<string,mixed>}>  $actions
     * @return array<int,array{intent:string,data:array<string,mixed>}>
     */
    private function mapLocalIntentions(array $actions): array
    {
        return collect($actions)->map(function (array $action) {
            $intent = $action['intent'];
            if ($intent === 'enroll_students') {
                $intent = 'enroll_students_course';
            }
            if (! in_array($intent, DirectorUnifiedAgentService::TOOLS, true)) {
                return null;
            }

            return [
                'intent' => $intent,
                'data' => $action['data'],
            ];
        })->filter()->values()->all();
    }

    /**
     * @param  array<string,mixed>  $plan
     */
    private function planHasPendingSlots(array $plan): bool
    {
        foreach ($plan['actions'] ?? [] as $action) {
            foreach ($action['missing_slots'] ?? [] as $slot) {
                if (($slot['required'] ?? true)
                    && (($slot['value'] ?? null) === null || ($slot['value'] ?? '') === '')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Convierte un ActionPlan en el formato de acciones del controlador.
     *
     * @param  array<string,mixed>  $plan
     * @return array<int,array{intent:string,data:array<string,mixed>}>
     */
    private function planToActions(array $plan): array
    {
        $validTools = DirectorUnifiedAgentService::TOOLS;
        $validTools[] = 'create_subject';

        $errors = [];
        $actions = collect($plan['actions'] ?? [])
            ->filter(fn (array $action) => ! in_array(($action['status'] ?? ''), ['needs_info', 'skipped'], true))
            ->map(function (array $action) use ($validTools, &$errors, $plan) {
                $intent = (string) ($action['type'] ?? '');
                if ($intent === 'enroll_students') {
                    $intent = 'enroll_students_course';
                }

                if ($intent === '') {
                    $errors[] = 'Una acción del plan no tiene tipo.';

                    return null;
                }

                if (! in_array($intent, $validTools, true)) {
                    $errors[] = "La acción '{$intent}' no está soportada.";

                    return null;
                }

                $data = (array) ($action['params'] ?? []);
                $missingRequired = $this->missingRequiredParams($intent, $data);
                if ($missingRequired !== []) {
                    $errors[] = "La acción '{$intent}' necesita: ".implode(', ', $missingRequired).'.';
                }

                return [
                    'intent' => $intent,
                    'data' => $data,
                    'action_plan_id' => $plan['id'] ?? null,
                    'action_id' => $action['id'] ?? null,
                    'planner_source' => $plan['planner_source'] ?? null,
                ];
            })
            ->filter()
            ->values()
            ->all();

        if ($errors !== []) {
            throw ValidationException::withMessages([
                'prompt' => "No pude usar el plan del asistente:\n".implode("\n", $errors),
            ]);
        }

        return $actions;
    }

    /**
     * @return array<int,string>
     */
    private function missingRequiredParams(string $intent, array $data): array
    {
        if ($intent === 'create_students_batch') {
            $hasStudentsData = ! empty($data['students_data']);
            $hasLegacy = ! empty($data['names']) && ! empty($data['grade']);

            return $hasStudentsData || $hasLegacy ? [] : ['students_data'];
        }

        $required = match ($intent) {
            'create_teacher' => ['teacher_name'],
            'enroll_students_course' => ['names', 'subject_name'],
            'update_student' => ['student_name'],
            'delete_teacher', 'delete_teacher_invite' => ['teacher_name'],
            'delete_student' => ['student_name'],
            'create_subject' => ['subject_name'],
            'create_course' => ['subject_name'],
            'create_courses_batch' => ['courses_data'],
            'assign_teacher' => ['teacher_name', 'subject_name'],
            'unassign_teacher' => ['teacher_name', 'subject_name'],
            'delete_course' => ['subject_name'],
            default => [],
        };

        $missing = [];
        foreach ($required as $param) {
            $value = $data[$param] ?? null;
            if ($param === 'subject_name' && $this->isGenericSubjectName($value)) {
                $value = null;
            }
            if ($intent === 'create_courses_batch' && $param === 'courses_data') {
                $hasConcrete = collect((array) $value)->contains(function ($item) {
                    if (! is_array($item)) {
                        return false;
                    }
                    $subject = $item['subject_name'] ?? null;

                    return ! $this->isGenericSubjectName($subject);
                });
                if (! $hasConcrete) {
                    $missing[] = 'subject_name';
                }
            }
            if ($value === null || $value === '' || (is_array($value) && $value === [])) {
                $missing[] = $param;
            }
        }

        return array_values(array_unique($missing));
    }

    private function isGenericSubjectName(mixed $value): bool
    {
        $subject = trim((string) $value);
        if ($subject === '') {
            return true;
        }
        $folded = mb_strtolower($subject);
        $folded = strtr($folded, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u']);

        return (bool) preg_match(self::GENERIC_SUBJECT_PATTERN, $folded);
    }

    /**
     * @param  array<string,mixed>  $plan
     */
    private function pendingSlotsResponse(array $plan, bool $isLegacy = false): JsonResponse
    {
        $pending = $this->confirmationManager->getPendingSlots();
        $next = $pending[0] ?? null;

        if ($next === null) {
            // No hay slots pendientes a pesar del flag; continuar con el plan.
            return $this->prepareActions(
                auth()->user(),
                $this->planToActions($plan),
                (string) ($plan['raw_text'] ?? ''),
                $plan['summary'] ?? null,
                $plan['all_or_nothing'] ?? false,
                $plan
            );
        }

        $message = $next['description'];
        $summary = $plan['summary'] ?? '';
        if ($summary !== '') {
            $message = "{$summary}\n\nPara continuar: {$message}";
        }

        $responsePlan = $plan;
        if ($isLegacy) {
            // Compatibilidad: los clientes antiguos esperan pending_plan.slots.
            $firstAction = $plan['actions'][0] ?? [];
            $slots = [];
            foreach ($firstAction['missing_slots'] ?? [] as $slot) {
                $slots[$slot['name']] = $slot['value'] ?? null;
            }
            $responsePlan = array_merge($plan, [
                'intent' => $firstAction['type'] ?? '',
                'data' => $firstAction['params'] ?? [],
                'slots' => $slots,
            ]);
        }

        $response = [
            'success' => false,
            'needs_clarification' => true,
            'pending_plan' => $responsePlan,
            'next_slot' => $next,
            'message' => $message,
            'buttons' => $this->mainMenuButtons(),
        ];

        if (! $isLegacy) {
            $response['action_plan'] = $plan;
        }

        return response()->json($response);
    }

    /**
     * @return array<int,string>
     */
    private function splitIntentClauses(string $text): array
    {
        $parts = preg_split(
            '/\s+(?:y\s+)?(?:tambien|también|ademas|además|adicional)\s*,?\s+|\s+y\s+(?=crea(?:r|me)?\s+(?:al?\s+)?(?:alumn[oa]|estudiante|profesor|docente))/iu',
            $text,
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        return collect($parts ?: [$text])
            ->map(fn ($part) => trim((string) $part))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<int,array{intent:string,data:array}>  $actions
     * @return array<int,array{intent:string,data:array}>
     */
    private function mergeMissingIntentsFromText(User $director, array $actions, string $text): array
    {
        foreach ($this->detectMultiIntentActions($director, $text) as $candidate) {
            if (! $this->planHasSimilarAction($actions, $candidate)) {
                $actions[] = $candidate;
            }
        }

        return array_values($actions);
    }

    /**
     * @param  array<int,array{intent:string,data:array}>  $actions
     * @param  array{intent:string,data:array}  $candidate
     */
    private function planHasSimilarAction(array $actions, array $candidate): bool
    {
        $intent = (string) ($candidate['intent'] ?? '');
        foreach ($actions as $action) {
            if (($action['intent'] ?? '') !== $intent) {
                continue;
            }
            if ($intent === 'create_teacher') {
                $existingName = trim((string) ($action['data']['teacher_name'] ?? ''));
                $candidateName = trim((string) ($candidate['data']['teacher_name'] ?? ''));
                if ($existingName === '' || $candidateName === '') {
                    continue;
                }
                $left = mb_strtolower($existingName);
                $right = mb_strtolower($candidateName);

                return $left === $right || str_contains($left, $right) || str_contains($right, $left);
            }
            if ($intent === 'assign_teacher') {
                $existingName = trim((string) ($action['data']['teacher_name'] ?? ''));
                $candidateName = trim((string) ($candidate['data']['teacher_name'] ?? ''));
                $existingSubject = trim((string) ($action['data']['subject_name'] ?? ''));
                $candidateSubject = trim((string) ($candidate['data']['subject_name'] ?? ''));
                if ($existingName === '' || $candidateName === '') {
                    continue;
                }
                $leftName = mb_strtolower($existingName);
                $rightName = mb_strtolower($candidateName);
                $sameName = $leftName === $rightName || str_contains($leftName, $rightName) || str_contains($rightName, $leftName);
                $sameSubject = $existingSubject !== '' && $candidateSubject !== '' && mb_strtolower($existingSubject) === mb_strtolower($candidateSubject);

                return $sameName && $sameSubject;
            }
            if (in_array($intent, ['create_students_batch', 'enroll_students_course'], true)) {
                $existing = collect($action['data']['names'] ?? [])->map(fn ($name) => mb_strtolower(trim((string) $name)));
                $incoming = collect($candidate['data']['names'] ?? [])->map(fn ($name) => mb_strtolower(trim((string) $name)));

                return $existing->intersect($incoming)->isNotEmpty() || $existing->isNotEmpty();
            }

            return true;
        }

        return false;
    }

    private function samePerson(string $left, string $right): bool
    {
        return mb_strtolower(trim($left)) === mb_strtolower(trim($right));
    }

    /**
     * @param  array<int,array{intent:string,data:array}>  $actions
     * @return array<int,array{intent:string,data:array}>
     */
    private function dedupeDetectedActions(array $actions): array
    {
        $seen = [];
        $unique = [];
        foreach ($actions as $action) {
            $key = ($action['intent'] ?? '').'|'.md5(json_encode($action['data'] ?? []));
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $action;
        }

        return $unique;
    }

    private function teacherClauseMentionsCourses(string $text): bool
    {
        $span = $text;
        if (preg_match('/^(.*?)(?:\b(?:tambien|también|ademas|además|y)\s+)?(?:crea(?:r|me)?\s+)?(?:al?\s+)?(?:alumn[oa]|estudiante)/iu', $text, $m)) {
            $span = trim((string) $m[1]);
        }

        return $span !== '' && $this->utteranceMentionsCourses($span);
    }

    /**
     * @return array{0:array,1:?string}
     */
    private function parseDeleteTeacher(User $director, string $text): array
    {
        $name = $this->extractNamedPersonAfterRole($text, 'profesor');
        if (! $name) {
            return [[], '¿A qué profesor deseas eliminar? Ejemplo: "Elimina al profesor Carlos Pérez".'];
        }

        return [[
            'teacher_name' => $name,
        ], null];
    }

    /**
     * @return array{0:array,1:?string}
     */
    private function parseDeleteInvite(User $director, string $text): array
    {
        $name = $this->extractNamedPersonAfterRole($text, 'profesor');
        if (! $name && preg_match('/invitaci[oó]n\s+(?:del\s+profesor|de\s+)?([A-Za-zÁÉÍÓÚáéíóúÑñ]+(?:\s+[A-Za-zÁÉÍÓÚáéíóúÑñ]+){0,3})\b/iu', $text, $m)) {
            $name = trim((string) $m[1]);
        }
        $name = $name ? $this->sanitizePersonName($name) : null;
        if (! $name) {
            return [[], '¿De qué profesor deseas cancelar la invitación? Ejemplo: "Cancela la invitación del profesor Carlos Pérez".'];
        }

        return [[
            'teacher_name' => $name,
        ], null];
    }

    /**
     * @return array{0:array,1:?string}
     */
    private function parseDeleteAllTeachers(User $director): array
    {
        $count = User::query()
            ->where('colegio_id', $director->colegio_id)
            ->where('role', 'profesor')
            ->count();

        return [[
            'count' => $count,
        ], null];
    }

    /**
     * @return array{0:array,1:?string}
     */
    private function parseDeleteCourse(User $director, string $text): array
    {
        $subject = $this->extractSubjectFromCoursePrompt($text) ?? $this->extractSubjectFromDeletePrompt($text);
        if (! $subject) {
            return [[], '¿Qué curso debo eliminar? Ejemplo: "Borra el curso de matemáticas".'];
        }

        $grade = $this->extractTargetGrade($text);
        $section = $this->extractSection($text);
        $subjectKey = rtrim(mb_strtolower($subject), 's');
        $query = Course::query()
            ->where('colegio_id', $director->colegio_id)
            ->whereRaw('LOWER(subject_name) like ?', ['%'.$subjectKey.'%']);
        if ($grade) {
            $query->whereRaw('LOWER(grade) = ?', [mb_strtolower($grade)]);
        }
        $matchCount = $query->count();
        if ($matchCount === 0) {
            return [[], 'No encontré un curso de '.$subject.($grade ? " {$grade}" : '').' en tu colegio.'];
        }

        return [[
            'subject_name' => $subject,
            'grade' => $grade,
            'section' => $section,
            'match_count' => $matchCount,
        ], null];
    }

    /**
     * @return array{0:array,1:?string}
     */
    private function parseDeleteAllCourses(User $director): array
    {
        $count = Course::query()->where('colegio_id', $director->colegio_id)->count();

        return [[
            'count' => $count,
        ], null];
    }

    /**
     * @return array{0:array,1:?string}
     */
    private function parseDeleteStudent(User $director, string $text): array
    {
        $blob = '';
        if (preg_match('/(?:elimina(?:r)?|borra(?:r)?|quita(?:r)?)\s+(?:a\s+|al\s+)?(?:los\s+|las\s+)?(?:alumn[oa]s?|estudiantes?)\s+(.+)$/iu', $text, $m)) {
            $blob = trim((string) $m[1]);
        }
        $names = $blob !== '' ? $this->splitAndSanitizeNames($blob) : [];
        $single = $this->extractNamedPersonAfterRole($text, 'alumno')
            ?? $this->extractNamedPersonAfterRole($text, 'estudiante');
        if ($single) {
            $names = array_values(array_unique(array_filter(array_merge(
                $names,
                count($this->splitAndSanitizeNames($single)) > 1
                    ? $this->splitAndSanitizeNames($single)
                    : [$single]
            ))));
        }
        if ($names === []) {
            return [[], '¿A qué alumno deseas eliminar? Ejemplo: "Elimina a los alumnos Carlos, Juan y María".'];
        }

        return [[
            'student_name' => $names[0],
            'names' => $names,
        ], null];
    }

    /**
     * @return array{0:array,1:?string}
     */
    private function parseUpdateStudent(User $director, string $text): array
    {
        $name = $this->extractNamedPersonAfterRole($text, 'alumno')
            ?? $this->extractNamedPersonAfterRole($text, 'estudiante');
        if (! $name) {
            return [[], '¿A qué alumno debo mover? Ejemplo: "Mueve al alumno Vicente José a 2do grado sección B".'];
        }

        $grade = $this->extractTargetGrade($text);
        $section = $this->extractSection($text);
        if (! $grade && ! $section) {
            return [[], 'Indica el grado o la sección destino. Ejemplo: "Mueve a Vicente José a 2do grado sección B".'];
        }

        return [[
            'student_name' => $name,
            'new_grade' => $grade,
            'new_section' => $section,
        ], null];
    }

    /**
     * @return array{0:array,1:?string}
     */
    private function parseCreateTeacher(User $director, string $text): array
    {
        $name = $this->sanitizePersonName($this->extractTeacherName($text));
        if (! $name) {
            return [[], '¿Cuál es el nombre completo del profesor que deseas crear?'];
        }

        $subject = $this->sanitizeSubjectName($this->extractKnownSubject($text)
            ?? $this->extractSubject($text)
            ?? $this->extractSubjectFromCoursePrompt($text)
            ?? $this->extractSubjectFromDeletePrompt($text));
        $grades = $this->extractGrades($text);
        $missingGrades = $this->missingGradesFor($director, $grades);

        return [[
            'teacher_name' => $name,
            'subject_name' => $subject,
            'grades' => $grades,
            'missing_grades' => $missingGrades,
            'expires_in_days' => 30,
        ], null];
    }

    /**
     * @return array{0:array,1:?string}
     */
    private function parseCreateSubject(string $text): array
    {
        $match = [];
        if (! preg_match('/materia(?:s)?\s+(.+)$/iu', trim($text), $match)) {
            return [[], '¿Qué materia agrego al catálogo? Ejemplo: "Crea la materia Biología".'];
        }
        $name = trim(preg_replace('/[.,;]+$/', '', $match[1]) ?? '');
        $name = trim(preg_replace('/\s+y\s+asígn.*$/iu', '', $name) ?? $name);
        if ($name === '') {
            return [[], 'Indica el nombre de la materia.'];
        }

        return [['subject_name' => $name], null];
    }

    /**
     * @return array{0:array,1:?string}
     */
    private function parseCreateCourse(User $director, string $text): array
    {
        $grades = $this->extractGrades($text);

        $subject = $this->sanitizeSubjectName($this->extractSubjectFromCoursePrompt($text));
        if (! $subject) {
            return [[], '¿Qué asignatura debo crear? Ejemplo: "Crea Matemática para 4.º, 5.º y 6.º".'];
        }

        if ($grades === []) {
            $grade = $this->extractTargetGrade($text);
            if (! $grade) {
                return [[], '¿Para qué grado debo crear el curso?'];
            }
            $grades = [$grade];
        }

        $section = $this->extractSection($text);
        $teacherName = $this->extractTeacherName($text);
        $missingGrades = $this->missingGradesFor($director, $grades);

        return [[
            'subject_name' => $subject,
            'grade' => $grades[0],
            'grades' => $grades,
            'section' => $section,
            'teacher_name' => $teacherName,
            'missing_grades' => $missingGrades,
        ], null];
    }

    /**
     * @return array{0:array,1:?string}
     */
    private function parseAssignTeacher(User $director, string $text): array
    {
        $context = $this->conversationContext->current();
        $rawName = $this->extractTeacherName($text);
        // Fallback para segmentos tipo "a Mariano Guevara lenguaje ..." sin palabra "profesor"
        // y para "a Mariano Guevara asignale el curso de lenguaje ..." donde el nombre precede al verbo.
        if ($rawName === null) {
            if (preg_match('/\ba\s+([A-Za-zÁÉÍÓÚáéíóúÑñ]+(?:\s+[A-Za-zÁÉÍÓÚáéíóúÑñ]+){0,2})\s+(?:asigna|asígnale|dara|dará|imparte)?\s*(?:el\s+curso\s+de\s+)?(?:robotica|rob[oó]tica|computaci[oó]n|matem[aá]tica|ingl[eé]s|lenguaje|biolog[ií]a|ciencias|historia|geograf[ií]a|f[ií]sica|qu[ií]mica|educaci[oó]n|religi[oó]n|arte|m[uú]sica)\b/iu', $text, $m)) {
                $rawName = trim($m[1]);
            } elseif (preg_match('/\ba\s+([A-Za-zÁÉÍÓÚáéíóúÑñ]+(?:\s+[A-Za-zÁÉÍÓÚáéíóúÑñ]+){0,2})\s+asigna/iu', $text, $m)) {
                $rawName = trim($m[1]);
            } elseif (preg_match('/\ba\s+([A-Za-zÁÉÍÓÚáéíóúÑñ]+(?:\s+[A-Za-zÁÉÍÓÚáéíóúÑñ]+){0,2})\s+de\s+[1-6]/iu', $text, $m)) {
                $rawName = trim($m[1]);
            }
        }
        $name = $this->sanitizePersonName($rawName ?? ($context['teacher_name'] ?? null));
        if (! $name) {
            return [[], '¿A qué profesor deseas asignar la materia?'];
        }

        $subject = $this->sanitizeSubjectName($this->extractSubject($text)
            ?? $this->extractSubjectFromCoursePrompt($text)
            ?? $this->extractSubjectFromDeletePrompt($text)
            ?? $this->extractKnownSubject($text)
            ?? ($context['subject_name'] ?? null));
        if (! $subject) {
            return [[], '¿Qué materia deseas asignar?'];
        }

        $grades = $this->extractGrades($text);
        if ($grades === [] && is_array($context['grades'] ?? null)) {
            $grades = $context['grades'];
        }
        if ($grades === []) {
            return [[], '¿Qué grados debo asignar? Ejemplo: 1ro a 6to.'];
        }

        $missingGrades = $this->missingGradesFor($director, $grades);

        return [[
            'teacher_name' => $name,
            'subject_name' => $subject,
            'grades' => $grades,
            'missing_grades' => $missingGrades,
        ], null];
    }

    /**
     * @return array{0:array,1:?string}
     */
    private function parseCreateStudentsBatch(User $director, string $text): array
    {
        $grade = $this->extractTargetGrade($text);
        if (! $grade) {
            return [[], '¿En qué grado debo crear a los estudiantes?'];
        }

        $names = $this->extractStudentNames($text);
        if ($names === []) {
            return [[], 'No pude detectar los nombres. Usa formato: "Agrega a Carlos, Juan y María al 3er grado".'];
        }

        $section = $this->extractSection($text);
        $subject = $this->extractKnownSubject($text)
            ?? $this->extractSubjectFromCoursePrompt($text)
            ?? $this->extractSubject($text);
        $teacherName = $this->sanitizePersonName($this->extractTeacherName($text));
        $missingGrades = $this->missingGradesFor($director, [$grade]);

        return [[
            'names' => $names,
            'grade' => $grade,
            'section' => $section,
            'subject_name' => $subject,
            'teacher_name' => $teacherName,
            'missing_grades' => $missingGrades,
        ], null];
    }

    /**
     * @return array{0:array,1:?string}
     */
    private function parseEnrollStudentsCourse(User $director, string $text): array
    {
        $grade = $this->extractTargetGrade($text);
        if (! $grade) {
            return [[], '¿En qué grado está ese curso?'];
        }

        $subjects = $this->extractKnownSubjects($text);
        $subject = $subjects[0]
            ?? $this->extractSubjectFromCoursePrompt($text)
            ?? $this->extractSubject($text);
        if (! $subject) {
            return [[], '¿En qué asignatura debo inscribirlos?'];
        }

        $section = $this->extractSection($text);
        $teacherName = $this->sanitizePersonName($this->extractTeacherName($text));
        $allInGrade = (bool) preg_match(
            '/\b(?:alumn[oa]s|estudiantes)\s+de\s+(?:el\s+|la\s+)?(?:[1-6]|primer|segundo|tercer|cuarto|quinto|sexto)/iu',
            $text
        );
        $names = $allInGrade ? [] : $this->extractStudentNames($text);
        if (! $allInGrade && $names === []) {
            return [[], 'No pude detectar los alumnos para inscribir. Ejemplo: "Inscribe a Luis y Marta en Matemática de 4to grado" o "Inscribe a los alumnos de 1ro en Computación".'];
        }

        return [[
            'names' => $names,
            'all_in_grade' => $allInGrade,
            'subject_name' => $subject,
            'subject_names' => $subjects !== [] ? $subjects : [$subject],
            'grade' => $grade,
            'section' => $section,
            'teacher_name' => $teacherName,
        ], null];
    }

    /**
     * @return array{0:array,1:?string}
     */
    private function parseGetTeacherInviteCode(string $text): array
    {
        $teacherName = $this->sanitizePersonName($this->extractTeacherName($text))
            ?? $this->extractNamedPersonAfterRole($text, 'profesor');
        if (! $teacherName) {
            return [[], 'Indícame el nombre del profesor para buscar su código de invitación.'];
        }

        return [[
            'teacher_name' => $teacherName,
        ], null];
    }

    /**
     * @return array{0:array,1:?string}
     */
    private function parseManageInviteCode(string $text): array
    {
        $inviteCode = null;
        if (preg_match('/(DOC-[A-Z0-9]{4,8})/i', $text, $m)) {
            $inviteCode = InviteCodeHelper::normalize($m[1]);
        }

        $teacherName = $this->extractTeacherName($text);
        if (! $inviteCode && ! $teacherName) {
            return [[], 'Indícame el código DOC- o el nombre del profesor para gestionar su invitación.'];
        }

        return [[
            'operation' => 'query',
            'invite_code' => $inviteCode,
            'teacher_name' => $teacherName,
            'expires_in_days' => 30,
        ], null];
    }

    /**
     * @return array{0:array,1:?string}
     */
    private function parseQueryAcademic(string $text): array
    {
        $value = $this->normalizedText($text);

        if (preg_match('/c[oó]mo va(?:\s+el|\s+la)?\s+profesor(?:a)?\s+(.+?)\??$/iu', trim($text), $m)) {
            return [[
                'query_type' => 'teacher_overview',
                'teacher_name' => trim($m[1]),
            ], null];
        }

        if (preg_match('/c[oó]mo va\s+(.+?)\s+en\s+([A-Za-zÁÉÍÓÚáéíóúÑñ\s]+)\??$/u', trim($text), $m)) {
            return [[
                'query_type' => 'student_subject_overview',
                'student_name' => trim($m[1]),
                'subject_name' => trim($m[2]),
            ], null];
        }

        if (preg_match('/qu[eé]\s+alumnos tiene\s+(.+?)\s+en\s+([1-6](?:ro|do|to|er|ero)?\s*grado)/iu', $text, $m)) {
            return [[
                'query_type' => 'teacher_students_grade',
                'teacher_name' => trim($m[1]),
                'grade' => $this->extractTargetGrade($m[2]) ?? trim($m[2]),
            ], null];
        }

        if (preg_match('/qu[eé]\s+cursos tiene asignad[oa]s?\s+(?:el|la)?\s*profesor(?:a)?\s+(.+?)\??$/iu', trim($text), $m)) {
            return [[
                'query_type' => 'teacher_courses',
                'teacher_name' => trim($m[1]),
            ], null];
        }

        if (preg_match('/cu[aá]ntas faltas tiene\s+(.+?)\??$/iu', trim($text), $m)) {
            return [[
                'query_type' => 'student_absences',
                'student_name' => trim($m[1]),
            ], null];
        }

        if (preg_match('/c[oó]mo est[aá]n(?:\s+sus)?\s+evaluaciones(?:\s+de)?\s+(.+?)\??$/iu', trim($text), $m)) {
            return [[
                'query_type' => 'student_evaluations',
                'student_name' => trim($m[1]),
            ], null];
        }

        // ── Analítica en tiempo real (motor seguro de solo lectura, scope colegio) ──

        // "Compara 2do con 4to", "comparame 2do grado con 4to grado de matemática"
        if (preg_match('/compara(?:r|me)?\s+(.+?)\s+con\s+(.+?)\s*[?¿.!]*$/iu', trim($text), $m)) {
            $gradeA = $this->extractTargetGrade($m[1]);
            $gradeB = $this->extractTargetGrade($m[2]);
            if ($gradeA && $gradeB) {
                return [[
                    'query_type' => 'compare_grades',
                    'grade' => $gradeA,
                    'grade_b' => $gradeB,
                    'subject_name' => $this->extractKnownSubject($text),
                ], null];
            }
        }

        // "tendencia de notas", "evolución de faltas"
        if (preg_match('/\b(?:tendencia|evoluci[oó]n)\b/iu', trim($text))) {
            return [[
                'query_type' => 'trends',
                'metric' => preg_match('/falta|asistencia|inasistencia/iu', $text) ? 'absences' : 'average',
            ], null];
        }

        // "¿Quién tiene mejor promedio?" / "¿Quiénes tienen mejor promedio en 4to?"
        if (preg_match('/qui[ée]n(?:es)?\s+(?:tiene|tienen)\s+(?:el\s+)?mejor\s+promedio/iu', trim($text))) {
            return [[
                'query_type' => 'rankings',
                'metric' => 'average',
                'grade' => $this->extractTargetGrade($text),
                'section' => $this->extractSection($text),
                'subject_name' => $this->extractKnownSubject($text),
            ], null];
        }

        // "¿Quién tiene más faltas?" / "¿Quiénes tienen más faltas en 2do?"
        if (preg_match('/qui[ée]n(?:es)?\s+(?:tiene|tienen|ha|han)\s+m[aá]s\s+faltas/iu', trim($text))) {
            return [[
                'query_type' => 'rankings',
                'metric' => 'absences',
                'grade' => $this->extractTargetGrade($text),
                'section' => $this->extractSection($text),
            ], null];
        }

        // "ranking de promedios", "ranking de faltas en 4to"
        if (preg_match('/\branking\s+de\s+(promedios?|notas?|calificaciones|faltas|asistencias?)/iu', trim($text), $m)) {
            $absences = (bool) preg_match('/faltas|asistencias?/iu', (string) $m[1]);

            return [[
                'query_type' => 'rankings',
                'metric' => $absences ? 'absences' : 'average',
                'grade' => $this->extractTargetGrade($text),
                'section' => $this->extractSection($text),
                'subject_name' => $this->extractKnownSubject($text),
            ], null];
        }

        // "top 5 alumnos", "top 3 estudiantes de 2do"
        if (preg_match('/\btop\s+(\d{1,2})\s+(?:alumnos|estudiantes)/iu', trim($text), $m)) {
            return [[
                'query_type' => 'rankings',
                'metric' => 'average',
                'limit' => (int) $m[1],
                'grade' => $this->extractTargetGrade($text),
                'section' => $this->extractSection($text),
            ], null];
        }

        // "¿Cómo van los de 4to?", "¿Cómo van los alumnos de 4to A?"
        if (preg_match('/c[oó]mo\s+van\s+(?:los\s+|las\s+)?(?:alumnos?\s+|estudiantes?\s+)?(?:de\s+|del\s+)?(.+?)\s*[?¿.!]*$/iu', trim($text), $m)) {
            $grade = $this->extractTargetGrade($m[1]);
            if ($grade) {
                $section = $this->extractSection($text);
                if (! $section && preg_match('/[1-6](?:ro|ero|do|to|er|°|º)?\s+([A-Ca-c])\b/u', (string) $m[1], $sm)) {
                    $section = strtoupper($sm[1]);
                }

                return [[
                    'query_type' => 'class_performance',
                    'grade' => $grade,
                    'section' => $section,
                    'subject_name' => $this->extractKnownSubject($text),
                ], null];
            }
        }

        // "¿Cómo están los alumnos de 4to A?" (plural + contexto). El singular
        // "cómo está 4to grado" lo sigue manejando grade_overview más abajo.
        if (preg_match('/c[oó]mo\s+est[aá]n\s+(?:los\s+|las\s+)?(?:alumnos?\s+|estudiantes?\s+)?(?:de\s+|del\s+)?(.+?)\s*[?¿.!]*$/iu', trim($text), $m)) {
            $grade = $this->extractTargetGrade($m[1]);
            if ($grade) {
                $section = $this->extractSection($text);
                if (! $section && preg_match('/[1-6](?:ro|ero|do|to|er|°|º)?\s+([A-Ca-c])\b/u', (string) $m[1], $sm)) {
                    $section = strtoupper($sm[1]);
                }

                return [[
                    'query_type' => 'class_performance',
                    'grade' => $grade,
                    'section' => $section,
                    'subject_name' => $this->extractKnownSubject($text),
                ], null];
            }
        }

        // "¿Cómo va Carlos?" (sin materia) → rendimiento general del alumno.
        // "¿Cómo va 4to?" → rendimiento del grado.
        // ("cómo va el profesor X" y "cómo va X en Y" ya se capturaron arriba).
        if (preg_match('/c[oó]mo\s+va\s+(.+?)\s*[?¿.!]*$/iu', trim($text), $m)) {
            $target = trim((string) $m[1]);
            $grade = $this->extractTargetGrade($target);
            if ($grade) {
                return [[
                    'query_type' => 'class_performance',
                    'grade' => $grade,
                    'section' => $this->extractSection($text),
                ], null];
            }

            return [[
                'query_type' => 'student_performance',
                'student_name' => $target,
            ], null];
        }

        // "¿Qué alumnos hay en 2do?" → lista filtrada por grado/sección.
        if (preg_match('/qu[eé]\s+(?:alumnos|estudiantes)\s+hay\s+en\s+(.+?)\s*[?¿.!]*$/iu', trim($text), $m)) {
            $grade = $this->extractTargetGrade($m[1]);
            if ($grade) {
                return [[
                    'query_type' => 'students_list',
                    'grade' => $grade,
                    'section' => $this->extractSection($text),
                ], null];
            }
        }

        // "¿Cuántos alumnos hay en cada sección?"
        if (preg_match('/cu[aá]ntos\s+(?:alumnos|estudiantes).*(?:cada\s+secci[oó]n|por\s+secci[oó]n|en\s+cada\s+grado)/iu', trim($text))) {
            return [[
                'query_type' => 'section_counts',
            ], null];
        }

        // "¿Quién es el más destacado?" / "mejor alumno"
        if (preg_match('/(?:m[aá]s\s+destacado|el\s+destacado|mejor\s+alumno|primer\s+lugar)/iu', trim($text))) {
            return [[
                'query_type' => 'rankings',
                'metric' => 'average',
                'limit' => 1,
                'grade' => $this->extractTargetGrade($text),
                'section' => $this->extractSection($text),
                'subject_name' => $this->extractKnownSubject($text),
            ], null];
        }

        // Consultas generales del colegio (school-wide, siempre dentro del colegio del director).
        if (preg_match('/cu[aá]ntos\s+alumnos\s+hay\s+en\s+([1-6](?:ro|ero|do|to|er|°|º)?\s*grado)/iu', trim($text), $m)) {
            return [[
                'query_type' => 'grade_overview',
                'grade' => $this->extractTargetGrade($m[1]) ?? trim($m[1]),
            ], null];
        }
        if (preg_match('/c[oó]mo est[aá]\s+([1-6](?:ro|ero|do|to|er|°|º)?\s*grado)/iu', trim($text), $m)) {
            return [[
                'query_type' => 'grade_overview',
                'grade' => $this->extractTargetGrade($m[1]) ?? trim($m[1]),
            ], null];
        }
        if (preg_match('/cu[aá]ntos\s+(?:profesor(?:a|es)?|docentes?)\s+(?:tengo|hay|tenemos|existen|registrados)/iu', trim($text))) {
            return [[
                'query_type' => 'school_stats',
                'stat' => 'teachers',
            ], null];
        }
        if (preg_match('/cu[aá]ntos\s+(?:alumnos|estudiantes)\s+(?:tengo|hay|tenemos|existen|registrados)/iu', trim($text))) {
            return [[
                'query_type' => 'school_stats',
                'stat' => 'students',
            ], null];
        }
        if (preg_match('/cu[aá]ntos\s+cursos\s+(?:tengo|hay|tenemos|existen|registrados)/iu', trim($text))) {
            return [[
                'query_type' => 'school_stats',
                'stat' => 'courses',
            ], null];
        }
        if (preg_match('/qu[eé]\s+(?:cursos|cursoss)\s+(?:existen|tengo|tenemos|hay)/iu', trim($text))) {
            return [[
                'query_type' => 'school_courses',
            ], null];
        }
        if (preg_match('/qu[eé]\s+profesores\s+(?:tengo|tenemos|hay|existen|est[aá]n asignados)/iu', trim($text))) {
            return [[
                'query_type' => 'school_teachers',
            ], null];
        }
        if (preg_match('/(?:qui[ée]n|quiénes|qui[eé]nes)\s+ha(?:n)?\s+faltado(?:\s+m[aá]s)?/iu', trim($text))) {
            return [[
                'query_type' => 'frequent_absentees',
            ], null];
        }
        if (preg_match('/qui[ée]n(?:es)?\s+est[aá]n?\s+(?:teniendo|presentando)\s+problemas\s+en\s+([A-Za-zÁÉÍÓÚáéíóúÑñ][A-Za-zÁÉÍÓÚáéíóúÑñ\s]{1,40}?)\??$/iu', trim($text), $m)) {
            return [[
                'query_type' => 'subject_at_risk',
                'subject_name' => trim($m[1]),
            ], null];
        }
        if (preg_match('/(?:alumnos|estudiantes)\s+(?:con\s+|que\s+)?(?:est[aá]n\s+|van\s+|tienen\s+)?bajo\s+rendimiento(?:\s+en\s+([A-Za-zÁÉÍÓÚáéíóúÑñ][A-Za-zÁÉÍÓÚáéíóúÑñ\s]{1,40}?))?\??$/iu', trim($text), $m)) {
            return [[
                'query_type' => 'at_risk_students',
                'subject_name' => isset($m[1]) ? trim($m[1]) : null,
            ], null];
        }

        if (str_contains($value, 'profesor')) {
            return [[], 'Especifica así: "¿Cómo va el profesor Carlos Pérez?" o "¿Qué cursos tiene asignados la profesora María?"'];
        }
        if (str_contains($value, 'alumno') || str_contains($value, 'estudiante')) {
            return [[], 'Especifica así: "¿Cómo va Carlos Pérez en Matemática?" o "¿Cuántas faltas tiene Carlos Pérez?"'];
        }

        return [[], 'No pude identificar la consulta académica. Intenta con un nombre de profesor/alumno y una pregunta concreta.'];
    }

    private function queryAcademic(User $director, array $data): array
    {
        $type = $data['query_type'] ?? '';
        $colegioId = (int) $director->colegio_id;

        return match ($type) {
            'teacher_overview' => $this->queryTeacherOverview($colegioId, (string) $data['teacher_name']),
            'student_subject_overview' => $this->queryStudentSubjectOverview($colegioId, (string) $data['student_name'], (string) $data['subject_name']),
            'teacher_students_grade' => $this->queryTeacherStudentsByGrade($colegioId, (string) $data['teacher_name'], (string) $data['grade']),
            'teacher_courses' => $this->queryTeacherCourses($colegioId, (string) $data['teacher_name']),
            'student_absences' => $this->queryStudentAbsences($colegioId, (string) $data['student_name']),
            'student_evaluations' => $this->queryStudentEvaluations($colegioId, (string) $data['student_name']),
            'school_stats' => $this->querySchoolStats($colegioId, (string) ($data['stat'] ?? 'teachers')),
            'school_info' => $this->analytics->getSchoolInfo($colegioId),
            'most_advanced_course' => $this->analytics->getMostAdvancedCourse($colegioId),
            'school_courses' => $this->querySchoolCourses($colegioId),
            'school_teachers' => $this->querySchoolTeachers($colegioId),
            'grade_overview' => $this->queryGradeOverview($colegioId, (string) $data['grade']),
            'frequent_absentees' => $this->queryFrequentAbsentees($colegioId),
            'subject_at_risk' => $this->querySubjectAtRisk($colegioId, (string) $data['subject_name']),
            'at_risk_students' => $this->queryAtRiskStudents($colegioId, isset($data['subject_name']) ? (string) $data['subject_name'] : null),
            // Motor analítico seguro (solo lectura, scope colegio_id, salida Markdown).
            'class_performance' => $this->analytics->getClassPerformance(
                $colegioId,
                (string) $data['grade'],
                isset($data['section']) ? (string) $data['section'] : null,
                isset($data['subject_name']) ? (string) $data['subject_name'] : null,
            ),
            'student_performance' => $this->analytics->getStudentPerformance($colegioId, (string) $data['student_name']),
            'attendance' => $this->analytics->getAttendance(
                $colegioId,
                isset($data['grade']) ? (string) $data['grade'] : null,
                isset($data['section']) ? (string) $data['section'] : null,
                isset($data['student_name']) ? (string) $data['student_name'] : null,
                (int) ($data['days'] ?? 30),
            ),
            'rankings' => $this->analytics->getRankings(
                $colegioId,
                (string) ($data['metric'] ?? 'average'),
                isset($data['grade']) ? (string) $data['grade'] : null,
                isset($data['section']) ? (string) $data['section'] : null,
                isset($data['subject_name']) ? (string) $data['subject_name'] : null,
                (int) ($data['limit'] ?? 5),
            ),
            'trends' => $this->analytics->getTrends(
                $colegioId,
                (string) ($data['metric'] ?? 'average'),
                (int) ($data['weeks'] ?? 4),
            ),
            'compare_grades' => $this->analytics->compareGrades(
                $colegioId,
                (string) $data['grade'],
                (string) $data['grade_b'],
                isset($data['subject_name']) ? (string) $data['subject_name'] : null,
            ),
            'students_list' => $this->analytics->getStudents(
                $colegioId,
                isset($data['grade']) ? (string) $data['grade'] : null,
                isset($data['section']) ? (string) $data['section'] : null,
            ),
            'section_counts' => $this->analytics->getSectionCounts($colegioId),
            'declining_students' => $this->analytics->getDecliningStudents(
                $colegioId,
                isset($data['grade']) ? (string) $data['grade'] : null,
                isset($data['section']) ? (string) $data['section'] : null,
            ),
            'school_report' => $this->analytics->generateSchoolReport(
                $colegioId,
                isset($data['grade']) ? (string) $data['grade'] : null,
                isset($data['section']) ? (string) $data['section'] : null,
            ),
            'compare_courses' => $this->analytics->compareCourses(
                $colegioId,
                (string) ($data['grade'] ?? $data['grade_a'] ?? ''),
                (string) ($data['grade_b'] ?? ''),
                isset($data['section']) ? (string) $data['section'] : ($data['section_a'] ?? null),
                isset($data['section_b']) ? (string) $data['section_b'] : null,
                isset($data['subject_name']) ? (string) $data['subject_name'] : null,
            ),
            default => throw ValidationException::withMessages([
                'query' => 'No pude interpretar el tipo de consulta académica.',
            ]),
        };
    }

    private function queryTeacherOverview(int $colegioId, string $teacherName): array
    {
        $teacher = $this->resolveTeacherForQuery($colegioId, $teacherName);
        $courses = Course::query()
            ->where('colegio_id', $colegioId)
            ->where('teacher_id', $teacher->id)
            ->withCount('students')
            ->orderBy('grade')
            ->orderBy('subject_name')
            ->get(['id', 'subject_name', 'grade', 'section']);

        $courseIds = $courses->pluck('id');
        $average = null;
        if ($courseIds->isNotEmpty()) {
            $average = Grade::query()
                ->join('activities', 'grades.activity_id', '=', 'activities.id')
                ->whereIn('activities.course_id', $courseIds->all())
                ->where('grades.colegio_id', $colegioId)
                ->avg('grades.score');
        }

        $msg = "Profesor {$teacher->name}: ".$courses->count().' curso(s) y '.$courses->sum('students_count').' alumno(s) asignados.';
        if ($average !== null) {
            $msg .= ' Promedio reciente: '.number_format((float) $average, 1).'.';
        }

        return [
            'message' => $msg,
            'data' => [
                'teacher' => $teacher->only(['id', 'name', 'email']),
                'courses' => $courses->map(fn ($c) => [
                    'course_id' => $c->id,
                    'subject_name' => $c->subject_name,
                    'grade' => $c->grade,
                    'section' => $c->section,
                    'students_count' => $c->students_count,
                ])->values()->all(),
            ],
        ];
    }

    private function queryStudentSubjectOverview(int $colegioId, string $studentName, string $subjectName): array
    {
        $student = $this->resolveStudentForQuery($colegioId, $studentName);
        $subjectKey = mb_strtolower(trim($subjectName));

        $courses = $student->courses()
            ->where('courses.colegio_id', $colegioId)
            ->whereRaw('LOWER(courses.subject_name) like ?', ['%'.$subjectKey.'%'])
            ->get(['courses.id', 'courses.subject_name', 'courses.grade', 'courses.section']);
        $courseIds = $courses->pluck('id');

        $grades = collect();
        $absences = 0;
        $evaluations = collect();
        if ($courseIds->isNotEmpty()) {
            $grades = Grade::query()
                ->join('activities', 'grades.activity_id', '=', 'activities.id')
                ->where('grades.student_id', $student->id)
                ->where('grades.colegio_id', $colegioId)
                ->whereIn('activities.course_id', $courseIds->all())
                ->orderByDesc('grades.created_at')
                ->limit(10)
                ->get(['grades.score', 'grades.status', 'grades.created_at']);

            $absences = Attendance::query()
                ->where('colegio_id', $colegioId)
                ->where('student_id', $student->id)
                ->whereIn('course_id', $courseIds->all())
                ->where('status', Attendance::STATUS_ABSENT)
                ->count();

            $evaluations = Evaluation::query()
                ->where('colegio_id', $colegioId)
                ->whereIn('course_id', $courseIds->all())
                ->orderByDesc('scheduled_at')
                ->limit(5)
                ->get(['id', 'title', 'status', 'scheduled_at']);
        }

        $average = $grades->avg('score');
        $msg = "{$student->name} en {$subjectName}: ".$courses->count().' curso(s), '.$grades->count().' calificación(es) registrada(s) y '.$absences.' falta(s).';
        if ($average !== null) {
            $msg .= ' Promedio: '.number_format((float) $average, 1).'.';
        }

        return [
            'message' => $msg,
            'data' => [
                'student' => $student->only(['id', 'name', 'grade', 'section']),
                'courses' => $courses,
                'recent_grades' => $grades,
                'evaluations' => $evaluations,
                'absences' => $absences,
            ],
        ];
    }

    private function queryTeacherStudentsByGrade(int $colegioId, string $teacherName, string $grade): array
    {
        $teacher = $this->resolveTeacherForQuery($colegioId, $teacherName);
        $courseIds = Course::query()
            ->where('colegio_id', $colegioId)
            ->where('teacher_id', $teacher->id)
            ->whereRaw('LOWER(grade) = ?', [mb_strtolower($grade)])
            ->pluck('id');

        if ($courseIds->isEmpty()) {
            return [
                'message' => "{$teacher->name} no tiene cursos en {$grade}.",
                'data' => ['students' => []],
            ];
        }

        $students = Student::query()
            ->where('students.colegio_id', $colegioId)
            ->join('course_student', 'students.id', '=', 'course_student.student_id')
            ->whereIn('course_student.course_id', $courseIds->all())
            ->select('students.id', 'students.name', 'students.grade', 'students.section')
            ->distinct()
            ->orderBy('students.name')
            ->get();

        return [
            'message' => "{$teacher->name} tiene {$students->count()} alumno(s) en {$grade}.",
            'data' => ['students' => $students],
        ];
    }

    private function queryTeacherCourses(int $colegioId, string $teacherName): array
    {
        $teacher = $this->resolveTeacherForQuery($colegioId, $teacherName);
        $courses = Course::query()
            ->where('colegio_id', $colegioId)
            ->where('teacher_id', $teacher->id)
            ->withCount('students')
            ->orderBy('grade')
            ->orderBy('subject_name')
            ->get(['id', 'subject_name', 'grade', 'section', 'invite_code']);

        return [
            'message' => "{$teacher->name} tiene {$courses->count()} curso(s) asignado(s).",
            'data' => ['courses' => $courses],
        ];
    }

    private function queryStudentAbsences(int $colegioId, string $studentName): array
    {
        $student = $this->resolveStudentForQuery($colegioId, $studentName);
        $absences = Attendance::query()
            ->where('colegio_id', $colegioId)
            ->where('student_id', $student->id)
            ->where('status', Attendance::STATUS_ABSENT)
            ->count();
        $tardies = Attendance::query()
            ->where('colegio_id', $colegioId)
            ->where('student_id', $student->id)
            ->where('status', Attendance::STATUS_TARDY)
            ->count();

        return [
            'message' => "{$student->name} tiene {$absences} falta(s) y {$tardies} tardanza(s) registradas.",
            'data' => [
                'student' => $student->only(['id', 'name', 'grade', 'section']),
                'absences' => $absences,
                'tardies' => $tardies,
            ],
        ];
    }

    private function queryStudentEvaluations(int $colegioId, string $studentName): array
    {
        $student = $this->resolveStudentForQuery($colegioId, $studentName);
        $evaluationRows = Evaluation::query()
            ->where('colegio_id', $colegioId)
            ->whereHas('attempts', fn ($query) => $query->where('student_id', $student->id))
            ->with(['course:id,subject_name,grade,section'])
            ->orderByDesc('scheduled_at')
            ->limit(8)
            ->get(['id', 'course_id', 'title', 'status', 'scheduled_at']);

        $gradeRows = Grade::query()
            ->where('colegio_id', $colegioId)
            ->where('student_id', $student->id)
            ->with('activity:id,title,course_id')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get(['id', 'activity_id', 'score', 'status', 'created_at']);

        $average = $gradeRows->avg('score');
        $msg = "{$student->name} tiene {$evaluationRows->count()} evaluación(es) recientes y {$gradeRows->count()} nota(s) registradas.";
        if ($average !== null) {
            $msg .= ' Promedio de notas recientes: '.number_format((float) $average, 1).'.';
        }

        return [
            'message' => $msg,
            'data' => [
                'student' => $student->only(['id', 'name', 'grade', 'section']),
                'evaluations' => $evaluationRows,
                'grades' => $gradeRows->map(fn ($grade) => [
                    'score' => $grade->score,
                    'status' => $grade->status,
                    'activity_title' => $grade->activity?->title,
                ])->values()->all(),
            ],
        ];
    }

    private function querySchoolStats(int $colegioId, string $stat): array
    {
        return match ($stat) {
            'teachers' => [
                'message' => 'Tienes '.User::where('role', 'profesor')->where('colegio_id', $colegioId)->count().' profesor(es) registrado(s).',
                'data' => ['teachers_count' => User::where('role', 'profesor')->where('colegio_id', $colegioId)->count()],
            ],
            'students' => [
                'message' => 'Hay '.Student::where('colegio_id', $colegioId)->count().' alumnos registrados.',
                'data' => ['students_count' => Student::where('colegio_id', $colegioId)->count()],
            ],
            default => [
                'message' => 'El colegio tiene '.Course::where('colegio_id', $colegioId)->count().' curso(s) registrado(s).',
                'data' => ['courses_count' => Course::where('colegio_id', $colegioId)->count()],
            ],
        };
    }

    private function querySchoolCourses(int $colegioId): array
    {
        $courses = Course::query()
            ->where('colegio_id', $colegioId)
            ->withCount('students')
            ->orderBy('grade')
            ->orderBy('subject_name')
            ->get(['id', 'subject_name', 'grade', 'section']);

        return [
            'message' => 'El colegio tiene '.$courses->count().' curso(s): '.$courses->map(fn ($c) => "{$c->subject_name} {$c->grade}".($c->section ? " sección {$c->section}" : ''))->implode(', ').'.',
            'data' => ['courses' => $courses],
        ];
    }

    private function querySchoolTeachers(int $colegioId): array
    {
        $teachers = User::query()
            ->where('colegio_id', $colegioId)
            ->where('role', 'profesor')
            ->withCount(['courses' => fn ($query) => $query->where('colegio_id', $colegioId)])
            ->orderBy('name')
            ->get(['id', 'name']);

        return [
            'message' => 'Hay '.$teachers->count().' profesor(es).'.($teachers->isNotEmpty() ? ' '.$teachers->map(fn ($t) => "{$t->name} ({$t->courses_count} curso(s))")->implode(', ').'.' : ''),
            'data' => ['teachers' => $teachers],
        ];
    }

    private function queryGradeOverview(int $colegioId, string $grade): array
    {
        $courses = Course::query()
            ->where('colegio_id', $colegioId)
            ->whereRaw('LOWER(grade) = ?', [mb_strtolower($grade)])
            ->withCount('students')
            ->orderBy('subject_name')
            ->get(['id', 'subject_name', 'grade', 'section']);

        $courseIds = $courses->pluck('id');
        $students = $courseIds->isNotEmpty()
            ? Student::query()
                ->where('students.colegio_id', $colegioId)
                ->join('course_student', 'students.id', '=', 'course_student.student_id')
                ->whereIn('course_student.course_id', $courseIds->all())
                ->distinct()
                ->count('students.id')
            : 0;

        $average = null;
        if ($courseIds->isNotEmpty()) {
            $average = Grade::query()
                ->join('activities', 'grades.activity_id', '=', 'activities.id')
                ->whereIn('activities.course_id', $courseIds->all())
                ->where('grades.colegio_id', $colegioId)
                ->avg('grades.score');
        }

        $msg = "{$grade} grado tiene {$students} alumno(s) en ".$courses->count().' curso(s).';
        if ($average !== null) {
            $msg .= ' Promedio de notas: '.number_format((float) $average, 1).'.';
        }

        return [
            'message' => $msg,
            'data' => [
                'grade' => $grade,
                'students_count' => $students,
                'courses_count' => $courses->count(),
                'average_score' => $average !== null ? round((float) $average, 1) : null,
                'courses' => $courses,
            ],
        ];
    }

    private function queryFrequentAbsentees(int $colegioId): array
    {
        $rows = Attendance::query()
            ->select('students.name')
            ->selectRaw('count(*) as total')
            ->join('students', 'attendances.student_id', '=', 'students.id')
            ->where('attendances.colegio_id', $colegioId)
            ->where('attendances.status', Attendance::STATUS_ABSENT)
            ->groupBy('students.id', 'students.name')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        if ($rows->isEmpty()) {
            return [
                'message' => 'No hay faltas de asistencia registradas.',
                'data' => ['absentees' => []],
            ];
        }

        $labels = $rows->map(fn ($row) => "{$row->name} ({$row->total})")->implode(', ');

        return [
            'message' => 'Alumnos con más faltas: '.$labels.'.',
            'data' => ['absentees' => $rows],
        ];
    }

    private function querySubjectAtRisk(int $colegioId, string $subjectName): array
    {
        $subjectKey = mb_strtolower(trim($subjectName));
        $courseIds = Course::query()
            ->where('colegio_id', $colegioId)
            ->whereRaw('LOWER(subject_name) like ?', ['%'.$subjectKey.'%'])
            ->pluck('id');

        if ($courseIds->isEmpty()) {
            return [
                'message' => "No encontré cursos de {$subjectName} en tu colegio.",
                'data' => ['students' => []],
            ];
        }

        $rows = Grade::query()
            ->select('students.name')
            ->join('students', 'grades.student_id', '=', 'students.id')
            ->join('activities', 'grades.activity_id', '=', 'activities.id')
            ->whereIn('activities.course_id', $courseIds->all())
            ->where('grades.colegio_id', $colegioId)
            ->whereNotNull('grades.score')
            ->selectRaw('avg(grades.score) as promedio, count(grades.id) as cantidad')
            ->groupBy('students.id', 'students.name')
            ->orderBy('promedio')
            ->limit(8)
            ->get();

        if ($rows->isEmpty()) {
            return [
                'message' => "No tengo calificaciones registradas para calcular rendimiento en {$subjectName}.",
                'data' => ['students' => []],
            ];
        }

        $labels = $rows->map(fn ($row) => "{$row->name} (prom. {$row->promedio})")->implode(', ');

        return [
            'message' => "Alumnos con menor rendimiento en {$subjectName}: ".$labels.'.',
            'data' => ['students' => $rows],
        ];
    }

    private function queryAtRiskStudents(int $colegioId, ?string $subjectName): array
    {
        if ($subjectName !== null && trim($subjectName) !== '') {
            return $this->querySubjectAtRisk($colegioId, $subjectName);
        }

        $rows = Grade::query()
            ->select('students.name', 'courses.subject_name', 'courses.grade')
            ->join('students', 'grades.student_id', '=', 'students.id')
            ->join('activities', 'grades.activity_id', '=', 'activities.id')
            ->join('courses', 'activities.course_id', '=', 'courses.id')
            ->where('grades.colegio_id', $colegioId)
            ->whereNotNull('grades.score')
            ->selectRaw('avg(grades.score) as promedio')
            ->groupBy('students.id', 'students.name', 'courses.subject_name', 'courses.grade')
            ->orderBy('promedio')
            ->limit(8)
            ->get();

        if ($rows->isEmpty()) {
            return [
                'message' => 'No tengo calificaciones suficientes para identificar bajo rendimiento.',
                'data' => ['students' => []],
            ];
        }

        $labels = $rows->map(fn ($row) => "{$row->name} en {$row->subject_name} {$row->grade} (prom. {$row->promedio})")->implode(', ');

        return [
            'message' => 'Alumnos con menor rendimiento registrado: '.$labels.'.',
            'data' => ['students' => $rows],
        ];
    }

    private function extractTeacherName(string $text): ?string
    {
        $patterns = [
            '/llamad[oa]\s+([A-Za-zÁÉÍÓÚáéíóúÑñ]+(?:\s+[A-Za-zÁÉÍÓÚáéíóúÑñ]+){0,3}?)(?=\s+(?:que\s+|va\s+a|para\s+|de\s+[1-6]|y\s+|tambien|también)|[,.]|$)/iu',
            '/con\s+(?:el\s+|la\s+)?profesor(?:a)?\s+(.+)$/iu',
            '/profesor(?:a)?\s+de\s+[A-Za-zÁÉÍÓÚáéíóúÑñ]+\s+llamad[oa]\s+([A-Za-zÁÉÍÓÚáéíóúÑñ][A-Za-zÁÉÍÓÚáéíóúÑñ\s]{1,80}?)(?:\s+(?:y|para|con|tambien|también)|,|\.|$)/iu',
            '/profesor(?:a)?\s+(?:llamad[oa]\s+)?([A-Za-zÁÉÍÓÚáéíóúÑñ]+(?:\s+[A-Za-zÁÉÍÓÚáéíóúÑñ]+){0,3}?)\s+(?:tambien|también|que\s+te|ademas|además|y\s+crea)/iu',
            '/llamad[oa]\s+([A-Za-zÁÉÍÓÚáéíóúÑñ][A-Za-zÁÉÍÓÚáéíóúÑñ\s]{1,80}?)(?:\s+(?:y|para|con|de\s+[1-6]|tambien|también)|,|\.|$)/iu',
            '/nombre(?:\s+es)?\s+([A-Za-zÁÉÍÓÚáéíóúÑñ][A-Za-zÁÉÍÓÚáéíóúÑñ\s]{1,80}?)(?:\s+(?:y|para|tambien|también)|,|\.|$)/iu',
            '/profesor(?:a)?\s+de\s+[A-Za-zÁÉÍÓÚáéíóúÑñ]+\s+(?:llamad[oa]\s+)?(.+?)(?:\s+(?:y\s+|para\s+|con\s+|tambien|también)|,|\.|$)/iu',
            '/profesor(?:a)?\s+(.+?)(?:\s+(?:donde|al\s+que|con\s+la|con\s+el|y\s+quiero|y\s+as[ií]gna|y\s+agrega|y\s+crea|que\s+crea|para\s+as[ií]gna|tambien|también|dara|dará)|,|\.|$)/iu',
            '/(?:as[ií]gna(?:le)?|agrega(?:le)?|asignar)\s+(?:los\s+cursos\s+|las\s+materias\s+)?(?:a\s+)?([A-Za-zÁÉÍÓÚáéíóúÑñ][A-Za-zÁÉÍÓÚáéíóúÑñ\s]{1,80})$/iu',
            '/^(.+?)\s+(?:dara|dará|asigna)/iu',
            '/(?:as[ií]gna(?:le)?|agrega(?:le)?|asignar|dara|dará)\s+(?:a\s+|al\s+)?([A-Za-zÁÉÍÓÚáéíóúÑñ][A-Za-zÁÉÍÓÚáéíóúÑñ\s]{1,80}?)(?:\s+(?:la\s+materia|el\s+curso|la\s+asignatura|robotica|rob[oó]tica|matematic|ingl[eé]s|lenguaje|ciencias|historia|fisica|quimica|biologia|geografia|de\s+[1-6]|para\s+el|del\s+curso)|,|\.|$)/iu',
            '/^(?:a\s+|al\s+)([A-Za-zÁÉÍÓÚáéíóúÑñ][A-Za-zÁÉÍÓÚáéíóúÑñ\s]{1,80}?)(?:\s+(?:as[ií]gna(?:le)?|dara|dará|agrega(?:le)?))/iu',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $m)) {
                $name = $this->sanitizePersonName((string) $m[1]);
                if ($name) {
                    return $name;
                }
            }
        }

        return $this->sanitizePersonName($this->extractNamedPersonAfterRole($text, 'profesor'));
    }

    /**
     * Extrae uno o varios nombres de profesor de un mismo mensaje,
     * separando conectores tipo "y al profesor", "y profesor", ", al profesor".
     *
     * @return array<int,string>
     */
    private function extractTeacherNames(string $text): array
    {
        $pattern = '/(?:a\s+)?(?:al\s+|a la\s+|el\s+|la\s+|un\s+|una\s+)?(?:profesor(?:a)?|docente|maestr[oa])\s+'
            .'(?:llamad[oa]\s+)?'
            .'([A-Za-zÁÉÍÓÚáéíóúÑñ][A-Za-zÁÉÍÓÚáéíóúÑñ\s\'-]{0,120}?)'
            .'(?=\s+(?:(?:y\s+|,\s*)?(?:a\s+)?(?:al\s+|a la\s+|el\s+|la\s+)?(?:profesor(?:a)?|docente|maestr[oa])'
            .'|de\s+[1-6](?:ro|er|do|to|°|º)?\s*(?:grado)?'
            .'|que\s+va|va\s+a\s+dar'
            .'|(?:de|para|en|del)\s+(?:el\s+|la\s+)?(?:curso|grado|materia|asignatura|seccion|sección))\b|$)/iu';

        if (! preg_match_all($pattern, $text, $matches)) {
            return [];
        }

        $names = [];
        foreach ($matches[1] as $raw) {
            $name = $this->sanitizePersonName($raw);
            if ($name !== null && ! $this->isGenericPersonLabel($name)) {
                $names[] = $name;
            }
        }

        return array_values(array_unique($names));
    }

    private function extractNamedPersonAfterRole(string $text, string $role): ?string
    {
        $rolePattern = $role === 'profesor'
            ? 'profesor(?:a)?'
            : ($role === 'alumno' ? 'alumn[oa]s?' : 'estudiantes?');

        if (! preg_match('/(?:al?\s+)?'.$rolePattern.'\s+(.+)$/iu', $text, $m)) {
            return null;
        }

        $name = trim((string) $m[1]);
        $name = trim(preg_replace('/[.!?]+$/', '', $name) ?? $name);
        $name = trim(preg_replace('/^(?:al|a la|el|la|los|las)\s+/iu', '', $name) ?? $name);
        $name = preg_replace(
            '/\s+(?:en el|en la|al curso|de primer|de 1|en 1|a\s+[1-6]|a\s+(?:primer|segundo|tercer|cuarto|quinto|sexto)|y as[ií]gna|y inscr[ií]be|y matr[ií]cula|y crea|(?:e|y)\s+(?:int[eé]gr|matr[ií]cul|inscr[ií]b|asign|agreg)[a-záéíóúñ]*|tambien|también|que te|y\s+(?:a\s+)?(?:al|el|la)\s+(?:profesor(?:a)?|docente|maestr[oa])).*$/iu',
            '',
            $name
        ) ?? $name;

        return $this->sanitizePersonName($name);
    }

    private function isGenericPersonLabel(string $value): bool
    {
        $normalized = $this->normalizedText($value);

        return (bool) preg_match('/^(?:los|las|todos|todas|que hay|existentes?|registrados?|del colegio|de la escuela)(?:\s|$)/', $normalized)
            || (bool) preg_match('/\b(?:que hay|existentes?|registrados?)\b/', $normalized);
    }

    /**
     * @param  array<int,array{intent:string,data:array}>  $actions
     * @return array<int,array{intent:string,data:array}>
     */
    private function enrichActionsFromText(array $actions, string $text): array
    {
        $knownSubject = $this->extractKnownSubject($text);
        $subject = $knownSubject
            ?? $this->extractSubject($text)
            ?? $this->extractSubjectFromCoursePrompt($text)
            ?? $this->extractSubjectFromDeletePrompt($text);
        if ($subject && ! $this->isValidCourseSubject((string) $subject)) {
            $subject = $knownSubject;
        }
        $grades = $this->extractGrades($text);
        $teacher = $this->sanitizePersonName($this->extractTeacherName($text));
        $teacherActions = collect($actions)->where('intent', 'create_teacher');
        $hasMultipleTeachers = $teacherActions->count() >= 2;
        $hasPerTeacherSubjects = $teacherActions
            ->filter(fn ($action) => trim((string) ($action['data']['subject_name'] ?? '')) !== '')
            ->count() >= 2;

        foreach ($actions as &$action) {
            $intent = (string) ($action['intent'] ?? '');
            $data = (array) ($action['data'] ?? []);
            if (! empty($data['teacher_name'])) {
                $data['teacher_name'] = $this->sanitizePersonName((string) $data['teacher_name']) ?? $data['teacher_name'];
            }
            if (in_array($intent, ['create_teacher', 'create_course', 'assign_teacher'], true)) {
                $skipSharedCourseCopy = $intent === 'create_teacher' && $hasMultipleTeachers && $hasPerTeacherSubjects;
                if (! $skipSharedCourseCopy && empty($data['subject_name']) && $subject) {
                    $data['subject_name'] = $subject;
                }
                if (! $skipSharedCourseCopy) {
                    $data['grades'] = GradeLabel::preferExpandedRange((array) ($data['grades'] ?? []), $text);
                    if ($data['grades'] === [] && $grades !== []) {
                        $data['grades'] = $grades;
                    }
                }
                if (empty($data['teacher_name']) && $teacher) {
                    $data['teacher_name'] = $teacher;
                }
            }
            if (in_array($intent, ['create_students_batch', 'enroll_students_course', 'unenroll_students_course', 'delete_student'], true)) {
                if (empty($data['grade']) && ($grades[0] ?? null) && $intent !== 'delete_student') {
                    $data['grade'] = $grades[0];
                }
                if (empty($data['subject_name']) && $subject && $intent !== 'delete_student') {
                    $data['subject_name'] = $subject;
                }
                $parsedNames = $intent === 'delete_student' ? [] : $this->extractStudentNames($text);
                $existingNames = collect($data['names'] ?? [])
                    ->map(fn ($name) => $this->sanitizePersonName((string) $name))
                    ->filter()
                    ->unique()
                    ->values();
                if ($parsedNames !== [] && (count($parsedNames) > $existingNames->count() || $existingNames->isEmpty())) {
                    $data['names'] = $parsedNames;
                } elseif ($existingNames->isNotEmpty()) {
                    $data['names'] = $existingNames->all();
                }
                if (! empty($data['student_name'])) {
                    $data['student_name'] = $this->sanitizePersonName((string) $data['student_name']) ?? $data['student_name'];
                }
            }
            $action['data'] = $data;
        }
        unset($action);

        return $actions;
    }

    private function sanitizePersonName(?string $name): ?string
    {
        $name = app(PersonNameSanitizer::class)->cleanTeacher($name);
        if ($name === null) {
            return null;
        }

        $name = preg_replace(
            '/\s+(?:y\s+)?(?:agrega(?:lo|le|s)?|as[ií]gna(?:lo|le)?|crea(?:r)?(?:s|me|les)?|quiero|donde|con\s+(?:los|las|el|la)|cursos?|materias?|asignaturas?).*$/iu',
            '',
            $name
        ) ?? $name;
        $name = preg_replace(
            '/\s+(?:y\s+|,\s*)?(?:a\s+)?(?:al\s+|a la\s+|el\s+|la\s+)?(?:profesor(?:a)?|docente|maestr[oa])\b.*$/iu',
            '',
            $name
        ) ?? $name;
        $name = trim($name, " \t\n\r\0\x0B,.");
        if ($name === '' || $this->isGenericPersonLabel($name) || mb_strlen($name) > 80) {
            return null;
        }

        return app(PersonNameSanitizer::class)->titleCase($name);
    }

    /**
     * @param  array<int,array{intent:string,data:array}>  $actions
     * @param  array<int,array{intent:string,data:array}>  $localBatch
     * @return array<int,array{intent:string,data:array}>
     */
    private function preferLocalTeacherBatch(array $actions, array $localBatch, string $text): array
    {
        if (count($localBatch) < 2) {
            return $actions;
        }
        if (preg_match('/\b(?:alumn[oa]s?|estudiantes?)\b/iu', $text)) {
            return $actions;
        }

        $kept = array_values(array_filter(
            $actions,
            fn ($action) => ! in_array($action['intent'] ?? '', ['create_teacher', 'assign_teacher', 'create_course'], true)
        ));

        return array_values(array_merge($localBatch, $kept));
    }

    /**
     * Si el extractor local encuentra varias intenciones claras, reemplaza las
     * acciones de nómina del LLM por las locales. Evita que "Carlos Gutiérrez"
     * se confunda con profesor cuando en realidad es alumno a matricular.
     *
     * @param  array<int,array{intent:string,data:array}>  $actions
     * @param  array<int,array{intent:string,data:array}>  $localIntentions
     * @return array<int,array{intent:string,data:array}>
     */
    private function preferLocalMultipleIntentions(array $actions, array $localIntentions): array
    {
        if (count($localIntentions) < 2) {
            return $actions;
        }

        $writableIntents = [
            'create_teacher', 'create_students_batch', 'enroll_students_course',
            'assign_teacher', 'create_course', 'update_student', 'delete_student',
            'unenroll_students_course', 'unassign_teacher', 'update_course', 'delete_course',
        ];

        $kept = array_values(array_filter(
            $actions,
            fn ($action) => ! in_array($action['intent'] ?? '', $writableIntents, true)
        ));

        return array_values(array_merge($localIntentions, $kept));
    }

    /**
     * Extrae todas las acciones de un mensaje con varias personas (texto o voz).
     *
     * @return array<int,array{intent:string,data:array}>
     */
    private function extractMultipleActions(User $director, string $text): array
    {
        $span = $this->teacherListSpan($text);
        $normalizedSpan = $this->normalizedText($span);
        if (
            preg_match('/\b(?:alumn[oa]s?|estudiantes?)\b/u', $normalizedSpan)
            && ! $this->looksLikeStaffingList($span)
        ) {
            return [];
        }
        $roster = $this->parseTeacherRosterList($director, $span);
        if (count($roster) >= 2) {
            return $roster;
        }

        $names = $this->extractMultipleNames($span);
        if (count($names) < 2) {
            return [];
        }
        if (count($names) > 20) {
            throw ValidationException::withMessages([
                'teacher' => 'Veo más de 20 profesores en tu mensaje. Parte la lista en dos envíos.',
            ]);
        }

        $withOwnSubject = 0;
        $prepared = [];
        foreach ($names as $name) {
            $slice = $this->sliceForName($span, $name, $names);
            $subject = $this->extractKnownSubject($slice);
            $grades = $this->extractGrades($slice);
            if ($subject) {
                $withOwnSubject++;
            }
            $prepared[] = [
                'name' => $name,
                'subject' => $subject,
                'grades' => $grades,
            ];
        }

        $sharedSubject = $withOwnSubject === 0 ? $this->extractKnownSubject($span) : null;
        $sharedGrades = $withOwnSubject === 0 ? $this->extractGrades($span) : [];

        $actions = [];
        foreach ($prepared as $item) {
            $subject = $item['subject'] ?? $sharedSubject;
            $grades = $item['grades'] !== [] ? $item['grades'] : $sharedGrades;
            $actions[] = [
                'intent' => 'create_teacher',
                'data' => [
                    'teacher_name' => $item['name'],
                    'subject_name' => $subject,
                    'grades' => $grades,
                    'missing_grades' => $this->missingGradesFor($director, $grades),
                    'expires_in_days' => 30,
                ],
            ];
        }

        return $this->dedupeDetectedActions($actions);
    }

    /**
     * Extrae todos los nombres de una lista: "María Clara, Ricardo Gutiérrez y Juan Carlos Guido".
     *
     * @return array<int,string>
     */
    private function extractMultipleNames(string $text): array
    {
        $normalized = $this->normalizedText($text);
        $isTeacherContext = $this->wantsCreateTeacherPhrase($normalized)
            || $this->looksLikeStaffingList($text)
            || (bool) preg_match('/\b(?:profesor(?:a|es)?|docentes?|maestro[as]?)\b/u', $normalized);
        if (! $isTeacherContext) {
            return [];
        }

        if (preg_match('/(?:profesor(?:a|es)?|docentes?|maestro[as]?)\b[^:]{0,180}[:\-]\s*(.+)$/ius', $text, $m)) {
            $names = $this->splitAndSanitizeNames(trim((string) $m[1]));
            if (count($names) >= 2) {
                return $names;
            }
        }

        if (preg_match(
            '/(?:crea(?:r|me|es|e|o)?|agrega(?:r)?|registra(?:r)?|invita(?:r)?)\s+(?:a\s+|al\s+)?(?:los\s+|las\s+|me\s+)?(?:siguientes\s+)?(?:profesor(?:a|es)?|docentes?|maestro[as]?)\s*:?\s*(.+)$/ius',
            $text,
            $m
        )) {
            $names = $this->splitAndSanitizeNames(trim((string) $m[1]));
            if (count($names) >= 2) {
                return $names;
            }
        }

        if (preg_match('/(?:crea(?:r|me)?|agrega(?:r)?|registra(?:r)?)\s+a\s+(.+)$/ius', $text, $m)) {
            $chunk = preg_replace(
                '/\b(?:los\s+|las\s+)?(?:siguientes\s+)?(?:profesor(?:a|es)?|docentes?|maestro[as]?)\s*:?\s*/iu',
                '',
                (string) $m[1]
            ) ?? (string) $m[1];
            $names = $this->splitAndSanitizeNames(trim($chunk));
            if (count($names) >= 2) {
                return $names;
            }
        }

        return $this->extractTeacherNames($text);
    }

    /**
     * Recorta el mensaje a la parte de profesores, sin la cláusula de alumnos.
     */
    private function teacherListSpan(string $text): string
    {
        $clauses = $this->splitIntentClauses($text);
        $teacherBits = [];
        foreach ($clauses as $clause) {
            $value = $this->normalizedText($clause);
            if ($this->wantsCreateTeacherPhrase($value) || $this->looksLikeStaffingList($clause)) {
                $teacherBits[] = $clause;
            }
        }

        return $teacherBits !== [] ? implode(' ', $teacherBits) : $text;
    }

    /**
     * @param  array<int,string>  $allNames
     */
    private function sliceForName(string $text, string $name, array $allNames): string
    {
        $pos = mb_stripos($text, $name);
        if ($pos === false) {
            return '';
        }

        $end = mb_strlen($text);
        foreach ($allNames as $other) {
            if (mb_strtolower($other) === mb_strtolower($name)) {
                continue;
            }
            $otherPos = mb_stripos($text, $other, $pos + 1);
            if ($otherPos !== false && $otherPos < $end) {
                $end = $otherPos;
            }
        }

        return trim(mb_substr($text, $pos, $end - $pos));
    }

    private function wantsCreateTeacherPhrase(string $value): bool
    {
        if (preg_match(
            '/\b(?:crea(?:r|me)?|crees|cree|creo|invita|agrega(?:r)?|registra(?:r)?)\b(?:\s+(?:a|al|el|la|los|las|me|un|una|uno|nuevo|nueva)){0,4}\s+(?:siguientes?\s+)?(?:profesor(?:a|es)?|docentes?|maestro[as]?)\b/u',
            $value
        )) {
            return true;
        }

        // "tiene un profesor llamado Vicente que va a dar matemáticas"
        if (! $this->hasDeleteVerb($value)
            && preg_match('/\b(?:profesor(?:a)?|docente|maestro[as]?)\s+llamad[oa]\b/u', $value)
            && preg_match('/\b(?:crea|crear|crees|agrega|invita|tiene|hay|nuevo|nueva|ingresa|va a dar|dara|dará|dicta|imparte|enseña)\b/u', $value)) {
            return true;
        }

        return false;
    }

    private function looksLikeTeacherStaffing(string $text): bool
    {
        $value = $this->normalizedText($text);

        return $this->wantsCreateTeacherPhrase($value)
            || (
                preg_match('/\b(?:profesor(?:a)?|docente|maestro[as]?)\b/u', $value)
                && preg_match('/\b(?:llamad[oa]|va a dar|dara|dará|imparte|enseña)\b/u', $value)
                && ! $this->hasDeleteVerb($value)
            );
    }

    private function looksLikeCapabilityMenu(?string $text): bool
    {
        if ($text === null || trim($text) === '') {
            return false;
        }

        $value = mb_strtolower($text);

        return str_contains($value, 'puedo crear y eliminar profesores')
            || (str_contains($value, 'ejemplos:') && str_contains($value, 'crea al profesor'));
    }

    private function looksLikeStaffingList(string $text): bool
    {
        $value = $this->normalizedText($text);

        return (bool) preg_match('/\b(?:profesor(?:a)?|docente)s\b/', $value)
            && (bool) preg_match('/\b(?:crea|crear|crees|creame|invita|siguientes|estos|lista)\b/', $value);
    }

    /**
     * Lista tipo: "crea a los siguientes profesores: Jorge Alarcón (inglés de 1ro a 6to), ..."
     *
     * @return array<int,array{intent:string,data:array}>
     */
    private function parseTeacherRosterList(User $director, string $text): array
    {
        if (! $this->looksLikeStaffingList($text) && ! preg_match('/\b(?:crea|crear|crees|creame|invita)\b/u', $this->normalizedText($text))) {
            return [];
        }

        $subjectGrade = '/\b(ingl[eé]s|matem[aá]ticas?|lenguaje|lengua|ciencias?|historia|geograf[ií]a|f[ií]sica|qu[ií]mica|biolog[ií]a|educaci[oó]n\s+f[ií]sica|rob[oó]tica|computaci[oó]n|religi[oó]n|educaci[oó]n\s+cristiana)\b\s+(?:de\s+)?([1-6](?:ro|do|to|ero|er)?)\s*(?:grado\s+)?(?:a|al|-|–|—)\s*([1-6](?:ro|do|to|ero|er)?)/iu';

        if (! preg_match_all($subjectGrade, $text, $matches, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $actions = [];
        $cursor = 0;
        foreach ($matches[0] as $index => $fullMatch) {
            $fullStart = (int) $fullMatch[1];
            $chunk = substr($text, $cursor, max(0, $fullStart - $cursor));
            $cursor = $fullStart + strlen((string) $fullMatch[0]);

            $name = $this->extractTrailingPersonName($chunk);
            $subject = $this->extractKnownSubject((string) $matches[1][$index][0]);
            $from = (int) $matches[2][$index][0];
            $to = (int) $matches[3][$index][0];

            if (! $name || ! $subject || $from < 1 || $to < $from || $to > 6) {
                continue;
            }

            $grades = collect(range($from, $to))->map(fn ($n) => $this->formatGrade((int) $n))->all();
            $actions[] = [
                'intent' => 'create_teacher',
                'data' => [
                    'teacher_name' => $name,
                    'subject_name' => $subject,
                    'grades' => $grades,
                    'missing_grades' => $this->missingGradesFor($director, $grades),
                    'expires_in_days' => 30,
                ],
            ];
        }

        if (count($actions) > 20) {
            throw ValidationException::withMessages([
                'teacher' => 'Veo más de 20 profesores en tu mensaje. Parte la lista en dos envíos.',
            ]);
        }

        return $this->dedupeDetectedActions($actions);
    }

    private function extractTrailingPersonName(string $chunk): ?string
    {
        $chunk = preg_replace('/[(:\-,;]\s*$/u', '', $chunk) ?? $chunk;
        $chunk = preg_replace(
            '/\b(?:profesor(?:a|es)?|docentes?|maestr[oa]s?|siguientes?|crea(?:r|me|es|e|o)?|quiero|que|me|los|las|al|a)\b/iu',
            ' ',
            $chunk
        ) ?? $chunk;
        $chunk = trim($chunk, " \t\n\r,:;()");
        if ($chunk === '') {
            return null;
        }

        if (! preg_match('/([A-Za-zÁÉÍÓÚáéíóúÑñ][A-Za-zÁÉÍÓÚáéíóúÑñ\'.-]+(?:\s+[A-Za-zÁÉÍÓÚáéíóúÑñ\'.-]+){0,3})\s*$/u', $chunk, $m)) {
            return null;
        }

        return $this->sanitizePersonName((string) $m[1]);
    }

    /**
     * @return array<string,string>
     */
    private function knownSubjectAliases(): array
    {
        return [
            'ingles' => 'Inglés',
            'matematica' => 'Matemática',
            'matematicas' => 'Matemática',
            'lenguaje' => 'Lenguaje',
            'lengua' => 'Lengua',
            'ciencias' => 'Ciencias',
            'historia' => 'Historia',
            'geografia' => 'Geografía',
            'fisica' => 'Física',
            'quimica' => 'Química',
            'biologia' => 'Biología',
            'educacion fisica' => 'Educación Física',
            'robotica' => 'Robótica',
            'computacion' => 'Computación',
            'religion' => 'Religión',
            'educacion cristiana' => 'Religión',
        ];
    }

    /**
     * @return array<int,string>
     */
    private function extractKnownSubjects(?string $text): array
    {
        if ($text === null || trim($text) === '') {
            return [];
        }

        $normalized = $this->normalizedText($text);
        $found = [];
        foreach ($this->knownSubjectAliases() as $alias => $canonical) {
            if (preg_match('/\b'.preg_quote($alias, '/').'\b/u', $normalized)) {
                $found[$canonical] = $canonical;
            }
        }

        return array_values($found);
    }

    private function extractKnownSubject(?string $text): ?string
    {
        $subjects = $this->extractKnownSubjects($text);

        return $subjects[0] ?? null;
    }

    private function sanitizeSubjectName(?string $subject): ?string
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

    private function utteranceMentionsCourses(string $text): bool
    {
        $value = $this->normalizedText($text);

        return (bool) preg_match('/\b(?:curso|materia|asignatura|grado|ingles|matematic)\b/', $value)
            || $this->extractGrades($text) !== [];
    }

    private function extractSubjectFromDeletePrompt(string $text): ?string
    {
        if (preg_match('/(?:curso|asignatura|materia)s?\s+(?:de\s+|del\s+)?([A-Za-zÁÉÍÓÚáéíóúÑñ][A-Za-zÁÉÍÓÚáéíóúÑñ\s]{1,60})$/iu', $text, $m)) {
            $subject = trim((string) $m[1]);
            $subject = trim(preg_replace('/\s+(?:de\s+)?[1-6](?:ro|er|do|to|°|º|ero)?\s*(?:grado)?.*$/iu', '', $subject) ?? $subject);
            if ($this->isValidCourseSubject($subject)) {
                return $this->titleCaseSubject($subject);
            }
        }

        return null;
    }

    private function hasDeleteVerb(string $value): bool
    {
        return (bool) preg_match('/\b(?:elimina(?:r)?|borra(?:r)?|quita(?:r)?|remover|remueve|limpia(?:r)?|cancel(?:a|ar|es)?)\b/', $value);
    }

    private function isMassPeopleTarget(string $value): bool
    {
        return (bool) preg_match('/\b(?:todos?|todas?|los|las)\s+(?:los\s+|las\s+)?(?:profesores|profesoras|docentes|alumnos|estudiantes)\b/', $value)
            || (bool) preg_match('/\b(?:profesores|profesoras|docentes|alumnos|estudiantes)\s+(?:que hay|existentes?|registrados?)\b/', $value);
    }

    private function isMassCourseTarget(string $value): bool
    {
        if (preg_match('/\b(?:curso|asignatura|materia)\s+(?:de|del)\b/', $value)) {
            return false;
        }

        return (bool) preg_match('/\b(?:todos?|todas?|los|las)\s+(?:los\s+|las\s+)?(?:cursos|asignaturas|materias)\b/', $value)
            || (bool) preg_match('/\b(?:cursos|asignaturas|materias)\s+(?:que hay|existentes?|registrados?)\b/', $value);
    }

    private function extractSubjectFromCoursePrompt(string $text): ?string
    {
        if (preg_match('/\b(?:primer|primero|segundo|tercer|tercero|cuarto|quinto|sexto|[1-6](?:ro|ero|do|to|er)?)\s*grado\s+de\s+([A-Za-zÁÉÍÓÚáéíóúÑñ][A-Za-zÁÉÍÓÚáéíóúÑñ\s]{1,40}?)(?:\s+(?:con|para|y|[:\-]|\.|$)|$)/iu', $text, $m)) {
            $subject = trim((string) $m[1]);
            $known = $this->extractKnownSubject($subject) ?? $this->titleCaseSubject($subject);
            if ($this->isValidCourseSubject($known)) {
                return $this->sanitizeSubjectName($known);
            }
        }

        // "cursos de inglés" / "materia de ingles" en cualquier parte del pedido.
        if (preg_match('/(?:cursos?|materias?|asignaturas?)\s+de\s+([A-Za-zÁÉÍÓÚáéíóúÑñ][A-Za-zÁÉÍÓÚáéíóúÑñ]{2,40})/iu', $text, $m)) {
            $subject = trim((string) $m[1]);
            $known = $this->extractKnownSubject($subject) ?? $this->titleCaseSubject($subject);
            if ($this->isValidCourseSubject($known)) {
                return $this->sanitizeSubjectName($known);
            }
        }

        // Forma 1: "los cursos de: 1ero, 2do...6to grado de INGLES" (lista de grados antes de la materia)
        if (preg_match('/(?:curso|cursos|cursso|asignatura|materia)s?\s+de\s*:?\s*([0-9].*?)\s+grado\s+de\s+([A-Za-zÁÉÍÓÚáéíóúÑñ][A-Za-zÁÉÍÓÚáéíóúÑñ\s]*?)(?:\s+(?:para|en|y|,|con)|\s*$)/iu', $text, $m)) {
            $subject = trim((string) $m[2]);
            if ($this->isValidCourseSubject($subject)) {
                return $this->sanitizeSubjectName($this->titleCaseSubject($subject));
            }
        }

        // Forma 2: "Crea Matemática para 4.º, 5.º y 6.º." o "Crea curso de Matemática para 4to" (verbo crear al inicio y materia antes de "para")
        if (preg_match('/^(?:cre(?:a|ar|arles|es|e|o)|creame)\s+(?:el\s+)?(?:curso\s+de\s+|cursos?\s+|cursso\s+|asignatura\s+|materia\s+)?([A-Za-zÁÉÍÓÚáéíóúÑñ][A-Za-zÁÉÍÓÚáéíóúÑñ\s]{2,60}?)\s+(?:para|en|de|del)\s+[1-6](?:ro|er|do|to|°|º|ero)?\s*(?:grado)?\b/iu', $text, $m)) {
            $subject = trim((string) $m[1]);
            if ($this->isValidCourseSubject($subject)) {
                return $this->sanitizeSubjectName($this->titleCaseSubject($subject));
            }
        }

        // Localizamos "curso/asignatura de" en el texto ORIGINAL para conservar
        // acentos y manejar variantes tipográficas ("cursso") sin desfases de índice.
        if (! preg_match('/(?:curso|cursso|asignatura)\s+de\s+(.+?)$/iu', $text, $m)) {
            return null;
        }
        $rest = trim((string) $m[1]);

        // Si el grado aparece antes de la materia ("1er grado de matematicas"),
        // lo descartamos: "1er grado de" -> "".
        $rest = preg_replace('/^(?:al\s+)?(?:primer|primero|segundo|tercer|tercero|cuarto|quinto|sexto|[1-6](?:ro|ero|do|to|er|º|°)?)\s*grado\s+(?:de\s+)?/iu', '', $rest) ?? $rest;

        // Cortamos en conectores/palabras reservadas y delimitadores de lista (:, -) (conserva acentos del original).
        $rest = preg_split('/\s*(?:[:\-]|\s+(?:para|en|del|de|al|a\s+la|con|seccion|sección|y|nivel)\s+)/iu', $rest)[0] ?? $rest;
        $rest = trim((string) $rest);
        $rest = trim(preg_replace('/^[1-6](?:ro|ero|do|to|er)?\.?\s*/iu', '', $rest) ?? '');

        if ($rest === '' || preg_match('/(grado|curso|asignatura|profesor|profesora|alumno|estudiante|docente|materia)$/iu', $rest)) {
            return null;
        }

        $subject = $this->titleCaseSubject($rest);
        if (mb_strlen($subject) < 2 || mb_strlen($subject) > 60 || preg_match('/\b(cursso|curso|asignatura)\b/iu', $subject)) {
            return null;
        }

        return $this->sanitizeSubjectName($subject);
    }

    private function isValidCourseSubject(string $subject): bool
    {
        $subject = trim($subject);
        if (mb_strlen($subject) < 2 || mb_strlen($subject) > 60) {
            return false;
        }

        return ! preg_match('/\b(?:grado|curso|asignatura|profesor|profesora|alumno|estudiante|docente|materia|cursso)\b/iu', $subject)
            && ! preg_match('/(ingles de|\bde\s+[1-6])$/iu', $subject);
    }

    private function titleCaseSubject(string $subject): string
    {
        return mb_convert_case(trim($subject), MB_CASE_TITLE, 'UTF-8');
    }

    private function extractSubject(string $text): ?string
    {
        $patterns = [
            '/(?:profesor(?:a)?|docente)\s+de\s+([A-Za-zÁÉÍÓÚáéíóúÑñ][A-Za-zÁÉÍÓÚáéíóúÑñ]{2,40})(?:\s+llamad|\s+y\s+|,|\.|$)/iu',
            '/(?:as[ií]gna(?:le)?|dara|dará)\s+([A-Za-zÁÉÍÓÚáéíóúÑñ\s]{3,50})\s+(?:de|del|para|en)\s+/u',
            '/(?:as[ií]gna(?:le)?|dara|dará)\s+([A-Za-zÁÉÍÓÚáéíóúÑñ\s]{3,50})(?:,|\.|$)/u',
            '/(?:materia|asignatura)\s+de\s+([A-Za-zÁÉÍÓÚáéíóúÑñ][A-Za-zÁÉÍÓÚáéíóúÑñ\s]{1,50}?)(?:\s+(?:con|para|de|en|a)\s+|,|\.|$)/iu',
            '/(?:agrega(?:le)?|asigna(?:le)?)\s+(?:la\s+materia\s+|las\s+materias\s+)?de?\s*([A-Za-zÁÉÍÓÚáéíóúÑñ][A-Za-zÁÉÍÓÚáéíóúÑñ\s]{1,50}?)(?:\s+(?:de|para|en|a)\s+|,|\.|$)/iu',
            '/(?:cursos?|materias?|asignaturas?)\s+de\s+([A-Za-zÁÉÍÓÚáéíóúÑñ][A-Za-zÁÉÍÓÚáéíóúÑñ]{2,40})/iu',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $m)) {
                $raw = trim($m[1]);

                return $this->sanitizeSubjectName($this->extractKnownSubject($raw) ?? trim($raw));
            }
        }

        return null;
    }

    /**
     * @return array<int,string>
     */
    private function extractGrades(string $text): array
    {
        $range = GradeLabel::expandRangeFromText($text);
        if ($range !== []) {
            return $range;
        }

        $value = mb_strtolower($text);
        $value = strtr($value, [
            'primer grado' => '1ro grado',
            'primero' => '1ro',
            'segundo' => '2do',
            'tercero' => '3ro',
            'tercer grado' => '3ro grado',
            'cuarto' => '4to',
            'quinto' => '5to',
            'sexto' => '6to',
            '1ero' => '1ro',
            '3ero' => '3ro',
        ]);

        if (preg_match('/([1-6])(?:ro|ero|er|°|º|do|to)?\s*\.{2,}\s*([1-6])(?:to|do|ro|ero|er|°|º)?/u', $value, $m)) {
            $from = (int) $m[1];
            $to = (int) $m[2];
            if ($from <= $to) {
                return collect(range($from, $to))->map(fn ($n) => $this->formatGrade((int) $n))->all();
            }
        }

        if (preg_match('/de\s+([1-6])(?:ro|ero|er|°|º|do|to)?\s*(?:grado)?\s+a(?:l)?\s+([1-6])(?:to|do|ro|ero|er|°|º)?/u', $value, $m)) {
            $from = (int) $m[1];
            $to = (int) $m[2];
            if ($from <= $to) {
                return collect(range($from, $to))->map(fn ($n) => $this->formatGrade((int) $n))->all();
            }
        }

        if (preg_match('/([1-6])(?:ro|ero|er|°|º|do|to)?\s*(?:grado)?\s*(?:a|al|-|–|—)\s*([1-6])(?:to|do|ro|ero|er|°|º)?/u', $value, $m)) {
            $from = (int) $m[1];
            $to = (int) $m[2];
            if ($from <= $to) {
                return collect(range($from, $to))->map(fn ($n) => $this->formatGrade((int) $n))->all();
            }
        }

        preg_match_all('/([1-6])(?:ro|ero|do|to|°|º)?\s*(?:grado)?/u', $value, $matches);
        $grades = collect($matches[1] ?? [])->map(fn ($n) => $this->formatGrade((int) $n))->unique()->values()->all();

        return $grades;
    }

    private function extractTargetGrade(string $text): ?string
    {
        $value = mb_strtolower($text);
        $value = strtr($value, [
            'primer grado' => '1ro grado',
            'primero' => '1ro',
            'segundo grado' => '2do grado',
            'segundo' => '2do',
            'tercer grado' => '3ro grado',
            'tercero' => '3ro',
            'cuarto grado' => '4to grado',
            'cuarto' => '4to',
            'quinto grado' => '5to grado',
            'quinto' => '5to',
            'sexto grado' => '6to grado',
            'sexto' => '6to',
            '1ero' => '1ro',
            '3ero' => '3ro',
        ]);

        if (preg_match('/al?\s+([1-6])(?:ro|ero|er|do|to|°|º)?\s*(?:er|do|to)?\s*grado/u', $value, $m)) {
            return $this->formatGrade((int) $m[1]);
        }
        if (preg_match('/([1-6])(?:ro|ero|er|do|to|°|º)\s*(?:grado)?/u', $value, $m)) {
            return $this->formatGrade((int) $m[1]);
        }

        return null;
    }

    /**
     * @return array<int,string>
     */
    private function extractStudentNames(string $text): array
    {
        // Lista después de "alumnos/alumnas/estudiantes ... :" aunque haya contexto de grado/sección/materia.
        if (preg_match('/(?:alumn[oa]s?|estudiantes?)\b[^:]{0,180}[:\-]\s*(.+)$/ius', $text, $m)) {
            $names = $this->splitAndSanitizeNames(trim((string) $m[1]));
            if ($names !== []) {
                return $names;
            }
        }

        // Último ":" del mensaje si lo que sigue parece una lista (comas o "y").
        if (preg_match('/:\s*([A-Za-zÁÉÍÓÚáéíóúÑñ][^:]{2,})$/u', $text, $m)) {
            $chunk = trim((string) $m[1]);
            if (preg_match('/,|\s+y\s+/u', $chunk)) {
                $names = $this->splitAndSanitizeNames($chunk);
                if (count($names) >= 2) {
                    return $names;
                }
            }
        }

        // Varios alumnos nombrados por separado, con o sin repetir "alumno/alumna" en cada uno:
        // "Crea al alumno Vicente José y a la alumna Georgina Vázquez", "Crea a Vicente José y a Georgina Vázquez".
        $connectorNames = $this->extractMultipleStudentNamesByConnectors($text);
        if (count($connectorNames) >= 2) {
            return $connectorNames;
        }

        $verb = '(?:agrega|agregar|matricula|matricular|inscribe|inscribir|crea|crear|crees|cree|creo|creame)';

        if (preg_match('/'.$verb.'\s+(?:a\s+|al\s+)?(?:los\s+|las\s+)?(?:siguientes\s+)?(?:alumn[oa]s?|estudiantes?)\b[^:]*[:\-]\s*(.+)$/iu', $text, $m)) {
            $names = $this->splitAndSanitizeNames(trim((string) $m[1]));
            if ($names !== []) {
                return $names;
            }
        }

        if (preg_match('/'.$verb.'\s+(?:a\s+|al\s+)?(?:los\s+|las\s+)?(?:siguientes\s+)?(?:alumn[oa]s?|estudiantes?)\s+(.+?)(?:\s+(?:para(?:\s+(?:el|la|[1-6]))?|al\s+(?:curso|grado)|en\s+(?:el|la|la\s+secci)|a\s+la|del\s+(?:curso|grado)))\b/iu', $text, $m)) {
            $names = $this->splitAndSanitizeNames(trim((string) $m[1]));
            if ($names !== []) {
                return $names;
            }
        }

        $single = $this->sanitizePersonName($this->extractNamedPersonAfterRole($text, 'alumno'))
            ?? $this->sanitizePersonName($this->extractNamedPersonAfterRole($text, 'estudiante'));
        if ($single) {
            return [$single];
        }

        if (count($connectorNames) === 1 && ! preg_match('/,/', $text)) {
            return $connectorNames;
        }

        if (preg_match('/'.$verb.'\s+(?:a|al)\s+(?:alumn[oa]|estudiante)\s+(.+?)\s+(?:y\s+)?(?:al|a la|en|asigna|inscribe|matricula)\s+/iu', $text, $m)) {
            $single = $this->sanitizePersonName(trim($m[1]));
            if ($single) {
                return [$single];
            }
        }

        if (! preg_match('/'.$verb.'\s+a?\s+(.+?)\s+(?:al|a la|en)\s+/iu', $text, $m)) {
            return [];
        }

        return $this->splitAndSanitizeNames(trim((string) $m[1]));
    }

    /**
     * Extrae varios nombres de alumnos introducidos por "a"/"al"/"a la" con o sin la
     * palabra de rol (alumno/alumna/estudiante) repetida antes de cada nombre. Cubre
     * frases como "Crea al alumno Vicente José y a la alumna Georgina Vázquez" y
     * "Crea a Vicente José y a Georgina Vázquez", donde el patrón de "y" simple con
     * un solo rol al principio no basta para separar ambos nombres.
     *
     * @return array<int,string>
     */
    private function extractMultipleStudentNamesByConnectors(string $text): array
    {
        if (! preg_match_all(
            '/(?:a\s+la\s+|al\s+|a\s+)(?:alumn[oa]s?\s+|estudiantes?\s+)?(?:llamad[oa]\s+)?([A-ZÁÉÍÓÚÑ][\p{L}]+(?:\s+[A-ZÁÉÍÓÚÑ][\p{L}]+){0,2})/u',
            $text,
            $matches
        )) {
            return [];
        }

        return collect($matches[1] ?? [])
            ->map(fn ($name) => $this->sanitizePersonName(trim((string) $name)))
            ->filter(fn ($name) => $name !== null && mb_strlen($name) >= 2)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int,string>
     */
    private function splitAndSanitizeNames(string $raw): array
    {
        $raw = preg_replace('/^(?:alumn[oa]|estudiante|a\s+|al\s+|el\s+|la\s+|los\s+|las\s+|siguientes\s+|para\s+(?:el|la)\s+)\s*/iu', '', $raw) ?? $raw;
        $raw = preg_replace('/^(?:para\s+(?:el|la)\s+(?:curso|grado|materia|asignatura)\s+(?:de\s+)?)\s*/iu', '', $raw) ?? $raw;
        $raw = preg_replace('/\s+y\s+/iu', ',', $raw) ?? $raw;

        return collect(explode(',', $raw))
            ->map(fn ($name) => $this->sanitizePersonName(trim($name)))
            ->filter(fn ($name) => $name !== null && mb_strlen($name) >= 2)
            ->unique()
            ->values()
            ->all();
    }

    private function extractSection(string $text): ?string
    {
        if (preg_match('/secci[oó]n\s+([A-Za-z0-9]+)/iu', $text, $m)) {
            $raw = mb_strtolower(trim((string) $m[1]));
            if (preg_match('/^(?:de|del|el|la|los|las|grado|curso)$/u', $raw)) {
                return null;
            }

            return strtoupper(trim((string) $m[1]));
        }

        if (preg_match('/[1-6](?:ro|ero|do|to|er|°|º)?(?:\s*grado)?\s+([A-Ca-c])\b/u', $text, $m)) {
            return strtoupper($m[1]);
        }

        return $this->dataAgent->extractSection($text);
    }

    /**
     * @param  array<int,string>  $grades
     * @return array<int,string>
     */
    private function missingGradesFor(User $director, array $grades): array
    {
        if ($grades === []) {
            return [];
        }

        $existing = $this->actionService->existingGradeKeys((int) $director->colegio_id);
        if ($existing->isEmpty()) {
            return [];
        }

        $missing = [];
        foreach ($grades as $grade) {
            $key = $this->actionService->normalizeGradeKey($grade);
            if ($key !== '' && ! $existing->contains($key)) {
                $missing[] = $grade;
            }
        }

        return $missing;
    }

    private function resolveTeacherForQuery(int $colegioId, string $teacherName): User
    {
        $match = app(PersonNameMatcher::class)->resolveTeacher($colegioId, $teacherName);

        if ($match->isAmbiguous() || $match->isNone()) {
            throw ValidationException::withMessages([
                'teacher' => $match->message ?? 'No encontré al profesor indicado en este colegio.',
            ]);
        }

        /** @var User $teacher */
        $teacher = $match->model;

        return $teacher;
    }

    private function resolveStudentForQuery(int $colegioId, string $studentName): Student
    {
        $match = app(PersonNameMatcher::class)->resolveStudent($colegioId, $studentName);

        if ($match->isAmbiguous() || $match->isNone()) {
            throw ValidationException::withMessages([
                'student' => $match->message ?? 'No encontré al alumno indicado en este colegio.',
            ]);
        }

        /** @var Student $student */
        $student = $match->model;

        return $student;
    }

    /**
     * @return array<int,array{status:string,message:string}>
     */
    private function mutationTimeline(string $state, string $finalMessage): array
    {
        $steps = [
            ['status' => 'analyzing', 'message' => 'Analizando tu mensaje...'],
            ['status' => 'planning', 'message' => 'Identificando acciones a ejecutar...'],
        ];

        if ($state === 'confirming') {
            $steps[] = ['status' => 'confirming', 'message' => $finalMessage];

            return $steps;
        }

        $steps[] = ['status' => 'executing', 'message' => 'Ejecutando acciones...'];
        $steps[] = [
            'status' => $state === 'rollback' ? 'error' : 'completed',
            'message' => $finalMessage,
        ];

        return $steps;
    }

    private function intentRequiresConfirmation(string $intent): bool
    {
        return in_array($intent, [
            'create_teacher',
            'create_subject',
            'create_course',
            'create_courses_batch',
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
            'sync_all_enrollments',
        ], true);
    }

    /**
     * Reconoce respuestas cortas de confirmación ("sí", "sí, créalos", "confirmo")
     * para completar la acción pendiente guardada en sesión.
     */
    private function isAffirmativeText(string $text): bool
    {
        $value = $this->affirmativeNormalized($text);
        if ($value === '') {
            return false;
        }

        $short = ['si', 'ok', 'okay', 'sip', 'dale', 'adelante', 'confirmo', 'confirmado', 'confirmar', 'proceder', 'procede', 'listo', 'hazlo', 'crealos', 'crealo', 'yes', 'yep', 'claro', 'correcto', 'afirmativo', 'exacto', 'de acuerdo', 'deacuerdo', 'por supuesto', 'claro que si', 'siguiente', 'eso es'];
        if (in_array($value, $short, true)) {
            return true;
        }

        if (preg_match('/^si(?:\s+(crealos|crealos igualmente|crearlos|crear todos|crear todas|crear estos|confirmo|confirmado|dale|adelante|hazlo|por favor|proceder|procede))?$/u', $value)) {
            return true;
        }

        if (preg_match('/^si\b/u', $value)
            && ! preg_match('/\buno por uno|\buna por una/u', $value)
            && preg_match('/\b(?:crealos|crearlos|crear todos|crear todas|crear estos|confirmo|confirmado|dale|adelante|hazlo|proceder|procede)\b/u', $value)
            && mb_strlen($value) <= 80
        ) {
            return true;
        }

        return false;
    }

    private function affirmativeNormalized(string $text): string
    {
        $value = mb_strtolower(trim($text));
        // Remover comillas, apóstrofes, signos de exclamación y puntuación accidental.
        $value = preg_replace('/[\'\"`´’‘¡!¿?*.,;:\-]/u', ' ', $value) ?? $value;
        // Normalizar acentos para que "sí" sea equivalente a "si".
        $value = strtr($value, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
        ]);

        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    private function isNegativeText(string $text): bool
    {
        $value = $this->normalizedText($text);
        $value = trim(preg_replace('/[.!?]+$/', '', $value) ?? $value);

        return (bool) preg_match('/^(?:no|cancelar?|cancela(?:r)?(?:\s+todo)?|olvidalo|dejalo|detente|mejor no)$/', $value);
    }

    private function formatGrade(int $n): string
    {
        return match ($n) {
            1 => '1ro',
            2 => '2do',
            3 => '3ro',
            4 => '4to',
            5 => '5to',
            6 => '6to',
            default => (string) $n,
        };
    }

    private function normalizedText(string $text): string
    {
        $value = mb_strtolower($text);

        $value = strtr($value, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ñ' => 'n',
        ]);

        // Frases introductorias que no aportan a la intención.
        $value = preg_replace('/^(?:yo\s+)?quiero\s+que\s*/u', '', $value) ?? $value;
        $value = preg_replace('/^(?:quiero|necesito|deber[íi]as)\s+/u', '', $value) ?? $value;
        $value = preg_replace('/\bpor favor\b/u', '', $value) ?? $value;

        // Variantes tipográficas comunes que deben interpretarse igual.
        $value = preg_replace('/\bcurs+o\b/', 'curso', $value) ?? $value;
        $value = preg_replace('/\b1(?:er|ero)?\s*grado\b/', '1er grado', $value) ?? $value;

        return $value;
    }

    // ── FASE HÍBRIDA: Menú + IA ───────────────────────────────────────
    private function detectHybridIntent(string $text): string
    {
        $t = $this->normalizedText($text);
        if (preg_match('/\b(?:crea|crear|nuevo|nueva|agregar|agrega|insertar)\b/u', $t) && ! preg_match('/\b(?:cuantos|como|quien|quienes|dime|dame|listado|ver|mostrar)\b/u', $t)) {
            return 'creating';
        }
        if (preg_match('/\b(?:elimina|eliminar|borrar|borra|quitar|sacar|remove|delete)\b/u', $t)) {
            return 'deleting';
        }
        if (preg_match('/\b(?:modificar|cambiar|actualizar|editar|cambia|modifica|actualiza|mover|pasar|asignar)\b/u', $t)) {
            return 'modifying';
        }
        if (preg_match('/\b(?:informe|reporte|resumen|panorama|como estamos|diagnostico)\b/u', $t)) {
            return 'reporting';
        }
        if (preg_match('/\b(?:cuantos|quien|quienes|como va|como van|dime|dame|listado|ver|mostrar|consultar|buscar|nombre|nombres|lista|listame)\b/u', $t) || preg_match('/\b(?:alumnos?|estudiantes?|profesores?|docentes?|cursos?|notas?|calificaciones?|asistencia|faltas|promedio|rendimiento)\b/u', $t)) {
            return 'consulting';
        }
        if (preg_match('/\b(?:revisa|verifica|confirma|es|son|esta|existe)\b/u', $t) && preg_match('/\b(?:profesor|alumno|estudiante|docente|curso)\b/u', $t)) {
            return 'consulting';
        }

        return 'unknown';
    }

    private function handleButton(string $buttonAction, ?string $customText, $director, array $screenContext): JsonResponse
    {
        $textMap = [
            'menu_consult' => 'quiero consultar información',
            'menu_create' => 'quiero crear algo nuevo',
            'menu_modify' => 'quiero modificar algo',
            'menu_delete' => 'quiero eliminar algo',
            'menu_report' => 'quiero un informe',
            'consult_students' => 'quiero ver alumnos',
            'consult_teachers' => 'quiero ver profesores',
            'consult_courses' => 'quiero ver cursos',
            'consult_grades' => 'quiero ver notas',
            'consult_attendance' => 'quiero ver asistencia',
            'create_student' => 'crear alumno',
            'create_teacher' => 'crear profesor',
            'create_course' => 'crear curso',
            'create_activity' => 'crear actividad',
            'modify_student' => 'modificar alumno',
            'modify_teacher' => 'modificar profesor',
            'modify_course' => 'modificar curso',
            'modify_grade' => 'modificar notas',
            'delete_student' => 'eliminar alumno',
            'delete_teacher' => 'eliminar profesor',
            'delete_course' => 'eliminar curso',
            'confirm_yes' => 'sí, confirmo',
            'confirm_no' => 'no, cancelar',
            'back' => 'volver al menú principal',
        ];
        $textToSend = $customText && trim($customText) !== '' ? $customText : ($textMap[$buttonAction] ?? $buttonAction);
        if ($buttonAction === 'back' || $buttonAction === 'menu_main') {
            session()->put(self::CHAT_MODE_KEY, 'main_menu');
            session()->forget(self::CHAT_SUBJECT_KEY);

            return $this->showMainMenu(null);
        }
        if ($buttonAction === 'confirm_one_by_one' && session()->has(self::PENDING_SESSION_KEY)) {
            return $this->startOneByOneReview();
        }
        if ($buttonAction === 'confirm_yes' && session()->has(self::PENDING_SESSION_KEY)) {
            return $this->executePendingBatch(request(), $director);
        }
        if ($buttonAction === 'confirm_no' && session()->has(self::PENDING_SESSION_KEY)) {
            $this->forgetPendingActions();
            $this->conversationContext->clearPendingReferences();
            session()->put(self::CHAT_MODE_KEY, 'main_menu');
            session()->forget(self::CHAT_SUBJECT_KEY);

            return response()->json(['success' => true, 'cancelled' => true, 'message' => 'Operación cancelada. No hice cambios.', 'buttons' => $this->mainMenuButtons(), 'mode' => 'main_menu']);
        }
        $modeMap = ['menu_consult' => 'consulting', 'menu_create' => 'creating', 'menu_modify' => 'modifying', 'menu_delete' => 'deleting', 'menu_report' => 'reporting', 'consult_' => 'consulting', 'create_' => 'creating', 'modify_' => 'modifying', 'delete_' => 'deleting'];
        foreach ($modeMap as $prefix => $mode) {
            if (str_starts_with($buttonAction, $prefix)) {
                session()->put(self::CHAT_MODE_KEY, $mode);
                break;
            }
        }
        $subjectMap = ['student' => 'students', 'teacher' => 'teachers', 'course' => 'courses', 'grade' => 'grades', 'attendance' => 'attendance'];
        foreach ($subjectMap as $key => $subject) {
            if (str_contains($buttonAction, $key)) {
                session()->put(self::CHAT_SUBJECT_KEY, $subject);
                break;
            }
        }

        // Si el botón ya trae toda la info, intentar ejecutar directo si es creación simple
        return $this->handleInMode($textToSend, session()->get(self::CHAT_MODE_KEY, 'main_menu'), session()->get(self::CHAT_SUBJECT_KEY), $director, $screenContext) ?? $this->askForSubject(session()->get(self::CHAT_MODE_KEY, 'main_menu'));
    }

    private function handleInMode(string $text, string $mode, ?string $subject, $director, array $screenContext): ?JsonResponse
    {
        // Si el texto ya es una instrucción completa, intentar ejecutarla con el modo actual como contexto
        // Para consulting, delegar al Data Agent con el subject como hint
        if ($mode === 'consulting' && $subject) {
            $hintMap = ['students' => 'alumnos', 'teachers' => 'profesores', 'courses' => 'cursos', 'grades' => 'notas', 'attendance' => 'asistencia'];
            $hint = $hintMap[$subject] ?? $subject;
            // Si el texto ya menciona el subject, usarlo directo
            if (mb_stripos($this->normalizedText($text), $hint) === false && mb_strlen($text) < 30) {
                // Texto corto sin subject, pedir detalle
                return $this->askForSubject($mode);
            }
        }
        // Para crear/modificar/eliminar, si el texto es solo el subject, pedir detalles
        if (in_array($mode, ['creating', 'modifying', 'deleting'], true) && $subject && mb_strlen(trim($text)) < 20 && preg_match('/^(?:quiero|crear|modificar|eliminar).*'.preg_quote($subject, '/').'/iu', $this->normalizedText($text))) {
            $labels = ['students' => 'alumno', 'teachers' => 'profesor', 'courses' => 'curso', 'grades' => 'nota', 'attendance' => 'asistencia'];
            $label = $labels[$subject] ?? $subject;

            return response()->json(['success' => false, 'needs_clarification' => true, 'message' => "Perfecto, dime los datos para {$label}. Por ejemplo: \"Crea al profesor Jose Marrero\" o \"Elimina al profesor Luis\".", 'buttons' => $this->subjectButtons($mode), 'mode' => $mode, 'subject' => $subject]);
        }

        return null;
    }

    public function showMainMenu(?string $message = null): JsonResponse
    {
        session()->put(self::CHAT_MODE_KEY, 'main_menu');
        session()->forget(self::CHAT_SUBJECT_KEY);

        return response()->json([
            'success' => true,
            'message' => $message ?? '¡Hola! Soy tu asistente. ¿Qué necesitas hacer?',
            'buttons' => $this->mainMenuButtons(),
            'mode' => 'main_menu',
        ]);
    }

    public function askForSubject(string $mode): JsonResponse
    {
        $subjectButtons = match ($mode) {
            'consulting' => [['id' => 'consult_students', 'label' => '👨‍🎓 Alumnos', 'color' => 'blue'], ['id' => 'consult_teachers', 'label' => '👨‍🏫 Profesores', 'color' => 'blue'], ['id' => 'consult_courses', 'label' => '📚 Cursos', 'color' => 'blue'], ['id' => 'consult_grades', 'label' => '📝 Notas', 'color' => 'blue'], ['id' => 'consult_attendance', 'label' => '📅 Asistencia', 'color' => 'blue'], ['id' => 'back', 'label' => '◀️ Volver', 'color' => 'gray']],
            'creating' => [['id' => 'create_student', 'label' => '👨‍🎓 Alumno', 'color' => 'green'], ['id' => 'create_teacher', 'label' => '👨‍🏫 Profesor', 'color' => 'green'], ['id' => 'create_course', 'label' => '📚 Curso', 'color' => 'green'], ['id' => 'create_activity', 'label' => '📝 Actividad', 'color' => 'green'], ['id' => 'back', 'label' => '◀️ Volver', 'color' => 'gray']],
            'modifying' => [['id' => 'modify_student', 'label' => '👨‍🎓 Alumno', 'color' => 'orange'], ['id' => 'modify_teacher', 'label' => '👨‍🏫 Profesor', 'color' => 'orange'], ['id' => 'modify_course', 'label' => '📚 Curso', 'color' => 'orange'], ['id' => 'modify_grade', 'label' => '📝 Notas', 'color' => 'orange'], ['id' => 'back', 'label' => '◀️ Volver', 'color' => 'gray']],
            'deleting' => [['id' => 'delete_student', 'label' => '👨‍🎓 Alumno', 'color' => 'red'], ['id' => 'delete_teacher', 'label' => '👨‍🏫 Profesor', 'color' => 'red'], ['id' => 'delete_course', 'label' => '📚 Curso', 'color' => 'red'], ['id' => 'back', 'label' => '◀️ Volver', 'color' => 'gray']],
            'reporting' => [['id' => 'back', 'label' => '◀️ Volver', 'color' => 'gray']],
            default => [],
        };
        $modeLabels = ['consulting' => 'consultar', 'creating' => 'crear', 'modifying' => 'modificar', 'deleting' => 'eliminar', 'reporting' => 'generar informe'];

        return response()->json([
            'success' => true,
            'message' => "¿Qué quieres {$modeLabels[$mode]}? Puedes tocar un botón o escribir lo que necesitas.",
            'buttons' => $subjectButtons,
            'mode' => $mode,
            'subject' => null,
        ]);
    }

    private function mainMenuButtons(): array
    {
        return [
            ['id' => 'menu_consult', 'label' => '📋 Consultar', 'color' => 'blue'],
            ['id' => 'menu_create', 'label' => '➕ Crear', 'color' => 'green'],
            ['id' => 'menu_modify', 'label' => '✏️ Modificar', 'color' => 'orange'],
            ['id' => 'menu_delete', 'label' => '🗑️ Eliminar', 'color' => 'red'],
            ['id' => 'menu_report', 'label' => '📊 Informes', 'color' => 'purple'],
        ];
    }

    private function subjectButtons(string $mode): array
    {
        return match ($mode) {
            'consulting' => [['id' => 'consult_students', 'label' => '👨‍🎓 Alumnos'], ['id' => 'consult_teachers', 'label' => '👨‍🏫 Profesores'], ['id' => 'consult_courses', 'label' => '📚 Cursos']],
            'creating' => [['id' => 'create_student', 'label' => '👨‍🎓 Alumno'], ['id' => 'create_teacher', 'label' => '👨‍🏫 Profesor'], ['id' => 'create_course', 'label' => '📚 Curso']],
            'deleting' => [['id' => 'delete_student', 'label' => '👨‍🎓 Alumno'], ['id' => 'delete_teacher', 'label' => '👨‍🏫 Profesor'], ['id' => 'delete_course', 'label' => '📚 Curso']],
            default => [],
        };
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Course;
use App\Models\CourseEvaluationPlan;
use App\Models\Evaluation;
use App\Models\EvaluationAttempt;
use App\Models\EvaluationQuestion;
use App\Models\Grade;
use App\Models\Notification;
use App\Models\Planificacion;
use App\Models\Student;
use App\Models\Tarea;
use App\Models\User;
use App\Services\DirectorAlertService;
use App\Support\LessonTemplate;
use Illuminate\Support\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AICommandHandlerController extends Controller
{
private const DESTRUCTIVE = ['destroyCourse', 'destroyAllStudentsFromCourse', 'deleteResource', 'deleteActivities'];

    private ?string $activeLessonTemplate = null;

    private function jsonOut(array $payload, int $status = 200): JsonResponse
    {
        $success = (bool) ($payload['success'] ?? false);
        $payload['status'] = $payload['status'] ?? ($success ? 'success' : 'error');
        if (! array_key_exists('data', $payload)) {
            $payload['data'] = $payload['actions'] ?? [];
        }

        return response()->json(
            $payload,
            $status,
            ['Content-Type' => 'application/json; charset=UTF-8'],
            JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );
    }

    private function activeLessonTemplateFor(?int $teacherId = null): string
    {
        if ($this->activeLessonTemplate) {
            return LessonTemplate::normalize($this->activeLessonTemplate);
        }
        if ($teacherId) {
            return LessonTemplate::forUser(User::find($teacherId));
        }

        return LessonTemplate::CLASSIC;
    }

    /**
     * Definiciones de herramientas para OpenAI
     */
    private function toolDefinitions(?string $lessonTemplate = null): array
    {
        $lessonTemplate = LessonTemplate::normalize((string) $lessonTemplate);
        $templateHeaders = LessonTemplate::promptLine($lessonTemplate);
        $templateLabel = LessonTemplate::label($lessonTemplate);

        return [
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'createCourse',
                    'description' => 'Crea un nuevo curso/sección para el profesor.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'subject_name' => ['type' => 'string',  'description' => 'Nombre de la materia, ej: Matemáticas'],
                            'grade'        => ['type' => 'string',  'description' => 'Grado, ej: 3ro Primaria'],
                            'section'      => ['type' => 'string',  'description' => 'Sección opcional, ej: A, B'],
                            'school_year'  => ['type' => 'string',  'description' => 'Año escolar, ej: 2025-2026'],
                        ],
                        'required' => ['subject_name', 'grade'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'createActivity',
                    'description' => 'Crea una clase (teórica), actividad evaluativa o tarea (homework) en un curso. Puede incluir adaptación NEE.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'course_id'         => ['type' => 'integer'],
                            'course_name_hint'  => ['type' => 'string'],
                            'type'              => ['type' => 'string', 'enum' => ['clase', 'actividad', 'tarea']],
                            'is_homework'       => ['type' => 'boolean'],
                            'nee_type'          => ['type' => 'string', 'description' => 'Tipo de necesidad (TDAH, TEA/Autismo, Dislexia, Discalculia, Otro)'],
                            'title'             => ['type' => 'string'],
                            'description'       => [
                                'type'        => 'string',
                                'description' => "Markdown obligatorio con la plantilla activa del profesor ({$templateLabel}). Encabezados EXACTOS en negrita y en este orden: {$templateHeaders}. No uses encabezados de otra plantilla. Mínimo 3 párrafos, viñetas y negritas en conceptos clave.",
                            ],
                            'max_score'         => ['type' => 'integer'],
                            'weight_percentage' => ['type' => 'number'],
                            'due_date'          => ['type' => 'string'],
                        ],
                        'required' => ['course_id', 'type', 'title', 'description', 'weight_percentage'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'modifyActivity',
                    'description' => 'Modifica una actividad o clase existente.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'activity_id'       => ['type' => 'integer'],
                            'title'             => ['type' => 'string'],
                            'description'       => ['type' => 'string'],
                            'due_date'          => ['type' => 'string'],
                            'weight_percentage' => ['type' => 'number'],
                        ],
                        'required' => ['activity_id'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'registerStudent',
                    'description' => 'Inscribe alumnos en un curso.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'names'            => ['type' => 'array', 'items' => ['type' => 'string']],
                            'course_id'        => ['type' => 'integer'],
                            'course_name_hint' => ['type' => 'string'],
                            'grade'            => ['type' => 'string'],
                        ],
                        'required' => ['course_id'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'bulkPlan',
                    'description' => 'Genera planificación mensual o por rango parcial para cualquier mes/año. Respeta días pedidos por el usuario. Lunes suele ser teoría/cuaderno, martes-miércoles práctica guiada y jueves práctica/lúdica. Cada sesión guarda descripción Markdown detallada.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'course_id'    => ['type' => 'integer'],
                            'topic'        => ['type' => 'string'],
                            'target_month' => [
                                'type' => 'string',
                                'description' => 'Mes a planificar en español minúsculas (ej: "abril", "mayo", "mayo 2026"). OBLIGATORIO: siempre pasar el nombre del mes del prompt del usuario.',
                            ],
                            'units'        => ['type' => 'array', 'items' => ['type' => 'object']],
                            'topics'       => ['type' => 'array', 'items' => ['type' => 'string']],
                            'calendar_preferences' => [
                                'type' => 'object',
                                'properties' => [
                                    'start_date' => ['type' => 'string'],
                                    'end_date' => ['type' => 'string'],
                                    'repeat_days' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Días en inglés: monday, tuesday, wednesday, thursday, friday. Si el usuario dice lunes martes miércoles, usa esos tres.'],
                                    'allow_past' => ['type' => 'boolean'],
                                    'override_conflicts' => ['type' => 'boolean'],
                                ],
                            ],
                            'confirmed' => ['type' => 'boolean', 'description' => 'Si true, crea directamente sin confirmación. Usa true cuando el usuario ya dio todos los datos (curso, mes, temas, días) y no hay ambigüedad.'],
                            'max_occurrences_per_day' => [
                                'type' => 'integer',
                                'description' => 'Máximo de veces que se repite cada día de la semana. Úsalo cuando el usuario diga «los primeros N lunes/martes/miércoles». Ejemplo: «los primeros 3 lunes y martes de julio» → max_occurrences_per_day: 3.',
                            ],
                        ],
                        'required' => ['course_id', 'target_month'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'deleteActivities',
                    'description' => 'BORRADO OBLIGATORIO en backend. Elimina múltiples actividades dentro de un rango de fechas para un curso. Úsalo cuando el usuario pida borrar varias clases/actividades en un mes o semana.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'course_id' => ['type' => 'integer'],
                            'start_date' => ['type' => 'string'],
                            'end_date' => ['type' => 'string'],
                            'override_conflicts' => ['type' => 'boolean'],
                        ],
                        'required' => ['course_id', 'start_date', 'end_date'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'deleteResource',
                    'description' => 'BORRADO OBLIGATORIO en backend. Elimina un recurso específico por ID (actividad, curso, alumno). Úsalo cuando el usuario pide borrar UNA actividad específica y ya identificaste su activity_id del calendario. Ejemplo: "borra la clase de matemáticas del jueves" → busca en calendario → encuentra "actividad_id 42" → llama deleteResource.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'resource_type' => [
                                'type' => 'string', 
                                'enum' => ['activity', 'student', 'course'],
                                'description' => 'Tipo de recurso a eliminar. Usa "activity" para borrar una actividad específica por ID.',
                            ],
                            'resource_id'   => [
                                'type' => 'integer',
                                'description' => 'ID único del recurso a eliminar. Para actividades, usa el activity_id que ves en el calendario inyectado (formato: "actividad_id 42").',
                            ],
                        ],
                        'required' => ['resource_type', 'resource_id'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'getCalendarContext',
                    'description' => 'Lee el calendario del docente en un rango de fechas.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'start_date' => ['type' => 'string'],
                            'end_date'   => ['type' => 'string'],
                            'course_id'  => ['type' => 'integer'],
                        ],
                        'required' => ['start_date', 'end_date'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'setGrade',
                    'description' => 'Guarda o actualiza una calificación para un alumno y actividad.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'student_id'  => ['type' => 'integer'],
                            'activity_id' => ['type' => 'integer', 'description' => 'ID de la actividad o del examen espejo'],
                            'evaluation_id' => ['type' => 'integer', 'description' => 'ID de la evaluación formal. Alternativa a activity_id.'],
                            'score'       => ['type' => 'number'],
                            'feedback'    => ['type' => 'string'],
                        ],
                        'required' => ['score'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'findStudent',
                    'description' => 'Busca alumnos por nombre y devuelve posibles coincidencias con IDs.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'query' => ['type' => 'string'],
                            'limit' => ['type' => 'integer'],
                        ],
                        'required' => ['query'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'getGradebookContext',
                    'description' => 'Lee el libro de calificaciones por actividad o curso (con promedios y alertas).',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'activity_id' => ['type' => 'integer'],
                            'course_id'   => ['type' => 'integer'],
                            'start_date'  => ['type' => 'string'],
                            'end_date'    => ['type' => 'string'],
                            'limit'       => ['type' => 'integer'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'getPedagogicalHistory',
                    'description' => 'Devuelve historial pedagógico reciente (actividades y planificaciones).',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'limit'       => ['type' => 'integer'],
                            'start_date'  => ['type' => 'string'],
                            'end_date'    => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'getCurrentWeek',
                    'description' => 'Consulta actividades de la semana actual (lunes-domingo). Devuelve respuesta JSON visual para el frontend.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'course_id' => ['type' => 'integer', 'description' => 'Filtrar por curso (opcional)'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'setGradeBatch',
                    'description' => 'Califica a MÚLTIPLES alumnos en una misma actividad de una sola vez. Úsalo cuando el usuario pida «ponles nota a todos», «califica a varios», «carga las notas del grupo» en lugar de llamar setGrade varias veces.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'activity_id' => ['type' => 'integer', 'description' => 'ID de la actividad a calificar'],
                            'evaluation_id' => ['type' => 'integer', 'description' => 'ID de la evaluación formal. Alternativa a activity_id.'],
                            'grades' => [
                                'type'  => 'array',
                    'items' => [
                        'type'       => 'object',
                        'properties' => [
                            'student_id'   => ['type' => 'integer', 'description' => 'ID del alumno (alternativa a student_name)'],
                            'student_name' => ['type' => 'string', 'description' => 'Nombre exacto del alumno (alternativa a student_id). Úsalo cuando el usuario dé el nombre en lugar del ID. Ej: "Jason"'],
                            'score'        => ['type' => 'number', 'description' => 'Nota del alumno (0 - max_score de la actividad)'],
                            'feedback'     => ['type' => 'string', 'description' => 'Retroalimentación opcional'],
                        ],
                    ],
                            ],
                        ],
                        'required' => ['activity_id', 'grades'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'publishGrades',
                    'description' => 'Publica TODAS las calificaciones en borrador de una actividad. Cambia el estado de draft a published. Úsalo cuando el usuario pida «publica las notas», «publica las calificaciones» de una actividad específica.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'activity_id' => ['type' => 'integer', 'description' => 'ID de la actividad cuyas notas se publicarán'],
                        ],
                        'required' => ['activity_id'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'createEvaluation',
                    'description' => 'Crea una evaluación formal (digital o física) en el módulo de Evaluaciones, genera preguntas con IA y opcionalmente la agrega al Plan de Evaluación del curso. Úsala cuando el usuario pida crear un examen, prueba, quiz o evaluación (NO uses createActivity para eso).',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'course_id' => ['type' => 'integer', 'description' => 'ID del curso. Si no lo conoces, usa course_name_hint.'],
                            'course_name_hint' => ['type' => 'string', 'description' => 'Nombre/grado del curso, ej: Matemáticas 1er grado'],
                            'title' => ['type' => 'string', 'description' => 'Título de la evaluación'],
                            'topic' => ['type' => 'string', 'description' => 'Tema o unidad'],
                            'prompt' => ['type' => 'string', 'description' => 'Descripción de qué debe evaluar (contenido, nivel, enfoque)'],
                            'mode' => ['type' => 'string', 'enum' => ['digital', 'physical'], 'description' => 'digital=online, physical=imprimible'],
                            'difficulty' => ['type' => 'string', 'enum' => ['basico', 'intermedio', 'avanzado']],
                            'question_mix' => ['type' => 'string', 'enum' => ['mixto', 'multiple_choice', 'true_false', 'open', 'completion']],
                            'question_count' => ['type' => 'integer', 'description' => 'Cantidad de preguntas (3-20). Default 8.'],
                            'weight_percentage' => ['type' => 'number', 'description' => 'Peso en el plan de evaluación (1-100). Default 10.'],
                            'category' => ['type' => 'string', 'enum' => ['formative', 'summative'], 'description' => 'Tipo pedagógico en el plan'],
                            'add_to_plan' => ['type' => 'boolean', 'description' => 'Si true, agrega automáticamente al Plan de Evaluación del curso'],
                            'status' => ['type' => 'string', 'enum' => ['draft', 'published'], 'description' => 'draft por defecto'],
                            'due_date' => ['type' => 'string', 'description' => 'Fecha YYYY-MM-DD opcional para el ítem del plan'],
                        ],
                        'required' => ['prompt'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'attachEvaluationToPlan',
                    'description' => 'Agrega una evaluación ya existente al Plan de Evaluación del curso. Úsala cuando el usuario diga «agrégala al plan», «súbela al plan de evaluación» o similar.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'evaluation_id' => ['type' => 'integer'],
                            'evaluation_title_hint' => ['type' => 'string', 'description' => 'Si no tienes ID, busca por título'],
                            'plan_id' => ['type' => 'integer', 'description' => 'Opcional. Si no se pasa, usa/crea el plan del curso de la evaluación'],
                            'weight_percentage' => ['type' => 'number'],
                            'category' => ['type' => 'string', 'enum' => ['formative', 'summative']],
                            'unit_name' => ['type' => 'string'],
                            'due_date' => ['type' => 'string'],
                        ],
                        'required' => [],
                    ],
                ],
            ],
        ];
    }

    /**
     * Punto de entrada principal
     */
    public function handle(Request $request): JsonResponse
    {
        try {
        @set_time_limit(120);
        @ini_set('max_execution_time', '120');
        // Intercepción local ANTES de validar o llamar a OpenAI
        $payload = $request->all();
        $rawMessage = (string) (
            data_get($payload, 'message')
            ?? data_get($payload, 'prompt')
            ?? data_get($payload, 'payload.mensaje_usuario')
            ?? data_get($payload, 'payload.prompt')
            ?? ''
        );
        $teacher = auth()->user();
        if (! $teacher) {
            return $this->jsonOut(['success' => false, 'error' => 'No autenticado.', 'message' => 'No autenticado.'], 401);
        }
        $requestedTemplate = $request->input('lesson_template');
        $this->activeLessonTemplate = is_string($requestedTemplate) && $requestedTemplate !== ''
            ? LessonTemplate::normalize($requestedTemplate)
            : LessonTemplate::forUser($teacher);
        $prompt = (string) (
            $request->input('prompt')
            ?? data_get($payload, 'payload.mensaje_usuario')
            ?? $rawMessage
        );
        $hasDeleteIntent = $this->hasDeleteIntent($prompt ?: $rawMessage);

        if (preg_match('/(borrar todo|limpiar todo|eliminar todo|vaciar todo|pizarra limpia)/i', $rawMessage)) {
            $teacherId = auth()->id();

            DB::table('tareas')
                ->whereIn('actividad_id', function ($query) use ($teacherId) {
                    $query->select('id')->from('activities')->where('teacher_id', $teacherId);
                })
                ->delete();

            DB::table('activities')->where('teacher_id', $teacherId)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Pizarra limpia.',
                'action' => 'refresh',
                'action_type' => 'delete',
                'icon' => '🗑️',
            ]);
        }

        $intentDeleteDates = $this->parseDatesFromText($rawMessage);
        $intentCourseId = $this->detectCourseContext($prompt ?: $rawMessage, $teacher);

        if ($hasDeleteIntent && !empty($intentDeleteDates)) {
            $courseId = $intentCourseId;
            $result = $this->doDeleteActivities([
                'course_id' => $courseId ?? 0,
                'start_date' => $intentDeleteDates[0]->format('Y-m-d'),
                'end_date' => end($intentDeleteDates)->format('Y-m-d'),
            ], $teacher->id);
            return response()->json($result);
        }

        $request->validate([
            'prompt' => ['sometimes', 'string', 'max:1000'],
            'message' => ['nullable', 'string'],
            'confirmed' => ['sometimes', 'boolean'],
            'screen_context' => ['sometimes', 'nullable', 'array'],
            'payload' => ['sometimes', 'array'],
            'payload.mensaje_usuario' => ['sometimes', 'string', 'max:1000'],
            'payload.contexto' => ['sometimes', 'nullable', 'array'],
            'conversation' => ['sometimes', 'array', 'max:40'],
            'conversation.*.role' => ['required_with:conversation', 'in:user,assistant'],
            'conversation.*.content' => ['required_with:conversation', 'string', 'max:12000'],
            'pending_actions' => ['sometimes', 'array', 'max:20'],
            'lesson_template' => ['sometimes', 'nullable', 'string', 'max:50'],
        ]);

        $wrappedPayload = $request->input('payload', []);
        $messageText = $request->input('message', '');
        
        $prompt = $request->input('prompt', $wrappedPayload['mensaje_usuario'] ?? $messageText);
        $confirmed = (bool) $request->input('confirmed', false);
        $screenContext = $request->input('screen_context', $wrappedPayload['contexto'] ?? null);
        $intentText = $prompt !== '' ? $prompt : $rawMessage;
        $hasDeleteIntent = $this->hasDeleteIntent($intentText);
        $hasModifyIntent = $this->hasModifyIntent($intentText);
        $hasPlanningIntent = $this->hasPlanningIntent($intentText);
        $hasCreateEvaluationIntent = $this->hasCreateEvaluationIntent($intentText);
        $explicitProceed = $this->hasProceedIntent($intentText);
        $deleteRange = $this->extractDateRangeFromText($intentText);

        Log::debug('AI_DELETE_ENTRY', [
            'teacher_id' => $teacher->id,
            'raw_message' => $rawMessage,
            'prompt' => $prompt,
            'intent_text' => $intentText,
            'has_delete_intent' => $hasDeleteIntent,
            'explicit_proceed' => $explicitProceed,
            'delete_range' => $deleteRange,
            'screen_course_id' => $screenContext['id'] ?? null,
        ]);

        if ($hasDeleteIntent && $deleteRange) {
            session()->put('nova_last_delete_args', array_filter([
                'course_id' => ! empty($screenContext['id']) ? (int) $screenContext['id'] : null,
                'start_date' => $deleteRange['start_date'],
                'end_date' => $deleteRange['end_date'],
            ], fn ($value) => $value !== null));
            Log::debug('AI_DELETE_SESSION', [
                'teacher_id' => $teacher->id,
                'nova_last_delete_args' => session('nova_last_delete_args'),
            ]);
        }

        $pendingActionsFromClient = $request->input('pending_actions');
        if ($confirmed && (session()->has('nova_pending_actions') || is_array($pendingActionsFromClient))) {
            $pendingToolCalls = session()->pull('nova_pending_actions', $pendingActionsFromClient);
            Log::info('AICommandHandler: executing confirmed pending actions', [
                'teacher_id' => $teacher->id,
                'tool_calls_count' => count($pendingToolCalls),
            ]);

            $pendingToolCalls = $this->dedupeAndOrderToolCalls($pendingToolCalls);
            $createdCourseMap = [];
            $results = [];
            foreach ($pendingToolCalls as $tc) {
                $fn = $tc['function']['name'];
                $args = $tc['function']['arguments'] ?? [];
                if (is_string($args)) {
                    $args = json_decode($args, true) ?? [];
                }
                if (! is_array($args)) {
                    $args = [];
                }
                if ($fn === 'bulkPlan') {
                    $args['confirmed'] = true;
                    $args = $this->enrichBulkPlanArgsFromIntent($args, $intentText, $teacher);
                }
                if (in_array($fn, ['createActivity', 'registerStudent', 'bulkPlan', 'deleteActivities', 'getCalendarContext', 'createEvaluation'], true)) {
                    $resolvedCourseId = $this->resolveCourseIdForArgs($args, $teacher->id, $createdCourseMap, $screenContext);
                    if ($resolvedCourseId > 0) {
                        $args['course_id'] = $resolvedCourseId;
                    }
                }
                $results[] = $this->executeAction($fn, $args, $teacher->id, $createdCourseMap);
            }

            $actions = collect($results)->map(function ($result) {
                $success = (bool) ($result['success'] ?? false);

                return [
                    'success'     => $success,
                    'status'      => $result['status'] ?? ($success ? 'success' : 'error'),
                    'message'     => $result['message'] ?? '',
                    'action_type' => $result['action_type'] ?? 'info',
                    'icon'        => $result['icon'] ?? ($success ? '✅' : 'ℹ️'),
                    'data'        => $result['data'] ?? [],
                ];
            })->toArray();

            $anySuccess = collect($actions)->contains(fn ($action) => $action['success']);
            $bulkMeta = $this->extractBulkPlanResponseMeta($results);

            return $this->jsonOut(array_filter([
                'success'      => true,
                'status'       => $bulkMeta ? 'success' : ($anySuccess ? 'success' : 'partial'),
                'actions'      => $actions,
                'any_success'  => $anySuccess,
                'bulk_plan'    => $bulkMeta,
                'message'      => $bulkMeta['assistant_message'] ?? null,
                'data'         => $actions,
            ], fn ($v) => $v !== null));
        }

        if (($confirmed || $explicitProceed) && ! session()->has('nova_pending_actions')) {
            $pendingDelete = session()->pull('nova_last_delete_args');
            Log::debug('AI_DELETE_PROCEED', [
                'teacher_id' => $teacher->id,
                'confirmed' => $confirmed,
                'explicit_proceed' => $explicitProceed,
                'pending_delete' => $pendingDelete,
            ]);
            if (is_array($pendingDelete) && isset($pendingDelete['start_date'], $pendingDelete['end_date'])) {
                $deleteArgs = array_merge([
                    'course_id' => $pendingDelete['course_id'] ?? null,
                ], $pendingDelete);
                $results = [$this->doDeleteActivities($deleteArgs, $teacher->id)];
                Log::debug('AI_DELETE_EXECUTED', [
                    'teacher_id' => $teacher->id,
                    'source' => 'proceed',
                    'delete_args' => $deleteArgs,
                    'result' => $results[0] ?? null,
                ]);
                return response()->json($this->buildActionResponsePayload($results));
            }

            if ($confirmed) {
                return response()->json([
                    'success' => false,
                    'message' => 'No encuentro una acción técnica pendiente. Dime otra vez qué quieres crear o ajustar y lo ejecuto con el contexto actual.',
                ]);
            }
        }

        $today = now()->format('Y-m-d');

        if ($hasCreateEvaluationIntent) {
            $evalArgs = $this->extractEvaluationArgsFromIntent($intentText, is_array($screenContext) ? $screenContext : [], $teacher);
            try {
                $results = [DB::transaction(fn () => $this->doCreateEvaluation($evalArgs, $teacher->id))];
            } catch (\Throwable $e) {
                Log::error('AICommandHandler local createEvaluation failed', [
                    'teacher_id' => $teacher->id,
                    'error' => $e->getMessage(),
                ]);
                $results = [[
                    'success' => false,
                    'status' => 'error',
                    'message' => 'No se pudo completar esta acción en este momento. Inténtalo de nuevo.',
                    'action_type' => 'evaluation',
                    'icon' => '⚠️',
                    'data' => [],
                ]];
            }

            return $this->jsonOut($this->buildActionResponsePayload($results));
        }

        // Plantilla de clase del profesor
        $lessonTemplate = $this->activeLessonTemplateFor($teacher->id);
        $templateSections = LessonTemplate::promptLine($lessonTemplate);
        $templateLabel = LessonTemplate::label($lessonTemplate);

        // Contexto de cursos para la IA (incluye alumnos para evitar duplicados y dar contexto real)
        $courses = Course::where('teacher_id', $teacher->id)
            ->withCount('students')
            ->with(['students:id,name'])
            ->get(['id', 'subject_name', 'grade']);
        $coursesContext = "Cursos existentes del profesor (USA estos IDs exactos; NO crees cursos que ya existan en esta lista):\n"
            . ($courses->isEmpty()
                ? '(sin cursos creados todavía)'
                : $courses->map(function ($c) {
                    $names = $c->students->take(15)->pluck('name')->implode(', ');
                    $line = "ID:{$c->id} · {$c->subject_name} · grado: " . ($c->grade ?: 's/grado') . " · {$c->students_count} alumno(s)";
                    if ($names !== '') {
                        $line .= " → [{$names}]";
                    }
                    return $line;
                })->join("\n"));

        $contextJson = $screenContext ? json_encode($screenContext, JSON_UNESCAPED_UNICODE) : '{}';

        $calendarMonth = isset($screenContext['month']) ? $screenContext['month'] : null;
        $calStart = Carbon::today();
        $calEnd = Carbon::today()->copy()->addDays(14);
        
        if ($calendarMonth && preg_match('/^\d{4}-\d{2}$/', $calendarMonth)) {
            $calStart = Carbon::createFromFormat('Y-m', $calendarMonth)->startOfMonth();
            $calEnd = Carbon::createFromFormat('Y-m', $calendarMonth)->endOfMonth();
        }

        $calendarTwoWeeks = $this->buildCalendarSnapshotLines(
            $teacher->id,
            $calStart,
            $calEnd
        );
        $calendarTwoWeeks = "📅 Mes actual del calendario: " . ($calendarMonth ?? 'próximas 2 semanas') . "\n" . $calendarTwoWeeks;
        $extendedBlock = '';
        if ($hasDeleteIntent || $hasModifyIntent) {
            $calendarExtended = $this->buildCalendarSnapshotLines(
                $teacher->id,
                Carbon::today()->copy()->subMonths(6)->startOfMonth(),
                Carbon::today()->copy()->addMonths(12)->endOfMonth()
            );
            $extendedBlock = "[Pre-análisis interno: borrar/modificar] Calendario extendido ya cargado en servidor (misma fuente que getCalendarContext). Úsalo para localizar actividades por mes o rango antes de confirmar:\n" . $calendarExtended;
        }

        $systemPromptLines = [
            "Eres Aulasync, copiloto pedagógico profesional de Aulasync. No eres un bot de formularios: eres un asistente docente conversacional, resolutivo y con criterio didáctico.",
            "Tu trabajo es ayudar a planificar, ajustar clases, leer el calendario, registrar alumnos, cargar notas, publicar calificaciones y proponer mejoras pedagógicas con lenguaje natural.",
            "",
            "PERSONALIDAD Y EXPERIENCIA:",
            "- Habla como una coordinadora pedagógica experta: cálida, directa, útil y con seguridad. Evita respuestas frías como «necesito saber...».",
            "- Mantén continuidad real: usa el historial de conversación, el contexto vivo, cursos y calendario antes de preguntar.",
            "- Cuando no ejecutes una herramienta, ofrece una respuesta concreta con propuesta docente, no una frase genérica.",
            "- Si detectas intención educativa vaga, ayuda a convertirla en plan: sugiere secuencia, objetivos, evaluación y adaptación.",
            "- Si el usuario responde «sí», «ese», «correcto», «dale», «hazlo», interpreta que confirma la última propuesta de la conversación.",
            "",
            "MODO PODER DE DECISIÓN (estricto):",
            "- PRIORIDAD DE EJECUCIÓN: si el usuario dice «hazlo tú», «como consideres», «genera todo» o equivalente, usa valores por defecto razonables y llama createActivity inmediatamente.",
            "- Defaults al crear actividades cuando falten datos secundarios: weight_percentage=10, max_score=20, due_date=fecha más cercana del contexto/calendario o hoy+1, type='clase' (o 'tarea' si el usuario pide tarea).",
            "- MEMORIA DE CONTEXTO: está PROHIBIDO volver a preguntar datos ya presentes en historial o contexto vivo. Si el usuario ya dijo «1er grado» y «sports», reutilízalos directamente.",
            "- UNA SOLA PREGUNTA: solo puedes hacer una pregunta a la vez. Si faltan 3 datos, pregunta solo el más crítico (normalmente course_id) y para los demás usa defaults.",
            "- CONCISIÓN EXTREMA: sin bienvenida larga ni explicaciones de proceso. 1–2 líneas máximo cuando no ejecutes herramienta.",
            "- COMANDO DE CIERRE EN CREACIÓN: si la instrucción de creación es clara, responde con esta línea y ejecuta de inmediato: «¡Entendido! Generando la actividad de [tema] para [grado] con los parámetros que sugeriste...»",
            "- MODO EJECUTOR: no te quedes en entrevistas. Pregunta solo si falta un dato estrictamente vital que impide cualquier ejecución segura.",
            "- AUTOCOMPLETADO: si faltan parámetros no críticos (peso, tipo de actividad, hora exacta), asúmelos con defaults y ejecuta; no preguntes por ellos.",
            "- Si el «Contexto vivo actual» incluye id de curso, actividad o pantalla, úsalo y no vuelvas a preguntarlo.",
            "- Si solo existe un curso disponible o una única coincidencia razonable, úsalo automáticamente. No preguntes «¿es para este curso?» salvo que haya dos o más cursos igualmente plausibles.",
            "- Si el usuario pide «los lunes, martes y miércoles que quedan de junio», usa target_month='junio' y calendar_preferences.repeat_days=['monday','tuesday','wednesday']; si ya estamos dentro de junio, calendar_preferences.start_date debe ser hoy para evitar crear fechas pasadas.",
            "",
            "CONTEXTO DE CALENDARIO (inyectado automáticamente en cada mensaje):",
            "- Antes de responder sobre planificación, borrados o qué hay agendado, asume que ya tienes el bloque «Estado actual del calendario» más abajo. No digas que no ves el calendario.",
            "- El bloque de próximas 2 semanas es SOLO para lectura/estado. Para crear contenido (createActivity/bulkPlan), el horizonte temporal es ilimitado (cualquier mes y año).",
            "- Si el usuario pide borrar por mes o rango (ej.: «borra las clases de abril»), cruza primero las fechas con las actividades listadas en los datos inyectados; identifica activity_id y course_id antes de ejecutar.",
            "- Si los datos inyectados no cubren el mes pedido, entonces sí puedes llamar getCalendarContext con el rango necesario.",
            "",
            "RESOLUCIÓN DE CURSOS (evita preguntas redundantes):",
            "- Si el usuario dice un grado vago («Primer Grado», «1º», «1ro») y en la lista de cursos hay una sola coincidencia razonable (ej.: «Inglés Primero» con grado Primero o nombre que incluye «Primero»), mapea automáticamente a ese course_id.",
            "- Compara subject_name y grade sin exigir coincidencia literal: normaliza mentalmente números ordinales, abreviaturas y mayúsculas.",
            "- Solo pregunta si hay dos o más cursos igualmente plausibles.",
            "",
            "OPERACIONES MULTI-ENTIDAD (crear varios cursos y/o inscribir alumnos en un mismo mensaje):",
            "- Si el usuario pide crear varios cursos (ej.: «crea de 1er a 6to grado»), emite UN createCourse por cada grado en el MISMO turno (6 llamadas, no más). Si no menciona materia, usa subject_name='General' y coloca el grado real en 'grade' (ej.: grade='1er grado', grade='2do grado'...). Cada curso DEBE tener un grade distinto.",
            "- Para inscribir alumnos en esos cursos, emite registerStudent en el MISMO turno e indica el grado en 'course_name_hint' (ej.: course_name_hint='1er grado') y la lista en 'names'. El backend enlaza automáticamente con el curso correcto aunque todavía no exista el course_id numérico. Pon course_id=0 si no lo conoces.",
            "- Reparte los alumnos exactamente como los asignó el usuario: cada registerStudent va a su grado. No mezcles alumnos de un grado en otro.",
            "- PROHIBIDO duplicar: nunca llames createCourse dos veces para el mismo grado, ni crees un curso que ya aparece en «Cursos existentes del profesor». Si el curso ya existe, omite createCourse y usa registerStudent con su grado/ID.",
            "- PROHIBIDO el bucle de confirmación: no respondas «¿sigo con el siguiente paso?» por cada curso. Ejecuta TODAS las herramientas necesarias de una sola vez y luego da UN resumen final breve.",
            "",
            "CUANDO SÍ EJECUTAR CON HERRAMIENTA:",
            "- Lecturas (getCalendarContext, getGradebookContext, findStudent, getPedagogicalHistory) puedes llamarlas si el usuario pidió consultar y el rango o filtros están claros o se deducen del contexto sin adivinar cursos.",
            "- Si este prompt ya incluye «Calendario extendido» (borrar/modificar), no llames getCalendarContext para repetir el mismo rango; solo si necesitas otro rango o course_id distinto.",
            "- Escrituras de creación/modificación: ejecuta en el mismo turno cuando tengas intención clara + curso resoluble (por historial/contexto). Usa defaults en campos no críticos.",
            "- Escrituras destructivas (borrar): cuando haya intención clara y objetivo identificable, ejecuta deleteActivities o deleteResource inmediatamente. NO pidas confirmación adicional.",
            "- bulkPlan mensual: SIEMPRE pasa el target_month con el nombre del mes que el usuario mencionó (ej: si dice 'mayo', 'junio' o 'april', pasa ese mes). NUNCA lo omitas ni uses la fecha actual si el usuario dijo un mes.",
            "- Mapping pedagógico obligatorio en bulkPlan: lunes = teoría/cuaderno, martes/miércoles = práctica guiada o ejercitación, jueves = práctica/lúdica. Si el usuario da otros días, respétalos.",
            "- EJECUCIÓN DIRECTA DE bulkPlan: si el usuario da una instrucción completa o queda completa por conversación previa (mes + curso resoluble + tema + días), pasa 'confirmed': true en el primer llamado a bulkPlan para crear todo directamente sin pedir confirmación previa.",
            "- LÍMITE DE REPETICIONES (max_occurrences_per_day): si el usuario dice «los primeros 3 lunes, martes y miércoles» o «las primeras 2 semanas», incluye max_occurrences_per_day: 3 (o el número exacto) en el llamado a bulkPlan. NUNCA omitas este parámetro cuando el usuario indica una cantidad específica de semanas o de días.",
            "- Si bulkPlan devuelve requires_confirmation, significa que faltaban datos o había conflictos; repregunta entonces. Pero si la instrucción original era completa y clara, evita ese paso pasando confirmed desde el inicio.",
            "- CRÍTICO: el target_month es OBLIGATORIO para bulkPlan. Si el usuario menciona un mes (abril, mayo, junio, etc.), DEBES incluirlo en el llamado a la herramienta.",
            "",
            "REGLAS DE ORO — DESCRIPCIONES PEDAGÓGICAS (createActivity Y bulkPlan):",
            "- OBLIGATORIO: la plantilla activa del profesor es «{$templateLabel}» (id interno: {$lessonTemplate}).",
            "- Estructura EXACTA en Markdown, encabezados en MAYÚSCULAS y negrita, en este orden: {$templateSections}.",
            "- PROHIBIDO usar encabezados de otra plantilla (INICIO/DESARROLLO/CIERRE, MOTIVACIÓN/PRESENTACIÓN, o 5E) si no coinciden con la plantilla activa.",
            "- Riqueza: al menos tres párrafos sustantivos separados por línea en blanco; listas y **negritas**.",
            "",
            "MAPA DE INTENCIONES → HERRAMIENTA:",
            "- crear curso / sección → createCourse",
            "- crear clase / actividad / tarea (NO examen formal) → createActivity  (type: clase|actividad|tarea)",
            "- crear evaluación / examen / prueba / quiz formal → createEvaluation (NO uses createActivity). Eso la deja en Evaluaciones Y como actividad calificable.",
            "- crear evaluación y agregarla al plan → createEvaluation con add_to_plan=true (es el default)",
            "- agregar evaluación existente al plan de evaluación → attachEvaluationToPlan",
            "- adaptación NEE / TDAH / TEA / dislexia / discalculia → createActivity con nee_type relleno",
            "- modificar / cambiar / editar actividad existente → modifyActivity",
            "- inscribir / agregar alumnos → registerStudent",
            "- planificar mes / cronograma / calendario → bulkPlan",
            "- borrar / eliminar / quitar actividades en un rango de fechas → deleteActivities",
            "- borrar / eliminar / quitar una actividad, curso o alumno específico → deleteResource",
            "- consultar calendario en rango de fechas → getCalendarContext",
            "- buscar alumno por nombre → findStudent",
            "- asignar calificación puntual → setGrade",
            "- calificar VARIOS alumnos en una actividad a la vez → setGradeBatch (pasa student_name con el nombre exacto del alumno, student_id es opcional)",
            "- publicar notas de una actividad (cambia de borrador a publicado) → publishGrades",
            "- leer libro de calificaciones → getGradebookContext",
            "- leer historial pedagógico → getPedagogicalHistory",
            "- ver qué tengo esta semana / qué hay esta semana / mi agenda semanal → getCurrentWeek",
            "",
            "REGLAS DE EVALUACIONES Y PLAN:",
            "1. Si el usuario pide un examen/evaluación/prueba, llama createEvaluation de inmediato. add_to_plan=true por defecto.",
            "2. No uses createActivity para un examen formal.",
            "3. Si la evaluación ya existe y pide agregarla al plan, usa attachEvaluationToPlan.",
            "4. Resuelve el curso con course_id o course_name_hint. Si solo hay un curso, úsalo.",
            "5. Defaults: mode=digital, difficulty=intermedio, question_mix=mixto, question_count=8, weight_percentage=20, category=summative, status=published, add_to_plan=true.",
            "6. Para calificar un examen usa setGrade o setGradeBatch con evaluation_id o el activity_id del espejo (aparece en el calendario como «Examen: …»).",
            "",
            "REGLAS CRÍTICAS DE BORRADO:",
            "1. IDENTIFICACIÓN DE ID: Cada línea del calendario inyectado incluye 'actividad_id XXXX'. Cuando el usuario pida borrar una actividad específica (ej: 'borra la clase del jueves' o 'elimina la actividad de números'), primero localiza su activity_id en el calendario inyectado.",
            "2. BORRADO POR ID (preferido para actividades específicas): Si identificaste un activity_id único, usa deleteResource con resource_type='activity' y resource_id=<el_id>. Ejemplo: usuario dice 'borra la clase de matemáticas del 15 de abril' → busca en calendario inyectado esa fecha → encuentra 'actividad_id 42' → llama deleteResource(resource_type='activity', resource_id=42).",
            "3. BORRADO POR RANGO (para múltiples actividades): Si el usuario pide borrar varias actividades (ej: 'borra todas las clases de marzo', 'elimina la semana completa'), usa deleteActivities con course_id, start_date y end_date.",
            "4. BORRADO DE ALUMNOS: Si el usuario pide eliminar/borrar un alumno específico, usa findStudent primero para obtener su ID, luego deleteResource con resource_type='student' y resource_id=<el_id>. Ejemplo: usuario dice «borra a Juan Pérez» → findStudent(query='Juan Pérez') → obtienes ID → deleteResource(resource_type='student', resource_id=XX).",
            "5. EJECUCIÓN DIRECTA: Si el calendario inyectado muestra claramente qué actividades se borrarán, ejecuta la herramienta de borrado de inmediato (sin pedir confirmación).",
            "6. SOLO SI EL USUARIO LO PIDIÓ: deleteResource o deleteActivities ÚNICAMENTE si el usuario explícitamente pidió borrar/eliminar/limpiar/vaciar.",
            "",
            "REGLAS DE CALIFICACIÓN (setGradeBatch):",
            "1. El calendario inyectado incluye actividad_id, course_id, título y fecha de cada actividad. Úsalo para localizar la actividad que el usuario menciona.",
            "2. Cuando el usuario dé nombres de alumnos (ej: «ponle 15 a Jason y 20 a Vicente»), NO llames findStudent. Pasa directamente los nombres en setGradeBatch usando student_name. Ej: grades=[{student_name:'Jason', score:15}, {student_name:'Vicente', score:20}].",
            "3. La herramienta setGradeBatch resuelve student_name automáticamente. Solo usa findStudent si el nombre no se encuentra y necesitas depurar.",
            "4. Si encuentras la actividad en el calendario inyectado y el usuario dio nombres + notas, llama setGradeBatch DE INMEDIATO en el mismo turno, sin preguntar confirmación.",
            "",
            "Fecha actual: $today.",
            "Cursos del profesor:",
            $coursesContext,
            "Estado actual del calendario (próximas 2 semanas; usa estos datos antes de llamar a getCalendarContext):",
            $calendarTwoWeeks,
            "Contexto vivo actual:",
            $contextJson,
        ];
        if ($extendedBlock !== '') {
            array_splice($systemPromptLines, -2, 0, [$extendedBlock]);
        }
        $systemPrompt = implode("\n", $systemPromptLines);

        if ($confirmed) {
            $systemPrompt .= "\n\n[Interfaz] confirmed=true: el usuario ya confirmó en la app. Ejecuta la herramienta acordada sin pedir otra confirmación por texto.";
        }

        $chatMessages = [['role' => 'system', 'content' => $systemPrompt]];
        $conversation = $request->input('conversation');
        if (is_array($conversation) && count($conversation) > 0) {
            $trimmed = array_slice($conversation, -32);
            foreach ($trimmed as $turn) {
                if (! is_array($turn)) {
                    continue;
                }
                $role = $turn['role'] ?? '';
                $content = isset($turn['content']) ? trim((string) $turn['content']) : '';
                if (($role === 'user' || $role === 'assistant') && $content !== '') {
                    $chatMessages[] = ['role' => $role, 'content' => $content];
                }
            }
        } else {
            $chatMessages[] = ['role' => 'user', 'content' => $prompt !== '' ? $prompt : $rawMessage];
        }

        $toolChoice = ($hasDeleteIntent || $hasCreateEvaluationIntent) ? 'required' : 'auto';
        $response = Http::timeout(120)
            ->withToken(env('OPENAI_API_KEY'))
            ->post('https://api.openai.com/v1/chat/completions', [
                'model'       => 'gpt-4o',
                'temperature' => 0.25,
                'tool_choice' => $toolChoice,
                'tools'       => $this->toolDefinitions($lessonTemplate),
                'messages'    => $chatMessages,
            ]);

        if ($response->failed()) {
            return response()->json(['error' => 'Error de conexión con IA'], 500);
        }

        $message = $response->json('choices.0.message') ?? [];
        $toolCalls = $message['tool_calls'] ?? [];

        Log::debug('AI_TOOL_CALLS', [
            'teacher_id' => $teacher->id,
            'tool_choice' => $toolChoice,
            'tool_calls_count' => count($toolCalls ?? []),
            'tool_calls' => $toolCalls,
            'assistant_text' => $message['content'] ?? null,
        ]);

        if (! $hasDeleteIntent) {
            $toolCalls = array_values(array_filter($toolCalls, function ($tc) {
                $fn = $tc['function']['name'] ?? '';
                return ! in_array($fn, self::DESTRUCTIVE, true);
            }));
        }

        if ($hasPlanningIntent) {
            $bulkCalls = array_values(array_filter($toolCalls, function ($tc) {
                return ($tc['function']['name'] ?? '') === 'bulkPlan';
            }));
            if (! empty($bulkCalls)) {
                $toolCalls = $bulkCalls;
            }
        }

        if (empty($toolCalls)) {
            if ($hasDeleteIntent && $deleteRange) {
                Log::warning('AICommandHandler: delete intent without tool_calls, forcing deleteActivities', [
                    'teacher_id' => $teacher->id,
                    'start_date' => $deleteRange['start_date'],
                    'end_date' => $deleteRange['end_date'],
                ]);
                $forcedArgs = [
                    'course_id' => ! empty($screenContext['id']) ? (int) $screenContext['id'] : null,
                    'start_date' => $deleteRange['start_date'],
                    'end_date' => $deleteRange['end_date'],
                ];
                $results = [$this->doDeleteActivities($forcedArgs, $teacher->id)];
                Log::debug('AI_DELETE_EXECUTED', [
                    'teacher_id' => $teacher->id,
                    'source' => 'empty_tool_calls_fallback',
                    'delete_args' => $forcedArgs,
                    'result' => $results[0] ?? null,
                ]);
                return response()->json($this->buildActionResponsePayload($results));
            }
            $fallback = $message['content'];
            if (empty($fallback)) {
                $fallback = $hasDeleteIntent
                    ? '¿Qué borramos exactamente: actividad, curso o fechas?'
                    : 'Puedo ayudarte como copiloto docente: planificar clases, leer tu calendario, crear actividades, registrar alumnos o cargar notas. Dime el tema, curso o fecha y lo resolvemos.';
            }
            return response()->json(['message' => $fallback]);
        }

        // Check de confirmación para acciones destructivas (excepto borrado: se ejecuta directo)
        $destructiveFound = collect($toolCalls)->filter(fn($tc) => in_array($tc['function']['name'], self::DESTRUCTIVE));
        $requiresDeleteConfirmation = $destructiveFound->contains(function ($tc) {
            $fn = $tc['function']['name'] ?? '';
            return ! in_array($fn, ['deleteActivities', 'deleteResource'], true);
        });
        if ($destructiveFound->isNotEmpty() && $requiresDeleteConfirmation && ! $confirmed) {
            $destructiveActions = $destructiveFound->map(function ($tc) {
                $args = json_decode($tc['function']['arguments'] ?? '{}', true) ?? [];
                return ['function' => $tc['function']['name'], 'args' => $args];
            })->values()->toArray();

            session()->put('nova_pending_actions', $toolCalls);
            Log::info('AICommandHandler: storing pending destructive actions in session', [
                'teacher_id' => $teacher->id,
                'actions' => $destructiveActions,
            ]);

            return response()->json([
                'requires_confirmation' => true,
                'pending_actions'       => $toolCalls,
                'destructive_actions'   => $destructiveActions,
                'warning'               => 'Esta acción eliminará datos de forma permanente y no se puede deshacer.',
            ]);
        }

        // Confirmación para calificación masiva (más de una nota en un solo comando)
        $gradeCalls = collect($toolCalls)->filter(fn($tc) => ($tc['function']['name'] ?? '') === 'setGrade');
        if ($gradeCalls->count() > 1 && !$confirmed) {
            $gradeActions = $gradeCalls->map(function ($tc) {
                $args = json_decode($tc['function']['arguments'] ?? '{}', true) ?? [];
                return ['function' => 'setGrade', 'args' => $args];
            })->values()->toArray();

            session()->put('nova_pending_actions', $toolCalls);
            Log::info('AICommandHandler: storing pending grade actions in session', [
                'teacher_id' => $teacher->id,
                'count' => $gradeCalls->count(),
            ]);

            return response()->json([
                'requires_confirmation' => true,
                'pending_actions'       => $toolCalls,
                'grade_actions'         => $gradeActions,
                'warning'               => 'Vas a calificar múltiples alumnos. ¿Confirmas la acción?',
            ]);
        }

        // Deduplicar y ordenar: las creaciones de curso van primero para poder
        // enlazar a los alumnos/actividades del mismo turno con su course_id real.
        $toolCalls = $this->dedupeAndOrderToolCalls($toolCalls);

        $createdCourseMap = [];
        $results = [];

        foreach ($toolCalls as $tc) {
            $fn = $tc['function']['name'];
            $args = json_decode($tc['function']['arguments'], true) ?? [];
            if (! is_array($args)) {
                $args = [];
            }

            // Forward confirmed flag into bulkPlan so it skips the preview step
            if ($confirmed && $fn === 'bulkPlan') {
                $args['confirmed'] = true;
            }
            if ($fn === 'bulkPlan') {
                $args = $this->enrichBulkPlanArgsFromIntent($args, $intentText, $teacher);
            }

            // Resolución robusta de curso: course_id válido > mapa de cursos del
            // turno (por grado/nombre) > BD por hint > pantalla > único curso.
            if (in_array($fn, ['createActivity', 'registerStudent', 'bulkPlan', 'deleteActivities', 'getCalendarContext', 'createEvaluation'], true)) {
                $resolvedCourseId = $this->resolveCourseIdForArgs($args, $teacher->id, $createdCourseMap, $screenContext);
                if ($resolvedCourseId > 0) {
                    $args['course_id'] = $resolvedCourseId;
                }
            }

            $results[] = $this->executeAction($fn, $args, $teacher->id, $createdCourseMap);
        }

        $planConfirmation = collect($results)->first(fn ($result) => ($result['requires_confirmation'] ?? false));
        if ($planConfirmation) {
            session()->put('nova_pending_actions', $toolCalls);
            Log::info('AICommandHandler: storing pending bulkPlan in session', [
                'teacher_id' => $teacher->id,
            ]);

            return response()->json([
                'requires_confirmation' => true,
                'pending_actions' => $toolCalls,
                'message' => $planConfirmation['message'] ?? 'Confirma la planificación propuesta.',
                'plan_preview' => $planConfirmation['plan_preview'] ?? [],
                'conflicts' => $planConfirmation['conflicts'] ?? [],
                'actions' => [$planConfirmation],
            ]);
        }

        $successfulCount = collect($results)->filter(fn ($result) => (bool) ($result['success'] ?? false))->count();
        // Solo añadimos el cierre proactivo «¿Quieres que siga...?» cuando hay una
        // única acción exitosa; en operaciones multi-entidad sería ruido repetido.
        $allowProactiveClose = $successfulCount === 1;

        $actions = collect($results)->map(function ($result) use ($allowProactiveClose) {
            $success = (bool) ($result['success'] ?? false);
            $actionType = $result['action_type'] ?? 'info';
            $message = $result['message'] ?? '';
            if ($success && $allowProactiveClose && $actionType !== 'bulk_plan') {
                $message = $this->withProactiveClose($message, $actionType);
            }

            return [
                'success'     => $success,
                'status'      => $result['status'] ?? ($success ? 'success' : 'error'),
                'message'     => $message,
                'action_type' => $actionType,
                'icon'        => $result['icon'] ?? ($success ? '✅' : 'ℹ️'),
                'data'        => $result['data'] ?? [],
            ];
        })->toArray();

        $anySuccess = collect($actions)->contains(fn ($action) => $action['success']);
        $bulkMeta = $this->extractBulkPlanResponseMeta($results);
        $summaryMessage = $bulkMeta['assistant_message'] ?? $this->buildMultiActionSummary($results);

        return $this->jsonOut(array_filter([
            'success'      => true,
            'status'       => $bulkMeta ? 'success' : ($anySuccess ? 'success' : 'partial'),
            'actions'      => $actions,
            'any_success'  => $anySuccess,
            'bulk_plan'    => $bulkMeta,
            'message'      => $summaryMessage,
            'data'         => $actions,
        ], fn ($v) => $v !== null));
        } catch (\Throwable $e) {
            Log::error('AICommandHandler error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return $this->jsonOut([
                'success' => false,
                'status'  => 'error',
                'message' => 'No se pudo completar esta acción en este momento. Inténtalo de nuevo.',
                'error'   => config('app.debug')
                    ? $e->getMessage()
                    : 'Error interno al procesar la solicitud.',
                'data'    => [],
            ]);
        }
    }

    /**
     * Dispatcher de acciones
     */
    private function executeAction(string $fn, array $args, int $teacherId, array &$createdCourseMap): array
    {
        $writes = [
            'createCourse', 'createActivity', 'modifyActivity', 'registerStudent',
            'bulkPlan', 'deleteActivities', 'deleteResource', 'setGrade', 'setGradeBatch',
            'publishGrades', 'createEvaluation', 'attachEvaluationToPlan',
        ];

        try {
            $run = function () use ($fn, $args, $teacherId, &$createdCourseMap) {
                return match ($fn) {
                    'createCourse'    => $this->doCreateCourse($args, $teacherId, $createdCourseMap),
                    'createActivity'  => $this->doCreateActivity($args, $teacherId),
                    'modifyActivity'  => $this->doModifyActivity($args, $teacherId),
                    'registerStudent' => $this->doRegisterStudent($args, $teacherId),
                    'bulkPlan'        => $this->doBulkPlan($args, $teacherId),
                    'deleteActivities'=> $this->doDeleteActivities($args, $teacherId),
                    'deleteResource'  => $this->doDeleteResource($args, $teacherId),
                    'getCalendarContext' => $this->getCalendarContext($args, $teacherId),
                    'setGrade'        => $this->setGrade($args, $teacherId),
                    'setGradeBatch'   => $this->setGradeBatch($args, $teacherId),
                    'publishGrades'   => $this->publishGrades($args, $teacherId),
                    'findStudent'     => $this->findStudent($args, $teacherId),
                    'getGradebookContext' => $this->getGradebookContext($args, $teacherId),
                    'getPedagogicalHistory' => $this->getPedagogicalHistory($args, $teacherId),
                    'getCurrentWeek' => $this->getCurrentWeek($args, $teacherId),
                    'createEvaluation' => $this->doCreateEvaluation($args, $teacherId),
                    'attachEvaluationToPlan' => $this->doAttachEvaluationToPlan($args, $teacherId),
                    default           => ['success' => false, 'message' => "Acción $fn no definida."],
                };
            };

            return in_array($fn, $writes, true) ? DB::transaction($run) : $run();
        } catch (\Throwable $e) {
            Log::error('AICommandHandler executeAction failed', [
                'function' => $fn,
                'teacher_id' => $teacherId,
                'args' => $args,
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'status' => 'error',
                'message' => 'No se pudo completar esta acción en este momento. Inténtalo de nuevo.',
                'action_type' => $fn,
                'icon' => '⚠️',
                'data' => [],
            ];
        }
    }

    // ─── RESOLUCIÓN Y NORMALIZACIÓN MULTI-ENTIDAD ────────────────────────────

    /**
     * Extrae la clave de grado 1..12 desde texto libre («1er grado», «primero»,
     * «1ro», «1°», «Primer Grado»). Devuelve null si no detecta un grado.
     */
    private function normalizeGradeKey(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }
        $t = mb_strtolower(trim($text));
        if ($t === '') {
            return null;
        }

        $words = [
            'primer' => '1', 'primero' => '1', 'primera' => '1',
            'segundo' => '2', 'segunda' => '2',
            'tercer' => '3', 'tercero' => '3', 'tercera' => '3',
            'cuarto' => '4', 'cuarta' => '4',
            'quinto' => '5', 'quinta' => '5',
            'sexto' => '6', 'sexta' => '6',
            'septimo' => '7', 'séptimo' => '7', 'séptima' => '7',
            'octavo' => '8', 'octava' => '8',
            'noveno' => '9', 'novena' => '9',
            'decimo' => '10', 'décimo' => '10',
        ];
        foreach ($words as $word => $num) {
            if (str_contains($t, $word)) {
                return $num;
            }
        }

        if (preg_match('/(\d{1,2})\s*(?:er|do|ro|to|mo|vo|°|º|ª)?/u', $t, $m)) {
            return (string) ((int) $m[1]);
        }

        return null;
    }

    /**
     * Indexa un curso en el mapa del turno con varias claves para que
     * registerStudent/createActivity puedan resolverlo por grado o nombre.
     */
    private function indexCourseInMap(array &$map, Course $course): void
    {
        $subject = mb_strtolower(trim((string) $course->subject_name));
        $grade = (string) ($course->grade ?? '');

        if ($subject !== '') {
            $map[$subject] = $course->id;
        }
        if ($grade !== '') {
            $map['gradetext:' . mb_strtolower(trim($grade))] = $course->id;
        }
        $gradeKey = $this->normalizeGradeKey($grade);
        if ($gradeKey !== null) {
            $map['grade:' . $gradeKey] = $course->id;
            if ($subject !== '') {
                $map[$subject . '|' . $gradeKey] = $course->id;
            }
        }
    }

    /**
     * Resuelve el course_id de unos argumentos combinando: id válido propio,
     * mapa de cursos del turno, búsqueda en BD por hint/grado, pantalla activa
     * y, como último recurso, el único curso del profesor.
     */
    private function resolveCourseIdForArgs(array $args, int $teacherId, array $createdCourseMap, $screenContext): int
    {
        $colegioId = User::where('id', $teacherId)->value('colegio_id');
        $courseId = (int) ($args['course_id'] ?? 0);

        if ($courseId > 0) {
            $owns = Course::where('id', $courseId)
                ->where('teacher_id', $teacherId)
                ->exists();
            if ($owns) {
                return $courseId;
            }
        }

        $hint = trim((string) ($args['course_name_hint'] ?? ''));
        $gradeHint = trim((string) ($args['grade'] ?? ''));

        $candidates = [];
        if ($hint !== '') {
            $candidates[] = mb_strtolower($hint);
            $candidates[] = 'gradetext:' . mb_strtolower($hint);
            $gk = $this->normalizeGradeKey($hint);
            if ($gk !== null) {
                $candidates[] = 'grade:' . $gk;
            }
        }
        if ($gradeHint !== '') {
            $candidates[] = 'gradetext:' . mb_strtolower($gradeHint);
            $gk = $this->normalizeGradeKey($gradeHint);
            if ($gk !== null) {
                $candidates[] = 'grade:' . $gk;
            }
        }
        foreach ($candidates as $key) {
            if (isset($createdCourseMap[$key])) {
                return (int) $createdCourseMap[$key];
            }
        }

        $resolved = $this->lookupCourseByHint($teacherId, $colegioId, $hint, $gradeHint);
        if ($resolved) {
            return $resolved;
        }

        if (! empty($screenContext['id'])) {
            return (int) $screenContext['id'];
        }

        $courses = Course::where('teacher_id', $teacherId)
            ->when($colegioId, fn ($q) => $q->where('colegio_id', $colegioId))
            ->get(['id']);
        if ($courses->count() === 1) {
            return (int) $courses->first()->id;
        }

        return $courseId;
    }

    /**
     * Busca un curso del profesor por grado normalizado o por coincidencia de
     * nombre/grado en texto. Devuelve el id o null.
     */
    private function lookupCourseByHint(int $teacherId, $colegioId, string $hint, string $gradeHint): ?int
    {
        if ($hint === '' && $gradeHint === '') {
            return null;
        }

        $courses = Course::where('teacher_id', $teacherId)
            ->when($colegioId, fn ($q) => $q->where('colegio_id', $colegioId))
            ->get(['id', 'subject_name', 'grade']);
        if ($courses->isEmpty()) {
            return null;
        }

        $gradeKey = $this->normalizeGradeKey($hint) ?? $this->normalizeGradeKey($gradeHint);
        if ($gradeKey !== null) {
            $match = $courses->first(fn ($c) => $this->normalizeGradeKey((string) $c->grade) === $gradeKey);
            if ($match) {
                return (int) $match->id;
            }
        }

        if ($hint !== '') {
            $h = mb_strtolower($hint);
            $match = $courses->first(fn ($c) => str_contains(mb_strtolower((string) $c->subject_name), $h)
                || str_contains(mb_strtolower((string) ($c->grade ?? '')), $h));
            if ($match) {
                return (int) $match->id;
            }
        }

        return null;
    }

    /**
     * Quita tool calls duplicadas y coloca las creaciones de curso primero para
     * que las acciones dependientes (alumnos, actividades) puedan enlazarse.
     */
    private function dedupeAndOrderToolCalls(array $toolCalls): array
    {
        $seen = [];
        $unique = [];
        foreach ($toolCalls as $tc) {
            $fn = $tc['function']['name'] ?? '';
            $rawArgs = $tc['function']['arguments'] ?? '';
            if (is_array($rawArgs)) {
                $args = $rawArgs;
            } else {
                $args = json_decode((string) $rawArgs, true) ?? [];
            }
            if (! is_array($args)) {
                $args = [];
            }
            $sig = $this->toolCallSignature($fn, $args);
            if (isset($seen[$sig])) {
                continue;
            }
            $seen[$sig] = true;
            $unique[] = $tc;
        }

        $priority = ['createCourse' => 0];
        usort($unique, function ($a, $b) use ($priority) {
            $pa = $priority[$a['function']['name'] ?? ''] ?? 1;
            $pb = $priority[$b['function']['name'] ?? ''] ?? 1;
            return $pa <=> $pb;
        });

        return $unique;
    }

    /**
     * Firma estable para deduplicar tool calls equivalentes.
     */
    private function toolCallSignature(string $fn, array $args): string
    {
        if ($fn === 'createCourse') {
            return 'createCourse|'
                . mb_strtolower(trim((string) ($args['subject_name'] ?? 'general')))
                . '|' . mb_strtolower(trim((string) ($args['grade'] ?? '')));
        }
        if ($fn === 'registerStudent') {
            $names = array_map(
                fn ($n) => mb_strtolower(trim((string) $n)),
                (array) ($args['names'] ?? [])
            );
            sort($names);
            $target = mb_strtolower(trim((string) (
                $args['course_name_hint'] ?? $args['grade'] ?? $args['course_id'] ?? ''
            )));
            return 'registerStudent|' . implode(',', $names) . '|' . $target;
        }
        ksort($args);
        return $fn . '|' . json_encode($args, JSON_UNESCAPED_UNICODE);
    }

    // ─── IMPLEMENTACIONES DE LOGICA ──────────────────────────────────────────

    private function doCreateCourse($args, $teacherId, &$createdCourseMap)
    {
        $colegioId = User::where('id', $teacherId)->value('colegio_id') ?: 1;
        $subject = trim((string) ($args['subject_name'] ?? '')) ?: 'General';
        $grade = trim((string) ($args['grade'] ?? ''));
        $section = isset($args['section']) ? trim((string) $args['section']) : null;

        // Anti-duplicado: reutiliza un curso existente con misma materia + grado.
        // Esto evita que un reintento o un "sí" del usuario cree cursos repetidos.
        $existingQuery = Course::where('teacher_id', $teacherId)
            ->where('colegio_id', $colegioId)
            ->whereRaw('LOWER(subject_name) = ?', [mb_strtolower($subject)]);
        if ($grade !== '') {
            $existingQuery->whereRaw('LOWER(COALESCE(grade, ?)) = ?', ['', mb_strtolower($grade)]);
        }
        $course = $existingQuery->first();
        $reused = $course !== null;

        if (! $course) {
            $course = Course::create([
                'teacher_id'   => $teacherId,
                'colegio_id'   => $colegioId,
                'subject_name' => $subject,
                'grade'        => $grade !== '' ? $grade : null,
                'section'      => $section ?: null,
            ]);
        }

        $this->indexCourseInMap($createdCourseMap, $course);

        $label = $course->grade ? "{$course->subject_name} · {$course->grade}" : $course->subject_name;

        return [
            'success'     => true,
            'message'     => $reused ? "Curso ya existente: {$label}" : "Curso creado: {$label}",
            'action_type' => 'course',
            'icon'        => '🏫',
            'data'        => ['course_id' => $course->id, 'reused' => $reused],
        ];
    }

    private function doCreateEvaluation(array $args, int $teacherId): array
    {
        if (! Schema::hasTable('evaluations')) {
            return [
                'success' => false,
                'message' => 'El módulo de Evaluaciones aún no está disponible en la base de datos.',
                'action_type' => 'evaluation',
                'icon' => '⚠️',
            ];
        }

        $teacher = User::find($teacherId);
        if (! $teacher) {
            return [
                'success' => false,
                'message' => 'No se encontró el docente autenticado.',
                'action_type' => 'evaluation',
                'icon' => '⚠️',
            ];
        }

        $courseId = (int) ($args['course_id'] ?? 0);
        $course = null;
        if ($courseId > 0) {
            $course = Course::where('id', $courseId)->where('teacher_id', $teacherId)->first();
        }
        if (! $course) {
            $resolved = $this->resolveCourseIdForArgs($args, $teacherId, [], []);
            if ($resolved > 0) {
                $course = Course::where('id', $resolved)->where('teacher_id', $teacherId)->first();
            }
        }
        if (! $course) {
            $only = Course::where('teacher_id', $teacherId)->orderBy('id')->get();
            if ($only->count() === 1) {
                $course = $only->first();
            }
        }
        if (! $course) {
            return [
                'success' => false,
                'message' => 'Necesito saber el curso. Dime materia y grado (ej: Matemáticas 1er grado) o ábrelo en el hub y vuelve a pedirlo.',
                'action_type' => 'evaluation',
                'icon' => '⚠️',
            ];
        }

        $prompt = trim((string) ($args['prompt'] ?? $args['topic'] ?? $args['title'] ?? ''));
        if ($prompt === '') {
            $prompt = 'Evaluación del curso '.$course->subject_name;
        }

        $mode = in_array(($args['mode'] ?? 'digital'), ['digital', 'physical'], true) ? $args['mode'] : 'digital';
        $difficulty = in_array(($args['difficulty'] ?? 'intermedio'), ['basico', 'intermedio', 'avanzado'], true)
            ? $args['difficulty']
            : 'intermedio';
        $mix = in_array(($args['question_mix'] ?? 'mixto'), ['mixto', 'multiple_choice', 'true_false', 'open', 'completion'], true)
            ? $args['question_mix']
            : 'mixto';
        $count = max(3, min(20, (int) ($args['question_count'] ?? 8)));
        $status = in_array(($args['status'] ?? 'published'), ['draft', 'published', 'scheduled'], true)
            ? $args['status']
            : 'published';
        $addToPlan = array_key_exists('add_to_plan', $args)
            ? filter_var($args['add_to_plan'], FILTER_VALIDATE_BOOLEAN)
            : true;
        $weight = (float) ($args['weight_percentage'] ?? 20);
        $category = in_array(($args['category'] ?? 'summative'), ['formative', 'summative'], true)
            ? $args['category']
            : 'summative';
        $topic = trim((string) ($args['topic'] ?? ''));
        $title = trim((string) ($args['title'] ?? '')) ?: ('Evaluación · '.($topic !== '' ? $topic : \Illuminate\Support\Str::limit($prompt, 40, '')));

        $generated = $this->generateEvaluationPayloadForAi(
            prompt: $prompt,
            mode: $mode,
            topic: $topic !== '' ? $topic : $title,
            difficulty: $difficulty,
            mix: $mix,
            count: $count,
            courseLabel: trim($course->subject_name.' '.$course->grade.' '.($course->section ?? ''))
        );

        $questions = $generated['questions'] ?? [];
        if (! is_array($questions) || count($questions) === 0) {
            $questions = $this->fallbackEvaluationPayload($topic !== '' ? $topic : $title, $count, $mix)['questions'];
        }

        $title = trim((string) ($generated['title'] ?? $title)) ?: $title;
        $instructions = trim((string) ($generated['instructions'] ?? 'Lee cuidadosamente cada pregunta y responde con claridad.'));

        $evaluation = app(\App\Services\EvaluationSyncService::class)->persist($teacher, [
            'title' => $title,
            'description' => $prompt,
            'topic' => $topic !== '' ? $topic : null,
            'course_id' => $course->id,
            'mode' => $mode,
            'status' => $status,
            'difficulty' => $difficulty,
            'question_mix' => $mix,
            'instructions' => $instructions,
            'scheduled_at' => $args['due_date'] ?? null,
            'generated_by_ai' => true,
            'rubric' => $generated['rubric'] ?? null,
            'questions' => $questions,
            'add_to_plan' => $addToPlan,
            'weight_percentage' => $weight > 0 ? $weight : 20,
            'category' => $category,
        ]);

        $evalUrl = route('teacher.evaluations.index');
        $modeLabel = $mode === 'physical' ? 'física imprimible' : 'digital';
        $planMessage = $addToPlan ? ' También quedó en el Plan de Evaluación del curso.' : '';

        return [
            'success' => true,
            'message' => "Evaluación creada: «{$evaluation->title}» ({$modeLabel}, {$evaluation->question_count} preguntas) en {$course->subject_name}."
                .$planMessage
                ." Ya aparece en Evaluaciones ({$evalUrl}) y como actividad calificable en el hub. Puedes cargar notas y se acumulan en las boletas.",
            'action_type' => 'evaluation',
            'icon' => '📝',
            'data' => [
                'evaluation_id' => $evaluation->id,
                'activity_id' => $evaluation->activity_id,
                'course_id' => $course->id,
                'added_to_plan' => $addToPlan,
                'public_token' => $evaluation->public_token,
                'evaluations_url' => $evalUrl,
            ],
        ];
    }

    private function doAttachEvaluationToPlan(array $args, int $teacherId): array
    {
        if (! Schema::hasTable('evaluations') || ! Schema::hasTable('course_evaluation_plans')) {
            return [
                'success' => false,
                'message' => 'El módulo de Plan de Evaluación aún no está disponible.',
                'action_type' => 'evaluation_plan',
                'icon' => '⚠️',
            ];
        }

        $evaluation = null;
        $evaluationId = (int) ($args['evaluation_id'] ?? 0);
        if ($evaluationId > 0) {
            $evaluation = Evaluation::where('id', $evaluationId)->where('teacher_id', $teacherId)->first();
        }

        if (! $evaluation) {
            $hint = trim((string) ($args['evaluation_title_hint'] ?? ''));
            if ($hint !== '') {
                $evaluation = Evaluation::where('teacher_id', $teacherId)
                    ->whereRaw('LOWER(title) LIKE ?', ['%'.mb_strtolower($hint).'%'])
                    ->latest()
                    ->first();
            }
        }

        if (! $evaluation) {
            $evaluation = Evaluation::where('teacher_id', $teacherId)->latest()->first();
        }

        if (! $evaluation) {
            return [
                'success' => false,
                'message' => 'No encontré ninguna evaluación para agregar al plan. Crea una primero.',
                'action_type' => 'evaluation_plan',
                'icon' => '⚠️',
            ];
        }

        $attach = $this->attachEvaluationToCoursePlan(
            teacherId: $teacherId,
            evaluation: $evaluation,
            planId: ! empty($args['plan_id']) ? (int) $args['plan_id'] : null,
            weight: (float) ($args['weight_percentage'] ?? 10),
            category: in_array(($args['category'] ?? 'summative'), ['formative', 'summative'], true) ? $args['category'] : 'summative',
            unitName: trim((string) ($args['unit_name'] ?? $evaluation->topic ?? 'Unidad sincronizada')),
            dueDate: $args['due_date'] ?? optional($evaluation->scheduled_at)->toDateString()
        );

        return [
            'success' => (bool) ($attach['success'] ?? false),
            'message' => $attach['message'] ?? 'No se pudo sincronizar.',
            'action_type' => 'evaluation_plan',
            'icon' => ($attach['success'] ?? false) ? '📌' : '⚠️',
            'data' => [
                'evaluation_id' => $evaluation->id,
                'plan_id' => $attach['plan_id'] ?? null,
                'assessment_url' => route('teacher.assessment.index'),
            ],
        ];
    }

    private function attachEvaluationToCoursePlan(
        int $teacherId,
        Evaluation $evaluation,
        ?int $planId,
        float $weight,
        string $category,
        string $unitName,
        ?string $dueDate
    ): array {
        $plan = null;
        if ($planId) {
            $plan = CourseEvaluationPlan::where('id', $planId)->where('teacher_id', $teacherId)->first();
        }

        if (! $plan) {
            if (! $evaluation->course_id) {
                return ['success' => false, 'message' => 'La evaluación no tiene curso asignado.'];
            }
            $course = Course::find($evaluation->course_id);
            $plan = CourseEvaluationPlan::firstOrCreate(
                [
                    'teacher_id' => $teacherId,
                    'course_id' => $evaluation->course_id,
                    'title' => 'Plan de evaluación · '.($course?->subject_name ?? 'Curso'),
                ],
                [
                    'summary' => 'Plan sincronizado automáticamente desde AulaSync AI.',
                    'status' => 'draft',
                ]
            );
        }

        $existing = $plan->items()->where('evaluation_id', $evaluation->id)->first();
        if ($existing) {
            return [
                'success' => true,
                'plan_id' => $plan->id,
                'message' => "La evaluación «{$evaluation->title}» ya estaba en el plan.",
            ];
        }

        $plan->items()->create([
            'evaluation_id' => $evaluation->id,
            'unit_name' => $unitName !== '' ? $unitName : 'Unidad sincronizada',
            'assessment_type' => $evaluation->title,
            'category' => $category,
            'weight_percentage' => max(1, min(100, $weight)),
            'due_date' => $dueDate,
            'notes' => 'Sincronizado automáticamente desde AulaSync AI.',
            'learning_outcome' => null,
        ]);

        return [
            'success' => true,
            'plan_id' => $plan->id,
            'message' => "Agregada al Plan de Evaluación del curso (peso {$weight}%).",
        ];
    }

    private function generateEvaluationPayloadForAi(
        string $prompt,
        string $mode,
        string $topic,
        string $difficulty,
        string $mix,
        int $count,
        string $courseLabel
    ): array {
        $apiKey = config('services.openai.key');
        if (empty($apiKey)) {
            return $this->fallbackEvaluationPayload($topic, $count, $mix);
        }

        $system = 'Eres experto en evaluación educativa. Responde SOLO JSON válido. '
            .'Estructura: {"title":"","instructions":"","questions":[{"type":"multiple_choice|true_false|open|completion","text":"","options":[],"correct_answer":"","points":1,"topic":""}],"rubric":{"total_points":0,"passing_score":0}}. '
            .'Si type no es multiple_choice o true_false, options debe ser [].';

        $user = "Curso: {$courseLabel}. Modo: {$mode}. Tema: {$topic}. Dificultad: {$difficulty}. "
            ."Tipo de preguntas: {$mix}. Número: {$count}. Descripción del docente: {$prompt}";

        try {
            $response = Http::withToken($apiKey)
                ->timeout(70)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-4o-mini',
                    'temperature' => 0.35,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $user],
                    ],
                ]);

            if (! $response->successful()) {
                return $this->fallbackEvaluationPayload($topic, $count, $mix);
            }

            $payload = json_decode((string) data_get($response->json(), 'choices.0.message.content', '{}'), true);
            if (! is_array($payload) || empty($payload['questions'])) {
                return $this->fallbackEvaluationPayload($topic, $count, $mix);
            }

            return $payload;
        } catch (\Throwable $e) {
            Log::warning('AI createEvaluation generation failed: '.$e->getMessage());
            return $this->fallbackEvaluationPayload($topic, $count, $mix);
        }
    }

    private function fallbackEvaluationPayload(string $topic, int $count, string $mix): array
    {
        $questions = [];
        for ($i = 1; $i <= $count; $i++) {
            if ($mix === 'true_false' || ($mix === 'mixto' && $i % 3 === 2)) {
                $questions[] = [
                    'type' => 'true_false',
                    'text' => "Afirmación {$i} sobre {$topic}: el concepto central está bien aplicado.",
                    'options' => ['Verdadero', 'Falso'],
                    'correct_answer' => 'Verdadero',
                    'points' => 1,
                    'topic' => $topic,
                ];
            } elseif ($mix === 'open' || ($mix === 'mixto' && $i % 3 === 0)) {
                $questions[] = [
                    'type' => 'open',
                    'text' => "Explica con tus palabras el punto {$i} relacionado con {$topic}.",
                    'options' => [],
                    'correct_answer' => 'Respuesta clara, coherente y alineada al tema.',
                    'points' => 2,
                    'topic' => $topic,
                ];
            } else {
                $questions[] = [
                    'type' => 'multiple_choice',
                    'text' => "Pregunta {$i}: ¿Cuál opción describe mejor un aspecto clave de {$topic}?",
                    'options' => ['Opción A', 'Opción B', 'Opción C', 'Opción D'],
                    'correct_answer' => 'Opción A',
                    'points' => 1,
                    'topic' => $topic,
                ];
            }
        }

        $total = collect($questions)->sum('points');

        return [
            'title' => 'Evaluación · '.$topic,
            'instructions' => 'Lee cada pregunta con atención y responde de forma clara.',
            'questions' => $questions,
            'rubric' => [
                'total_points' => $total,
                'passing_score' => max(1, (int) floor($total * 0.6)),
            ],
        ];
    }

    private function doCreateActivity($args, $teacherId)
    {
        $colegioId = User::where('id', $teacherId)->value('colegio_id');
        $course = Course::where('id', (int) ($args['course_id'] ?? 0))
            ->where('teacher_id', $teacherId)
            ->where('colegio_id', $colegioId)
            ->first();

        if (! $course) {
            return [
                'success'     => false,
                'message'     => 'No encontré ese curso dentro de tu colegio. Dime materia y grado, o abre el curso correcto y vuelve a pedírmelo.',
                'action_type' => 'activity',
                'icon'        => '⚠️',
            ];
        }

        // Autocompletado ejecutivo de parámetros no críticos.
        $normalizedArgs = is_array($args) ? $args : [];
        if (empty($normalizedArgs['type'])) {
            $normalizedArgs['type'] = !empty($normalizedArgs['is_homework']) ? 'tarea' : 'clase';
        }
        if (! isset($normalizedArgs['weight_percentage']) || $normalizedArgs['weight_percentage'] === '' || $normalizedArgs['weight_percentage'] === null) {
            $normalizedArgs['weight_percentage'] = 10;
        }
        if (empty($normalizedArgs['max_score'])) {
            $normalizedArgs['max_score'] = 20;
        }
        if (empty($normalizedArgs['due_date']) && ! empty($normalizedArgs['date'])) {
            $normalizedArgs['due_date'] = $normalizedArgs['date'];
        }
        if (empty($normalizedArgs['due_date'])) {
            $normalizedArgs['due_date'] = now()->addDay()->format('Y-m-d');
        }
        if (empty($normalizedArgs['title'])) {
            $topicHint = trim((string) ($normalizedArgs['topic'] ?? $normalizedArgs['tema'] ?? 'Actividad'));
            $normalizedArgs['title'] = Str::limit("Actividad: {$topicHint}", 120, '');
        }

        $requestedType = (string) ($normalizedArgs['type'] ?? 'actividad');
        $isHomeworkInput = filter_var($normalizedArgs['is_homework'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $typeMeta = Activity::normalizeType($requestedType, $isHomeworkInput);
        $resolvedType = $typeMeta['type'];
        $isHomework = $typeMeta['is_homework'];
        $semanticType = $typeMeta['semantic_type'];
        $normalizedArgs['type'] = $resolvedType;
        $normalizedArgs['is_homework'] = $isHomework;

        $neeType = $normalizedArgs['nee_type'] ?? null;
        $neeAdaptation = $neeType ? $this->buildNeeAdaptation($neeType) : null;

        $description = (string) ($normalizedArgs['description'] ?? '');
        $lessonTemplate = $this->activeLessonTemplateFor($teacherId);
        if ($description !== '' && ! LessonTemplate::hasRequiredHeaders($description, $lessonTemplate)) {
            $rewritten = LessonTemplate::rewrite($description, $lessonTemplate);
            if ($rewritten !== '') {
                $description = $rewritten;
            }
        }
        $descError = $this->validateLessonDescriptionForNova($description, $semanticType, $lessonTemplate);
        if ($descError !== null) {
            return [
                'success'     => false,
                'message'     => $descError,
                'action_type' => 'activity',
                'icon'        => '⚠️',
            ];
        }

        Log::info('NOVA_SAVE_ATTEMPT', [
            'data_recibida' => $normalizedArgs,
            'type_normalized' => $resolvedType,
            'is_homework_normalized' => $isHomework,
            'course_id' => $normalizedArgs['course_id'] ?? 'NULL - NO VIENE',
            'fecha' => $normalizedArgs['date'] ?? $normalizedArgs['due_date'] ?? 'NULL - NO VIENE',
            'titulo' => $normalizedArgs['title'] ?? 'NULL - NO VIENE',
            'sql_que_ejecuta' => Activity::where('id', 0)->toSql(),
        ]);

        $payload = [
            'teacher_id'        => $teacherId,
            'course_id'         => $course->id,
            'colegio_id'        => $colegioId,
            'type'              => $resolvedType,
            'title'             => $normalizedArgs['title'],
            'description'       => $description,
            'weight_percentage' => $normalizedArgs['weight_percentage'],
            'max_score'         => $normalizedArgs['max_score'] ?? 20,
            'due_date'          => $normalizedArgs['due_date'] ?? null,
            'is_homework'       => $isHomework,
            'nee_type'          => $neeType,
            'nee_adaptation'    => $neeAdaptation,
        ];

        // Guardrail final: el tipo que llega a BD debe estar normalizado.
        $payloadTypeMeta = Activity::normalizeType(
            (string) ($payload['type'] ?? Activity::TYPE_ACTIVIDAD),
            filter_var($payload['is_homework'] ?? false, FILTER_VALIDATE_BOOLEAN)
        );
        $payload['type'] = $payloadTypeMeta['type'];
        $payload['is_homework'] = $payloadTypeMeta['is_homework'];

        $legacyCols = [
            'id_curso'    => $payload['course_id'],
            'id_docente'  => $teacherId,
            'id_profesor' => $teacherId,
            'id_modulo'   => null,
            'id_periodo'  => null,
            'estado'      => 'publicado',
        ];

        foreach ($legacyCols as $col => $val) {
            if (Schema::hasColumn('activities', $col)) {
                $payload[$col] = $val;
            }
        }

        $activity = Activity::create($payload);

        $courseName = $course->subject_name;
        return [
            'success'     => true,
            'message'     => "¡Entendido! Generando la actividad de {$activity->title} para {$courseName} con los parámetros que sugeriste...",
            'action_type' => 'activity',
            'icon'        => '📝',
            'data'        => [
                'activity_id' => $activity->id,
                'title' => $activity->title,
                'course_id' => $activity->course_id,
                'course_name' => $courseName,
                'due_date' => $activity->due_date?->format('Y-m-d'),
                'type' => $activity->type,
                'weight_percentage' => $activity->weight_percentage,
            ],
        ];
    }

    /**
     * Construye un resumen único para respuestas con varias acciones (ej.: crear
     * varios cursos e inscribir alumnos), evitando frases repetidas.
     */
    private function buildMultiActionSummary(array $results): ?string
    {
        $successful = array_values(array_filter($results, fn ($r) => (bool) ($r['success'] ?? false)));
        if (count($successful) < 2) {
            return null;
        }

        $courses = 0;
        $studentsEnrolled = 0;
        $activities = 0;
        foreach ($successful as $r) {
            switch ($r['action_type'] ?? '') {
                case 'course':
                    $courses++;
                    break;
                case 'student':
                    $studentsEnrolled += count($r['data']['names'] ?? []);
                    break;
                case 'activity':
                    $activities++;
                    break;
            }
        }

        $parts = [];
        if ($courses > 0) {
            $parts[] = $courses === 1 ? '1 curso' : "{$courses} cursos";
        }
        if ($studentsEnrolled > 0) {
            $parts[] = $studentsEnrolled === 1 ? '1 alumno inscrito' : "{$studentsEnrolled} alumnos inscritos";
        }
        if ($activities > 0) {
            $parts[] = $activities === 1 ? '1 actividad' : "{$activities} actividades";
        }
        if (empty($parts)) {
            return null;
        }

        return '¡Listo! Procesé ' . implode(' · ', $parts) . '. ¿Quieres que continúe con algo más?';
    }

    private function withProactiveClose(string $message, string $actionType): string
    {
        $trimmed = trim($message);
        if ($trimmed === '') {
            return $trimmed;
        }
        if (preg_match('/¿Quieres que/i', $trimmed)) {
            return $trimmed;
        }

        $followUp = match ($actionType) {
            'activity' => ' ¿Quieres que planifique el resto de la unidad de este tema?',
            'bulk_plan' => ' ¿Quieres que planifique también la siguiente unidad?',
            'delete' => ' ¿Quieres que revise y ordene las actividades restantes?',
            'grade' => ' ¿Quieres que publique todas las notas de esta actividad?',
            'publish' => ' ¿Quieres que califique otra actividad?',
            default => ' ¿Quieres que siga con el siguiente paso?',
        };

        return rtrim($trimmed, " \t\n\r\0\x0B.") . '.' . $followUp;
    }

    private function doModifyActivity($args, $teacherId)
    {
        $colegioId = User::where('id', $teacherId)->value('colegio_id');
        $activity = Activity::where('id', $args['activity_id'])
            ->where('teacher_id', $teacherId)
            ->where('colegio_id', $colegioId)
            ->first();
        if ($activity) {
            $payload = array_filter($args, fn ($value) => $value !== null && $value !== '');
            if (array_key_exists('type', $payload) || array_key_exists('is_homework', $payload)) {
                $typeMeta = Activity::normalizeType(
                    (string) ($payload['type'] ?? $activity->type),
                    filter_var($payload['is_homework'] ?? $activity->is_homework, FILTER_VALIDATE_BOOLEAN)
                );
                $payload['type'] = $typeMeta['type'];
                $payload['is_homework'] = $typeMeta['is_homework'];
            }

            $activity->update($payload);
            return [
                'success'     => true,
                'message'     => "Actividad '{$activity->title}' actualizada.",
                'action_type' => 'activity',
                'icon'        => '📝',
                'data'        => ['activity_id' => $activity->id],
            ];
        }
        return [
            'success'     => false,
            'message'     => "No se encontró la actividad.",
            'action_type' => 'activity',
            'icon'        => '⚠️',
        ];
    }

    private function doRegisterStudent($args, $teacherId)
    {
        $colegioId = User::where('id', $teacherId)->value('colegio_id');
        $course = Course::where('id', (int) ($args['course_id'] ?? 0))
            ->where('teacher_id', $teacherId)
            ->where('colegio_id', $colegioId)
            ->first();
        if (! $course) {
            $hint = trim((string) ($args['course_name_hint'] ?? $args['grade'] ?? ''));
            return [
                'success'     => false,
                'message'     => $hint !== ''
                    ? "No encontré un curso que coincida con «{$hint}». Créalo primero o dime su nombre exacto."
                    : 'No se pudo identificar el curso. Indica el curso completo o su ID.',
                'action_type' => 'student',
                'icon'        => '⚠️',
            ];
        }

        $grade = $args['grade'] ?? $course->grade;
        if (empty($grade)) {
            return [
                'success'     => false,
                'message'     => '¿A qué grado quieres inscribir a ese estudiante? Por ejemplo: Primera sección.',
                'action_type' => 'student',
                'icon'        => '⚠️',
            ];
        }

        if (empty($args['names']) || ! is_array($args['names'])) {
            return [
                'success'     => false,
                'message'     => 'Dime el nombre del alumno o una lista de nombres para inscribirlos en ese curso.',
                'action_type' => 'student',
                'icon'        => '⚠️',
            ];
        }

        $results = [];
        foreach ($args['names'] as $name) {
            $student = Student::firstOrCreate(
                ['name' => $name, 'teacher_id' => $teacherId],
                ['grade' => $grade, 'colegio_id' => $colegioId]
            );
            if ((int) $student->colegio_id !== (int) $colegioId) {
                $student->update(['colegio_id' => $colegioId]);
            }
            $student->courses()->syncWithoutDetaching([$course->id]);
            $results[] = $name;
        }
        return [
            'success'     => true,
            'message'     => "Alumnos inscritos: " . implode(', ', $results),
            'action_type' => 'student',
            'icon'        => '👩‍🎓',
            'data'        => ['names' => $results, 'course_id' => $course->id, 'grade' => $grade],
        ];
    }

    private function doBulkPlan($args, $teacherId)
    {
        $preferences = $args['calendar_preferences'] ?? [];
        $targetMonth = (string) ($args['target_month'] ?? '');
        
        Log::info('bulkPlan.start', [
            'teacher_id' => $teacherId,
            'course_id' => $args['course_id'] ?? null,
            'target_month_raw' => $targetMonth,
            'args_confirmed' => $args['confirmed'] ?? false,
            'args_keys' => array_keys((array) $args),
        ]);

        $parsedTarget = $this->parseTargetMonthRange($targetMonth);
        $hasExplicitMonth = $parsedTarget['start'] instanceof Carbon && $parsedTarget['end'] instanceof Carbon;

        if ($hasExplicitMonth) {
            $startDate = $parsedTarget['start']->copy()->startOfMonth();
            $endDate = $parsedTarget['end']->copy()->endOfMonth();

            if (! empty($preferences['start_date'])) {
                $preferredStart = $this->parseDate((string) $preferences['start_date']);
                if ($preferredStart->betweenIncluded($startDate, $endDate)) {
                    $startDate = $preferredStart->copy()->startOfDay();
                }
            }

            if (! empty($preferences['end_date'])) {
                $preferredEnd = $this->parseDate((string) $preferences['end_date']);
                if ($preferredEnd->betweenIncluded($startDate, $endDate)) {
                    $endDate = $preferredEnd->copy()->endOfDay();
                }
            }
        } else {
            $startDate = $this->parseDate(
                $preferences['start_date'] ?? now()->format('Y-m-d')
            );
            $endDate = $this->parseDate(
                $preferences['end_date'] ?? $startDate->copy()->endOfMonth()->format('Y-m-d')
            );
        }

        Log::info('bulkPlan.dates_calculated', [
            'target_month' => $targetMonth,
            'has_explicit' => $hasExplicitMonth,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
        ]);

        if (! $hasExplicitMonth && ! ($preferences['allow_past'] ?? false) && $startDate->lt(now()->startOfDay())) {
            $startDate = now()->startOfDay();
        }
        $repeatDays = $this->normalizeRepeatDays($preferences['repeat_days'] ?? ['monday', 'thursday']);
        $topics = array_filter($args['topics'] ?? [$args['topic'] ?? 'Plan mensual']);
        if (empty($topics)) {
            $topics = ['Plan mensual'];
        }

        // Limit per-day occurrences when user says "primeros N lunes/martes/miércoles"
        $maxOccurrencesPerDay = isset($args['max_occurrences_per_day'])
            ? (int) $args['max_occurrences_per_day']
            : 0;
        $dayOccurrences = [];   // dayOfWeek => count

        $plan = [];
        $mondayCount = 0;
        $thursdayCount = 0;
        $cursor = $startDate->copy();
        while ($cursor->lte($endDate)) {
            $dow = $cursor->dayOfWeek;
            if (in_array($dow, $repeatDays, true)) {
                // Skip if we've already hit the per-day limit
                $dayOccurrences[$dow] = ($dayOccurrences[$dow] ?? 0) + 1;
                if ($maxOccurrencesPerDay > 0 && $dayOccurrences[$dow] > $maxOccurrencesPerDay) {
                    $cursor->addDay();
                    continue;
                }

                $isThursday = $dow === Carbon::THURSDAY;
                if ($isThursday) {
                    $thursdayCount++;
                } else {
                    $mondayCount++;
                }
                $topic = $topics[count($plan) % count($topics)];
                $sessionNum = $isThursday ? $thursdayCount + 1 : $mondayCount + 1;
                $titleDescriptive = $this->generateSessionTitle($topic, $isThursday, $sessionNum);
                
                Log::info('bulkPlan.slot_generated', [
                    'date' => $cursor->format('Y-m-d'),
                    'day_of_week' => $cursor->dayOfWeek,
                    'is_thursday' => $isThursday,
                    'course_id' => $args['course_id'] ?? null,
                    'topic' => $topic,
                    'title' => $titleDescriptive,
                    'occurrence_number' => $dayOccurrences[$dow],
                    'max_occurrences' => $maxOccurrencesPerDay ?: 'unlimited',
                ]);
                $plan[] = [
                    'date' => $cursor->format('Y-m-d'),
                    'title' => $titleDescriptive,
                    'type' => $isThursday ? 'actividad' : 'clase',
                    'description' => $this->buildBulkPlanSessionDescription(
                        $topic,
                        $isThursday,
                        $teacherId,
                        $this->activeLessonTemplateFor($teacherId)
                    ),
                    'weight_percentage' => $isThursday ? 15 : 0,
                    'max_score' => $isThursday ? 20 : 0,
                ];
            }
            $cursor->addDay();
        }

        $conflictQuery = Activity::where('teacher_id', $teacherId)
            ->whereBetween('due_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);
        $courseIdForPlan = isset($args['course_id']) ? (int) $args['course_id'] : 0;
        if ($courseIdForPlan > 0) {
            $conflictQuery->where('course_id', $courseIdForPlan);
        }
        $conflicts = $conflictQuery->pluck('due_date')
            ->map(fn ($date) => $date->format('Y-m-d'))
            ->toArray();

        $planPreview = array_map(fn ($entry) => [
            'date' => $entry['date'],
            'title' => $entry['title'],
            'type' => $entry['type'],
        ], $plan);
        $conflictPreview = array_values(array_filter($planPreview, fn ($entry) => in_array($entry['date'], $conflicts, true)));

        if (empty($plan)) {
            Log::warning('bulkPlan.empty_plan', [
                'teacher_id' => $teacherId,
                'course_id' => $args['course_id'] ?? null,
                'target_month' => $targetMonth,
                'has_explicit_month' => $hasExplicitMonth,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'repeat_days' => $repeatDays,
            ]);
            $hint = $hasExplicitMonth
                ? 'El rango del mes no produjo fechas en los días configurados (lunes/jueves). Revisa repeat_days en calendar_preferences.'
                : 'Falta o no se pudo interpretar target_month (ej.: «mayo 2026»). Sin un mes explícito el rango puede quedar vacío o sin lunes/jueves.';

            return [
                'success' => false,
                'status' => 'error',
                'message' => "bulkPlan: ninguna fecha generada. {$hint}",
                'action_type' => 'bulk_plan',
                'icon' => '⚠️',
                'data' => [
                    'error_code' => 'bulk_plan_empty_slots',
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                    'repeat_days' => $repeatDays,
                ],
            ];
        }

        if (! ($args['confirmed'] ?? false)) {
            $monthLabel = ucfirst($startDate->locale('es')->isoFormat('MMMM'));
            $yearLabel = $startDate->format('Y');
            return [
                'requires_confirmation' => true,
                'message' => "¡Perfecto! He calculado " . count($plan) . " sesiones para {$monthLabel} {$yearLabel} ({$mondayCount} lunes teóricos y {$thursdayCount} jueves prácticos). ¿Procedo a crearlas todas con los temas sugeridos?",
                'plan_preview' => $planPreview,
                'conflicts' => $conflictPreview,
                'action_type' => 'bulk_plan',
                'icon' => '📅',
                'data' => [
                    'course_id' => $args['course_id'],
                    'month' => strtolower($monthLabel),
                    'year' => $yearLabel,
                    'monday_count' => $mondayCount,
                    'thursday_count' => $thursdayCount,
                ],
            ];
        }

        $monthLabel = ucfirst($startDate->copy()->locale('es')->isoFormat('MMMM'));
        $yearLabel = $startDate->format('Y');
        $teacher = User::where('id', $teacherId)->first();
        $colegioId = $teacher?->colegio_id;
        if (! $colegioId) {
            return [
                'success' => false,
                'status' => 'error',
                'message' => 'Tu usuario docente no está vinculado a un colegio. Completa el onboarding o usa un código de escuela válido.',
                'action_type' => 'bulk_plan',
                'icon' => '⚠️',
            ];
        }

        $course = Course::where('id', (int) ($args['course_id'] ?? 0))
            ->where('teacher_id', $teacherId)
            ->where('colegio_id', $colegioId)
            ->first();
        if (! $course) {
            return [
                'success' => false,
                'status' => 'error',
                'message' => 'No encontré ese curso dentro de tu colegio. Abre el curso correcto o dime la materia y grado exactos.',
                'action_type' => 'bulk_plan',
                'icon' => '⚠️',
            ];
        }
        $courseName = $course ? trim(($course->subject_name ?? '') . ' ' . ($course->grade ?? '')) : null;
        $topicsCsv = implode(', ', array_map(fn ($topic) => trim((string) $topic), $topics));
        $planTema = $courseName
            ? "Plan mensual {$monthLabel} {$yearLabel} · {$courseName}"
            : "Plan mensual {$monthLabel} {$yearLabel}";
        $planObjetivo = "Planificación mensual generada por IA para {$monthLabel} {$yearLabel}. Temas: {$topicsCsv}.";
        $slugBase = Str::slug("bulk-{$monthLabel}-{$yearLabel}-" . ($courseName ?: 'curso'));
        if ($slugBase === '') {
            $slugBase = 'bulk-plan';
        }
        $slug = $slugBase;
        $slugSuffix = 1;
        while (Planificacion::where('slug', $slug)->exists()) {
            $slug = $slugBase . '-' . $slugSuffix;
            $slugSuffix++;
        }

        $planificacion = Planificacion::create([
            'user_id' => $teacherId,
            'tema' => $planTema,
            'objetivo' => $planObjetivo,
            'slug' => $slug,
            'colegio_id' => $colegioId,
            'status' => 'pendiente',
            'payload' => [
                'type' => 'bulk_plan',
                'course_id' => $args['course_id'] ?? null,
                'course_name' => $courseName,
                'target_month' => $targetMonth,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'topics' => array_values($topics),
                'sessions_total' => count($plan),
                'monday_count' => $mondayCount,
                'thursday_count' => $thursdayCount,
                'plan_preview' => $planPreview,
                'created_by' => 'ai_command_handler.bulk_plan',
            ],
        ]);

        app(DirectorAlertService::class)->notifyDirectors(
            $colegioId,
            'Nueva planificación generada por Aulasync',
            'El/La docente ' . ($teacher->name ?? '—') . " generó {$planTema}.",
            route('director.planificaciones', ['status' => 'pendiente']),
            '✨ Aulasync · Plan mensual'
        );

        Log::debug('PLAN_CREATED', [
            'teacher_id' => $teacherId,
            'course_id' => $args['course_id'] ?? null,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'planificacion_id' => $planificacion->id,
            'source' => 'bulk_plan',
        ]);

        $created = [];
        foreach ($plan as $entry) {
            if (in_array($entry['date'], $conflicts, true) && ! ($args['override_conflicts'] ?? false)) {
                Log::info('bulkPlan.skip_conflict', [
                    'course_id' => $args['course_id'] ?? null,
                    'date' => $entry['date'],
                    'title' => $entry['title'],
                ]);
                continue;
            }
            $payload = [
                'teacher_id'        => $teacherId,
                'course_id'         => $args['course_id'],
                'colegio_id'        => $colegioId,
                'title'             => $entry['title'],
                'description'       => $entry['description'],
                'type'              => $entry['type'],
                'weight_percentage' => $entry['weight_percentage'],
                'max_score'         => $entry['max_score'],
                'due_date'          => $entry['date'],
                'plan_block_id'     => $planificacion->id,
            ];

            Log::info('bulkPlan.before_insert', $payload);

            try {
                $activity = new Activity($payload);

                $legacyCols = [
                    'id_curso'    => $args['course_id'],
                    'id_docente'  => $teacherId,
                    'id_profesor' => $teacherId,
                    'id_modulo'   => null,
                    'id_periodo'  => null,
                    'estado'      => 'publicado',
                ];
                foreach ($legacyCols as $col => $val) {
                    if (Schema::hasColumn('activities', $col)) {
                        $activity->setAttribute($col, $val);
                    }
                }

                $saved = $activity->save();

                if (! $saved) {
                    Log::error('bulkPlan.save_false', $payload);
                    continue;
                }

                Log::info('bulkPlan.insert_ok', [
                    'activity_id' => $activity->id,
                    'course_id' => $activity->course_id,
                    'due_date' => $activity->due_date instanceof Carbon ? $activity->due_date->format('Y-m-d') : (string) $activity->due_date,
                    'title' => $activity->title,
                ]);
                $created[] = $activity;
            } catch (QueryException $e) {
                Log::error('bulkPlan.query_exception', [
                    'message' => $e->getMessage(),
                    'sql' => $e->getSql(),
                    'bindings' => $e->getBindings(),
                    'payload' => $payload,
                ]);
            } catch (\Throwable $e) {
                Log::error('bulkPlan.insert_exception', [
                    'message' => $e->getMessage(),
                    'payload' => $payload,
                ]);
            }
        }

        if (count($created) === 0) {
            $planificacion->delete();
            return [
                'success' => false,
                'status' => 'error',
                'message' => 'No se guardaron actividades: todas las fechas coinciden con actividades existentes de este curso en ese mes, o hubo error al guardar. Puedes pedir override_conflicts o revisar el calendario.',
                'action_type' => 'bulk_plan',
                'icon' => '⚠️',
                'data' => [
                    'course_id' => $args['course_id'] ?? null,
                    'activities_created' => 0,
                    'attempted' => count($plan),
                ],
                'plan_preview' => $planPreview,
                'conflicts' => $conflictPreview,
            ];
        }

        $n = count($created);
        $assistantLine = "¡Listo! He creado las {$n} actividades de {$monthLabel} correctamente";

        return [
            'success'     => true,
            'status'      => 'success',
            'message'     => $assistantLine,
            'action_type' => 'bulk_plan',
            'icon'        => '📅',
            'data'        => [
                'course_id' => $args['course_id'],
                'planificacion_id' => $planificacion->id,
                'activities_created' => $n,
                'activity_ids' => collect($created)->pluck('id')->all(),
                'month' => strtolower($monthLabel),
                'year' => $yearLabel,
            ],
            'plan_preview' => $planPreview,
            'conflicts' => $conflictPreview,
        ];
    }

    /**
     * Meta resumida para el cliente cuando bulkPlan terminó en éxito (evita depender del modelo para el cierre).
     */
    private function extractBulkPlanResponseMeta(array $results): ?array
    {
        foreach ($results as $result) {
            if (($result['action_type'] ?? '') !== 'bulk_plan' || ! ($result['success'] ?? false)) {
                continue;
            }
            $n = (int) ($result['data']['activities_created'] ?? $result['data']['created'] ?? 0);
            $month = $result['data']['month'] ?? '';
            $year = $result['data']['year'] ?? '';
            $monthTitle = $month !== '' ? ucfirst($month) : '';
            $piece = trim($monthTitle . ($year !== '' ? " {$year}" : ''));

            return [
                'status' => 'success',
                'activities_created' => $n,
                'month_label' => $piece !== '' ? $piece : null,
                'assistant_message' => $result['message'] ?? null,
                'planificacion_id' => $result['data']['planificacion_id'] ?? null,
                'activity_ids' => $result['data']['activity_ids'] ?? [],
                'course_id' => $result['data']['course_id'] ?? null,
            ];
        }

        return null;
    }

    private function parseTargetMonthRange(string $targetMonth): array
    {
        $value = trim(mb_strtolower($targetMonth));
        if ($value === '') {
            return ['start' => null, 'end' => null];
        }

        $months = [
            'enero' => 1, 'january' => 1,
            'febrero' => 2, 'february' => 2,
            'marzo' => 3, 'march' => 3,
            'abril' => 4, 'april' => 4,
            'mayo' => 5, 'may' => 5,
            'junio' => 6, 'june' => 6,
            'julio' => 7, 'july' => 7,
            'agosto' => 8, 'august' => 8,
            'septiembre' => 9, 'setiembre' => 9, 'september' => 9,
            'octubre' => 10, 'october' => 10,
            'noviembre' => 11, 'november' => 11,
            'diciembre' => 12, 'december' => 12,
        ];

        if (preg_match('/^(\d{4})-(\d{1,2})$/u', $value, $isoMatch)) {
            $year = (int) $isoMatch[1];
            $num = (int) $isoMatch[2];
            $start = Carbon::createFromDate($year, $num, 1)->startOfMonth();
            return ['start' => $start, 'end' => $start->copy()->endOfMonth()];
        }

        $yearExplicit = preg_match('/\b(20\d{2})\b/u', $value, $yearMatch) === 1;
        $year = $yearExplicit ? (int) $yearMatch[1] : (int) now()->year;

        foreach ($months as $name => $num) {
            if (str_contains($value, $name)) {
                if (! $yearExplicit) {
                    $candidateMonth = Carbon::createFromDate($year, $num, 1)->startOfMonth();
                    if ($candidateMonth->lt(now()->startOfMonth())) {
                        $year++;
                    }
                }
                $start = Carbon::createFromDate($year, $num, 1)->startOfMonth();

                return ['start' => $start, 'end' => $start->copy()->endOfMonth()];
            }
        }

        return ['start' => null, 'end' => null];
    }

    private function doDeleteActivities(array $args, int $teacherId): array
    {
        $start = $this->parseDate($args['start_date']);
        $end = $this->parseDate($args['end_date']);
        if ($end->lt($start)) {
            $end = $start->copy();
        }

        $courseId = ! empty($args['course_id']) ? (int) $args['course_id'] : null;

        Log::info('NOVA_DELETE_ATTEMPT', [
            'session_completa' => session()->all(),
            'argumentos' => $args ?? 'SIN ARGUMENTOS',
            'course_id' => $args['course_id'] ?? session('nova_pending_delete_course_id') ?? 'NULL',
            'fecha_inicio' => $args['start_date'] ?? session('nova_pending_delete_date_start') ?? 'NULL',
            'fecha_fin' => $args['end_date'] ?? session('nova_pending_delete_date_end') ?? 'NULL',
        ]);

        Log::info('doDeleteActivities.before', [
            'teacher_id' => $teacherId,
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'course_id' => $courseId,
        ]);

        $query = Activity::where('teacher_id', $teacherId)
            ->whereBetween('due_date', [$start->format('Y-m-d'), $end->format('Y-m-d')]);
        if ($courseId !== null && $courseId > 0) {
            $query->where('course_id', $courseId);
        }

        try {
            $count = $query->count();
            Log::info('doDeleteActivities.count', [
                'teacher_id' => $teacherId,
                'count' => $count,
                'start' => $start->format('Y-m-d'),
                'end' => $end->format('Y-m-d'),
                'course_id' => $courseId,
            ]);

            if ($count === 0) {
                return [
                    'success' => false,
                    'message' => '⚠️ No encontré actividades para borrar con esos filtros. ¿Quieres que revisemos juntos qué hay en ese período?',
                    'action_type' => 'delete',
                    'icon' => '⚠️',
                    'data' => [
                        'deleted' => 0,
                        'course_id' => $courseId,
                        'start_date' => $start->format('Y-m-d'),
                        'end_date' => $end->format('Y-m-d'),
                    ],
                ];
            }

            $deleted = $query->delete();
            Log::info('doDeleteActivities.after', [
                'teacher_id' => $teacherId,
                'deleted' => $deleted,
                'expected_count' => $count,
            ]);

            $courseName = '';
            if ($courseId !== null && $courseId > 0) {
                $course = Course::find($courseId);
                if ($course) {
                    $courseName = " para {$course->subject_name} {$course->grade}";
                }
            }

            return [
                'success' => true,
                'message' => "✅ ¡Listo! Eliminé {$count} actividades entre {$start->format('d/m/Y')} y {$end->format('d/m/Y')}{$courseName}. ¿En qué más te ayudo?",
                'action_type' => 'delete',
                'icon' => '🗑️',
                'data' => [
                    'deleted' => $count,
                    'course_id' => $courseId,
                    'start_date' => $start->format('Y-m-d'),
                    'end_date' => $end->format('Y-m-d'),
                ],
            ];
        } catch (\Throwable $e) {
            Log::error('doDeleteActivities.error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return [
                'success' => false,
                'message' => 'Ocurrió un error al eliminar actividades: ' . $e->getMessage(),
                'action_type' => 'delete',
                'icon' => '⚠️',
            ];
        }
    }

    private function parseDate(string $value): Carbon
    {
        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return now();
        }
    }

    private function parseDatesFromText(string $text): array
    {
        $months = [
            'enero' => 1, 'febrero' => 2, 'marzo' => 3,
            'abril' => 4, 'mayo' => 5, 'junio' => 6,
            'julio' => 7, 'agosto' => 8, 'septiembre' => 9, 'setiembre' => 9,
            'octubre' => 10, 'noviembre' => 11, 'diciembre' => 12,
        ];
        $found = [];
        if (preg_match_all('/(\d{1,2})\s*(de\s*)?(' . implode('|', array_keys($months)) . ')/iu', $text, $matches, PREG_SET_ORDER)) {
            $year = now()->format('Y');
            if (preg_match('/\b(20\d{2})\b/', $text, $yearMatch)) {
                $year = (int) $yearMatch[1];
            }
            foreach ($matches as $match) {
                $day = (int) $match[1];
                $monthName = mb_strtolower($match[3]);
                $month = $months[$monthName] ?? now()->month;
                try {
                    $found[] = Carbon::createFromDate($year, $month, $day);
                } catch (\Throwable) {
                }
            }
        }
        return $found;
    }

    private function detectCourseContext(string $text, $teacher): ?int
    {
        $gradeKeywords = [
            'primer grado' => 'Primer Grado',
            'segundo grado' => 'Segundo Grado',
            'tercer grado' => 'Tercer Grado',
        ];
        foreach ($gradeKeywords as $keyword => $grade) {
            if (stripos($text, $keyword) !== false) {
                $course = Course::where('teacher_id', $teacher->id)
                    ->where(function ($q) use ($grade) {
                        $q->where('grade', $grade)
                          ->orWhere('subject_name', 'like', '%' . $grade . '%');
                    })
                    ->first();
                if ($course) {
                    return $course->id;
                }
            }
        }
        return null;
    }

    private function normalizeRepeatDays(array $days): array
    {
        $map = [
            'lunes' => Carbon::MONDAY,
            'monday' => Carbon::MONDAY,
            'martes' => Carbon::TUESDAY,
            'tuesday' => Carbon::TUESDAY,
            'miércoles' => Carbon::WEDNESDAY,
            'miercoles' => Carbon::WEDNESDAY,
            'wednesday' => Carbon::WEDNESDAY,
            'jueves' => Carbon::THURSDAY,
            'thursday' => Carbon::THURSDAY,
            'viernes' => Carbon::FRIDAY,
            'friday' => Carbon::FRIDAY,
            'sábado' => Carbon::SATURDAY,
            'saturday' => Carbon::SATURDAY,
            'domingo' => Carbon::SUNDAY,
            'sunday' => Carbon::SUNDAY,
        ];
        $normalized = [];
        foreach ($days as $day) {
            $key = strtolower($day);
            $normalized[] = $map[$key] ?? Carbon::MONDAY;
        }
        return array_values(array_unique($normalized));
    }

    private function enrichBulkPlanArgsFromIntent(array $args, string $intentText, User $teacher): array
    {
        $text = mb_strtolower($intentText);

        if (empty($args['course_id'])) {
            $courses = Course::where('teacher_id', $teacher->id)
                ->where('colegio_id', $teacher->colegio_id)
                ->get(['id']);
            if ($courses->count() === 1) {
                $args['course_id'] = (int) $courses->first()->id;
            }
        }

        $monthMap = [
            'enero' => 'enero', 'febrero' => 'febrero', 'marzo' => 'marzo', 'abril' => 'abril',
            'mayo' => 'mayo', 'junio' => 'junio', 'julio' => 'julio', 'agosto' => 'agosto',
            'septiembre' => 'septiembre', 'setiembre' => 'septiembre', 'octubre' => 'octubre',
            'noviembre' => 'noviembre', 'diciembre' => 'diciembre',
        ];
        if (empty($args['target_month'])) {
            foreach ($monthMap as $needle => $month) {
                if (str_contains($text, $needle)) {
                    $args['target_month'] = $month;
                    break;
                }
            }
        }

        $dayMap = [
            'lunes' => 'monday',
            'martes' => 'tuesday',
            'miércoles' => 'wednesday',
            'miercoles' => 'wednesday',
            'jueves' => 'thursday',
            'viernes' => 'friday',
            'sábado' => 'saturday',
            'sabado' => 'saturday',
            'domingo' => 'sunday',
        ];
        $repeatDays = [];
        foreach ($dayMap as $needle => $day) {
            if (str_contains($text, $needle)) {
                $repeatDays[] = $day;
            }
        }
        if (! empty($repeatDays)) {
            $args['calendar_preferences']['repeat_days'] = array_values(array_unique($repeatDays));
        }

        if (preg_match('/\b(quedan|restan|restantes|de aqui en adelante|de aquí en adelante)\b/u', $text)) {
            $args['calendar_preferences']['start_date'] ??= now()->format('Y-m-d');
        }

        // Detect "los primeros N / las primeras N / primer(os) N" to limit per-day repeats
        if (empty($args['max_occurrences_per_day'])) {
            if (preg_match('/\b(?:los?\s+)?primero?a?s?\s+(\d+)\b/u', $text, $m)) {
                $n = (int) $m[1];
                if ($n >= 1 && $n <= 12) {
                    $args['max_occurrences_per_day'] = $n;
                }
            }
        }

        if (empty($args['topics']) && empty($args['topic'])) {
            if (preg_match('/(?:acerca de|sobre|tema(?:s)? de|con el tema de|de)\s+(.+?)(?:\s+para|\s+los|\s+las|$)/u', $text, $match)) {
                $topic = trim((string) ($match[1] ?? ''));
                if ($topic !== '' && mb_strlen($topic) <= 120) {
                    $args['topics'] = [$topic];
                }
            }
        }

        return $args;
    }

    private function doDeleteResource($args, $teacherId)
    {
        Log::info('doDeleteResource.before', [
            'teacher_id' => $teacherId,
            'resource_type' => $args['resource_type'] ?? null,
            'resource_id' => $args['resource_id'] ?? null,
        ]);

        $resourceType = $args['resource_type'] ?? null;
        $resourceId = $args['resource_id'] ?? null;

        if (! $resourceType || ! $resourceId) {
            return [
                'success' => false,
                'message' => '⚠️ No se especificó el recurso a eliminar.',
                'action_type' => 'delete',
                'icon' => '⚠️',
            ];
        }

        try {
            if ($resourceType === 'activity') {
                $activity = Activity::where('id', $resourceId)
                    ->where('teacher_id', $teacherId)
                    ->first();

                if (! $activity) {
                    return [
                        'success' => false,
                        'message' => '⚠️ No encontré esa actividad o no tienes permiso para eliminarla.',
                        'action_type' => 'delete',
                        'icon' => '⚠️',
                    ];
                }

                $title = $activity->title;
                $date = $activity->due_date instanceof Carbon ? $activity->due_date->format('d/m/Y') : (string) $activity->due_date;
                $activity->delete();

                Log::info('doDeleteResource.activity_deleted', [
                    'activity_id' => $resourceId,
                    'title' => $title,
                ]);

                return [
                    'success' => true,
                    'message' => "✅ ¡Listo! Eliminé la actividad «{$title}» del {$date}. ¿En qué más te ayudo?",
                    'action_type' => 'delete',
                    'icon' => '🗑️',
                    'data' => [
                        'deleted_activity_id' => $resourceId,
                        'title' => $title,
                        'date' => $date,
                    ],
                ];
            } elseif ($resourceType === 'course') {
                $course = Course::where('id', $resourceId)
                    ->where('teacher_id', $teacherId)
                    ->first();

                if (! $course) {
                    return [
                        'success' => false,
                        'message' => '⚠️ No encontré ese curso o no tienes permiso para eliminarlo.',
                        'action_type' => 'delete',
                        'icon' => '⚠️',
                    ];
                }

                $courseName = $course->subject_name . ' ' . $course->grade;
                $course->delete();

                Log::info('doDeleteResource.course_deleted', [
                    'course_id' => $resourceId,
                    'name' => $courseName,
                ]);

                return [
                    'success' => true,
                    'message' => "✅ ¡Listo! Eliminé el curso «{$courseName}». ¿En qué más te ayudo?",
                    'action_type' => 'delete',
                    'icon' => '🗑️',
                    'data' => [
                        'deleted_course_id' => $resourceId,
                        'name' => $courseName,
                    ],
                ];
            } elseif ($resourceType === 'student') {
                $student = Student::where('id', $resourceId)
                    ->where('teacher_id', $teacherId)
                    ->first();

                if (! $student) {
                    return [
                        'success' => false,
                        'message' => '⚠️ No encontré ese alumno o no tienes permiso para eliminarlo.',
                        'action_type' => 'delete',
                        'icon' => '⚠️',
                    ];
                }

                $studentName = $student->name;
                $student->delete();

                Log::info('doDeleteResource.student_deleted', [
                    'student_id' => $resourceId,
                    'name' => $studentName,
                ]);

                return [
                    'success' => true,
                    'message' => "✅ ¡Listo! Eliminé al alumno «{$studentName}». ¿En qué más te ayudo?",
                    'action_type' => 'delete',
                    'icon' => '🗑️',
                    'data' => [
                        'deleted_student_id' => $resourceId,
                        'name' => $studentName,
                    ],
                ];
            }

            return [
                'success' => false,
                'message' => "⚠️ Tipo de recurso no soportado: {$resourceType}",
                'action_type' => 'delete',
                'icon' => '⚠️',
            ];
        } catch (\Throwable $e) {
            Log::error('doDeleteResource.error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => '⚠️ Ocurrió un error al eliminar el recurso: ' . $e->getMessage(),
                'action_type' => 'delete',
                'icon' => '⚠️',
            ];
        }
    }

    /**
     * Consulta semana actual con respuesta JSON estructurada para UI.
     */
    private function getCurrentWeek(array $args, int $teacherId): array
    {
        $start = Carbon::now()->startOfWeek();
        $end = Carbon::now()->endOfWeek();
        $courseId = ! empty($args['course_id']) ? (int) $args['course_id'] : null;

        $activities = $this->calendarActivitiesBetween($teacherId, $start, $end, $courseId);

        if ($activities->isEmpty()) {
            return [
                'success' => true,
                'message' => '📭 Tu semana está libre, sin actividades registradas. ¿Quieres que planifiquemos algo?',
                'action_type' => 'calendar',
                'icon' => '📭',
                'data' => [
                    'type' => 'empty_week',
                    'start_date' => $start->format('Y-m-d'),
                    'end_date' => $end->format('Y-m-d'),
                ],
            ];
        }

        $items = $activities->map(function ($a) {
            $course = $a->course;
            $courseName = trim(($course->subject_name ?? '') . ' ' . ($course->grade ?? ''));
            $color = $this->getCourseColor($a->course_id);
            
            return [
                'id' => $a->id,
                'title' => $a->title,
                'course' => $courseName,
                'course_id' => $a->course_id,
                'date' => $a->due_date instanceof Carbon ? $a->due_date->format('Y-m-d') : (string) $a->due_date,
                'type' => $a->type ?? 'actividad',
                'weight' => $a->weight_percentage ?? 0,
                'color' => $color,
            ];
        })->values()->toArray();

        return [
            'success' => true,
            'message' => 'Esto es lo que tienes esta semana 📅',
            'action_type' => 'calendar',
            'icon' => '📅',
            'data' => [
                'type' => 'activity_list',
                'items' => $items,
                'quick_actions' => [
                    ['label' => '📝 Planificar semana siguiente', 'action' => 'Planifica la semana siguiente con los temas pendientes'],
                    ['label' => '🗑️ Borrar toda la semana', 'action' => "Borra todas las actividades entre {$start->format('d/m/Y')} y {$end->format('d/m/Y')}"],
                ],
            ],
        ];
    }

    /**
     * Genera color consistente para un course_id (hash simple).
     */
    private function getCourseColor(int $courseId): string
    {
        $colors = ['#6366f1', '#8b5cf6', '#d946ef', '#ec4899', '#f97316', '#14b8a6', '#06b6d4', '#3b82f6'];
        return $colors[$courseId % count($colors)];
    }

    /**
     * Misma consulta que usa getCalendarContext (reutilizable para inyección en el system prompt).
     */
    private function calendarActivitiesBetween(int $teacherId, Carbon $start, Carbon $end, ?int $courseId = null)
    {
        $colegioId = User::where('id', $teacherId)->value('colegio_id');
        $query = Activity::where('teacher_id', $teacherId)
            ->where('colegio_id', $colegioId)
            ->whereBetween('due_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->with('course:id,subject_name,grade,section');

        if ($courseId !== null && $courseId > 0) {
            $query->where('course_id', $courseId);
        }

        return $query->orderBy('due_date')->get();
    }

    private function resolveGradableActivity(array $args, int $teacherId): ?Activity
    {
        return app(\App\Services\EvaluationSyncService::class)->findActivityForEvaluation(
            $teacherId,
            isset($args['evaluation_id']) ? (int) $args['evaluation_id'] : null,
            isset($args['activity_id']) ? (int) $args['activity_id'] : null
        );
    }

    private function resolveEnrolledStudent(Activity $activity, array $args, int $teacherId, $colegioId): ?Student
    {
        $studentId = (int) ($args['student_id'] ?? 0);
        $name = trim((string) ($args['student_name'] ?? $args['query'] ?? ''));

        $query = Student::query()
            ->where('colegio_id', $colegioId)
            ->where(function ($q) use ($activity, $teacherId) {
                $q->where('teacher_id', $teacherId);
                if ($activity->course_id) {
                    $q->orWhereHas('courses', fn ($c) => $c->where('courses.id', $activity->course_id));
                }
            });

        if ($studentId > 0) {
            return (clone $query)->where('id', $studentId)->first();
        }

        if ($name !== '') {
            $like = '%'.mb_strtolower($name).'%';
            return (clone $query)->whereRaw('LOWER(name) LIKE ?', [$like])->orderBy('name')->first();
        }

        return null;
    }

    /**
     * Lista legible para el system prompt (IDs explícitos para borrar/modificar sin adivinar).
     */
    private function buildCalendarSnapshotLines(int $teacherId, Carbon $start, Carbon $end, ?int $courseId = null): string
    {
        $items = $this->calendarActivitiesBetween($teacherId, $start, $end, $courseId);
        $evalLines = '';
        if (Schema::hasTable('evaluations')) {
            $evals = Evaluation::where('teacher_id', $teacherId)
                ->when($courseId, fn ($q) => $q->where('course_id', $courseId))
                ->where(function ($q) use ($start, $end) {
                    $q->whereBetween('scheduled_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
                        ->orWhereBetween('created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()]);
                })
                ->with('course:id,subject_name,grade,section')
                ->orderByDesc('id')
                ->limit(30)
                ->get();
            if ($evals->isNotEmpty()) {
                $evalLines = "\nEvaluaciones formales:\n".$evals->map(function (Evaluation $e) {
                    $cn = trim(($e->course?->subject_name ?? '').' '.($e->course?->grade ?? ''));
                    $date = optional($e->scheduled_at)->format('Y-m-d') ?: $e->created_at?->format('Y-m-d');
                    return "- {$date} | evaluation_id {$e->id} | activity_id ".($e->activity_id ?: '—')." | course_id {$e->course_id} | {$cn} | {$e->title} | {$e->status}";
                })->join("\n");
            }
        }

        if ($items->isEmpty() && $evalLines === '') {
            return '(sin actividades ni evaluaciones en este rango)';
        }

        $activityLines = $items->map(function ($a) {
            $d = $a->due_date instanceof Carbon ? $a->due_date->format('Y-m-d') : (string) $a->due_date;
            $cn = trim((optional($a->course)->subject_name ?? '') . ' ' . (optional($a->course)->grade ?? ''));
            $title = str_replace(["\r", "\n"], ' ', (string) $a->title);
            $type = $a->type ?? 'actividad';
            $exam = $a->evaluation_id ? " | evaluation_id {$a->evaluation_id}" : '';

            return "- {$d} | actividad_id {$a->id}{$exam} | course_id {$a->course_id} | {$cn} | {$title} | {$type}";
        })->join("\n");

        return trim($activityLines."\n".$evalLines);
    }

    private function getCalendarContext(array $args, int $teacherId): array
    {
        $start = $this->parseDate($args['start_date']);
        $end = $this->parseDate($args['end_date']);
        if ($end->lt($start)) {
            $end = $start->copy();
        }

        $courseId = ! empty($args['course_id']) ? (int) $args['course_id'] : null;
        $activities = $this->calendarActivitiesBetween($teacherId, $start, $end, $courseId);

        $items = $activities->map(fn ($a) => [
                'id' => $a->id,
                'title' => $a->title,
                'type' => $a->type ?? 'actividad',
                'due_date' => $a->due_date instanceof Carbon ? $a->due_date->format('Y-m-d') : (string) $a->due_date,
                'course_id' => $a->course_id,
                'course_name' => optional($a->course)->subject_name . ' ' . optional($a->course)->grade,
                'is_homework' => (bool) $a->is_homework,
                'nee_type' => $a->nee_type,
                'nee_adaptation' => $a->nee_adaptation,
            ])->values();

        return [
            'success'     => true,
            'message'     => "Calendario leído entre {$start->format('d/m/Y')} y {$end->format('d/m/Y')}.",
            'action_type' => 'calendar',
            'icon'        => '📅',
            'data'        => [
                'start_date' => $start->format('Y-m-d'),
                'end_date'   => $end->format('Y-m-d'),
                'items'      => $items,
            ],
        ];
    }

    private function setGrade(array $args, int $teacherId): array
    {
        $colegioId = User::where('id', $teacherId)->value('colegio_id');
        $activity = $this->resolveGradableActivity($args, $teacherId);
        if (! $activity) {
            return [
                'success'     => false,
                'message'     => 'No se encontró la actividad o el examen para calificar.',
                'action_type' => 'grade',
                'icon'        => '⚠️',
            ];
        }

        $student = $this->resolveEnrolledStudent($activity, $args, $teacherId, $colegioId);
        if (! $student) {
            return [
                'success'     => false,
                'message'     => 'No se encontró el alumno para calificar en ese curso.',
                'action_type' => 'grade',
                'icon'        => '⚠️',
            ];
        }

        $score = $args['score'];
        $feedback = $args['feedback'] ?? null;

        $grade = Grade::updateOrCreate(
            ['activity_id' => $activity->id, 'student_id' => $student->id],
            ['colegio_id' => $colegioId, 'score' => $score, 'status' => 'published', 'published_at' => now(), 'feedback_text' => $feedback]
        );

        if ($activity->evaluation_id) {
            EvaluationAttempt::updateOrCreate(
                ['evaluation_id' => $activity->evaluation_id, 'student_id' => $student->id],
                ['student_name' => $student->name, 'score' => $score, 'status' => 'graded', 'answers' => []]
            );
        }

        return [
            'success'     => true,
            'message'     => "Calificación guardada para {$student->name} en «{$activity->title}».",
            'action_type' => 'grade',
            'icon'        => '🧮',
            'data'        => [
                'activity_id' => $activity->id,
                'evaluation_id' => $activity->evaluation_id,
                'student_id'  => $student->id,
                'score'       => $grade->score,
                'feedback'    => $grade->feedback_text,
            ],
        ];
    }

    private function setGradeBatch(array $args, int $teacherId): array
    {
        $colegioId = User::where('id', $teacherId)->value('colegio_id');
        $activity = $this->resolveGradableActivity($args, $teacherId);
        if (! $activity) {
            return [
                'success'     => false,
                'message'     => 'No se encontró la actividad o el examen para calificar.',
                'action_type' => 'grade',
                'icon'        => '⚠️',
            ];
        }

        $gradesInput = $args['grades'] ?? [];
        if (empty($gradesInput) || ! is_array($gradesInput)) {
            return [
                'success'     => false,
                'message'     => 'No se recibieron calificaciones para guardar.',
                'action_type' => 'grade',
                'icon'        => '⚠️',
            ];
        }

        $graded = 0;
        $errors = [];
        foreach ($gradesInput as $entry) {
            $studentId = $entry['student_id'] ?? null;
            $studentName = $entry['student_name'] ?? null;
            $score = $entry['score'] ?? null;
            $feedback = $entry['feedback'] ?? null;
            if ($score === null) {
                continue;
            }
            if ($studentId) {
                $student = $this->resolveEnrolledStudent($activity, ['student_id' => $studentId], $teacherId, $colegioId);
            } elseif ($studentName) {
                $student = $this->resolveEnrolledStudent($activity, ['student_name' => $studentName], $teacherId, $colegioId);
            } else {
                $errors[] = 'Cada calificación debe incluir student_id o student_name.';
                continue;
            }
            if (! $student) {
                $errName = $studentName ?? "ID {$studentId}";
                $errors[] = "Alumno «{$errName}» no encontrado.";
                continue;
            }
            Grade::updateOrCreate(
                ['activity_id' => $activity->id, 'student_id' => $student->id],
                ['colegio_id' => $colegioId, 'score' => $score, 'status' => 'published', 'published_at' => now(), 'feedback_text' => $feedback]
            );
            if ($activity->evaluation_id) {
                EvaluationAttempt::updateOrCreate(
                    ['evaluation_id' => $activity->evaluation_id, 'student_id' => $student->id],
                    ['student_name' => $student->name, 'score' => $score, 'status' => 'graded', 'answers' => []]
                );
            }
            $graded++;
        }

        $msg = "{$graded} calificaciones guardadas en «{$activity->title}».";
        if (! empty($errors)) {
            $msg .= ' Errores: ' . implode('; ', $errors);
        }

        return [
            'success'     => true,
            'message'     => $msg,
            'action_type' => 'grade',
            'icon'        => '📊',
            'data'        => [
                'activity_id'  => $activity->id,
                'graded_count' => $graded,
                'errors'       => $errors,
            ],
        ];
    }

    private function publishGrades(array $args, int $teacherId): array
    {
        $colegioId = User::where('id', $teacherId)->value('colegio_id');
        $activity = Activity::where('id', $args['activity_id'] ?? null)
            ->where('teacher_id', $teacherId)
            ->where('colegio_id', $colegioId)
            ->first();
        if (! $activity) {
            return [
                'success'     => false,
                'message'     => 'No se encontró la actividad para publicar notas.',
                'action_type' => 'publish',
                'icon'        => '⚠️',
            ];
        }

        $published = Grade::where('activity_id', $activity->id)
            ->whereNotNull('score')
            ->update([
                'colegio_id' => $colegioId,
                'status'       => 'published',
                'published_at' => now(),
            ]);

        return [
            'success'     => true,
            'message'     => "{$published} calificaciones publicadas para «{$activity->title}».",
            'action_type' => 'publish',
            'icon'        => '🚀',
            'data'        => [
                'activity_id'    => $activity->id,
                'published_count' => $published,
            ],
        ];
    }

    private function findStudent(array $args, int $teacherId): array
    {
        $query = trim((string) ($args['query'] ?? ''));
        $limit = (int) ($args['limit'] ?? 8);
        if ($limit <= 0) {
            $limit = 8;
        }

        $results = Student::where('teacher_id', $teacherId)
            ->where('colegio_id', User::where('id', $teacherId)->value('colegio_id'))
            ->where('name', 'like', '%' . $query . '%')
            ->limit($limit)
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'grade' => $s->grade,
                'section' => $s->section,
            ])
            ->values();

        return [
            'success'     => true,
            'message'     => 'Búsqueda de alumnos completada.',
            'action_type' => 'student_lookup',
            'icon'        => '🔎',
            'data'        => [
                'query' => $query,
                'results' => $results,
            ],
        ];
    }

    private function getGradebookContext(array $args, int $teacherId): array
    {
        $activityId = $args['activity_id'] ?? null;
        $courseId = $args['course_id'] ?? null;
        $limit = (int) ($args['limit'] ?? 50);
        if ($limit <= 0) {
            $limit = 50;
        }

        $start = isset($args['start_date']) ? $this->parseDate($args['start_date']) : null;
        $end = isset($args['end_date']) ? $this->parseDate($args['end_date']) : null;
        if ($start && $end && $end->lt($start)) {
            $end = $start->copy();
        }

        if (! $activityId && ! $courseId) {
            return [
                'success' => false,
                'message' => 'Debes indicar activity_id o course_id para leer el gradebook.',
                'action_type' => 'gradebook',
                'icon' => '⚠️',
            ];
        }

        if ($activityId) {
            $activity = Activity::where('id', $activityId)
                ->where('teacher_id', $teacherId)
                ->with('course:id,subject_name,grade,section')
                ->first();
            if (! $activity) {
                return [
                    'success' => false,
                    'message' => 'Actividad no encontrada para el gradebook.',
                    'action_type' => 'gradebook',
                    'icon' => '⚠️',
                ];
            }

            $students = $activity->course
                ->students()
                ->orderBy('name')
                ->get(['students.id', 'students.name', 'students.grade', 'students.section'])
                ->take($limit);

            $grades = Grade::where('activity_id', $activity->id)
                ->whereIn('student_id', $students->pluck('id'))
                ->get()
                ->keyBy('student_id');

            $items = $students->map(function ($s) use ($grades, $activity) {
                $score = $grades[$s->id]->score ?? null;
                $pct = $score !== null && $activity->max_score > 0
                    ? round(($score / $activity->max_score) * 100, 1)
                    : null;
                return [
                    'student_id' => $s->id,
                    'student_name' => $s->name,
                    'grade' => $s->grade,
                    'section' => $s->section,
                    'score' => $score,
                    'pct' => $pct,
                ];
            });

            return [
                'success' => true,
                'message' => 'Gradebook de actividad leído.',
                'action_type' => 'gradebook',
                'icon' => '📘',
                'data' => [
                    'activity' => [
                        'id' => $activity->id,
                        'title' => $activity->title,
                        'max_score' => $activity->max_score,
                        'course_name' => optional($activity->course)->subject_name . ' ' . optional($activity->course)->grade,
                        'due_date' => $activity->due_date?->format('Y-m-d'),
                    ],
                    'items' => $items,
                ],
            ];
        }

        $course = Course::where('id', $courseId)
            ->where('teacher_id', $teacherId)
            ->first();
        if (! $course) {
            return [
                'success' => false,
                'message' => 'Curso no encontrado para el gradebook.',
                'action_type' => 'gradebook',
                'icon' => '⚠️',
            ];
        }

        $activitiesQuery = Activity::where('course_id', $course->id);
        if ($start) {
            $activitiesQuery->whereDate('due_date', '>=', $start->format('Y-m-d'));
        }
        if ($end) {
            $activitiesQuery->whereDate('due_date', '<=', $end->format('Y-m-d'));
        }
        $activities = $activitiesQuery
            ->orderBy('due_date')
            ->get(['id', 'title', 'due_date', 'max_score', 'type']);

        $activityIds = $activities->pluck('id');
        $grades = Grade::whereIn('activity_id', $activityIds)->get();

        $studentMap = $course->students()
            ->orderBy('name')
            ->get(['students.id', 'students.name', 'students.grade', 'students.section'])
            ->keyBy('id');

        $byStudent = [];
        foreach ($grades as $g) {
            $student = $studentMap[$g->student_id] ?? null;
            if (! $student) {
                continue;
            }
            $activity = $activities->firstWhere('id', $g->activity_id);
            $maxScore = $activity?->max_score ?? 0;
            $pct = $maxScore > 0 ? round(($g->score / $maxScore) * 100, 1) : null;
            $byStudent[$student->id]['student_id'] = $student->id;
            $byStudent[$student->id]['student_name'] = $student->name;
            $byStudent[$student->id]['grade'] = $student->grade;
            $byStudent[$student->id]['section'] = $student->section;
            $byStudent[$student->id]['scores'][] = [
                'activity_id' => $g->activity_id,
                'score' => $g->score,
                'pct' => $pct,
            ];
        }

        $items = collect($byStudent)->map(function ($row) {
            $scores = $row['scores'] ?? [];
            $avg = null;
            if (! empty($scores)) {
                $avg = round(collect($scores)->pluck('pct')->filter()->avg(), 1);
            }
            $row['avg_pct'] = $avg;
            return $row;
        })->values();

        return [
            'success' => true,
            'message' => 'Gradebook de curso leído.',
            'action_type' => 'gradebook',
            'icon' => '📘',
            'data' => [
                'course' => [
                    'id' => $course->id,
                    'name' => $course->subject_name . ' · ' . $course->grade,
                ],
                'activities' => $activities->map(fn ($a) => [
                    'id' => $a->id,
                    'title' => $a->title,
                    'due_date' => $a->due_date?->format('Y-m-d'),
                    'max_score' => $a->max_score,
                    'type' => $a->type ?? 'actividad',
                ])->values(),
                'items' => $items,
            ],
        ];
    }

    private function getPedagogicalHistory(array $args, int $teacherId): array
    {
        $limit = (int) ($args['limit'] ?? 15);
        if ($limit <= 0) {
            $limit = 15;
        }

        $start = isset($args['start_date']) ? $this->parseDate($args['start_date']) : null;
        $end = isset($args['end_date']) ? $this->parseDate($args['end_date']) : null;
        if ($start && $end && $end->lt($start)) {
            $end = $start->copy();
        }

        $activitiesQuery = Activity::where('teacher_id', $teacherId)
            ->with('course:id,subject_name,grade,section')
            ->latest();
        if ($start) {
            $activitiesQuery->whereDate('created_at', '>=', $start->format('Y-m-d'));
        }
        if ($end) {
            $activitiesQuery->whereDate('created_at', '<=', $end->format('Y-m-d'));
        }

        $activities = $activitiesQuery
            ->limit($limit)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'title' => $a->title,
                'type' => $a->type ?? 'actividad',
                'due_date' => $a->due_date?->format('Y-m-d'),
                'course_name' => optional($a->course)->subject_name . ' ' . optional($a->course)->grade,
                'created_at' => $a->created_at?->format('Y-m-d'),
            ])->values();

        $plansQuery = Planificacion::where('user_id', $teacherId)->latest();
        if ($start) {
            $plansQuery->whereDate('created_at', '>=', $start->format('Y-m-d'));
        }
        if ($end) {
            $plansQuery->whereDate('created_at', '<=', $end->format('Y-m-d'));
        }

        $plans = $plansQuery
            ->limit($limit)
            ->get()
            ->map(function ($p) {
                $payload = is_array($p->payload) ? $p->payload : (json_decode($p->payload, true) ?? []);
                return [
                    'id' => $p->id,
                    'tema' => $p->tema,
                    'objetivo' => $p->objetivo,
                    'type' => $payload['type'] ?? 'ai_plan',
                    'created_at' => $p->created_at?->format('Y-m-d'),
                ];
            })->values();

        return [
            'success' => true,
            'message' => 'Historial pedagógico leído.',
            'action_type' => 'history',
            'icon' => '🧠',
            'data' => [
                'activities' => $activities,
                'planifications' => $plans,
            ],
        ];
    }

    /**
     * Valida descripciones Markdown para clases/actividades/tareas según reglas de Aulasync.
     * Devuelve null si es válida, o un mensaje de error en español.
     */
    private function validateLessonDescriptionForNova(string $description, string $resolvedType, ?string $template = null): ?string
    {
        $trimmed = trim($description);
        $template = $template ?: LessonTemplate::CLASSIC;
        $headers = LessonTemplate::promptLine($template);

        if ($trimmed === '') {
            return "La descripción no puede estar vacía. Usa Markdown con {$headers}.";
        }

        $matchesPreferred = LessonTemplate::hasRequiredHeaders($trimmed, $template);
        $matchesAny = $matchesPreferred;
        if (! $matchesAny) {
            foreach ([LessonTemplate::CLASSIC, LessonTemplate::DIRECT, LessonTemplate::CONSTRUCTIVIST] as $id) {
                if (LessonTemplate::hasRequiredHeaders($trimmed, $id)) {
                    $matchesAny = true;
                    break;
                }
            }
        }

        if (! $matchesAny) {
            return "La descripción debe incluir las secciones de tu plantilla en negrita: {$headers}.";
        }

        $paragraphs = preg_split('/\R\s*\R/u', $trimmed, -1, PREG_SPLIT_NO_EMPTY);
        $paragraphs = array_values(array_filter($paragraphs, fn ($p) => trim(strip_tags($p)) !== ''));

        $minParagraphs = $resolvedType === 'tarea' ? 2 : 3;
        $minLen = $resolvedType === 'tarea' ? 280 : 400;

        if (count($paragraphs) < $minParagraphs) {
            return $resolvedType === 'tarea'
                ? "La descripción debe tener al menos dos párrafos separados por una línea en blanco, además de las secciones {$headers}."
                : "La descripción debe tener al menos tres párrafos detallados (separados por línea en blanco), con {$headers} en Markdown.";
        }

        if (mb_strlen($trimmed) < $minLen) {
            return $resolvedType === 'tarea'
                ? 'La descripción es demasiado breve: desarrolla instrucciones y criterios con listas y negritas.'
                : 'La descripción es demasiado breve: desarrolla al menos tres párrafos con contenido copiable, listas y negritas.';
        }

        return null;
    }

    /**
     * Plantilla rica en Markdown para cada hueco de bulkPlan.
     */
    private function buildBulkPlanSessionDescription(string $topic, bool $isThursday, int $teacherId = 0, ?string $template = null): string
    {
        $topicEsc = trim($topic) !== '' ? $topic : 'el tema del curso';
        $template = LessonTemplate::normalize(
            $template ?: $this->activeLessonTemplateFor($teacherId)
        );

        if ($template === 'directa') {
            if ($isThursday) {
                return <<<MD
**MOTIVACIÓN**

Activamos saberes previos sobre {$topicEsc} con una pregunta-problema breve y dos ejemplos cotidianos. Se aclara el propósito de la práctica y por qué importa dominar el procedimiento hoy.

**PRESENTACIÓN**

El docente modela **un ejercicio resuelto** de {$topicEsc} en pizarra: enunciado, pasos numerados y resultado verificado. Se copian al cuaderno el formato y los **criterios de corrección** (procedimiento, exactitud, presentación).

**PRÁCTICA GUIADA**

Estaciones o trabajo en parejas: una ronda con apoyo del docente y otra de aplicación. Se corrigen errores frecuentes en voz alta y se deja un desafío opcional para quienes terminen primero.

**CIERRE REFLEXIVO**

Juego rápido o ronda de justificaciones sobre {$topicEsc}. Ticket de salida: “Lo más importante fue…” y una dificultad detectada. Se anuncia el puente con la próxima clase.
MD;
            }

            return <<<MD
**MOTIVACIÓN**

Se presenta {$topicEsc} con una **pregunta disparadora** y un mapa mental colectivo. Se explicitan saberes previos y el objetivo: qué podrán explicar y aplicar al finalizar.

**PRESENTACIÓN**

Exposición **ordenada para el cuaderno**: definición en negrita, **dos ejemplos resueltos** y un **contraejemplo**. Incluye un esquema numerado y preguntas de procesamiento.

**PRÁCTICA GUIADA**

Los alumnos resuelven ítems con apoyo. El docente circula, corrige y pide justificar cada paso. Todo lo esencial queda redactado para copiar y subrayar.

**CIERRE REFLEXIVO**

Mini-resumen en parejas de tres frases. Juego breve de consolidación. Tarea puente opcional de un ítem para la próxima sesión práctica.
MD;
        }

        if ($template === 'constructivista') {
            if ($isThursday) {
                return <<<MD
**ACTIVACIÓN**

Situación problemática breve sobre {$topicEsc} que conecta con la vida cotidiana. Se recogen hipótesis iniciales del grupo.

**EXPLORACIÓN**

Estaciones de laboratorio o práctica: los alumnos prueban, comparan resultados y registran evidencias en el cuaderno.

**EXPLICACIÓN**

Se formaliza el procedimiento correcto de {$topicEsc} con lenguaje disciplinar, un ejemplo modelo y criterios de calidad.

**APLICACIÓN**

Desafío en parejas o estaciones de transferencia. Incluye un ítem opcional de mayor complejidad.

**EVALUACIÓN**

Ticket de salida y autoevaluación: qué se dominó, qué falta y un ejemplo propio de {$topicEsc}.
MD;
            }

            return <<<MD
**ACTIVACIÓN**

Pregunta provocadora sobre {$topicEsc}. Se activa la curiosidad y se registran ideas previas en un mapa colectivo.

**EXPLORACIÓN**

Los alumnos exploran el fenómeno o concepto con observaciones, lecturas cortas o ejemplos concretos antes de la definición formal.

**EXPLICACIÓN**

El docente sistematiza {$topicEsc}: definición, dos ejemplos resueltos y un error frecuente. Esquema numerado para copiar.

**APLICACIÓN**

Preguntas de procesamiento y un caso nuevo. Trabajo individual o en parejas para transferir el concepto.

**EVALUACIÓN**

Metacognición de tres frases y un ítem de cierre. Se deja puente opcional hacia la próxima práctica.
MD;
        }

        if ($isThursday) {
            return <<<MD
**INICIO** (motivación y saberes previos)

Activamos conocimientos previos sobre {$topicEsc} con una pregunta-problema breve y dos ejemplos cotidianos. Se registra en voz alta qué se entiende ya y qué falta aclarar, para ajustar el ritmo de la práctica.

**DESARROLLO** (explicación y contenido para copiar)

Se organizan **estaciones de trabajo** o **laboratorio guiado** sobre {$topicEsc}: una estación con ejercicios modelo en la pizarra (para copiar el formato), otra con aplicación en parejas y una tercera con desafío opcional. En el cuaderno deben dejar: enunciado, procedimiento y resultado verificado. Incluye **criterios de corrección** al pie (qué se valora: procedimiento, exactitud, presentación).

**CIERRE** (actividad de fijación o juego)

Cierre con **juego rápido** (quiz de 5 ítems o “¿verdadero o falso?”) o **ronda de justificaciones** sobre {$topicEsc}. Ticket de salida de una línea: “Lo más importante fue…” y una dificultad detectada. Se anuncia el vínculo con la próxima clase teórica.
MD;
        }

        return <<<MD
**INICIO** (motivación y saberes previos)

Se presenta {$topicEsc} con una **pregunta disparadora** y un mapa mental colectivo en pizarra. Se explicitan **saberes previos** que el curso ya domina y se delimita el objetivo de la clase: qué van a poder explicar y aplicar al finalizar.

**DESARROLLO** (explicación y contenido para copiar)

Exposición **ordenada para el cuaderno**: definición en negrita, **dos ejemplos resueltos** y un **contraejemplo** o error frecuente. Incluye un **esquema numerado** (pasos o propiedades) y **preguntas de procesamiento** para resolver en clase. Todo lo esencial debe quedar redactado para **copiar y subrayar** conceptos clave.

**CIERRE** (actividad de fijación o juego)

**Juego breve de consolidación** (sorteo de tarjetas, “completa el hueco” o memoria conceptual) sobre {$topicEsc}. **Metacognición**: en parejas, un mini-resumen de tres frases. Se deja **tarea puente** opcional si el docente lo desea (1 ítem para preparar el jueves práctico).
MD;
    }

    /**
     * Genera título descriptivo para sesiones de bulkPlan (varía según tipo y secuencia).
     */
    private function generateSessionTitle(string $topic, bool $isThursday, int $sessionNum): string
    {
        $topicCapitalized = ucfirst(trim($topic));
        
        if ($isThursday) {
            $patterns = [
                "{$topicCapitalized}: Ejercicios prácticos",
                "{$topicCapitalized}: Taller grupal",
                "{$topicCapitalized}: Práctica guiada",
                "{$topicCapitalized}: Actividad lúdica",
                "{$topicCapitalized}: Laboratorio",
            ];
            return $patterns[($sessionNum - 1) % count($patterns)];
        } else {
            $patterns = [
                "{$topicCapitalized}: Introducción",
                "{$topicCapitalized}: Conceptos clave",
                "{$topicCapitalized}: Teoría fundamental",
                "{$topicCapitalized}: Profundización",
                "{$topicCapitalized}: Repaso teórico",
            ];
            return $patterns[($sessionNum - 1) % count($patterns)];
        }
    }

    private function buildNeeAdaptation(string $neeType): string
    {
        return match (mb_strtolower($neeType)) {
            'tdah' => "Para este alumno con TDAH, segmenta la actividad en pasos de 10 minutos, usa recordatorios visuales y alterna momentos de movimiento breve. Evita instrucciones largas y confirma comprensión con preguntas cortas.",
            'tea', 'autismo', 'tea/autismo', 'tea - autismo' => "Para este alumno con TEA, anticipa la secuencia con un mini-guion visual, reduce estímulos distractores y ofrece ejemplos concretos. Permite tiempos de respuesta más amplios y valida con apoyos visuales.",
            'dislexia' => "Para este alumno con dislexia, prioriza instrucciones orales claras, textos con tipografía legible y apoyos visuales. Permite responder de forma oral o con opciones guiadas y evita copiado extenso.",
            'discalculia' => "Para este alumno con discalculia, utiliza material manipulativo, ejemplos paso a paso y apoyos visuales. Evita sobrecarga numérica y ofrece tiempo adicional para resolver.",
            default => "Para este alumno con {$neeType}, adapta la actividad con instrucciones breves, apoyos visuales y tiempo extra según necesidad. Prioriza la comprensión del objetivo sobre la cantidad de ejercicios.",
        };
    }

    private function hasDeleteIntent(?string $text): bool
    {
        $value = mb_strtolower((string) $text);
        return (bool) preg_match('/\b(borr\w*|elimin\w*|limpi\w*|vaci\w*|quit\w*)\b/u', $value);
    }

    private function hasPlanningIntent(?string $text): bool
    {
        $value = mb_strtolower((string) $text);
        return (bool) preg_match('/\b(planifica|planificar|planificación|planificacion|cronograma|calendario|genera.*mes|organiza.*mes|mes de|desglosa|desglosar|siguientes d[ií]as|pr[oó]ximos d[ií]as)\b/u', $value);
    }

    private function hasCreateEvaluationIntent(?string $text): bool
    {
        $value = mb_strtolower((string) $text);
        if (preg_match('/plan\s+de\s+evaluaci/u', $value)) {
            return false;
        }
        $wantsCreate = (bool) preg_match('/\b(crea|crear|genera|generar|hazme|haz|hacer|arma|armar|prepara|preparar|diseña|diseñar|elabora|elaborar)\b/u', $value);
        $wantsExam = (bool) preg_match('/\b(examen|exámenes|examenes|prueba|pruebas|quiz|evaluaci[oó]n|evaluaciones)\b/u', $value);

        return $wantsCreate && $wantsExam;
    }

    /**
     * Intención de modificar actividades/clases (dispara pre-carga de calendario extendido).
     */
    private function hasModifyIntent(?string $text): bool
    {
        $value = mb_strtolower((string) $text);
        return (bool) preg_match('/\b(modificar|cambiar|editar|actualizar|reemplazar)\b/u', $value);
    }

    private function hasProceedIntent(?string $text): bool
    {
        $value = mb_strtolower((string) $text);
        return (bool) preg_match('/\b(procede|proceder|adelante|continua|continuar|confirmo|confirmar|ok|vale|sí|si)\b/u', $value);
    }

    private function extractDateRangeFromText(?string $text): ?array
    {
        $value = mb_strtolower((string) $text);
        $year = (int) now()->format('Y');
        $monthMap = [
            'enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4, 'mayo' => 5, 'junio' => 6,
            'julio' => 7, 'agosto' => 8, 'septiembre' => 9, 'setiembre' => 9, 'octubre' => 10,
            'noviembre' => 11, 'diciembre' => 12,
        ];
        $monthRegex = implode('|', array_keys($monthMap));

        if (preg_match('/(\d{4})-(\d{1,2})-(\d{1,2}).*?(?:al|a|-|hasta)\s*(\d{4})-(\d{1,2})-(\d{1,2})/u', $value, $m)) {
            return $this->buildDateRange((int) $m[1], (int) $m[2], (int) $m[3], (int) $m[4], (int) $m[5], (int) $m[6]);
        }

        if (preg_match('/(?:del?\s+)?(\d{1,2})[\/\-](\d{1,2})(?:[\/\-](\d{2,4}))?.*?(?:al|a|-|hasta)\s*(\d{1,2})[\/\-](\d{1,2})(?:[\/\-](\d{2,4}))?/u', $value, $m)) {
            $startYear = $this->normalizeYear($m[3] ?? $year);
            $endYear = $this->normalizeYear($m[6] ?? $startYear);
            return $this->buildDateRange($startYear, (int) $m[2], (int) $m[1], $endYear, (int) $m[5], (int) $m[4]);
        }

        if (preg_match('/\b(?:todo(?:\s+lo\s+que\s+hay)?\s+en|todo\s+el\s+mes\s+de|el\s+mes\s+de|mes\s+de|en)\s+(' . $monthRegex . ')(?:\s+de\s+(\d{4}))?\b/u', $value, $m)) {
            $monthName = $m[1] ?? null;
            $parsedYear = isset($m[2]) ? (int) $m[2] : $year;
            $month = $monthMap[$monthName] ?? null;
            if ($month) {
                try {
                    $start = Carbon::createFromDate($this->normalizeYear($parsedYear), $month, 1)->startOfMonth();
                    return [
                        'start_date' => $start->format('Y-m-d'),
                        'end_date' => $start->copy()->endOfMonth()->format('Y-m-d'),
                    ];
                } catch (\Throwable) {
                    return null;
                }
            }
        }

        return null;
    }

    private function buildDateRange(int $startYear, int $startMonth, int $startDay, int $endYear, int $endMonth, int $endDay): ?array
    {
        try {
            $start = Carbon::createFromDate($startYear, $startMonth, $startDay)->startOfDay();
            $end = Carbon::createFromDate($endYear, $endMonth, $endDay)->startOfDay();
            if ($end->lt($start)) {
                [$start, $end] = [$end, $start];
            }
            return [
                'start_date' => $start->format('Y-m-d'),
                'end_date' => $end->format('Y-m-d'),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeYear($value): int
    {
        $year = (int) $value;
        if ($year < 100) {
            $year += 2000;
        }
        return $year > 0 ? $year : (int) now()->format('Y');
    }

    private function buildActionResponsePayload(array $results): array
    {
        $actions = collect($results)->map(function ($result) {
            $success = (bool) ($result['success'] ?? false);
            $actionType = $result['action_type'] ?? 'info';
            $message = $result['message'] ?? '';
            if ($success && $actionType !== 'bulk_plan') {
                $message = $this->withProactiveClose($message, $actionType);
            }
            return [
                'success' => $success,
                'status' => $result['status'] ?? ($success ? 'success' : 'error'),
                'message' => $message,
                'action_type' => $actionType,
                'icon' => $result['icon'] ?? ($success ? '✅' : 'ℹ️'),
                'data' => $result['data'] ?? [],
            ];
        })->toArray();

        $anySuccess = collect($actions)->contains(fn ($action) => $action['success']);
        $bulkMeta = $this->extractBulkPlanResponseMeta($results);

        return array_filter([
            'success' => true,
            'status' => $bulkMeta ? 'success' : ($anySuccess ? 'success' : 'partial'),
            'actions' => $actions,
            'any_success' => $anySuccess,
            'bulk_plan' => $bulkMeta,
            'message' => $bulkMeta['assistant_message'] ?? null,
            'data' => $actions,
        ], fn ($v) => $v !== null);
    }

    private function extractEvaluationArgsFromIntent(string $intentText, array $screenContext, User $teacher): array
    {
        $args = [
            'prompt' => $intentText,
            'course_id' => ! empty($screenContext['id']) ? (int) $screenContext['id'] : 0,
            'course_name_hint' => null,
            'add_to_plan' => true,
            'status' => 'published',
        ];

        if (is_string($screenContext['subject_name'] ?? null)) {
            $args['course_name_hint'] = trim(($screenContext['subject_name'] ?? '').' '.($screenContext['grade'] ?? ''));
        }
        if (empty($args['course_name_hint'])) {
            $args['course_name_hint'] = $intentText;
        }

        if (preg_match('/(?:peso|weight|porcentaje|percentage)\D{0,16}(\d{1,3})\s*%?/iu', $intentText, $m)
            || preg_match('/(\d{1,3})\s*%/u', $intentText, $m)) {
            $weight = (float) $m[1];
            if ($weight > 0 && $weight <= 100) {
                $args['weight_percentage'] = $weight;
            }
        }

        $lower = mb_strtolower($intentText);
        if (preg_match('/\bhoy\b/u', $lower)) {
            $args['due_date'] = now()->toDateString();
        } elseif (preg_match('/\bma[nñ]ana\b/u', $lower)) {
            $args['due_date'] = now()->addDay()->toDateString();
        } elseif (preg_match('/\b(\d{4}-\d{2}-\d{2})\b/', $intentText, $m)) {
            $args['due_date'] = $m[1];
        }

        if ((int) $args['course_id'] <= 0) {
            $resolved = $this->lookupCourseByHint($teacher->id, $teacher->colegio_id, $intentText, '');
            if ($resolved) {
                $args['course_id'] = $resolved;
            }
        }

        return $args;
    }
}
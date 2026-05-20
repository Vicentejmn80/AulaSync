<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Course;
use App\Models\Grade;
use App\Models\Planificacion;
use App\Models\Student;
use App\Models\Tarea;
use Illuminate\Support\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AICommandHandlerController extends Controller
{
private const DESTRUCTIVE = ['destroyCourse', 'destroyAllStudentsFromCourse', 'deleteResource', 'deleteActivities'];

    /**
     * Definiciones de herramientas para OpenAI
     */
    private function toolDefinitions(): array
    {
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
                                'description' => 'Markdown obligatorio: secciones **INICIO**, **DESARROLLO**, **CIERRE** en negrita; mínimo 3 párrafos separados por línea en blanco; viñetas y **negritas** en conceptos clave.',
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
                    'description' => 'Genera planificación mensual para cualquier mes/año. Regla fija: Lunes = Teoría/Cuaderno y Jueves = Práctica/Lúdica. Cada sesión guarda descripción Markdown detallada.',
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
                                    'repeat_days' => ['type' => 'array', 'items' => ['type' => 'string']],
                                    'allow_past' => ['type' => 'boolean'],
                                    'override_conflicts' => ['type' => 'boolean'],
                                ],
                            ],
                            'confirmed' => ['type' => 'boolean', 'description' => 'Si true, crea directamente sin confirmación. Usa true cuando el usuario ya dio todos los datos (curso, mes, temas, días) y no hay ambigüedad.'],
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
                            'activity_id' => ['type' => 'integer'],
                            'score'       => ['type' => 'number'],
                            'feedback'    => ['type' => 'string'],
                        ],
                        'required' => ['student_id', 'activity_id', 'score'],
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
        ];
    }

    /**
     * Punto de entrada principal
     */
    public function handle(Request $request): JsonResponse
    {
        try {
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
            return response()->json(['error' => 'No autenticado.'], 401);
        }
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

        if ($confirmed && session()->has('nova_pending_actions')) {
            $pendingToolCalls = session()->pull('nova_pending_actions');
            Log::info('AICommandHandler: executing confirmed pending actions', [
                'teacher_id' => $teacher->id,
                'tool_calls_count' => count($pendingToolCalls),
            ]);

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
                }
                if (isset($args['course_id']) && $args['course_id'] <= 0 && !empty($screenContext['id'])) {
                    $args['course_id'] = $screenContext['id'];
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

            return response()->json(array_filter([
                'success'      => true,
                'status'       => $bulkMeta ? 'success' : ($anySuccess ? 'success' : 'partial'),
                'results'      => $results,
                'actions'      => $actions,
                'any_success'  => $anySuccess,
                'bulk_plan'    => $bulkMeta,
                'message'      => $bulkMeta['assistant_message'] ?? null,
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
            return response()->json([
                'success' => false,
                'message' => '⚠️ No hay acción pendiente de confirmar (quizá la sesión expiró o ya se aplicó). Vuelve a pedir la planificación o el borrado.',
            ]);
        }

        $today = now()->format('Y-m-d');

        // Contexto de cursos para la IA
        $courses = Course::where('teacher_id', $teacher->id)->get(['id', 'subject_name', 'grade']);
        $coursesContext = "Cursos del profesor:\n" . $courses->map(fn($c) => "ID:{$c->id} - {$c->subject_name} ({$c->grade})")->join("\n");

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
            "Eres Nova, asistente conversacional activo para docentes. Tu prioridad es ejecutar con criterio y solo preguntar cuando sea estrictamente vital.",
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
            "CUANDO SÍ EJECUTAR CON HERRAMIENTA:",
            "- Lecturas (getCalendarContext, getGradebookContext, findStudent, getPedagogicalHistory) puedes llamarlas si el usuario pidió consultar y el rango o filtros están claros o se deducen del contexto sin adivinar cursos.",
            "- Si este prompt ya incluye «Calendario extendido» (borrar/modificar), no llames getCalendarContext para repetir el mismo rango; solo si necesitas otro rango o course_id distinto.",
            "- Escrituras de creación/modificación: ejecuta en el mismo turno cuando tengas intención clara + curso resoluble (por historial/contexto). Usa defaults en campos no críticos.",
            "- Escrituras destructivas (borrar): cuando haya intención clara y objetivo identificable, ejecuta deleteActivities o deleteResource inmediatamente. NO pidas confirmación adicional.",
            "- bulkPlan mensual: SIEMPRE pasa el target_month con el nombre del mes que el usuario mencionó (ej: si dice 'mayo' o 'april', pasa 'mayo' o 'april' tal cual). NUNCA lo omitas ni uses la fecha actual.",
            "- Mapping pedagógico obligatorio en bulkPlan: Lunes = Teoría/Cuaderno, Jueves = Práctica/Lúdica.",
            "- EJECUCIÓN DIRECTA DE bulkPlan: si el usuario da una instrucción completa con mes, curso, temas y días (ej. «planifica abril para primer grado con colors, numbers y sports los lunes y jueves»), pasa 'confirmed': true en el primer llamado a bulkPlan para crear todo directamente sin pedir confirmación previa.",
            "- Si bulkPlan devuelve requires_confirmation, significa que faltaban datos o había conflictos; repregunta entonces. Pero si la instrucción original era completa y clara, evita ese paso pasando confirmed desde el inicio.",
            "- CRÍTICO: el target_month es OBLIGATORIO para bulkPlan. Si el usuario menciona un mes (abril, mayo, junio, etc.), DEBES incluirlo en el llamado a la herramienta.",
            "",
            "REGLAS DE ORO — DESCRIPCIONES PEDAGÓGICAS (solo al rellenar createActivity / textos largos; bulkPlan lo arma el sistema):",
            "- Estructura obligatoria en Markdown: **INICIO**, **DESARROLLO**, **CIERRE** en negrita.",
            "- Riqueza: al menos tres párrafos sustantivos separados por línea en blanco cuando el usuario pide desarrollar; listas y **negritas**.",
            "",
            "MAPA DE INTENCIONES → HERRAMIENTA:",
            "- crear curso / sección → createCourse",
            "- crear clase / actividad / evaluación / tarea → createActivity  (type: clase|actividad|tarea)",
            "- adaptación NEE / TDAH / TEA / dislexia / discalculia → createActivity con nee_type relleno",
            "- modificar / cambiar / editar actividad existente → modifyActivity",
            "- inscribir / agregar alumnos → registerStudent",
            "- planificar mes / cronograma / calendario → bulkPlan",
            "- borrar / eliminar / quitar actividades en un rango de fechas → deleteActivities",
            "- borrar / eliminar / quitar una actividad, curso o alumno específico → deleteResource",
            "- consultar calendario en rango de fechas → getCalendarContext",
            "- buscar alumno por nombre → findStudent",
            "- asignar calificación puntual → setGrade",
            "- leer libro de calificaciones → getGradebookContext",
            "- leer historial pedagógico → getPedagogicalHistory",
            "- ver qué tengo esta semana / qué hay esta semana / mi agenda semanal → getCurrentWeek",
            "",
            "REGLAS CRÍTICAS DE BORRADO:",
            "1. IDENTIFICACIÓN DE ID: Cada línea del calendario inyectado incluye 'actividad_id XXXX'. Cuando el usuario pida borrar una actividad específica (ej: 'borra la clase del jueves' o 'elimina la actividad de números'), primero localiza su activity_id en el calendario inyectado.",
            "2. BORRADO POR ID (preferido para actividades específicas): Si identificaste un activity_id único, usa deleteResource con resource_type='activity' y resource_id=<el_id>. Ejemplo: usuario dice 'borra la clase de matemáticas del 15 de abril' → busca en calendario inyectado esa fecha → encuentra 'actividad_id 42' → llama deleteResource(resource_type='activity', resource_id=42).",
            "3. BORRADO POR RANGO (para múltiples actividades): Si el usuario pide borrar varias actividades (ej: 'borra todas las clases de marzo', 'elimina la semana completa'), usa deleteActivities con course_id, start_date y end_date.",
            "4. EJECUCIÓN DIRECTA: Si el calendario inyectado muestra claramente qué actividades se borrarán, ejecuta la herramienta de borrado de inmediato (sin pedir confirmación).",
            "5. SOLO SI EL USUARIO LO PIDIÓ: deleteResource o deleteActivities ÚNICAMENTE si el usuario explícitamente pidió borrar/eliminar/limpiar/vaciar.",
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

        $toolChoice = $hasDeleteIntent ? 'required' : 'auto';
        $response = Http::timeout(120)
            ->withToken(env('OPENAI_API_KEY'))
            ->post('https://api.openai.com/v1/chat/completions', [
                'model'       => 'gpt-4o',
                'temperature' => 0,
                'tool_choice' => $toolChoice,
                'tools'       => $this->toolDefinitions(),
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
                    : '¿Qué quieres hacer? Indica curso (o materia/grado), tema y fecha si aplica.';
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
                'grade_actions'         => $gradeActions,
                'warning'               => 'Vas a calificar múltiples alumnos. ¿Confirmas la acción?',
            ]);
        }

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

            // Resolver ID de curso si es 0 usando el nombre o el contexto de pantalla
            if (isset($args['course_id']) && $args['course_id'] <= 0) {
                if (!empty($screenContext['id'])) {
                    $args['course_id'] = $screenContext['id'];
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
                'message' => $planConfirmation['message'] ?? 'Confirma la planificación propuesta.',
                'plan_preview' => $planConfirmation['plan_preview'] ?? [],
                'conflicts' => $planConfirmation['conflicts'] ?? [],
                'actions' => [$planConfirmation],
            ]);
        }

        $actions = collect($results)->map(function ($result) {
            $success = (bool) ($result['success'] ?? false);
            $actionType = $result['action_type'] ?? 'info';
            $message = $result['message'] ?? '';
            if ($success && $actionType !== 'bulk_plan') {
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

        return response()->json(array_filter([
            'success'      => true,
            'status'       => $bulkMeta ? 'success' : ($anySuccess ? 'success' : 'partial'),
            'results'      => $results,
            'actions'      => $actions,
            'any_success'  => $anySuccess,
            'bulk_plan'    => $bulkMeta,
            'message'      => $bulkMeta['assistant_message'] ?? null,
        ], fn ($v) => $v !== null));
        } catch (\Throwable $e) {
            Log::error('AICommandHandler error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al procesar el comando de IA.',
                'error'   => config('app.debug')
                    ? $e->getMessage()
                    : 'Error interno al procesar la solicitud.',
            ], 500);
        }
    }

    /**
     * Dispatcher de acciones
     */
    private function executeAction(string $fn, array $args, int $teacherId, array &$createdCourseMap): array
    {
        try {
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
                'findStudent'     => $this->findStudent($args, $teacherId),
                'getGradebookContext' => $this->getGradebookContext($args, $teacherId),
                'getPedagogicalHistory' => $this->getPedagogicalHistory($args, $teacherId),
                'getCurrentWeek' => $this->getCurrentWeek($args, $teacherId),
                default           => ['success' => false, 'message' => "Acción $fn no definida."],
            };
        } catch (\Throwable $e) {
            Log::error('AICommandHandler executeAction failed', [
                'function' => $fn,
                'teacher_id' => $teacherId,
                'args' => $args,
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'message' => 'No se pudo completar esta acción en este momento. Inténtalo de nuevo.',
            ];
        }
    }

    // ─── IMPLEMENTACIONES DE LOGICA ──────────────────────────────────────────

    private function doCreateCourse($args, $teacherId, &$createdCourseMap)
    {
        $course = Course::create([
            'teacher_id'   => $teacherId,
            'subject_name' => $args['subject_name'],
            'grade'        => $args['grade'],
            'section'      => $args['section'] ?? null,
        ]);
        $createdCourseMap[strtolower($args['subject_name'])] = $course->id;
        return [
            'success'     => true,
            'message'     => "Curso creado: {$course->subject_name}",
            'action_type' => 'course',
            'icon'        => '🏫',
            'data'        => ['course_id' => $course->id],
        ];
    }

    private function doCreateActivity($args, $teacherId)
    {
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
        $descError = $this->validateLessonDescriptionForNova($description, $semanticType);
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
            'course_id'         => $normalizedArgs['course_id'],
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

        $activity = Activity::create($payload);

        $courseName = Course::where('id', $activity->course_id)->value('subject_name');
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
            default => ' ¿Quieres que siga con el siguiente paso?',
        };

        return rtrim($trimmed, " \t\n\r\0\x0B.") . '.' . $followUp;
    }

    private function doModifyActivity($args, $teacherId)
    {
        $activity = Activity::where('id', $args['activity_id'])->first();
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
        $course = Course::where('id', $args['course_id'])->first();
        if (! $course) {
            return [
                'success'     => false,
                'message'     => 'No se pudo identificar el curso. Indica el curso completo o su ID.',
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

        $results = [];
        foreach ($args['names'] as $name) {
            $student = Student::firstOrCreate(
                ['name' => $name, 'teacher_id' => $teacherId],
                ['grade' => $grade]
            );
            $student->courses()->syncWithoutDetaching([$args['course_id']]);
            $results[] = $name;
        }
        return [
            'success'     => true,
            'message'     => "Alumnos inscritos: " . implode(', ', $results),
            'action_type' => 'student',
            'icon'        => '👩‍🎓',
            'data'        => ['names' => $results, 'course_id' => $args['course_id'], 'grade' => $grade],
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

        $plan = [];
        $mondayCount = 0;
        $thursdayCount = 0;
        $cursor = $startDate->copy();
        while ($cursor->lte($endDate)) {
            if (in_array($cursor->dayOfWeek, $repeatDays, true)) {
                $isThursday = $cursor->dayOfWeek === Carbon::THURSDAY;
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
                ]);
                $plan[] = [
                    'date' => $cursor->format('Y-m-d'),
                    'title' => $titleDescriptive,
                    'type' => $isThursday ? 'actividad' : 'clase',
                    'description' => $this->buildBulkPlanSessionDescription($topic, $isThursday),
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
        $course = Course::where('id', (int) ($args['course_id'] ?? 0))
            ->where('teacher_id', $teacherId)
            ->first();
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
            'monday' => Carbon::MONDAY,
            'martes' => Carbon::TUESDAY,
            'tuesday' => Carbon::TUESDAY,
            'miércoles' => Carbon::WEDNESDAY,
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
        $query = Activity::where('teacher_id', $teacherId)
            ->whereBetween('due_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->with('course:id,subject_name,grade,section');

        if ($courseId !== null && $courseId > 0) {
            $query->where('course_id', $courseId);
        }

        return $query->orderBy('due_date')->get();
    }

    /**
     * Lista legible para el system prompt (IDs explícitos para borrar/modificar sin adivinar).
     */
    private function buildCalendarSnapshotLines(int $teacherId, Carbon $start, Carbon $end, ?int $courseId = null): string
    {
        $items = $this->calendarActivitiesBetween($teacherId, $start, $end, $courseId);
        if ($items->isEmpty()) {
            return '(sin actividades en este rango)';
        }

        return $items->map(function ($a) {
            $d = $a->due_date instanceof Carbon ? $a->due_date->format('Y-m-d') : (string) $a->due_date;
            $cn = trim((optional($a->course)->subject_name ?? '') . ' ' . (optional($a->course)->grade ?? ''));
            $title = str_replace(["\r", "\n"], ' ', (string) $a->title);
            $type = $a->type ?? 'actividad';

            return "- {$d} | actividad_id {$a->id} | course_id {$a->course_id} | {$cn} | {$title} | {$type}";
        })->join("\n");
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
        $activity = Activity::where('id', $args['activity_id'] ?? null)
            ->where('teacher_id', $teacherId)
            ->first();
        if (! $activity) {
            return [
                'success'     => false,
                'message'     => 'No se encontró la actividad para calificar.',
                'action_type' => 'grade',
                'icon'        => '⚠️',
            ];
        }

        $student = Student::where('id', $args['student_id'] ?? null)
            ->where('teacher_id', $teacherId)
            ->first();
        if (! $student) {
            return [
                'success'     => false,
                'message'     => 'No se encontró el alumno para calificar.',
                'action_type' => 'grade',
                'icon'        => '⚠️',
            ];
        }

        $score = $args['score'];
        $feedback = $args['feedback'] ?? null;

        $grade = Grade::updateOrCreate(
            ['activity_id' => $activity->id, 'student_id' => $student->id],
            ['score' => $score, 'feedback_text' => $feedback]
        );

        return [
            'success'     => true,
            'message'     => "Calificación guardada para {$student->name}.",
            'action_type' => 'grade',
            'icon'        => '🧮',
            'data'        => [
                'activity_id' => $activity->id,
                'student_id'  => $student->id,
                'score'       => $grade->score,
                'feedback'    => $grade->feedback_text,
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
     * Valida descripciones Markdown para clases/actividades/tareas según reglas de Nova.
     * Devuelve null si es válida, o un mensaje de error en español.
     */
    private function validateLessonDescriptionForNova(string $description, string $resolvedType): ?string
    {
        $trimmed = trim($description);
        if ($trimmed === '') {
            return 'La descripción no puede estar vacía. Usa Markdown con **INICIO**, **DESARROLLO** y **CIERRE**.';
        }

        foreach (['INICIO', 'DESARROLLO', 'CIERRE'] as $label) {
            if (! preg_match('/\*\*\s*' . preg_quote($label, '/') . '\s*\*\*/u', $trimmed)) {
                return 'La descripción debe incluir tres secciones en negrita: **INICIO**, **DESARROLLO** y **CIERRE** (motivación y saberes previos; contenido para copiar; cierre o juego).';
            }
        }

        $paragraphs = preg_split('/\R\s*\R/u', $trimmed, -1, PREG_SPLIT_NO_EMPTY);
        $paragraphs = array_values(array_filter($paragraphs, fn ($p) => trim(strip_tags($p)) !== ''));

        $minParagraphs = $resolvedType === 'tarea' ? 2 : 3;
        $minLen = $resolvedType === 'tarea' ? 280 : 400;

        if (count($paragraphs) < $minParagraphs) {
            return $resolvedType === 'tarea'
                ? 'La descripción debe tener al menos dos párrafos separados por una línea en blanco, además de las secciones **INICIO**, **DESARROLLO** y **CIERRE**.'
                : 'La descripción debe tener al menos tres párrafos detallados (separados por línea en blanco), con **INICIO**, **DESARROLLO** y **CIERRE** en Markdown.';
        }

        if (mb_strlen($trimmed) < $minLen) {
            return $resolvedType === 'tarea'
                ? 'La descripción es demasiado breve: desarrolla instrucciones y criterios con listas y negritas.'
                : 'La descripción es demasiado breve: desarrolla al menos tres párrafos con contenido copiable, listas y negritas.';
        }

        return null;
    }

    /**
     * Plantilla rica en Markdown para cada hueco de bulkPlan (Lunes / Jueves).
     */
    private function buildBulkPlanSessionDescription(string $topic, bool $isThursday): string
    {
        $topicEsc = trim($topic) !== '' ? $topic : 'el tema del curso';
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
        return (bool) preg_match('/\b(planifica|planificar|planificación|planificacion|cronograma|calendario|genera.*mes|organiza.*mes|mes de)\b/u', $value);
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
            'results' => $results,
            'actions' => $actions,
            'any_success' => $anySuccess,
            'bulk_plan' => $bulkMeta,
            'message' => $bulkMeta['assistant_message'] ?? null,
        ], fn ($v) => $v !== null);
    }
}
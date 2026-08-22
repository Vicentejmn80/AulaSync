<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Course;
use App\Support\GradingScale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ActivitiesController extends Controller
{
    /**
     * Muestra la lista de actividades.
     */
    public function index(Request $request): View|JsonResponse
    {
        $activities = Activity::query()
            ->select([
                'id',
                'teacher_id',
                'course_id',
                'title',
                'description',
                'type',
                'max_score',
                'weight_percentage',
                'due_date',
                'is_homework',
                'nee_type',
                'nee_adaptation',
                'created_at',
            ])
            ->with([
                'course:id,subject_name,grade,section',
                'tareas:id,actividad_id,titulo,descripcion,fecha_entrega,puntos,calificacion,feedback',
            ])
            ->where('teacher_id', auth()->id())
            ->withCount('tareas')
            ->latest()
            ->paginate(20);

        if ($request->wantsJson()) {
            return response()->json($activities->map(fn ($activity) => $this->serializeActivity($activity))->values());
        }

        $courses = Course::where('teacher_id', auth()->id())
            ->with(['students:id,name'])
            ->orderBy('subject_name')
            ->get(['id', 'subject_name', 'grade', 'section']);

        $coursesJson = $courses->map(fn($c) => [
            'id' => $c->id,
            'students' => $c->students->map(fn($s) => ['id' => $s->id, 'name' => $s->name])->values(),
        ])->values();

        return view('teacher.activities.index', compact('activities', 'courses', 'coursesJson'));
    }

    private function serializeActivity(Activity $activity): array
    {
        $course = $activity->course;
        $tareas = $activity->tareas->map(fn ($tarea) => [
            'id' => $tarea->id,
            'titulo' => $tarea->titulo,
            'descripcion' => $tarea->descripcion,
            'fecha_entrega' => optional($tarea->fecha_entrega)->format('Y-m-d'),
            'puntos' => $tarea->puntos,
            'calificacion' => $tarea->calificacion,
            'feedback' => $tarea->feedback, 
        ])->values()->toArray();

        return [
            'id' => $activity->id,
            'title' => $activity->title,
            'description' => $activity->description,
            'type' => $activity->type ?? 'actividad',
            'max_score' => $activity->max_score,
            'weight_percentage' => $activity->weight_percentage,
            'due_date' => optional($activity->due_date)->format('Y-m-d'),
            'course_id' => $activity->course_id,
            'course_name' => $course?->subject_name ? $course->subject_name . ($course->grade ? ' · ' . $course->grade : '') : '—',
            'course_grade' => $course?->grade,
            'course_section' => $course?->section,
            'is_homework' => (bool) $activity->is_homework,
            'nee_type' => $activity->nee_type,
            'nee_adaptation' => $activity->nee_adaptation,
            'tareas_count' => $activity->tareas_count ?? count($tareas),
            'tareas' => $tareas,
        ];
    }

    /**
     * Muestra el formulario de creación (Necesario para la ruta .create).
     */
    public function create(): View
    {
        $courses = Course::where('teacher_id', auth()->id())
            ->with(['students:id,name'])
            ->orderBy('subject_name')
            ->get(['id', 'subject_name', 'grade', 'section']);

        return view('teacher.activities.create', compact('courses'));
    }

    /**
     * Almacena una nueva actividad.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'course_id'          => ['required', 'exists:courses,id'],
            'title'              => ['required', 'string', 'max:160'],
            'description'        => ['nullable', 'string', 'max:500'],
            'max_score'          => ['required', 'integer', 'min:1', 'max:100'],
            'weight_percentage'  => ['required', 'numeric', 'min:0', 'max:100'],
            'due_date'           => ['nullable', 'date'],
            'type'               => ['nullable', 'string', 'max:50'],
            'is_homework'        => ['sometimes', 'boolean'],
            'has_adaptation'     => ['sometimes', 'boolean'],
            'nee_type'           => ['nullable', 'string', 'max:80'],
            'nee_adaptation'     => ['nullable', 'string', 'max:2000'],
            'nee_students'       => ['nullable', 'array'],
            'nee_students.*'     => ['integer', 'exists:students,id'],
        ]);

        // Verificar que el curso pertenezca al docente
        $course = Course::where('id', $data['course_id'])
            ->where('teacher_id', auth()->id())
            ->where('colegio_id', auth()->user()->colegio_id)
            ->firstOrFail();

        $scaleMax = GradingScale::maxFor($course->grading_scale);
        if ((int) $data['max_score'] > $scaleMax) {
            return back()
                ->withInput()
                ->withErrors(['max_score' => "La nota máxima no puede superar la escala del curso ({$scaleMax})."]);
        }

        $isHomework = $request->boolean('is_homework');
        $requestedType = trim((string) ($data['type'] ?? 'actividad'));
        $typeMeta = Activity::normalizeType($requestedType, $isHomework);
        $resolvedType = $typeMeta['type'];
        $isHomework = $typeMeta['is_homework'];

        $adaptationEnabled = $request->boolean('has_adaptation');
        $neeType = $adaptationEnabled ? ($data['nee_type'] ?? 'Personalizada') : null;
        $neeAdaptation = null;

        if ($adaptationEnabled) {
            $selectedStudentIds = collect($data['nee_students'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->unique()
                ->values();

            $studentsForAdaptation = collect();
            if ($selectedStudentIds->isNotEmpty()) {
                $studentsForAdaptation = $course->students()
                    ->whereIn('students.id', $selectedStudentIds)
                    ->get(['students.id', 'students.name']);

                if ($studentsForAdaptation->count() !== $selectedStudentIds->count()) {
                    return back()
                        ->withInput()
                        ->withErrors(['nee_students' => 'Solo puedes seleccionar estudiantes inscritos en el curso.']);
                }
            }

            $manualAdaptation = trim((string) ($data['nee_adaptation'] ?? ''));
            $baseAdaptation = $manualAdaptation !== ''
                ? $manualAdaptation
                : ($neeType ? $this->buildNeeAdaptation($neeType) : null);

            if ($baseAdaptation) {
                $neeAdaptation = $baseAdaptation;
            }

            if ($studentsForAdaptation->isNotEmpty()) {
                $studentBlock = "Estudiantes con adaptación:\n- " . $studentsForAdaptation->pluck('name')->implode("\n- ");
                $neeAdaptation = trim(($neeAdaptation ? $neeAdaptation . "\n\n" : '') . $studentBlock);
            }
        }

        $activityData = array_merge($data, [
            'teacher_id'     => auth()->id(),
            'colegio_id'     => $course->colegio_id,
            'is_homework'    => $isHomework,
            'type'           => $resolvedType,
            'nee_type'       => $neeType,
            'nee_adaptation' => $neeAdaptation,
        ]);

        $legacyCols = [
            'id_curso'    => $data['course_id'],
            'id_docente'  => auth()->id(),
            'id_profesor' => auth()->id(),
            'id_modulo'   => null,
            'id_periodo'  => null,
            'estado'      => 'publicado',
        ];

        foreach ($legacyCols as $col => $val) {
            if (Schema::hasColumn('activities', $col)) {
                $activityData[$col] = $val;
            }
        }

        Activity::create($activityData);

        app(ProductTelemetry::class)->record([
            'user_id' => auth()->id(),
            'colegio_id' => $course->colegio_id,
            'role' => 'profesor',
            'source' => 'teacher',
            'event' => 'activity_created',
            'action' => 'store',
            'category' => $this->telemetryCategoryFor('activity'),
            'status' => 'success',
            'meta' => [
                'activity_id' => $activity->id,
                'type' => $resolvedType,
                'is_homework' => $isHomework,
            ],
        ]);

        return redirect()->route('teacher.activities.index')->with('success', 'Actividad creada correctamente.');
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

   
   
    /**
     * Elimina una actividad.
     */
    public function destroy(Activity $activity, Request $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        abort_unless($activity->teacher_id === auth()->id(), 403);
        $activity->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Actividad eliminada.']);
        }

        return redirect()->route('teacher.activities.index')
                         ->with('success', 'Actividad eliminada.');
    }

    /**
     * Genera descripción pedagógica mediante IA.
     */
    public function generateDescription(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'type' => ['required', 'string', 'max:50'],
        ]);

        $apiKey = config('services.openai.key', env('OPENAI_API_KEY'));
        if (! $apiKey) {
            return response()->json(['success' => false, 'error' => 'OPENAI_API_KEY no configurada'], 422);
        }

        $system = 'Eres un experto en diseño instruccional. Devuelve SOLO una línea de descripción pedagógica breve (máx 260 caracteres), sin viñetas ni markdown.';
        $user = "Genera descripción para una {$data['type']} titulada: {$data['title']}. "
            . "Incluye metodología activa, objetivo breve y resultado esperado.";

        try {
            $res = Http::withToken($apiKey)
                ->timeout(25)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-5.1 mini',
                    'temperature' => 0.7,
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $user],
                    ],
                ]);

            if (! $res->successful()) {
                Log::warning('generateDescription OpenAI error', ['status' => $res->status(), 'body' => $res->body()]);
                return response()->json(['success' => false, 'error' => 'No se pudo generar descripción.'], 422);
            }

            $description = trim((string) $res->json('choices.0.message.content', ''));
            return response()->json(['success' => true, 'description' => $description]);
        } catch (\Throwable $e) {
            Log::error('generateDescription exception', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Error al conectar con IA.'], 422);
        }
    }

    public function generateNee(Request $request, Activity $activity): JsonResponse
    {
        abort_unless($activity->teacher_id === auth()->id(), 403);

        $data = $request->validate([
            'nee_type' => ['required', 'string', 'max:80'],
        ]);

        $apiKey = config('services.openai.key', env('OPENAI_API_KEY'));
        if (! $apiKey) {
            return response()->json(['success' => false, 'error' => 'OPENAI_API_KEY no configurada'], 422);
        }

        $system = 'Eres un especialista en educación inclusiva. Devuelve SOLO un párrafo de adaptación NEE, claro y aplicable en aula.';
        $user = "Clase: {$activity->title}\nDescripción: {$activity->description}\nNEE: {$data['nee_type']}\n"
            . "Genera una adaptación pedagógica con estrategias concretas y breves.";

        try {
            $res = Http::withToken($apiKey)
                ->timeout(25)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-5.1 mini ',
                    'temperature' => 0.7,
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $user],
                    ],
                ]);

            if (! $res->successful()) {
                Log::warning('generateNee OpenAI error', ['status' => $res->status(), 'body' => $res->body()]);
                return response()->json(['success' => false, 'error' => 'No se pudo generar la adaptación.'], 422);
            }

            $adaptation = trim((string) $res->json('choices.0.message.content', ''));
            return response()->json(['success' => true, 'adaptation' => $adaptation]);
        } catch (\Throwable $e) {
            Log::error('generateNee exception', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Error al conectar con IA.'], 422);
        }
    }

    public function saveNee(Request $request, Activity $activity): JsonResponse
    {
        abort_unless($activity->teacher_id === auth()->id(), 403);

        $data = $request->validate([
            'nee_type' => ['required', 'string', 'max:80'],
            'nee_adaptation' => ['required', 'string', 'max:2000'],
        ]);

        $activity->update([
            'nee_type' => $data['nee_type'],
            'nee_adaptation' => $data['nee_adaptation'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Adaptación NEE guardada correctamente.',
            'nee_type' => $activity->nee_type,
            'nee_adaptation' => $activity->nee_adaptation,
        ]);
    }

    /**
     * Edita una descripción existente usando IA.
     */
    public function editWithAI(Request $request, Activity $activity): JsonResponse
    {
        abort_unless($activity->teacher_id === auth()->id(), 403);

        $data = $request->validate([
            'instruction' => ['required', 'string', 'max:300'],
        ]);

        $apiKey = config('services.openai.key', env('OPENAI_API_KEY'));
        if (! $apiKey) {
            return response()->json(['success' => false, 'error' => 'OPENAI_API_KEY no configurada'], 422);
        }

        $system = 'Eres experto en redacción pedagógica. Devuelve SOLO texto de descripción final.';
        $user = "Título: {$activity->title}\nDescripción actual: {$activity->description}\n"
            . "Modifica según esta instrucción del docente: {$data['instruction']}";

        try {
            $res = Http::withToken($apiKey)
                ->timeout(25)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-5.1 mini ', // Corregido
                    'temperature' => 0.8,
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $user],
                    ],
                ]);

            if (! $res->successful()) {
                return response()->json(['success' => false, 'error' => 'No se pudo editar con IA.'], 422);
            }

            $newDescription = trim((string) $res->json('choices.0.message.content', ''));
            $activity->update(['description' => $newDescription]);

            return response()->json([
                'success' => true,
                'message' => 'Actividad actualizada con IA.',
                'description' => $newDescription,
            ]);
        } catch (\Throwable $e) {
            Log::error('editWithAI exception', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Error al conectar con IA.'], 422);
        }
    }

    /**
     * Guarda una observación pedagógica general de la clase/actividad (distinta del feedback por alumno).
     */
    public function updateNotes(Request $request, Activity $activity): JsonResponse
    {
        abort_unless($activity->teacher_id === auth()->id(), 403);

        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $activity->update(['notes' => $data['notes'] ?? null]);

        return response()->json([
            'success' => true,
            'message' => 'Observación guardada.',
            'notes' => $activity->notes,
        ]);
    }

    /**
     * Actualiza Inicio / Desarrollo / Cierre sin llamar a OpenAI.
     */
    public function updatePhases(Request $request, Activity $activity): JsonResponse
    {
        abort_unless($activity->teacher_id === auth()->id(), 403);

        $data = $request->validate([
            'inicio' => ['nullable', 'string', 'max:10000'],
            'desarrollo' => ['nullable', 'string', 'max:10000'],
            'cierre' => ['nullable', 'string', 'max:10000'],
            'description' => ['nullable', 'string', 'max:30000'],
            'phases' => ['nullable', 'array'],
            'phases.*' => ['nullable', 'string', 'max:10000'],
            'template' => ['nullable', 'string', 'in:clasica,directa,constructivista,proyecto'],
        ]);

        $description = trim((string) ($data['description'] ?? ''));
        if ($description === '' && ! empty($data['phases']) && is_array($data['phases'])) {
            $template = $data['template'] ?? \App\Support\LessonTemplate::detect((string) $activity->description);
            $description = \App\Support\LessonTemplate::build($data['phases'], $template);
        }
        if ($description === '') {
            $parts = [];
            if (trim((string) ($data['inicio'] ?? '')) !== '') {
                $parts[] = "**INICIO**\n" . trim($data['inicio']);
            }
            if (trim((string) ($data['desarrollo'] ?? '')) !== '') {
                $parts[] = "**DESARROLLO**\n" . trim($data['desarrollo']);
            }
            if (trim((string) ($data['cierre'] ?? '')) !== '') {
                $parts[] = "**CIERRE**\n" . trim($data['cierre']);
            }
            $description = implode("\n\n", $parts);
        }

        $activity->update(['description' => $description]);

        return response()->json([
            'success' => true,
            'message' => 'Fases actualizadas.',
            'activity' => [
                'id' => $activity->id,
                'description' => $activity->description,
            ],
        ]);
    }
}
<?php

namespace App\Http\Controllers;

use App\Models\Planificacion;
use App\Services\DirectorAlertService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AIController extends Controller
{
    private function buildContextFromUser(): string
    {
        $user = Auth::user();
        if (! $user || ! $user->relationLoaded('settings')) {
            $user = Auth::user()?->load('settings');
        }
        $settings = $user?->settings;
        if (! $settings) {
            return '';
        }
        $parts = [];
        if (! empty($user?->role)) {
            $parts[] = 'Rol: ' . $user->role;
        }
        if (! empty($settings->nombre_institucion)) {
            $parts[] = 'Institución: ' . $settings->nombre_institucion;
        }
        if (! empty($settings->modelo_pedagogico)) {
            $parts[] = 'Modelo pedagógico institucional: ' . $settings->modelo_pedagogico;
        }
        if (! empty($settings->nivel_educativo)) {
            $parts[] = 'Nivel educativo: ' . $settings->nivel_educativo;
        }
        if (! empty($settings->materias_asignadas) && is_array($settings->materias_asignadas)) {
            $parts[] = 'Materias asignadas: ' . implode(', ', $settings->materias_asignadas);
        }
        if (! empty($settings->materias) && is_array($settings->materias)) {
            $parts[] = 'Materias: ' . implode(', ', $settings->materias);
        }
        if (! empty($settings->dias_clase) && is_array($settings->dias_clase)) {
            $parts[] = 'Días de clase: ' . implode(', ', $settings->dias_clase);
        }
        if (! empty($settings->estilo_pedagogico)) {
            $parts[] = 'Estilo preferido: ' . $settings->estilo_pedagogico;
        }
        if (empty($parts)) {
            return '';
        }
        return 'Contexto del profesor (usa esto para adaptar la planificación): ' . implode('. ', $parts);
    }

    public function generate(Request $request)
    {
        $request->validate([
            'prompt' => 'required|string'
        ]);

        $apiKey = config('services.openai.key');
        if (empty($apiKey)) {
            return response()->json([
                'success' => false,
                'error' => 'OPENAI_API_KEY no está configurada en el archivo .env'
            ], 200);
        }

        $temaInput = $request->input('prompt');
        $context = $this->buildContextFromUser();
        $systemPrompt = 'Eres un experto pedagogo. Responde ÚNICAMENTE con un JSON válido, sin texto extra ni markdown. Estructura exacta: {"tema":"...", "objetivo":"...", "inicio":{"actividades":[]}, "desarrollo":{"actividades":[]}, "cierre":{"actividades":[]}, "recursos":[]}';
        if ($context !== '') {
            $systemPrompt .= "\n\n" . $context;
        }

        $userMessage = 'Planifica una clase sobre: ' . $temaInput;

        try {
            $response = Http::withToken($apiKey)
                ->timeout(60)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-5.1 mini',
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userMessage],
                    ],
                ]);

            if (!$response->successful()) {
                $body = $response->json();
                $message = $body['error']['message'] ?? $response->body();
                Log::error('OpenAI API error', ['status' => $response->status(), 'body' => $body]);
                return response()->json([
                    'success' => false,
                    'error' => 'API: ' . (is_string($message) ? $message : json_encode($message))
                ], 200);
            }

            $responseContent = $response->json('choices.0.message.content', '');

            if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $responseContent, $m)) {
                $responseContent = trim($m[1]);
            }
            $responseContent = trim($responseContent);

            $data = json_decode($responseContent, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('OpenAI invalid JSON', ['raw' => substr($responseContent, 0, 500)]);
                return response()->json([
                    'success' => false,
                    'error' => 'La IA no devolvió un formato válido. Intenta de nuevo.'
                ], 200);
            }

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Throwable $e) {
            Log::error('OpenAI error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 200);
        }
    }

    public function improveSection(Request $request)
    {
        $request->validate([
            'phase' => 'required|string|in:inicio,desarrollo,cierre',
            'content' => 'required|string',
            'instruction' => 'required|string|max:500',
        ]);

        $apiKey = config('services.openai.key');
        if (empty($apiKey)) {
            return response()->json([
                'success' => false,
                'error' => 'OPENAI_API_KEY no configurada'
            ], 200);
        }

        $phase = $request->input('phase');
        $content = $request->input('content');
        $instruction = $request->input('instruction');

        $phaseLabel = ['inicio' => 'Inicio', 'desarrollo' => 'Desarrollo', 'cierre' => 'Cierre'][$phase];
        $systemPrompt = 'Eres un experto pedagogo. Te dan el contenido actual de la sección "' . $phaseLabel . '" de una planificación de clase (lista de actividades). El usuario pide una mejora con una instrucción concreta. Debes devolver ÚNICAMENTE un JSON con la misma estructura: {"actividades": ["actividad 1", "actividad 2", ...]}. Sin texto extra, sin markdown, sin explicaciones. Solo el JSON.';
        $userMessage = "Contenido actual de la sección " . $phaseLabel . ":\n" . $content . "\n\nInstrucción del profesor: " . $instruction . "\n\nDevuelve el nuevo contenido mejorado en JSON con clave \"actividades\" (array de strings).";

        try {
            $response = Http::withToken($apiKey)
                ->timeout(45)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-5.1 mini',
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userMessage],
                    ],
                ]);

            if (! $response->successful()) {
                $body = $response->json();
                $message = $body['error']['message'] ?? $response->body();
                Log::error('OpenAI improveSection API error', ['status' => $response->status()]);
                return response()->json([
                    'success' => false,
                    'error' => is_string($message) ? $message : 'Error en la API'
                ], 200);
            }

            $responseContent = $response->json('choices.0.message.content', '');
            if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $responseContent, $m)) {
                $responseContent = trim($m[1]);
            }
            $responseContent = trim($responseContent);

            $data = json_decode($responseContent, true);
            if (json_last_error() !== JSON_ERROR_NONE || empty($data['actividades'])) {
                Log::warning('OpenAI improveSection invalid JSON', ['raw' => substr($responseContent, 0, 300)]);
                return response()->json([
                    'success' => false,
                    'error' => 'La IA no devolvió un formato válido. Intenta de nuevo.'
                ], 200);
            }

            return response()->json([
                'success' => true,
                'phase' => $phase,
                'actividades' => $data['actividades'],
            ]);
        } catch (\Throwable $e) {
            Log::error('OpenAI improveSection: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 200);
        }
    }

    private function callOpenAIJson(string $systemPrompt, string $userMessage, int $timeout = 60): array
    {
        $apiKey = config('services.openai.key');
        if (empty($apiKey)) {
            return ['success' => false, 'error' => 'OPENAI_API_KEY no configurada'];
        }
        try {
            $response = Http::withToken($apiKey)
                ->timeout($timeout)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-5.1 mini',
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userMessage],
                    ],
                ]);
            if (! $response->successful()) {
                $body = $response->json();
                $msg = $body['error']['message'] ?? $response->body();
                Log::error('OpenAI API error', ['status' => $response->status()]);
                return ['success' => false, 'error' => is_string($msg) ? $msg : 'Error en la API'];
            }
            $content = $response->json('choices.0.message.content', '');
            if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $content, $m)) {
                $content = trim($m[1]);
            }
            $content = trim($content);
            $data = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('OpenAI invalid JSON', ['raw' => substr($content, 0, 400)]);
                return ['success' => false, 'error' => 'La IA no devolvió un formato válido.'];
            }
            return ['success' => true, 'data' => $data];
        } catch (\Throwable $e) {
            Log::error('OpenAI: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function planProNEE(Request $request)
    {
        $request->validate([
            'plan' => 'required|array',
            'plan.tema' => 'sometimes|string',
            'plan.objetivo' => 'sometimes|string',
            'plan.inicio' => 'sometimes|array',
            'plan.desarrollo' => 'sometimes|array',
            'plan.cierre' => 'sometimes|array',
            'plan.recursos' => 'sometimes|array',
            'condition' => 'required|string|max:100',
        ]);
        $plan = $request->input('plan');
        $condition = $request->input('condition');

        $planJson = json_encode($plan, JSON_UNESCAPED_UNICODE);
        $systemPrompt = 'Eres un especialista en diseño curricular y educación inclusiva. Te proporciono una planificación de clase en JSON. Debes reescribir las actividades de Inicio, Desarrollo y Cierre para un alumno con ' . $condition . '. Enfócate en aprendizaje kinestésico, apoyos visuales y reducción de carga cognitiva sin perder el objetivo pedagógico. Responde ÚNICAMENTE con un JSON válido, sin texto extra ni markdown: {"inicio": {"actividades": ["...", "..."]}, "desarrollo": {"actividades": ["...", "..."]}, "cierre": {"actividades": ["...", "..."]}}.';
        $userMessage = "Planificación de referencia:\n" . $planJson . "\n\nGenera la adecuación curricular para " . $condition . " en el mismo formato JSON (inicio, desarrollo, cierre con actividades).";

        $result = $this->callOpenAIJson($systemPrompt, $userMessage, 60);
        if (! $result['success']) {
            return response()->json($result, 200);
        }
        return response()->json(['success' => true, 'data' => $result['data']], 200);
    }

    public function planProCalendario(Request $request)
    {
        $request->validate([
            'plan' => 'required|array',
            'plan.tema' => 'sometimes|string',
            'plan.inicio' => 'sometimes|array',
            'plan.desarrollo' => 'sometimes|array',
            'plan.cierre' => 'sometimes|array',
        ]);
        $plan = $request->input('plan');
        $planJson = json_encode($plan, JSON_UNESCAPED_UNICODE);

        $systemPrompt = 'Eres un especialista en logística docente. Dada una planificación de clase en JSON, define la duración ideal de cada fase (en minutos) y genera recordatorios breves para la agenda del docente (ej: "Preparar voltímetro antes de las 8 AM"). Responde ÚNICAMENTE con un JSON válido, sin texto extra ni markdown: {"duracion_inicio": número, "duracion_desarrollo": número, "duracion_cierre": número, "recordatorios": ["...", "..."], "alerta_agenda": "texto breve para mañana o próxima clase"}';
        $userMessage = "Planificación:\n" . $planJson . "\n\nGenera duraciones y recordatorios en el formato JSON indicado.";

        $result = $this->callOpenAIJson($systemPrompt, $userMessage, 45);
        if (! $result['success']) {
            return response()->json($result, 200);
        }
        return response()->json(['success' => true, 'data' => $result['data']], 200);
    }

    public function planProMateriales(Request $request)
    {
        $request->validate([
            'plan' => 'required|array',
            'plan.tema' => 'sometimes|string',
            'plan.recursos' => 'sometimes|array',
        ]);
        $plan = $request->input('plan');
        $planJson = json_encode($plan, JSON_UNESCAPED_UNICODE);

        $systemPrompt = 'Eres un asistente de materiales para el docente. Dada una planificación de clase en JSON, crea una lista de compras o recolección detallada. Responde ÚNICAMENTE con un JSON válido, sin texto extra ni markdown: {"basicos": ["papelería y materiales básicos"], "especificos": ["materiales específicos de la clase, ej. cables, bombillos"], "digitales": ["links, nombres de juegos o recursos digitales"]}.';
        $userMessage = "Planificación:\n" . $planJson . "\n\nGenera la lista de materiales en el formato JSON (basicos, especificos, digitales).";

        $result = $this->callOpenAIJson($systemPrompt, $userMessage, 45);
        if (! $result['success']) {
            return response()->json($result, 200);
        }
        return response()->json(['success' => true, 'data' => $result['data']], 200);
    }

    public function save(Request $request)
    {
        if (! Auth::check()) {
            return response()->json([
                'success' => false,
                'error' => 'No autenticado.',
            ], 401);
        }

        $user = Auth::user();
        if ($user->role !== 'profesor') {
            return response()->json([
                'success' => false,
                'error' => 'Solo los docentes pueden guardar planificaciones pedagógicas.',
            ], 403);
        }

        if (! $user->colegio_id) {
            return response()->json([
                'success' => false,
                'error' => 'Tu usuario docente no está vinculado a un colegio.',
            ], 422);
        }

        $validated = $request->validate([
            'plan' => 'required|array',
            'plan.tema' => 'nullable|string|max:255',
            'plan.objetivo' => 'nullable|string',
            'slug' => 'nullable|string|max:255',
        ]);

        // Preserve the full object sent by frontend (including nested sections/metadata)
        $plan = $request->input('plan', []);
        $tema = $plan['tema'] ?? 'Planificación sin título';
        $objetivo = $plan['objetivo'] ?? '';

        $baseSlug = $validated['slug'] ?? Str::slug($tema ?: 'planificacion');
        if ($baseSlug === '') {
            $baseSlug = 'planificacion';
        }
        $slug = $baseSlug;
        $i = 1;
        while (Planificacion::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $i;
            $i++;
        }

        $planificacion = Planificacion::create([
            'user_id' => $user->id,
            'tema' => $tema,
            'objetivo' => $objetivo,
            'slug' => $slug,
            'colegio_id' => $user->colegio_id,
            'payload' => $plan,
            'status' => 'pendiente',
        ]);

        app(DirectorAlertService::class)->notifyDirectors(
            (int) $user->colegio_id,
            'Nueva planificación IA pendiente',
            "El/La docente {$user->name} guardó una planificación IA: «{$tema}».",
            route('director.planificaciones', ['status' => 'pendiente']),
            '🤖 Aulasync · Planificación IA'
        );

        Log::debug('PLAN_CREATED', [
            'teacher_id' => Auth::id(),
            'colegio_id' => $user->colegio_id,
            'course_id' => data_get($plan, 'course_id'),
            'start_date' => data_get($plan, 'start_date'),
            'end_date' => data_get($plan, 'end_date'),
            'planificacion_id' => $planificacion->id,
            'source' => 'ai_controller.save',
        ]);

        $payload = [
            'success' => true,
            'message' => 'Planificación guardada en tu historial.',
            'id' => $planificacion->id,
            'slug' => $planificacion->slug,
        ];

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json($payload, 200);
        }

        return back()->with('success', $payload['message']);
    }

    public function historial()
    {
        $user = auth()->user();
        $plans = $user->planificaciones()
            ->where('colegio_id', $user->colegio_id)
            ->withCount('activities')
            ->latest()
            ->get();

        Log::debug('MY_PLANS_QUERY', [
            'teacher_id' => $user?->id,
            'source' => 'AIController@historial',
            'filters' => [
                'user_id' => $user?->id,
            ],
            'count' => $plans->count(),
        ]);

        return view('historial', compact('plans'));
    }

    public function destroy(int $id)
    {
        if (! Auth::check()) {
            return response()->json([
                'success' => false,
                'error' => 'No autenticado.',
            ], 401);
        }

        $plan = Planificacion::query()
            ->where('id', $id)
            ->where('user_id', Auth::id())
            ->where('colegio_id', Auth::user()->colegio_id)
            ->first();

        if (! $plan) {
            return response()->json([
                'success' => false,
                'error' => 'Planificación no encontrada.',
            ], 404);
        }

        $plan->delete();

        return response()->json([
            'success' => true,
            'message' => 'Planificación eliminada.',
        ], 200);
    }
}
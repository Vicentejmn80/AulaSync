<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Tarea;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class TareaController extends Controller
{
    public function generate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'activity_id' => ['required', 'integer', 'exists:activities,id'],
        ]);

        $activity = Activity::where('id', $data['activity_id'])
            ->where('teacher_id', auth()->id())
            ->firstOrFail();

        $apiKey = config('services.openai.key', env('OPENAI_API_KEY'));
        if (! $apiKey) {
            return response()->json([
                'success' => false,
                'error' => 'OPENAI_API_KEY no configurada.',
            ], 422);
        }

        $classContext = "Clase: {$activity->title}\nContenido: " . ($activity->description ?: 'Sin descripción');
        $system = 'Eres un experto en diseño de tareas escolares. Responde SOLO en JSON con: {"titulo":"...","descripcion":"..."}';
        $user = "Basado en [clase], genera 1 idea de tarea creativa y corta.\n{$classContext}";

        $response = Http::withToken($apiKey)
            ->timeout(25)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-5.1 mini ',
                'temperature' => 0.7,
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
            ]);

        if (! $response->successful()) {
            return response()->json([
                'success' => false,
                'error' => 'No se pudo generar la sugerencia de tarea.',
            ], 422);
        }

        $raw = trim((string) $response->json('choices.0.message.content', ''));
        $decoded = json_decode($raw, true);

        $titulo = trim((string) ($decoded['titulo'] ?? 'Tarea creativa sugerida'));
        $descripcion = trim((string) ($decoded['descripcion'] ?? $raw));

        return response()->json([
            'success' => true,
            'idea' => [
                'titulo' => $titulo,
                'descripcion' => $descripcion,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'activity_id' => ['required', 'integer', 'exists:activities,id'],
            'titulo' => ['required', 'string', 'max:180'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'fecha_entrega' => ['required', 'date'],
            'puntos' => ['required', 'integer', 'min:1', 'max:1000'],
            'mirror_activity' => ['sometimes', 'boolean'],
        ]);

        $activity = Activity::where('id', $data['activity_id'])
            ->where('teacher_id', auth()->id())
            ->firstOrFail();

        $tarea = Tarea::create([
            'actividad_id' => $activity->id,
            'titulo' => $data['titulo'],
            'descripcion' => $data['descripcion'] ?? null,
            'fecha_entrega' => $data['fecha_entrega'],
            'puntos' => $data['puntos'],
        ]);

        $mirroredActivity = null;
        if ($request->boolean('mirror_activity')) {
            $typeMeta = Activity::normalizeType('tarea', true);
            $mirroredActivity = Activity::create([
                'teacher_id'        => auth()->id(),
                'course_id'         => $activity->course_id,
                'title'             => $data['titulo'],
                'description'       => $data['descripcion'] ?? 'Tarea asignada desde el calendario.',
                'type'              => $typeMeta['type'],
                'is_homework'       => $typeMeta['is_homework'],
                'due_date'          => $data['fecha_entrega'],
                'max_score'         => $data['puntos'],
                'weight_percentage' => 0,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Tarea guardada correctamente.',
            'tarea' => [
                'id' => $tarea->id,
                'titulo' => $tarea->titulo,
                'descripcion' => $tarea->descripcion,
                'fecha_entrega' => optional($tarea->fecha_entrega)->format('Y-m-d'),
                'puntos' => $tarea->puntos,
                'calificacion' => $tarea->calificacion,
                'feedback' => $tarea->feedback,
            ],
            'mirrored_activity' => $mirroredActivity ? [
                'id' => $mirroredActivity->id,
                'title' => $mirroredActivity->title,
                'due_date' => optional($mirroredActivity->due_date)->format('Y-m-d'),
                'type' => $mirroredActivity->type,
                'is_homework' => (bool) $mirroredActivity->is_homework,
            ] : null,
        ]);
    }

    public function updateGrade(Request $request, Tarea $tarea): JsonResponse
    {
        $activity = Activity::where('id', $tarea->actividad_id)
            ->where('teacher_id', auth()->id())
            ->firstOrFail();

        $data = $request->validate([
            'calificacion' => ['required', 'numeric', 'min:0', 'max:999.99'],
            'feedback' => ['nullable', 'string', 'max:2000'],
        ]);

        $tarea->update([
            'calificacion' => $data['calificacion'],
            'feedback' => $data['feedback'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Calificación guardada correctamente.',
            'tarea' => [
                'id' => $tarea->id,
                'actividad_id' => $activity->id,
                'calificacion' => $tarea->calificacion,
                'feedback' => $tarea->feedback,
            ],
        ]);
    }
}

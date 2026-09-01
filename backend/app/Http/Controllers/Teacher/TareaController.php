<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Tarea;
use App\Services\LessonAiService;
use App\Services\ProductTelemetry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TareaController extends Controller
{
    public function __construct(private LessonAiService $lessonAi)
    {
    }

    public function generate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'activity_id' => ['required', 'integer', 'exists:activities,id'],
        ]);

        $activity = Activity::where('id', $data['activity_id'])
            ->where('teacher_id', auth()->id())
            ->where('colegio_id', auth()->user()->colegio_id)
            ->firstOrFail();

        $ideas = $this->lessonAi->generateTaskProposals($activity);

        return response()->json([
            'success' => true,
            'ideas' => $ideas,
            'idea' => $ideas[0] ?? null,
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
            'enfoque' => ['nullable', 'string', 'max:80'],
            'mirror_activity' => ['sometimes', 'boolean'],
        ]);

        $activity = Activity::where('id', $data['activity_id'])
            ->where('teacher_id', auth()->id())
            ->where('colegio_id', auth()->user()->colegio_id)
            ->firstOrFail();

        $saved = $this->lessonAi->assignOfficialTask(
            $activity,
            [
                'titulo' => $data['titulo'],
                'descripcion' => $data['descripcion'] ?? '',
                'enfoque' => $data['enfoque'] ?? '',
            ],
            $data['fecha_entrega'],
            (int) $data['puntos'],
            $request->boolean('mirror_activity', true),
        );

        app(ProductTelemetry::class)->record([
            'user_id' => auth()->id(),
            'colegio_id' => auth()->user()->colegio_id,
            'role' => 'profesor',
            'source' => 'teacher',
            'event' => 'task_created',
            'action' => 'store',
            'category' => 'planning',
            'status' => 'success',
            'meta' => [
                'tarea_id' => $saved['tarea']['id'] ?? null,
                'titulo' => $data['titulo'],
                'actividad_id' => $activity->id,
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tarea guardada como la asignada a esta clase.',
            'tarea' => $saved['tarea'],
            'mirrored_activity' => $saved['mirrored_activity'],
        ]);
    }

    public function updateGrade(Request $request, Tarea $tarea): JsonResponse
    {
        $activity = Activity::where('id', $tarea->actividad_id)
            ->where('teacher_id', auth()->id())
            ->where('colegio_id', auth()->user()->colegio_id)
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

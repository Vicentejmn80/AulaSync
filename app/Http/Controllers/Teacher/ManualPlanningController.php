<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\ManualPlanning;
use App\Models\Planificacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ManualPlanningController extends Controller
{
    /**
     * Muestra el formulario de planificación.
     */
    public function show($id = null) 
    {
        $planning = null;

        if ($id) {
            $planning = ManualPlanning::where('teacher_id', auth()->id())->find($id);
            
            if (!$planning) {
                $historial = Planificacion::where('user_id', auth()->id())->find($id);
                
                if ($historial && isset($historial->payload['sessions'])) {
                    $planning = (object) [
                        'id' => $historial->id,
                        'sessions' => $historial->payload['sessions']
                    ];
                }
            }
        }

        return view('teacher.planner.manual', compact('planning'));
    }

    /**
     * Guarda la planificación y redirige al Historial Morado.
     */
    public function store(Request $request): JsonResponse
    {
        $teacherId = auth()->id();
        
        $data = $request->validate([
            'sessions' => 'required|array|min:1',
            'sessions.*.date'       => 'required|date',
            'sessions.*.inicio'     => 'nullable|string|max:1000',
            'sessions.*.desarrollo' => 'nullable|string|max:2000',
            'sessions.*.cierre'     => 'nullable|string|max:1000',
        ]);

        return DB::transaction(function () use ($teacherId, $data) {
            try {
                $manual = ManualPlanning::create([
                    'teacher_id' => $teacherId,
                    'sessions'   => $data['sessions'],
                ]);

                $planificacion = Planificacion::create([
                    'user_id' => $teacherId,
                    'tema'    => 'Planificación manual · ' . now()->format('d/m/Y'),
                    'objetivo'=> 'Sesiones institucionales generadas manualmente.',
                    'slug'    => 'manual-' . bin2hex(random_bytes(5)),
                    'payload' => [
                        'type'      => 'manual_plan',
                        'sessions'  => $data['sessions'],
                        'manual_id' => $manual->id
                    ],
                ]);

                Log::debug('PLAN_CREATED', [
                    'teacher_id' => $teacherId,
                    'course_id' => null,
                    'start_date' => collect($data['sessions'])->min('date'),
                    'end_date' => collect($data['sessions'])->max('date'),
                    'planificacion_id' => $planificacion->id,
                    'source' => 'manual_planning.store',
                ]);

                return response()->json([
                    'success' => true,
                    // CAMBIO AQUÍ: Ahora apunta a la ruta 'historial' (/historial)
                    'redirect' => route('historial') 
                ]);

            } catch (\Exception $e) {
                return response()->json([
                    'success' => false, 
                    'error'   => 'Error al guardar: ' . $e->getMessage()
                ], 500);
            }
        });
    }

    public function pdf($id = null)
    {
        return response()->json(['message' => 'Función PDF en desarrollo para el ID: ' . $id]);
    }
}
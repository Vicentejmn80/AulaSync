<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Course;
use App\Models\ManualPlanning;
use App\Models\Planificacion;
use App\Services\DirectorAlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ManualPlanningController extends Controller
{
    /**
     * Muestra el formulario de planificación.
     */
    public function show($id = null) 
    {
        $planning = null;
        $selectedCourseId = null;
        $teacherId = auth()->id();
        $colegioId = auth()->user()->colegio_id;

        $courses = Course::where('teacher_id', $teacherId)
            ->where('colegio_id', $colegioId)
            ->orderBy('subject_name')
            ->get(['id', 'subject_name', 'grade', 'section']);

        if ($id) {
            $planning = ManualPlanning::where('teacher_id', $teacherId)->find($id);
            
            if (!$planning) {
                $historial = Planificacion::where('user_id', $teacherId)
                    ->where('colegio_id', $colegioId)
                    ->find($id);
                
                if ($historial && isset($historial->payload['sessions'])) {
                    $planning = (object) [
                        'id' => $historial->id,
                        'planificacion_id' => $historial->id,
                        'sessions' => $historial->payload['sessions'],
                        'course_id' => $historial->payload['course_id'] ?? null,
                    ];
                }
            } elseif ($planning) {
                $linkedPlan = Planificacion::where('user_id', $teacherId)
                    ->where('colegio_id', $colegioId)
                    ->latest('id')
                    ->limit(80)
                    ->get(['id', 'payload'])
                    ->first(function (Planificacion $plan) use ($planning) {
                        $payload = $plan->payload;
                        return is_array($payload)
                            && ($payload['type'] ?? null) === 'manual_plan'
                            && (int) ($payload['manual_id'] ?? 0) === (int) $planning->id;
                    });

                if ($linkedPlan) {
                    $planning->planificacion_id = $linkedPlan->id;
                }
            }
        }

        $selectedCourseId = $planning->course_id ?? null;
        if (! $selectedCourseId && $courses->isNotEmpty()) {
            $selectedCourseId = $courses->first()->id;
        }

        return view('teacher.planner.manual', compact('planning', 'courses', 'selectedCourseId'));
    }

    /**
     * Guarda la planificación y redirige al Historial Morado.
     */
    public function store(Request $request): JsonResponse
    {
        $user = auth()->user();
        $teacherId = $user->id;
        $teacherName = (string) ($user->name ?? 'Docente');
        $colegioId = $user->colegio_id;
        $data = $request->validate([
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'planificacion_id' => ['nullable', 'integer'],
            'sessions' => ['required', 'array', 'min:1'],
            'sessions.*.date' => ['nullable', 'date'],
            'sessions.*.inicio' => ['nullable', 'string'],
            'sessions.*.desarrollo' => ['nullable', 'string'],
            'sessions.*.cierre' => ['nullable', 'string'],
        ]);

        try {
            $requestedCourseId = (int) $data['course_id'];
            $course = Course::where('teacher_id', $teacherId)
                ->where('colegio_id', $colegioId)
                ->where('id', $requestedCourseId)
                ->orderBy('subject_name')
                ->first(['id', 'subject_name', 'grade', 'section']);

            if (! $course) {
                return response()->json([
                    'success' => false,
                    'error' => 'El curso seleccionado no pertenece al docente autenticado.',
                ], 403);
            }

            $existingPlan = null;
            if (!empty($data['planificacion_id'])) {
                $existingPlan = Planificacion::where('id', (int) $data['planificacion_id'])
                    ->where('user_id', $teacherId)
                    ->where('colegio_id', $colegioId)
                    ->first();
            }

            $signaturePayload = [
                'course_id' => $course->id,
                'sessions' => collect($data['sessions'])
                    ->map(fn ($s) => [
                        'date' => $s['date'] ?? null,
                        'inicio' => trim((string) ($s['inicio'] ?? '')),
                        'desarrollo' => trim((string) ($s['desarrollo'] ?? '')),
                        'cierre' => trim((string) ($s['cierre'] ?? '')),
                    ])
                    ->values()
                    ->all(),
            ];
            $signature = sha1(json_encode($signaturePayload, JSON_UNESCAPED_UNICODE));

            if (! $existingPlan) {
                $duplicatePlan = Planificacion::where('user_id', $teacherId)
                    ->where('colegio_id', $colegioId)
                    ->latest('id')
                    ->limit(30)
                    ->get(['id', 'payload', 'created_at'])
                    ->first(function (Planificacion $plan) use ($signature) {
                        $payload = $plan->payload;
                        return is_array($payload)
                            && ($payload['type'] ?? null) === 'manual_plan'
                            && ($payload['signature'] ?? null) === $signature;
                    });

                if ($duplicatePlan) {
                    $firstDuplicateDate = collect($duplicatePlan->payload['sessions'] ?? [])
                        ->pluck('date')
                        ->filter()
                        ->sort()
                        ->first();
                    $duplicateMonth = $firstDuplicateDate
                        ? Carbon::parse($firstDuplicateDate)->format('Y-m')
                        : now()->format('Y-m');

                    return response()->json([
                        'success' => true,
                        'redirect' => route('teacher.hub', [
                            'plan_block' => $duplicatePlan->id,
                            'month' => $duplicateMonth,
                        ]),
                        'deduplicated' => true,
                    ]);
                }
            }

            $courseName = trim($course->subject_name . ' ' . $course->grade . ($course->section ? ' / ' . $course->section : ''));
            $originalStatus = $existingPlan ? (string) ($existingPlan->status ?? '') : null;

            $planificacion = DB::transaction(function () use ($teacherId, $colegioId, $data, $course, $courseName, $signature, $existingPlan, &$originalStatus) {
                $manualPayload = [
                    'teacher_id' => $teacherId,
                    'course_id'  => $course->id,
                    'sessions'   => $data['sessions'],
                ];

                if (Schema::hasColumn('manual_plannings', 'colegio_id')) {
                    $manualPayload['colegio_id'] = $colegioId;
                }

                $manual = ManualPlanning::create($manualPayload);

                $payload = [
                    'type'      => 'manual_plan',
                    'course_id' => $course->id,
                    'course_name' => $courseName,
                    'signature' => $signature,
                    'sessions'  => $data['sessions'],
                    'manual_id' => $manual->id,
                ];

                if ($existingPlan) {
                    $originalStatus = (string) ($existingPlan->status ?? '');
                    $newStatus = $originalStatus === 'rechazado'
                        ? 'pendiente_revision'
                        : ($originalStatus !== '' ? $originalStatus : 'pendiente');

                    $existingPlan->update([
                        'tema'    => 'Planificación manual · ' . now()->format('d/m/Y'),
                        'objetivo'=> 'Sesiones institucionales generadas manualmente.',
                        'status'  => $newStatus,
                        'payload' => $payload,
                    ]);

                    return $existingPlan->fresh();
                }

                return Planificacion::create([
                    'user_id' => $teacherId,
                    'tema'    => 'Planificación manual · ' . now()->format('d/m/Y'),
                    'objetivo'=> 'Sesiones institucionales generadas manualmente.',
                    'slug'    => 'manual-' . bin2hex(random_bytes(5)),
                    'colegio_id' => $colegioId,
                    'payload' => $payload,
                    'status' => 'pendiente',
                ]);
            });

            if (! $existingPlan) {
                $this->notifyDirectorsOfNewPlan($colegioId, $teacherName, $courseName);
            }

            if ($originalStatus === 'rechazado') {
                $this->notifyDirectorsOfCorrectedPlan($colegioId, $teacherName, $courseName);
            }

            DB::transaction(function () use ($teacherId, $colegioId, $data, $course, $courseName, $planificacion) {
                Activity::where('plan_block_id', $planificacion->id)
                    ->where('colegio_id', $colegioId)
                    ->delete();

                $legacyCols = [
                    'id_curso'    => $course->id,
                    'id_docente'  => $teacherId,
                    'id_profesor' => $teacherId,
                    'id_modulo'   => null,
                    'id_periodo'  => null,
                    'estado'      => 'publicado',
                ];

                foreach ($data['sessions'] as $idx => $session) {
                    $inicio = trim((string) ($session['inicio'] ?? ''));
                    $desarrollo = trim((string) ($session['desarrollo'] ?? ''));
                    $cierre = trim((string) ($session['cierre'] ?? ''));
                    $sessionDate = filled($session['date'] ?? null)
                        ? Carbon::parse((string) $session['date'])->format('Y-m-d')
                        : null;

                    $description = collect([
                        $inicio ? "INICIO:\n{$inicio}" : null,
                        $desarrollo ? "DESARROLLO:\n{$desarrollo}" : null,
                        $cierre ? "CIERRE:\n{$cierre}" : null,
                    ])->filter()->implode("\n\n");

                    $activity = new Activity([
                        'teacher_id'       => $teacherId,
                        'course_id'        => $course->id,
                        'plan_block_id'    => $planificacion->id,
                        'title'            => $courseName . ' · Sesión manual #' . ($idx + 1),
                        'description'      => $description,
                        'type'             => Activity::TYPE_CLASE,
                        'is_homework'      => false,
                        'max_score'        => 0,
                        'weight_percentage'=> 0.0,
                        'due_date'         => $sessionDate,
                        'colegio_id'       => $colegioId,
                    ]);

                    foreach ($legacyCols as $col => $val) {
                        if (Schema::hasColumn('activities', $col)) {
                            $activity->setAttribute($col, $val);
                        }
                    }

                    $activity->save();
                }
            });

            $firstDate = collect($data['sessions'])->pluck('date')->filter()->sort()->first();
            $month = $firstDate ? Carbon::parse($firstDate)->format('Y-m') : now()->format('Y-m');

            Log::debug('PLAN_CREATED', [
                'teacher_id' => $teacherId,
                'colegio_id' => $colegioId,
                'course_id' => $course->id,
                'start_date' => collect($data['sessions'])->min('date'),
                'end_date' => collect($data['sessions'])->max('date'),
                'planificacion_id' => $planificacion->id,
                'source' => 'manual_planning.store',
                'status' => $planificacion->status,
            ]);

            return response()->json([
                'success' => true,
                'redirect' => route('teacher.hub', [
                    'plan_block' => $planificacion->id,
                    'month' => $month,
                ]),
            ]);

        } catch (\Exception $e) {
            Log::error('MANUAL_PLAN_STORE_FAILED', [
                'teacher_id' => $teacherId,
                'colegio_id' => $colegioId,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'Error al guardar: ' . $e->getMessage()
            ], 500);
        }
    }

    public function pdf($id = null)
    {
        return response()->json(['message' => 'Función PDF en desarrollo para el ID: ' . $id]);
    }

    private function notifyDirectorsOfNewPlan(int $colegioId, string $teacherName, string $courseName): void
    {
        app(DirectorAlertService::class)->notifyDirectors(
            $colegioId,
            'Nueva planificación pendiente',
            "El/La docente {$teacherName} envió una planificación de {$courseName} para revisión.",
            route('director.planificaciones', ['status' => 'pendiente']),
            '📋 Aulasync · Nueva planificación'
        );
    }

    private function notifyDirectorsOfCorrectedPlan(int $colegioId, string $teacherName, string $courseName): void
    {
        app(DirectorAlertService::class)->notifyDirectors(
            $colegioId,
            'Planificación corregida por docente',
            "El/La docente {$teacherName} ha corregido la planificación de {$courseName}.",
            route('director.planificaciones', ['status' => 'pendiente_revision']),
            '🔄 Aulasync · Planificación corregida'
        );
    }
}
<?php

namespace App\Http\Controllers\Director;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Notification;
use App\Models\Planificacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ActivityFeedbackController extends Controller
{
    private function findPlanForDirector(int $planId): Planificacion
    {
        $colegioId = auth()->user()->colegio_id;

        return Planificacion::where('id', $planId)
            ->where('colegio_id', $colegioId)
            ->whereHas('user', fn ($query) => $query
                ->where('role', 'profesor')
                ->where('colegio_id', $colegioId))
            ->firstOrFail();
    }

    public function planActivities(int $id): JsonResponse
    {
        try {
            $plan = $this->findPlanForDirector($id);
            $colegioId = auth()->user()->colegio_id;

            $activities = Activity::where('plan_block_id', $plan->id)
                ->where('colegio_id', $colegioId)
                ->orderBy('due_date')
                ->orderBy('id')
                ->get(['id', 'due_date', 'title', 'description', 'director_notes', 'type', 'created_at', 'updated_at'])
                ->values()
                ->map(function (Activity $activity, int $idx) {
                    $sections = $this->parsePedagogicalSections($activity->description);
                    $hasDirectorNote = trim((string) $activity->director_notes) !== '';
                    $isDirectorEdited = $hasDirectorNote
                        || ($activity->updated_at && $activity->created_at
                            && $activity->updated_at->gt($activity->created_at->copy()->addSeconds(2)));

                    return [
                        'id' => $activity->id,
                        'index' => $idx + 1,
                        'title' => $activity->title,
                        'date' => $activity->due_date?->format('d/m/Y') ?? '',
                        'due_date' => $activity->due_date?->format('Y-m-d') ?? '',
                        'inicio' => $sections['inicio'],
                        'desarrollo' => $sections['desarrollo'],
                        'cierre' => $sections['cierre'],
                        'director_notes' => $activity->director_notes,
                        'has_director_note' => $hasDirectorNote,
                        'is_director_edited' => $isDirectorEdited,
                        'version_label' => $hasDirectorNote
                            ? '✓ Versión 2 (Editada por Dirección)'
                            : 'Versión original del Docente',
                    ];
                });

            return response()->json([
                'success' => true,
                'activities' => $activities,
            ]);
        } catch (\Throwable $e) {
            Log::error('director.planificaciones.activities.error', [
                'planificacion_id' => $id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'No se pudieron cargar las actividades de la planificación.',
            ], 500);
        }
    }

    public function storeFeedback(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'director_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $colegioId = auth()->user()->colegio_id;
        $activity = Activity::where('id', $id)
            ->where('colegio_id', $colegioId)
            ->firstOrFail();

        if ($activity->plan_block_id) {
            $this->findPlanForDirector((int) $activity->plan_block_id);
        }

        $activity->update([
            'director_notes' => $validated['director_notes'] ?? '',
        ]);

        if ($activity->teacher_id) {
            Notification::create([
                'user_id' => $activity->teacher_id,
                'colegio_id' => $colegioId,
                'title' => 'Observación del director',
                'message' => 'Dirección dejó una nota en «' . ($activity->title ?? 'una clase') . '».',
                'link' => route('teacher.hub', array_filter([
                    'open_activity' => $activity->id,
                    'plan_block' => $activity->plan_block_id,
                ])),
            ]);
        }

        return response()->json([
            'success' => true,
            'director_notes' => $activity->director_notes,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $colegioId = auth()->user()->colegio_id;
        $activity = Activity::where('id', $id)
            ->where('colegio_id', $colegioId)
            ->firstOrFail();

        if ($activity->plan_block_id) {
            $this->findPlanForDirector((int) $activity->plan_block_id);
        }

        $activity->update([
            'title' => $validated['title'] ?? $activity->title,
            'description' => $validated['description'] ?? $activity->description,
        ]);

        if ($activity->teacher_id) {
            Notification::create([
                'user_id' => $activity->teacher_id,
                'colegio_id' => $colegioId,
                'title' => 'Actividad editada por Dirección',
                'message' => 'Dirección actualizó la actividad «' . ($activity->title ?? '') . '».',
                'link' => route('teacher.hub', array_filter([
                    'open_activity' => $activity->id,
                    'plan_block' => $activity->plan_block_id,
                ])),
            ]);
        }

        return response()->json([
            'success' => true,
            'activity' => [
                'id' => $activity->id,
                'title' => $activity->title,
                'description' => $activity->description,
            ],
        ]);
    }

    public function updatePlanificacionSession(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'session_index' => ['required', 'integer', 'min:0'],
            'title' => ['nullable', 'string', 'max:255'],
            'date' => ['nullable', 'date'],
            'inicio' => ['nullable', 'string'],
            'desarrollo' => ['nullable', 'string'],
            'cierre' => ['nullable', 'string'],
        ]);

        $plan = $this->findPlanForDirector($id);
        $payload = is_array($plan->payload) ? $plan->payload : [];
        $sessionsKey = isset($payload['sessions']) ? 'sessions' : 'sesiones';
        $sessions = $payload[$sessionsKey] ?? [];

        if (! isset($sessions[$validated['session_index']])) {
            return response()->json([
                'success' => false,
                'error' => 'Sesión no encontrada.',
            ], 404);
        }

        $session = is_array($sessions[$validated['session_index']])
            ? $sessions[$validated['session_index']]
            : [];

        foreach (['title', 'date', 'inicio', 'desarrollo', 'cierre'] as $field) {
            if (array_key_exists($field, $validated) && $validated[$field] !== null) {
                $session[$field] = $validated[$field];
            }
        }

        $sessions[$validated['session_index']] = $session;
        $payload[$sessionsKey] = array_values($sessions);
        $plan->update(['payload' => $payload]);

        $activities = Activity::where('plan_block_id', $plan->id)
            ->where('colegio_id', auth()->user()->colegio_id)
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        if (isset($activities[$validated['session_index']])) {
            $activity = $activities[$validated['session_index']];
            $inicio = trim((string) ($session['inicio'] ?? ''));
            $desarrollo = trim((string) ($session['desarrollo'] ?? ''));
            $cierre = trim((string) ($session['cierre'] ?? ''));

            $description = collect([
                $inicio ? "**INICIO**\n{$inicio}" : null,
                $desarrollo ? "**DESARROLLO**\n{$desarrollo}" : null,
                $cierre ? "**CIERRE**\n{$cierre}" : null,
            ])->filter()->implode("\n\n");

            $activity->update([
                'title' => $session['title'] ?? $activity->title,
                'description' => $description !== '' ? $description : $activity->description,
                'due_date' => filled($session['date'] ?? null) ? $session['date'] : $activity->due_date,
            ]);
        }

        Notification::create([
            'user_id' => $plan->user_id,
            'colegio_id' => auth()->user()->colegio_id,
            'title' => 'El director editó tu planificación',
            'message' => 'El director realizó cambios directos en tu planificación «' . ($plan->tema ?? '') . '».',
            'link' => route('teacher.planner.show', ['id' => $plan->id]),
        ]);

        return response()->json([
            'success' => true,
            'sessions' => $payload[$sessionsKey],
        ]);
    }

    /**
     * @return array{inicio:string,desarrollo:string,cierre:string}
     */
    private function parsePedagogicalSections(?string $description): array
    {
        $text = trim((string) $description);
        $empty = ['inicio' => '', 'desarrollo' => '', 'cierre' => ''];

        if ($text === '') {
            return $empty;
        }

        $allHeaders = [
            'INICIO', 'DESARROLLO', 'CIERRE',
            'MOTIVACIÓN', 'PRESENTACIÓN', 'PRÁCTICA GUIADA', 'CIERRE REFLEXIVO',
            'ACTIVACIÓN', 'EXPLORACIÓN', 'EXPLICACIÓN', 'APLICACIÓN', 'EVALUACIÓN',
        ];

        $extract = function (string $source, array $headers) use ($allHeaders): string {
            foreach ($headers as $header) {
                $marker = '**' . $header . '**';
                $pos = mb_stripos($source, $marker);
                if ($pos === false) {
                    continue;
                }

                $start = $pos + mb_strlen($marker);
                $end = mb_strlen($source);

                foreach ($allHeaders as $nextHeader) {
                    if (mb_strtoupper($nextHeader) === mb_strtoupper($header)) {
                        continue;
                    }

                    $nextPos = mb_stripos($source, '**' . $nextHeader . '**', $start);
                    if ($nextPos !== false && $nextPos < $end) {
                        $end = $nextPos;
                    }
                }

                return trim(mb_substr($source, $start, $end - $start));
            }

            return '';
        };

        $inicio = $extract($text, ['INICIO', 'MOTIVACIÓN', 'ACTIVACIÓN']);
        $desarrollo = $extract($text, ['DESARROLLO', 'PRESENTACIÓN', 'EXPLORACIÓN', 'EXPLICACIÓN', 'PRÁCTICA GUIADA', 'APLICACIÓN']);
        $cierre = $extract($text, ['CIERRE', 'CIERRE REFLEXIVO', 'EVALUACIÓN']);

        if ($inicio !== '' || $desarrollo !== '' || $cierre !== '') {
            return compact('inicio', 'desarrollo', 'cierre');
        }

        if (preg_match('/INICIO:/i', $text)) {
            preg_match('/INICIO:\s*(.*?)(?=DESARROLLO:|$)/si', $text, $inicioMatch);
            preg_match('/DESARROLLO:\s*(.*?)(?=CIERRE:|$)/si', $text, $desarrolloMatch);
            preg_match('/CIERRE:\s*(.*?)$/si', $text, $cierreMatch);

            return [
                'inicio' => trim($inicioMatch[1] ?? ''),
                'desarrollo' => trim($desarrolloMatch[1] ?? ''),
                'cierre' => trim($cierreMatch[1] ?? ''),
            ];
        }

        return [
            'inicio' => '',
            'desarrollo' => $text,
            'cierre' => '',
        ];
    }
}

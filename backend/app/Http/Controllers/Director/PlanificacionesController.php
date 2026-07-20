<?php

namespace App\Http\Controllers\Director;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Notification;
use App\Models\Planificacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class PlanificacionesController extends Controller
{
    private function baseQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $colegioId = auth()->user()->colegio_id;

        return Planificacion::where('colegio_id', $colegioId)
            ->whereHas('user', fn ($query) => $query
                ->where('role', 'profesor')
                ->where('colegio_id', $colegioId));
    }

    public function index(Request $request): View
    {
        $selectedGrade = trim((string) $request->query('grade', ''));
        $selectedSubject = trim((string) $request->query('subject', ''));

        $query = $this->baseQuery()
            ->with('user:id,name')
            ->when($selectedGrade !== '', function ($q) use ($selectedGrade) {
                $q->whereHas('activities.course', fn ($courseQ) => $courseQ->where('grade', $selectedGrade));
            })
            ->when($selectedSubject !== '', function ($q) use ($selectedSubject) {
                $q->whereHas('activities.course', fn ($courseQ) => $courseQ->where('subject_name', $selectedSubject));
            })
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $colegioId = auth()->user()->colegio_id;
        $coursesQuery = Course::query()
            ->where('colegio_id', $colegioId)
            ->whereHas('teacher', fn ($teacherQ) => $teacherQ
                ->where('role', 'profesor')
                ->where('colegio_id', $colegioId));

        $gradeOptions = (clone $coursesQuery)
            ->select('grade')
            ->whereNotNull('grade')
            ->where('grade', '!=', '')
            ->distinct()
            ->orderBy('grade')
            ->pluck('grade')
            ->values();

        $subjectOptions = (clone $coursesQuery)
            ->select('subject_name')
            ->whereNotNull('subject_name')
            ->where('subject_name', '!=', '')
            ->distinct()
            ->orderBy('subject_name')
            ->pluck('subject_name')
            ->values();

        $planificaciones = $query->withCount('activities')->paginate(20)->withQueryString();
        $statusCounts = $this->baseQuery()
            ->selectRaw("COALESCE(status, 'pendiente') as status_key, COUNT(*) as total")
            ->groupByRaw("COALESCE(status, 'pendiente')")
            ->pluck('total', 'status_key');

        return view('director.planificaciones', compact(
            'planificaciones',
            'gradeOptions',
            'subjectOptions',
            'selectedGrade',
            'selectedSubject',
            'statusCounts'
        ));
    }

    public function sessions(int $id): JsonResponse
    {
        try {
            $plan = $this->baseQuery()->with('user:id,name')->findOrFail($id);
            if ($plan instanceof \Illuminate\Support\Collection) {
                $plan = $plan->first();
            }
            if (! $plan) {
                return response()->json([
                    'success' => false,
                    'error' => 'Planificación no encontrada.',
                ], 404);
            }

            $rawPayload = $plan->payload;
            if (is_array($rawPayload)) {
                $payload = $rawPayload;
            } elseif ($rawPayload instanceof \Illuminate\Support\Collection) {
                $payload = $rawPayload->toArray();
            } elseif (is_string($rawPayload) && trim($rawPayload) !== '') {
                $decoded = json_decode($rawPayload, true);
                $payload = is_array($decoded) ? $decoded : [];
            } else {
                $payload = [];
            }

            $rawSessions = $payload['sessions']
                ?? $payload['sesiones']
                ?? [];
            $sessions = is_array($rawSessions) ? $rawSessions : [];

            $courseName = (string) ($payload['course_name'] ?? '');
            $rechazoMotivo = $payload['rechazo_motivo']
                ?? $payload['rechazo_feedback']
                ?? '';

            $sessionsData = collect($sessions)
                ->filter(fn ($s) => is_array($s))
                ->values()
                ->map(fn ($s, $i) => [
                    'index' => $i + 1,
                    'date' => (string) ($s['date'] ?? '—'),
                    'inicio' => (string) ($s['inicio'] ?? ''),
                    'desarrollo' => (string) ($s['desarrollo'] ?? ''),
                    'cierre' => (string) ($s['cierre'] ?? ''),
                ])
                ->values()
                ->all();

            return response()->json([
                'success' => true,
                'id' => $plan->id,
                'tema' => (string) ($plan->tema ?? ''),
                'status' => (string) ($plan->status ?? 'pendiente'),
                'course_name' => $courseName,
                'teacher_name' => (string) ($plan->user?->name ?? '—'),
                'created_at' => optional($plan->created_at)?->format('d/m/Y H:i') ?? '',
                'sessions' => $sessionsData,
                'rechazo_motivo' => (string) $rechazoMotivo,
            ]);
        } catch (\Throwable $e) {
            Log::error('director.planificaciones.sessions.error', [
                'planificacion_id' => $id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function approve(int $id): JsonResponse
    {
        $plan = $this->baseQuery()->findOrFail($id);
        $plan->update(['status' => 'aprobado']);

        Notification::create([
            'user_id' => $plan->user_id,
            'colegio_id' => auth()->user()->colegio_id,
            'title' => 'Planificación aprobada',
            'message' => 'Dirección aprobó tu planificación «' . ($plan->tema ?? '') . '».',
            'link' => route('teacher.planner.show', ['id' => $plan->id]),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Planificación aprobada correctamente.',
            'status' => 'aprobado',
        ]);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'feedback' => ['nullable', 'string', 'max:1000'],
        ]);

        $plan = $this->baseQuery()->findOrFail($id);
        $payload = $plan->payload ?? [];
        $payload['rechazo_feedback'] = $validated['feedback'] ?? null;
        $plan->update([
            'status' => 'rechazado',
            'payload' => $payload,
        ]);

        Log::info('PLAN_RECHAZADA', [
            'planificacion_id' => $plan->id,
            'teacher_id' => $plan->user_id,
            'feedback' => $validated['feedback'] ?? null,
        ]);

        Notification::create([
            'user_id' => $plan->user_id,
            'colegio_id' => $request->user()->colegio_id,
            'title' => 'Planificación rechazada',
            'message' => 'El director rechazó tu planificación «' . ($plan->tema ?? '') . '»' . ($validated['feedback'] ? '. Motivo: ' . $validated['feedback'] : ''),
            'link' => route('teacher.planner.show', ['id' => $plan->id]),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Planificación rechazada. El docente recibirá el feedback.',
            'status' => 'rechazado',
        ]);
    }

    public function pendientesCount(): int
    {
        return $this->baseQuery()->whereIn('status', ['pendiente', 'pendiente_revision'])->count();
    }
}

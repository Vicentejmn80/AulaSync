<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Course;
use App\Models\ManualPlanning;
use App\Models\Planificacion;
use App\Services\DirectorAlertService;
use App\Support\LessonTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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

        $lessonTemplate = LessonTemplate::forUser(auth()->user());
        if ($planning) {
            $fromPayload = data_get($planning, 'payload.lesson_template');
            $fromSession = data_get($planning, 'sessions.0.lesson_template');
            $lessonTemplate = LessonTemplate::normalize((string) ($fromPayload ?: $fromSession ?: $lessonTemplate));
            if (! empty($planning->planificacion_id)) {
                $linked = Planificacion::find($planning->planificacion_id);
                if (is_array($linked?->payload) && ! empty($linked->payload['lesson_template'])) {
                    $lessonTemplate = LessonTemplate::normalize((string) $linked->payload['lesson_template']);
                }
            }
        }

        $templates = collect(LessonTemplate::ids())->map(fn ($id) => [
            'id' => $id,
            'label' => LessonTemplate::label($id),
            'phases' => LessonTemplate::phaseDefs($id),
        ])->values();

        return view('teacher.planner.manual', compact(
            'planning',
            'courses',
            'selectedCourseId',
            'lessonTemplate',
            'templates'
        ));
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
            'lesson_template' => ['nullable', 'string', 'max:40'],
            'sessions' => ['required', 'array', 'min:1'],
            'sessions.*.date' => ['nullable', 'date'],
            'sessions.*.title' => ['nullable', 'string', 'max:180'],
            'sessions.*.inicio' => ['nullable', 'string'],
            'sessions.*.desarrollo' => ['nullable', 'string'],
            'sessions.*.cierre' => ['nullable', 'string'],
            'sessions.*.phases' => ['nullable', 'array'],
            'sessions.*.phases.*' => ['nullable', 'string'],
        ]);

        $template = LessonTemplate::normalize((string) ($data['lesson_template'] ?? LessonTemplate::forUser($user)));
        $data['sessions'] = collect($data['sessions'])
            ->map(fn ($session) => $this->normalizeSession($session, $template))
            ->values()
            ->all();

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
                'lesson_template' => $template,
                'sessions' => collect($data['sessions'])
                    ->map(fn ($s) => [
                        'date' => $s['date'] ?? null,
                        'title' => trim((string) ($s['title'] ?? '')),
                        'inicio' => trim((string) ($s['inicio'] ?? '')),
                        'desarrollo' => trim((string) ($s['desarrollo'] ?? '')),
                        'cierre' => trim((string) ($s['cierre'] ?? '')),
                        'phases' => $s['phases'] ?? [],
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

            $planificacion = DB::transaction(function () use ($teacherId, $colegioId, $data, $course, $courseName, $signature, $existingPlan, $template, &$originalStatus) {
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
                    'lesson_template' => $template,
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

            DB::transaction(function () use ($teacherId, $colegioId, $data, $course, $courseName, $planificacion, $template) {
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
                    $sessionDate = filled($session['date'] ?? null)
                        ? Carbon::parse((string) $session['date'])->format('Y-m-d')
                        : null;
                    $description = LessonTemplate::build($session['phases'] ?? [], $template);
                    if ($description === '') {
                        $inicio = trim((string) ($session['inicio'] ?? ''));
                        $desarrollo = trim((string) ($session['desarrollo'] ?? ''));
                        $cierre = trim((string) ($session['cierre'] ?? ''));
                        $description = collect([
                            $inicio ? "INICIO:\n{$inicio}" : null,
                            $desarrollo ? "DESARROLLO:\n{$desarrollo}" : null,
                            $cierre ? "CIERRE:\n{$cierre}" : null,
                        ])->filter()->implode("\n\n");
                    }

                    $title = trim((string) ($session['title'] ?? ''));
                    if ($title === '') {
                        $title = $courseName.' · Sesión #'.($idx + 1);
                    }

                    $activity = new Activity([
                        'teacher_id'       => $teacherId,
                        'course_id'        => $course->id,
                        'plan_block_id'    => $planificacion->id,
                        'title'            => $title,
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

    public function generate(Request $request): JsonResponse
    {
        $user = auth()->user();
        $data = $request->validate([
            'prompt' => ['required', 'string', 'min:8', 'max:2000'],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'lesson_template' => ['nullable', 'string', 'max:40'],
            'session_count' => ['nullable', 'integer', 'min:1', 'max:12'],
            'start_date' => ['nullable', 'date'],
        ]);

        $template = LessonTemplate::normalize((string) ($data['lesson_template'] ?? LessonTemplate::forUser($user)));
        $count = (int) ($data['session_count'] ?? 4);
        $start = filled($data['start_date'] ?? null)
            ? Carbon::parse((string) $data['start_date'])->startOfDay()
            : now()->startOfDay();

        $course = null;
        if (! empty($data['course_id'])) {
            $course = Course::where('teacher_id', $user->id)
                ->where('colegio_id', $user->colegio_id)
                ->where('id', (int) $data['course_id'])
                ->first(['id', 'subject_name', 'grade', 'section']);
        }

        $apiKey = config('services.openai.key', env('OPENAI_API_KEY'));
        if (empty($apiKey)) {
            return response()->json([
                'success' => false,
                'error' => 'OPENAI_API_KEY no está configurada.',
            ], 200);
        }

        $phaseDefs = LessonTemplate::phaseDefs($template);
        $phaseKeys = array_column($phaseDefs, 'key');
        $phaseExample = collect($phaseDefs)
            ->mapWithKeys(fn ($def) => [$def['key'] => 'texto breve de '.$def['label']])
            ->all();
        $courseLabel = $course
            ? trim($course->subject_name.' '.$course->grade.($course->section ? ' / '.$course->section : ''))
            : 'el curso del docente';
        $dates = [];
        for ($i = 0; $i < $count; $i++) {
            $dates[] = $start->copy()->addDays($i)->toDateString();
        }

        $system = 'Eres un pedagogo experto en colegios de Latinoamérica. '
            .'Responde SOLO JSON válido, sin markdown. '
            .'Estructura exacta: {"sessions":[{"date":"YYYY-MM-DD","title":"","phases":{}}]}. '
            .'Cada phases debe incluir exactamente estas claves: '.implode(', ', $phaseKeys).'. '
            .'El estilo pedagógico obligatorio es «'.LessonTemplate::label($template).'». '
            .'Redacta clases concretas, accionables y de 4 a 8 líneas por fase. No inventes el nombre del colegio.';

        $userMessage = 'Curso: '.$courseLabel.'. '
            .'Fechas sugeridas (usa estas u otras cercanas): '.implode(', ', $dates).'. '
            .'Cantidad de sesiones: '.$count.'. '
            .'Ejemplo de phases: '.json_encode($phaseExample, JSON_UNESCAPED_UNICODE).'. '
            .'Pedido del docente: '.$data['prompt'];

        try {
            $response = Http::withToken($apiKey)
                ->timeout(70)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-4o-mini',
                    'temperature' => 0.4,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $userMessage],
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('Manual planning AI error', ['status' => $response->status(), 'body' => $response->body()]);

                return response()->json(['success' => false, 'error' => 'La IA no pudo generar la planificación.'], 200);
            }

            $content = data_get($response->json(), 'choices.0.message.content', '{}');
            $payload = json_decode((string) $content, true);
            $rawSessions = is_array($payload) ? ($payload['sessions'] ?? $payload['sesiones'] ?? []) : [];
            if (! is_array($rawSessions) || $rawSessions === []) {
                return response()->json(['success' => false, 'error' => 'La IA devolvió un formato inválido.'], 200);
            }

            $sessions = collect($rawSessions)
                ->take(12)
                ->values()
                ->map(function ($session, $index) use ($template, $start) {
                    if (! is_array($session)) {
                        $session = [];
                    }
                    if (empty($session['date'])) {
                        $session['date'] = $start->copy()->addDays($index)->toDateString();
                    }

                    return $this->normalizeSession($session, $template);
                })
                ->all();

            return response()->json([
                'success' => true,
                'message' => 'Sesiones generadas. Revisa, edita y guarda cuando estés listo.',
                'lesson_template' => $template,
                'sessions' => $sessions,
            ]);
        } catch (\Throwable $e) {
            Log::error('Manual planning AI exception: '.$e->getMessage());

            return response()->json(['success' => false, 'error' => 'Error al contactar la IA.'], 200);
        }
    }

    public function pdf($id = null)
    {
        return response()->json(['message' => 'Función PDF en desarrollo para el ID: ' . $id]);
    }

    private function normalizeSession(array $session, string $template): array
    {
        $phases = is_array($session['phases'] ?? null) ? $session['phases'] : [];
        $empty = LessonTemplate::emptyPhases($template);

        foreach ($empty as $key => $_) {
            $empty[$key] = trim((string) ($phases[$key] ?? $session[$key] ?? ''));
        }

        if (! implode('', $empty)) {
            $fallback = trim((string) ($session['desarrollo'] ?? $session['inicio'] ?? $session['title'] ?? ''));
            $keys = array_keys($empty);
            if ($fallback !== '' && isset($keys[min(1, count($keys) - 1)])) {
                $empty[$keys[min(1, count($keys) - 1)]] = $fallback;
            }
        }

        $classic = LessonTemplate::parse(
            LessonTemplate::rewrite(LessonTemplate::build($empty, $template), LessonTemplate::CLASSIC),
            LessonTemplate::CLASSIC
        );

        $date = $session['date'] ?? null;
        if (filled($date)) {
            try {
                $date = Carbon::parse((string) $date)->toDateString();
            } catch (\Throwable) {
                $date = null;
            }
        }

        return [
            'id' => $session['id'] ?? (int) (microtime(true) * 1000) + random_int(1, 99),
            'date' => $date,
            'title' => trim((string) ($session['title'] ?? '')),
            'inicio' => trim((string) ($classic['inicio'] ?? '')),
            'desarrollo' => trim((string) ($classic['desarrollo'] ?? '')),
            'cierre' => trim((string) ($classic['cierre'] ?? '')),
            'phases' => $empty,
            'lesson_template' => $template,
        ];
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
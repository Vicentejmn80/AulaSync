<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Colegio;
use App\Models\Course;
use App\Models\DirectorAiOperationLog;
use App\Models\Evaluation;
use App\Models\IntelligenceDocument;
use App\Models\Planificacion;
use App\Models\ProductEvent;
use App\Models\Student;
use App\Models\User;
use App\Support\SuperAdminCopy;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SuperAdminAnalyticsService
{
    /**
     * @return array{from: Carbon, to: Carbon, colegio_id: ?int, role: ?string}
     */
    public function filters(array $input): array
    {
        $to = ! empty($input['to']) ? Carbon::parse($input['to'])->endOfDay() : now()->endOfDay();
        $from = ! empty($input['from']) ? Carbon::parse($input['from'])->startOfDay() : now()->subDays(29)->startOfDay();
        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        $colegioId = isset($input['colegio_id']) && $input['colegio_id'] !== '' ? (int) $input['colegio_id'] : null;
        $role = isset($input['role']) && in_array($input['role'], ['director', 'profesor', 'representante', 'super_admin'], true)
            ? $input['role']
            : null;

        return [
            'from' => $from,
            'to' => $to,
            'colegio_id' => $colegioId,
            'role' => $role,
        ];
    }

    public function overview(array $filters): array
    {
        $from = $filters['from'];
        $to = $filters['to'];

        $activeCutoff = now()->subDays(30);
        $activeColegioIds = $this->activeColegioIds($activeCutoff);

        $directors = User::query()->where('role', 'director');
        $teachers = User::query()->where('role', 'profesor');
        $this->scopeUsers($directors, $filters);
        $this->scopeUsers($teachers, $filters);

        $newUsers = User::query()->whereBetween('created_at', [$from, $to]);
        $this->scopeUsers($newUsers, $filters);

        return [
            'colegios' => Colegio::count(),
            'colegios_activos' => $activeColegioIds->count(),
            'directores' => (clone $directors)->count(),
            'directores_activos' => (clone $directors)->where('last_login_at', '>=', $activeCutoff)->count(),
            'docentes' => (clone $teachers)->count(),
            'docentes_activos' => (clone $teachers)->where('last_login_at', '>=', $activeCutoff)->count(),
            'usuarios_hoy' => $this->activeUsersSince(now()->startOfDay(), $filters),
            'usuarios_7d' => $this->activeUsersSince(now()->subDays(6)->startOfDay(), $filters),
            'usuarios_30d' => $this->activeUsersSince(now()->subDays(29)->startOfDay(), $filters),
            'nuevos_usuarios' => $newUsers->count(),
            'sesiones_activas' => $this->activeSessions($filters),
            'logins_periodo' => $this->eventsQuery($filters)->where('event', 'login')->count(),
            'crecimiento' => $this->userGrowth($from, $to, $filters),
            'actividad_reciente' => $this->recentActivity($filters, 12),
        ];
    }

    public function usage(array $filters): array
    {
        $from = $filters['from'];
        $to = $filters['to'];

        $events = $this->eventsQuery($filters)
            ->whereNotNull('action')
            ->selectRaw('action, category, COUNT(*) as total, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as ok, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as fail', ['success', 'failed'])
            ->groupBy('action', 'category')
            ->orderByDesc('total')
            ->limit(20)
            ->get();

        $aiActions = $this->eventsQuery($filters)
            ->whereIn('source', $this->aiSources())
            ->whereNotNull('action')
            ->selectRaw('action, source, COUNT(*) as total')
            ->groupBy('action', 'source')
            ->orderByDesc('total')
            ->limit(15)
            ->get();

        $docs = IntelligenceDocument::query()->whereBetween('created_at', [$from, $to]);
        $plans = Planificacion::query()->whereBetween('created_at', [$from, $to]);
        $activities = Activity::query()->whereBetween('created_at', [$from, $to]);
        $evals = Evaluation::query()->whereBetween('created_at', [$from, $to]);
        if ($filters['colegio_id']) {
            $docs->where('colegio_id', $filters['colegio_id']);
            $plans->where('colegio_id', $filters['colegio_id']);
            $activities->where('colegio_id', $filters['colegio_id']);
            $evals->where('colegio_id', $filters['colegio_id']);
        }

        $errors = $this->eventsQuery($filters)
            ->where('status', 'failed')
            ->whereNotNull('error_code')
            ->selectRaw('error_code, COUNT(*) as total')
            ->groupBy('error_code')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        $used = $events->pluck('action')->all();
        $known = ['createActivity', 'bulkPlan', 'createEvaluation', 'setGradeBatch', 'query_academic', 'create_student', 'create_course', 'create_teacher'];
        $unused = array_values(array_filter($known, fn ($name) => ! in_array($name, $used, true)));

        return [
            'mas_usadas' => $events,
            'menos_usadas' => $unused,
            'acciones_ia' => $aiActions,
            'documentos' => [
                'total' => (clone $docs)->count(),
                'aplicados' => (clone $docs)->where('status', IntelligenceDocument::STATUS_APPLIED)->count(),
                'fallidos' => (clone $docs)->where('status', IntelligenceDocument::STATUS_FAILED)->count(),
            ],
            'planificaciones' => $plans->count(),
            'actividades' => $activities->count(),
            'tareas' => (clone $activities)->where(function ($q) {
                $q->where('type', 'tarea')->orWhere('is_homework', true);
            })->count(),
            'evaluaciones_ia' => $evals->where('generated_by_ai', true)->count(),
            'consultas' => $this->eventsQuery($filters)->where('category', 'academic')->where('event', 'ai_action')->count()
                + DirectorAiOperationLog::query()->whereBetween('created_at', [$from, $to])
                    ->when($filters['colegio_id'], fn ($q) => $q->where('colegio_id', $filters['colegio_id']))
                    ->where('intent', 'like', 'query%')
                    ->count(),
            'exitosas' => $this->eventsQuery($filters)->where('status', 'success')->count(),
            'fallidas' => $this->eventsQuery($filters)->where('status', 'failed')->count(),
            'errores' => $errors,
        ];
    }

    public function intelligence(array $filters): array
    {
        $from = $filters['from'];
        $to = $filters['to'];

        $byRole = $this->eventsQuery($filters)
            ->whereIn('source', $this->aiSources())
            ->selectRaw('source, role, COUNT(*) as total')
            ->groupBy('source', 'role')
            ->get();

        $intents = DirectorAiOperationLog::query()
            ->whereBetween('created_at', [$from, $to])
            ->when($filters['colegio_id'], fn ($q) => $q->where('colegio_id', $filters['colegio_id']))
            ->selectRaw('intent, COUNT(*) as total')
            ->groupBy('intent')
            ->orderByDesc('total')
            ->limit(12)
            ->get();

        $categories = $this->eventsQuery($filters)
            ->whereIn('source', $this->aiSources())
            ->selectRaw('category, COUNT(*) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        $unresolved = DirectorAiOperationLog::query()
            ->whereBetween('created_at', [$from, $to])
            ->when($filters['colegio_id'], fn ($q) => $q->where('colegio_id', $filters['colegio_id']))
            ->where('status', 'pending_confirmation')
            ->count()
            + $this->eventsQuery($filters)->where('status', 'unresolved')->count();

        $failedActions = $this->eventsQuery($filters)
            ->whereIn('source', $this->aiSources())
            ->where('status', 'failed')
            ->selectRaw('action, COUNT(*) as total')
            ->groupBy('action')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        $latency = $this->eventsQuery($filters)
            ->whereNotNull('duration_ms')
            ->selectRaw('AVG(duration_ms) as avg_ms, MAX(duration_ms) as max_ms, COUNT(*) as samples')
            ->first();

        $cost = $this->eventsQuery($filters)
            ->selectRaw('SUM(estimated_cost_usd) as cost, SUM(COALESCE(prompt_tokens,0) + COALESCE(completion_tokens,0)) as tokens')
            ->first();

        $trend = $this->eventsQuery($filters)
            ->whereIn('source', $this->aiSources())
            ->selectRaw($this->dayExpr('created_at').' as day, COUNT(*) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $directorFailed = DirectorAiOperationLog::query()
            ->whereBetween('created_at', [$from, $to])
            ->when($filters['colegio_id'], fn ($q) => $q->where('colegio_id', $filters['colegio_id']))
            ->where('status', 'failed')
            ->count();

        return [
            'por_rol' => $byRole,
            'intenciones' => $intents,
            'categorias' => $categories,
            'sin_resolver' => $unresolved,
            'acciones_error' => $failedActions,
            'latencia' => [
                'avg_ms' => $latency?->avg_ms !== null ? (int) round((float) $latency->avg_ms) : null,
                'max_ms' => $latency?->max_ms !== null ? (int) $latency->max_ms : null,
                'samples' => (int) ($latency?->samples ?? 0),
            ],
            'tokens' => (int) ($cost?->tokens ?? 0),
            'costo_usd' => $cost?->cost !== null ? round((float) $cost->cost, 4) : null,
            'tendencia' => $trend,
            'director_fallos' => $directorFailed,
        ];
    }

    public function schools(array $filters): Collection
    {
        $from = $filters['from'];
        $cutoff7 = now()->subDays(7);
        $cutoff30 = now()->subDays(30);

        $colegios = Colegio::query()
            ->with('director:id,name,email,last_login_at')
            ->withCount([
                'users',
                'users as docentes_count' => fn ($q) => $q->where('role', 'profesor'),
            ])
            ->orderBy('name')
            ->get();

        $lastLogins = User::query()
            ->whereNotNull('colegio_id')
            ->whereNotNull('last_login_at')
            ->selectRaw('colegio_id, MAX(last_login_at) as last_seen')
            ->groupBy('colegio_id')
            ->pluck('last_seen', 'colegio_id');

        $activityCounts = Activity::query()
            ->whereBetween('created_at', [$from, $filters['to']])
            ->selectRaw('colegio_id, COUNT(*) as total')
            ->groupBy('colegio_id')
            ->pluck('total', 'colegio_id');

        $eventCounts = ProductEvent::query()
            ->whereBetween('created_at', [$from, $filters['to']])
            ->whereNotNull('colegio_id')
            ->selectRaw('colegio_id, COUNT(*) as total, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as fail', ['failed'])
            ->groupBy('colegio_id')
            ->get()
            ->keyBy('colegio_id');

        $functions = ProductEvent::query()
            ->whereBetween('created_at', [$from, $filters['to']])
            ->whereNotNull('colegio_id')
            ->whereNotNull('action')
            ->selectRaw('colegio_id, COUNT(DISTINCT action) as functions')
            ->groupBy('colegio_id')
            ->pluck('functions', 'colegio_id');

        return $colegios->map(function (Colegio $colegio) use ($lastLogins, $activityCounts, $eventCounts, $functions, $cutoff7, $cutoff30) {
            $lastSeen = $lastLogins[$colegio->id] ?? null;
            $lastSeenAt = $lastSeen ? Carbon::parse($lastSeen) : null;
            $status = 'inactivo';
            if ($lastSeenAt && $lastSeenAt->gte($cutoff7)) {
                $status = 'activo';
            } elseif ($lastSeenAt && $lastSeenAt->gte($cutoff30)) {
                $status = 'riesgo';
            }

            $events = $eventCounts[$colegio->id] ?? null;
            $functionCount = (int) ($functions[$colegio->id] ?? 0);
            $adoption = $functionCount >= 6 ? 'alta' : ($functionCount >= 3 ? 'media' : ($functionCount > 0 ? 'baja' : 'nula'));

            return [
                'id' => $colegio->id,
                'name' => $colegio->name,
                'director' => $colegio->director?->name,
                'usuarios' => (int) $colegio->users_count,
                'docentes' => (int) $colegio->docentes_count,
                'actividad' => (int) ($activityCounts[$colegio->id] ?? 0),
                'eventos' => (int) ($events->total ?? 0),
                'errores' => (int) ($events->fail ?? 0),
                'ultimo_acceso' => $lastSeenAt,
                'funciones' => $functionCount,
                'adopcion' => $adoption,
                'estado' => $status,
            ];
        });
    }

    public function schoolDetail(Colegio $colegio, array $filters): array
    {
        $from = $filters['from'];
        $to = $filters['to'];
        $scoped = array_merge($filters, ['colegio_id' => $colegio->id]);

        $users = User::query()
            ->where('colegio_id', $colegio->id)
            ->orderBy('role')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'last_login_at', 'onboarding_completed']);

        return [
            'colegio' => $colegio->load('director:id,name,email'),
            'overview' => $this->overview($scoped),
            'usage' => $this->usage($scoped),
            'intelligence' => $this->intelligence($scoped),
            'users' => $users,
            'director_intents' => DirectorAiOperationLog::query()
                ->where('colegio_id', $colegio->id)
                ->whereBetween('created_at', [$from, $to])
                ->orderByDesc('id')
                ->limit(20)
                ->get(['id', 'intent', 'status', 'created_at', 'executed_at']),
            'documentos' => IntelligenceDocument::query()
                ->where('colegio_id', $colegio->id)
                ->whereBetween('created_at', [$from, $to])
                ->orderByDesc('id')
                ->limit(15)
                ->get(['id', 'original_name', 'kind', 'status', 'created_at']),
            'courses' => Course::query()
                ->where('colegio_id', $colegio->id)
                ->orderBy('grade')
                ->orderBy('subject_name')
                ->get(['id', 'subject_name', 'grade', 'section', 'teacher_id']),
            'teachers' => User::query()
                ->where('colegio_id', $colegio->id)
                ->where('role', 'profesor')
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'role']),
            'students' => Student::query()
                ->where('colegio_id', $colegio->id)
                ->orderBy('grade')
                ->orderBy('name')
                ->get(['id', 'name', 'grade', 'section']),
        ];
    }

    public function health(array $filters): array
    {
        $failedJobs = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;

        $recentFails = $this->eventsQuery($filters)
            ->where('status', 'failed')
            ->orderByDesc('id')
            ->limit(15)
            ->get(['id', 'source', 'action', 'error_code', 'colegio_id', 'created_at']);

        $latency = $this->eventsQuery($filters)
            ->whereNotNull('duration_ms')
            ->selectRaw('AVG(duration_ms) as avg_ms, COUNT(*) as samples')
            ->first();

        $iaFails = $this->eventsQuery($filters)
            ->whereIn('source', $this->aiSources())
            ->where('status', 'failed')
            ->count()
            + DirectorAiOperationLog::query()
                ->whereBetween('created_at', [$filters['from'], $filters['to']])
                ->when($filters['colegio_id'], fn ($q) => $q->where('colegio_id', $filters['colegio_id']))
                ->where('status', 'failed')
                ->count();

        $docFails = IntelligenceDocument::query()
            ->whereBetween('created_at', [$filters['from'], $filters['to']])
            ->when($filters['colegio_id'], fn ($q) => $q->where('colegio_id', $filters['colegio_id']))
            ->where('status', IntelligenceDocument::STATUS_FAILED)
            ->count();

        $status = 'estable';
        if ($iaFails > 10 || $failedJobs > 0) {
            $status = 'degradado';
        }
        if ($iaFails > 40) {
            $status = 'critico';
        }

        return [
            'estado' => $status,
            'errores_recientes' => $recentFails,
            'acciones_fallidas' => $this->eventsQuery($filters)->where('status', 'failed')->count(),
            'fallos_ia' => $iaFails,
            'documentos_fallidos' => $docFails,
            'failed_jobs' => $failedJobs,
            'latencia_ms' => $latency?->avg_ms !== null ? (int) round((float) $latency->avg_ms) : null,
            'latencia_muestras' => (int) ($latency?->samples ?? 0),
        ];
    }

    public function insights(array $filters): array
    {
        $usage = $this->usage($filters);
        $intelligence = $this->intelligence($filters);
        $health = $this->health($filters);
        $overview = $this->overview($filters);

        $top = $usage['mas_usadas']->first();
        $topIntent = $intelligence['intenciones']->first();
        $errorLabels = $usage['errores']->take(3)->map(fn ($row) => SuperAdminCopy::error($row->error_code))->all();
        $unusedLabels = array_map(fn ($name) => SuperAdminCopy::action($name), array_slice($usage['menos_usadas'], 0, 5));

        return [
            'mas_utilizan' => $top
                ? SuperAdminCopy::action($top->action).' ('.$top->total.' veces en el periodo).'
                : 'Todavía no hay actividad registrada en este periodo.',
            'intentando' => $topIntent
                ? 'En el chat del director lo más pedido es «'.SuperAdminCopy::action($topIntent->intent).'».'
                : 'No hay pedidos del chat de dirección en este periodo.',
            'problemas' => $health['acciones_fallidas'] > 0
                ? $health['acciones_fallidas'].' acciones fallaron y '.$health['fallos_ia'].' fueron del chat o de documentos con IA.'
                : 'No hay fallos registrados en este periodo.',
            'mejorar' => $usage['errores']->isNotEmpty()
                ? 'Revisar: '.implode(', ', $errorLabels).'.'
                : ($intelligence['sin_resolver'] > 0
                    ? $intelligence['sin_resolver'].' consultas quedaron sin respuesta clara o esperando confirmación.'
                    : 'No hay suficiente señal de error para priorizar un arreglo.'),
            'casi_nadie' => $unusedLabels !== []
                ? implode(', ', $unusedLabels)
                : 'Las funciones conocidas ya aparecen en el uso, o aún no hay actividad suficiente para comparar.',
            'tendencia' => $overview['nuevos_usuarios'].' usuarios nuevos; '.$overview['usuarios_30d'].' activos en 30 días; '.$overview['colegios_activos'].' colegios activos.',
            'costo' => $intelligence['costo_usd'] !== null
                ? '$'.number_format($intelligence['costo_usd'], 4).' USD estimados, según el consumo que sí se registró.'
                : 'Todavía no hay consumo de IA registrado. El costo aparecerá cuando las llamadas guarden tokens.',
        ];
    }

    public function filterOptions(): array
    {
        return [
            'colegios' => Colegio::query()->orderBy('name')->get(['id', 'name']),
        ];
    }

    private function eventsQuery(array $filters)
    {
        return ProductEvent::query()
            ->whereBetween('created_at', [$filters['from'], $filters['to']])
            ->when($filters['colegio_id'], fn ($q) => $q->where('colegio_id', $filters['colegio_id']))
            ->when($filters['role'], fn ($q) => $q->where('role', $filters['role']));
    }

    private function scopeUsers($query, array $filters): void
    {
        $query->when($filters['colegio_id'], fn ($q) => $q->where('colegio_id', $filters['colegio_id']))
            ->when($filters['role'], fn ($q) => $q->where('role', $filters['role']));
    }

    private function activeUsersSince(Carbon $since, array $filters): int
    {
        $query = User::query()->where('last_login_at', '>=', $since);
        $this->scopeUsers($query, $filters);

        return $query->count();
    }

    private function activeSessions(array $filters): int
    {
        if (! Schema::hasTable('sessions')) {
            return 0;
        }

        $cutoff = now()->subMinutes(120)->timestamp;
        $query = DB::table('sessions')->where('last_activity', '>=', $cutoff);
        if ($filters['colegio_id'] || $filters['role']) {
            $userIds = User::query()
                ->when($filters['colegio_id'], fn ($q) => $q->where('colegio_id', $filters['colegio_id']))
                ->when($filters['role'], fn ($q) => $q->where('role', $filters['role']))
                ->pluck('id');
            $query->whereIn('user_id', $userIds);
        }

        return $query->count();
    }

    private function activeColegioIds(Carbon $since): Collection
    {
        $fromLogins = User::query()
            ->whereNotNull('colegio_id')
            ->where('last_login_at', '>=', $since)
            ->distinct()
            ->pluck('colegio_id');

        $fromActivity = Activity::query()
            ->where('created_at', '>=', $since)
            ->whereNotNull('colegio_id')
            ->distinct()
            ->pluck('colegio_id');

        return $fromLogins->merge($fromActivity)->unique()->values();
    }

    private function userGrowth(Carbon $from, Carbon $to, array $filters): Collection
    {
        $query = User::query()->whereBetween('created_at', [$from, $to]);
        $this->scopeUsers($query, $filters);

        return $query
            ->selectRaw($this->dayExpr('created_at').' as day, COUNT(*) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->get();
    }

    private function recentActivity(array $filters, int $limit): Collection
    {
        return $this->eventsQuery($filters)
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'source', 'event', 'action', 'status', 'role', 'colegio_id', 'created_at']);
    }

    private function dayExpr(string $column): string
    {
        return DB::getDriverName() === 'pgsql'
            ? "to_char({$column}, 'YYYY-MM-DD')"
            : "strftime('%Y-%m-%d', {$column})";
    }

    /**
     * Fuentes que cuentan como uso de IA (chat docente, chat director y documentos).
     *
     * @return list<string>
     */
    private function aiSources(): array
    {
        return ['teacher_ai', 'director_ai', 'director_data_agent', 'intelligence'];
    }
}

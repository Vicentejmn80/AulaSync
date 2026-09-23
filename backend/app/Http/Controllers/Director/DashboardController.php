<?php

namespace App\Http\Controllers\Director;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Colegio;
use App\Models\Course;
use App\Models\Grade;
use App\Models\Planificacion;
use App\Models\Student;
use App\Models\TeacherInvite;
use App\Models\User;
use App\Services\DirectorAnalyticsQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DashboardController extends Controller
{
    private const WEIGHTED_AVERAGE_SQL = '
        CASE
            WHEN SUM(activities.max_score * activities.weight_percentage) > 0
            THEN SUM(grades.score * activities.weight_percentage) * 100.0 / SUM(activities.max_score * activities.weight_percentage)
            ELSE AVG((grades.score * 100.0) / NULLIF(activities.max_score, 0))
        END
    ';

    private const AT_RISK_THRESHOLD = 60;

    public function __construct(private DirectorAnalyticsQueryService $analytics)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $settings = $user->settings;
        $colegioId = $user->colegio_id;

        // Promedios, riesgo, cumplimiento y alertas escanean notas × actividades.
        // Eso es lo que mantenía el loader de login >20s: el navegador no
        // muestra el dashboard hasta que este HTML termina. 90s de caché
        // saca ese cálculo del camino de cada inicio de sesión.
        $snapshot = Cache::remember(
            'director.dashboard.v1.'.($colegioId ?: 'none'),
            90,
            fn () => $this->buildDashboardSnapshot($colegioId ? (int) $colegioId : null)
        );

        $colegio = $colegioId ? Colegio::find($colegioId) : null;

        $institution = [
            'name' => $colegio?->name ?? $settings?->nombre_institucion ?? 'Aulasync',
            'period' => data_get($settings?->preferencias, 'periodo_academico', now()->year . '-' . now()->copy()->addYear()->year),
            'campuses' => data_get($settings?->preferencias, 'cantidad_sedes', 1),
            'invite_code_masked' => true,
            'has_codes_pin' => filled($colegio?->codes_pin),
        ];

        return view('director.dashboard', array_merge($snapshot, compact(
            'user',
            'settings',
            'colegio',
            'institution',
        )));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDashboardSnapshot(?int $colegioId): array
    {
        $planificacionesRecientes = collect();
        $actividadesRecientes = collect();
        $profesores = collect();

        $totalStudents = Student::where('colegio_id', $colegioId)->count();
        $totalCourses = Course::where('colegio_id', $colegioId)->count();
        $pendingInvites = TeacherInvite::where('colegio_id', $colegioId)
            ->whereNull('claimed_by')
            ->whereNull('revoked_at')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->count();

        $globalAverageQuery = Grade::query()
            ->join('activities', 'grades.activity_id', '=', 'activities.id')
            ->join('courses', 'activities.course_id', '=', 'courses.id')
            ->where('activities.max_score', '>', 0)
            ->where('activities.colegio_id', $colegioId)
            ->where('courses.colegio_id', $colegioId)
            ->where('grades.colegio_id', $colegioId)
            ->selectRaw('
                ' . self::WEIGHTED_AVERAGE_SQL . ' as avg_pct
            ');
        $this->onlyPublishedGrades($globalAverageQuery);
        $globalAverage = $globalAverageQuery
            ->value('avg_pct');

        $atRiskStudents = $this->atRiskStudentsQuery($colegioId)->get()->count();

        $teacherCount = User::where('role', 'profesor')
            ->where('colegio_id', $colegioId)
            ->count();

        $needsSetup = $teacherCount === 0 || $totalCourses === 0 || $totalStudents === 0;

        $teachersWithPendingGrades = $this->teachersWithPendingGrades($colegioId);
        $teacherCompliance = $teacherCount > 0
            ? round((($teacherCount - $teachersWithPendingGrades) / $teacherCount) * 100)
            : 100;

        $kpis = [
            [
                'id' => 'enrollment',
                'label' => 'Matrícula Total',
                'value' => number_format($totalStudents),
                'hint' => 'Alumnos registrados en la institución',
                'icon' => 'fa-users',
                'accent' => 'from-cyan-400 to-blue-500',
                'action' => null,
            ],
            [
                'id' => 'average',
                'label' => 'Promedio Global',
                'value' => $globalAverage !== null ? round((float) $globalAverage, 1) . '%' : '—',
                'hint' => 'Promedio ponderado sobre registros de notas',
                'icon' => 'fa-chart-line',
                'accent' => 'from-violet-400 to-fuchsia-500',
                'action' => null,
            ],
            [
                'id' => 'compliance',
                'label' => 'Cumplimiento Docente',
                'value' => $teacherCompliance . '%',
                'hint' => $teachersWithPendingGrades . ' docentes con actividades pendientes',
                'icon' => 'fa-clipboard-check',
                'accent' => 'from-emerald-400 to-cyan-500',
                'action' => 'pending',
                'action_label' => 'Ver docentes pendientes',
            ],
            [
                'id' => 'at-risk',
                'label' => 'Riesgo Académico',
                'value' => number_format($atRiskStudents),
                'hint' => 'Alumnos con promedio menor a 60%',
                'icon' => 'fa-triangle-exclamation',
                'accent' => 'from-amber-400 to-rose-500',
                'action' => 'at-risk',
                'action_label' => 'Ver alumnos en riesgo',
            ],
        ];

        $gradePerformance = $this->gradePerformance($colegioId);
        $lowPerformingRooms = $this->buildLowPerformingRooms($colegioId);

        if ($colegioId) {
            $planificacionesRecientes = Planificacion::query()
                ->with(['user' => function ($query) use ($colegioId) {
                    $query->select('id', 'name')
                        ->where('colegio_id', $colegioId);
                }])
                ->where('planificacions.colegio_id', $colegioId)
                ->whereHas('user', function ($query) use ($colegioId) {
                    $query->where('role', 'profesor')
                        ->where('colegio_id', $colegioId);
                })
                ->latest('planificacions.created_at')
                ->limit(10)
                ->get();

            $actividadesRecientes = Activity::query()
                ->with([
                    'course' => function ($query) use ($colegioId) {
                        $query->select('id', 'subject_name', 'grade', 'section')
                            ->where('colegio_id', $colegioId);
                    },
                    'teacher' => function ($query) use ($colegioId) {
                        $query->select('id', 'name')
                            ->where('colegio_id', $colegioId);
                    },
                ])
                ->where('activities.colegio_id', $colegioId)
                ->whereHas('course', function ($query) use ($colegioId) {
                    $query->where('colegio_id', $colegioId);
                })
                ->whereHas('teacher', function ($query) use ($colegioId) {
                    $query->where('colegio_id', $colegioId);
                })
                ->latest('activities.created_at')
                ->limit(8)
                ->get();

            $profesores = User::query()
                ->where('users.role', 'profesor')
                ->where('users.colegio_id', $colegioId)
                ->with(['courses' => function ($query) use ($colegioId) {
                    $query->where('colegio_id', $colegioId)
                        ->orderBy('subject_name')
                        ->orderBy('grade')
                        ->orderBy('section');
                }])
                ->orderBy('users.name')
                ->get(['users.id', 'users.name', 'users.email']);
        }

        $planificacionesCountEsteMes = Planificacion::where('colegio_id', $colegioId)
            ->whereHas('user', fn ($query) => $query->where('role', 'profesor')->where('colegio_id', $colegioId))
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $planificacionesPendientes = Planificacion::where('colegio_id', $colegioId)
            ->whereHas('user', fn ($query) => $query->where('role', 'profesor')->where('colegio_id', $colegioId))
            ->where('status', 'pendiente')
            ->count();

        $planificacionesPendientesRevision = Planificacion::where('colegio_id', $colegioId)
            ->whereHas('user', fn ($query) => $query->where('role', 'profesor')->where('colegio_id', $colegioId))
            ->where('status', 'pendiente_revision')
            ->count();

        $totalTeachers = User::where('role', 'profesor')
            ->where('colegio_id', $colegioId)
            ->count();

        // ── Planificaciones estancadas (> 48h en pendiente) ──
        $deadline = now()->subHours(48);
        $stuckPlanificaciones = Planificacion::with('user:id,name')
            ->where('colegio_id', $colegioId)
            ->whereHas('user', fn ($query) => $query->where('role', 'profesor')->where('colegio_id', $colegioId))
            ->where('status', 'pendiente')
            ->where('created_at', '<', $deadline)
            ->latest()
            ->get();

        $stuckPlanificacionesConDepartamento = $stuckPlanificaciones
            ->groupBy(fn ($p) => $p->user?->name ?? 'Sin asignar')
            ->map(fn ($plans, $teacherName) => [
                'teacher_name' => $teacherName,
                'count' => $plans->count(),
                'oldest' => $plans->first()->created_at->diffForHumans(),
            ]);

        // ── Docentes sin actividad esta semana ──
        $weekStart = now()->startOfWeek();
        $teachersWithoutActivity = User::where('role', 'profesor')
            ->where('colegio_id', $colegioId)
            ->whereDoesntHave('courses.activities', function ($q) use ($weekStart, $colegioId) {
                $q->where('activities.colegio_id', $colegioId)
                    ->where('due_date', '>=', $weekStart);
            })
            ->get(['id', 'name']);

        // ── Combinar alertas ──
        $novaAlerts = collect();

        if ($planificacionesPendientesRevision > 0) {
            $novaAlerts->push([
                'type' => 'revision',
                'icon' => 'fa-rotate-right',
                'title' => 'Correcciones listas para revisar',
                'body' => "{$planificacionesPendientesRevision} planificación(es) corregida(s) por docentes esperan una nueva decisión.",
                'action_text' => 'Revisar correcciones',
                'action_url' => route('director.planificaciones', ['status' => 'pendiente_revision']),
            ]);
        }

        foreach ($stuckPlanificacionesConDepartamento as $item) {
            $novaAlerts->push([
                'type' => 'stuck',
                'icon' => 'fa-clock',
                'title' => "Planificaciones estancadas",
                'body' => "{$item['teacher_name']} tiene {$item['count']} planificación(es) sin revisar desde hace {$item['oldest']}.",
                'action_text' => "Revisar {$item['count']} planificaciones de {$item['teacher_name']}",
                'action_url' => route('director.planificaciones', ['status' => 'pendiente']),
            ]);
        }

        foreach ($teachersWithoutActivity as $teacher) {
            $novaAlerts->push([
                'type' => 'inactive',
                'icon' => 'fa-user-slash',
                'title' => 'Docente sin actividad',
                'body' => "{$teacher->name} no ha registrado actividades en el calendario esta semana.",
                'action_text' => 'Ver docentes',
                'action_url' => route('director.planificaciones'),
            ]);
        }

        $alertsWithContent = $novaAlerts->take(4);
        $stuckCount = $stuckPlanificaciones->count();
        $inactiveTeachersCount = $teachersWithoutActivity->count();

        $insightBootstrap = [
            'rooms' => $lowPerformingRooms,
            'inactive_teachers' => $teachersWithoutActivity
                ->map(fn (User $teacher) => [
                    'id' => $teacher->id,
                    'name' => $teacher->name,
                    'detail' => "{$teacher->name} no registró actividades con fecha en el calendario desde el inicio de esta semana (lunes). Conviene confirmar si está enfermo, de permiso o si necesita apoyo para cargar evaluaciones.",
                ])
                ->values()
                ->all(),
            'stuck_planificaciones' => $stuckPlanificacionesConDepartamento->values()->all(),
        ];

        return compact(
            'kpis',
            'gradePerformance',
            'lowPerformingRooms',
            'teachersWithPendingGrades',
            'planificacionesRecientes',
            'actividadesRecientes',
            'planificacionesCountEsteMes',
            'planificacionesPendientes',
            'planificacionesPendientesRevision',
            'totalTeachers',
            'profesores',
            'alertsWithContent',
            'stuckCount',
            'inactiveTeachersCount',
            'totalStudents',
            'totalCourses',
            'pendingInvites',
            'needsSetup',
            'insightBootstrap',
        );
    }

    public function profesores(Request $request): View
    {
        $user = $request->user();
        $colegioId = $user->colegio_id;

        $teachers = User::query()
            ->where('colegio_id', $colegioId)
            ->where('role', 'profesor')
            ->with(['courses' => function ($query) {
                $query->orderBy('subject_name')
                    ->orderBy('grade')
                    ->orderBy('section');
            }])
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'colegio_id']);

        return view('director.profesores', compact('teachers'));
    }

    public function atRiskStudents(Request $request): JsonResponse
    {
        $colegioId = (int) $request->user()->colegio_id;
        $rows = $this->atRiskStudentsQuery($colegioId)->limit(40)->get();

        return response()->json([
            'ok' => true,
            'threshold' => self::AT_RISK_THRESHOLD,
            'count' => $rows->count(),
            'students' => $rows->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => $row->name,
                'grade' => $row->grade,
                'section' => $row->section,
                'average' => round((float) $row->avg_pct, 1),
            ])->values(),
        ]);
    }

    public function pendingGrades(Request $request): JsonResponse
    {
        $colegioId = (int) $request->user()->colegio_id;
        $rows = $this->pendingGradeActivities($colegioId);
        $missingByActivity = $this->missingStudentNamesByActivity(
            $colegioId,
            $rows->pluck('activity_id')->map(fn ($id) => (int) $id)->unique()->values()->all()
        );

        $teachers = $rows->groupBy('teacher_id')->map(function (Collection $activities) use ($missingByActivity) {
            $first = $activities->first();
            $courses = $activities
                ->unique('course_id')
                ->map(fn ($row) => [
                    'course_id' => (int) $row->course_id,
                    'subject_name' => $row->subject_name,
                    'grade' => $row->grade,
                    'section' => $row->section,
                    'label' => trim($row->subject_name.' '.$row->grade.($row->section ? ' / '.$row->section : '')),
                    'missing_count' => (int) $activities->where('course_id', $row->course_id)->sum('missing_count'),
                ])
                ->values();

            return [
                'teacher_id' => (int) $first->teacher_id,
                'teacher_name' => $first->teacher_name,
                'missing_count' => (int) $activities->sum('missing_count'),
                'activity_count' => $activities->count(),
                'courses' => $courses,
                'activities' => $activities->map(function ($row) use ($missingByActivity) {
                    $activityId = (int) $row->activity_id;
                    $missingStudents = $missingByActivity[$activityId] ?? [];

                    return [
                        'activity_id' => $activityId,
                        'title' => $row->activity_title,
                        'course' => trim($row->subject_name.' '.$row->grade.($row->section ? ' / '.$row->section : '')),
                        'due_date' => $row->due_date ? (string) $row->due_date : null,
                        'enrolled' => (int) $row->enrolled,
                        'graded' => (int) $row->graded,
                        'missing_count' => (int) $row->missing_count,
                        'missing_students' => $missingStudents,
                        'summary' => (int) $row->graded.' de '.(int) $row->enrolled.' alumnos calificados',
                    ];
                })->values(),
            ];
        })->values();

        return response()->json([
            'ok' => true,
            'count' => $teachers->count(),
            'teachers' => $teachers,
        ]);
    }

    public function lowPerformingRooms(Request $request): JsonResponse
    {
        $rooms = $this->buildLowPerformingRooms((int) $request->user()->colegio_id);

        return response()->json([
            'ok' => true,
            'count' => count($rooms),
            'rooms' => $rooms,
            'note' => 'Salones ordenados por promedio ponderado (notas publicadas). Umbral de seguimiento: promedio por debajo de 65%.',
        ]);
    }

    public function schoolHealth(Request $request): JsonResponse
    {
        $health = $this->analytics->getSchoolHealth((int) $request->user()->colegio_id);

        return response()->json([
            'ok' => true,
            'message' => $health['message'] ?? '',
            'data' => $health['data'] ?? [],
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    private function atRiskStudentsQuery(int $colegioId)
    {
        $query = Grade::query()
            ->join('activities', 'grades.activity_id', '=', 'activities.id')
            ->join('students', 'grades.student_id', '=', 'students.id')
            ->join('courses', 'activities.course_id', '=', 'courses.id')
            ->where('activities.max_score', '>', 0)
            ->where('activities.colegio_id', $colegioId)
            ->where('courses.colegio_id', $colegioId)
            ->where('students.colegio_id', $colegioId)
            ->where('grades.colegio_id', $colegioId)
            ->groupBy('students.id', 'students.name', 'students.grade', 'students.section')
            ->selectRaw('
                students.id,
                students.name,
                students.grade,
                students.section,
                ' . self::WEIGHTED_AVERAGE_SQL . ' as avg_pct
            ')
            ->havingRaw('(' . self::WEIGHTED_AVERAGE_SQL . ') < ?', [self::AT_RISK_THRESHOLD])
            ->orderBy('avg_pct');

        $this->onlyPublishedGrades($query);

        return $query;
    }

    private function teachersWithPendingGrades(int $colegioId): int
    {
        return $this->pendingGradeActivities($colegioId)
            ->pluck('teacher_id')
            ->unique()
            ->count();
    }

    private function pendingGradeActivities(int $colegioId): Collection
    {
        return Activity::query()
            ->where('activities.type', '!=', 'clase')
            ->where('activities.colegio_id', $colegioId)
            ->where('activities.weight_percentage', '>', 0)
            ->join('courses', 'activities.course_id', '=', 'courses.id')
            ->where('courses.colegio_id', $colegioId)
            ->join('users as teachers', 'activities.teacher_id', '=', 'teachers.id')
            ->where('teachers.role', 'profesor')
            ->where('teachers.colegio_id', $colegioId)
            ->leftJoin('course_student', 'courses.id', '=', 'course_student.course_id')
            ->leftJoin('grades', function ($join) {
                $join->on('grades.activity_id', '=', 'activities.id')
                    ->on('grades.student_id', '=', 'course_student.student_id');
            })
            ->groupBy(
                'activities.id',
                'activities.teacher_id',
                'activities.title',
                'activities.due_date',
                'teachers.name',
                'courses.id',
                'courses.subject_name',
                'courses.grade',
                'courses.section'
            )
            ->havingRaw('COUNT(course_student.student_id) > COUNT(grades.id)')
            ->orderBy('teachers.name')
            ->orderBy('courses.subject_name')
            ->selectRaw('
                activities.id as activity_id,
                activities.teacher_id,
                activities.title as activity_title,
                activities.due_date as due_date,
                teachers.name as teacher_name,
                courses.id as course_id,
                courses.subject_name,
                courses.grade,
                courses.section,
                COUNT(course_student.student_id) as enrolled,
                COUNT(grades.id) as graded,
                COUNT(course_student.student_id) - COUNT(grades.id) as missing_count
            ')
            ->get();
    }

    /**
     * @param  array<int>  $activityIds
     * @return array<int, array<int, string>>
     */
    private function missingStudentNamesByActivity(int $colegioId, array $activityIds): array
    {
        if ($activityIds === []) {
            return [];
        }

        $rows = DB::table('activities')
            ->join('courses', 'activities.course_id', '=', 'courses.id')
            ->join('course_student', 'courses.id', '=', 'course_student.course_id')
            ->join('students', 'course_student.student_id', '=', 'students.id')
            ->leftJoin('grades', function ($join) {
                $join->on('grades.activity_id', '=', 'activities.id')
                    ->on('grades.student_id', '=', 'students.id');
            })
            ->where('activities.colegio_id', $colegioId)
            ->where('courses.colegio_id', $colegioId)
            ->where('students.colegio_id', $colegioId)
            ->whereIn('activities.id', $activityIds)
            ->whereNull('grades.id')
            ->orderBy('activities.id')
            ->orderBy('students.name')
            ->get(['activities.id as activity_id', 'students.name']);

        $grouped = [];
        foreach ($rows as $row) {
            $id = (int) $row->activity_id;
            $grouped[$id] ??= [];
            $grouped[$id][] = (string) $row->name;
        }

        return $grouped;
    }

    private function gradePerformance(int $colegioId): array
    {
        $labels = ['1ro', '2do', '3ro', '4to', '5to'];

        return collect($labels)->map(function (string $grade) use ($colegioId) {
            $avgQuery = Grade::query()
                ->join('activities', 'grades.activity_id', '=', 'activities.id')
                ->join('courses', 'activities.course_id', '=', 'courses.id')
                ->where('activities.max_score', '>', 0)
                ->where('activities.colegio_id', $colegioId)
                ->where('grades.colegio_id', $colegioId)
                ->where('courses.colegio_id', $colegioId)
                ->where('courses.grade', 'like', $grade . '%')
                ->selectRaw('
                    ' . self::WEIGHTED_AVERAGE_SQL . ' as avg_pct
                ');
            $this->onlyPublishedGrades($avgQuery);
            $avg = $avgQuery
                ->value('avg_pct');

            return [
                'grade' => $grade,
                'average' => $avg !== null ? round((float) $avg, 1) : 0,
                'has_data' => $avg !== null,
            ];
        })->values()->toArray();
    }

    private function buildLowPerformingRooms(int $colegioId): array
    {
        $query = Grade::query()
            ->join('activities', 'grades.activity_id', '=', 'activities.id')
            ->join('courses', 'activities.course_id', '=', 'courses.id')
            ->where('activities.max_score', '>', 0)
            ->where('activities.colegio_id', $colegioId)
            ->where('grades.colegio_id', $colegioId)
            ->where('courses.colegio_id', $colegioId)
            ->groupBy('courses.id', 'courses.subject_name', 'courses.grade', 'courses.section')
            ->selectRaw("
                courses.id,
                courses.subject_name,
                courses.grade,
                courses.section,
                " . self::WEIGHTED_AVERAGE_SQL . " as avg_pct,
                COUNT(grades.id) as grades_count
            ");
        $this->onlyPublishedGrades($query);

        return $query
            ->havingRaw('COUNT(grades.id) > 0')
            ->orderBy('avg_pct')
            ->limit(3)
            ->get()
            ->map(fn ($room) => [
                'course_id' => (int) $room->id,
                'subject_name' => $room->subject_name,
                'grade' => $room->grade,
                'section' => $room->section,
                'name' => trim($room->subject_name . ' · ' . $room->grade . ($room->section ? ' / ' . $room->section : '')),
                'average' => round((float) $room->avg_pct, 1),
                'grades_count' => (int) $room->grades_count,
                'recommendation' => $this->recommendationFor((float) $room->avg_pct),
                'severity' => (float) $room->avg_pct < 50 ? 'critical' : 'watch',
            ])
            ->toArray();
    }

    private function recommendationFor(float $average): string
    {
        return match (true) {
            $average < 50 => 'Intervención prioritaria y reunión de seguimiento esta semana.',
            $average < 65 => 'Reforzar contenidos base y revisar evidencias pendientes.',
            default => 'Monitorear tendencia y compartir buenas prácticas con docentes.',
        };
    }

    private function onlyPublishedGrades($query): void
    {
        if (Schema::hasColumn('grades', 'status')) {
            $query->where('grades.status', 'published');
        }
    }
}

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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DashboardController extends Controller
{
    private const WEIGHTED_AVERAGE_SQL = '
        CASE
            WHEN SUM(activities.max_score * activities.weight_percentage) > 0
            THEN SUM(grades.score * activities.weight_percentage) / SUM(activities.max_score * activities.weight_percentage) * 100
            ELSE AVG((grades.score / activities.max_score) * 100)
        END
    ';

    public function index(Request $request): View
    {
        $user = $request->user();
        $settings = $user->settings;
        $colegioId = $user->colegio_id;

        $planificacionesRecientes = collect();
        $actividadesRecientes = collect();
        $profesores = collect();

        $totalStudents = Student::where('colegio_id', $colegioId)->count();
        $totalCourses = Course::where('colegio_id', $colegioId)->count();
        $pendingInvites = TeacherInvite::where('colegio_id', $colegioId)
            ->whereNull('claimed_by')
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

        $atRiskStudentsQuery = Grade::query()
            ->join('activities', 'grades.activity_id', '=', 'activities.id')
            ->join('students', 'grades.student_id', '=', 'students.id')
            ->join('courses', 'activities.course_id', '=', 'courses.id')
            ->where('activities.max_score', '>', 0)
            ->where('activities.colegio_id', $colegioId)
            ->where('courses.colegio_id', $colegioId)
            ->where('students.colegio_id', $colegioId)
            ->where('grades.colegio_id', $colegioId)
            ->groupBy('students.id', 'students.name')
            ->selectRaw('
                students.id,
                students.name,
                ' . self::WEIGHTED_AVERAGE_SQL . ' as avg_pct
            ')
            ->havingRaw('(' . self::WEIGHTED_AVERAGE_SQL . ') < 60');
        $this->onlyPublishedGrades($atRiskStudentsQuery);
        $atRiskStudents = $atRiskStudentsQuery
            ->get()
            ->count();

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
                'label' => 'Matrícula Total',
                'value' => number_format($totalStudents),
                'hint' => 'Alumnos registrados en la institución',
                'icon' => 'fa-users',
                'accent' => 'from-cyan-400 to-blue-500',
            ],
            [
                'label' => 'Promedio Global',
                'value' => $globalAverage !== null ? round((float) $globalAverage, 1) . '%' : '—',
                'hint' => 'Promedio ponderado sobre registros de notas',
                'icon' => 'fa-chart-line',
                'accent' => 'from-violet-400 to-fuchsia-500',
            ],
            [
                'label' => 'Cumplimiento Docente',
                'value' => $teacherCompliance . '%',
                'hint' => $teachersWithPendingGrades . ' docentes con actividades pendientes',
                'icon' => 'fa-clipboard-check',
                'accent' => 'from-emerald-400 to-cyan-500',
            ],
            [
                'label' => 'Riesgo Académico',
                'value' => number_format($atRiskStudents),
                'hint' => 'Alumnos con promedio menor a 60%',
                'icon' => 'fa-triangle-exclamation',
                'accent' => 'from-amber-400 to-rose-500',
            ],
        ];

        $gradePerformance = $this->gradePerformance($colegioId);
        $lowPerformingRooms = $this->lowPerformingRooms($colegioId);

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

        $colegio = $colegioId ? Colegio::find($colegioId) : null;

        $institution = [
            'name' => $colegio?->name ?? $settings?->nombre_institucion ?? 'Aulasync',
            'period' => data_get($settings?->preferencias, 'periodo_academico', now()->year . '-' . now()->copy()->addYear()->year),
            'campuses' => data_get($settings?->preferencias, 'cantidad_sedes', 1),
            'invite_code_masked' => true,
            'has_codes_pin' => filled($colegio?->codes_pin),
        ];

        return view('director.dashboard', compact(
            'user',
            'settings',
            'colegio',
            'institution',
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
            'needsSetup'
        ));
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

    private function teachersWithPendingGrades(int $colegioId): int
    {
        return Activity::query()
            ->where('activities.type', '!=', 'clase')
            ->where('activities.colegio_id', $colegioId)
            ->where('activities.weight_percentage', '>', 0)
            ->join('courses', 'activities.course_id', '=', 'courses.id')
            ->where('courses.colegio_id', $colegioId)
            ->leftJoin('course_student', 'courses.id', '=', 'course_student.course_id')
            ->leftJoin('grades', function ($join) {
                $join->on('grades.activity_id', '=', 'activities.id')
                    ->on('grades.student_id', '=', 'course_student.student_id');
            })
            ->groupBy('activities.teacher_id', 'activities.id')
            ->havingRaw('COUNT(course_student.student_id) > COUNT(grades.id)')
            ->select('activities.teacher_id')
            ->get()
            ->pluck('teacher_id')
            ->unique()
            ->count();
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

    private function lowPerformingRooms(int $colegioId): array
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
                'name' => trim($room->subject_name . ' · ' . $room->grade . ($room->section ? ' / ' . $room->section : '')),
                'average' => round((float) $room->avg_pct, 1),
                'grades_count' => (int) $room->grades_count,
                'recommendation' => $this->recommendationFor((float) $room->avg_pct),
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

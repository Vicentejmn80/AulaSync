<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Course;
use App\Models\Grade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class HubController extends Controller
{
    // ─── Main hub view ───────────────────────────────────────────────────────

    public function index(Request $request): View
    {
        $teacher = auth()->user();
        $teacher->loadMissing('settings');

        $quotes = [
            'La educación es el arma más poderosa que puedes usar para cambiar el mundo. — Nelson Mandela',
            'Enseñar no es transferir conocimiento, sino crear posibilidades para su producción. — Paulo Freire',
            'La mejor manera de predecir el futuro es crearlo. — Peter Drucker',
            'El aprendizaje nunca agota la mente. — Leonardo da Vinci',
            'Un maestro afecta la eternidad; nunca puede saber dónde termina su influencia. — Henry Adams',
            'Educar es encender una llama, no llenar un recipiente. — Sócrates',
            'El éxito del alumno es el éxito del maestro.',
            'La creatividad es la inteligencia divirtiéndose. — Albert Einstein',
            'Aprender es descubrir que algo es posible. — Fritz Perls',
            'Cada alumno que iluminas es un futuro que transformas.',
        
        ];
        $dailyQuote = $quotes[abs(crc32(now()->toDateString())) % count($quotes)];

        // ── Optional: load from historial with plan_block filter ──
        $initialCourseId  = $request->query('course');
        $initialPlanBlock = $request->query('plan_block');

        return view('teacher.hub', compact('teacher', 'dailyQuote', 'initialCourseId', 'initialPlanBlock'));
    }

    // ─── Canvas API — Stats ──────────────────────────────────────────────────

    public function apiStats(): JsonResponse
    {
        $teacher = auth()->user();

        $courseIds = Course::where('teacher_id', $teacher->id)->pluck('id');
        $activityIds = Activity::whereIn('course_id', $courseIds)->pluck('id');

        $totalStudents = Course::whereIn('id', $courseIds)
            ->with('students')
            ->get()
            ->flatMap(fn ($c) => $c->students->pluck('id'))
            ->unique()
            ->count();

        $totalCourses    = $courseIds->count();
        $totalActivities = $activityIds->count();

        $avgGrade = null;
        if ($activityIds->isNotEmpty()) {
            $avgGrade = Grade::whereIn('activity_id', $activityIds)->avg('score');
        }

        // Activities due this week
        $activitiesThisWeek = Activity::whereIn('course_id', $courseIds)
            ->whereBetween('due_date', [now()->startOfWeek(), now()->endOfWeek()])
            ->count();

        // Next upcoming activity
        $nextActivity = Activity::whereIn('course_id', $courseIds)
            ->where('due_date', '>=', now()->toDateString())
            ->orderBy('due_date')
            ->with('course:id,subject_name,grade,section')
            ->first(['id', 'title', 'due_date', 'course_id']);

        // Climate: computed from recent grade average
        $climate = $this->computeClimate($avgGrade);

        return response()->json([
            'total_courses'        => $totalCourses,
            'total_students'       => $totalStudents,
            'total_activities'     => $totalActivities,
            'activities_this_week' => $activitiesThisWeek,
            'avg_grade'            => $avgGrade ? round($avgGrade, 1) : null,
            'climate'              => $climate,
            'next_activity'        => $nextActivity ? [
                'title'       => $nextActivity->title,
                'due_date'    => $nextActivity->due_date,
                'course_name' => optional($nextActivity->course)->subject_name . ' ' . optional($nextActivity->course)->grade,
            ] : null,
        ]);
    }

    // ─── Canvas API — Courses list ───────────────────────────────────────────

    public function apiCourses(): JsonResponse
    {
        $courses = Course::where('teacher_id', auth()->id())
            ->withCount(['students', 'activities'])
            ->with(['activities' => fn ($q) => $q->orderBy('due_date')->limit(3)])
            ->latest()
            ->get()
            ->map(fn ($c) => [
                'id'               => $c->id,
                'subject_name'     => $c->subject_name,
                'grade'            => $c->grade,
                'section'          => $c->section,
                'school_year'      => $c->school_year,
                'name'             => $c->subject_name . ' · ' . $c->grade . ($c->section ? ' / ' . $c->section : ''),
                'students_count'   => $c->students_count,
                'activities_count' => $c->activities_count,
            ]);

        return response()->json($courses);
    }

    // ─── Canvas API — Single course detail ───────────────────────────────────

    public function apiCourse(Course $course): JsonResponse
    {
        if ($course->teacher_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado.'], 403);
        }

        $course->load([
            'students' => fn ($q) => $q->orderBy('name'),
            'activities' => fn ($q) => $q->with('tareas')->orderBy('due_date'),
        ]);

        $activityIds = $course->activities->pluck('id');
        $gradeStats  = [];
        $gradeCounts = [];
        $studentAverages = [];
        if ($activityIds->isNotEmpty()) {
            $gradeStats = Grade::whereIn('activity_id', $activityIds)
                ->selectRaw('activity_id, AVG(score) as avg_score, COUNT(*) as total')
                ->groupBy('activity_id')
                ->pluck('avg_score', 'activity_id')
                ->toArray();

            $gradeCounts = Grade::whereIn('activity_id', $activityIds)
                ->selectRaw('activity_id, COUNT(*) as total')
                ->groupBy('activity_id')
                ->pluck('total', 'activity_id')
                ->toArray();

            $studentAverages = Grade::query()
                ->join('activities', 'grades.activity_id', '=', 'activities.id')
                ->where('activities.course_id', $course->id)
                ->groupBy('grades.student_id')
                ->selectRaw('grades.student_id, AVG(grades.score) as avg_score')
                ->pluck('avg_score', 'grades.student_id')
                ->toArray();
        }

        $totalStudents = $course->students->count();

        return response()->json([
            'id'           => $course->id,
            'subject_name' => $course->subject_name,
            'grade'        => $course->grade,
            'section'      => $course->section,
            'school_year'  => $course->school_year,
            'name'         => $course->subject_name . ' · ' . $course->grade . ($course->section ? ' / ' . $course->section : ''),
            'students'     => $course->students->map(fn ($s) => [
                'id'   => $s->id,
                'name' => $s->name,
                'avg_score' => isset($studentAverages[$s->id]) ? round((float) $studentAverages[$s->id], 2) : null,
            ]),
            'activities'   => $course->activities->map(fn ($a) => [
                'id'                => $a->id,
                'type'              => $a->type ?? 'actividad',
                'is_homework'       => (bool) $a->is_homework,
                'title'             => $a->title,
                'description'       => $a->description ?? '',
                'max_score'         => $a->max_score,
                'weight_percentage' => $a->weight_percentage,
                'due_date'          => $a->due_date instanceof \Carbon\Carbon
                                       ? $a->due_date->format('Y-m-d')
                                       : (string) $a->due_date,
                'nee_type'          => $a->nee_type,
                'nee_adaptation'    => $a->nee_adaptation,
                'avg_score'         => isset($gradeStats[$a->id]) ? round($gradeStats[$a->id], 1) : null,
                'graded_count'      => (int) ($gradeCounts[$a->id] ?? 0),
                'total_students'    => $totalStudents,
                'grades_url'        => route('teacher.grades.create', $a->id),
                'tareas'            => $a->tareas->map(fn ($t) => [
                    'id' => $t->id,
                    'titulo' => $t->titulo,
                    'descripcion' => $t->descripcion,
                    'fecha_entrega' => $t->fecha_entrega?->format('Y-m-d'),
                    'puntos' => $t->puntos,
                    'calificacion' => $t->calificacion,
                    'feedback' => $t->feedback,
                ])->values(),
            ]),
        ]);
    }

    // ─── Canvas API — Calendar ───────────────────────────────────────────────

    public function apiCalendar(Request $request): JsonResponse
    {
        $teacher     = auth()->user();
        $monthStr    = $request->query('month', now()->format('Y-m'));
        $planBlockId = $request->query('plan_block'); // optional filter

        try {
            $start = Carbon::parse($monthStr . '-01')->startOfMonth();
        } catch (\Exception) {
            $start = now()->startOfMonth();
        }
        $end = $start->copy()->endOfMonth();

        $courseIds = Course::where('teacher_id', $teacher->id)->pluck('id');

        // Course color palette (cycles)
        $palette = ['#7c3aed','#2563eb','#059669','#d97706','#dc2626','#0891b2','#7c3aed'];
        $courseColors = Course::whereIn('id', $courseIds)
            ->get(['id'])
            ->pluck('id')
            ->values()
            ->mapWithKeys(fn ($id, $idx) => [$id => $palette[$idx % count($palette)]])
            ->toArray();

        // Build query with optional plan_block filter
        $query = Activity::whereIn('course_id', $courseIds)
            ->whereBetween('due_date', [$start->toDateString(), $end->toDateString()])
            ->with(['course:id,subject_name,grade,section', 'tareas']);

        if ($planBlockId) {
            $query->where('plan_block_id', $planBlockId);
        }

        $activitiesByDay = $query->get()
            ->groupBy(fn ($a) => Carbon::parse($a->due_date)->format('Y-m-d'))
            ->map(fn ($group) => $group->map(fn ($a) => [
                'id'            => $a->id,
                'type'          => $a->type ?? 'actividad',
                'title'         => $a->title,
                'description'   => $a->description ?? '',
                'course_name'   => optional($a->course)->subject_name . ' ' . optional($a->course)->grade,
                'course_id'     => $a->course_id,
                'grade'         => optional($a->course)->grade,
                'section'       => optional($a->course)->section,
                'color'         => $a->is_homework ? '#0ea5e9' : ($courseColors[$a->course_id] ?? '#7c3aed'),
                'due_date'      => $a->due_date instanceof \Carbon\Carbon
                                   ? $a->due_date->format('Y-m-d')
                                   : (string) $a->due_date,
                'max_score'     => $a->max_score,
                'plan_block_id' => $a->plan_block_id,
                'grades_url'    => route('teacher.grades.create', $a->id),
                'is_homework'   => (bool) $a->is_homework,
                'nee_type'      => $a->nee_type,
                'nee_adaptation'=> $a->nee_adaptation,
                'tareas'        => $a->tareas->map(fn ($t) => [
                    'id' => $t->id,
                    'titulo' => $t->titulo,
                    'descripcion' => $t->descripcion,
                    'fecha_entrega' => $t->fecha_entrega?->format('Y-m-d'),
                    'puntos' => $t->puntos,
                    'calificacion' => $t->calificacion,
                    'feedback' => $t->feedback,
                ])->values(),
            ]));

        return response()->json([
            'month'             => $start->format('Y-m'),
            'month_name'        => $this->monthNameEs($start->month) . ' ' . $start->year,
            'days_in_month'     => $start->daysInMonth,
            'first_weekday'     => (int) $start->format('w'), // 0=Sun … 6=Sat
            'activities_by_day' => $activitiesByDay,
            'total_activities'  => $activitiesByDay->flatten(1)->count(),
            'plan_block_filter' => $planBlockId ? (int) $planBlockId : null,
        ]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function monthNameEs(int $month): string
    {
        $names = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio',
                  'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
        return $names[$month] ?? '';
    }

    private function computeClimate(?float $avg): array
    {
        if ($avg === null) {
            return ['label' => 'Sin datos', 'color' => 'slate', 'icon' => '📊', 'pct' => null];
        }
        return match (true) {
            $avg >= 17 => ['label' => 'Excelente',   'color' => 'emerald', 'icon' => '🌟', 'pct' => round(($avg / 20) * 100)],
            $avg >= 13 => ['label' => 'Bueno',        'color' => 'blue',    'icon' => '✅', 'pct' => round(($avg / 20) * 100)],
            $avg >= 10 => ['label' => 'Atención',     'color' => 'amber',   'icon' => '⚠️', 'pct' => round(($avg / 20) * 100)],
            default    => ['label' => 'Intervención', 'color' => 'red',     'icon' => '🚨', 'pct' => round(($avg / 20) * 100)],
        };
    }
}

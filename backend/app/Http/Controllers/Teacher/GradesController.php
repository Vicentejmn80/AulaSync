<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Grade;
use App\Models\Notification;
use App\Models\User;
use App\Services\GradeProcessingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Log;

class GradesController extends Controller
{
    public function __construct(private GradeProcessingService $gradeService) {}

    /**
     * List activities for the authenticated teacher.
     */
    public function index(): View|RedirectResponse
    {
        $activity = Activity::where('teacher_id', auth()->id())
            ->where('type', '!=', 'clase')
            ->latest()
            ->first();

        if (! $activity) {
            return redirect()->route('teacher.activities.index')
                ->with('info', 'No hay actividades calificables para cargar notas aún.');
        }

        return $this->create($activity);
    }

    /**
     * Show the grade entry form for a specific activity.
     */
    public function create(Activity $activity): View
    {
        abort_unless($activity->teacher_id === auth()->id(), 403);
        abort_unless((int) $activity->colegio_id === (int) auth()->user()->colegio_id, 403);

        $course = $activity->course;
        $studentIds = $course->students()->pluck('students.id')->toArray();

        $gradeMap = Grade::where('activity_id', $activity->id)
            ->whereIn('student_id', $studentIds)
            ->pluck('score', 'student_id')
            ->toArray();

        $statusMap = $this->supportsGradeWorkflow()
            ? Grade::where('activity_id', $activity->id)
                ->whereIn('student_id', $studentIds)
                ->pluck('status', 'student_id')
                ->toArray()
            : [];

        $weightedMap = [];
        if ($this->supportsCourseStudentAccumulated()) {
            $weightedMap = DB::table('course_student')
                ->where('course_id', $course->id)
                ->whereIn('student_id', $studentIds)
                ->pluck('nota_actual', 'student_id')
                ->map(fn ($v) => $v ?? 0.0)
                ->toArray();
        } else {
            $weightedMap = Grade::query()
                ->join('activities', 'grades.activity_id', '=', 'activities.id')
                ->where('activities.course_id', $course->id)
                ->whereIn('grades.student_id', $studentIds)
                ->where('activities.type', '!=', 'clase')
                ->groupBy('grades.student_id')
                ->selectRaw('grades.student_id, COALESCE(SUM((grades.score * activities.weight_percentage) / 100.0), 0) as weighted_total')
                ->pluck('weighted_total', 'grades.student_id')
                ->map(fn ($v) => (float) $v)
                ->toArray();
        }

        $students = $course->students()
            ->get()
            ->map(fn ($student) => [
                'id'             => $student->id,
                'name'           => $student->name,
                'existing_score' => isset($gradeMap[$student->id]) ? (float) $gradeMap[$student->id] : null,
                'is_published'   => ($statusMap[$student->id] ?? null) === 'published',
                'nota_actual'    => isset($weightedMap[$student->id]) ? round((float) $weightedMap[$student->id], 2) : null,
            ]);

        return view('teacher.grades.create', compact('activity', 'students'));
    }

    /**
     * Persist grades submitted from the table form (Supports AJAX).
     */
    public function store(Request $request, Activity $activity): JsonResponse|RedirectResponse
    {
        abort_unless($activity->teacher_id === auth()->id(), 403);
        abort_unless((int) $activity->colegio_id === (int) auth()->user()->colegio_id, 403);

        $data = $request->validate([
            'grades'                => ['required', 'array'],
            'grades.*.student_id'   => ['required', 'exists:students,id'],
            'grades.*.score'        => ['required', 'numeric', 'min:0', "max:{$activity->max_score}"],
            'grades.*.feedback'     => ['nullable', 'string', 'max:500'],
        ]);

        foreach ($data['grades'] as $entry) {
            Grade::updateOrCreate(
                ['activity_id' => $activity->id, 'student_id' => $entry['student_id']],
                [
                    'colegio_id' => $activity->colegio_id,
                    'score' => $entry['score'],
                    'feedback_text' => $entry['feedback'] ?? null,
                ]
            );
        }

        if ($request->wantsJson()) {
            $studentId = $data['grades'][0]['student_id'] ?? null;
            $acumulated = $this->updateCourseStudentAccumulated($activity->course_id, $studentId);

            return response()->json([
                'success' => true,
                'message' => 'Nota guardada.',
                'acumulated' => round($acumulated, 2),
            ]);
        }

        return redirect()->back()->with('success', 'Notas guardadas correctamente.');
    }

    /**
     * Payload para panel lateral de notas (Hub).
     */
    public function panel(Activity $activity): JsonResponse
    {
        abort_unless($activity->teacher_id === auth()->id(), 403);
        abort_unless((int) $activity->colegio_id === (int) auth()->user()->colegio_id, 403);

        $course = $activity->course()->with('students:id,name')->firstOrFail();
        $studentIds = $course->students->pluck('id');

        $gradeMap = Grade::where('activity_id', $activity->id)
            ->whereIn('student_id', $studentIds)
            ->pluck('score', 'student_id')
            ->toArray();
        $statusMap = $this->supportsGradeWorkflow()
            ? Grade::where('activity_id', $activity->id)
                ->whereIn('student_id', $studentIds)
                ->pluck('status', 'student_id')
                ->toArray()
            : [];

        $studentAverages = Grade::query()
            ->join('activities', 'grades.activity_id', '=', 'activities.id')
            ->where('activities.course_id', $course->id)
            ->whereIn('grades.student_id', $studentIds)
            ->groupBy('grades.student_id')
            ->selectRaw('grades.student_id, AVG(grades.score) as avg_score')
            ->pluck('avg_score', 'grades.student_id')
            ->toArray();
        $weightedMap = Grade::query()
            ->join('activities', 'grades.activity_id', '=', 'activities.id')
            ->where('activities.course_id', $course->id)
            ->whereIn('grades.student_id', $studentIds)
            ->where('activities.type', '!=', 'clase')
            ->groupBy('grades.student_id')
            ->selectRaw('grades.student_id, COALESCE(SUM((grades.score * activities.weight_percentage) / 100.0), 0) as weighted_total')
            ->pluck('weighted_total', 'grades.student_id')
            ->toArray();

        return response()->json([
            'success' => true,
            'activity' => [
                'id' => $activity->id,
                'title' => $activity->title,
                'max_score' => $activity->max_score,
                'course_id' => $course->id,
                'course_name' => $course->subject_name . ' · ' . $course->grade . ($course->section ? ' / ' . $course->section : ''),
            ],
            'students' => $course->students->map(fn ($student) => [
                'id' => $student->id,
                'name' => $student->name,
                'score' => isset($gradeMap[$student->id]) ? (float) $gradeMap[$student->id] : null,
                'avg_score' => isset($studentAverages[$student->id]) ? round((float) $studentAverages[$student->id], 2) : null,
                'status' => $statusMap[$student->id] ?? null,
                'nota_actual' => isset($weightedMap[$student->id]) ? round((float) $weightedMap[$student->id], 2) : 0.0,
            ])->values(),
        ]);
    }

    /**
     * Guardado instantáneo de una nota individual (blur/change).
     */
    public function quickStore(Request $request, Activity $activity): JsonResponse
    {
        abort_unless($activity->teacher_id === auth()->id(), 403);
        abort_unless((int) $activity->colegio_id === (int) auth()->user()->colegio_id, 403);
        $course = $activity->course()->firstOrFail();

        $data = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'score' => ['required', 'numeric', 'min:0', "max:{$activity->max_score}"],
        ]);

        $isEnrolled = $course
            ->students()
            ->where('students.id', $data['student_id'])
            ->exists();

        if (! $isEnrolled) {
            return response()->json([
                'success' => false,
                'error' => 'El alumno no pertenece al curso de esta actividad.',
            ], 422);
        }

        $payload = [
            'colegio_id' => $activity->colegio_id,
            'score' => $data['score'],
        ];
        if ($this->supportsGradeWorkflow()) {
            $payload['status'] = 'draft';
            $payload['published_at'] = null;
        }

        $grade = Grade::updateOrCreate(
            ['activity_id' => $activity->id, 'student_id' => $data['student_id']],
            $payload
        );

        $activityAvg = Grade::where('activity_id', $activity->id)->avg('score');
        $studentAvg = Grade::query()
            ->join('activities', 'grades.activity_id', '=', 'activities.id')
            ->where('activities.course_id', $course->id)
            ->where('grades.student_id', $data['student_id'])
            ->avg('grades.score');

        $gradedCount = Grade::where('activity_id', $activity->id)->count();
        $totalStudents = $course->students()->count();
        $accumulated = $this->updateCourseStudentAccumulated(
            courseId: (int) $course->id,
            studentId: (int) $data['student_id']
        );

        return response()->json([
            'success' => true,
            'message' => 'Nota guardada.',
            'activity_id' => $activity->id,
            'student_id' => (int) $data['student_id'],
            'score' => (float) $data['score'],
            'status' => $this->supportsGradeWorkflow() ? $grade->status : null,
            'activity_avg_score' => $activityAvg !== null ? round((float) $activityAvg, 2) : null,
            'student_avg_score' => $studentAvg !== null ? round((float) $studentAvg, 2) : null,
            'graded_count' => $gradedCount,
            'total_students' => $totalStudents,
            'nota_actual' => $accumulated,
        ]);
    }

    public function publish(Activity $activity): JsonResponse
    {
        abort_unless($activity->teacher_id === auth()->id(), 403);
        abort_unless((int) $activity->colegio_id === (int) auth()->user()->colegio_id, 403);
        if (! $this->supportsGradeWorkflow()) {
            return response()->json([
                'success' => false,
                'error' => 'El flujo de publicación requiere ejecutar migraciones pendientes.',
            ], 422);
        }

        $totalGrades = Grade::where('activity_id', $activity->id)->count();
        if ($totalGrades === 0) {
            return response()->json([
                'success' => false,
                'error' => 'No hay notas para publicar en esta actividad.',
            ], 422);
        }

        Grade::where('activity_id', $activity->id)->update([
            'colegio_id' => $activity->colegio_id,
            'status' => 'published',
            'published_at' => now(),
        ]);

        $directors = User::where('role', 'director')
            ->where('colegio_id', auth()->user()->colegio_id)
            ->get();

        foreach ($directors as $director) {
            Notification::create([
                'user_id' => $director->id,
                'colegio_id' => auth()->user()->colegio_id,
                'title' => 'Notas publicadas',
                'message' => "El docente " . (auth()->user()->name ?? '—') . " publicó {$totalGrades} nota(s) en «{$activity->title}».",
                'link' => null,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Notas publicadas correctamente.',
            'activity_id' => $activity->id,
            'published_count' => $totalGrades,
        ]);
    }

    private function updateCourseStudentAccumulated(int $courseId, int $studentId): float
    {
        if (! $this->supportsCourseStudentAccumulated()) {
            return 0.0;
        }

        $weighted = Grade::query()
            ->join('activities', 'grades.activity_id', '=', 'activities.id')
            ->where('activities.course_id', $courseId)
            ->where('grades.student_id', $studentId)
            ->where('activities.type', '!=', 'clase')
            ->selectRaw('COALESCE(SUM((grades.score * activities.weight_percentage) / 100.0), 0) as weighted_total')
            ->value('weighted_total');

        $weighted = round((float) $weighted, 2);

        DB::table('course_student')
            ->where('course_id', $courseId)
            ->where('student_id', $studentId)
            ->update([
                'nota_actual' => $weighted,
                'promedio_acumulado' => $weighted,
            ]);

        return $weighted;
    }

    private function supportsGradeWorkflow(): bool
    {
        return Schema::hasColumn('grades', 'status') && Schema::hasColumn('grades', 'published_at');
    }

    private function supportsCourseStudentAccumulated(): bool
    {
        return Schema::hasColumn('course_student', 'nota_actual')
            && Schema::hasColumn('course_student', 'promedio_acumulado');
    }

    /**
     * Parse a free-text / voice prompt and return structured grade suggestions via AJAX.
     */
    public function parseWithAI(Request $request, Activity $activity): JsonResponse
    {
        abort_unless($activity->teacher_id === auth()->id(), 403);

        $request->validate([
            'prompt' => ['required', 'string', 'max:2000'],
        ]);

        try {
            // Llamada al servicio de procesamiento de IA
            $suggestions = $this->gradeService->parseGradesFromText(
                $request->input('prompt'),
                $activity->max_score
            );

            // Verificación de resultados
            if (empty($suggestions)) {
                return response()->json([
                    'success' => false, 
                    'error' => 'La IA no pudo identificar nombres o notas en el texto proporcionado.'
                ], 422);
            }

            return response()->json([
                'success' => true,
                'suggestions' => $suggestions
            ]);

        } catch (\Exception $e) {
            Log::error('Error en parseWithAI: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Hubo un problema al procesar la solicitud con IA. Intente de nuevo.'
            ], 500);
        }
    }
}
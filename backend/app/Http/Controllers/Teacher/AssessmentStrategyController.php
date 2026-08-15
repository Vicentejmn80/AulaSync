<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Course;
use App\Models\CourseEvaluationPlan;
use App\Models\CourseEvaluationPlanItem;
use App\Models\Evaluation;
use App\Models\Rubric;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class AssessmentStrategyController extends Controller
{
    public function index(): View
    {
        $teacher = auth()->user();
        $courses = Course::where('teacher_id', $teacher->id)
            ->orderBy('subject_name')
            ->get(['id', 'subject_name', 'grade', 'section']);

        $plans = collect();
        $rubrics = collect();
        $evaluations = collect();

        if (Schema::hasTable('course_evaluation_plans')) {
            $plans = CourseEvaluationPlan::where('teacher_id', $teacher->id)
                ->with(['course:id,subject_name,grade,section', 'items.evaluation:id,title,status,mode'])
                ->latest()
                ->limit(30)
                ->get();
        }

        if (Schema::hasTable('rubrics')) {
            $rubrics = Rubric::where('teacher_id', $teacher->id)
                ->with(['course:id,subject_name,grade,section', 'criteria', 'evaluation:id,title'])
                ->latest()
                ->limit(40)
                ->get();
        }

        if (Schema::hasTable('evaluations')) {
            $evaluations = Evaluation::where('teacher_id', $teacher->id)
                ->with('course:id,subject_name,grade,section')
                ->latest()
                ->limit(50)
                ->get(['id', 'title', 'course_id', 'status', 'mode', 'topic', 'total_points', 'scheduled_at']);
        }

        return view('teacher.assessment-strategy.index', compact(
            'teacher',
            'courses',
            'plans',
            'rubrics',
            'evaluations'
        ));
    }

    public function generatePlan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'course_id' => 'required|integer',
            'program_text' => 'required|string|min:12|max:6000',
            'weeks' => 'nullable|integer|min:4|max:40',
            'balance' => 'nullable|in:balanced,process,product',
        ]);

        $teacher = auth()->user();
        $course = Course::where('id', $data['course_id'])->where('teacher_id', $teacher->id)->firstOrFail();
        $weeks = $data['weeks'] ?? 12;
        $balance = $data['balance'] ?? 'balanced';

        $apiKey = config('services.openai.key');
        if (empty($apiKey)) {
            return response()->json(['success' => true, 'plan' => $this->fallbackPlan($course, $weeks, $balance)]);
        }

        $balanceGuide = match ($balance) {
            'process' => 'Prioriza evaluación formativa (aprox 55-60%) y sumativa (40-45%).',
            'product' => 'Prioriza evaluación sumativa (aprox 65-70%) y formativa (30-35%).',
            default => 'Balance recomendado mundial: formativa 35-40% y sumativa 60-65%.',
        };

        $prompt = "Curso: {$course->subject_name} · {$course->grade} {$course->section}\n"
            . "Duración: {$weeks} semanas.\n"
            . "Programa: {$data['program_text']}\n"
            . "{$balanceGuide}\n"
            . 'Diseña un plan de evaluación de clase mundial alineado a outcomes. '
            . 'JSON: {"title":"","summary":"","formative_weight":0,"summative_weight":0,"items":[{"unit_name":"","assessment_type":"","category":"formative|summative","weight_percentage":0,"due_date":"YYYY-MM-DD","notes":"","learning_outcome":""}]} '
            . 'Los pesos deben sumar 100. Incluye quizzes, proyectos, examen y evidencia de proceso.';

        try {
            $response = Http::withToken($apiKey)
                ->timeout(60)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-4o-mini',
                    'temperature' => 0.3,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => 'Eres experto internacional en assessment design (constructive alignment). Responde solo JSON válido.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ]);

            if (! $response->successful()) {
                return response()->json(['success' => false, 'error' => 'No se pudo generar el plan con IA.'], 200);
            }

            $payload = json_decode((string) data_get($response->json(), 'choices.0.message.content', '{}'), true);
            if (! is_array($payload) || empty($payload['items'])) {
                return response()->json(['success' => false, 'error' => 'La IA devolvió un formato inválido.'], 200);
            }

            return response()->json(['success' => true, 'plan' => $payload]);
        } catch (\Throwable $e) {
            Log::error('Assessment plan generation error: '.$e->getMessage());
            return response()->json(['success' => false, 'error' => 'Error al contactar la IA.'], 200);
        }
    }

    public function storePlan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'course_id' => 'required|integer',
            'title' => 'required|string|max:255',
            'summary' => 'nullable|string',
            'formative_weight' => 'nullable|numeric|min:0|max:100',
            'summative_weight' => 'nullable|numeric|min:0|max:100',
            'status' => 'nullable|in:draft,published',
            'items' => 'required|array|min:1',
            'items.*.unit_name' => 'required|string|max:255',
            'items.*.assessment_type' => 'required|string|max:120',
            'items.*.category' => 'nullable|in:formative,summative',
            'items.*.weight_percentage' => 'required|numeric|min:0|max:100',
            'items.*.due_date' => 'nullable|date',
            'items.*.notes' => 'nullable|string|max:1000',
            'items.*.learning_outcome' => 'nullable|string|max:255',
            'items.*.evaluation_id' => 'nullable|integer',
        ]);

        $teacher = auth()->user();
        Course::where('id', $data['course_id'])->where('teacher_id', $teacher->id)->firstOrFail();

        $plan = CourseEvaluationPlan::create([
            'teacher_id' => $teacher->id,
            'course_id' => $data['course_id'],
            'title' => $data['title'],
            'summary' => $data['summary'] ?? null,
            'formative_weight' => $data['formative_weight'] ?? null,
            'summative_weight' => $data['summative_weight'] ?? null,
            'status' => $data['status'] ?? 'draft',
        ]);

        foreach ($data['items'] as $item) {
            $plan->items()->create([
                'evaluation_id' => $item['evaluation_id'] ?? null,
                'unit_name' => $item['unit_name'],
                'assessment_type' => $item['assessment_type'],
                'category' => $item['category'] ?? 'summative',
                'weight_percentage' => (float) $item['weight_percentage'],
                'due_date' => $item['due_date'] ?? null,
                'notes' => $item['notes'] ?? null,
                'learning_outcome' => $item['learning_outcome'] ?? null,
            ]);
        }

        return response()->json(['success' => true, 'plan' => $plan->fresh(['course', 'items.evaluation'])]);
    }

    public function attachEvaluation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'evaluation_id' => 'required|integer',
            'plan_id' => 'nullable|integer',
            'unit_name' => 'nullable|string|max:255',
            'weight_percentage' => 'nullable|numeric|min:1|max:100',
            'category' => 'nullable|in:formative,summative',
            'due_date' => 'nullable|date',
        ]);

        $teacher = auth()->user();
        $evaluation = Evaluation::where('id', $data['evaluation_id'])
            ->where('teacher_id', $teacher->id)
            ->firstOrFail();

        $plan = null;
        if (! empty($data['plan_id'])) {
            $plan = CourseEvaluationPlan::where('id', $data['plan_id'])
                ->where('teacher_id', $teacher->id)
                ->firstOrFail();
        } else {
            $courseId = $evaluation->course_id;
            if (! $courseId) {
                return response()->json(['success' => false, 'error' => 'La evaluación no tiene curso. Asígnale un curso primero.'], 200);
            }
            $plan = CourseEvaluationPlan::firstOrCreate(
                [
                    'teacher_id' => $teacher->id,
                    'course_id' => $courseId,
                    'title' => 'Plan de evaluación · '.$evaluation->course?->subject_name,
                ],
                [
                    'summary' => 'Plan generado automáticamente al sincronizar evaluaciones.',
                    'status' => 'draft',
                ]
            );
        }

        $existing = $plan->items()->where('evaluation_id', $evaluation->id)->first();
        if ($existing) {
            return response()->json(['success' => true, 'item' => $existing, 'plan' => $plan->fresh(['items.evaluation', 'course']), 'message' => 'Esta evaluación ya estaba en el plan.']);
        }

        $item = $plan->items()->create([
            'evaluation_id' => $evaluation->id,
            'unit_name' => $data['unit_name'] ?: ($evaluation->topic ?: 'Unidad sincronizada'),
            'assessment_type' => $evaluation->title,
            'category' => $data['category'] ?? 'summative',
            'weight_percentage' => (float) ($data['weight_percentage'] ?? 10),
            'due_date' => $data['due_date'] ?? optional($evaluation->scheduled_at)->toDateString(),
            'notes' => 'Sincronizado desde el módulo de Evaluaciones.',
            'learning_outcome' => null,
        ]);

        return response()->json([
            'success' => true,
            'item' => $item,
            'plan' => $plan->fresh(['items.evaluation', 'course']),
            'message' => 'Evaluación agregada al plan correctamente.',
        ]);
    }

    public function analyzeOverload(Request $request): JsonResponse
    {
        $data = $request->validate([
            'course_id' => 'required|integer',
            'items' => 'required|array|min:1',
            'items.*.due_date' => 'nullable|date',
            'items.*.assessment_type' => 'nullable|string|max:120',
            'items.*.category' => 'nullable|string|max:30',
            'items.*.weight_percentage' => 'nullable|numeric',
        ]);

        $teacher = auth()->user();
        Course::where('id', $data['course_id'])->where('teacher_id', $teacher->id)->firstOrFail();

        $weekly = [];
        $sumWeights = 0.0;
        $formative = 0.0;
        $summative = 0.0;

        foreach ($data['items'] as $item) {
            $weight = (float) ($item['weight_percentage'] ?? 0);
            $sumWeights += $weight;
            if (($item['category'] ?? 'summative') === 'formative') {
                $formative += $weight;
            } else {
                $summative += $weight;
            }
            if (! empty($item['due_date'])) {
                $week = Carbon::parse($item['due_date'])->startOfWeek()->toDateString();
                $weekly[$week] = ($weekly[$week] ?? 0) + 1;
            }
        }

        $warnings = [];
        foreach ($weekly as $week => $count) {
            if ($count >= 3) {
                $warnings[] = "Semana del {$week}: {$count} evaluaciones concentradas (riesgo de sobrecarga).";
            }
        }
        if (abs($sumWeights - 100) > 1.5) {
            $warnings[] = 'Los pesos suman '.round($sumWeights, 1).'%. Lo ideal es 100%.';
        }
        if ($formative > 0 && $formative < 20) {
            $warnings[] = 'La componente formativa es baja ('.round($formative, 1).'%). Estándares internacionales recomiendan más evidencia formativa.';
        }

        return response()->json([
            'success' => true,
            'warnings' => $warnings,
            'status' => count($warnings) > 0 ? 'warning' : 'ok',
            'balance' => [
                'formative' => round($formative, 1),
                'summative' => round($summative, 1),
                'total' => round($sumWeights, 1),
            ],
            'message' => count($warnings) > 0
                ? 'Se detectaron oportunidades de mejora en el plan.'
                : 'El plan está balanceado y alineado a buenas prácticas.',
        ]);
    }

    public function publishPlanToCalendar(CourseEvaluationPlan $plan): JsonResponse
    {
        abort_unless($plan->teacher_id === auth()->id(), 403);
        $plan->load('items');

        $created = 0;
        foreach ($plan->items as $item) {
            if (! $item->due_date) {
                continue;
            }
            $activity = Activity::firstOrCreate(
                [
                    'teacher_id' => $plan->teacher_id,
                    'course_id' => $plan->course_id,
                    'title' => $item->assessment_type.' · '.$item->unit_name,
                    'due_date' => $item->due_date,
                ],
                [
                    'description' => $item->notes ?: ('Evaluación planificada desde: '.$plan->title),
                    'max_score' => 20,
                    'weight_percentage' => $item->weight_percentage,
                    'type' => 'actividad',
                    'is_homework' => false,
                    'colegio_id' => auth()->user()->colegio_id,
                ]
            );
            if ($activity->wasRecentlyCreated) {
                $created++;
            }
        }

        return response()->json([
            'success' => true,
            'created' => $created,
            'message' => "Se publicaron {$created} eventos de evaluación en el calendario.",
        ]);
    }

    public function generateRubric(Request $request): JsonResponse
    {
        $data = $request->validate([
            'prompt' => 'required|string|min:10|max:4000',
            'type' => 'nullable|in:analytic,holistic,single_point',
            'course_id' => 'nullable|integer',
            'task_type' => 'nullable|string|max:120',
            'levels_count' => 'nullable|integer|min:3|max:5',
        ]);

        $type = $data['type'] ?? 'analytic';
        $levelsCount = $data['levels_count'] ?? 4;
        $apiKey = config('services.openai.key');

        if (empty($apiKey)) {
            return response()->json(['success' => true, 'rubric' => $this->fallbackRubric($type, $data['prompt'], $levelsCount)]);
        }

        $prompt = "Diseña una rúbrica escolar profesional.\n"
            . "Tipo: {$type}. Niveles: {$levelsCount}.\n"
            . 'Tipo de tarea: '.($data['task_type'] ?: 'evaluación abierta').".\n"
            . "Descripción: {$data['prompt']}\n"
            . 'JSON: {"title":"","description":"","type":"'.$type.'","levels":[{"key":"excellent","label":"Excelente","points":4},...],"total_points":100,"criteria":[{"name":"","weight_percentage":0,"descriptors":{"excellent":"","proficient":"","developing":"","beginning":""}}]}. '
            . 'Máximo 6 criterios. Descriptores observables, medibles y sin lenguaje vago. Los pesos deben sumar 100.';

        try {
            $response = Http::withToken($apiKey)
                ->timeout(60)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-4o-mini',
                    'temperature' => 0.3,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => 'Eres especialista en rubrics (AAC&U VALUE / analytic assessment). Responde solo JSON válido.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ]);

            if (! $response->successful()) {
                return response()->json(['success' => false, 'error' => 'No se pudo generar la rúbrica.'], 200);
            }

            $payload = json_decode((string) data_get($response->json(), 'choices.0.message.content', '{}'), true);
            if (! is_array($payload) || empty($payload['criteria'])) {
                return response()->json(['success' => false, 'error' => 'Formato de rúbrica inválido.'], 200);
            }

            return response()->json(['success' => true, 'rubric' => $payload]);
        } catch (\Throwable $e) {
            Log::error('Rubric generation error: '.$e->getMessage());
            return response()->json(['success' => false, 'error' => 'Error al contactar la IA.'], 200);
        }
    }

    public function storeRubric(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'course_id' => 'nullable|integer',
            'evaluation_id' => 'nullable|integer',
            'task_type' => 'nullable|string|max:120',
            'type' => 'required|in:analytic,holistic,single_point',
            'levels' => 'nullable|array',
            'total_points' => 'nullable|integer|min:1|max:1000',
            'status' => 'nullable|in:draft,published',
            'generated_by_ai' => 'nullable|boolean',
            'criteria' => 'required|array|min:1',
            'criteria.*.name' => 'required|string|max:255',
            'criteria.*.weight_percentage' => 'nullable|numeric|min:0|max:100',
            'criteria.*.descriptors' => 'nullable|array',
        ]);

        $teacher = auth()->user();
        if (! empty($data['course_id'])) {
            Course::where('id', $data['course_id'])->where('teacher_id', $teacher->id)->firstOrFail();
        }
        if (! empty($data['evaluation_id'])) {
            Evaluation::where('id', $data['evaluation_id'])->where('teacher_id', $teacher->id)->firstOrFail();
        }

        $rubric = Rubric::create([
            'teacher_id' => $teacher->id,
            'course_id' => $data['course_id'] ?? null,
            'evaluation_id' => $data['evaluation_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'task_type' => $data['task_type'] ?? null,
            'type' => $data['type'],
            'levels' => $data['levels'] ?? $this->defaultLevels(),
            'total_points' => $data['total_points'] ?? 100,
            'generated_by_ai' => (bool) ($data['generated_by_ai'] ?? false),
            'status' => $data['status'] ?? 'draft',
        ]);

        foreach (array_values($data['criteria']) as $index => $criterion) {
            $rubric->criteria()->create([
                'sort_order' => $index,
                'name' => $criterion['name'],
                'weight_percentage' => (float) ($criterion['weight_percentage'] ?? 0),
                'descriptors' => $criterion['descriptors'] ?? [],
            ]);
        }

        return response()->json(['success' => true, 'rubric' => $rubric->fresh(['criteria', 'course', 'evaluation'])]);
    }

    public function destroyRubric(Rubric $rubric): JsonResponse
    {
        abort_unless($rubric->teacher_id === auth()->id(), 403);
        $rubric->delete();
        return response()->json(['success' => true]);
    }

    public function destroyPlan(CourseEvaluationPlan $plan): JsonResponse
    {
        abort_unless($plan->teacher_id === auth()->id(), 403);
        $plan->delete();
        return response()->json(['success' => true]);
    }

    private function defaultLevels(): array
    {
        return [
            ['key' => 'excellent', 'label' => 'Excelente', 'points' => 4],
            ['key' => 'proficient', 'label' => 'Competente', 'points' => 3],
            ['key' => 'developing', 'label' => 'En desarrollo', 'points' => 2],
            ['key' => 'beginning', 'label' => 'Inicial', 'points' => 1],
        ];
    }

    private function fallbackPlan(Course $course, int $weeks, string $balance): array
    {
        $start = now()->addWeek();
        $items = [
            [
                'unit_name' => 'Unidad 1',
                'assessment_type' => 'Quiz diagnóstico',
                'category' => 'formative',
                'weight_percentage' => $balance === 'product' ? 10 : 15,
                'due_date' => $start->copy()->toDateString(),
                'notes' => 'Evidencia formativa de punto de partida.',
                'learning_outcome' => 'Identificar conocimientos previos del tema.',
            ],
            [
                'unit_name' => 'Unidad 2',
                'assessment_type' => 'Proyecto aplicado',
                'category' => 'summative',
                'weight_percentage' => $balance === 'process' ? 25 : 35,
                'due_date' => $start->copy()->addWeeks(max(2, (int) floor($weeks / 4)))->toDateString(),
                'notes' => 'Desempeño auténtico con rúbrica analítica.',
                'learning_outcome' => 'Aplicar conceptos en un producto contextualizado.',
            ],
            [
                'unit_name' => 'Unidad 3',
                'assessment_type' => 'Examen parcial',
                'category' => 'summative',
                'weight_percentage' => 25,
                'due_date' => $start->copy()->addWeeks(max(4, (int) floor($weeks / 2)))->toDateString(),
                'notes' => 'Evaluación sumativa de dominio conceptual.',
                'learning_outcome' => 'Demostrar comprensión de contenidos clave.',
            ],
            [
                'unit_name' => 'Cierre',
                'assessment_type' => 'Portafolio + presentación',
                'category' => 'summative',
                'weight_percentage' => $balance === 'process' ? 35 : 25,
                'due_date' => $start->copy()->addWeeks(max(6, $weeks - 2))->toDateString(),
                'notes' => 'Síntesis final con evidencia acumulada.',
                'learning_outcome' => 'Integrar y comunicar aprendizajes del curso.',
            ],
        ];

        $formative = collect($items)->where('category', 'formative')->sum('weight_percentage');
        $summative = collect($items)->where('category', 'summative')->sum('weight_percentage');

        return [
            'title' => 'Plan de evaluación · '.$course->subject_name,
            'summary' => "Plan alineado a outcomes para {$weeks} semanas, con balance formativa/sumativa internacional.",
            'formative_weight' => $formative,
            'summative_weight' => $summative,
            'items' => $items,
        ];
    }

    private function fallbackRubric(string $type, string $prompt, int $levelsCount): array
    {
        $levels = array_slice($this->defaultLevels(), 0, $levelsCount);

        return [
            'title' => 'Rúbrica de desempeño',
            'description' => 'Rúbrica generada a partir de: '.mb_substr($prompt, 0, 120),
            'type' => $type,
            'levels' => $levels,
            'total_points' => 100,
            'criteria' => [
                [
                    'name' => 'Comprensión del contenido',
                    'weight_percentage' => 30,
                    'descriptors' => [
                        'excellent' => 'Domina el contenido y lo aplica con precisión en contextos nuevos.',
                        'proficient' => 'Comprende el contenido y lo aplica correctamente en contextos conocidos.',
                        'developing' => 'Comprende parcialmente; requiere apoyo para aplicar ideas clave.',
                        'beginning' => 'Muestra comprensión limitada o fragmentada del contenido.',
                    ],
                ],
                [
                    'name' => 'Calidad de la evidencia',
                    'weight_percentage' => 25,
                    'descriptors' => [
                        'excellent' => 'Aporta evidencia sólida, pertinente y bien justificada.',
                        'proficient' => 'Aporta evidencia adecuada y mayormente pertinente.',
                        'developing' => 'Aporta evidencia incompleta o poco conectada a los criterios.',
                        'beginning' => 'La evidencia es escasa, irrelevante o ausente.',
                    ],
                ],
                [
                    'name' => 'Organización y claridad',
                    'weight_percentage' => 20,
                    'descriptors' => [
                        'excellent' => 'Estructura impecable, coherente y fácil de seguir.',
                        'proficient' => 'Organización clara con transición lógica entre ideas.',
                        'developing' => 'Organización irregular; algunas ideas se diluyen.',
                        'beginning' => 'Desorganizado o difícil de seguir.',
                    ],
                ],
                [
                    'name' => 'Uso del lenguaje académico',
                    'weight_percentage' => 15,
                    'descriptors' => [
                        'excellent' => 'Lenguaje preciso, formal y apropiado al nivel.',
                        'proficient' => 'Lenguaje correcto con vocabulario adecuado.',
                        'developing' => 'Lenguaje irregular; imprecisiones frecuentes.',
                        'beginning' => 'Lenguaje confuso o inadecuado al contexto.',
                    ],
                ],
                [
                    'name' => 'Autonomía y proceso',
                    'weight_percentage' => 10,
                    'descriptors' => [
                        'excellent' => 'Muestra alto nivel de autonomía y mejora continua.',
                        'proficient' => 'Trabaja de forma consistente con buena autonomía.',
                        'developing' => 'Requiere guía frecuente para completar tareas.',
                        'beginning' => 'Depende casi totalmente de apoyo externo.',
                    ],
                ],
            ],
        ];
    }
}

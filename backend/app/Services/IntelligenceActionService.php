<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Course;
use App\Models\User;
use App\Models\UserSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;

/**
 * Acciones concretas de "Inteligencia AulaSync": análisis de grupo y de
 * estudiante, detección de estudiantes que requieren atención, generación
 * de planificaciones/actividades/tareas (propuestas que el profesor revisa
 * antes de aplicar) e informes con datos reales.
 */
class IntelligenceActionService
{
    private const ACTIONS = [
        'analyze_group', 'analyze_student', 'detect_attention',
        'generate_planning', 'generate_activities', 'generate_tasks', 'generate_report',
    ];

    public const PROPOSAL_SESSION_KEY = 'nova_intelligence_proposal';

    public function __construct(
        private IntelligenceAnalyticsService $analytics,
        private IntelligenceApplicationService $application,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function run(User $teacher, string $action, array $params): array
    {
        if (! in_array($action, self::ACTIONS, true)) {
            return ['success' => false, 'type' => 'error', 'message' => 'Acción no disponible.'];
        }

        $courseId = isset($params['course_id']) ? (int) $params['course_id'] : null;

        return match ($action) {
            'analyze_group' => $this->analyzeGroup($teacher, $courseId),
            'analyze_student' => $this->analyzeStudent($teacher, $courseId, (string) ($params['student_name'] ?? '')),
            'detect_attention' => $this->detectAttention($teacher, $courseId),
            'generate_planning' => $this->generateProposal($teacher, $courseId, 'clase', (int) ($params['count'] ?? 4)),
            'generate_activities' => $this->generateProposal($teacher, $courseId, 'actividad', (int) ($params['count'] ?? 3)),
            'generate_tasks' => $this->generateProposal($teacher, $courseId, 'tarea', (int) ($params['count'] ?? 2)),
            'generate_report' => $this->generateReport($teacher, $courseId),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function analyzeGroup(User $teacher, ?int $courseId): array
    {
        return [
            'success' => true,
            'type' => 'insight',
            'action' => 'analyze_group',
            'payload' => $this->analytics->groupSummary($teacher, $courseId),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function analyzeStudent(User $teacher, ?int $courseId, string $studentName): array
    {
        if (trim($studentName) === '') {
            return ['success' => false, 'type' => 'error', 'action' => 'analyze_student', 'message' => 'Indica el nombre del alumno que quieres analizar.'];
        }

        $payload = $this->analytics->studentSummary($teacher, $courseId, $studentName);

        return [
            'success' => true,
            'type' => 'insight',
            'action' => 'analyze_student',
            'payload' => $payload,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detectAttention(User $teacher, ?int $courseId): array
    {
        return [
            'success' => true,
            'type' => 'insight',
            'action' => 'detect_attention',
            'payload' => ['students' => $this->analytics->atRisk($teacher, $courseId)],
        ];
    }

    /**
     * Genera una propuesta de planificación/actividades/tareas basada en el
     * contexto real del curso (temas recientes y rendimiento). Las fechas se
     * calculan en el servidor según los días de clase del profesor; la IA
     * solo propone títulos y descripciones.
     *
     * @return array<string, mixed>
     */
    private function generateProposal(User $teacher, ?int $courseId, string $type, int $count): array
    {
        $course = $this->resolveCourse($teacher, $courseId);

        if (! $course) {
            return ['success' => false, 'type' => 'error', 'message' => 'Selecciona uno de tus cursos para generar la propuesta.'];
        }

        $count = max(1, min($count, 8));

        $recent = Activity::where('teacher_id', $teacher->id)
            ->where('course_id', $course->id)
            ->orderByDesc('due_date')
            ->orderByDesc('id')
            ->limit(10)
            ->get(['title', 'type', 'due_date'])
            ->map(fn ($activity) => '- '.$activity->title.($activity->due_date ? ' ('.$activity->due_date->format('d/m/Y').')' : ''))
            ->implode("\n");

        $summary = $this->analytics->groupSummary($teacher, $course->id);
        $performanceLine = $summary['has_data']
            ? "Promedio del grupo: {$summary['performance']['avg_pct']}%. Temas con más dificultad: ".(
                collect($summary['difficulty'])->take(3)->pluck('title')->implode(', ') ?: 'sin datos suficientes'
            )
            : 'Aún no hay calificaciones registradas.';

        $items = $this->callGenerationModel($teacher, $course, $type, $count, $recent, $performanceLine);

        if ($items === null) {
            return ['success' => false, 'type' => 'error', 'message' => 'La generación inteligente no está disponible en este momento. Verifica la configuración de la IA e inténtalo de nuevo.'];
        }

        $dates = $this->nextClassDates($teacher, $count);

        $proposal = [
            'course_id' => (int) $course->id,
            'course_label' => trim($course->subject_name.' · '.$course->grade.($course->section ? ' / '.$course->section : '')),
            'type' => $type,
            'items' => $items,
            'dates' => $dates,
            'created_at' => now()->toIso8601String(),
        ];

        Session::put(self::PROPOSAL_SESSION_KEY, $proposal);

        return [
            'success' => true,
            'type' => 'proposal',
            'action' => 'generate_'.$type,
            'payload' => $proposal,
            'message' => 'Revisa la propuesta y confirma para agregarla a tu calendario.',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    private function callGenerationModel(User $teacher, Course $course, string $type, int $count, string $recent, string $performanceLine): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        $typeLabel = match ($type) {
            'clase' => 'sesiones de clase (planificación)',
            'tarea' => 'tareas para el hogar',
            default => 'actividades evaluables',
        };

        $sectionLabel = $course->section ? ' / '.$course->section : '';
        $courseLabel = $course->subject_name.' · '.$course->grade.$sectionLabel;

        $system = 'Eres un asistente de planificación docente para AulaSync. Propones contenido pedagógico realista y específico de la materia. No inventes datos de alumnos. Responde SOLO JSON: {"items": [{"title": "...", "description": "..."}]}. Títulos de máximo 80 caracteres y descripciones de máximo 300 caracteres, en español.';

        $user = <<<PROMPT
Profesor: {$teacher->name}
Curso: {$courseLabel}
Propón exactamente {$count} {$typeLabel}.

Temas y actividades recientes del curso (para continuar la secuencia, no repetir):
{$recent}

Rendimiento real del grupo: {$performanceLine}

Cada propuesta debe tener "title" (tema o título concreto) y "description" (qué se hará, breve y accionable).
PROMPT;

        try {
            $response = Http::timeout(60)
                ->withToken((string) config('services.openai.key'))
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => (string) config('services.openai.intelligence_model', 'gpt-4o-mini'),
                    'temperature' => 0.4,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $user],
                    ],
                ]);

            if ($response->failed()) {
                return null;
            }

            $data = json_decode((string) $response->json('choices.0.message.content', ''), true);

            if (! is_array($data)) {
                return null;
            }

            $items = [];
            foreach (array_slice((array) ($data['items'] ?? []), 0, $count) as $item) {
                $title = trim((string) ($item['title'] ?? ''));
                if ($title === '') {
                    continue;
                }
                $items[] = [
                    'title' => mb_substr($title, 0, 180),
                    'description' => mb_substr(trim((string) ($item['description'] ?? '')), 0, 1000),
                ];
            }

            return $items !== [] ? $items : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Calcula las próximas fechas de clase según los días configurados por
     * el profesor (UserSettings->dias_clase) o, como respaldo, una fecha
     * semanal a partir del próximo lunes.
     *
     * @return array<int, string>
     */
    private function nextClassDates(User $teacher, int $count): array
    {
        $settings = UserSettings::where('user_id', $teacher->id)->first();
        $days = is_array($settings?->dias_clase) ? $settings->dias_clase : [];

        $map = [
            'lunes' => 1, 'martes' => 2, 'miercoles' => 3, 'miércoles' => 3, 'jueves' => 4,
            'viernes' => 5, 'sabado' => 6, 'sábado' => 6, 'domingo' => 0,
            'monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4, 'friday' => 5, 'saturday' => 6, 'sunday' => 0,
        ];

        $weekdays = collect($days)
            ->map(fn ($day) => $map[mb_strtolower(trim((string) $day))] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->sort()
            ->all();

        $dates = [];
        $cursor = now()->startOfDay()->addDay();
        $guard = 0;

        if ($weekdays === []) {
            $nextMonday = now()->startOfWeek()->addWeek();

            for ($i = 0; $i < $count; $i++) {
                $dates[] = $nextMonday->copy()->addWeeks($i)->format('Y-m-d');
            }

            return $dates;
        }

        while (count($dates) < $count && $guard < 200) {
            if (in_array((int) $cursor->format('w'), $weekdays, true)) {
                $dates[] = $cursor->format('Y-m-d');
            }
            $cursor->addDay();
            $guard++;
        }

        return $dates;
    }

    /**
     * Informe del grupo construido SOLO con datos reales (determinista).
     *
     * @return array<string, mixed>
     */
    private function generateReport(User $teacher, ?int $courseId): array
    {
        $summary = $this->analytics->groupSummary($teacher, $courseId);

        if (! ($summary['has_data'] ?? false)) {
            return [
                'success' => true,
                'type' => 'report',
                'action' => 'generate_report',
                'markdown' => "## Informe del grupo\n\n".$summary['message'],
            ];
        }

        $lines = [
            '## Informe del grupo — '.$summary['label'],
            '',
            '**Fecha:** '.now()->format('d/m/Y'),
            '',
            '### Rendimiento',
            "- Promedio general: **{$summary['performance']['avg_pct']}%** ({$summary['performance']['graded_students']} alumnos evaluados)",
            '- Distribución: '.$summary['performance']['distribution']['high'].' en alto rendimiento, '.$summary['performance']['distribution']['mid'].' en desarrollo, '.$summary['performance']['distribution']['low'].' requieren apoyo.',
        ];

        if ($summary['performance']['top'] !== []) {
            $lines[] = '- Destacados: '.collect($summary['performance']['top'])->map(fn ($row) => "{$row['name']} ({$row['avg_pct']}%)")->implode(', ');
        }

        if ($summary['trend'] !== []) {
            $trendLine = collect($summary['trend'])->map(fn ($week) => $week['avg_pct'].'%')->implode(' → ');
            $lines[] = '';
            $lines[] = '### Tendencia semanal';
            $lines[] = '- '.$trendLine;
        }

        if ($summary['attendance']['rate'] !== null) {
            $lines[] = '';
            $lines[] = '### Asistencia (30 días)';
            $lines[] = "- Presencia: {$summary['attendance']['rate']}% ({$summary['attendance']['absent']} ausencias)";
            if ($summary['attendance']['top_absentees'] !== []) {
                $lines[] = '- Mayores ausencias: '.collect($summary['attendance']['top_absentees'])->map(fn ($row) => "{$row['name']} ({$row['absences']})")->implode(', ');
            }
        }

        if ($summary['attention'] !== []) {
            $lines[] = '';
            $lines[] = '### Estudiantes que requieren atención';
            foreach ($summary['attention'] as $student) {
                $lines[] = "- **{$student['name']}**: ".implode('; ', $student['reasons']);
            }
        }

        if ($summary['difficulty'] !== []) {
            $lines[] = '';
            $lines[] = '### Áreas con dificultades';
            foreach ($summary['difficulty'] as $activity) {
                $lines[] = "- {$activity['title']} ({$activity['subject']}): {$activity['avg_pct']}% de promedio";
            }
        }

        if ($summary['detected'] !== []) {
            $lines[] = '';
            $lines[] = '### Información detectada en documentos';
            foreach ($summary['detected'] as $observation) {
                $lines[] = '- '.$observation;
            }
        }

        $lines[] = '';
        $lines[] = '### Recomendaciones';
        foreach ($summary['recommendations'] as $tip) {
            $lines[] = '- '.$tip;
        }

        return [
            'success' => true,
            'type' => 'report',
            'action' => 'generate_report',
            'markdown' => implode("\n", $lines),
        ];
    }

    /**
     * Aplica la propuesta pendiente (server-canonical) tras la confirmación.
     * El profesor puede ajustar las fechas sugeridas antes de aplicar.
     *
     * @param  array<int, int>  $selectedIndices
     * @param  array<int, string|null>  $dates
     * @return array<string, mixed>
     */
    public function applyProposal(User $teacher, array $selectedIndices, array $dates = []): array
    {
        $proposal = Session::pull(self::PROPOSAL_SESSION_KEY);

        if (! is_array($proposal)) {
            return ['success' => false, 'type' => 'error', 'message' => 'No encontré una propuesta pendiente en esta sesión. Genera una nueva e inténtalo de nuevo.'];
        }

        foreach ($dates as $index => $date) {
            if ($date !== null && trim((string) $date) !== '') {
                $proposal['dates'][$index] = date('Y-m-d', strtotime((string) $date));
            }
        }

        return $this->application->applyProposal($teacher, $proposal, $selectedIndices);
    }

    private function resolveCourse(User $teacher, ?int $courseId): ?Course
    {
        $query = Course::where('teacher_id', $teacher->id)
            ->when($teacher->colegio_id, fn ($q) => $q->where('colegio_id', $teacher->colegio_id));

        if ($courseId) {
            return $query->where('id', $courseId)->first();
        }

        $courses = $query->orderBy('subject_name')->get();

        return $courses->count() === 1 ? $courses->first() : null;
    }

    private function enabled(): bool
    {
        if (! config('services.openai.intelligence_enabled', true)) {
            return false;
        }

        if (app()->environment('testing') && ! config('services.openai.intelligence_test_enabled', false)) {
            return false;
        }

        $key = trim((string) config('services.openai.key'));

        return $key !== '' && ! str_contains($key, 'your_openai');
    }
}

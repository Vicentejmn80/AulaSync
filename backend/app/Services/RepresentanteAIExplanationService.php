<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Course;
use App\Models\Evaluation;
use App\Models\Student;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RepresentanteAIExplanationService
{
    private const PROMPT_ACTIVIDAD = 'Eres asistente para familias de AulaSync. Explica al representante qué debe hacer el alumno basándote EXCLUSIVAMENTE en la información proporcionada. Usa lenguaje sencillo y cercano, en español. No inventes tareas, fechas, requisitos ni materiales. No agregues pasos que no estén en los datos. Si falta información, dilo. Máximo 120 palabras. Devuelve solo la explicación, sin saludo ni pregunta de seguimiento.';

    private const PROMPT_EVALUACION = 'Eres asistente para familias. Explica qué debe estudiar y preparar el alumno para esta evaluación usando EXCLUSIVAMENTE la información proporcionada (título, tema, instrucciones, fecha, materia). No inventes contenidos, fechas ni formato. Usa lenguaje claro y breve. Máximo 120 palabras. Solo la explicación.';

    private const PROMPT_CALENDARIO = 'Resume en español, en máximo 130 palabras, qué tiene pendiente el alumno esta semana basándote EXCLUSIVAMENTE en la lista de eventos proporcionada (tareas, evaluaciones, clases). Menciona fechas y materias reales. No inventes actividades. Si no hay pendientes, dilo. Tono cercano y útil. Solo el resumen.';

    private const PROMPT_CALIFICACIONES = 'Explica en español, de forma sencilla y breve (máx 130 palabras), el progreso académico del alumno usando EXCLUSIVAMENTE los promedios y calificaciones proporcionados. Indica fortalezas y aspectos a mejorar sin inventar notas. No uses jerga técnica. Si no hay calificaciones, dilo. Solo la explicación.';

    private const PROMPT_ASISTENCIA = 'Explica en español, de forma breve y clara (máx 120 palabras), el estado de asistencia del alumno usando EXCLUSIVAMENTE los datos proporcionados (presentes, ausentes, tardanzas, porcentaje, por materia si aplica). No inventes fechas ni motivos. Si la asistencia es buena, reconócelo; si hay ausencias, sugiere hablar con el docente sin alarmar. Solo la explicación.';

    public function __construct(
        private RepresentanteDashboardService $dashboard,
        private AttendanceSummaryService $attendanceSummary,
        private ReportCardService $reportCard,
    ) {
    }

    public function explainActivity(Student $student, Activity $activity): array
    {
        $course = $activity->course;
        $teacher = $this->sanitize($course?->teacher?->name ?? $activity->teacher?->name ?? 'Docente');
        $materia = $this->sanitize($course?->subject_name ?? 'Materia');
        $title = $this->sanitize($activity->title);
        $desc = $this->sanitize($activity->description);
        $notes = $this->sanitize($activity->notes);
        $due = $activity->due_date ? $activity->due_date->format('d/m/Y') : '';
        $weight = $activity->weight_percentage !== null ? (string) $activity->weight_percentage : '';
        $max = $activity->max_score !== null ? (string) $activity->max_score : '';

        $hasContent = $title !== '' || $desc !== '' || $notes !== '';
        if (! $hasContent) {
            return $this->response('Sin información', 'No hay suficiente información disponible para esta actividad.', 'activity_explanation', true);
        }

        $context = collect([
            $materia !== '' ? "Materia: {$materia}".($course?->grade ? " {$course->grade}".($course->section ? " / {$course->section}" : '') : '') : null,
            $teacher !== '' ? "Docente: {$teacher}" : null,
            $title !== '' ? "Actividad: {$title}" : null,
            $due !== '' ? "Fecha de entrega: {$due}" : null,
            $desc !== '' ? "Descripción: {$desc}" : null,
            $notes !== '' ? "Notas: {$notes}" : null,
            $weight !== '' ? "Peso: {$weight}%" : null,
            $max !== '' ? "Puntaje máximo: {$max}" : null,
        ])->filter()->implode("\n");

        $content = $this->callLLM(self::PROMPT_ACTIVIDAD, $context, 220);
        if ($content !== null) {
            return $this->response('Así debe hacerlo', $content, 'activity_explanation', true);
        }

        $fallback = $title !== '' ? "La actividad es \"{$title}\"".($desc !== '' ? ": {$desc}" : '.') : 'Revisa los detalles de la actividad.';
        if ($due !== '') {
            $fallback .= " Debe entregarse el {$due}.";
        }
        if ($notes !== '') {
            $fallback .= " Nota: {$notes}";
        }

        return $this->response('Así debe hacerlo', Str::limit($fallback, 700), 'activity_explanation', true);
    }

    public function explainEvaluation(Student $student, Evaluation $evaluation): array
    {
        $course = $evaluation->course;
        $materia = $this->sanitize($course?->subject_name ?? 'Materia');
        $title = $this->sanitize($evaluation->title);
        $topic = $this->sanitize($evaluation->topic);
        $desc = $this->sanitize($evaluation->description);
        $instr = $this->sanitize($evaluation->instructions);
        $date = $evaluation->scheduled_at ? $evaluation->scheduled_at->format('d/m/Y H:i') : '';
        $points = $evaluation->total_points !== null ? (string) $evaluation->total_points : '';

        $hasContent = $title !== '' || $topic !== '' || $desc !== '' || $instr !== '';
        if (! $hasContent) {
            return $this->response('Sin información', 'No hay suficiente información disponible para esta evaluación.', 'evaluation_explanation', true);
        }

        $context = collect([
            $materia !== '' ? "Materia: {$materia}" : null,
            $title !== '' ? "Evaluación: {$title}" : null,
            $topic !== '' ? "Tema: {$topic}" : null,
            $date !== '' ? "Fecha: {$date}" : null,
            $desc !== '' ? "Descripción: {$desc}" : null,
            $instr !== '' ? "Instrucciones: {$instr}" : null,
            $points !== '' ? "Puntaje total: {$points}" : null,
        ])->filter()->implode("\n");

        $content = $this->callLLM(self::PROMPT_EVALUACION, $context, 220);
        if ($content !== null) {
            return $this->response('Qué debe estudiar', $content, 'evaluation_explanation', true);
        }

        $fallback = $title !== '' ? "Evaluación \"{$title}\"".($topic !== '' ? " sobre {$topic}" : '') : 'Evaluación programada';
        if ($date !== '') {
            $fallback .= " el {$date}";
        }
        $fallback .= '.';
        if ($desc !== '') {
            $fallback .= " {$desc}";
        }
        if ($instr !== '') {
            $fallback .= " Instrucciones: {$instr}";
        }

        return $this->response('Qué debe estudiar', Str::limit($fallback, 700), 'evaluation_explanation', true);
    }

    public function summarizeWeek(Student $student, ?string $month = null): array
    {
        $calendar = $this->dashboard->calendar($student, $month);
        $events = collect($calendar['events'] ?? [])->flatten(1);

        $start = now()->startOfDay();
        $end = now()->copy()->addDays(6)->endOfDay();
        $weekEvents = $events->filter(function ($ev) use ($start, $end) {
            if (empty($ev['date'])) {
                return false;
            }
            try {
                $d = \Illuminate\Support\Carbon::parse($ev['date']);
            } catch (\Throwable) {
                return false;
            }

            return $d->between($start, $end);
        })->values();

        if ($weekEvents->isEmpty()) {
            return $this->response('Esta semana', 'No hay actividades ni evaluaciones pendientes esta semana.', 'week_summary', true);
        }

        $lines = $weekEvents->take(12)->map(function ($ev) {
            $date = $this->sanitize($ev['date'] ?? '');
            $t = $this->sanitize($ev['title'] ?? 'Actividad');
            $type = $this->sanitize($ev['type_label'] ?? $ev['type'] ?? '');
            $course = $this->sanitize($ev['course'] ?? '');

            return trim("{$date} · {$type} · {$t}".($course !== '' ? " ({$course})" : ''));
        })->implode("\n");

        $context = "Eventos de la semana ({$start->format('d/m')} al {$end->format('d/m/Y')}):\n".$lines;

        $content = $this->callLLM(self::PROMPT_CALENDARIO, $context, 260);
        if ($content !== null) {
            return $this->response('Resumen de la semana', $content, 'week_summary', true);
        }

        $fallback = $weekEvents->count().' actividades esta semana: '.$weekEvents->pluck('title')->take(5)->implode(', ').'.';

        return $this->response('Resumen de la semana', Str::limit($fallback, 700), 'week_summary', true);
    }

    public function explainGrades(Student $student, ?Course $course = null): array
    {
        if ($course) {
            $detail = $this->dashboard->subjectDetail($student, $course);
            $avg = $detail['average'];
            $history = collect($detail['history'] ?? [])->take(6);
            $items = collect($detail['items'] ?? [])->take(8);

            if ($avg === null && $history->isEmpty() && $items->isEmpty()) {
                return $this->response('Su progreso', 'Aún no hay calificaciones publicadas para esta materia.', 'grades_explanation', true);
            }

            $lines = [];
            $lines[] = "Materia: ".$this->sanitize($course->subject_name)." {$course->grade}".($course->section ? " / {$course->section}" : '');
            $lines[] = $avg !== null ? "Promedio: {$avg}" : "Promedio: sin datos";
            if ($history->isNotEmpty()) {
                $lines[] = "Historial: ".$history->map(fn ($h) => $this->sanitize($h['label'] ?? '')." ".($h['score'] ?? '')."/".($h['max_score'] ?? ''))->implode(', ');
            }
            if ($items->isNotEmpty()) {
                $lines[] = "Actividades: ".$items->map(fn ($i) => $this->sanitize($i['title'] ?? '')." ".($i['score'] !== null ? $i['score']."/".($i['max_score'] ?? '') : 'pendiente'))->take(5)->implode('; ');
            }

            $context = implode("\n", $lines);
            $content = $this->callLLM(self::PROMPT_CALIFICACIONES, $context, 260);
            if ($content !== null) {
                return $this->response('Su progreso', $content, 'grades_explanation', true);
            }

            $fallback = $avg !== null ? "Promedio en {$course->subject_name}: {$avg}. " : "Sin promedio aún en {$course->subject_name}. ";
            $fallback .= $history->isNotEmpty() ? "Últimas notas: ".$history->pluck('score')->implode(', ')."." : "";

            return $this->response('Su progreso', Str::limit(trim($fallback), 700), 'grades_explanation', true);
        }

        $summary = $this->dashboard->summary($student);
        $avg = $summary['average']['value'] ?? null;
        $pending = $summary['pending_tasks']['count'] ?? 0;
        $subjects = $this->dashboard->subjects($student);

        if ($avg === null && empty($subjects)) {
            return $this->response('Su progreso', 'Aún no hay información de calificaciones disponible.', 'grades_explanation', true);
        }

        $lines = [];
        $lines[] = $avg !== null ? "Promedio general: {$avg}" : "Promedio general: sin datos";
        $lines[] = "Materias: ".collect($subjects)->map(fn ($s) => $this->sanitize($s['name'] ?? '')." ".($s['average'] ?? '—'))->implode(', ');
        if ($pending > 0) {
            $lines[] = "Tareas pendientes: {$pending}";
        }

        $context = implode("\n", $lines);
        $content = $this->callLLM(self::PROMPT_CALIFICACIONES, $context, 260);
        if ($content !== null) {
            return $this->response('Su progreso', $content, 'grades_explanation', true);
        }

        $fallback = $avg !== null ? "Promedio general: {$avg}. " : "";
        $fallback .= count($subjects)." materias inscritas.";

        return $this->response('Su progreso', Str::limit(trim($fallback), 700), 'grades_explanation', true);
    }

    public function explainAttendance(Student $student, ?Course $course = null): array
    {
        if ($course) {
            $stats = $this->attendanceSummary->percentForStudentInCourse($student, $course);
            if (($stats['total'] ?? 0) === 0) {
                return $this->response('Su asistencia', 'Aún no hay registros de asistencia para esta materia.', 'attendance_explanation', true);
            }

            $context = "Materia: ".$this->sanitize($course->subject_name)."\n"
                ."Asistencia: {$stats['percentage']}% ({$stats['present']} presentes, {$stats['absent']} ausencias, {$stats['tardy']} tardanzas, total {$stats['total']})";

            $content = $this->callLLM(self::PROMPT_ASISTENCIA, $context, 220);
            if ($content !== null) {
                return $this->response('Su asistencia', $content, 'attendance_explanation', true);
            }

            $fallback = "En {$course->subject_name}: {$stats['percentage']}% de asistencia ({$stats['present']} presentes, {$stats['absent']} ausencias, {$stats['tardy']} tardanzas).";

            return $this->response('Su asistencia', $fallback, 'attendance_explanation', true);
        }

        $summary = $this->dashboard->summary($student);
        $att = $summary['attendance'] ?? [];
        $percent = $att['percent'] ?? null;
        $abs = $att['absences'] ?? 0;
        $tardy = $att['tardies'] ?? 0;
        $byCourse = $att['by_course'] ?? [];

        if ($percent === null && $abs === 0 && $tardy === 0 && empty($byCourse)) {
            return $this->response('Su asistencia', 'Aún no hay registros de asistencia disponibles.', 'attendance_explanation', true);
        }

        $lines = [];
        $lines[] = $percent !== null ? "Asistencia general: {$percent}%" : "Asistencia general: sin datos";
        $lines[] = "Ausencias este mes: {$abs} · Tardanzas: {$tardy}";
        if (! empty($byCourse)) {
            $lines[] = "Por materia: ".collect($byCourse)->take(4)->map(fn ($c) => $this->sanitize($c['course'] ?? '')." {$c['percentage']}%")->implode(', ');
        }

        $context = implode("\n", $lines);
        $content = $this->callLLM(self::PROMPT_ASISTENCIA, $context, 220);
        if ($content !== null) {
            return $this->response('Su asistencia', $content, 'attendance_explanation', true);
        }

        $fallback = $percent !== null ? "Asistencia general: {$percent}%. " : "";
        $fallback .= "{$abs} ausencias y {$tardy} tardanzas este mes.";

        return $this->response('Su asistencia', trim($fallback), 'attendance_explanation', true);
    }

    private function callLLM(string $system, string $user, int $maxTokens = 220): ?string
    {
        $key = config('services.openai.key');
        if (empty($key)) {
            return null;
        }

        try {
            $model = config('services.openai.intelligence_model', config('services.openai.director_model', 'gpt-4o-mini'));
            $response = Http::withToken($key)
                ->timeout(12)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $model,
                    'temperature' => 0.2,
                    'max_tokens' => $maxTokens,
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $user],
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('IA representante fallo', ['status' => $response->status(), 'body' => Str::limit($response->body(), 500)]);

                return null;
            }

            $content = trim((string) data_get($response->json(), 'choices.0.message.content', ''));
            if ($content === '') {
                return null;
            }

            return Str::limit($content, 900);
        } catch (\Throwable $e) {
            Log::warning('IA representante error', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function sanitize(?string $value): string
    {
        $text = trim(strip_tags((string) $value));
        $text = preg_replace('/\s+/', ' ', $text);

        return Str::limit($text, 600, '');
    }

    private function response(string $title, string $content, string $action, bool $success): array
    {
        return [
            'success' => $success,
            'title' => $title,
            'content' => Str::limit(trim($content), 900, ''),
            'action' => $action,
        ];
    }
}

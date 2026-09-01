<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Student;
use App\Models\Tarea;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class LessonAiService
{
    /**
     * @return array<int, array{titulo:string,descripcion:string,enfoque:string}>
     */
    public function generateTaskProposals(Activity $activity): array
    {
        $fallback = $this->fallbackTaskProposals($activity);
        $apiKey = trim((string) config('services.openai.key', env('OPENAI_API_KEY')));
        if ($apiKey === '' || str_contains($apiKey, 'your_openai')) {
            return $fallback;
        }

        $classContext = "Clase: {$activity->title}\nContenido: ".($activity->description ?: 'Sin descripción');
        $system = 'Eres experto en diseño de tareas escolares. Responde SOLO JSON: {"ideas":[{"titulo":"...","descripcion":"...","enfoque":"..."}, ...]} con exactamente 3 ideas. Enfoques distintos: práctica guiada, aplicación creativa, y reto de extensión. Textos cortos en español.';
        $user = "Genera 3 propuestas de tarea para esta clase, con distinto nivel o enfoque.\n{$classContext}";

        try {
            $response = Http::withToken($apiKey)
                ->timeout(25)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => (string) config('services.openai.intelligence_model', 'gpt-4o-mini'),
                    'temperature' => 0.7,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $user],
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('LessonAiService task proposals failed', ['status' => $response->status()]);

                return $fallback;
            }

            $decoded = json_decode((string) $response->json('choices.0.message.content', ''), true);
            $ideas = is_array($decoded['ideas'] ?? null) ? $decoded['ideas'] : [];
            $normalized = $this->normalizeIdeas($ideas, $activity);

            return count($normalized) === 3 ? $normalized : $fallback;
        } catch (\Throwable $e) {
            Log::warning('LessonAiService task proposals exception', ['error' => $e->getMessage()]);

            return $fallback;
        }
    }

    /**
     * @param  array{titulo:string,descripcion?:string,enfoque?:string}  $idea
     */
    public function assignOfficialTask(
        Activity $activity,
        array $idea,
        ?string $dueDate = null,
        int $points = 20,
        bool $mirrorActivity = true,
    ): array {
        $titulo = trim((string) ($idea['titulo'] ?? 'Tarea de la clase'));
        $descripcion = trim((string) ($idea['descripcion'] ?? ''));
        $fecha = $dueDate ?: optional($activity->due_date)?->format('Y-m-d') ?: now()->addDay()->format('Y-m-d');

        $tarea = Tarea::create([
            'actividad_id' => $activity->id,
            'colegio_id' => $activity->colegio_id,
            'titulo' => $titulo !== '' ? $titulo : 'Tarea de la clase',
            'descripcion' => $descripcion !== '' ? $descripcion : null,
            'fecha_entrega' => $fecha,
            'puntos' => max(1, min(1000, $points)),
        ]);

        $mirrored = null;
        if ($mirrorActivity) {
            $typeMeta = Activity::normalizeType('tarea', true);
            $payload = [
                'teacher_id' => $activity->teacher_id,
                'course_id' => $activity->course_id,
                'colegio_id' => $activity->colegio_id,
                'title' => $tarea->titulo,
                'description' => $tarea->descripcion ?? 'Tarea asignada desde la clase.',
                'type' => $typeMeta['type'],
                'is_homework' => $typeMeta['is_homework'],
                'due_date' => $fecha,
                'max_score' => $tarea->puntos,
                'weight_percentage' => 0,
            ];
            foreach (['id_curso' => $activity->course_id, 'id_docente' => $activity->teacher_id, 'id_profesor' => $activity->teacher_id, 'estado' => 'publicado'] as $col => $val) {
                if (Schema::hasColumn('activities', $col)) {
                    $payload[$col] = $val;
                }
            }
            $mirrored = Activity::create($payload);
        }

        return [
            'tarea' => $this->serializeTarea($tarea),
            'mirrored_activity' => $mirrored ? [
                'id' => $mirrored->id,
                'title' => $mirrored->title,
                'due_date' => optional($mirrored->due_date)->format('Y-m-d'),
                'type' => $mirrored->type,
                'is_homework' => (bool) $mirrored->is_homework,
            ] : null,
        ];
    }

    public function generateNeeAdaptation(Activity $activity, string $neeType, ?Student $student = null): string
    {
        $apiKey = trim((string) config('services.openai.key', env('OPENAI_API_KEY')));
        if ($apiKey === '' || str_contains($apiKey, 'your_openai')) {
            return $this->fallbackNeeAdaptation($neeType, $student);
        }

        $who = $student?->name ? "Alumno: {$student->name}\n" : 'Alumno: (adaptación de aula, sin nombre específico)\n';
        $system = 'Eres especialista en educación inclusiva. Devuelve SOLO un párrafo de adaptación NEE, claro y aplicable en esta clase.';
        $user = $who
            ."Clase: {$activity->title}\nDescripción: {$activity->description}\nNEE / diagnóstico: {$neeType}\n"
            .'Genera una adaptación pedagógica con estrategias concretas y breves.';

        try {
            $res = Http::withToken($apiKey)
                ->timeout(25)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => (string) config('services.openai.intelligence_model', 'gpt-4o-mini'),
                    'temperature' => 0.7,
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $user],
                    ],
                ]);

            if (! $res->successful()) {
                return $this->fallbackNeeAdaptation($neeType, $student);
            }

            $adaptation = trim((string) $res->json('choices.0.message.content', ''));

            return $adaptation !== '' ? $adaptation : $this->fallbackNeeAdaptation($neeType, $student);
        } catch (\Throwable $e) {
            Log::warning('LessonAiService NEE generation failed', ['error' => $e->getMessage()]);

            return $this->fallbackNeeAdaptation($neeType, $student);
        }
    }

    public function saveNeeAdaptation(Activity $activity, string $neeType, string $text, ?Student $student = null): Activity
    {
        $activity->update([
            'nee_type' => $neeType,
            'nee_adaptation' => $text,
            'nee_student_id' => $student?->id,
        ]);

        return $activity->fresh(['neeStudent:id,name']);
    }

    public function resolveStudentForActivity(Activity $activity, ?int $studentId, ?string $studentName): ?Student
    {
        $courseId = (int) $activity->course_id;
        if ($studentId) {
            return Student::query()
                ->where('id', $studentId)
                ->whereHas('courses', fn ($q) => $q->where('courses.id', $courseId))
                ->first();
        }

        $needle = trim((string) $studentName);
        if ($needle === '') {
            return null;
        }

        return Student::query()
            ->whereHas('courses', fn ($q) => $q->where('courses.id', $courseId))
            ->where('colegio_id', $activity->colegio_id)
            ->where('name', 'like', '%'.$needle.'%')
            ->orderBy('name')
            ->first();
    }

    /**
     * @param  array<string,mixed>  $args
     * @param  array<string,mixed>  $screenContext
     */
    public function resolveActivity(User $teacher, array $args, array $screenContext = []): ?Activity
    {
        $query = Activity::query()
            ->where('teacher_id', $teacher->id)
            ->where('colegio_id', $teacher->colegio_id);

        $activityId = (int) ($args['activity_id'] ?? 0);
        if ($activityId <= 0 && ($screenContext['type'] ?? '') === 'activity') {
            $activityId = (int) ($screenContext['id'] ?? 0);
        }
        if ($activityId > 0) {
            return (clone $query)->where('id', $activityId)->first();
        }

        $courseId = (int) ($args['course_id'] ?? $screenContext['course_id'] ?? $screenContext['id'] ?? 0);
        $title = trim((string) ($args['title_hint'] ?? $args['title'] ?? ''));
        $dueDate = trim((string) ($args['due_date'] ?? ''));

        $scoped = clone $query;
        if ($courseId > 0) {
            $scoped->where('course_id', $courseId);
        }
        $scoped->where(function ($q) {
            $q->where('type', Activity::TYPE_CLASE)->orWhere(function ($inner) {
                $inner->where('type', '!=', Activity::TYPE_TAREA)->where('is_homework', 0);
            });
        });
        if ($dueDate !== '') {
            $scoped->whereDate('due_date', $dueDate);
        }
        if ($title !== '') {
            $scoped->where('title', 'like', '%'.$title.'%');
        }

        return $scoped->orderByDesc('due_date')->orderByDesc('id')->first()
            ?: (clone $query)->when($courseId > 0, fn ($q) => $q->where('course_id', $courseId))
                ->orderByDesc('due_date')
                ->orderByDesc('id')
                ->first();
    }

    public function fallbackNeeAdaptation(string $neeType, ?Student $student = null): string
    {
        $who = $student?->name ? "Para {$student->name}" : 'Para este alumno';
        $key = mb_strtolower($neeType);

        $body = match (true) {
            str_contains($key, 'tdah') => 'segmenta la actividad en pasos de 10 minutos, usa recordatorios visuales y alterna momentos de movimiento breve. Evita instrucciones largas y confirma comprensión con preguntas cortas.',
            str_contains($key, 'tea') || str_contains($key, 'autismo') => 'anticipa la secuencia con un mini-guion visual, reduce estímulos distractores y ofrece ejemplos concretos. Permite tiempos de respuesta más amplios.',
            str_contains($key, 'dislexia') => 'prioriza instrucciones orales claras, textos con tipografía legible y apoyos visuales. Permite responder de forma oral o con opciones guiadas.',
            str_contains($key, 'discalculia') => 'utiliza material manipulativo, ejemplos paso a paso y apoyos visuales. Evita sobrecarga numérica y ofrece tiempo adicional.',
            default => 'adapta la actividad con instrucciones breves, apoyos visuales y tiempo extra según necesidad. Prioriza la comprensión del objetivo sobre la cantidad de ejercicios.',
        };

        return "{$who}, con {$neeType}, {$body}";
    }

    /**
     * @return array{id:int,titulo:string,descripcion:?string,fecha_entrega:?string,puntos:int,calificacion:mixed,feedback:mixed}
     */
    public function serializeTarea(Tarea $tarea): array
    {
        return [
            'id' => $tarea->id,
            'titulo' => $tarea->titulo,
            'descripcion' => $tarea->descripcion,
            'fecha_entrega' => optional($tarea->fecha_entrega)->format('Y-m-d'),
            'puntos' => $tarea->puntos,
            'calificacion' => $tarea->calificacion,
            'feedback' => $tarea->feedback,
        ];
    }

    /**
     * @param  array<int, mixed>  $ideas
     * @return array<int, array{titulo:string,descripcion:string,enfoque:string}>
     */
    private function normalizeIdeas(array $ideas, Activity $activity): array
    {
        $defaults = ['Práctica guiada', 'Aplicación creativa', 'Reto de extensión'];
        $out = [];
        foreach (array_slice(array_values($ideas), 0, 3) as $i => $idea) {
            if (! is_array($idea)) {
                continue;
            }
            $titulo = trim((string) ($idea['titulo'] ?? ''));
            $descripcion = trim((string) ($idea['descripcion'] ?? ''));
            if ($titulo === '' || $descripcion === '') {
                continue;
            }
            $out[] = [
                'titulo' => $titulo,
                'descripcion' => $descripcion,
                'enfoque' => trim((string) ($idea['enfoque'] ?? $defaults[$i] ?? 'Propuesta')),
            ];
        }

        return $out;
    }

    /**
     * @return array<int, array{titulo:string,descripcion:string,enfoque:string}>
     */
    private function fallbackTaskProposals(Activity $activity): array
    {
        $topic = $activity->title ?: 'la clase';

        return [
            [
                'titulo' => "Práctica: {$topic}",
                'descripcion' => "Resuelve 5 ejercicios guiados sobre {$topic}. Muestra el procedimiento y subraya la idea clave de cada uno.",
                'enfoque' => 'Práctica guiada',
            ],
            [
                'titulo' => "Crear con {$topic}",
                'descripcion' => "Diseña un ejemplo de la vida cotidiana que use {$topic} y explícalo en media página o un esquema visual.",
                'enfoque' => 'Aplicación creativa',
            ],
            [
                'titulo' => "Reto de {$topic}",
                'descripcion' => "Elige un caso más complejo de {$topic} y propone una solución con justificación. Puedes usar un organizador gráfico.",
                'enfoque' => 'Reto de extensión',
            ],
        ];
    }
}

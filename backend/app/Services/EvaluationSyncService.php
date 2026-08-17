<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Course;
use App\Models\CourseEvaluationPlan;
use App\Models\Evaluation;
use App\Models\EvaluationAttempt;
use App\Models\EvaluationQuestion;
use App\Models\Grade;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class EvaluationSyncService
{
    /**
     * Persist an evaluation, its questions, a gradeable activity mirror, and optionally the plan item.
     */
    public function persist(User $teacher, array $payload): Evaluation
    {
        if (! Schema::hasTable('evaluations')) {
            throw new \RuntimeException('La tabla de evaluaciones aún no existe. Ejecuta las migraciones.');
        }

        return DB::transaction(function () use ($teacher, $payload) {
            return $this->persistInsideTransaction($teacher, $payload);
        });
    }

    private function persistInsideTransaction(User $teacher, array $payload): Evaluation
    {
        $questions = $this->normalizeQuestions($payload['questions'] ?? []);
        if ($questions === []) {
            $topic = trim((string) ($payload['title'] ?? $payload['topic'] ?? $payload['description'] ?? 'Evaluación'));
            $questions = [[
                'type' => 'open',
                'text' => 'Desarrolla con claridad lo aprendido sobre '.$topic.'.',
                'options' => [],
                'correct_answer' => null,
                'points' => 1,
                'topic' => $topic !== '' ? $topic : null,
            ]];
        }
        $total = collect($questions)->sum(fn ($q) => (int) ($q['points'] ?? 1));
        $scheduledAt = $this->parseDateTime(
            $payload['scheduled_at']
            ?? $payload['due_date']
            ?? $payload['date']
            ?? null
        );
        $weight = (float) (
            $payload['weight_percentage']
            ?? $payload['percentage']
            ?? $payload['weight']
            ?? 20
        );
        if ($weight <= 0) {
            $weight = 20;
        }

        $courseId = $payload['course_id'] ?? null;
        if ($courseId === '' || $courseId === false) {
            $courseId = null;
        }
        $courseId = $courseId !== null ? (int) $courseId : null;
        if ($courseId <= 0) {
            $courseId = null;
        }

        $data = $this->onlyExistingColumns('evaluations', [
            'teacher_id' => $teacher->id,
            'course_id' => $courseId,
            'colegio_id' => $teacher->colegio_id,
            'title' => Str::limit(trim((string) ($payload['title'] ?? 'Evaluación')), 240, ''),
            'description' => $payload['description'] ?? $payload['prompt'] ?? null,
            'topic' => $payload['topic'] ?? null,
            'mode' => in_array(($payload['mode'] ?? 'digital'), ['digital', 'physical'], true) ? $payload['mode'] : 'digital',
            'status' => in_array(($payload['status'] ?? 'draft'), ['draft', 'scheduled', 'published', 'graded'], true)
                ? $payload['status']
                : 'draft',
            'difficulty' => $payload['difficulty'] ?? null,
            'question_mix' => $payload['question_mix'] ?? null,
            'question_count' => count($questions),
            'generated_by_ai' => (bool) ($payload['generated_by_ai'] ?? false),
            'instructions' => $payload['instructions'] ?? null,
            'scheduled_at' => $scheduledAt,
            'total_points' => $total,
            'passing_score' => (int) data_get($payload, 'rubric.passing_score', max(1, (int) floor($total * 0.6))),
            'rubric' => $payload['rubric'] ?? ['total_points' => $total, 'passing_score' => max(1, (int) floor($total * 0.6))],
            'physical_format' => $payload['physical_format'] ?? [
                'paper_size' => 'A4',
                'orientation' => 'portrait',
                'font_size' => 12,
                'include_qr' => true,
            ],
            'large_print' => (bool) ($payload['large_print'] ?? false),
        ]);

        /** @var Evaluation $evaluation */
        $evaluation = ! empty($payload['id'])
            ? Evaluation::where('teacher_id', $teacher->id)->findOrFail((int) $payload['id'])
            : new Evaluation();

        $evaluation->forceFill($data);
        $evaluation->save();

        $this->syncQuestions($evaluation, $questions);

        try {
            $this->mirrorActivity($evaluation->fresh(), $teacher, $weight);
        } catch (\Throwable $e) {
            Log::error('EvaluationSyncService mirrorActivity failed', [
                'evaluation_id' => $evaluation->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        if (! empty($payload['add_to_plan'])) {
            try {
                $this->attachToPlan(
                    $teacher,
                    $evaluation->fresh(),
                    $weight,
                    in_array(($payload['category'] ?? 'summative'), ['formative', 'summative'], true)
                        ? $payload['category']
                        : 'summative',
                    trim((string) ($payload['topic'] ?? $evaluation->topic ?? 'Unidad')),
                    $scheduledAt?->toDateString()
                );
            } catch (\Throwable $e) {
                Log::warning('EvaluationSyncService attachToPlan failed', [
                    'evaluation_id' => $evaluation->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $relations = ['questions', 'course'];
        if (Schema::hasColumn('evaluations', 'activity_id')) {
            $relations[] = 'activity';
        }

        return $evaluation->fresh($relations) ?: $evaluation;
    }

    public function ensureActivityMirror(Evaluation $evaluation, ?User $teacher = null): ?Activity
    {
        $teacher = $teacher ?: $evaluation->teacher;
        if (! $teacher) {
            return $evaluation->activity;
        }

        return $this->mirrorActivity($evaluation, $teacher, (float) ($evaluation->activity?->weight_percentage ?: 20));
    }

    public function delete(Evaluation $evaluation): void
    {
        $activity = $evaluation->activity;
        $evaluation->delete();
        if ($activity) {
            $activity->delete();
        }
    }

    public function roster(Evaluation $evaluation): array
    {
        $this->ensureActivityMirror($evaluation);
        $evaluation->loadMissing(['course.students', 'activity']);

        $students = $evaluation->course
            ? $evaluation->course->students()->orderBy('name')->get()
            : collect();

        $activityId = $evaluation->activity_id;
        $grades = $activityId
            ? Grade::where('activity_id', $activityId)->get()->keyBy('student_id')
            : collect();

        $attempts = EvaluationAttempt::where('evaluation_id', $evaluation->id)
            ->whereNotNull('student_id')
            ->get()
            ->keyBy('student_id');

        return $students->map(function (Student $student) use ($evaluation, $grades, $attempts) {
            $grade = $grades->get($student->id);
            $attempt = $attempts->get($student->id);
            $score = $grade?->score ?? $attempt?->score;
            $max = (float) ($evaluation->activity?->max_score ?: 20);

            return [
                'student_id' => $student->id,
                'name' => $student->name,
                'grade' => $student->grade,
                'section' => $student->section,
                'score' => $score !== null ? (float) $score : null,
                'max_score' => $max,
                'percentage' => $score !== null && $max > 0 ? round(((float) $score / $max) * 100, 1) : null,
                'feedback' => $grade?->feedback_text,
            ];
        })->values()->all();
    }

    /**
     * Save numeric grades for enrolled students. Scores are on the activity max_score scale.
     *
     * @param  array<int, array{student_id:int, score:float, feedback?:?string}>  $rows
     */
    public function saveGrades(Evaluation $evaluation, User $teacher, array $rows): array
    {
        $activity = $this->ensureActivityMirror($evaluation, $teacher);
        if (! $activity) {
            throw new \RuntimeException('La evaluación no tiene un curso asignado para calificar.');
        }

        $studentIds = $evaluation->course
            ? $evaluation->course->students()->pluck('students.id')
            : collect();

        $saved = 0;
        $max = (float) ($activity->max_score ?: 20);

        foreach ($rows as $row) {
            $studentId = (int) ($row['student_id'] ?? 0);
            if ($studentId <= 0 || ! $studentIds->contains($studentId)) {
                continue;
            }
            if (! array_key_exists('score', $row) || $row['score'] === null || $row['score'] === '') {
                continue;
            }

            $score = max(0, min($max, (float) $row['score']));
            $feedback = $row['feedback'] ?? null;

            Grade::updateOrCreate(
                ['activity_id' => $activity->id, 'student_id' => $studentId],
                [
                    'colegio_id' => $teacher->colegio_id,
                    'score' => $score,
                    'status' => 'published',
                    'published_at' => now(),
                    'feedback_text' => $feedback,
                ]
            );

            EvaluationAttempt::updateOrCreate(
                ['evaluation_id' => $evaluation->id, 'student_id' => $studentId],
                [
                    'student_name' => Student::find($studentId)?->name,
                    'score' => $score,
                    'status' => 'graded',
                    'answers' => [],
                ]
            );

            $saved++;
        }

        return ['saved' => $saved, 'activity_id' => $activity->id];
    }

    public function findActivityForEvaluation(int $teacherId, ?int $evaluationId, ?int $activityId): ?Activity
    {
        if ($activityId) {
            $activity = Activity::where('id', $activityId)->where('teacher_id', $teacherId)->first();
            if ($activity) {
                return $activity;
            }
        }

        if ($evaluationId) {
            $evaluation = Evaluation::where('id', $evaluationId)->where('teacher_id', $teacherId)->first();
            if ($evaluation) {
                return $this->ensureActivityMirror($evaluation);
            }
        }

        return null;
    }

    private function mirrorActivity(Evaluation $evaluation, User $teacher, float $weight): ?Activity
    {
        if (! $evaluation->course_id) {
            return null;
        }

        $dueDate = $evaluation->scheduled_at
            ? $evaluation->scheduled_at->toDateString()
            : now()->toDateString();

        $maxScore = max(1, (int) ($evaluation->total_points ?: 20));
        if ($maxScore > 100) {
            $maxScore = 100;
        }

        $title = Str::limit('Examen: '.$evaluation->title, 240, '');
        $payload = $this->onlyExistingColumns('activities', [
            'teacher_id' => $teacher->id,
            'course_id' => $evaluation->course_id,
            'colegio_id' => $teacher->colegio_id,
            'type' => Activity::TYPE_ACTIVIDAD,
            'title' => $title,
            'description' => $evaluation->instructions ?: ($evaluation->description ?: 'Evaluación formal sincronizada.'),
            'max_score' => $maxScore,
            'weight_percentage' => $weight,
            'due_date' => $dueDate,
            'is_homework' => 0,
        ]);

        $activity = null;
        if (Schema::hasColumn('evaluations', 'activity_id') && $evaluation->getAttribute('activity_id')) {
            $activity = Activity::where('id', $evaluation->getAttribute('activity_id'))->first();
        }
        if (! $activity && Schema::hasColumn('activities', 'evaluation_id')) {
            $activity = Activity::where('evaluation_id', $evaluation->id)->first();
        }

        if (! $activity) {
            $activity = new Activity();
        }

        $activity->forceFill($payload);
        foreach ([
            'id_curso' => $evaluation->course_id,
            'id_docente' => $teacher->id,
            'id_profesor' => $teacher->id,
            'id_modulo' => null,
            'estado' => 'publicado',
            'evaluation_id' => $evaluation->id,
        ] as $col => $val) {
            if (Schema::hasColumn('activities', $col)) {
                $activity->setAttribute($col, $val);
            }
        }
        $activity->save();

        if (Schema::hasColumn('evaluations', 'activity_id') && $evaluation->getAttribute('activity_id') !== $activity->id) {
            $evaluation->forceFill(['activity_id' => $activity->id])->save();
        }

        return $activity;
    }

    private function syncQuestions(Evaluation $evaluation, array $questions): void
    {
        $evaluation->questions()->delete();
        foreach (array_values($questions) as $index => $question) {
            EvaluationQuestion::create([
                'evaluation_id' => $evaluation->id,
                'sort_order' => $index,
                'type' => $question['type'],
                'text' => $question['text'],
                'options' => $question['options'],
                'correct_answer' => $question['correct_answer'],
                'points' => $question['points'],
                'topic' => $question['topic'],
            ]);
        }
    }

    private function attachToPlan(
        User $teacher,
        Evaluation $evaluation,
        float $weight,
        string $category,
        string $unitName,
        ?string $dueDate
    ): void {
        if (! Schema::hasTable('course_evaluation_plans') || ! $evaluation->course_id) {
            return;
        }

        $course = Course::find($evaluation->course_id);
        $createAttrs = $this->onlyExistingColumns('course_evaluation_plans', [
            'title' => 'Plan de evaluación · '.($course?->subject_name ?? 'Curso'),
            'summary' => 'Plan sincronizado automáticamente desde AulaSync.',
            'status' => 'draft',
        ]);
        $plan = CourseEvaluationPlan::firstOrCreate(
            [
                'teacher_id' => $teacher->id,
                'course_id' => $evaluation->course_id,
            ],
            $createAttrs
        );

        if (Schema::hasColumn('course_evaluation_plan_items', 'evaluation_id')) {
            $exists = $plan->items()->where('evaluation_id', $evaluation->id)->exists();
            if ($exists) {
                return;
            }
        }

        $plan->items()->create($this->onlyExistingColumns('course_evaluation_plan_items', [
            'evaluation_id' => $evaluation->id,
            'unit_name' => $unitName !== '' ? $unitName : 'Unidad',
            'assessment_type' => $evaluation->title,
            'category' => $category,
            'weight_percentage' => max(1, min(100, $weight)),
            'due_date' => $dueDate,
            'notes' => 'Sincronizado desde el módulo de Evaluaciones / chat IA.',
        ]));
    }

    private function normalizeQuestions(array $questions): array
    {
        $out = [];
        foreach ($questions as $question) {
            if (! is_array($question)) {
                continue;
            }
            $text = $this->toPlainString($question['text'] ?? 'Pregunta');
            if ($text === '') {
                $text = 'Pregunta';
            }
            $options = $question['options'] ?? [];
            if (! is_array($options)) {
                $options = $options ? [(string) $options] : [];
            }
            $options = array_values(array_map(fn ($opt) => $this->toPlainString($opt), $options));

            $type = (string) ($question['type'] ?? 'open');
            if (! in_array($type, ['multiple_choice', 'true_false', 'open', 'completion'], true)) {
                $type = 'open';
            }

            $out[] = [
                'type' => $type,
                'text' => $text,
                'options' => $options,
                'correct_answer' => $this->toPlainString($question['correct_answer'] ?? null),
                'points' => max(1, (int) ($question['points'] ?? 1)),
                'topic' => $this->toPlainString($question['topic'] ?? null) ?: null,
            ];
        }

        return $out;
    }

    private function toPlainString(mixed $value): string
    {
        if (is_array($value)) {
            return trim(implode(', ', array_map(fn ($v) => is_scalar($v) ? (string) $v : json_encode($v), $value)));
        }
        if (is_object($value)) {
            return trim((string) json_encode($value));
        }
        if ($value === null) {
            return '';
        }

        return trim((string) $value);
    }

    private function onlyExistingColumns(string $table, array $data): array
    {
        if (! Schema::hasTable($table)) {
            return [];
        }

        $columns = array_flip(Schema::getColumnListing($table));

        return array_intersect_key($data, $columns);
    }

    private function parseDateTime(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return now();
        }
        if ($value instanceof Carbon) {
            return $value;
        }
        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return now();
        }
    }
}

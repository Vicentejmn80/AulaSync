<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Attendance;
use App\Models\Course;
use App\Models\Grade;
use App\Models\IntelligenceDocument;
use App\Models\Student;
use App\Models\User;
use App\Support\GradingScale;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Aplica al sistema los datos confirmados por el profesor tras revisar la
 * extracción de un documento: actividades al calendario, vinculación de
 * alumnos existentes al curso, calificaciones y asistencia. Todo dentro del
 * scope del profesor y su colegio, con auditoría de escritura.
 */
class IntelligenceApplicationService
{
    private const ACCESS_DENIED = 'No tienes permisos para acceder a esta información o realizar esta acción.';

    public function __construct(private StudentEnrollmentService $enrollment) {}

    /**
     * @param  array{course_id?: int, students?: array<int, int>, student_choices?: array<string, int>, activities?: array<int, int>, grades?: array<int, int>, attendance?: array<int, int>}  $selection
     * @return array{success: bool, message: string, data: array<string, mixed>}
     */
    public function apply(IntelligenceDocument $document, User $teacher, array $selection): array
    {
        $review = $document->review ?? [];
        $extraction = $document->extraction ?? [];

        if (! in_array($document->status, [IntelligenceDocument::STATUS_EXTRACTED, IntelligenceDocument::STATUS_APPLIED], true)) {
            return [
                'success' => false,
                'message' => 'Este documento no tiene datos listos para aplicar.',
                'data' => [],
            ];
        }

        $courseId = (int) ($selection['course_id'] ?? 0);
        $course = Course::where('id', $courseId)
            ->where('teacher_id', $teacher->id)
            ->when($teacher->colegio_id, fn ($query) => $query->where('colegio_id', $teacher->colegio_id))
            ->first();

        if (! $course) {
            throw ValidationException::withMessages(['course_id' => 'Selecciona uno de tus cursos antes de aplicar.']);
        }

        $scaleMax = GradingScale::maxFor($course->grading_scale);

        return DB::transaction(function () use ($document, $teacher, $selection, $review, $extraction, $course, $scaleMax) {
            $summary = [
                'created_activities' => 0,
                'linked_students' => 0,
                'created_grades' => 0,
                'created_attendance' => 0,
                'skipped_duplicates' => 0,
                'requires_director' => [],
                'skipped_students' => [],
                'skipped_grades' => [],
            ];

            $studentIdByName = [];

            // 1. Vincular alumnos existentes al curso.
            foreach ((array) ($selection['students'] ?? []) as $index) {
                $item = $review['students'][(int) $index] ?? null;
                if (! $item) {
                    continue;
                }

                $studentId = (int) ($item['student_id'] ?? 0);
                if ($studentId === 0 && isset($selection['student_choices'][(string) $index])) {
                    $studentId = (int) $selection['student_choices'][(string) $index];
                }

                $student = $studentId > 0
                    ? Student::where('id', $studentId)->where('colegio_id', $teacher->colegio_id)->first()
                    : null;

                if (! $student) {
                    $summary['requires_director'][] = $item['name'];
                    continue;
                }

                $this->enrollment->attachExisting($course, $student, $teacher);
                $studentIdByName[$this->key($item['name'])] = (int) $student->id;
                $summary['linked_students']++;
            }

            foreach ((array) ($review['students'] ?? []) as $item) {
                if (($item['status'] ?? '') === 'new') {
                    $summary['requires_director'][] = $item['name'];
                }
            }
            $summary['requires_director'] = array_values(array_unique($summary['requires_director']));

            // 2. Actividades (planificación → calendario).
            foreach ((array) ($selection['activities'] ?? []) as $index) {
                $item = $review['activities'][(int) $index] ?? null;
                if (! $item || empty($item['title'])) {
                    continue;
                }

                $duplicateId = $this->findDuplicate($course, (string) $item['title'], $item['date'] ?? null);
                if ($duplicateId) {
                    $summary['skipped_duplicates']++;
                    continue;
                }

                $maxScore = isset($item['max_score']) && $item['max_score'] > 0
                    ? min($scaleMax, (int) round($item['max_score']))
                    : $scaleMax;

                Activity::create([
                    'teacher_id' => $teacher->id,
                    'course_id' => $course->id,
                    'colegio_id' => $teacher->colegio_id,
                    'title' => (string) $item['title'],
                    'description' => $item['description'] ?? null,
                    'due_date' => $item['date'] ?? null,
                    'type' => $item['type'] ?? 'actividad',
                    'is_homework' => ($item['type'] ?? '') === 'tarea',
                    'max_score' => $maxScore,
                ]);
                $summary['created_activities']++;
            }

            // 3. Calificaciones.
            $activityIdByTitle = [];
            foreach ((array) ($selection['grades'] ?? []) as $index) {
                $item = $review['grades'][(int) $index] ?? null;
                if (! $item) {
                    continue;
                }

                $studentId = (int) ($item['student_id'] ?? 0);
                if ($studentId === 0) {
                    $studentId = (int) ($studentIdByName[$this->key((string) $item['student'])] ?? 0);
                }
                $student = $studentId > 0
                    ? Student::where('id', $studentId)->where('colegio_id', $teacher->colegio_id)->first()
                    : null;

                if (! $student) {
                    $summary['skipped_grades'][] = $item['student'].' — '.$item['activity_title'];
                    continue;
                }

                $titleKey = $this->key((string) $item['activity_title']);
                if (! array_key_exists($titleKey, $activityIdByTitle)) {
                    $activityIdByTitle[$titleKey] = $this->resolveOrCreateActivity($course, $teacher, (string) $item['activity_title'], $scaleMax, $item['max_score'] ?? null, $summary);
                }
                $activityId = $activityIdByTitle[$titleKey];

                if ($activityId === null) {
                    $summary['skipped_grades'][] = $item['student'].' — '.$item['activity_title'];
                    continue;
                }

                $score = (float) $item['score'];
                $importMax = isset($item['max_score']) && $item['max_score'] > 0 ? (float) $item['max_score'] : null;
                if ($importMax !== null && $importMax > $scaleMax) {
                    $score = round($score * $scaleMax / $importMax, 2);
                }
                $score = GradingScale::clampScore($course->grading_scale, $score);

                Grade::updateOrCreate(
                    ['activity_id' => $activityId, 'student_id' => $student->id],
                    [
                        'colegio_id' => $teacher->colegio_id,
                        'score' => $score,
                        'status' => 'draft',
                    ]
                );
                $summary['created_grades']++;
            }

            // 4. Asistencia.
            foreach ((array) ($selection['attendance'] ?? []) as $index) {
                $item = $review['attendance'][(int) $index] ?? null;
                if (! $item || empty($item['date'])) {
                    continue;
                }

                $studentId = (int) ($item['student_id'] ?? 0);
                if ($studentId === 0) {
                    $studentId = (int) ($studentIdByName[$this->key((string) $item['student'])] ?? 0);
                }
                $student = $studentId > 0
                    ? Student::where('id', $studentId)->where('colegio_id', $teacher->colegio_id)->first()
                    : null;

                if (! $student) {
                    continue;
                }

                $attendedOn = $item['date'] ? \Carbon\Carbon::parse($item['date'])->format('Y-m-d') : now()->format('Y-m-d');

                Attendance::updateOrCreate(
                    ['student_id' => $student->id, 'course_id' => $course->id, 'attended_on' => $attendedOn],
                    [
                        'teacher_id' => $teacher->id,
                        'colegio_id' => $teacher->colegio_id,
                        'status' => $item['status'] ?? 'present',
                        'source' => 'import',
                    ]
                );
                $summary['created_attendance']++;
            }

            $document->course_id = $course->id;
            $document->status = IntelligenceDocument::STATUS_APPLIED;
            $document->applied_at = now();
            $document->save();

            Log::info('nova_ai_write', [
                'user_id' => $teacher->id,
                'school_id' => $teacher->colegio_id,
                'action' => 'intelligence_apply',
                'document_id' => $document->id,
                'course_id' => $course->id,
                'timestamp' => now()->toIso8601String(),
                'created_activities' => $summary['created_activities'],
                'linked_students' => $summary['linked_students'],
                'created_grades' => $summary['created_grades'],
                'created_attendance' => $summary['created_attendance'],
            ]);

            return [
                'success' => true,
                'message' => $this->buildMessage($summary, $extraction),
                'data' => $summary,
            ];
        });
    }

    /**
     * Aplica una propuesta generada por las acciones (planificación,
     * actividades o tareas) al calendario del curso.
     *
     * @param  array<string, mixed>  $proposal
     * @param  array<int, int>  $selectedIndices
     * @return array{success: bool, message: string, data: array<string, mixed>}
     */
    public function applyProposal(User $teacher, array $proposal, array $selectedIndices): array
    {
        $course = Course::where('id', (int) ($proposal['course_id'] ?? 0))
            ->where('teacher_id', $teacher->id)
            ->when($teacher->colegio_id, fn ($query) => $query->where('colegio_id', $teacher->colegio_id))
            ->first();

        if (! $course) {
            return ['success' => false, 'message' => self::ACCESS_DENIED, 'data' => []];
        }

        $items = (array) ($proposal['items'] ?? []);
        $dates = (array) ($proposal['dates'] ?? []);
        $type = in_array($proposal['type'] ?? '', ['clase', 'actividad', 'tarea'], true) ? $proposal['type'] : 'actividad';
        $scaleMax = GradingScale::maxFor($course->grading_scale);

        return DB::transaction(function () use ($teacher, $course, $items, $dates, $type, $selectedIndices, $scaleMax) {
            $created = 0;
            $skipped = 0;

            foreach ($selectedIndices as $index) {
                $item = $items[(int) $index] ?? null;
                if (! $item || empty($item['title'])) {
                    continue;
                }

                $date = $dates[(int) $index] ?? null;
                if ($this->findDuplicate($course, (string) $item['title'], $date)) {
                    $skipped++;
                    continue;
                }

                Activity::create([
                    'teacher_id' => $teacher->id,
                    'course_id' => $course->id,
                    'colegio_id' => $teacher->colegio_id,
                    'title' => (string) $item['title'],
                    'description' => $item['description'] ?? null,
                    'due_date' => $date,
                    'type' => $type,
                    'is_homework' => $type === 'tarea',
                    'max_score' => $scaleMax,
                ]);
                $created++;
            }

            Log::info('nova_ai_write', [
                'user_id' => $teacher->id,
                'school_id' => $teacher->colegio_id,
                'action' => 'intelligence_apply_proposal',
                'course_id' => $course->id,
                'timestamp' => now()->toIso8601String(),
                'created_activities' => $created,
            ]);

            return [
                'success' => true,
                'message' => $created > 0
                    ? "✅ Listo: agregué {$created} ".($type === 'tarea' ? 'tarea(s)' : ($type === 'clase' ? 'clase(s)' : 'actividad(es)'))." al calendario de {$course->subject_name}.{$this->skippedSuffix($skipped)}"
                    : 'No se creó ninguna actividad. Revisa la propuesta y vuelve a intentarlo.',
                'data' => ['created' => $created, 'skipped' => $skipped, 'course_id' => $course->id],
            ];
        });
    }

    private function resolveOrCreateActivity(Course $course, User $teacher, string $title, int $scaleMax, ?float $importMax, array &$summary): ?int
    {
        $existing = $course->activities()
            ->whereRaw('LOWER(title) = ?', [mb_strtolower($title)])
            ->orderByDesc('id')
            ->first();

        if ($existing) {
            return (int) $existing->id;
        }

        $activity = Activity::create([
            'teacher_id' => $teacher->id,
            'course_id' => $course->id,
            'colegio_id' => $teacher->colegio_id,
            'title' => $title,
            'description' => 'Creada automáticamente al importar calificaciones.',
            'type' => 'actividad',
            'max_score' => $importMax !== null && $importMax > 0 ? min($scaleMax, (int) round($importMax)) : $scaleMax,
        ]);
        $summary['created_activities']++;

        return (int) $activity->id;
    }

    private function findDuplicate(Course $course, string $title, ?string $date): ?int
    {
        $query = $course->activities()
            ->whereRaw('LOWER(title) = ?', [mb_strtolower($title)]);

        if ($date) {
            $query->whereDate('due_date', $date);
        }

        $duplicate = $query->value('id');

        return $duplicate ? (int) $duplicate : null;
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>  $extraction
     */
    private function buildMessage(array $summary, array $extraction): string
    {
        $parts = [];
        $kindLabel = \App\Models\IntelligenceDocument::kindLabels()[$extraction['document_type'] ?? 'otro'] ?? 'documento';

        if ($summary['created_activities'] > 0) {
            $parts[] = "📝 {$summary['created_activities']} actividad(es) agregadas al calendario";
        }
        if ($summary['linked_students'] > 0) {
            $parts[] = "👩‍🎓 {$summary['linked_students']} alumno(s) vinculados al curso";
        }
        if ($summary['created_grades'] > 0) {
            $parts[] = "📊 {$summary['created_grades']} calificación(es) registradas";
        }
        if ($summary['created_attendance'] > 0) {
            $parts[] = "🗓️ {$summary['created_attendance']} registro(s) de asistencia";
        }

        if ($parts === []) {
            $message = "No apliqué ningún dato del {$kindLabel}. Revisa la selección e inténtalo de nuevo.";
        } else {
            $message = "✅ {$kindLabel} aplicado: ".implode(' · ', $parts).'.';
        }

        if ($summary['skipped_duplicates'] > 0) {
            $message .= " ({$summary['skipped_duplicates']} duplicados omitidos)";
        }
        if (count($summary['requires_director']) > 0) {
            $message .= ' ⚠️ '.count($summary['requires_director']).' alumno(s) aún no existen en AulaSync: pide al director que los matricule ('.implode(', ', array_slice($summary['requires_director'], 0, 3)).').';
        }

        return $message;
    }

    private function skippedSuffix(int $skipped): string
    {
        return $skipped > 0 ? " ({$skipped} duplicados omitidos)" : '';
    }

    private function key(string $value): string
    {
        return strtr(mb_strtolower(trim($value)), [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);
    }
}

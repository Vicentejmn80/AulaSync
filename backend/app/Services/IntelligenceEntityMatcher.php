<?php

namespace App\Services;

use App\Models\Course;
use App\Models\User;

/**
 * Relaciona los datos extraídos de un documento con las entidades reales
 * de AulaSync (alumnos del colegio y cursos del profesor) sin crear nada:
 * clasifica cada dato como existente, ambiguo o nuevo para que el profesor
 * confirme antes de aplicar.
 */
class IntelligenceEntityMatcher
{
    public function __construct(private PersonNameMatcher $matcher) {}

    /**
     * @param  array<string, mixed>  $extraction
     * @return array<string, mixed>
     */
    public function buildReview(array $extraction, User $teacher): array
    {
        $colegioId = $teacher->colegio_id ? (int) $teacher->colegio_id : 0;
        $courses = Course::where('teacher_id', $teacher->id)
            ->when($colegioId > 0, fn ($query) => $query->where('colegio_id', $colegioId))
            ->orderBy('subject_name')
            ->orderBy('grade')
            ->get(['id', 'subject_name', 'grade', 'section']);

        $courseOptions = $courses->map(fn ($course) => [
            'id' => $course->id,
            'label' => trim($course->subject_name.' · '.$course->grade.($course->section ? ' / '.$course->section : '')),
        ])->values()->all();

        $context = (array) ($extraction['context'] ?? []);
        $suggestedCourseId = $this->suggestCourse($courses, $context, $extraction);
        $reviewCourse = $suggestedCourseId ? $courses->firstWhere('id', $suggestedCourseId) : null;

        $students = [];
        $newStudents = [];
        $seenStudents = [];
        foreach ((array) ($extraction['students'] ?? []) as $student) {
            $name = trim((string) ($student['name'] ?? ''));
            if ($name === '' || mb_strlen($name) < 2) {
                continue;
            }
            $key = $this->key($name);
            if (isset($seenStudents[$key])) {
                continue;
            }
            $seenStudents[$key] = true;

            $item = [
                'name' => $name,
                'grade' => $student['grade'] ?? null,
                'section' => $student['section'] ?? null,
                'confidence' => $this->clamp($student['confidence'] ?? null),
                'status' => 'new',
                'student_id' => null,
                'candidates' => [],
            ];

            if ($colegioId > 0) {
                $match = $this->matcher->resolveStudent($colegioId, $name);
                if ($match->isUnique() && $match->model) {
                    $item['status'] = 'existing';
                    $item['student_id'] = (int) $match->model->id;
                    $item['name'] = (string) $match->model->name;
                } elseif ($match->isAmbiguous()) {
                    $item['status'] = 'ambiguous';
                    $item['candidates'] = array_values(array_map(fn ($candidate) => [
                        'id' => (int) $candidate['id'],
                        'name' => (string) $candidate['name'],
                        'code' => $candidate['code'] ?? null,
                    ], $match->candidates));
                }
            }

            if ($item['status'] === 'new') {
                $newStudents[] = $name;
            }
            $students[] = $item;
        }

        $activities = [];
        foreach ((array) ($extraction['activities'] ?? []) as $activity) {
            $title = trim((string) ($activity['title'] ?? ''));
            if ($title === '') {
                continue;
            }

            $activities[] = [
                'title' => mb_substr($title, 0, 180),
                'date' => $activity['date'] ?? null,
                'type' => in_array($activity['type'] ?? '', ['clase', 'actividad', 'tarea'], true) ? $activity['type'] : 'actividad',
                'description' => $activity['description'] ?? null,
                'max_score' => isset($activity['max_score']) && is_numeric($activity['max_score']) ? (float) $activity['max_score'] : null,
                'confidence' => $this->clamp($activity['confidence'] ?? null),
                'duplicate_of' => $reviewCourse ? $this->findDuplicateActivity($reviewCourse, $title, $activity['date'] ?? null) : null,
            ];
        }

        $grades = [];
        foreach ((array) ($extraction['grades'] ?? []) as $grade) {
            $studentName = trim((string) ($grade['student'] ?? ''));
            $score = isset($grade['score']) && is_numeric($grade['score']) ? (float) $grade['score'] : null;
            $activityTitle = trim((string) ($grade['activity_title'] ?? ''));

            if ($studentName === '' || $activityTitle === '' || $score === null) {
                continue;
            }

            $studentMatch = $colegioId > 0 ? $this->matcher->resolveStudent($colegioId, $studentName) : null;
            $studentId = $studentMatch && $studentMatch->isUnique() && $studentMatch->model ? (int) $studentMatch->model->id : null;

            $grades[] = [
                'student' => $studentName,
                'student_id' => $studentId,
                'student_status' => $studentId ? 'existing' : ($studentMatch && $studentMatch->isAmbiguous() ? 'ambiguous' : 'new'),
                'activity_title' => mb_substr($activityTitle, 0, 180),
                'score' => $score,
                'max_score' => isset($grade['max_score']) && is_numeric($grade['max_score']) ? (float) $grade['max_score'] : null,
                'confidence' => $this->clamp($grade['confidence'] ?? null),
            ];
        }

        $attendance = [];
        foreach ((array) ($extraction['attendance'] ?? []) as $row) {
            $studentName = trim((string) ($row['student'] ?? ''));
            $date = $row['date'] ?? null;
            $status = in_array($row['status'] ?? '', ['present', 'absent', 'tardy'], true) ? $row['status'] : null;

            if ($studentName === '' || ! $date || ! $status) {
                continue;
            }

            $studentMatch = $colegioId > 0 ? $this->matcher->resolveStudent($colegioId, $studentName) : null;
            $studentId = $studentMatch && $studentMatch->isUnique() && $studentMatch->model ? (int) $studentMatch->model->id : null;

            $attendance[] = [
                'student' => $studentName,
                'student_id' => $studentId,
                'date' => $date,
                'status' => $status,
                'confidence' => $this->clamp($row['confidence'] ?? null),
            ];
        }

        $warnings = [];
        if ($newStudents !== []) {
            $warnings[] = count($newStudents).' alumno(s) no existen en AulaSync: '.implode(', ', array_slice($newStudents, 0, 5)).'. El director debe matricularlos para poder vincularlos.';
        }
        if ($reviewCourse === null && $courses->isNotEmpty() && ($activities !== [] || $grades !== [])) {
            $warnings[] = 'No pude detectar con certeza a qué curso pertenece el documento. Selecciona el curso antes de aplicar.';
        }
        if ($courses->isEmpty()) {
            $warnings[] = 'Todavía no tienes cursos en AulaSync. Crea tu primer curso para poder aplicar documentos.';
        }

        return [
            'document_type' => $extraction['document_type'] ?? 'otro',
            'confidence' => $this->clamp($extraction['confidence'] ?? null),
            'course_options' => $courseOptions,
            'suggested_course_id' => $suggestedCourseId,
            'students' => $students,
            'activities' => $activities,
            'grades' => $grades,
            'attendance' => $attendance,
            'observations' => array_values(array_map(fn ($item) => mb_substr((string) $item, 0, 500), (array) ($extraction['observations'] ?? []))),
            'uncertain' => array_values(array_map(fn ($item) => mb_substr((string) $item, 0, 500), (array) ($extraction['uncertain'] ?? []))),
            'warnings' => $warnings,
        ];
    }

    /**
     * Sugiere el curso del profesor según el contexto detectado (materia,
     * grado y sección) o si el profesor tiene un único curso.
     */
    private function suggestCourse($courses, array $context, array $extraction): ?int
    {
        if ($courses->isEmpty()) {
            return null;
        }

        if ($courses->count() === 1) {
            return (int) $courses->first()->id;
        }

        $subject = $this->key((string) ($context['subject'] ?? ''));
        $grade = $this->key((string) ($context['grade'] ?? ''));
        $section = $this->key((string) ($context['section'] ?? ''));

        if ($subject === '') {
            $subject = $this->key((string) ($extraction['subject_hint'] ?? ''));
        }

        if ($subject === '' && $grade === '') {
            return null;
        }

        $best = null;
        $bestScore = 0;
        foreach ($courses as $course) {
            $score = 0;
            if ($subject !== '' && $this->key($course->subject_name) === $subject) {
                $score += 4;
            } elseif ($subject !== '' && str_contains($this->key($course->subject_name), $subject)) {
                $score += 2;
            }
            if ($grade !== '' && $this->key($course->grade) === $grade) {
                $score += 3;
            }
            if ($section !== '' && $this->key((string) $course->section) === $section) {
                $score += 1;
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $course;
            }
        }

        return $bestScore >= 3 && $best ? (int) $best->id : null;
    }

    private function findDuplicateActivity(Course $course, string $title, ?string $date): ?int
    {
        $query = $course->activities()
            ->whereRaw('LOWER(title) = ?', [mb_strtolower($title)]);

        if ($date) {
            $query->whereDate('due_date', $date);
        }

        $duplicate = $query->value('id');

        return $duplicate ? (int) $duplicate : null;
    }

    private function clamp(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        return round(max(0, min(1, (float) $value)), 2);
    }

    private function key(string $value): string
    {
        $value = mb_strtolower(trim($value));

        return strtr($value, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);
    }
}

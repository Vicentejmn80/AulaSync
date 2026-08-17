<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Course;
use App\Models\Grade;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class ReportCardService
{
    /**
     * Same payload used by teacher, director and family so the boletin never diverges.
     */
    public function build(Student $student, bool $publishedOnly = false): array
    {
        $student->loadMissing(['courses.teacher', 'colegio']);

        $filterPublished = $publishedOnly && Schema::hasColumn('grades', 'status');

        $courseData = $student->courses->map(function (Course $course) use ($student, $filterPublished) {
            $activities = Activity::where('course_id', $course->id)
                ->where(fn ($q) => $q->whereNull('type')->orWhere('type', '!=', 'clase'))
                ->with(['grades' => fn ($q) => $q->where('student_id', $student->id)
                    ->when($filterPublished, fn ($gq) => $gq->where('status', 'published'))])
                ->orderBy('due_date')
                ->get(['id', 'title', 'type', 'max_score', 'weight_percentage', 'due_date']);

            $mapped = $activities->map(function (Activity $activity) {
                $grade = $activity->grades->first();
                $score = $grade?->score !== null ? (float) $grade->score : null;
                $max = (float) ($activity->max_score ?: 20);

                return [
                    'title' => $activity->title,
                    'type' => $activity->type,
                    'score' => $score ?? 0,
                    'has_score' => $score !== null,
                    'max_score' => $max,
                    'percentage' => $score !== null && $max > 0 ? round(($score / $max) * 100, 1) : 0,
                    'due_date' => $activity->due_date?->format('d/m/Y'),
                    'feedback' => $grade?->feedback_text,
                ];
            });

            $scored = $mapped->filter(fn ($row) => $row['has_score']);
            $promedio = $scored->isNotEmpty()
                ? round((float) $scored->avg('percentage'), 1)
                : 0;

            return [
                'course_id' => $course->id,
                'course_name' => trim($course->subject_name.' '.$course->grade.($course->section ? ' / '.$course->section : '')),
                'teacher_name' => $course->teacher?->name ?? '—',
                'promedio' => $promedio,
                'activities' => $mapped->values(),
            ];
        });

        $withData = $courseData->filter(fn ($c) => $c['promedio'] > 0 || $c['activities']->contains(fn ($a) => $a['has_score']));

        return [
            'courseData' => $courseData,
            'globalAverage' => $withData->isNotEmpty() ? round((float) $withData->avg('promedio'), 1) : 0,
            'institutionName' => $student->colegio?->name ?? 'AulaSync',
        ];
    }
}

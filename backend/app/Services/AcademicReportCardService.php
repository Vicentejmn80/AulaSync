<?php

namespace App\Services;

use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Models\Course;
use App\Models\GradeAuditLog;
use App\Models\Notification;
use App\Models\ReportCard;
use App\Models\ReportCardGrade;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AcademicReportCardService
{
    // ── Periods ──────────────────────────────────────────────────────────────

    public function periodsForSchool(int $colegioId): Collection
    {
        return AcademicPeriod::where('colegio_id', $colegioId)
            ->withCount(['reportCards', 'reportCards as published_count' => fn ($q) => $q->where('status', 'published')])
            ->orderByDesc('start_date')
            ->get();
    }

    public function createPeriod(int $colegioId, array $data): AcademicPeriod
    {
        return AcademicPeriod::create([
            'colegio_id'           => $colegioId,
            'name'                 => $data['name'],
            'start_date'           => $data['start_date'],
            'end_date'             => $data['end_date'],
            'report_card_due_date' => $data['report_card_due_date'] ?? null,
            'status'               => $data['status'] ?? 'active',
        ]);
    }

    public function updatePeriod(AcademicPeriod $period, array $data): AcademicPeriod
    {
        $period->update([
            'name'                 => $data['name']                 ?? $period->name,
            'start_date'           => $data['start_date']           ?? $period->start_date,
            'end_date'             => $data['end_date']             ?? $period->end_date,
            'report_card_due_date' => $data['report_card_due_date'] ?? $period->report_card_due_date,
            'status'               => $data['status']               ?? $period->status,
        ]);

        return $period->fresh();
    }

    // ── Grades summary (live calculation) ───────────────────────────────────

    /**
     * Matrix of all students x all courses for the period, using live activity data.
     * Returns [{student, courses: [{course_id, course_name, average, letter}]}]
     */
    public function gradesSummary(AcademicPeriod $period): array
    {
        $students = Student::where('colegio_id', $period->colegio_id)
            ->with([
                'courses' => function ($q) {
                    $q->select('courses.id', 'courses.subject_name', 'courses.grade', 'courses.section', 'courses.teacher_id')
                      ->with('teacher:id,name');
                },
            ])
            ->orderBy('grade')
            ->orderBy('section')
            ->orderBy('name')
            ->get();

        // Load all activities and grades for the school in this period range at once
        $courseIds = $students->flatMap(fn (Student $s) => $s->courses->pluck('id'))->unique()->values();

        $activities = Activity::whereIn('course_id', $courseIds)
            ->where(fn ($q) => $q->whereNull('type')->orWhere('type', '!=', 'clase'))
            ->whereBetween('due_date', [$period->start_date, $period->end_date])
            ->with('grades:id,activity_id,student_id,score')
            ->get(['id', 'course_id', 'max_score', 'weight_percentage']);

        // Group activities by course
        $activitiesByCourse = $activities->groupBy('course_id');

        $rows = $students->map(function (Student $student) use ($activitiesByCourse, $period) {
            $courses = $student->courses->map(function (Course $course) use ($student, $activitiesByCourse) {
                $courseActivities = $activitiesByCourse->get($course->id, collect());
                $average = $this->computeWeightedAverage($student->id, $courseActivities);

                return [
                    'course_id'   => $course->id,
                    'course_name' => $this->courseName($course),
                    'teacher'     => $course->teacher?->name ?? '—',
                    'average'     => $average,
                    'letter'      => $average !== null ? $this->letterGrade($average) : '—',
                ];
            })->values();

            $globalAvg = $courses->filter(fn ($c) => $c['average'] !== null)->avg('average');

            return [
                'student_id'     => $student->id,
                'student_name'   => $student->name,
                'grade'          => $student->grade,
                'section'        => $student->section,
                'global_average' => $globalAvg !== null ? round((float) $globalAvg, 1) : null,
                'courses'        => $courses,
            ];
        });

        // Collect all unique courses for column headers
        $allCourses = $students->flatMap(fn (Student $s) => $s->courses)->unique('id')
            ->map(fn (Course $c) => ['id' => $c->id, 'name' => $this->courseName($c)])
            ->sortBy('name')
            ->values();

        return [
            'period'     => $this->periodPayload($period),
            'columns'    => $allCourses,
            'rows'       => $rows->values(),
        ];
    }

    // ── Generate boletas ─────────────────────────────────────────────────────

    /**
     * Creates (or re-generates) a draft report_card for every student in the period.
     * Existing published boletas are NOT overwritten.
     */
    public function generateForPeriod(AcademicPeriod $period, User $director): array
    {
        $students = Student::where('colegio_id', $period->colegio_id)
            ->with([
                'courses' => fn ($q) => $q->select('courses.id', 'courses.subject_name', 'courses.grade', 'courses.section'),
            ])
            ->get();

        $courseIds    = $students->flatMap(fn (Student $s) => $s->courses->pluck('id'))->unique()->values();
        $activities   = Activity::whereIn('course_id', $courseIds)
            ->where(fn ($q) => $q->whereNull('type')->orWhere('type', '!=', 'clase'))
            ->whereBetween('due_date', [$period->start_date, $period->end_date])
            ->with('grades:id,activity_id,student_id,score')
            ->get(['id', 'course_id', 'max_score', 'weight_percentage']);

        $activitiesByCourse = $activities->groupBy('course_id');
        $generated = 0;
        $skipped   = 0;

        DB::transaction(function () use ($students, $period, $director, $activitiesByCourse, &$generated, &$skipped) {
            foreach ($students as $student) {
                // Skip already published boletas
                $existing = ReportCard::where('student_id', $student->id)
                    ->where('academic_period_id', $period->id)
                    ->first();

                if ($existing && $existing->isPublished()) {
                    $skipped++;
                    continue;
                }

                // Build grades per course
                $courseGrades = [];
                foreach ($student->courses as $course) {
                    $courseActivities = $activitiesByCourse->get($course->id, collect());
                    $average = $this->computeWeightedAverage($student->id, $courseActivities);
                    if ($average === null) {
                        continue; // No graded activities for this course in the period
                    }

                    $courseGrades[] = [
                        'course_id'   => $course->id,
                        'course_name' => $this->courseName($course),
                        'grade'       => round($average, 2),
                        'letter_grade' => $this->letterGrade($average),
                        'is_manual'   => false,
                    ];
                }

                if (empty($courseGrades)) {
                    $skipped++;
                    continue;
                }

                $card = ReportCard::updateOrCreate(
                    ['student_id' => $student->id, 'academic_period_id' => $period->id],
                    [
                        'colegio_id'   => $period->colegio_id,
                        'status'       => 'draft',
                        'generated_at' => now(),
                    ]
                );

                // Refresh grades (delete non-manual, re-insert)
                ReportCardGrade::where('report_card_id', $card->id)
                    ->where('is_manual', false)
                    ->delete();

                foreach ($courseGrades as $cg) {
                    ReportCardGrade::updateOrCreate(
                        ['report_card_id' => $card->id, 'course_id' => $cg['course_id']],
                        $cg
                    );
                }

                GradeAuditLog::create([
                    'report_card_id' => $card->id,
                    'user_id'        => $director->id,
                    'action'         => 'generated',
                    'created_at'     => now(),
                ]);

                $generated++;
            }
        });

        return ['generated' => $generated, 'skipped' => $skipped];
    }

    // ── Publish ───────────────────────────────────────────────────────────────

    public function publishPeriod(AcademicPeriod $period, User $director): array
    {
        $cards = ReportCard::where('academic_period_id', $period->id)
            ->where('status', 'draft')
            ->get();

        if ($cards->isEmpty()) {
            return ['published' => 0, 'message' => 'No hay boletas en borrador para publicar.'];
        }

        DB::transaction(function () use ($cards, $director) {
            foreach ($cards as $card) {
                $card->update(['status' => 'published', 'published_at' => now()]);

                GradeAuditLog::create([
                    'report_card_id' => $card->id,
                    'user_id'        => $director->id,
                    'action'         => 'published',
                    'created_at'     => now(),
                ]);

                // Notify linked guardians
                $student = $card->student()->with('guardians')->first();
                if ($student) {
                    foreach ($student->guardians as $guardian) {
                        Notification::create([
                            'user_id'    => $guardian->id,
                            'type'       => 'boletin_publicado',
                            'title'      => 'Boleta publicada',
                            'body'       => "La boleta de {$student->name} para el período \"{$card->period->name}\" ya está disponible.",
                            'data'       => json_encode(['report_card_id' => $card->id, 'student_id' => $student->id]),
                            'read_at'    => null,
                        ]);
                    }
                }
            }
        });

        return ['published' => $cards->count()];
    }

    // ── Single boleta edit ────────────────────────────────────────────────────

    public function getReportCard(int $reportCardId, int $colegioId): ReportCard
    {
        return ReportCard::where('colegio_id', $colegioId)
            ->with(['student', 'period', 'grades', 'auditLogs.user:id,name'])
            ->findOrFail($reportCardId);
    }

    public function updateReportCard(ReportCard $card, array $data, User $director): ReportCard
    {
        if (isset($data['observations'])) {
            $old = $card->observations;
            $card->update(['observations' => $data['observations']]);
            GradeAuditLog::create([
                'report_card_id' => $card->id,
                'user_id'        => $director->id,
                'action'         => 'edited',
                'field_changed'  => 'observations',
                'old_value'      => $old,
                'new_value'      => $data['observations'],
                'created_at'     => now(),
            ]);
        }

        if (isset($data['grades']) && is_array($data['grades'])) {
            foreach ($data['grades'] as $gradeRow) {
                $rcGrade = ReportCardGrade::where('report_card_id', $card->id)
                    ->where('course_id', $gradeRow['course_id'])
                    ->first();

                if (! $rcGrade) {
                    continue;
                }

                $oldGrade = $rcGrade->grade;
                $newGrade = isset($gradeRow['grade']) ? (float) $gradeRow['grade'] : null;

                if ($newGrade !== null && $newGrade !== (float) $oldGrade) {
                    GradeAuditLog::create([
                        'report_card_id' => $card->id,
                        'user_id'        => $director->id,
                        'action'         => 'edited',
                        'field_changed'  => "grade:{$rcGrade->course_id}",
                        'old_value'      => $oldGrade,
                        'new_value'      => $newGrade,
                        'created_at'     => now(),
                    ]);
                }

                $rcGrade->update([
                    'grade'                => $newGrade ?? $rcGrade->grade,
                    'letter_grade'         => $newGrade !== null ? $this->letterGrade($newGrade) : $rcGrade->letter_grade,
                    'teacher_observations' => $gradeRow['teacher_observations'] ?? $rcGrade->teacher_observations,
                    'is_manual'            => true,
                ]);
            }
        }

        return $card->fresh(['grades', 'student', 'period']);
    }

    // ── Published boletas for representative ──────────────────────────────────

    public function publishedForStudent(Student $student): Collection
    {
        return ReportCard::where('student_id', $student->id)
            ->where('status', 'published')
            ->with(['period:id,name,start_date,end_date', 'grades'])
            ->orderByDesc('published_at')
            ->get()
            ->map(fn (ReportCard $card) => $this->reportCardPayload($card));
    }

    // ── PDF data ──────────────────────────────────────────────────────────────

    public function pdfData(ReportCard $card): array
    {
        $card->loadMissing(['student.colegio', 'period', 'grades']);

        return [
            'card'           => $card,
            'student'        => $card->student,
            'period'         => $card->period,
            'colegio'        => $card->student->colegio,
            'grades'         => $card->grades,
            'global_average' => $card->globalAverage(),
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function letterGrade(float $score): string
    {
        return match (true) {
            $score >= 90 => 'A',
            $score >= 80 => 'B+',
            $score >= 70 => 'C+',
            $score >= 60 => 'D',
            default      => 'F',
        };
    }

    private function computeWeightedAverage(int $studentId, Collection $activities): ?float
    {
        $totalWeight = 0.0;
        $weightedSum = 0.0;
        $hasAny      = false;

        foreach ($activities as $activity) {
            $grade = $activity->grades->firstWhere('student_id', $studentId);
            if (! $grade || $grade->score === null) {
                continue;
            }

            $max    = (float) ($activity->max_score ?: 20);
            $pct    = ($max > 0) ? ((float) $grade->score / $max) * 100 : 0;
            $weight = (float) ($activity->weight_percentage ?: 1);

            $totalWeight += $weight;
            $weightedSum += $pct * $weight;
            $hasAny = true;
        }

        if (! $hasAny || $totalWeight === 0.0) {
            return null;
        }

        return $weightedSum / $totalWeight;
    }

    private function courseName(Course $course): string
    {
        return trim($course->subject_name.' '.($course->grade ?? '').(isset($course->section) ? ' / '.$course->section : ''));
    }

    private function periodPayload(AcademicPeriod $period): array
    {
        return [
            'id'         => $period->id,
            'name'       => $period->name,
            'start_date' => $period->start_date?->format('d/m/Y'),
            'end_date'   => $period->end_date?->format('d/m/Y'),
            'status'     => $period->status,
        ];
    }

    public function reportCardPayload(ReportCard $card): array
    {
        return [
            'id'             => $card->id,
            'period'         => $card->period ? ['id' => $card->period->id, 'name' => $card->period->name] : null,
            'status'         => $card->status,
            'status_label'   => $card->statusLabel(),
            'observations'   => $card->observations,
            'published_at'   => $card->published_at?->format('d/m/Y'),
            'global_average' => $card->globalAverage(),
            'grades'         => $card->grades->map(fn (ReportCardGrade $g) => [
                'course_id'            => $g->course_id,
                'course_name'          => $g->course_name,
                'grade'                => (float) $g->grade,
                'letter_grade'         => $g->letter_grade,
                'teacher_observations' => $g->teacher_observations,
                'is_manual'            => $g->is_manual,
            ])->values(),
        ];
    }
}

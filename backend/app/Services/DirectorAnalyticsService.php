<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Course;
use App\Models\Grade;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates academic performance data for the Director dashboard.
 *
 * MVP scope: queries ALL data in the system (single-tenant).
 * Future: scope by institution_id once the Institution model is added.
 */
class DirectorAnalyticsService
{
    // ─── Filter state ────────────────────────────────────────────────────────

    private ?string $filterGrade   = null;
    private ?string $filterSection = null;

    public function withFilters(?string $grade, ?string $section): static
    {
        $this->filterGrade   = $grade   ?: null;
        $this->filterSection = $section ?: null;
        return $this;
    }

    // ─── Stats cards ──────────────────────────────────────────────────────────

    public function getStats(): array
    {
        $studentsQuery  = Student::query();
        $coursesQuery   = Course::query();
        $activitiesQuery = Activity::query();

        if ($this->filterGrade) {
            $studentsQuery->where('grade', $this->filterGrade);
            $coursesQuery->where('grade', $this->filterGrade);
            $activitiesQuery->whereHas('course', fn ($q) => $q->where('grade', $this->filterGrade));
        }
        if ($this->filterSection) {
            $studentsQuery->where('section', $this->filterSection);
            $coursesQuery->where('section', $this->filterSection);
            $activitiesQuery->whereHas('course', fn ($q) => $q->where('section', $this->filterSection));
        }

        $totalStudents    = $studentsQuery->count();
        $totalCourses     = $coursesQuery->count();
        $activitiesWeek   = $activitiesQuery->where('created_at', '>=', now()->startOfWeek())->count();

        // Institutional average as percentage of max_score
        $avgRow = Grade::query()
            ->join('activities', 'grades.activity_id', '=', 'activities.id')
            ->when($this->filterGrade || $this->filterSection, function ($q) {
                $q->join('courses', 'activities.course_id', '=', 'courses.id');
                if ($this->filterGrade)   $q->where('courses.grade', $this->filterGrade);
                if ($this->filterSection) $q->where('courses.section', $this->filterSection);
            })
            ->selectRaw('AVG(grades.score / activities.max_score * 100) as avg_pct')
            ->first();

        $institutionalAvg = round($avgRow?->avg_pct ?? 0, 1);

        return compact('totalStudents', 'totalCourses', 'activitiesWeek', 'institutionalAvg');
    }

    // ─── Average per course (for Chart.js) ───────────────────────────────────

    public function getAveragePerCourse(): array
    {
        $courses = Course::query()
            ->when($this->filterGrade,   fn ($q) => $q->where('grade',   $this->filterGrade))
            ->when($this->filterSection, fn ($q) => $q->where('section', $this->filterSection))
            ->get(['id', 'subject_name', 'grade', 'section']);

        $result = [];
        foreach ($courses as $course) {
            $avg = Grade::query()
                ->join('activities', 'grades.activity_id', '=', 'activities.id')
                ->where('activities.course_id', $course->id)
                ->selectRaw('AVG(grades.score / activities.max_score * 100) as avg_pct')
                ->value('avg_pct');

            $label = $course->subject_name . ' · ' . $course->grade;
            if ($course->section) $label .= '/' . $course->section;

            $result[] = [
                'label'   => $label,
                'avg_pct' => $avg !== null ? round($avg, 1) : null,
                'has_data' => $avg !== null,
            ];
        }

        return $result;
    }

    // ─── Top 5 & Bottom 5 students ────────────────────────────────────────────

    public function getTopAndBottomStudents(): array
    {
        $subquery = Grade::query()
            ->join('activities', 'grades.activity_id', '=', 'activities.id')
            ->join('courses', 'activities.course_id', '=', 'courses.id')
            ->join('students', 'grades.student_id', '=', 'students.id')
            ->when($this->filterGrade,   fn ($q) => $q->where('courses.grade',   $this->filterGrade))
            ->when($this->filterSection, fn ($q) => $q->where('courses.section', $this->filterSection))
            ->selectRaw('
                students.id as student_id,
                students.name as student_name,
                students.grade,
                students.section,
                AVG(grades.score / activities.max_score * 100) as avg_pct,
                COUNT(grades.id) as grade_count
            ')
            ->groupBy('students.id', 'students.name', 'students.grade', 'students.section')
            ->havingRaw('COUNT(grades.id) >= 1')
            ->orderByDesc('avg_pct')
            ->get();

        return [
            'top'    => $subquery->take(5)->values(),
            'bottom' => $subquery->sortBy('avg_pct')->take(5)->values(),
        ];
    }

    // ─── Grade distribution (buckets) ─────────────────────────────────────────

    public function getGradeDistribution(): array
    {
        $grades = Grade::query()
            ->join('activities', 'grades.activity_id', '=', 'activities.id')
            ->join('courses', 'activities.course_id', '=', 'courses.id')
            ->when($this->filterGrade,   fn ($q) => $q->where('courses.grade',   $this->filterGrade))
            ->when($this->filterSection, fn ($q) => $q->where('courses.section', $this->filterSection))
            ->selectRaw('grades.score / activities.max_score * 100 as pct')
            ->pluck('pct')
            ->map(fn ($v) => (float) $v);

        $total = $grades->count();

        if ($total === 0) {
            return [
                ['label' => 'Excelente (≥90%)',   'count' => 0, 'pct' => 0, 'color' => 'emerald'],
                ['label' => 'Bueno (70–89%)',      'count' => 0, 'pct' => 0, 'color' => 'violet'],
                ['label' => 'Suficiente (50–69%)', 'count' => 0, 'pct' => 0, 'color' => 'amber'],
                ['label' => 'En riesgo (<50%)',    'count' => 0, 'pct' => 0, 'color' => 'red'],
            ];
        }

        $buckets = [
            'excelente'  => $grades->filter(fn ($v) => $v >= 90)->count(),
            'bueno'      => $grades->filter(fn ($v) => $v >= 70 && $v < 90)->count(),
            'suficiente' => $grades->filter(fn ($v) => $v >= 50 && $v < 70)->count(),
            'riesgo'     => $grades->filter(fn ($v) => $v < 50)->count(),
        ];

        return [
            ['label' => 'Excelente (≥90%)',   'count' => $buckets['excelente'],  'pct' => round($buckets['excelente']  / $total * 100), 'color' => 'emerald'],
            ['label' => 'Bueno (70–89%)',      'count' => $buckets['bueno'],      'pct' => round($buckets['bueno']      / $total * 100), 'color' => 'violet'],
            ['label' => 'Suficiente (50–69%)', 'count' => $buckets['suficiente'], 'pct' => round($buckets['suficiente'] / $total * 100), 'color' => 'amber'],
            ['label' => 'En riesgo (<50%)',    'count' => $buckets['riesgo'],     'pct' => round($buckets['riesgo']     / $total * 100), 'color' => 'red'],
        ];
    }

    // ─── At-risk students (last 3 activities, avg < 50%) ─────────────────────

    public function getAtRiskStudents(int $limit = 10): Collection
    {
        $recentActivityIds = Activity::query()
            ->when($this->filterGrade || $this->filterSection, fn ($q) =>
                $q->whereHas('course', function ($cq) {
                    if ($this->filterGrade)   $cq->where('grade',   $this->filterGrade);
                    if ($this->filterSection) $cq->where('section', $this->filterSection);
                })
            )
            ->latest()
            ->limit(30)
            ->pluck('id');

        return Grade::query()
            ->join('activities', 'grades.activity_id', '=', 'activities.id')
            ->join('students',   'grades.student_id',  '=', 'students.id')
            ->join('courses',    'activities.course_id', '=', 'courses.id')
            ->whereIn('grades.activity_id', $recentActivityIds)
            ->when($this->filterGrade,   fn ($q) => $q->where('courses.grade',   $this->filterGrade))
            ->when($this->filterSection, fn ($q) => $q->where('courses.section', $this->filterSection))
            ->selectRaw('
                students.id,
                students.name,
                students.grade,
                courses.subject_name,
                AVG(grades.score / activities.max_score * 100) as avg_pct,
                COUNT(grades.id) as grade_count
            ')
            ->groupBy('students.id', 'students.name', 'students.grade', 'courses.subject_name')
            ->havingRaw('AVG(grades.score / activities.max_score * 100) < 50')
            ->orderBy('avg_pct')
            ->limit($limit)
            ->get();
    }

    // ─── Distinct grades and sections for filter dropdowns ───────────────────

    public function getAvailableFilters(): array
    {
        return [
            'grades'   => Course::distinct()->orderBy('grade')  ->pluck('grade'),
            'sections' => Course::whereNotNull('section')
                                 ->distinct()
                                 ->orderBy('section')
                                 ->pluck('section'),
        ];
    }
}

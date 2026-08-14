<?php

namespace App\Http\Controllers\Director;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Course;
use App\Models\Grade;
use App\Models\Student;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportCardController extends Controller
{
    public function preview(int $studentId): View
    {
        $colegioId = auth()->user()->colegio_id;
        $student = Student::where('colegio_id', $colegioId)->with('courses')->findOrFail($studentId);
        $courses = $student->courses;

        $courseData = $courses->map(function (Course $course) use ($student) {
            $activities = Activity::where('course_id', $course->id)
                ->whereHas('grades', fn ($q) => $q->where('student_id', $student->id))
                ->with(['grades' => fn ($q) => $q->where('student_id', $student->id)])
                ->get(['id', 'title', 'type', 'max_score', 'weight_percentage', 'due_date']);

            $grades = Grade::with('activity')
                ->where('student_id', $student->id)
                ->whereIn('activity_id', $activities->pluck('id'))
                ->get();

            $promedio = $activities->count() > 0
                ? round($grades->avg(fn ($g) => $g->activity?->max_score > 0
                    ? ($g->score / $g->activity->max_score) * 100
                    : 0), 1)
                : 0;

            return [
                'course_name' => $course->subject_name . ' ' . $course->grade . ($course->section ? ' / ' . $course->section : ''),
                'teacher_name' => $course->teacher?->name ?? '—',
                'promedio' => $promedio,
                'activities' => $activities->map(fn ($a) => [
                    'title' => $a->title,
                    'type' => $a->type,
                    'score' => $a->grades->first()?->score ?? 0,
                    'max_score' => $a->max_score,
                    'percentage' => $a->max_score > 0
                        ? round(($a->grades->first()?->score ?? 0) / $a->max_score * 100, 1)
                        : 0,
                    'due_date' => $a->due_date?->format('d/m/Y'),
                ]),
            ];
        });

        $globalAverage = $courseData->avg('promedio');

        return view('director.report-card', compact('student', 'courseData', 'globalAverage'));
    }

    public function pdf(int $studentId)
    {
        $colegioId = auth()->user()->colegio_id;
        $student = Student::where('colegio_id', $colegioId)->with('courses')->findOrFail($studentId);
        $courses = $student->courses;

        $courseData = $courses->map(function (Course $course) use ($student) {
            $activities = Activity::where('course_id', $course->id)
                ->whereHas('grades', fn ($q) => $q->where('student_id', $student->id))
                ->with(['grades' => fn ($q) => $q->where('student_id', $student->id)])
                ->get(['id', 'title', 'type', 'max_score', 'weight_percentage', 'due_date']);

            $grades = Grade::with('activity')
                ->where('student_id', $student->id)
                ->whereIn('activity_id', $activities->pluck('id'))
                ->get();

            $promedio = $activities->count() > 0
                ? round($grades->avg(fn ($g) => $g->activity?->max_score > 0
                    ? ($g->score / $g->activity->max_score) * 100
                    : 0), 1)
                : 0;

            return [
                'course_name' => $course->subject_name . ' ' . $course->grade . ($course->section ? ' / ' . $course->section : ''),
                'teacher_name' => $course->teacher?->name ?? '—',
                'promedio' => $promedio,
                'activities' => $activities->map(fn ($a) => [
                    'title' => $a->title,
                    'type' => $a->type,
                    'score' => $a->grades->first()?->score ?? 0,
                    'max_score' => $a->max_score,
                    'percentage' => $a->max_score > 0
                        ? round(($a->grades->first()?->score ?? 0) / $a->max_score * 100, 1)
                        : 0,
                    'due_date' => $a->due_date?->format('d/m/Y'),
                ]),
            ];
        });

        $globalAverage = $courseData->avg('promedio');

        $pdf = Pdf::loadView('director.report-card-pdf', compact('student', 'courseData', 'globalAverage'));
        $pdf->setPaper('letter', 'portrait');

        return $pdf->download('boleta-' . $student->id . '-' . now()->format('Ymd') . '.pdf');
    }
}

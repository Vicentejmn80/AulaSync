<?php

namespace App\Http\Controllers\Director;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Course;
use App\Models\Grade;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $settings = $user->settings;

        $totalStudents = Student::count();
        $globalAverage = Grade::query()
            ->join('activities', 'grades.activity_id', '=', 'activities.id')
            ->where('activities.max_score', '>', 0)
            ->selectRaw('AVG((grades.score / activities.max_score) * 100) as avg_pct')
            ->value('avg_pct');

        $atRiskStudents = Grade::query()
            ->join('activities', 'grades.activity_id', '=', 'activities.id')
            ->join('students', 'grades.student_id', '=', 'students.id')
            ->where('activities.max_score', '>', 0)
            ->groupBy('students.id', 'students.name')
            ->selectRaw('students.id, students.name, AVG((grades.score / activities.max_score) * 100) as avg_pct')
            ->havingRaw('AVG((grades.score / activities.max_score) * 100) < 60')
            ->count();

        $teacherCount = User::where('role', 'profesor')->count();
        $teachersWithPendingGrades = $this->teachersWithPendingGrades();
        $teacherCompliance = $teacherCount > 0
            ? round((($teacherCount - $teachersWithPendingGrades) / $teacherCount) * 100)
            : 100;

        $kpis = [
            [
                'label' => 'Matrícula Total',
                'value' => number_format($totalStudents),
                'hint' => 'Alumnos registrados en la institución',
                'icon' => 'fa-users',
                'accent' => 'from-cyan-400 to-blue-500',
            ],
            [
                'label' => 'Promedio Global',
                'value' => $globalAverage !== null ? round((float) $globalAverage, 1) . '%' : '—',
                'hint' => 'Promedio ponderado sobre registros de notas',
                'icon' => 'fa-chart-line',
                'accent' => 'from-violet-400 to-fuchsia-500',
            ],
            [
                'label' => 'Cumplimiento Docente',
                'value' => $teacherCompliance . '%',
                'hint' => $teachersWithPendingGrades . ' docentes con actividades pendientes',
                'icon' => 'fa-clipboard-check',
                'accent' => 'from-emerald-400 to-cyan-500',
            ],
            [
                'label' => 'Riesgo Académico',
                'value' => number_format($atRiskStudents),
                'hint' => 'Alumnos con promedio menor a 60%',
                'icon' => 'fa-triangle-exclamation',
                'accent' => 'from-amber-400 to-rose-500',
            ],
        ];

        $gradePerformance = $this->gradePerformance();
        $lowPerformingRooms = $this->lowPerformingRooms();
        $institution = [
            'name' => $settings?->nombre_institucion ?? 'Nova Academy',
            'period' => data_get($settings?->preferencias, 'periodo_academico', now()->year . '-' . now()->copy()->addYear()->year),
            'campuses' => data_get($settings?->preferencias, 'cantidad_sedes', 1),
        ];

        return view('director.dashboard', compact(
            'user',
            'settings',
            'institution',
            'kpis',
            'gradePerformance',
            'lowPerformingRooms',
            'teachersWithPendingGrades'
        ));
    }

    private function teachersWithPendingGrades(): int
    {
        return Activity::query()
            ->where('activities.type', '!=', 'clase')
            ->join('courses', 'activities.course_id', '=', 'courses.id')
            ->leftJoin('course_student', 'courses.id', '=', 'course_student.course_id')
            ->leftJoin('grades', function ($join) {
                $join->on('grades.activity_id', '=', 'activities.id')
                    ->on('grades.student_id', '=', 'course_student.student_id');
            })
            ->groupBy('activities.teacher_id', 'activities.id')
            ->havingRaw('COUNT(course_student.student_id) > COUNT(grades.id)')
            ->select('activities.teacher_id')
            ->get()
            ->pluck('teacher_id')
            ->unique()
            ->count();
    }

    private function gradePerformance(): array
    {
        $labels = ['1ro', '2do', '3ro', '4to', '5to'];

        return collect($labels)->map(function (string $grade) {
            $avg = Grade::query()
                ->join('activities', 'grades.activity_id', '=', 'activities.id')
                ->join('courses', 'activities.course_id', '=', 'courses.id')
                ->where('activities.max_score', '>', 0)
                ->where('courses.grade', 'like', $grade . '%')
                ->selectRaw('AVG((grades.score / activities.max_score) * 100) as avg_pct')
                ->value('avg_pct');

            return [
                'grade' => $grade,
                'average' => $avg !== null ? round((float) $avg, 1) : 0,
                'has_data' => $avg !== null,
            ];
        })->values()->toArray();
    }

    private function lowPerformingRooms(): array
    {
        return Grade::query()
            ->join('activities', 'grades.activity_id', '=', 'activities.id')
            ->join('courses', 'activities.course_id', '=', 'courses.id')
            ->where('activities.max_score', '>', 0)
            ->groupBy('courses.id', 'courses.subject_name', 'courses.grade', 'courses.section')
            ->selectRaw("
                courses.id,
                courses.subject_name,
                courses.grade,
                courses.section,
                AVG((grades.score / activities.max_score) * 100) as avg_pct,
                COUNT(grades.id) as grades_count
            ")
            ->havingRaw('COUNT(grades.id) > 0')
            ->orderBy('avg_pct')
            ->limit(3)
            ->get()
            ->map(fn ($room) => [
                'name' => trim($room->subject_name . ' · ' . $room->grade . ($room->section ? ' / ' . $room->section : '')),
                'average' => round((float) $room->avg_pct, 1),
                'grades_count' => (int) $room->grades_count,
                'recommendation' => $this->recommendationFor((float) $room->avg_pct),
            ])
            ->toArray();
    }

    private function recommendationFor(float $average): string
    {
        return match (true) {
            $average < 50 => 'Intervención prioritaria y reunión de seguimiento esta semana.',
            $average < 65 => 'Reforzar contenidos base y revisar evidencias pendientes.',
            default => 'Monitorear tendencia y compartir buenas prácticas con docentes.',
        };
    }
}

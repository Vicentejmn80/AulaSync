<?php

namespace App\Services;

use App\Models\Grade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StudentGradeAccumulationService
{
    public function supportsPivotAccumulated(): bool
    {
        return Schema::hasColumn('course_student', 'nota_actual')
            && Schema::hasColumn('course_student', 'promedio_acumulado');
    }

    public function calculateForStudent(int $courseId, int $studentId): float
    {
        $weighted = Grade::query()
            ->join('activities', 'grades.activity_id', '=', 'activities.id')
            ->where('activities.course_id', $courseId)
            ->where('grades.student_id', $studentId)
            ->where('activities.type', '!=', 'clase')
            ->selectRaw('COALESCE(SUM((grades.score * activities.weight_percentage) / 100.0), 0) as weighted_total')
            ->value('weighted_total');

        return round((float) $weighted, 2);
    }

    /**
     * @return array<int, float> student_id => accumulated
     */
    public function calculateForCourse(int $courseId): array
    {
        $rows = Grade::query()
            ->join('activities', 'grades.activity_id', '=', 'activities.id')
            ->where('activities.course_id', $courseId)
            ->where('activities.type', '!=', 'clase')
            ->groupBy('grades.student_id')
            ->selectRaw('grades.student_id, COALESCE(SUM((grades.score * activities.weight_percentage) / 100.0), 0) as weighted_total')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->student_id] = round((float) $row->weighted_total, 2);
        }

        return $map;
    }

    public function updateForStudent(int $courseId, int $studentId): float
    {
        $weighted = $this->calculateForStudent($courseId, $studentId);

        if ($this->supportsPivotAccumulated()) {
            DB::table('course_student')
                ->where('course_id', $courseId)
                ->where('student_id', $studentId)
                ->update([
                    'nota_actual' => $weighted,
                    'promedio_acumulado' => $weighted,
                ]);
        }

        return $weighted;
    }
}

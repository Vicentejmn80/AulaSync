<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Course;
use App\Models\Student;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Computes attendance percentages shared by the teacher hub, Director, and Representante views.
 * "Tardy" counts toward the attendance percentage (the student was present, just late);
 * only "absent" counts against it.
 */
class AttendanceSummaryService
{
    /**
     * @return array{present:int,absent:int,tardy:int,total:int,percentage:float|null}
     */
    public function percentForStudentInCourse(Student $student, Course $course, ?Carbon $from = null): array
    {
        if (! Schema::hasTable('attendances')) {
            return $this->emptySummary();
        }

        $query = Attendance::where('course_id', $course->id)->where('student_id', $student->id);
        if ($from) {
            $query->whereDate('attended_on', '>=', $from->toDateString());
        }

        $counts = $query->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return $this->summarizeCounts($counts);
    }

    /**
     * Bulk variant: attendance summary for every student with recorded attendance in a course, keyed by student_id.
     *
     * @return array<int, array{present:int,absent:int,tardy:int,total:int,percentage:float|null}>
     */
    public function percentForCourse(Course $course, ?Carbon $from = null): array
    {
        if (! Schema::hasTable('attendances')) {
            return [];
        }

        $query = Attendance::where('course_id', $course->id);
        if ($from) {
            $query->whereDate('attended_on', '>=', $from->toDateString());
        }

        $rows = $query->selectRaw('student_id, status, count(*) as total')
            ->groupBy('student_id', 'status')
            ->get()
            ->groupBy('student_id');

        $result = [];
        foreach ($rows as $studentId => $statuses) {
            $result[(int) $studentId] = $this->summarizeCounts($statuses->pluck('total', 'status'));
        }

        return $result;
    }

    /**
     * @param  Collection<string, int>  $counts
     * @return array{present:int,absent:int,tardy:int,total:int,percentage:float|null}
     */
    private function summarizeCounts(Collection $counts): array
    {
        $present = (int) ($counts['present'] ?? 0);
        $absent = (int) ($counts['absent'] ?? 0);
        $tardy = (int) ($counts['tardy'] ?? 0);
        $total = $present + $absent + $tardy;

        return [
            'present' => $present,
            'absent' => $absent,
            'tardy' => $tardy,
            'total' => $total,
            'percentage' => $total > 0 ? round((($present + $tardy) / $total) * 100, 1) : null,
        ];
    }

    private function emptySummary(): array
    {
        return ['present' => 0, 'absent' => 0, 'tardy' => 0, 'total' => 0, 'percentage' => null];
    }
}

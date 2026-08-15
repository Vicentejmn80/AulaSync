<?php

namespace App\Http\Controllers\Director;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class AttendanceDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $colegioId = $user->colegio_id;
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();
        $gradeFilter = $request->string('grade')->toString();

        $byGrade = collect();
        $chronic = collect();
        $today = [
            'present' => 0,
            'absent' => 0,
            'tardy' => 0,
            'rate' => null,
        ];
        $grades = collect();

        if (Schema::hasTable('attendances')) {
            $grades = Student::where('colegio_id', $colegioId)
                ->whereNotNull('grade')
                ->distinct()
                ->orderBy('grade')
                ->pluck('grade');

            $todayQuery = Attendance::where('colegio_id', $colegioId)
                ->whereDate('attended_on', now()->toDateString());
            $today['present'] = (clone $todayQuery)->where('status', 'present')->count();
            $today['absent'] = (clone $todayQuery)->where('status', 'absent')->count();
            $today['tardy'] = (clone $todayQuery)->where('status', 'tardy')->count();
            $totalToday = $today['present'] + $today['absent'] + $today['tardy'];
            $today['rate'] = $totalToday > 0
                ? round((($today['present'] + $today['tardy']) / $totalToday) * 100, 1)
                : null;

            $byGrade = Attendance::query()
                ->join('students', 'attendances.student_id', '=', 'students.id')
                ->where('attendances.colegio_id', $colegioId)
                ->whereBetween('attendances.attended_on', [$monthStart, $monthEnd])
                ->when($gradeFilter !== '', fn ($q) => $q->where('students.grade', $gradeFilter))
                ->select(
                    'students.grade',
                    DB::raw("SUM(CASE WHEN attendances.status = 'present' THEN 1 ELSE 0 END) as presents"),
                    DB::raw("SUM(CASE WHEN attendances.status = 'absent' THEN 1 ELSE 0 END) as absents"),
                    DB::raw("SUM(CASE WHEN attendances.status = 'tardy' THEN 1 ELSE 0 END) as tardies"),
                    DB::raw('COUNT(*) as total')
                )
                ->groupBy('students.grade')
                ->orderBy('students.grade')
                ->get()
                ->map(function ($row) {
                    $total = (int) $row->total;
                    $row->rate = $total > 0
                        ? round((((int) $row->presents + (int) $row->tardies) / $total) * 100, 1)
                        : 0;
                    return $row;
                });

            $chronicQuery = Attendance::query()
                ->join('students', 'attendances.student_id', '=', 'students.id')
                ->where('attendances.colegio_id', $colegioId)
                ->where('attendances.status', 'absent')
                ->whereBetween('attendances.attended_on', [$monthStart, $monthEnd])
                ->when($gradeFilter !== '', fn ($q) => $q->where('students.grade', $gradeFilter))
                ->select(
                    'students.id',
                    'students.name',
                    'students.grade',
                    'students.section',
                    DB::raw('COUNT(*) as absences')
                )
                ->groupBy('students.id', 'students.name', 'students.grade', 'students.section')
                ->havingRaw('COUNT(*) > 3')
                ->orderByDesc('absences');

            $chronic = $chronicQuery->limit(40)->get();
        }

        return view('director.attendance', compact(
            'user',
            'byGrade',
            'chronic',
            'today',
            'grades',
            'gradeFilter',
            'monthStart'
        ));
    }
}

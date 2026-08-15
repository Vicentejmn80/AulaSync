<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\ReportCardService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\View\View;

class ReportCardController extends Controller
{
    public function __construct(private ReportCardService $reportCards)
    {
    }

    public function preview(int $studentId): View
    {
        $student = $this->findOwnedStudent($studentId);
        $payload = $this->reportCards->build($student);
        $courseData = $payload['courseData'];
        $globalAverage = $payload['globalAverage'];

        return view('director.report-card', compact('student', 'courseData', 'globalAverage'));
    }

    public function pdf(int $studentId)
    {
        $student = $this->findOwnedStudent($studentId);
        $payload = $this->reportCards->build($student);
        $courseData = $payload['courseData'];
        $globalAverage = $payload['globalAverage'];

        $pdf = Pdf::loadView('director.report-card-pdf', compact('student', 'courseData', 'globalAverage'));
        $pdf->setPaper('letter', 'portrait');

        return $pdf->download('boleta-'.$student->id.'-'.now()->format('Ymd').'.pdf');
    }

    private function findOwnedStudent(int $studentId): Student
    {
        return Student::query()
            ->where(function ($q) {
                $q->where('teacher_id', auth()->id())
                    ->orWhereHas('courses', fn ($c) => $c->where('teacher_id', auth()->id()));
            })
            ->with(['courses.teacher', 'colegio'])
            ->findOrFail($studentId);
    }
}

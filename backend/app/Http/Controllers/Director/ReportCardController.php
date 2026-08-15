<?php

namespace App\Http\Controllers\Director;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\ReportCardService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportCardController extends Controller
{
    public function __construct(private ReportCardService $reportCards)
    {
    }

    public function index(Request $request): View
    {
        $colegioId = auth()->user()->colegio_id;
        $query = Student::where('colegio_id', $colegioId)->with(['courses.teacher']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('grade', 'like', "%{$search}%")
                    ->orWhere('document_id', 'like', "%{$search}%");
            });
        }

        if ($request->filled('grade')) {
            $query->where('grade', $request->grade);
        }

        $students = $query->orderBy('name')->paginate(30)->withQueryString();
        $grades = Student::where('colegio_id', $colegioId)->distinct()->orderBy('grade')->pluck('grade');

        $rows = $students->getCollection()->map(function (Student $student) {
            $payload = $this->reportCards->build($student);

            return [
                'student' => $student,
                'globalAverage' => $payload['globalAverage'],
                'courses' => $payload['courseData']->count(),
                'has_grades' => $payload['courseData']->contains(fn ($c) => collect($c['activities'])->contains(fn ($a) => $a['has_score'])),
            ];
        });

        return view('director.boletines', compact('students', 'rows', 'grades'));
    }

    public function preview(int $studentId): View
    {
        $student = $this->findSchoolStudent($studentId);
        $payload = $this->reportCards->build($student);
        $courseData = $payload['courseData'];
        $globalAverage = $payload['globalAverage'];

        return view('director.report-card', compact('student', 'courseData', 'globalAverage'));
    }

    public function pdf(int $studentId)
    {
        $student = $this->findSchoolStudent($studentId);
        $payload = $this->reportCards->build($student);
        $courseData = $payload['courseData'];
        $globalAverage = $payload['globalAverage'];

        $pdf = Pdf::loadView('director.report-card-pdf', compact('student', 'courseData', 'globalAverage'));
        $pdf->setPaper('letter', 'portrait');

        return $pdf->download('boleta-'.$student->id.'-'.now()->format('Ymd').'.pdf');
    }

    private function findSchoolStudent(int $studentId): Student
    {
        return Student::where('colegio_id', auth()->user()->colegio_id)
            ->with(['courses.teacher', 'colegio'])
            ->findOrFail($studentId);
    }
}

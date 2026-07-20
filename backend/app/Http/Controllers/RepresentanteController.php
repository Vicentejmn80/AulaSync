<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\View\View;

class RepresentanteController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();
        $familyCode = $user->family_code;

        $students = collect();
        $school = null;

        if ($familyCode) {
            $students = Student::where('family_code', $familyCode)
                ->with('colegio', 'courses')
                ->get();

            $firstStudent = $students->first();
            if ($firstStudent && $firstStudent->colegio) {
                $school = $firstStudent->colegio;
            }
        }

        return view('representante.dashboard', compact('students', 'school', 'familyCode'));
    }
}

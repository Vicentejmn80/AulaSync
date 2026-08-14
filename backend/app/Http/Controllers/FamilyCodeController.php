<?php

namespace App\Http\Controllers;

use App\Helpers\InviteCodeHelper;
use App\Models\Colegio;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FamilyCodeController extends Controller
{
    public function validateFamilyCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'family_code' => 'required|string|max:20',
        ]);

        $code = InviteCodeHelper::normalize($validated['family_code']);

        // CNX- codes are institution codes, not family codes
        if (str_starts_with($code, 'CNX-')) {
            return response()->json([
                'valid' => false,
                'message' => 'Este código corresponde a una institución, no a un representante. Si eres docente o director, selecciona el rol correspondiente.',
            ], 422);
        }

        $student = Student::where('family_code', $code)->first();

        if (! $student) {
            return response()->json([
                'valid' => false,
                'message' => 'Código no encontrado. Verifica el código en la boleta de tu representado.',
            ], 404);
        }

        $school = Colegio::find($student->colegio_id);

        return response()->json([
            'valid' => true,
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'grade' => $student->grade,
                'section' => $student->section,
            ],
            'school' => $school ? [
                'id' => $school->id,
                'name' => $school->name,
            ] : null,
        ]);
    }
}

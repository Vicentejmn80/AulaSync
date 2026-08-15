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
            'school_code' => 'nullable|string|max:20',
        ]);

        $code = InviteCodeHelper::normalize($validated['family_code']);

        if (str_starts_with($code, 'CNX-') || str_starts_with($code, 'DEMO-')) {
            return response()->json([
                'valid' => false,
                'message' => 'Este código corresponde a una institución, no a una familia. Primero valida el código del colegio.',
            ], 422);
        }

        $colegio = null;
        if (! empty($validated['school_code'])) {
            $colegio = Colegio::where('invite_code', InviteCodeHelper::normalize($validated['school_code']))->first();
            if (! $colegio) {
                return response()->json([
                    'valid' => false,
                    'message' => 'El código de colegio no es válido.',
                ], 404);
            }
        }

        $query = Student::query()->where('family_code', $code);
        if ($colegio) {
            $query->where('colegio_id', $colegio->id);
        }

        $students = $query->orderBy('name')->get(['id', 'name', 'grade', 'section', 'family_code', 'colegio_id']);

        if ($students->isEmpty()) {
            return response()->json([
                'valid' => false,
                'message' => 'No encontramos alumnos con ese código familiar en este colegio. Pide el código NV- al director o al docente.',
            ], 404);
        }

        $school = $colegio ?? Colegio::find($students->first()->colegio_id);

        return response()->json([
            'valid' => true,
            'students' => $students->map(fn (Student $student) => [
                'id' => $student->id,
                'name' => $student->name,
                'grade' => $student->grade,
                'section' => $student->section,
            ])->values(),
            'school' => $school ? [
                'id' => $school->id,
                'name' => $school->name,
            ] : null,
        ]);
    }
}

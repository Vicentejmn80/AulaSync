<?php

namespace App\Http\Controllers;

use App\Models\Colegio;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class CodesRevealController extends Controller
{
    /**
     * Verify school PIN and return a sensitive invite/family code for 20s client display.
     */
    public function reveal(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pin' => ['required', 'string', 'max:12'],
            'type' => ['required', 'in:school,family'],
            'student_id' => ['nullable', 'integer'],
        ]);

        $user = $request->user();
        $colegio = Colegio::find($user->colegio_id);

        if (! $colegio) {
            return response()->json(['ok' => false, 'error' => 'No tienes un colegio vinculado.'], 422);
        }

        if (! $colegio->codes_pin || ! Hash::check($validated['pin'], $colegio->codes_pin)) {
            return response()->json(['ok' => false, 'error' => 'PIN incorrecto.'], 403);
        }

        if ($validated['type'] === 'school') {
            if (! in_array($user->role, ['director', 'profesor'], true)) {
                return response()->json(['ok' => false, 'error' => 'No autorizado.'], 403);
            }

            return response()->json([
                'ok' => true,
                'label' => 'Código de colegio',
                'code' => $colegio->invite_code,
                'ttl_seconds' => 20,
            ]);
        }

        $studentId = (int) ($validated['student_id'] ?? 0);
        $student = Student::query()
            ->where('colegio_id', $colegio->id)
            ->where('id', $studentId)
            ->first();

        if (! $student || ! $student->family_code) {
            return response()->json(['ok' => false, 'error' => 'No se encontró el código familiar.'], 404);
        }

        if ($user->role === 'profesor') {
            $teachesStudent = $student->courses()
                ->where('teacher_id', $user->id)
                ->exists();

            if (! $teachesStudent) {
                return response()->json(['ok' => false, 'error' => 'No autorizado para este alumno.'], 403);
            }
        } elseif ($user->role !== 'director') {
            return response()->json(['ok' => false, 'error' => 'No autorizado.'], 403);
        }

        return response()->json([
            'ok' => true,
            'label' => 'Código familiar',
            'code' => $student->family_code,
            'student_name' => $student->name,
            'ttl_seconds' => 20,
        ]);
    }

    public function updatePin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_pin' => ['required', 'string', 'max:12'],
            'new_pin' => ['required', 'string', 'regex:/^\d{4,6}$/'],
        ]);

        $user = $request->user();
        if ($user->role !== 'director') {
            return response()->json(['ok' => false, 'error' => 'Solo el director puede cambiar el PIN.'], 403);
        }

        $colegio = Colegio::find($user->colegio_id);
        if (! $colegio) {
            return response()->json(['ok' => false, 'error' => 'Colegio no encontrado.'], 404);
        }

        if (! $colegio->codes_pin || ! Hash::check($validated['current_pin'], $colegio->codes_pin)) {
            return response()->json(['ok' => false, 'error' => 'PIN actual incorrecto.'], 403);
        }

        $colegio->update([
            'codes_pin' => Hash::make($validated['new_pin']),
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'PIN actualizado. Úsalo para revelar códigos por 20 segundos.',
        ]);
    }
}

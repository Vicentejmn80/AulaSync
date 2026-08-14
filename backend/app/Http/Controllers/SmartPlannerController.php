<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Subject;

class SmartPlannerController extends Controller
{
    public function parseText(Request $request) {
        $texto = $request->input('plan_text');
        
        // Aquí es donde sucede la lógica. 
        // Por ahora, haremos una búsqueda simple de palabras clave 
        // para que veas que funciona sin romper nada.
        
        $materiasEncontradas = [];
        $subjects = Subject::all(); // Traemos las materias que ya creaste

        foreach ($subjects as $subject) {
            if (stripos($texto, $subject->name) !== false) {
                $materiasEncontradas[] = $subject->name;
            }
        }

        return response()->json([
            'status' => 'success',
            'mensaje' => 'He detectado estas materias en tu plan: ' . implode(', ', $materiasEncontradas),
            'original' => $texto
        ]);
    }
}
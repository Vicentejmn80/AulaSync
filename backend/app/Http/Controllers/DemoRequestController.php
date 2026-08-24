<?php

namespace App\Http\Controllers;

use App\Models\DemoRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DemoRequestController extends Controller
{
    /**
     * Guarda una solicitud de demo desde la landing (espejo local de Formspree).
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'apellido' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150'],
            'telefono' => ['required', 'string', 'max:40'],
            'nombre_colegio' => ['required', 'string', 'max:150'],
            'estado_region' => ['required', 'string', 'max:100'],
        ]);

        DemoRequest::create([
            'name' => $data['nombre'],
            'last_name' => $data['apellido'],
            'email' => $data['email'],
            'phone' => $data['telefono'],
            'school_name' => $data['nombre_colegio'],
            'estado_region' => $data['estado_region'],
            'role' => 'Solicitud demo',
        ]);

        return response()->json([
            'success' => true,
            'message' => '¡Gracias! Tu solicitud ha sido enviada. Te contactaremos pronto.',
        ]);
    }
}

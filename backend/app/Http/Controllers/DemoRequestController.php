<?php

namespace App\Http\Controllers;

use App\Models\DemoRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DemoRequestController extends Controller
{
    /**
     * Guarda una solicitud de demo desde la landing pública.
     * No requiere autenticación. No envía correos (aún no configurado);
     * las solicitudes quedan registradas para seguimiento manual del equipo.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'school_name' => ['required', 'string', 'max:150'],
            'role' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
            'school_size' => ['nullable', 'string', 'max:60'],
        ]);

        DemoRequest::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Recibimos tu solicitud. Nuestro equipo te contactará pronto.',
        ]);
    }
}

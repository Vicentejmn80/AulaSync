<?php

namespace App\Http\Controllers;

use App\Models\UserSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class OnboardingController extends Controller
{
    public function show()
    {
        if (Auth::check() && Auth::user()->onboarding_completed) {
            return redirect()->route('dashboard');
        }

        return view('onboarding.wizard');
    }

    public function save(Request $request)
    {
        $validated = $request->validate([
            'role' => 'required|in:profesor,director',
            'nivel_educativo' => 'nullable|string|max:120',
            'materias' => 'nullable|array',
            'materias.*' => 'string|max:120',
            'otra_materia' => 'nullable|string|max:120',
            'materias_asignadas' => 'nullable|array',
            'materias_asignadas.*' => 'string|max:120',
            'cursos' => 'nullable|array',
            'cursos.*' => 'string|max:120',
            'dias' => 'nullable|array',
            'dias.*' => 'string|max:20',
            'nombre_institucion' => 'nullable|string|max:255',
            'cantidad_sedes' => 'nullable|integer|min:1|max:500',
            'periodo_academico' => 'nullable|string|max:120',
            'modelo_pedagogico' => 'nullable|string|max:255',
            'cantidad_docentes' => 'nullable|integer|min:1|max:5000',
            'vision_pedagogica' => 'nullable|string|max:2000',
            'clases_semana' => 'nullable|integer|min:1|max:20',
            'duracion_clase' => 'nullable|integer|min:15|max:240',
        ]);

        if ($validated['role'] === 'profesor') {
            $request->validate([
                'materias' => 'required|array|min:1',
                'cursos' => 'required|array|min:1',
            ]);
            
            // Procesar materias: Si "otro" está presente, validar y reemplazar
            $materias = $validated['materias'] ?? [];
            
            if (in_array('otro', $materias)) {
                $request->validate([
                    'otra_materia' => 'required|string|min:2|max:120',
                ]);
                
                // Reemplazar 'otro' con el valor custom
                $materias = array_filter($materias, fn($m) => $m !== 'otro');
                $materias[] = trim($validated['otra_materia']);
            }
            
            $validated['materias'] = $materias;
        } else {
            $request->validate([
                'nombre_institucion' => 'required|string|max:255',
                'cantidad_sedes' => 'required|integer|min:1|max:500',
                'periodo_academico' => 'required|string|max:120',
            ]);
        }

        try {
            $user = auth()->user();
            if (! $user) {
                throw new \Exception('Error crítico: No hay usuario autenticado durante el onboarding.');
            }

            $role = $validated['role'];
            $materias = $validated['materias'] ?? [];
            $materiasAsignadas = $validated['materias_asignadas'] ?? $materias;
            $diasClase = $validated['dias'] ?? ['lunes', 'martes', 'miercoles', 'jueves', 'viernes'];
            $nombreInstitucion = $validated['nombre_institucion'] ?? null;
            $modeloPedagogico = $validated['modelo_pedagogico'] ?? null;

            $user->settings()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'nivel_educativo' => $validated['nivel_educativo'] ?? null,
                    'materias' => $role === 'profesor' ? $materias : null,
                    'materias_asignadas' => $materiasAsignadas,
                    'cursos_grados' => $role === 'profesor' ? ($validated['cursos'] ?? []) : null,
                    'dias_clase' => $role === 'profesor' ? $diasClase : null,
                    'estilo_pedagogico' => $validated['modelo_pedagogico'] ?? 'inicio_desarrollo_cierre',
                    'modelo_pedagogico' => $modeloPedagogico,
                    'nombre_institucion' => $nombreInstitucion,
                    'tono' => $request->input('tono', 'amigable'),
                    'clases_semana' => (int) ($validated['clases_semana'] ?? 5),
                    'duracion_clase_min' => (int) ($validated['duracion_clase'] ?? 60),
                    'preferencias' => [
                        'horarios' => $request->input('horarios', []),
                        'incluir' => $request->input('incluir', []),
                        'cantidad_docentes' => $validated['cantidad_docentes'] ?? null,
                        'cantidad_sedes' => $validated['cantidad_sedes'] ?? null,
                        'periodo_academico' => $validated['periodo_academico'] ?? null,
                        'logo_placeholder' => 'nova-institution-placeholder',
                        'vision_pedagogica' => $validated['vision_pedagogica'] ?? null,
                    ],
                ],
            );

            $user->update([
                'role' => $role,
                'nivel_educativo' => $validated['nivel_educativo'] ?? null,
                'asignatura_principal' => ! empty($materiasAsignadas) ? implode(',', $materiasAsignadas) : null,
                'horario_clases' => $role === 'profesor' ? $diasClase : [],
            ]);

            $user = auth()->user();
            $user->onboarding_completed = true;
            $user->save();
            $user->refresh(); // Forzar actualización de datos
            if (! $user->onboarding_completed) {
                throw new \Exception('Error crítico: La base de datos no actualizó el estado de onboarding.');
            }

            session()->forget('onboarding_status');
            Log::info('Onboarding completado para el usuario: ' . auth()->id());

            return redirect()->to($role === 'director' ? '/director/dashboard' : '/dashboard')
                ->with('success', '¡Bienvenido!');
        } catch (\Throwable $e) {
            Log::error('Fallo crítico en onboarding', [
                'user_id' => auth()->id(),
                'message' => $e->getMessage(),
            ]);

            return back()->withInput()->withErrors([
                'onboarding' => $e->getMessage(),
            ]);
        }
    }
}
<?php

namespace App\Http\Controllers;

use App\Helpers\InviteCodeHelper;
use App\Models\Colegio;
use App\Models\Student;
use App\Models\User;
use App\Models\UserSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OnboardingController extends Controller
{
    public function show()
    {
        if (Auth::check() && Auth::user()->onboarding_completed) {
            return redirect()->route('dashboard');
        }

        $preselectedRole = Auth::user()?->role;
        if (! in_array($preselectedRole, ['profesor', 'director', 'representante'], true)) {
            $preselectedRole = '';
        }

        return view('onboarding.wizard', compact('preselectedRole'));
    }

    public function save(Request $request)
    {
        Log::info('Onboarding save - datos recibidos', $request->all());

        $validated = $request->validate([
            'role' => 'required|in:profesor,director,representante',
            'school_code' => 'nullable|string|max:20',
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
            'family_code' => 'nullable|string|max:20',
            'student_ids' => 'nullable|array',
            'student_ids.*' => 'integer',
        ]);

        if ($validated['role'] === 'profesor') {
            $request->validate([
                'school_code' => 'required|string|max:20',
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
        } elseif ($validated['role'] === 'director') {
            $request->validate([
                'nombre_institucion' => 'required|string|max:255',
                'cantidad_sedes' => 'required|integer|min:1|max:500',
                'periodo_academico' => 'required|string|max:120',
            ]);
        } elseif ($validated['role'] === 'representante') {
            $request->validate([
                'school_code' => 'required|string|max:20',
                'family_code' => 'required|string|max:20',
                'student_ids' => 'required|array|min:1',
                'student_ids.*' => 'integer',
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
            $colegioId = null;
            $inviteCode = null;
            $familyCode = null;
            $linkedStudents = collect();

            if ($role === 'director') {
                $colegio = $this->createOrUpdateDirectorSchool(
                    user: $user,
                    nombreInstitucion: (string) $validated['nombre_institucion']
                );

                $colegioId = $colegio->id;
                $inviteCode = $colegio->invite_code;
                $nombreInstitucion = $colegio->name;
            }

            if ($role === 'profesor') {
                $colegio = Colegio::where('invite_code', InviteCodeHelper::normalize((string) $validated['school_code']))
                    ->first();

                if (! $colegio) {
                    return back()->withInput()->withErrors([
                        'school_code' => 'El código de escuela no es válido. Verifica e inténtalo de nuevo.',
                    ]);
                }

                $colegioId = $colegio->id;
                $nombreInstitucion = $colegio->name;
            }

            if ($role === 'representante') {
                $colegio = Colegio::where('invite_code', InviteCodeHelper::normalize((string) $validated['school_code']))
                    ->first();

                if (! $colegio) {
                    return back()->withInput()->withErrors([
                        'school_code' => 'El código de colegio no es válido.',
                    ]);
                }

                $familyCode = InviteCodeHelper::normalize((string) $validated['family_code']);
                $studentIds = collect($validated['student_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique();

                $linkedStudents = Student::where('colegio_id', $colegio->id)
                    ->where('family_code', $familyCode)
                    ->whereIn('id', $studentIds)
                    ->get();

                if ($linkedStudents->isEmpty() || $linkedStudents->count() !== $studentIds->count()) {
                    return back()->withInput()->withErrors([
                        'family_code' => 'Los alumnos seleccionados no coinciden con ese código familiar en este colegio.',
                    ]);
                }

                $colegioId = $colegio->id;
                $nombreInstitucion = $colegio->name;
            }

            if ($role !== 'representante') {
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
            }

            $updateData = [
                'role' => $role,
                'colegio_id' => $colegioId ?? $user->colegio_id,
                'nivel_educativo' => $validated['nivel_educativo'] ?? null,
                'asignatura_principal' => ! empty($materiasAsignadas) ? implode(',', $materiasAsignadas) : null,
                'horario_clases' => ($role === 'profesor' || $role === 'representante') ? $diasClase : [],
            ];

            if ($role === 'representante') {
                $updateData['family_code'] = $familyCode;
            }

            $user->update($updateData);

            if ($role === 'representante' && $linkedStudents->isNotEmpty()) {
                $user->representedStudents()->sync(
                    $linkedStudents->mapWithKeys(fn (Student $student) => [
                        $student->id => ['relationship' => 'representante'],
                    ])->all()
                );
            }

            $user = auth()->user();
            DB::table('users')->where('id', $user->id)->update(['onboarding_completed' => DB::raw('true')]);
            $user->refresh(); // Forzar actualización de datos
            if (! $user->onboarding_completed) {
                throw new \Exception('Error crítico: La base de datos no actualizó el estado de onboarding.');
            }

            session()->forget('onboarding_status');
            Log::info('Onboarding completado para el usuario: ' . auth()->id());

            if ($role === 'director') {
                return redirect()->route('onboarding.director_success', [
                    'invite_code' => $inviteCode,
                    'school' => $nombreInstitucion,
                ]);
            }

            return redirect()->to('/dashboard')
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

    public function validateSchoolCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'school_code' => 'required|string|max:20',
        ]);

        $code = InviteCodeHelper::normalize($validated['school_code']);
        $colegio = Colegio::with('director:id,name')
            ->where('invite_code', $code)
            ->first();

        if (! $colegio) {
            return response()->json([
                'valid' => false,
                'message' => 'Código no encontrado.',
            ], 404);
        }

        $directorName = $colegio->director?->name;

        if (! $directorName) {
            $directorName = User::where('colegio_id', $colegio->id)
                ->where('role', 'director')
                ->value('name');
        }

        return response()->json([
            'valid' => true,
            'school' => [
                'id' => $colegio->id,
                'name' => $colegio->name,
                'invite_code' => $colegio->invite_code,
            ],
            'director' => $directorName,
        ]);
    }

    public function directorSuccess(Request $request)
    {
        $inviteCode = $request->query('invite_code');
        $schoolName = $request->query('school', 'tu institución');

        if (! $inviteCode) {
            return redirect()->route('dashboard');
        }

        return view('onboarding.director-success', compact('inviteCode', 'schoolName'));
    }

    public function joinAsDemo(Request $request)
    {
        $user = auth()->user();
        if (! $user) {
            return response()->json(['error' => 'No autenticado'], 401);
        }

        $demoColegio = Colegio::firstOrCreate(
            ['name' => 'Modo Demo Libre'],
            [
                'invite_code' => 'DEMO-' . strtoupper(substr(md5(uniqid()), 0, 6)),
                'director_user_id' => null,
            ]
        );

        $user->update([
            'role' => 'profesor',
            'colegio_id' => $demoColegio->id,
            'onboarding_completed' => true,
        ]);

        $user->settings()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'nombre_institucion' => 'Modo Demo Libre',
                'materias' => [],
                'cursos_grados' => [],
                'dias_clase' => ['lunes', 'martes', 'miercoles', 'jueves', 'viernes'],
                'tono' => 'amigable',
                'clases_semana' => 5,
                'duracion_clase_min' => 60,
                'preferencias' => [
                    'demo_mode' => true,
                ],
            ]
        );

        session()->forget('onboarding_status');

        return response()->json([
            'redirect' => route('teacher.hub'),
        ]);
    }

    private function createOrUpdateDirectorSchool(User $user, string $nombreInstitucion): Colegio
    {
        $cleanName = trim($nombreInstitucion);
        $colegio = Colegio::where('director_user_id', $user->id)->first();

        if ($colegio) {
            $colegio->name = $cleanName;
            if (! $colegio->invite_code) {
                $colegio->invite_code = InviteCodeHelper::generateUnique($cleanName);
            }
            $colegio->save();

            return $colegio;
        }

        return Colegio::create([
            'name' => $cleanName,
            'invite_code' => InviteCodeHelper::generateUnique($cleanName),
            'director_user_id' => $user->id,
        ]);
    }
}
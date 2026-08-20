<?php

namespace App\Http\Controllers;

use App\Helpers\InviteCodeHelper;
use App\Models\Colegio;
use App\Models\Course;
use App\Models\Student;
use App\Models\TeacherInvite;
use App\Models\User;
use App\Models\UserSettings;
use App\Services\TeacherInviteClaimService;
use App\Support\LessonTemplate;
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
            'teacher_invite_code' => 'nullable|string|max:20',
            'lesson_template' => 'nullable|string|max:50',
            'family_code' => 'nullable|string|max:20',
            'student_ids' => 'nullable|array',
            'student_ids.*' => 'integer',
        ]);

        if ($validated['role'] === 'profesor') {
            $request->validate([
                'school_code' => 'required|string|max:20',
                'teacher_invite_code' => 'required|string|max:20',
                'dias' => 'required|array|min:1',
                'lesson_template' => 'nullable|string|max:50',
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
            $lessonTemplate = LessonTemplate::normalize(
                (string) ($validated['lesson_template'] ?? $modeloPedagogico ?? LessonTemplate::CLASSIC)
            );
            $colegioId = null;
            $inviteCode = null;
            $familyCode = null;
            $linkedStudents = collect();
            $teacherInvite = null;

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
                $pair = $this->resolveTeacherCodes(
                    (string) $validated['school_code'],
                    (string) ($validated['teacher_invite_code'] ?? ''),
                    $user
                );

                if (isset($pair['error'])) {
                    return back()->withInput()->withErrors([
                        $pair['field'] => $pair['error'],
                    ]);
                }

                $teacherInvite = $pair['invite'];
                $colegio = $pair['colegio'];
                $colegioId = $colegio->id;
                $nombreInstitucion = $colegio->name;
                $modeloPedagogico = LessonTemplate::label($lessonTemplate);
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
                    'estilo_pedagogico' => $role === 'profesor' ? $lessonTemplate : ($validated['modelo_pedagogico'] ?? 'inicio_desarrollo_cierre'),
                    'modelo_pedagogico' => $modeloPedagogico,
                    'lesson_template' => $lessonTemplate,
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

            if ($role === 'profesor') {
                $claimService = app(TeacherInviteClaimService::class);
                if ($teacherInvite instanceof TeacherInvite) {
                    $claimService->claimForUser($user->fresh(), $teacherInvite);
                } else {
                    // Por si el código institucional se usó pero hay invitación DOC- con el mismo email
                    $claimService->claimForUser($user->fresh());
                }

                $preparedCourses = Course::where('teacher_id', $user->id)
                    ->where('colegio_id', $colegioId)
                    ->get(['subject_name', 'grade']);
                if ($preparedCourses->isNotEmpty()) {
                    $subjects = $preparedCourses->pluck('subject_name')->filter()->unique()->values()->all();
                    $grades = $preparedCourses->pluck('grade')->filter()->unique()->values()->all();
                    $user->settings()->update([
                        'materias' => $subjects ?: $user->settings?->materias,
                        'materias_asignadas' => $subjects ?: $user->settings?->materias_asignadas,
                        'cursos_grados' => $grades ?: $user->settings?->cursos_grados,
                    ]);
                    $user->update([
                        'asignatura_principal' => implode(',', $subjects),
                    ]);
                }
            }

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
                ])->with('director_setup', true);
            }

            if ($role === 'profesor') {
                return redirect()->route('teacher.hub')
                    ->with('success', '¡Bienvenido! Tu aula ya está vinculada.');
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
            'teacher_invite_code' => 'nullable|string|max:20',
        ]);

        $inviteCode = InviteCodeHelper::normalize((string) ($validated['teacher_invite_code'] ?? ''));
        if ($inviteCode !== '') {
            $pair = $this->resolveTeacherCodes(
                $validated['school_code'],
                $inviteCode,
                auth()->user()
            );

            if (isset($pair['error'])) {
                return response()->json([
                    'valid' => false,
                    'message' => $pair['error'],
                ], 422);
            }

            return response()->json($this->teacherInvitePayload($pair['colegio'], $pair['invite']));
        }

        $code = InviteCodeHelper::normalize($validated['school_code']);

        $teacherInvite = TeacherInvite::where('invite_code', $code)->first();
        if ($teacherInvite) {
            if ($teacherInvite->isRevoked()) {
                return response()->json([
                    'valid' => false,
                    'message' => 'Ese código DOC- fue revocado por el director.',
                ], 404);
            }
            if ($teacherInvite->isExpired()) {
                return response()->json([
                    'valid' => false,
                    'message' => 'Ese código DOC- expiró. Solicita uno nuevo al director.',
                ], 404);
            }
            if ($teacherInvite->isClaimed() && (int) $teacherInvite->claimed_by !== (int) auth()->id()) {
                return response()->json([
                    'valid' => false,
                    'message' => 'Ese código de docente ya fue utilizado.',
                ], 404);
            }

            $colegio = Colegio::with('director:id,name')->find($teacherInvite->colegio_id);
            if (! $colegio) {
                return response()->json([
                    'valid' => false,
                    'message' => 'Código no encontrado.',
                ], 404);
            }

            return response()->json($this->teacherInvitePayload($colegio, $teacherInvite));
        }

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

        $demoInvite = 'DEMO-' . strtoupper(substr(md5(uniqid()), 0, 6));
        $demoColegio = Colegio::firstOrCreate(
            ['name' => 'Modo Demo Libre'],
            [
                'invite_code' => $demoInvite,
                'codes_pin' => Colegio::hashPinFromInvite($demoInvite),
                'director_user_id' => null,
            ]
        );
        if (! $demoColegio->codes_pin) {
            $demoColegio->update([
                'codes_pin' => Colegio::hashPinFromInvite($demoColegio->invite_code),
            ]);
        }

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
                'estilo_pedagogico' => 'clasica',
                'modelo_pedagogico' => 'Clásica',
                'lesson_template' => 'clasica',
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

    /**
     * @return array{colegio: Colegio, invite: TeacherInvite}|array{error: string, field: string}
     */
    private function resolveTeacherCodes(string $schoolCode, string $inviteCode, ?User $user): array
    {
        $schoolCode = InviteCodeHelper::normalize($schoolCode);
        $inviteCode = InviteCodeHelper::normalize($inviteCode);

        if ($schoolCode === '' || $inviteCode === '') {
            return [
                'error' => 'Ingresa el código de la institución y tu código DOC-.',
                'field' => 'school_code',
            ];
        }

        $colegio = Colegio::with('director:id,name')->where('invite_code', $schoolCode)->first();
        if (! $colegio) {
            return [
                'error' => 'El código de la institución no es válido.',
                'field' => 'school_code',
            ];
        }

        $invite = TeacherInvite::where('invite_code', $inviteCode)->first();
        if (! $invite) {
            return [
                'error' => 'El código de invitación docente no es válido. Debe verse como DOC-8X92K.',
                'field' => 'teacher_invite_code',
            ];
        }

        if ((int) $invite->colegio_id !== (int) $colegio->id) {
            return [
                'error' => 'Ese código DOC- no pertenece a esta institución.',
                'field' => 'teacher_invite_code',
            ];
        }

        if ($invite->isRevoked()) {
            return [
                'error' => 'Ese código DOC- fue revocado por el director.',
                'field' => 'teacher_invite_code',
            ];
        }

        if ($invite->isExpired()) {
            return [
                'error' => 'Ese código DOC- expiró. Solicita uno nuevo al director.',
                'field' => 'teacher_invite_code',
            ];
        }

        $claimedByOther = $invite->isClaimed()
            && (! $user || (int) $invite->claimed_by !== (int) $user->id);

        if ($claimedByOther) {
            return [
                'error' => 'Ese código de docente ya fue utilizado.',
                'field' => 'teacher_invite_code',
            ];
        }

        return [
            'colegio' => $colegio,
            'invite' => $invite,
        ];
    }

    private function teacherInvitePayload(Colegio $colegio, TeacherInvite $teacherInvite): array
    {
        $directorName = $colegio->director?->name
            ?: User::where('colegio_id', $colegio->id)->where('role', 'director')->value('name');

        $assignedCourses = Course::query()
            ->where('colegio_id', $colegio->id)
            ->where(function ($query) use ($teacherInvite) {
                $query->where('teacher_invite_id', $teacherInvite->id);
                $ids = collect($teacherInvite->course_ids ?? [])->filter()->map(fn ($id) => (int) $id);
                if ($ids->isNotEmpty()) {
                    $query->orWhereIn('id', $ids->all());
                }
            })
            ->withCount('students')
            ->orderBy('subject_name')
            ->get(['id', 'subject_name', 'grade', 'section'])
            ->map(fn (Course $course) => [
                'id' => $course->id,
                'subject_name' => $course->subject_name,
                'grade' => $course->grade,
                'section' => $course->section,
                'students_count' => (int) $course->students_count,
            ])
            ->values();

        $studentsTotal = (int) $assignedCourses->sum('students_count');

        return [
            'valid' => true,
            'type' => 'teacher_invite',
            'school' => [
                'id' => $colegio->id,
                'name' => $colegio->name,
                'invite_code' => $colegio->invite_code,
            ],
            'director' => $directorName,
            'teacher_name' => $teacherInvite->name,
            'assigned_courses' => $assignedCourses,
            'students_total' => $studentsTotal,
            'courses_total' => $assignedCourses->count(),
            'message' => $assignedCourses->isNotEmpty()
                ? 'Código válido. El director ya te preparó cursos y alumnos.'
                : 'Código válido. Quedarás vinculado al colegio; el director aún puede asignarte cursos.',
        ];
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
            if (! $colegio->codes_pin) {
                $colegio->codes_pin = Colegio::hashPinFromInvite($colegio->invite_code);
            }
            $colegio->save();

            return $colegio;
        }

        $inviteCode = InviteCodeHelper::generateUnique($cleanName);

        return Colegio::create([
            'name' => $cleanName,
            'invite_code' => $inviteCode,
            'codes_pin' => Colegio::hashPinFromInvite($inviteCode),
            'director_user_id' => $user->id,
        ]);
    }
}
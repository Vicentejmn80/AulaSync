<?php

namespace App\Http\Controllers\Director;

use App\Helpers\InviteCodeHelper;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Course;
use App\Models\DirectorAiOperationLog;
use App\Models\Evaluation;
use App\Models\Grade;
use App\Models\Student;
use App\Models\TeacherInvite;
use App\Models\User;
use App\Services\DirectorActionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AICommandController extends Controller
{
    private const PENDING_SESSION_KEY = 'director_ai_pending_actions';

    public function __construct(
        private DirectorActionService $actionService
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $director = $request->user();
        if (! $director || $director->role !== 'director') {
            return response()->json([
                'success' => false,
                'error' => 'No autorizado.',
                'message' => 'Solo directores pueden usar este asistente.',
            ], 403);
        }

        $payload = $request->validate([
            'prompt' => ['nullable', 'string', 'max:1200'],
            'message' => ['nullable', 'string', 'max:1200'],
            'confirmed' => ['sometimes', 'boolean'],
            'pending_actions' => ['sometimes', 'array'],
            'pending_actions.*.intent' => ['required_with:pending_actions', 'string'],
            'pending_actions.*.data' => ['required_with:pending_actions', 'array'],
            'pending_actions.*.audit_log_id' => ['nullable', 'integer'],
        ]);

        if ($request->boolean('confirmed')) {
            return $this->executePending($request, $director);
        }

        $text = trim((string) ($payload['prompt'] ?? $payload['message'] ?? ''));
        if ($text === '') {
            return response()->json([
                'success' => false,
                'message' => 'Escribe una instrucción. Ejemplo: "Crea al profesor Vicente Maduro y asígnale Inglés de 1ro a 6to".',
            ], 422);
        }

        // Respuestas cortas de confirmación ("sí", "sí, créalos", "confirmo")
        // completan la acción pendiente guardada en sesión, sin bucle sin contexto.
        if ($this->isAffirmativeText($text) && session()->has(self::PENDING_SESSION_KEY)) {
            return $this->executePending($request, $director);
        }

        $intent = $this->detectIntent($text);
        if (! $intent) {
            return response()->json([
                'success' => false,
                'message' => 'No pude interpretar la operación. Prueba con: crear profesor/curso/alumnos, asignar/inscribir o consultar estado académico.',
            ], 422);
        }

        try {
            [$operationData, $missingDataMessage] = $this->buildOperationData($director, $intent, $text);
            if ($missingDataMessage) {
                return response()->json([
                    'success' => false,
                    'message' => $missingDataMessage,
                ], 422);
            }

            if (! $this->intentRequiresConfirmation($intent)) {
                $log = DirectorAiOperationLog::create([
                    'director_user_id' => $director->id,
                    'colegio_id' => $director->colegio_id,
                    'intent' => $intent,
                    'status' => 'received',
                    'input_payload' => [
                        'raw_text' => $text,
                        'data' => $operationData,
                    ],
                ]);

                $result = $this->runIntent($director, $intent, $operationData);
                $summary = $this->verifyResult($director, $intent, $result);

                $log->update([
                    'status' => 'verified',
                    'executed_at' => now(),
                    'verified_at' => now(),
                    'result_payload' => $summary,
                ]);

                return response()->json([
                    'success' => true,
                    'any_success' => true,
                    'actions' => [[
                        'success' => true,
                        'action_type' => $intent,
                        'message' => $summary['message'] ?? 'Consulta completada.',
                        'data' => $summary['data'] ?? [],
                    ]],
                    'message' => 'Consulta completada.',
                ]);
            }

            $log = DirectorAiOperationLog::create([
                'director_user_id' => $director->id,
                'colegio_id' => $director->colegio_id,
                'intent' => $intent,
                'status' => 'pending_confirmation',
                'input_payload' => [
                    'raw_text' => $text,
                    'data' => $operationData,
                ],
            ]);

            $pending = [[
                'intent' => $intent,
                'data' => $operationData,
                'audit_log_id' => $log->id,
            ]];
            session([self::PENDING_SESSION_KEY => $pending]);

            return response()->json([
                'success' => true,
                'requires_confirmation' => true,
                'message' => $this->confirmationMessageFor($intent, $operationData),
                'pending_actions' => $pending,
            ]);
        } catch (ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first() ?: 'No se pudo procesar la instrucción.';

            return response()->json([
                'success' => false,
                'message' => $msg,
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Director AI handle failed', [
                'director_id' => $director->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al preparar la operación.',
            ], 500);
        }
    }

    private function executePending(Request $request, User $director): JsonResponse
    {
        $actions = collect($request->input('pending_actions', session(self::PENDING_SESSION_KEY, [])));
        if ($actions->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No hay acciones pendientes por confirmar.',
            ], 422);
        }

        $results = [];
        $anySuccess = false;

        foreach ($actions as $action) {
            $intent = (string) Arr::get($action, 'intent', '');
            $data = (array) Arr::get($action, 'data', []);
            $logId = Arr::get($action, 'audit_log_id');
            $log = $logId ? DirectorAiOperationLog::find($logId) : null;

            try {
                if ($log) {
                    $log->update([
                        'status' => 'confirmed',
                        'confirmed_at' => now(),
                    ]);
                }

                $result = $this->runIntent($director, $intent, $data);
                $summary = $this->verifyResult($director, $intent, $result);

                if ($log) {
                    $log->update([
                        'status' => 'verified',
                        'executed_at' => now(),
                        'verified_at' => now(),
                        'result_payload' => $summary,
                    ]);
                }

                $results[] = [
                    'success' => true,
                    'action_type' => $intent,
                    'message' => $summary['message'] ?? 'Operación ejecutada.',
                    'data' => $summary['data'] ?? [],
                ];
                $anySuccess = true;
            } catch (ValidationException $e) {
                $msg = collect($e->errors())->flatten()->first() ?: 'Error de validación.';
                if ($log) {
                    $log->update([
                        'status' => 'failed',
                        'executed_at' => now(),
                        'error_payload' => ['message' => $msg],
                    ]);
                }
                $results[] = [
                    'success' => false,
                    'action_type' => $intent,
                    'message' => $msg,
                ];
            } catch (\Throwable $e) {
                Log::error('Director AI execution failed', [
                    'director_id' => $director->id,
                    'intent' => $intent,
                    'message' => $e->getMessage(),
                ]);
                if ($log) {
                    $log->update([
                        'status' => 'failed',
                        'executed_at' => now(),
                        'error_payload' => ['message' => $e->getMessage()],
                    ]);
                }
                $results[] = [
                    'success' => false,
                    'action_type' => $intent,
                    'message' => app()->environment('testing')
                        ? 'Falló la ejecución de la operación: '.$e->getMessage()
                        : 'Falló la ejecución de la operación.',
                ];
            }
        }

        session()->forget(self::PENDING_SESSION_KEY);

        return response()->json([
            'success' => $anySuccess,
            'any_success' => $anySuccess,
            'actions' => $results,
            'message' => $anySuccess
                ? 'Operación del director ejecutada y verificada.'
                : 'No se pudo ejecutar la operación.',
        ]);
    }

    private function runIntent(User $director, string $intent, array $data): array
    {
        return match ($intent) {
            'create_teacher' => $this->actionService->createTeacherInviteWithAssignments($director, $data),
            'create_course' => $this->actionService->createCourse($director, $data),
            'assign_teacher' => $this->actionService->assignTeacherToGradesSubject($director, $data),
            'create_students_batch' => $this->actionService->createStudentsBatch($director, $data),
            'enroll_students_course' => $this->actionService->enrollStudentsToCourse($director, $data),
            'manage_invite_code' => $this->actionService->manageInviteCode($director, $data),
            'query_academic' => $this->queryAcademic($director, $data),
            default => throw ValidationException::withMessages([
                'intent' => 'Intent no soportado para Director AI.',
            ]),
        };
    }

    private function verifyResult(User $director, string $intent, array $result): array
    {
        return match ($intent) {
            'create_teacher' => $this->verifyCreateTeacher($director, $result),
            'create_course' => $this->verifyCreateCourse($director, $result),
            'assign_teacher' => $this->verifyAssignTeacher($director, $result),
            'create_students_batch' => $this->verifyCreateStudentsBatch($director, $result),
            'enroll_students_course' => $this->verifyEnrollStudentsToCourse($director, $result),
            'manage_invite_code' => $this->verifyManageInviteCode($director, $result),
            'query_academic' => $this->verifyAcademicQueryResult($result),
            default => throw ValidationException::withMessages([
                'intent' => 'No se pudo verificar el resultado.',
            ]),
        };
    }

    private function verifyCreateTeacher(User $director, array $result): array
    {
        /** @var TeacherInvite $invite */
        $invite = $result['invite'];
        if ((int) $invite->colegio_id !== (int) $director->colegio_id) {
            throw ValidationException::withMessages([
                'invite' => 'La invitación creada no pertenece al colegio del director.',
            ]);
        }

        /** @var Collection<int,Course> $courses */
        $courses = $result['courses'];

        return [
            'message' => "Profesor invitado correctamente. Código DOC-: {$invite->invite_code}.",
            'data' => [
                'invite_code' => $invite->invite_code,
                'teacher_name' => $invite->name,
                'courses' => $courses->map(fn ($course) => [
                    'course_id' => $course->id,
                    'subject_name' => $course->subject_name,
                    'grade' => $course->grade,
                    'section' => $course->section,
                    'students_count' => $course->students_count,
                ])->values()->all(),
            ],
        ];
    }

    private function verifyCreateCourse(User $director, array $result): array
    {
        /** @var Course $course */
        $course = $result['course'];
        if ((int) $course->colegio_id !== (int) $director->colegio_id) {
            throw ValidationException::withMessages([
                'course' => 'El curso creado no pertenece al colegio del director.',
            ]);
        }

        $action = $result['was_existing'] ? 'Curso actualizado' : 'Curso creado';
        $teacherText = $result['teacher_label'] ? " asignado a {$result['teacher_label']}" : '';

        return [
            'message' => "{$action}: {$course->subject_name} {$course->grade}".($course->section ? " sección {$course->section}" : '')."{$teacherText}.",
            'data' => [
                'course_id' => $course->id,
                'subject_name' => $course->subject_name,
                'grade' => $course->grade,
                'section' => $course->section,
                'invite_code' => $course->invite_code,
                'students_count' => $course->students_count,
            ],
        ];
    }

    private function verifyAssignTeacher(User $director, array $result): array
    {
        /** @var Collection<int,Course> $courses */
        $courses = $result['courses'];
        foreach ($courses as $course) {
            if ((int) $course->colegio_id !== (int) $director->colegio_id) {
                throw ValidationException::withMessages([
                    'assignment' => 'Una asignación quedó fuera del colegio del director.',
                ]);
            }
        }

        return [
            'message' => "Asignación completada para {$result['teacher_label']}.",
            'data' => [
                'teacher' => $result['teacher_label'],
                'courses' => $courses->map(fn ($course) => [
                    'course_id' => $course->id,
                    'subject_name' => $course->subject_name,
                    'grade' => $course->grade,
                    'section' => $course->section,
                    'students_count' => $course->students_count,
                ])->values()->all(),
            ],
        ];
    }

    private function verifyCreateStudentsBatch(User $director, array $result): array
    {
        /** @var Collection<int,Student> $created */
        $created = $result['created'];
        foreach ($created as $student) {
            if ((int) $student->colegio_id !== (int) $director->colegio_id) {
                throw ValidationException::withMessages([
                    'students' => 'Un estudiante creado no pertenece al colegio del director.',
                ]);
            }
        }

        return [
            'message' => "Creé {$created->count()} estudiante(s) correctamente.",
            'data' => [
                'students' => $created->map(fn ($student) => [
                    'student_id' => $student->id,
                    'name' => $student->name,
                    'grade' => $student->grade,
                    'section' => $student->section,
                    'family_code' => $student->family_code,
                ])->values()->all(),
                'duplicates' => $result['duplicates'],
            ],
        ];
    }

    private function verifyEnrollStudentsToCourse(User $director, array $result): array
    {
        /** @var Course $course */
        $course = $result['course'];
        if ((int) $course->colegio_id !== (int) $director->colegio_id) {
            throw ValidationException::withMessages([
                'enroll' => 'La inscripción resultó fuera del colegio del director.',
            ]);
        }

        return [
            'message' => 'Inscripción verificada en '.$course->subject_name.' '.$course->grade.'.',
            'data' => [
                'course_id' => $course->id,
                'course' => $course->subject_name.' '.$course->grade.($course->section ? ' sección '.$course->section : ''),
                'enrolled' => $result['enrolled'],
                'already_enrolled' => $result['already_enrolled'],
                'missing_students' => $result['missing_students'],
                'total_students_in_course' => $result['total_students_in_course'],
            ],
        ];
    }

    private function verifyManageInviteCode(User $director, array $result): array
    {
        /** @var TeacherInvite $invite */
        $invite = $result['invite'];
        if ((int) $invite->colegio_id !== (int) $director->colegio_id) {
            throw ValidationException::withMessages([
                'invite' => 'La invitación no pertenece al colegio del director.',
            ]);
        }

        $message = "Código DOC- activo: {$invite->invite_code}.";

        return [
            'message' => $message,
            'data' => [
                'invite_code' => $invite->invite_code,
                'teacher_name' => $invite->name,
                'revoked_at' => optional($invite->revoked_at)->toISOString(),
                'expires_at' => optional($invite->expires_at)->toISOString(),
                'claimed_at' => optional($invite->claimed_at)->toISOString(),
            ],
        ];
    }

    private function verifyAcademicQueryResult(array $result): array
    {
        return [
            'message' => $result['message'] ?? 'Consulta completada.',
            'data' => $result['data'] ?? [],
        ];
    }

    /**
     * @return array{0:array,1:?string}
     */
    private function buildOperationData(User $director, string $intent, string $text): array
    {
        return match ($intent) {
            'create_teacher' => $this->parseCreateTeacher($director, $text),
            'create_course' => $this->parseCreateCourse($director, $text),
            'assign_teacher' => $this->parseAssignTeacher($director, $text),
            'create_students_batch' => $this->parseCreateStudentsBatch($director, $text),
            'enroll_students_course' => $this->parseEnrollStudentsCourse($director, $text),
            'manage_invite_code' => $this->parseManageInviteCode($text),
            'query_academic' => $this->parseQueryAcademic($text),
            default => [[], 'No pude convertir tu solicitud en una operación segura.'],
        };
    }

    private function confirmationMessageFor(string $intent, array $data): string
    {
        $createdGrades = $this->mentionMissingGrades($intent, $data);
        $createdGrades = $createdGrades !== '' ? ' '.$createdGrades : '';

        $message = match ($intent) {
            'create_teacher' => "Voy a crear la invitación para {$data['teacher_name']} y asignar ".
                ($data['subject_name'] ? "{$data['subject_name']} en ".implode(', ', $data['grades']) : 'sin materias iniciales').
                '. Responde "sí" para confirmar.',
            'create_course' => "Voy a crear el curso {$data['subject_name']} para {$data['grade']}".(($data['section'] ?? null) ? " sección {$data['section']}" : '').'. Responde "sí" para confirmar.',
            'assign_teacher' => "Voy a asignar a {$data['teacher_name']} la materia {$data['subject_name']} en ".implode(', ', $data['grades']).'. Responde "sí" para confirmar.',
            'create_students_batch' => 'Voy a crear '.count($data['names'])." estudiante(s) en {$data['grade']}".($data['section'] ? " / {$data['section']}" : '').'. Responde "sí" para confirmar.',
            'enroll_students_course' => 'Voy a inscribir '.count($data['names'])." alumno(s) en {$data['subject_name']} {$data['grade']}".(($data['section'] ?? null) ? " sección {$data['section']}" : '').'. Responde "sí" para confirmar.',
            'manage_invite_code' => 'Voy a consultar el estado del código DOC-. Responde "sí" para confirmar.',
            default => 'Confirma la operación.',
        };

        return $message.$createdGrades;
    }

    /**
     * Cuando faltan grados en el colegio, el flujo los crea automáticamente al ejecutar,
     * así que el mensaje lo aclara para que el director confirme con "sí"/"confirmo".
     */
    private function mentionMissingGrades(string $intent, array $data): string
    {
        $missing = (array) ($data['missing_grades'] ?? []);
        $missing = array_values(array_filter($missing, fn ($g) => (string) $g !== ''));

        if ($missing === []) {
            return '';
        }

        return 'También crearé automáticamente los grados que faltan: '.implode(', ', $missing).'.';
    }

    private function detectIntent(string $text): ?string
    {
        $value = $this->normalizedText($text);

        if ((str_contains($value, 'crea') || str_contains($value, 'crear')) && (str_contains($value, 'curso') || str_contains($value, 'cursso') || str_contains($value, 'asignatura'))) {
            return 'create_course';
        }
        if ((preg_match('/\bcrea(?:r|me)?\b/', $value) || str_contains($value, 'creame') || str_contains($value, 'crearme') || str_contains($value, 'invita')) && str_contains($value, 'profesor')) {
            return 'create_teacher';
        }
        if ((str_contains($value, 'dara') || str_contains($value, 'asigna'))
            && (str_contains($value, 'grado') || preg_match('/\b[1-6](ro|do|to|er)?\b/', $value))) {
            return 'assign_teacher';
        }
        if ((str_contains($value, 'inscribe') || str_contains($value, 'asigna'))
            && str_contains($value, 'curso')
            && (str_contains($value, 'alumno') || str_contains($value, 'estudiante') || preg_match('/\sa\s+[a-z]/', $value))) {
            return 'enroll_students_course';
        }
        if ((str_contains($value, 'agrega') || str_contains($value, 'matricula') || str_contains($value, 'crear') || str_contains($value, 'inscribe'))
            && (str_contains($value, 'alumno') || str_contains($value, 'estudiante'))) {
            return 'create_students_batch';
        }
        if ((str_contains($value, 'agrega') || str_contains($value, 'matricula') || str_contains($value, 'crear'))
            && preg_match('/\b[1-6](ro|do|to|er)?\b.*grado/', $value)) {
            return 'create_students_batch';
        }
        if ((str_contains($value, 'doc-') || str_contains($value, 'codigo'))
            && (str_contains($value, 'consulta') || str_contains($value, 'estado') || str_contains($value, 'mostrar') || str_contains($value, 'tiene'))) {
            return 'manage_invite_code';
        }
        if (
            str_contains($value, 'como va')
            || str_contains($value, 'que alumnos tiene')
            || str_contains($value, 'que cursos tiene')
            || str_contains($value, 'cuantas faltas')
            || str_contains($value, 'como estan sus evaluaciones')
            || str_contains($value, 'como estan las evaluaciones')
            || ((str_contains($value, 'consulta') || str_contains($value, 'muestrame') || str_contains($value, 'mostrar') || str_contains($value, 'estado'))
                && (str_contains($value, 'profesor') || str_contains($value, 'estudiante') || str_contains($value, 'alumno') || str_contains($value, 'curso')))
        ) {
            return 'query_academic';
        }

        return null;
    }

    /**
     * @return array{0:array,1:?string}
     */
    private function parseCreateTeacher(User $director, string $text): array
    {
        $name = $this->extractTeacherName($text);
        if (! $name) {
            return [[], '¿Cuál es el nombre completo del profesor que deseas crear?'];
        }

        $subject = $this->extractSubject($text);
        $grades = $this->extractGrades($text);
        $missingGrades = $this->missingGradesFor($director, $grades);

        return [[
            'teacher_name' => $name,
            'subject_name' => $subject,
            'grades' => $grades,
            'missing_grades' => $missingGrades,
            'expires_in_days' => 30,
        ], null];
    }

    /**
     * @return array{0:array,1:?string}
     */
    private function parseCreateCourse(User $director, string $text): array
    {
        $subject = $this->extractSubjectFromCoursePrompt($text);
        if (! $subject) {
            return [[], '¿Qué asignatura debo crear? Ejemplo: "Crea curso de Matemática para 4to grado".'];
        }

        $grade = $this->extractTargetGrade($text);
        if (! $grade) {
            return [[], '¿Para qué grado debo crear el curso?'];
        }

        $section = $this->extractSection($text);
        $teacherName = $this->extractTeacherName($text);
        $missingGrades = $this->missingGradesFor($director, [$grade]);

        return [[
            'subject_name' => $subject,
            'grade' => $grade,
            'section' => $section,
            'teacher_name' => $teacherName,
            'missing_grades' => $missingGrades,
        ], null];
    }

    /**
     * @return array{0:array,1:?string}
     */
    private function parseAssignTeacher(User $director, string $text): array
    {
        $name = $this->extractTeacherName($text);
        if (! $name) {
            return [[], '¿A qué profesor deseas asignar la materia?'];
        }

        $subject = $this->extractSubject($text);
        if (! $subject) {
            return [[], '¿Qué materia deseas asignar?'];
        }

        $grades = $this->extractGrades($text);
        if ($grades === []) {
            return [[], '¿Qué grados debo asignar? Ejemplo: 1ro a 6to.'];
        }

        $missingGrades = $this->missingGradesFor($director, $grades);

        return [[
            'teacher_name' => $name,
            'subject_name' => $subject,
            'grades' => $grades,
            'missing_grades' => $missingGrades,
        ], null];
    }

    /**
     * @return array{0:array,1:?string}
     */
    private function parseCreateStudentsBatch(User $director, string $text): array
    {
        $grade = $this->extractTargetGrade($text);
        if (! $grade) {
            return [[], '¿En qué grado debo crear a los estudiantes?'];
        }

        $names = $this->extractStudentNames($text);
        if ($names === []) {
            return [[], 'No pude detectar los nombres. Usa formato: "Agrega a Carlos, Juan y María al 3er grado".'];
        }

        $section = $this->extractSection($text);
        $missingGrades = $this->missingGradesFor($director, [$grade]);

        return [[
            'names' => $names,
            'grade' => $grade,
            'section' => $section,
            'missing_grades' => $missingGrades,
        ], null];
    }

    /**
     * @return array{0:array,1:?string}
     */
    private function parseEnrollStudentsCourse(User $director, string $text): array
    {
        $names = $this->extractStudentNames($text);
        if ($names === []) {
            return [[], 'No pude detectar los alumnos para inscribir. Ejemplo: "Inscribe a Luis y Marta en Matemática de 4to grado".'];
        }

        $subject = $this->extractSubjectFromCoursePrompt($text);
        if (! $subject) {
            return [[], '¿En qué asignatura debo inscribirlos?'];
        }

        $grade = $this->extractTargetGrade($text);
        if (! $grade) {
            return [[], '¿En qué grado está ese curso?'];
        }

        $section = $this->extractSection($text);
        $missingGrades = $this->missingGradesFor($director, [$grade]);
        if ($missingGrades !== []) {
            return [[], 'No encontré el grado '.$grade.' en tu colegio. Revisa la instrucción.'];
        }

        return [[
            'names' => $names,
            'subject_name' => $subject,
            'grade' => $grade,
            'section' => $section,
        ], null];
    }

    /**
     * @return array{0:array,1:?string}
     */
    private function parseManageInviteCode(string $text): array
    {
        $inviteCode = null;
        if (preg_match('/(DOC-[A-Z0-9]{4,8})/i', $text, $m)) {
            $inviteCode = InviteCodeHelper::normalize($m[1]);
        }

        $teacherName = $this->extractTeacherName($text);
        if (! $inviteCode && ! $teacherName) {
            return [[], 'Indícame el código DOC- o el nombre del profesor para gestionar su invitación.'];
        }

        return [[
            'operation' => 'query',
            'invite_code' => $inviteCode,
            'teacher_name' => $teacherName,
            'expires_in_days' => 30,
        ], null];
    }

    /**
     * @return array{0:array,1:?string}
     */
    private function parseQueryAcademic(string $text): array
    {
        $value = $this->normalizedText($text);

        if (preg_match('/c[oó]mo va(?:\s+el|\s+la)?\s+profesor(?:a)?\s+(.+?)\??$/iu', trim($text), $m)) {
            return [[
                'query_type' => 'teacher_overview',
                'teacher_name' => trim($m[1]),
            ], null];
        }

        if (preg_match('/c[oó]mo va\s+(.+?)\s+en\s+([A-Za-zÁÉÍÓÚáéíóúÑñ\s]+)\??$/u', trim($text), $m)) {
            return [[
                'query_type' => 'student_subject_overview',
                'student_name' => trim($m[1]),
                'subject_name' => trim($m[2]),
            ], null];
        }

        if (preg_match('/qu[eé]\s+alumnos tiene\s+(.+?)\s+en\s+([1-6](?:ro|do|to|er|ero)?\s*grado)/iu', $text, $m)) {
            return [[
                'query_type' => 'teacher_students_grade',
                'teacher_name' => trim($m[1]),
                'grade' => $this->extractTargetGrade($m[2]) ?? trim($m[2]),
            ], null];
        }

        if (preg_match('/qu[eé]\s+cursos tiene asignad[oa]s?\s+(?:el|la)?\s*profesor(?:a)?\s+(.+?)\??$/iu', trim($text), $m)) {
            return [[
                'query_type' => 'teacher_courses',
                'teacher_name' => trim($m[1]),
            ], null];
        }

        if (preg_match('/cu[aá]ntas faltas tiene\s+(.+?)\??$/iu', trim($text), $m)) {
            return [[
                'query_type' => 'student_absences',
                'student_name' => trim($m[1]),
            ], null];
        }

        if (preg_match('/c[oó]mo est[aá]n(?:\s+sus)?\s+evaluaciones(?:\s+de)?\s+(.+?)\??$/iu', trim($text), $m)) {
            return [[
                'query_type' => 'student_evaluations',
                'student_name' => trim($m[1]),
            ], null];
        }

        if (str_contains($value, 'profesor')) {
            return [[], 'Especifica así: "¿Cómo va el profesor Carlos Pérez?" o "¿Qué cursos tiene asignados la profesora María?"'];
        }
        if (str_contains($value, 'alumno') || str_contains($value, 'estudiante')) {
            return [[], 'Especifica así: "¿Cómo va Carlos Pérez en Matemática?" o "¿Cuántas faltas tiene Carlos Pérez?"'];
        }

        return [[], 'No pude identificar la consulta académica. Intenta con un nombre de profesor/alumno y una pregunta concreta.'];
    }

    private function queryAcademic(User $director, array $data): array
    {
        $type = $data['query_type'] ?? '';
        $colegioId = (int) $director->colegio_id;

        return match ($type) {
            'teacher_overview' => $this->queryTeacherOverview($colegioId, (string) $data['teacher_name']),
            'student_subject_overview' => $this->queryStudentSubjectOverview($colegioId, (string) $data['student_name'], (string) $data['subject_name']),
            'teacher_students_grade' => $this->queryTeacherStudentsByGrade($colegioId, (string) $data['teacher_name'], (string) $data['grade']),
            'teacher_courses' => $this->queryTeacherCourses($colegioId, (string) $data['teacher_name']),
            'student_absences' => $this->queryStudentAbsences($colegioId, (string) $data['student_name']),
            'student_evaluations' => $this->queryStudentEvaluations($colegioId, (string) $data['student_name']),
            default => throw ValidationException::withMessages([
                'query' => 'No pude interpretar el tipo de consulta académica.',
            ]),
        };
    }

    private function queryTeacherOverview(int $colegioId, string $teacherName): array
    {
        $teacher = $this->resolveTeacherForQuery($colegioId, $teacherName);
        $courses = Course::query()
            ->where('colegio_id', $colegioId)
            ->where('teacher_id', $teacher->id)
            ->withCount('students')
            ->orderBy('grade')
            ->orderBy('subject_name')
            ->get(['id', 'subject_name', 'grade', 'section']);

        $courseIds = $courses->pluck('id');
        $average = null;
        if ($courseIds->isNotEmpty()) {
            $average = Grade::query()
                ->join('activities', 'grades.activity_id', '=', 'activities.id')
                ->whereIn('activities.course_id', $courseIds->all())
                ->where('grades.colegio_id', $colegioId)
                ->avg('grades.score');
        }

        $msg = "Profesor {$teacher->name}: ".$courses->count().' curso(s) y '.$courses->sum('students_count').' alumno(s) asignados.';
        if ($average !== null) {
            $msg .= ' Promedio reciente: '.number_format((float) $average, 1).'.';
        }

        return [
            'message' => $msg,
            'data' => [
                'teacher' => $teacher->only(['id', 'name', 'email']),
                'courses' => $courses->map(fn ($c) => [
                    'course_id' => $c->id,
                    'subject_name' => $c->subject_name,
                    'grade' => $c->grade,
                    'section' => $c->section,
                    'students_count' => $c->students_count,
                ])->values()->all(),
            ],
        ];
    }

    private function queryStudentSubjectOverview(int $colegioId, string $studentName, string $subjectName): array
    {
        $student = $this->resolveStudentForQuery($colegioId, $studentName);
        $subjectKey = mb_strtolower(trim($subjectName));

        $courses = $student->courses()
            ->where('courses.colegio_id', $colegioId)
            ->whereRaw('LOWER(courses.subject_name) like ?', ['%'.$subjectKey.'%'])
            ->get(['courses.id', 'courses.subject_name', 'courses.grade', 'courses.section']);
        $courseIds = $courses->pluck('id');

        $grades = collect();
        $absences = 0;
        $evaluations = collect();
        if ($courseIds->isNotEmpty()) {
            $grades = Grade::query()
                ->join('activities', 'grades.activity_id', '=', 'activities.id')
                ->where('grades.student_id', $student->id)
                ->where('grades.colegio_id', $colegioId)
                ->whereIn('activities.course_id', $courseIds->all())
                ->orderByDesc('grades.created_at')
                ->limit(10)
                ->get(['grades.score', 'grades.status', 'grades.created_at']);

            $absences = Attendance::query()
                ->where('colegio_id', $colegioId)
                ->where('student_id', $student->id)
                ->whereIn('course_id', $courseIds->all())
                ->where('status', Attendance::STATUS_ABSENT)
                ->count();

            $evaluations = Evaluation::query()
                ->where('colegio_id', $colegioId)
                ->whereIn('course_id', $courseIds->all())
                ->orderByDesc('scheduled_at')
                ->limit(5)
                ->get(['id', 'title', 'status', 'scheduled_at']);
        }

        $average = $grades->avg('score');
        $msg = "{$student->name} en {$subjectName}: ".$courses->count().' curso(s), '.$grades->count().' calificación(es) registrada(s) y '.$absences.' falta(s).';
        if ($average !== null) {
            $msg .= ' Promedio: '.number_format((float) $average, 1).'.';
        }

        return [
            'message' => $msg,
            'data' => [
                'student' => $student->only(['id', 'name', 'grade', 'section']),
                'courses' => $courses,
                'recent_grades' => $grades,
                'evaluations' => $evaluations,
                'absences' => $absences,
            ],
        ];
    }

    private function queryTeacherStudentsByGrade(int $colegioId, string $teacherName, string $grade): array
    {
        $teacher = $this->resolveTeacherForQuery($colegioId, $teacherName);
        $courseIds = Course::query()
            ->where('colegio_id', $colegioId)
            ->where('teacher_id', $teacher->id)
            ->whereRaw('LOWER(grade) = ?', [mb_strtolower($grade)])
            ->pluck('id');

        if ($courseIds->isEmpty()) {
            return [
                'message' => "{$teacher->name} no tiene cursos en {$grade}.",
                'data' => ['students' => []],
            ];
        }

        $students = Student::query()
            ->where('students.colegio_id', $colegioId)
            ->join('course_student', 'students.id', '=', 'course_student.student_id')
            ->whereIn('course_student.course_id', $courseIds->all())
            ->select('students.id', 'students.name', 'students.grade', 'students.section')
            ->distinct()
            ->orderBy('students.name')
            ->get();

        return [
            'message' => "{$teacher->name} tiene {$students->count()} alumno(s) en {$grade}.",
            'data' => ['students' => $students],
        ];
    }

    private function queryTeacherCourses(int $colegioId, string $teacherName): array
    {
        $teacher = $this->resolveTeacherForQuery($colegioId, $teacherName);
        $courses = Course::query()
            ->where('colegio_id', $colegioId)
            ->where('teacher_id', $teacher->id)
            ->withCount('students')
            ->orderBy('grade')
            ->orderBy('subject_name')
            ->get(['id', 'subject_name', 'grade', 'section', 'invite_code']);

        return [
            'message' => "{$teacher->name} tiene {$courses->count()} curso(s) asignado(s).",
            'data' => ['courses' => $courses],
        ];
    }

    private function queryStudentAbsences(int $colegioId, string $studentName): array
    {
        $student = $this->resolveStudentForQuery($colegioId, $studentName);
        $absences = Attendance::query()
            ->where('colegio_id', $colegioId)
            ->where('student_id', $student->id)
            ->where('status', Attendance::STATUS_ABSENT)
            ->count();
        $tardies = Attendance::query()
            ->where('colegio_id', $colegioId)
            ->where('student_id', $student->id)
            ->where('status', Attendance::STATUS_TARDY)
            ->count();

        return [
            'message' => "{$student->name} tiene {$absences} falta(s) y {$tardies} tardanza(s) registradas.",
            'data' => [
                'student' => $student->only(['id', 'name', 'grade', 'section']),
                'absences' => $absences,
                'tardies' => $tardies,
            ],
        ];
    }

    private function queryStudentEvaluations(int $colegioId, string $studentName): array
    {
        $student = $this->resolveStudentForQuery($colegioId, $studentName);
        $evaluationRows = Evaluation::query()
            ->where('colegio_id', $colegioId)
            ->whereHas('attempts', fn ($query) => $query->where('student_id', $student->id))
            ->with(['course:id,subject_name,grade,section'])
            ->orderByDesc('scheduled_at')
            ->limit(8)
            ->get(['id', 'course_id', 'title', 'status', 'scheduled_at']);

        $gradeRows = Grade::query()
            ->where('colegio_id', $colegioId)
            ->where('student_id', $student->id)
            ->with('activity:id,title,course_id')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get(['id', 'activity_id', 'score', 'status', 'created_at']);

        $average = $gradeRows->avg('score');
        $msg = "{$student->name} tiene {$evaluationRows->count()} evaluación(es) recientes y {$gradeRows->count()} nota(s) registradas.";
        if ($average !== null) {
            $msg .= ' Promedio de notas recientes: '.number_format((float) $average, 1).'.';
        }

        return [
            'message' => $msg,
            'data' => [
                'student' => $student->only(['id', 'name', 'grade', 'section']),
                'evaluations' => $evaluationRows,
                'grades' => $gradeRows->map(fn ($grade) => [
                    'score' => $grade->score,
                    'status' => $grade->status,
                    'activity_title' => $grade->activity?->title,
                ])->values()->all(),
            ],
        ];
    }

    private function extractTeacherName(string $text): ?string
    {
        $patterns = [
            '/profesor(?:a)?\s+(.+?)(?:\s+y\s+as[ií]gna|\s+para\s+as[ií]gna|\s+dara|\s+dará|,|\.|$)/iu',
            '/^(.+?)\s+(?:dara|dará|asigna)/iu',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $m)) {
                $name = trim((string) $m[1]);
                if ($name !== '' && mb_strlen($name) <= 80) {
                    return $name;
                }
            }
        }

        return null;
    }

    private function extractSubjectFromCoursePrompt(string $text): ?string
    {
        // Localizamos "curso/asignatura de" en el texto ORIGINAL para conservar
        // acentos y manejar variantes tipográficas ("cursso") sin desfases de índice.
        if (! preg_match('/(?:curso|cursso|asignatura)\s+de\s+(.+?)$/iu', $text, $m)) {
            return null;
        }
        $rest = trim((string) $m[1]);

        // Si el grado aparece antes de la materia ("1er grado de matematicas"),
        // lo descartamos: "1er grado de" -> "".
        $rest = preg_replace('/^(?:al\s+)?[1-6](?:ro|ero|do|to|er|º|°)?\s*grado\s+(?:de\s+)?/iu', '', $rest) ?? $rest;

        // Cortamos en conectores/palabras reservadas (conserva acentos del original).
        $rest = preg_split('/\s+(?:para|en|del|de|al|a\s+la|con|seccion|sección|y|nivel)\s+/iu', $rest)[0] ?? $rest;
        $rest = trim((string) $rest);
        $rest = trim(preg_replace('/^[1-6](?:ro|ero|do|to|er)?\.?\s*/iu', '', $rest) ?? '');

        if ($rest === '' || preg_match('/(grado|curso|asignatura|profesor|profesora|alumno|estudiante|docente|materia)$/iu', $rest)) {
            return null;
        }

        $subject = $this->titleCaseSubject($rest);
        if (mb_strlen($subject) < 2 || mb_strlen($subject) > 60 || preg_match('/\b(cursso|curso|asignatura)\b/iu', $subject)) {
            return null;
        }

        return $subject;
    }

    private function titleCaseSubject(string $subject): string
    {
        return mb_convert_case(trim($subject), MB_CASE_TITLE, 'UTF-8');
    }

    private function extractSubject(string $text): ?string
    {
        $patterns = [
            '/(?:as[ií]gna(?:le)?|dara|dará)\s+([A-Za-zÁÉÍÓÚáéíóúÑñ\s]{3,50})\s+(?:de|del|para|en)\s+/u',
            '/(?:as[ií]gna(?:le)?|dara|dará)\s+([A-Za-zÁÉÍÓÚáéíóúÑñ\s]{3,50})(?:,|\.|$)/u',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $m)) {
                return trim($m[1]);
            }
        }

        return null;
    }

    /**
     * @return array<int,string>
     */
    private function extractGrades(string $text): array
    {
        $value = mb_strtolower($text);
        if (preg_match('/de\s+([1-6])(?:ro|ero|er|°|º)?\s+a\s+([1-6])(?:to|do|ro|°|º)?/u', $value, $m)) {
            $from = (int) $m[1];
            $to = (int) $m[2];
            if ($from <= $to) {
                return collect(range($from, $to))->map(fn ($n) => $this->formatGrade((int) $n))->all();
            }
        }

        preg_match_all('/([1-6])(?:ro|ero|do|to|°|º)?\s*(?:grado)?/u', $value, $matches);
        $grades = collect($matches[1] ?? [])->map(fn ($n) => $this->formatGrade((int) $n))->unique()->values()->all();

        return $grades;
    }

    private function extractTargetGrade(string $text): ?string
    {
        $value = mb_strtolower($text);
        if (preg_match('/al?\s+([1-6])(?:ro|ero|er|do|to|°|º)?\s*(?:er|do|to)?\s*grado/u', $value, $m)) {
            return $this->formatGrade((int) $m[1]);
        }
        if (preg_match('/([1-6])(?:ro|ero|er|do|to|°|º)\s*(?:grado)?/u', $value, $m)) {
            return $this->formatGrade((int) $m[1]);
        }

        return null;
    }

    /**
     * @return array<int,string>
     */
    private function extractStudentNames(string $text): array
    {
        if (! preg_match('/(?:agrega|agregar|matricula|matricular|inscribe|inscribir|crea|crear)\s+a?\s+(.+?)\s+(?:al|a la|en)\s+/iu', $text, $m)) {
            return [];
        }
        $raw = trim($m[1]);
        $raw = preg_replace('/\s+y\s+/iu', ',', $raw) ?? $raw;

        return collect(explode(',', $raw))
            ->map(fn ($name) => trim($name))
            ->filter(fn ($name) => mb_strlen($name) >= 2)
            ->unique()
            ->values()
            ->all();
    }

    private function extractSection(string $text): ?string
    {
        if (preg_match('/secci[oó]n\s+([A-Za-z0-9]+)/iu', $text, $m)) {
            return strtoupper(trim($m[1]));
        }

        return null;
    }

    /**
     * @param  array<int,string>  $grades
     * @return array<int,string>
     */
    private function missingGradesFor(User $director, array $grades): array
    {
        if ($grades === []) {
            return [];
        }

        $existing = $this->actionService->existingGradeKeys((int) $director->colegio_id);
        if ($existing->isEmpty()) {
            return [];
        }

        $missing = [];
        foreach ($grades as $grade) {
            $key = $this->actionService->normalizeGradeKey($grade);
            if ($key !== '' && ! $existing->contains($key)) {
                $missing[] = $grade;
            }
        }

        return $missing;
    }

    private function resolveTeacherForQuery(int $colegioId, string $teacherName): User
    {
        $name = mb_strtolower(trim($teacherName));
        $teacher = User::query()
            ->where('colegio_id', $colegioId)
            ->where('role', 'profesor')
            ->where(function ($query) use ($name) {
                $query->whereRaw('LOWER(name) = ?', [$name])
                    ->orWhereRaw('LOWER(name) like ?', ['%'.$name.'%']);
            })
            ->orderByRaw('CASE WHEN LOWER(name) = ? THEN 0 ELSE 1 END', [$name])
            ->first();

        if (! $teacher) {
            throw ValidationException::withMessages([
                'teacher' => 'No encontré al profesor indicado en este colegio.',
            ]);
        }

        return $teacher;
    }

    private function resolveStudentForQuery(int $colegioId, string $studentName): Student
    {
        $name = mb_strtolower(trim($studentName));
        $student = Student::query()
            ->where('colegio_id', $colegioId)
            ->where(function ($query) use ($name) {
                $query->whereRaw('LOWER(name) = ?', [$name])
                    ->orWhereRaw('LOWER(name) like ?', ['%'.$name.'%']);
            })
            ->orderByRaw('CASE WHEN LOWER(name) = ? THEN 0 ELSE 1 END', [$name])
            ->first();

        if (! $student) {
            throw ValidationException::withMessages([
                'student' => 'No encontré al alumno indicado en este colegio.',
            ]);
        }

        return $student;
    }

    private function intentRequiresConfirmation(string $intent): bool
    {
        return in_array($intent, [
            'create_teacher',
            'create_course',
            'assign_teacher',
            'create_students_batch',
            'enroll_students_course',
        ], true);
    }

    /**
     * Reconoce respuestas cortas de confirmación ("sí", "sí, créalos", "confirmo")
     * para completar la acción pendiente guardada en sesión.
     */
    private function isAffirmativeText(string $text): bool
    {
        $value = mb_strtolower(trim($text));
        $value = trim(preg_replace('/[.!?]+$/', '', $value) ?? '');
        if ($value === '') {
            return false;
        }

        $short = ['sí', 'si', 'ok', 'okay', 'sip', 'dale', 'adelante', 'confirmo', 'confirmar', 'procede', 'listo', 'hazlo', 'crealos', 'crealo', 'yes', 'yep', 'claro', 'correcto', 'afirmativo', 'exacto', 'siguiente'];
        if (in_array($value, $short, true)) {
            return true;
        }

        if (preg_match('/^(s[ií])(\s*[,.;:\-]\s*(cr[eé]alos|cr[eé]alos igualmente|crearlos|confirmo|dale|adelante|hazlo|por favor))?$/u', $value)) {
            return true;
        }

        return false;
    }

    private function formatGrade(int $n): string
    {
        return match ($n) {
            1 => '1ro',
            2 => '2do',
            3 => '3ro',
            4 => '4to',
            5 => '5to',
            6 => '6to',
            default => (string) $n,
        };
    }

    private function normalizedText(string $text): string
    {
        $value = mb_strtolower($text);

        $value = strtr($value, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ñ' => 'n',
        ]);

        // Variantes tipográficas comunes que deben interpretarse igual.
        $value = preg_replace('/\bcurs+o\b/', 'curso', $value) ?? $value;
        $value = preg_replace('/\b1(?:er|ero)?\s*grado\b/', '1er grado', $value) ?? $value;

        return $value;
    }
}

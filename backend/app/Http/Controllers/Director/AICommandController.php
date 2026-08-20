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
use App\Services\DirectorAIInterpreterService;
use App\Services\DirectorAnalyticsQueryService;
use App\Services\DirectorConversationContextService;
use App\Services\PersonNameMatcher;
use App\Services\PersonNameSanitizer;
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
        private DirectorActionService $actionService,
        private DirectorAIInterpreterService $interpreter,
        private DirectorConversationContextService $conversationContext,
        private DirectorAnalyticsQueryService $analytics,
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
            'conversation' => ['sometimes', 'array', 'max:40'],
            'conversation.*.role' => ['required_with:conversation', 'in:user,assistant'],
            'conversation.*.content' => ['required_with:conversation', 'string', 'max:12000'],
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

        if ($this->isNegativeText($text) && session()->has(self::PENDING_SESSION_KEY)) {
            session()->forget(self::PENDING_SESSION_KEY);
            $this->conversationContext->clearPendingReferences();

            return response()->json([
                'success' => true,
                'cancelled' => true,
                'message' => 'Operación cancelada. No hice cambios.',
            ]);
        }

        try {
            $interpreted = $this->interpreter->interpret(
                $director,
                $text,
                (array) ($payload['conversation'] ?? []),
                $this->conversationContext->current(),
            );

            $actions = $this->enrichActionsFromText((array) ($interpreted['actions'] ?? []), $text);
            if ($actions !== []) {
                $actions = $this->mergeMissingIntentsFromText($director, $actions, $text);
            }

            if ($actions === []) {
                $llmReply = is_array($interpreted)
                    ? trim((string) ($interpreted['message'] ?? $interpreted['clarification'] ?? ''))
                    : '';
                $intentGuess = $this->detectIntent($text);
                if ($llmReply !== '') {
                    // query_academic no puede contestarse desde el roster: si el LLM
                    // respondió en texto sin tool, caemos al parser local.
                    $trustLlmText = $intentGuess !== 'query_academic'
                        && ! $this->intentRequiresConfirmation((string) $intentGuess);
                    if ($trustLlmText) {
                        return response()->json([
                            'success' => true,
                            'message' => $llmReply,
                        ]);
                    }
                    if ($intentGuess !== null && $intentGuess !== 'query_academic') {
                        $this->conversationContext->rememberError($llmReply);

                        return response()->json([
                            'success' => false,
                            'needs_clarification' => true,
                            'message' => $llmReply,
                        ], 422);
                    }
                }
            }

            if ($actions === []) {
                $contextualAction = $this->contextualFallbackAction($text);
                if ($contextualAction !== null) {
                    $actions = [$contextualAction];
                }
            }

            if ($actions === []) {
                $composite = $this->detectMultiIntentActions($director, $text);
                if ($composite !== []) {
                    $actions = $this->enrichActionsFromText($composite, $text);
                }
            }

            if ($actions === []) {
                $intent = $this->detectIntent($text);
                if ($intent) {
                    [$operationData, $missingDataMessage] = $this->buildOperationData($director, $intent, $text);
                    if ($missingDataMessage) {
                        return response()->json([
                            'success' => false,
                            'needs_clarification' => true,
                            'message' => $missingDataMessage,
                        ]);
                    }
                    $actions = $this->enrichActionsFromText([['intent' => $intent, 'data' => $operationData]], $text);
                }
            }

            if ($actions === []) {
                return response()->json([
                    'success' => false,
                    'needs_clarification' => true,
                    'message' => (is_array($interpreted) ? ($interpreted['clarification'] ?? null) : null)
                        ?: 'Puedo crear y eliminar profesores, cursos y alumnos, matricular alumnos en cursos y consultar notas o faltas. Ejemplos: "Crea al alumno Andrés Pérez y asígnalo al curso de Inglés de 1ro" o "Crea al profesor Yovanny Andrade y asígnale Inglés de 1ro a 6to".',
                ]);
            }

            return $this->prepareActions($director, $actions, $text);
        } catch (ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first() ?: 'No se pudo procesar la instrucción.';
            $this->conversationContext->rememberError($msg);

            return response()->json([
                'success' => false,
                'needs_clarification' => true,
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

    /**
     * @param  array<int,array{intent:string,data:array}>  $actions
     */
    private function prepareActions(User $director, array $actions, string $rawText): JsonResponse
    {
        $actions = $this->enrichActionsFromText($actions, $rawText);
        $hasStudentAction = collect($actions)->contains(
            fn ($action) => in_array($action['intent'] ?? '', ['create_students_batch', 'enroll_students_course'], true)
        );
        $prepared = [];
        foreach ($actions as $action) {
            $intent = (string) ($action['intent'] ?? '');
            $data = $this->hydrateActionData($director, $intent, (array) ($action['data'] ?? []));
            if ($intent === 'create_teacher'
                && $this->teacherClauseMentionsCourses($rawText)
                && ! $hasStudentAction
                && empty($data['subject_name'])) {
                throw ValidationException::withMessages([
                    'prompt' => 'Entendí que también quieres crear o asignar cursos. Dime la materia y los grados, por ejemplo: "asígnale Inglés de 1ro a 6to".',
                ]);
            }
            $data = $this->resolveDeleteTarget($director, $intent, $data);
            $this->validateActionReferences($director, $intent, $data);
            $prepared[] = ['intent' => $intent, 'data' => $data];
        }

        $this->conversationContext->rememberPlan($prepared, $rawText);
        $requiresConfirmation = collect($prepared)
            ->contains(fn ($action) => $this->intentRequiresConfirmation($action['intent']));

        if (! $requiresConfirmation) {
            $results = [];
            foreach ($prepared as $action) {
                $intent = $action['intent'];
                $data = $action['data'];
                $log = $this->createAuditLog($director, $intent, 'received', $rawText, $data);
                $result = $this->runIntent($director, $intent, $data);
                $summary = $this->verifyResult($director, $intent, $result);
                $log->update([
                    'status' => 'verified',
                    'executed_at' => now(),
                    'verified_at' => now(),
                    'result_payload' => $summary,
                ]);
                $this->conversationContext->rememberResult($intent, $data, $summary);
                $results[] = [
                    'success' => true,
                    'action_type' => $intent,
                    'message' => $summary['message'] ?? 'Consulta completada.',
                    'data' => $summary['data'] ?? [],
                ];
            }

            return response()->json([
                'success' => true,
                'any_success' => true,
                'actions' => $results,
                'message' => $this->interpreter->narrate($rawText, $results),
            ]);
        }

        $pending = [];
        foreach ($prepared as $action) {
            $log = $this->createAuditLog(
                $director,
                $action['intent'],
                'pending_confirmation',
                $rawText,
                $action['data'],
            );
            $pending[] = [
                'intent' => $action['intent'],
                'data' => $action['data'],
                'audit_log_id' => $log->id,
            ];
        }

        session([self::PENDING_SESSION_KEY => $pending]);
        $confirmations = collect($pending)
            ->map(fn ($action) => [
                'success' => true,
                'message' => $this->confirmationMessageFor($action['intent'], $action['data']),
            ])
            ->all();

        return response()->json([
            'success' => true,
            'requires_confirmation' => true,
            'message' => $this->interpreter->composeReply($confirmations, true),
            'pending_actions' => $pending,
        ]);
    }

    private function createAuditLog(
        User $director,
        string $intent,
        string $status,
        string $rawText,
        array $data,
    ): DirectorAiOperationLog {
        Log::info('nova_ai_write', [
            'user_id' => $director->id,
            'school_id' => $director->colegio_id,
            'action' => $intent,
            'status' => $status,
            'timestamp' => now()->toIso8601String(),
        ]);

        return DirectorAiOperationLog::create([
            'director_user_id' => $director->id,
            'colegio_id' => $director->colegio_id,
            'intent' => $intent,
            'status' => $status,
            'input_payload' => [
                'raw_text' => $rawText,
                'data' => $data,
            ],
        ]);
    }

    private function hydrateActionData(User $director, string $intent, array $data): array
    {
        if (in_array($intent, ['create_teacher', 'create_course', 'assign_teacher', 'unassign_teacher'], true)) {
            $data['grades'] = array_values(array_filter((array) ($data['grades'] ?? [])));
            if ($intent === 'create_course' && $data['grades'] !== []) {
                $data['grade'] = $data['grade'] ?? $data['grades'][0];
            }
            $data['missing_grades'] = $this->missingGradesFor($director, $data['grades']);
        }
        if ($intent === 'create_teacher') {
            $data['subject_name'] = $data['subject_name'] ?? null;
            $data['expires_in_days'] = 30;
        }
        if ($intent === 'delete_all_teachers') {
            $data['count'] = User::query()
                ->where('colegio_id', $director->colegio_id)
                ->where('role', 'profesor')
                ->count();
        }
        if ($intent === 'delete_all_courses') {
            $data['count'] = Course::query()->where('colegio_id', $director->colegio_id)->count();
        }
        if ($intent === 'delete_course') {
            $subject = trim((string) ($data['subject_name'] ?? ''));
            $query = Course::query()
                ->where('colegio_id', $director->colegio_id)
                ->whereRaw('LOWER(subject_name) like ?', ['%'.rtrim(mb_strtolower($subject), 's').'%']);
            if (! empty($data['grade'])) {
                $query->whereRaw('LOWER(grade) = ?', [mb_strtolower((string) $data['grade'])]);
            }
            $data['match_count'] = $query->count();
        }

        return $data;
    }

    private function validateActionReferences(User $director, string $intent, array $data): void
    {
        if (in_array($intent, ['create_course', 'assign_teacher', 'unassign_teacher'], true) && ! empty($data['teacher_name'])) {
            $this->actionService->resolveAssigneeForDirector($director, (string) $data['teacher_name']);
        }
    }

    /**
     * Resuelve el objetivo de eliminación antes de pedir confirmación.
     * Si hay ambigüedad, lanza ValidationException para que Nova pida aclaración.
     */
    private function resolveDeleteTarget(User $director, string $intent, array $data): array
    {
        $colegioId = (int) $director->colegio_id;
        $matcher = app(PersonNameMatcher::class);

        if ($intent === 'delete_teacher' && ! empty($data['teacher_name'])) {
            $name = (string) $data['teacher_name'];
            $match = $matcher->resolveTeacher($colegioId, $name);
            if ($match->isUnique()) {
                $data['teacher_name'] = $match->model->name;

                return $data;
            }
            if ($match->isNone()) {
                $inviteMatch = $matcher->resolveInvite($colegioId, $name);
                if ($inviteMatch->isUnique()) {
                    throw ValidationException::withMessages([
                        'teacher' => "No encontré a \"{$name}\" como profesor registrado. Sí hay una invitación pendiente: {$inviteMatch->label}. Para cancelarla escribe: \"Cancela la invitación del profesor {$inviteMatch->model->name}\".",
                    ]);
                }
                if ($inviteMatch->isAmbiguous()) {
                    throw ValidationException::withMessages([
                        'teacher' => "No encontré un profesor registrado con \"{$name}\", pero hay varias invitaciones pendientes.\n{$inviteMatch->message}",
                    ]);
                }
            }
            throw ValidationException::withMessages([
                'teacher' => $match->message ?? 'No encontré al profesor indicado en este colegio.',
            ]);
        }

        if ($intent === 'delete_teacher_invite' && ! empty($data['teacher_name'])) {
            $name = (string) $data['teacher_name'];
            $inviteMatch = $matcher->resolveInvite($colegioId, $name);
            if ($inviteMatch->isUnique()) {
                $data['teacher_name'] = $inviteMatch->model->name;
                $data['invite_id'] = $inviteMatch->model->id;
                $data['invite_code'] = $inviteMatch->model->invite_code;

                return $data;
            }
            if ($inviteMatch->isAmbiguous()) {
                throw ValidationException::withMessages([
                    'teacher' => $inviteMatch->message,
                ]);
            }

            $teacherMatch = $matcher->resolveTeacher($colegioId, $name);
            if ($teacherMatch->isUnique()) {
                throw ValidationException::withMessages([
                    'teacher' => "No hay una invitación pendiente para \"{$name}\" en este colegio. Encontré al profesor registrado {$teacherMatch->label}. Para eliminarlo escribe: \"Elimina al profesor {$teacherMatch->label}\".",
                ]);
            }
            if ($teacherMatch->isAmbiguous()) {
                throw ValidationException::withMessages([
                    'teacher' => "No hay una invitación pendiente para \"{$name}\" en este colegio, pero hay varios profesores registrados.\n{$teacherMatch->message}",
                ]);
            }

            throw ValidationException::withMessages([
                'teacher' => "No encontré una invitación pendiente ni un profesor registrado con \"{$name}\" en este colegio.",
            ]);
        }

        if ($intent === 'delete_student') {
            $batchNames = collect($data['names'] ?? [])
                ->map(fn ($name) => trim((string) $name))
                ->filter()
                ->unique()
                ->values();
            if ($batchNames->count() > 1) {
                unset($data['student_id']);
                $data['names'] = $batchNames->all();

                return $data;
            }

            $name = (string) ($data['student_name'] ?? $batchNames->first() ?? '');
            if ($name === '') {
                return $data;
            }
            $match = $matcher->resolveStudent($colegioId, $name);
            if (! $match->isUnique()) {
                throw ValidationException::withMessages([
                    'student' => $match->message ?? 'No encontré al alumno indicado en este colegio.',
                ]);
            }
            $data['student_name'] = $match->model->name;
            $data['student_id'] = $match->model->id;
        }

        return $data;
    }

    private function executePending(Request $request, User $director): JsonResponse
    {
        // The client copy is display-only. Execute only the canonical server-side plan.
        $actions = collect(session(self::PENDING_SESSION_KEY, []));
        if ($actions->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No hay acciones pendientes por confirmar.',
            ], 422);
        }

        $results = [];
        $anySuccess = false;
        $failedActions = [];

        foreach ($actions as $action) {
            $intent = (string) Arr::get($action, 'intent', '');
            $data = (array) Arr::get($action, 'data', []);
            $logId = Arr::get($action, 'audit_log_id');
            $log = $logId
                ? DirectorAiOperationLog::query()
                    ->where('id', $logId)
                    ->where('director_user_id', $director->id)
                    ->where('colegio_id', $director->colegio_id)
                    ->first()
                : null;

            try {
                if ($logId && ! $log) {
                    throw ValidationException::withMessages([
                        'pending_actions' => 'La acción pendiente no pertenece a este director o colegio.',
                    ]);
                }
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
                $this->conversationContext->rememberResult($intent, $data, $summary);
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
                $failedActions[] = $action;
                $this->conversationContext->rememberError($msg);
            } catch (\Throwable $e) {
                Log::error('Director AI execution failed', [
                    'director_id' => $director->id,
                    'intent' => $intent,
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
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
                    'message' => 'Falló la ejecución de la operación.',
                ];
                $failedActions[] = $action;
                $this->conversationContext->rememberError('Falló la ejecución de la operación.');
            }
        }

        if ($failedActions === []) {
            session()->forget(self::PENDING_SESSION_KEY);
        } else {
            session([self::PENDING_SESSION_KEY => $failedActions]);
        }

        return response()->json([
            'success' => $anySuccess,
            'any_success' => $anySuccess,
            'requires_clarification' => $failedActions !== [],
            'pending_actions' => $failedActions !== [] ? $failedActions : null,
            'actions' => $results,
            'message' => $this->interpreter->narrate(
                (string) ($this->conversationContext->current()['last_user_text'] ?? ''),
                $results,
            ),
        ]);
    }

    private function runIntent(User $director, string $intent, array $data): array
    {
        return match ($intent) {
            'create_teacher' => $this->actionService->createTeacherInviteWithAssignments($director, $data),
            'create_course' => count($data['grades'] ?? []) > 1
                ? $this->actionService->createCourses($director, $data)
                : $this->actionService->createCourse($director, $data),
            'assign_teacher' => $this->actionService->assignTeacherToGradesSubject($director, $data),
            'create_students_batch' => $this->actionService->createStudentsBatch($director, $data),
            'enroll_students_course' => $this->actionService->enrollStudentsToCourse($director, $data),
            'unenroll_students_course' => $this->actionService->unenrollStudentsFromCourse($director, $data),
            'unassign_teacher' => $this->actionService->unassignTeacher($director, $data),
            'update_course' => $this->actionService->updateCourse($director, $data),
            'update_student' => $this->actionService->updateStudent($director, $data),
            'manage_invite_code' => $this->actionService->manageInviteCode($director, $data),
            'query_academic' => $this->queryAcademic($director, $data),
            'delete_teacher' => $this->actionService->deleteTeacher($director, $data),
            'delete_teacher_invite' => $this->actionService->deleteTeacherInvite($director, $data),
            'delete_all_teachers' => $this->actionService->deleteAllTeachers($director, $data),
            'delete_course' => $this->actionService->deleteCourse($director, $data),
            'delete_all_courses' => $this->actionService->deleteAllCourses($director, $data),
            'delete_student' => $this->actionService->deleteStudent($director, $data),
            default => throw ValidationException::withMessages([
                'intent' => 'Intent no soportado para Director AI.',
            ]),
        };
    }

    private function verifyResult(User $director, string $intent, array $result): array
    {
        return match ($intent) {
            'create_teacher' => $this->verifyCreateTeacher($director, $result),
            'create_course' => isset($result['courses'])
                ? $this->verifyCreateCourses($director, $result)
                : $this->verifyCreateCourse($director, $result),
            'assign_teacher' => $this->verifyAssignTeacher($director, $result),
            'create_students_batch' => $this->verifyCreateStudentsBatch($director, $result),
            'enroll_students_course' => $this->verifyEnrollStudentsToCourse($director, $result),
            'unenroll_students_course' => $this->verifyUnenrollStudentsFromCourse($director, $result),
            'unassign_teacher', 'update_course', 'update_student' => $this->verifyGenericMutation($result),
            'manage_invite_code' => $this->verifyManageInviteCode($director, $result),
            'query_academic' => $this->verifyAcademicQueryResult($result),
            'delete_teacher' => $this->verifyDeletePeople($director, $result, 'profesor'),
            'delete_teacher_invite' => $this->verifyDeleteInvite($result),
            'delete_all_teachers' => $this->verifyDeletePeople($director, $result, 'profesor'),
            'delete_course' => $this->verifyDeleteCourses($director, $result),
            'delete_all_courses' => $this->verifyDeleteCourses($director, $result),
            'delete_student' => $this->verifyDeletePeople($director, $result, 'alumno'),
            default => throw ValidationException::withMessages([
                'intent' => 'No se pudo verificar el resultado.',
            ]),
        };
    }

    private function verifyCreateCourses(User $director, array $result): array
    {
        /** @var Collection<int,Course> $courses */
        $courses = $result['courses'];
        foreach ($courses as $course) {
            if ((int) $course->colegio_id !== (int) $director->colegio_id) {
                throw ValidationException::withMessages([
                    'course' => 'Un curso creado no pertenece al colegio del director.',
                ]);
            }
        }

        $labels = $courses->map(fn ($c) => "{$c->subject_name} {$c->grade}".($c->section ? " sección {$c->section}" : ''))->implode(', ');
        $created = (int) ($result['created_count'] ?? 0);
        $existing = (int) ($result['existing_count'] ?? 0);
        $parts = [];
        if ($created > 0) {
            $parts[] = "{$created} curso(s) creado(s)";
        }
        if ($existing > 0) {
            $parts[] = "{$existing} ya existente(s) y actualizado(s)";
        }
        $teacherText = $result['teacher_label'] ? " asignado a {$result['teacher_label']}" : '';

        return [
            'message' => 'Cursos listos: '.$labels.'.'.($parts !== [] ? ' '.implode(', ', $parts).'.' : '').$teacherText.'.',
            'data' => [
                'courses' => $courses->map(fn ($c) => [
                    'course_id' => $c->id,
                    'subject_name' => $c->subject_name,
                    'grade' => $c->grade,
                    'section' => $c->section,
                    'students_count' => $c->students_count,
                ])->values()->all(),
                'created_count' => $created,
                'existing_count' => $existing,
            ],
        ];
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
            'message' => "Profesor {$invite->name} invitado correctamente. Código DOC-: {$invite->invite_code}.",
            'data' => [
                'invite_code' => $invite->invite_code,
                'teacher_name' => $invite->name,
                'invite_id' => $invite->id,
                'status' => 'invitado',
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
            'message' => $this->composeCreateStudentsVerifiedMessage($created, $result),
            'data' => [
                'students' => $created->map(fn ($student) => [
                    'student_id' => $student->id,
                    'name' => $student->name,
                    'grade' => $student->grade,
                    'section' => $student->section,
                    'family_code' => $student->family_code,
                ])->values()->all(),
                'duplicates' => $result['duplicates'],
                'enrolled_count' => (int) ($result['enrolled_count'] ?? 0),
                'course_id' => $result['course']->id ?? null,
                'students_count' => $result['course']?->students()->count(),
            ],
        ];
    }

    private function composeCreateStudentsVerifiedMessage(Collection $created, array $result): string
    {
        $count = $created->count();
        $message = "Creé {$count} estudiante(s) correctamente.";
        /** @var Course|null $course */
        $course = $result['course'] ?? null;
        if ($course) {
            $teacher = $course->teacher?->name;
            $place = $course->subject_name.' '.$course->grade.($course->section ? ' / '.$course->section : '');
            $enrolled = (int) ($result['enrolled_count'] ?? 0);
            $total = (int) $course->students()->count();
            if (! empty($result['course_created'])) {
                $message .= " También creé el curso {$place}.";
            }
            $message .= $enrolled > 0
                ? " Quedó(aron) matriculado(s) en {$place}".($teacher ? " con {$teacher}" : '').". El curso tiene {$total} alumno(s)."
                : " Todavía no pude matricularlos en {$place}.";
        } elseif (! empty($result['placement_note'])) {
            $message .= ' '.$result['placement_note'];
        }

        return $message;
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

    private function verifyUnenrollStudentsFromCourse(User $director, array $result): array
    {
        /** @var Course $course */
        $course = $result['course'];
        if ((int) $course->colegio_id !== (int) $director->colegio_id) {
            throw ValidationException::withMessages([
                'unenroll' => 'La desmatriculación resultó fuera del colegio del director.',
            ]);
        }

        return [
            'message' => 'Desmatriculé '.count($result['unenrolled']).' alumno(s) de '.$course->subject_name.' '.$course->grade.'.',
            'data' => [
                'course_id' => $course->id,
                'unenrolled' => $result['unenrolled'],
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

    private function verifyGenericMutation(array $result): array
    {
        return [
            'message' => $result['message'] ?? 'Cambio aplicado correctamente.',
            'data' => $result['data'] ?? [],
        ];
    }

    /**
     * @param  array{deleted_count?:int, deleted_names?:array<int,string>}  $result
     */
    private function verifyDeletePeople(User $director, array $result, string $label): array
    {
        $count = (int) ($result['deleted_count'] ?? 0);
        $names = collect($result['deleted_names'] ?? [])->filter()->values();
        $suffix = $names->isNotEmpty() ? ' '.$names->implode(', ').'.' : '.';

        return [
            'message' => $count === 1
                ? "Eliminé 1 {$label} correctamente.".$suffix
                : "Eliminé {$count} {$label}(es) correctamente.".$suffix,
            'data' => [
                'deleted_count' => $count,
                'deleted_names' => $names->all(),
                'colegio_id' => $director->colegio_id,
            ],
        ];
    }

    /**
     * @param  array{deleted_count?:int, deleted_invites?:int, invite_label?:string, invite_code?:string}  $result
     */
    private function verifyDeleteInvite(array $result): array
    {
        $label = trim((string) ($result['invite_label'] ?? ''));
        $code = trim((string) ($result['invite_code'] ?? ''));
        $message = $label !== ''
            ? "Cancelé la invitación pendiente de {$label}."
            : 'Cancelé la invitación pendiente.';
        if ($code !== '') {
            $message .= " El código DOC- {$code} ya no es válido.";
        }
        $message .= ' No se eliminó ningún profesor registrado.';

        return [
            'message' => $message,
            'data' => [
                'invite_name' => $label,
                'invite_code' => $code,
                'deleted_count' => (int) ($result['deleted_count'] ?? 1),
                'deleted_invites' => (int) ($result['deleted_invites'] ?? 1),
            ],
        ];
    }

    /**
     * @param  array{deleted_count?:int, deleted_courses?:array<int,array>}  $result
     */
    private function verifyDeleteCourses(User $director, array $result): array
    {
        $count = (int) ($result['deleted_count'] ?? 0);
        $courses = collect($result['deleted_courses'] ?? []);
        $labels = $courses->map(function ($course) {
            $section = ! empty($course['section']) ? ' sección '.$course['section'] : '';

            return trim(($course['subject_name'] ?? '').' '.($course['grade'] ?? '').$section);
        })->filter()->implode(', ');

        return [
            'message' => $count === 0
                ? 'No había cursos para eliminar.'
                : "Eliminé {$count} curso(s)".($labels !== '' ? ': '.$labels : '').'.',
            'data' => [
                'deleted_count' => $count,
                'deleted_courses' => $courses->all(),
                'colegio_id' => $director->colegio_id,
            ],
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
            'unenroll_students_course' => $this->parseEnrollStudentsCourse($director, $text),
            'update_student' => $this->parseUpdateStudent($director, $text),
            'manage_invite_code' => $this->parseManageInviteCode($text),
            'query_academic' => $this->parseQueryAcademic($text),
            'delete_teacher' => $this->parseDeleteTeacher($director, $text),
            'delete_teacher_invite' => $this->parseDeleteInvite($director, $text),
            'delete_all_teachers' => $this->parseDeleteAllTeachers($director),
            'delete_course' => $this->parseDeleteCourse($director, $text),
            'delete_all_courses' => $this->parseDeleteAllCourses($director),
            'delete_student' => $this->parseDeleteStudent($director, $text),
            default => [[], 'No pude convertir tu solicitud en una operación segura.'],
        };
    }

    private function confirmationMessageFor(string $intent, array $data): string
    {
        $createdGrades = $this->mentionMissingGrades($intent, $data);
        $createdGrades = $createdGrades !== '' ? ' '.$createdGrades : '';

        $message = match ($intent) {
            'create_teacher' => $this->summarizeCreateTeacher($data),
            'create_course' => count($data['grades'] ?? []) > 1
                ? 'Crear los cursos de '.$data['subject_name'].' para '.implode(', ', $data['grades']).(($data['section'] ?? null) ? " sección {$data['section']}" : '').'.'
                : "Crear el curso {$data['subject_name']} para {$data['grade']}".(($data['section'] ?? null) ? " sección {$data['section']}" : '').'.',
            'assign_teacher' => "Asignar a {$data['teacher_name']} la materia {$data['subject_name']} en ".$this->formatGradeSpan((array) ($data['grades'] ?? [])).'.',
            'create_students_batch' => $this->summarizeCreateStudents($data),
            'enroll_students_course' => ! empty($data['all_in_grade'])
                ? 'Inscribir a los alumnos de '.$data['grade'].(($data['section'] ?? null) ? " sección {$data['section']}" : '')." en {$data['subject_name']}".(! empty($data['teacher_name']) ? " con {$data['teacher_name']}" : '').'.'
                : 'Inscribir '.implode(', ', (array) ($data['names'] ?? []))." en {$data['subject_name']} {$data['grade']}".(($data['section'] ?? null) ? " sección {$data['section']}" : '').'.',
            'unenroll_students_course' => 'Desmatricular '.count($data['names'] ?? [])." alumno(s) de {$data['subject_name']} {$data['grade']}".(($data['section'] ?? null) ? " sección {$data['section']}" : '').'.',
            'unassign_teacher' => 'Desasignar los cursos indicados de '.($data['teacher_name'] ?? 'ese profesor').'.',
            'update_course' => 'Modificar el curso '.($data['subject_name'] ?? '').' '.($data['grade'] ?? '').'.',
            'update_student' => 'Mover / actualizar a '.($data['student_name'] ?? 'ese alumno')
                .(! empty($data['new_grade']) ? ' a '.$data['new_grade'] : '')
                .(! empty($data['new_section']) ? ' sección '.$data['new_section'] : '').'.',
            'manage_invite_code' => 'Consultar el estado del código DOC-.',
            'delete_teacher' => 'Eliminar al profesor '.($data['teacher_name'] ?? '').'. Los cursos se desasignarán, no se borrarán.',
            'delete_teacher_invite' => 'Cancelar la invitación pendiente de '.($data['teacher_name'] ?? '').
                (($data['invite_code'] ?? null) ? ' (código DOC- '.$data['invite_code'].')' : '').
                '. No se eliminará ningún profesor registrado.',
            'delete_all_teachers' => 'Eliminar a '.((int) ($data['count'] ?? 0)).' profesor(es) de tu colegio. Los cursos se desasignarán, no se borrarán.',
            'delete_course' => 'Eliminar '.((int) ($data['match_count'] ?? 0)).' curso(s) de '.($data['subject_name'] ?? 'la asignatura indicada').
                (($data['grade'] ?? null) ? ' '.$data['grade'] : '').'.',
            'delete_all_courses' => 'Eliminar todos los cursos del colegio ('.((int) ($data['count'] ?? 0)).').',
            'delete_student' => ! empty($data['names']) && count((array) $data['names']) > 1
                ? 'Eliminar a '.count((array) $data['names']).' alumnos: '.implode(', ', (array) $data['names']).'.'
                : 'Eliminar al alumno '.($data['student_name'] ?? implode(', ', (array) ($data['names'] ?? []))).'.',
            default => 'Confirmar la operación.',
        };

        return $message.$createdGrades;
    }

    /**
     * @param  array{teacher_name?:string,subject_name?:string|null,grades?:array}  $data
     */
    private function summarizeCreateTeacher(array $data): string
    {
        $name = trim((string) ($data['teacher_name'] ?? 'el profesor'));
        $subject = trim((string) ($data['subject_name'] ?? ''));
        $span = $this->formatGradeSpan((array) ($data['grades'] ?? []));
        if ($subject !== '' && $span !== '') {
            return "Crear al Profesor {$name} ({$subject} - {$span}).";
        }
        if ($subject !== '') {
            return "Crear al Profesor {$name} ({$subject}).";
        }

        return "Crear la invitación para {$name} sin materias iniciales.";
    }

    /**
     * @param  array{names?:array,grade?:string,section?:string|null,subject_name?:string|null}  $data
     */
    private function summarizeCreateStudents(array $data): string
    {
        $names = array_filter((array) ($data['names'] ?? []));
        $grade = trim((string) ($data['grade'] ?? ''));
        $section = trim((string) ($data['section'] ?? ''));
        $subject = trim((string) ($data['subject_name'] ?? ''));
        $place = $grade !== '' ? "{$grade} Grado" : 'el grado indicado';
        if ($section !== '') {
            $place .= " / {$section}";
        }

        if (count($names) > 1) {
            $lines = [];
            $idx = 1;
            foreach ($names as $name) {
                $lines[] = "{$idx}. {$name}";
                $idx++;
            }
            $subjectPart = $subject !== '' ? " ({$subject})" : '';

            return 'Crear '.count($names)." estudiantes en {$place}{$subjectPart}:\n".implode("\n", $lines).'.';
        }

        $namesStr = implode(', ', $names) ?: 'alumno(s)';
        $teacher = trim((string) ($data['teacher_name'] ?? ''));
        $extra = '';
        if ($subject !== '') {
            $extra .= " ({$subject})";
        }
        if ($teacher !== '') {
            $extra .= " con {$teacher}";
        }
        if ($extra !== '') {
            return "Crear al Alumno {$namesStr} en {$place}{$extra} y matricularlo en el curso.";
        }

        return "Crear al Alumno {$namesStr} en {$place}.";
    }

    /**
     * @param  array<int,string>  $grades
     */
    private function formatGradeSpan(array $grades): string
    {
        $grades = array_values(array_filter($grades, fn ($grade) => trim((string) $grade) !== ''));
        if ($grades === []) {
            return '';
        }
        if (count($grades) === 1) {
            return $grades[0];
        }

        return $grades[0].' a '.$grades[array_key_last($grades)];
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

    /**
     * Resolve terse follow-ups when the external interpreter is unavailable.
     *
     * @return array{intent:string,data:array}|null
     */
    private function contextualFallbackAction(string $text): ?array
    {
        $value = $this->normalizedText($text);
        $context = $this->conversationContext->current();
        $teacher = trim((string) ($context['teacher_name'] ?? ''));
        $subject = trim((string) ($context['subject_name'] ?? ''));
        $grades = array_values(array_filter((array) ($context['grades'] ?? [])));

        if (preg_match('/^(?:crealo|crear?lo|hazlo)$/', $value) && $teacher !== '') {
            return [
                'intent' => 'create_teacher',
                'data' => [
                    'teacher_name' => $teacher,
                    'subject_name' => $subject !== '' ? $subject : null,
                    'grades' => $grades,
                    'expires_in_days' => 30,
                ],
            ];
        }

        if ((str_contains($value, 'agregale') || str_contains($value, 'asignale') || str_contains($value, 'los cursos que dijimos'))
            && $teacher !== '') {
            $parsedSubject = $this->extractSubject($text)
                ?? $this->extractSubjectFromDeletePrompt($text)
                ?? ($subject !== '' ? $subject : null);
            $parsedGrades = $this->extractGrades($text);
            if ($parsedGrades === []) {
                $parsedGrades = $grades;
            }
            if ($parsedSubject && $parsedGrades !== []) {
                return [
                    'intent' => 'assign_teacher',
                    'data' => [
                        'teacher_name' => $this->extractTeacherName($text) ?? $teacher,
                        'subject_name' => $parsedSubject,
                        'grades' => $parsedGrades,
                    ],
                ];
            }
        }

        return null;
    }

    private function detectIntent(string $text): ?string
    {
        $value = $this->normalizedText($text);

        // Eliminar / borrar / quitar / remover / limpiar / cancelar (antes de crear, para no confundir verbos).
        if ($this->hasDeleteVerb($value)) {
            if (preg_match('/\b(?:invitacion|invitaci[oó]n|invitaciones|invite|invites)\b/', $value)) {
                if (preg_match('/\b(?:profesor(?:a)?|docente)\b/', $value) || preg_match('/\b(?:de|del)\s+[a-z]+\s+profesor\b/', $value)) {
                    return 'delete_teacher_invite';
                }
                if (! preg_match('/\b(?:alumno|estudiante|curso|materia|asignatura)s?\b/', $value)) {
                    return 'delete_teacher_invite';
                }
            }
            if ($this->isMassPeopleTarget($value) && preg_match('/\b(?:profesores|profesoras|docentes)\b/', $value)) {
                return 'delete_all_teachers';
            }
            if ($this->isMassCourseTarget($value)) {
                return 'delete_all_courses';
            }
            if (preg_match('/\b(?:profesor(?:a)?|docente)\b/', $value)) {
                return 'delete_teacher';
            }
            if (preg_match('/\b(?:alumno|estudiante)s?\b/', $value) && ! preg_match('/\b(?:profesores|docentes|cursos)\b/', $value)) {
                return 'delete_student';
            }
            if (preg_match('/\b(?:curso|asignatura|materia)s?\b/', $value)) {
                return 'delete_course';
            }
        }

        // A teacher creation may also mention courses/subjects. It must win over create_course.
        if (preg_match('/\b(?:cre(?:a|ar|es|e|o)|creame|invita)\s+(?:(?:a|al)\s+|el\s+|la\s+)?(?:profesor(?:a)?|docente)\b/', $value)) {
            return 'create_teacher';
        }

        // Mover / cambiar de grado o sección.
        if (preg_match('/\b(?:mueve|mover|traslada|trasladar|pasa(?:r)?|cambi(?:a|ar))\b/', $value)
            && preg_match('/\b(?:alumno|estudiante)s?\b/', $value)
            && (str_contains($value, 'grado') || str_contains($value, 'seccion') || str_contains($value, 'curso'))) {
            return 'update_student';
        }

        // Student creation/enrollment must win over create_course when the user mentions alumnos.
        if (preg_match('/\b(?:alumno|estudiante)s?\b/', $value)
            && (preg_match('/\b(?:cre(?:a|ar|es|e|o)|creame|agrega|matricula|inscribe)\b/', $value)
                || preg_match('/\b(?:asigna|inscribe|matricula|agregalo|añade)\b/', $value))) {
            $looksLikeEnroll = (str_contains($value, 'curso') || str_contains($value, 'materia') || str_contains($value, 'asignatura'))
                && preg_match('/\b(?:asigna(?:lo|le|r|les)?|inscribe(?:lo|le|r|les)?|matricula(?:lo|le|r|les)?|agregalo|añade|anade)\b/', $value);
            $enrollWithoutCreate = preg_match('/\b(?:inscribe|asigna(?:lo|le)?|matricula(?:lo|le)?)\b/', $value)
                && ! preg_match('/\b(?:cre(?:a|ar|es|e|o)|creame)\b/', $value);
            if ($looksLikeEnroll || $enrollWithoutCreate) {
                return 'enroll_students_course';
            }

            return 'create_students_batch';
        }

        // Crear uno o varios cursos: "crea el curso de X", "crees los cursos de: 1ero..6to de ingles",
        // "Crea Matemática para 4.º, 5.º y 6.º."
        if (! preg_match('/\b(?:alumno|estudiante)s?\b/', $value)
            && (preg_match('/\bcre(?:a|ar|es|e|o)\b/', $value) || str_contains($value, 'crea') || str_contains($value, 'crear') || str_contains($value, 'crees'))
            && (str_contains($value, 'curso') || str_contains($value, 'cursso') || str_contains($value, 'asignatura') || str_contains($value, 'materia')
                || preg_match('/\b(?:para|en|de|del)\s+(?:el\s+)?[1-6](?:ro|er|do|to|°|º|ero)?\s*(?:grado\b|[,.]|(?:y|e)\b|$)/', $value))) {
            return 'create_course';
        }
        if ((str_contains($value, 'dara') || str_contains($value, 'asigna') || str_contains($value, 'agregale') || str_contains($value, 'asignale'))
            && (str_contains($value, 'grado') || str_contains($value, 'curso') || str_contains($value, 'materia') || preg_match('/\b[1-6](ro|do|to|er)?\b/', $value))) {
            return 'assign_teacher';
        }
        if ((str_contains($value, 'desmatricula') || str_contains($value, 'retira') || str_contains($value, 'saca'))
            && str_contains($value, 'curso')
            && (str_contains($value, 'alumno') || str_contains($value, 'estudiante') || preg_match('/\sa\s+[a-z]/', $value))) {
            return 'unenroll_students_course';
        }
        if ((str_contains($value, 'inscribe') || preg_match('/\basigna(?:lo|le|r|les)?\b/', $value))
            && str_contains($value, 'curso')
            && (str_contains($value, 'alumno') || str_contains($value, 'estudiante') || preg_match('/\sa\s+[a-z]/', $value))) {
            return 'enroll_students_course';
        }
        if ((str_contains($value, 'agrega') || str_contains($value, 'matricula') || str_contains($value, 'crear') || str_contains($value, 'inscribe') || str_contains($value, 'crea a') || str_contains($value, 'crear a'))
            && (str_contains($value, 'alumno') || str_contains($value, 'estudiante'))) {
            return 'create_students_batch';
        }
        if ((str_contains($value, 'agrega') || str_contains($value, 'matricula') || str_contains($value, 'crea') || str_contains($value, 'crear'))
            && preg_match('/\b[1-6](ro|do|to|er)?\b.*grado/', $value)) {
            return 'create_students_batch';
        }
        if ((str_contains($value, 'doc-') || str_contains($value, 'codigo'))
            && (str_contains($value, 'consulta') || str_contains($value, 'estado') || str_contains($value, 'mostrar') || str_contains($value, 'tiene') || str_contains($value, 'dame'))) {
            return 'manage_invite_code';
        }
        if (
            str_contains($value, 'como va')
            || str_contains($value, 'como van')
            || str_contains($value, 'como estan')
            || str_contains($value, 'que alumnos tiene')
            || str_contains($value, 'que alumnos hay')
            || str_contains($value, 'que cursos tiene')
            || str_contains($value, 'cuantas faltas')
            || str_contains($value, 'como estan sus evaluaciones')
            || str_contains($value, 'como estan las evaluaciones')
            || str_contains($value, 'cuantos alumnos')
            || str_contains($value, 'cuantos profesores')
            || str_contains($value, 'cuantos cursos')
            || str_contains($value, 'que profesores')
            || str_contains($value, 'que cursos')
            || str_contains($value, 'quien ha faltado')
            || str_contains($value, 'quienes han faltado')
            || str_contains($value, 'quien esta faltando')
            || str_contains($value, 'problemas en')
            || str_contains($value, 'bajo rendimiento')
            || str_contains($value, 'como esta')
            // Analítica en tiempo real.
            || str_contains($value, 'cada seccion')
            || str_contains($value, 'por seccion')
            || str_contains($value, 'mas destacado')
            || str_contains($value, 'el destacado')
            || str_contains($value, 'mejor alumno')
            || str_contains($value, 'mejor promedio')
            || str_contains($value, 'mejores promedios')
            || str_contains($value, 'mas faltas')
            || str_contains($value, 'compara')
            || str_contains($value, 'tendencia')
            || str_contains($value, 'evolucion')
            || str_contains($value, 'ranking')
            || preg_match('/\btop\s+\d/', $value)
            || preg_match('/\bquien(?:es)?\s+tiene(?:n)?\b/', $value)
            || ((str_contains($value, 'consulta') || str_contains($value, 'muestrame') || str_contains($value, 'mostrar') || str_contains($value, 'estado') || str_contains($value, 'dame'))
                && (str_contains($value, 'profesor') || str_contains($value, 'estudiante') || str_contains($value, 'alumno') || str_contains($value, 'curso')))
        ) {
            return 'query_academic';
        }

        return null;
    }

    /**
     * Detecta varias intenciones en un mismo mensaje (profesor + cursos + alumno + matrícula).
     *
     * @return array<int,array{intent:string,data:array}>
     */
    private function detectMultiIntentActions(User $director, string $text): array
    {
        $actions = [];
        $clauses = $this->splitIntentClauses($text);
        if ($clauses === []) {
            $clauses = [$text];
        }

        foreach ($clauses as $clause) {
            $value = $this->normalizedText($clause);
            $wantsTeacher = (bool) preg_match(
                '/\b(?:cre(?:a|ar|es|e|o)|creame|invita)\s+(?:(?:a|al)\s+|el\s+|la\s+)?(?:profesor(?:a)?|docente)\b/',
                $value
            );
            $wantsAssignTeacher = (bool) preg_match(
                '/\b(?:as[ií]gna(?:le)?|dara|dará|agrega(?:le)?)\b/',
                $value
            );
            $wantsStudent = (bool) preg_match('/\b(?:alumno|estudiante)s?\b/', $value)
                && (bool) preg_match('/\b(?:cre(?:a|ar|es|e|o)|creame|agrega|matricula)\b/', $value);
            $wantsEnroll = $wantsStudent && (
                str_contains($value, 'curso') || str_contains($value, 'materia') || str_contains($value, 'asignatura')
            ) && (bool) preg_match('/\b(?:asigna(?:lo|le|r|les)?|inscribe(?:lo|le|r|les)?|matricula(?:lo|le|r|les)?|agregalo|añade|anade)\b/', $value);

            if ($wantsTeacher) {
                $names = $this->extractTeacherNames($clause);
                if ($names === []) {
                    [$data, $msg] = $this->parseCreateTeacher($director, $clause);
                    if (! $msg && ! empty($data['teacher_name'])) {
                        $names = [$data['teacher_name']];
                    }
                }
                if (count($names) > 5) {
                    throw ValidationException::withMessages([
                        'teacher' => 'Veo más de 5 profesores en tu mensaje. Dime los nombres de a uno o máximo 5 a la vez.',
                    ]);
                }
                if ($names !== []) {
                    [$sharedData, $msg] = $this->parseCreateTeacher($director, $clause);
                    foreach ($names as $name) {
                        $data = $sharedData;
                        $data['teacher_name'] = $name;
                        $actions[] = ['intent' => 'create_teacher', 'data' => $data];
                    }
                }
            }

            if ($wantsAssignTeacher) {
                $assignSegments = preg_split(
                    '/\s+y\s+(?=a\s+[A-Za-zÁÉÍÓÚáéíóúÑñ]|as[ií]gna(?:le)?\s+|dara\s+|dará\s+|agrega(?:le)?\s+)/iu',
                    $clause,
                    -1,
                    PREG_SPLIT_NO_EMPTY
                );
                if ($assignSegments === [] || count($assignSegments) === 1) {
                    [$data, $msg] = $this->parseAssignTeacher($director, $clause);
                    if (! $msg && ! empty($data['teacher_name']) && ! empty($data['subject_name']) && $data['grades'] !== []) {
                        $actions[] = ['intent' => 'assign_teacher', 'data' => $data];
                    }
                } else {
                    foreach ($assignSegments as $segment) {
                        $segment = trim((string) $segment);
                        if ($segment === '') {
                            continue;
                        }
                        [$data, $msg] = $this->parseAssignTeacher($director, $segment);
                        if (! $msg && ! empty($data['teacher_name']) && ! empty($data['subject_name']) && $data['grades'] !== []) {
                            $actions[] = ['intent' => 'assign_teacher', 'data' => $data];
                        }
                    }
                }
            }

            if ($wantsStudent) {
                [$data, $msg] = $this->parseCreateStudentsBatch($director, $clause);
                if (! $msg && ! empty($data['names'])) {
                    $actions[] = ['intent' => 'create_students_batch', 'data' => $data];
                }
                if ($wantsEnroll) {
                    [$enroll, $enrollMsg] = $this->parseEnrollStudentsCourse($director, $clause);
                    if (! $enrollMsg && ! empty($enroll['names'])) {
                        $actions[] = ['intent' => 'enroll_students_course', 'data' => $enroll];
                    }
                }
            }
        }

        return $this->dedupeDetectedActions($actions);
    }

    /**
     * @return array<int,string>
     */
    private function splitIntentClauses(string $text): array
    {
        $parts = preg_split(
            '/\s+(?:y\s+)?(?:tambien|también|ademas|además)\s+|\s+y\s+(?=crea(?:r|me)?\s+(?:al?\s+)?(?:alumno|estudiante|profesor|docente))/iu',
            $text,
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        return collect($parts ?: [$text])
            ->map(fn ($part) => trim((string) $part))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<int,array{intent:string,data:array}>  $actions
     * @return array<int,array{intent:string,data:array}>
     */
    private function mergeMissingIntentsFromText(User $director, array $actions, string $text): array
    {
        foreach ($this->detectMultiIntentActions($director, $text) as $candidate) {
            if (! $this->planHasSimilarAction($actions, $candidate)) {
                $actions[] = $candidate;
            }
        }

        return array_values($actions);
    }

    /**
     * @param  array<int,array{intent:string,data:array}>  $actions
     * @param  array{intent:string,data:array}  $candidate
     */
    private function planHasSimilarAction(array $actions, array $candidate): bool
    {
        $intent = (string) ($candidate['intent'] ?? '');
        foreach ($actions as $action) {
            if (($action['intent'] ?? '') !== $intent) {
                continue;
            }
            if ($intent === 'create_teacher') {
                $existingName = trim((string) ($action['data']['teacher_name'] ?? ''));
                $candidateName = trim((string) ($candidate['data']['teacher_name'] ?? ''));
                if ($existingName === '' || $candidateName === '') {
                    continue;
                }
                $left = mb_strtolower($existingName);
                $right = mb_strtolower($candidateName);

                return $left === $right || str_contains($left, $right) || str_contains($right, $left);
            }
            if ($intent === 'assign_teacher') {
                $existingName = trim((string) ($action['data']['teacher_name'] ?? ''));
                $candidateName = trim((string) ($candidate['data']['teacher_name'] ?? ''));
                $existingSubject = trim((string) ($action['data']['subject_name'] ?? ''));
                $candidateSubject = trim((string) ($candidate['data']['subject_name'] ?? ''));
                if ($existingName === '' || $candidateName === '') {
                    continue;
                }
                $leftName = mb_strtolower($existingName);
                $rightName = mb_strtolower($candidateName);
                $sameName = $leftName === $rightName || str_contains($leftName, $rightName) || str_contains($rightName, $leftName);
                $sameSubject = $existingSubject !== '' && $candidateSubject !== '' && mb_strtolower($existingSubject) === mb_strtolower($candidateSubject);

                return $sameName && $sameSubject;
            }
            if (in_array($intent, ['create_students_batch', 'enroll_students_course'], true)) {
                $existing = collect($action['data']['names'] ?? [])->map(fn ($name) => mb_strtolower(trim((string) $name)));
                $incoming = collect($candidate['data']['names'] ?? [])->map(fn ($name) => mb_strtolower(trim((string) $name)));

                return $existing->intersect($incoming)->isNotEmpty() || $existing->isNotEmpty();
            }

            return true;
        }

        return false;
    }

    private function samePerson(string $left, string $right): bool
    {
        return mb_strtolower(trim($left)) === mb_strtolower(trim($right));
    }

    /**
     * @param  array<int,array{intent:string,data:array}>  $actions
     * @return array<int,array{intent:string,data:array}>
     */
    private function dedupeDetectedActions(array $actions): array
    {
        $seen = [];
        $unique = [];
        foreach ($actions as $action) {
            $key = ($action['intent'] ?? '').'|'.md5(json_encode($action['data'] ?? []));
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $action;
        }

        return $unique;
    }

    private function teacherClauseMentionsCourses(string $text): bool
    {
        $span = $text;
        if (preg_match('/^(.*?)(?:\b(?:tambien|también|ademas|además|y)\s+)?(?:crea(?:r|me)?\s+)?(?:al?\s+)?(?:alumno|estudiante)/iu', $text, $m)) {
            $span = trim((string) $m[1]);
        }

        return $span !== '' && $this->utteranceMentionsCourses($span);
    }

    /**
     * @return array{0:array,1:?string}
     */
    private function parseDeleteTeacher(User $director, string $text): array
    {
        $name = $this->extractNamedPersonAfterRole($text, 'profesor');
        if (! $name) {
            return [[], '¿A qué profesor deseas eliminar? Ejemplo: "Elimina al profesor Carlos Pérez".'];
        }

        return [[
            'teacher_name' => $name,
        ], null];
    }

    /**
     * @return array{0:array,1:?string}
     */
    private function parseDeleteInvite(User $director, string $text): array
    {
        $name = $this->extractNamedPersonAfterRole($text, 'profesor');
        if (! $name && preg_match('/invitaci[oó]n\s+(?:del\s+profesor|de\s+)?([A-Za-zÁÉÍÓÚáéíóúÑñ]+(?:\s+[A-Za-zÁÉÍÓÚáéíóúÑñ]+){0,3})\b/iu', $text, $m)) {
            $name = trim((string) $m[1]);
        }
        $name = $name ? $this->sanitizePersonName($name) : null;
        if (! $name) {
            return [[], '¿De qué profesor deseas cancelar la invitación? Ejemplo: "Cancela la invitación del profesor Carlos Pérez".'];
        }

        return [[
            'teacher_name' => $name,
        ], null];
    }

    /**
     * @return array{0:array,1:?string}
     */
    private function parseDeleteAllTeachers(User $director): array
    {
        $count = User::query()
            ->where('colegio_id', $director->colegio_id)
            ->where('role', 'profesor')
            ->count();

        return [[
            'count' => $count,
        ], null];
    }

    /**
     * @return array{0:array,1:?string}
     */
    private function parseDeleteCourse(User $director, string $text): array
    {
        $subject = $this->extractSubjectFromCoursePrompt($text) ?? $this->extractSubjectFromDeletePrompt($text);
        if (! $subject) {
            return [[], '¿Qué curso debo eliminar? Ejemplo: "Borra el curso de matemáticas".'];
        }

        $grade = $this->extractTargetGrade($text);
        $section = $this->extractSection($text);
        $subjectKey = rtrim(mb_strtolower($subject), 's');
        $query = Course::query()
            ->where('colegio_id', $director->colegio_id)
            ->whereRaw('LOWER(subject_name) like ?', ['%'.$subjectKey.'%']);
        if ($grade) {
            $query->whereRaw('LOWER(grade) = ?', [mb_strtolower($grade)]);
        }
        $matchCount = $query->count();
        if ($matchCount === 0) {
            return [[], 'No encontré un curso de '.$subject.($grade ? " {$grade}" : '').' en tu colegio.'];
        }

        return [[
            'subject_name' => $subject,
            'grade' => $grade,
            'section' => $section,
            'match_count' => $matchCount,
        ], null];
    }

    /**
     * @return array{0:array,1:?string}
     */
    private function parseDeleteAllCourses(User $director): array
    {
        $count = Course::query()->where('colegio_id', $director->colegio_id)->count();

        return [[
            'count' => $count,
        ], null];
    }

    /**
     * @return array{0:array,1:?string}
     */
    private function parseDeleteStudent(User $director, string $text): array
    {
        $blob = '';
        if (preg_match('/(?:elimina(?:r)?|borra(?:r)?|quita(?:r)?)\s+(?:a\s+|al\s+)?(?:los\s+|las\s+)?(?:alumnos?|estudiantes?)\s+(.+)$/iu', $text, $m)) {
            $blob = trim((string) $m[1]);
        }
        $names = $blob !== '' ? $this->splitAndSanitizeNames($blob) : [];
        $single = $this->extractNamedPersonAfterRole($text, 'alumno')
            ?? $this->extractNamedPersonAfterRole($text, 'estudiante');
        if ($single) {
            $names = array_values(array_unique(array_filter(array_merge(
                $names,
                count($this->splitAndSanitizeNames($single)) > 1
                    ? $this->splitAndSanitizeNames($single)
                    : [$single]
            ))));
        }
        if ($names === []) {
            return [[], '¿A qué alumno deseas eliminar? Ejemplo: "Elimina a los alumnos Carlos, Juan y María".'];
        }

        return [[
            'student_name' => $names[0],
            'names' => $names,
        ], null];
    }

    /**
     * @return array{0:array,1:?string}
     */
    private function parseUpdateStudent(User $director, string $text): array
    {
        $name = $this->extractNamedPersonAfterRole($text, 'alumno')
            ?? $this->extractNamedPersonAfterRole($text, 'estudiante');
        if (! $name) {
            return [[], '¿A qué alumno debo mover? Ejemplo: "Mueve al alumno Vicente José a 2do grado sección B".'];
        }

        $grade = $this->extractTargetGrade($text);
        $section = $this->extractSection($text);
        if (! $grade && ! $section) {
            return [[], 'Indica el grado o la sección destino. Ejemplo: "Mueve a Vicente José a 2do grado sección B".'];
        }

        return [[
            'student_name' => $name,
            'new_grade' => $grade,
            'new_section' => $section,
        ], null];
    }

    /**
     * @return array{0:array,1:?string}
     */
    private function parseCreateTeacher(User $director, string $text): array
    {
        $name = $this->sanitizePersonName($this->extractTeacherName($text));
        if (! $name) {
            return [[], '¿Cuál es el nombre completo del profesor que deseas crear?'];
        }

        $subject = $this->extractKnownSubject($text)
            ?? $this->extractSubject($text)
            ?? $this->extractSubjectFromCoursePrompt($text)
            ?? $this->extractSubjectFromDeletePrompt($text);
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
        $grades = $this->extractGrades($text);

        $subject = $this->extractSubjectFromCoursePrompt($text);
        if (! $subject) {
            return [[], '¿Qué asignatura debo crear? Ejemplo: "Crea Matemática para 4.º, 5.º y 6.º".'];
        }

        if ($grades === []) {
            $grade = $this->extractTargetGrade($text);
            if (! $grade) {
                return [[], '¿Para qué grado debo crear el curso?'];
            }
            $grades = [$grade];
        }

        $section = $this->extractSection($text);
        $teacherName = $this->extractTeacherName($text);
        $missingGrades = $this->missingGradesFor($director, $grades);

        return [[
            'subject_name' => $subject,
            'grade' => $grades[0],
            'grades' => $grades,
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
        $context = $this->conversationContext->current();
        $name = $this->sanitizePersonName($this->extractTeacherName($text) ?? ($context['teacher_name'] ?? null));
        if (! $name) {
            return [[], '¿A qué profesor deseas asignar la materia?'];
        }

        $subject = $this->extractSubject($text)
            ?? $this->extractSubjectFromCoursePrompt($text)
            ?? $this->extractSubjectFromDeletePrompt($text)
            ?? $this->extractKnownSubject($text)
            ?? ($context['subject_name'] ?? null);
        if (! $subject) {
            return [[], '¿Qué materia deseas asignar?'];
        }

        $grades = $this->extractGrades($text);
        if ($grades === [] && is_array($context['grades'] ?? null)) {
            $grades = $context['grades'];
        }
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
        $subject = $this->extractKnownSubject($text)
            ?? $this->extractSubjectFromCoursePrompt($text)
            ?? $this->extractSubject($text);
        $teacherName = $this->sanitizePersonName($this->extractTeacherName($text));
        $missingGrades = $this->missingGradesFor($director, [$grade]);

        return [[
            'names' => $names,
            'grade' => $grade,
            'section' => $section,
            'subject_name' => $subject,
            'teacher_name' => $teacherName,
            'missing_grades' => $missingGrades,
        ], null];
    }

    /**
     * @return array{0:array,1:?string}
     */
    private function parseEnrollStudentsCourse(User $director, string $text): array
    {
        $grade = $this->extractTargetGrade($text);
        if (! $grade) {
            return [[], '¿En qué grado está ese curso?'];
        }

        $subject = $this->extractKnownSubject($text)
            ?? $this->extractSubjectFromCoursePrompt($text)
            ?? $this->extractSubject($text);
        if (! $subject) {
            return [[], '¿En qué asignatura debo inscribirlos?'];
        }

        $section = $this->extractSection($text);
        $teacherName = $this->sanitizePersonName($this->extractTeacherName($text));
        $allInGrade = (bool) preg_match(
            '/\b(?:alumnos|estudiantes)\s+de\s+(?:el\s+|la\s+)?(?:[1-6]|primer|segundo|tercer|cuarto|quinto|sexto)/iu',
            $text
        );
        $names = $allInGrade ? [] : $this->extractStudentNames($text);
        if (! $allInGrade && $names === []) {
            return [[], 'No pude detectar los alumnos para inscribir. Ejemplo: "Inscribe a Luis y Marta en Matemática de 4to grado" o "Inscribe a los alumnos de 1ro en Computación".'];
        }

        return [[
            'names' => $names,
            'all_in_grade' => $allInGrade,
            'subject_name' => $subject,
            'grade' => $grade,
            'section' => $section,
            'teacher_name' => $teacherName,
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

        // ── Analítica en tiempo real (motor seguro de solo lectura, scope colegio) ──

        // "Compara 2do con 4to", "comparame 2do grado con 4to grado de matemática"
        if (preg_match('/compara(?:r|me)?\s+(.+?)\s+con\s+(.+?)\s*[?¿.!]*$/iu', trim($text), $m)) {
            $gradeA = $this->extractTargetGrade($m[1]);
            $gradeB = $this->extractTargetGrade($m[2]);
            if ($gradeA && $gradeB) {
                return [[
                    'query_type' => 'compare_grades',
                    'grade' => $gradeA,
                    'grade_b' => $gradeB,
                    'subject_name' => $this->extractKnownSubject($text),
                ], null];
            }
        }

        // "tendencia de notas", "evolución de faltas"
        if (preg_match('/\b(?:tendencia|evoluci[oó]n)\b/iu', trim($text))) {
            return [[
                'query_type' => 'trends',
                'metric' => preg_match('/falta|asistencia|inasistencia/iu', $text) ? 'absences' : 'average',
            ], null];
        }

        // "¿Quién tiene mejor promedio?" / "¿Quiénes tienen mejor promedio en 4to?"
        if (preg_match('/qui[ée]n(?:es)?\s+(?:tiene|tienen)\s+(?:el\s+)?mejor\s+promedio/iu', trim($text))) {
            return [[
                'query_type' => 'rankings',
                'metric' => 'average',
                'grade' => $this->extractTargetGrade($text),
                'section' => $this->extractSection($text),
                'subject_name' => $this->extractKnownSubject($text),
            ], null];
        }

        // "¿Quién tiene más faltas?" / "¿Quiénes tienen más faltas en 2do?"
        if (preg_match('/qui[ée]n(?:es)?\s+(?:tiene|tienen|ha|han)\s+m[aá]s\s+faltas/iu', trim($text))) {
            return [[
                'query_type' => 'rankings',
                'metric' => 'absences',
                'grade' => $this->extractTargetGrade($text),
                'section' => $this->extractSection($text),
            ], null];
        }

        // "ranking de promedios", "ranking de faltas en 4to"
        if (preg_match('/\branking\s+de\s+(promedios?|notas?|calificaciones|faltas|asistencias?)/iu', trim($text), $m)) {
            $absences = (bool) preg_match('/faltas|asistencias?/iu', (string) $m[1]);

            return [[
                'query_type' => 'rankings',
                'metric' => $absences ? 'absences' : 'average',
                'grade' => $this->extractTargetGrade($text),
                'section' => $this->extractSection($text),
                'subject_name' => $this->extractKnownSubject($text),
            ], null];
        }

        // "top 5 alumnos", "top 3 estudiantes de 2do"
        if (preg_match('/\btop\s+(\d{1,2})\s+(?:alumnos|estudiantes)/iu', trim($text), $m)) {
            return [[
                'query_type' => 'rankings',
                'metric' => 'average',
                'limit' => (int) $m[1],
                'grade' => $this->extractTargetGrade($text),
                'section' => $this->extractSection($text),
            ], null];
        }

        // "¿Cómo van los de 4to?", "¿Cómo van los alumnos de 4to A?"
        if (preg_match('/c[oó]mo\s+van\s+(?:los\s+|las\s+)?(?:alumnos?\s+|estudiantes?\s+)?(?:de\s+|del\s+)?(.+?)\s*[?¿.!]*$/iu', trim($text), $m)) {
            $grade = $this->extractTargetGrade($m[1]);
            if ($grade) {
                $section = $this->extractSection($text);
                if (! $section && preg_match('/[1-6](?:ro|ero|do|to|er|°|º)?\s+([A-Ca-c])\b/u', (string) $m[1], $sm)) {
                    $section = strtoupper($sm[1]);
                }

                return [[
                    'query_type' => 'class_performance',
                    'grade' => $grade,
                    'section' => $section,
                    'subject_name' => $this->extractKnownSubject($text),
                ], null];
            }
        }

        // "¿Cómo están los alumnos de 4to A?" (plural + contexto). El singular
        // "cómo está 4to grado" lo sigue manejando grade_overview más abajo.
        if (preg_match('/c[oó]mo\s+est[aá]n\s+(?:los\s+|las\s+)?(?:alumnos?\s+|estudiantes?\s+)?(?:de\s+|del\s+)?(.+?)\s*[?¿.!]*$/iu', trim($text), $m)) {
            $grade = $this->extractTargetGrade($m[1]);
            if ($grade) {
                $section = $this->extractSection($text);
                if (! $section && preg_match('/[1-6](?:ro|ero|do|to|er|°|º)?\s+([A-Ca-c])\b/u', (string) $m[1], $sm)) {
                    $section = strtoupper($sm[1]);
                }

                return [[
                    'query_type' => 'class_performance',
                    'grade' => $grade,
                    'section' => $section,
                    'subject_name' => $this->extractKnownSubject($text),
                ], null];
            }
        }

        // "¿Cómo va Carlos?" (sin materia) → rendimiento general del alumno.
        // "¿Cómo va 4to?" → rendimiento del grado.
        // ("cómo va el profesor X" y "cómo va X en Y" ya se capturaron arriba).
        if (preg_match('/c[oó]mo\s+va\s+(.+?)\s*[?¿.!]*$/iu', trim($text), $m)) {
            $target = trim((string) $m[1]);
            $grade = $this->extractTargetGrade($target);
            if ($grade) {
                return [[
                    'query_type' => 'class_performance',
                    'grade' => $grade,
                    'section' => $this->extractSection($text),
                ], null];
            }

            return [[
                'query_type' => 'student_performance',
                'student_name' => $target,
            ], null];
        }

        // "¿Qué alumnos hay en 2do?" → lista filtrada por grado/sección.
        if (preg_match('/qu[eé]\s+(?:alumnos|estudiantes)\s+hay\s+en\s+(.+?)\s*[?¿.!]*$/iu', trim($text), $m)) {
            $grade = $this->extractTargetGrade($m[1]);
            if ($grade) {
                return [[
                    'query_type' => 'students_list',
                    'grade' => $grade,
                    'section' => $this->extractSection($text),
                ], null];
            }
        }

        // "¿Cuántos alumnos hay en cada sección?"
        if (preg_match('/cu[aá]ntos\s+(?:alumnos|estudiantes).*(?:cada\s+secci[oó]n|por\s+secci[oó]n|en\s+cada\s+grado)/iu', trim($text))) {
            return [[
                'query_type' => 'section_counts',
            ], null];
        }

        // "¿Quién es el más destacado?" / "mejor alumno"
        if (preg_match('/(?:m[aá]s\s+destacado|el\s+destacado|mejor\s+alumno|primer\s+lugar)/iu', trim($text))) {
            return [[
                'query_type' => 'rankings',
                'metric' => 'average',
                'limit' => 1,
                'grade' => $this->extractTargetGrade($text),
                'section' => $this->extractSection($text),
                'subject_name' => $this->extractKnownSubject($text),
            ], null];
        }

        // Consultas generales del colegio (school-wide, siempre dentro del colegio del director).
        if (preg_match('/cu[aá]ntos\s+alumnos\s+hay\s+en\s+([1-6](?:ro|ero|do|to|er|°|º)?\s*grado)/iu', trim($text), $m)) {
            return [[
                'query_type' => 'grade_overview',
                'grade' => $this->extractTargetGrade($m[1]) ?? trim($m[1]),
            ], null];
        }
        if (preg_match('/c[oó]mo est[aá]\s+([1-6](?:ro|ero|do|to|er|°|º)?\s*grado)/iu', trim($text), $m)) {
            return [[
                'query_type' => 'grade_overview',
                'grade' => $this->extractTargetGrade($m[1]) ?? trim($m[1]),
            ], null];
        }
        if (preg_match('/cu[aá]ntos\s+(?:profesor(?:a|es)?|docentes?)\s+(?:tengo|hay|tenemos|existen|registrados)/iu', trim($text))) {
            return [[
                'query_type' => 'school_stats',
                'stat' => 'teachers',
            ], null];
        }
        if (preg_match('/cu[aá]ntos\s+(?:alumnos|estudiantes)\s+(?:tengo|hay|tenemos|existen|registrados)/iu', trim($text))) {
            return [[
                'query_type' => 'school_stats',
                'stat' => 'students',
            ], null];
        }
        if (preg_match('/cu[aá]ntos\s+cursos\s+(?:tengo|hay|tenemos|existen|registrados)/iu', trim($text))) {
            return [[
                'query_type' => 'school_stats',
                'stat' => 'courses',
            ], null];
        }
        if (preg_match('/qu[eé]\s+(?:cursos|cursoss)\s+(?:existen|tengo|tenemos|hay)/iu', trim($text))) {
            return [[
                'query_type' => 'school_courses',
            ], null];
        }
        if (preg_match('/qu[eé]\s+profesores\s+(?:tengo|tenemos|hay|existen|est[aá]n asignados)/iu', trim($text))) {
            return [[
                'query_type' => 'school_teachers',
            ], null];
        }
        if (preg_match('/(?:qui[ée]n|quiénes|qui[eé]nes)\s+ha(?:n)?\s+faltado(?:\s+m[aá]s)?/iu', trim($text))) {
            return [[
                'query_type' => 'frequent_absentees',
            ], null];
        }
        if (preg_match('/qui[ée]n(?:es)?\s+est[aá]n?\s+(?:teniendo|presentando)\s+problemas\s+en\s+([A-Za-zÁÉÍÓÚáéíóúÑñ][A-Za-zÁÉÍÓÚáéíóúÑñ\s]{1,40}?)\??$/iu', trim($text), $m)) {
            return [[
                'query_type' => 'subject_at_risk',
                'subject_name' => trim($m[1]),
            ], null];
        }
        if (preg_match('/(?:alumnos|estudiantes)\s+(?:con\s+|que\s+)?(?:est[aá]n\s+|van\s+|tienen\s+)?bajo\s+rendimiento(?:\s+en\s+([A-Za-zÁÉÍÓÚáéíóúÑñ][A-Za-zÁÉÍÓÚáéíóúÑñ\s]{1,40}?))?\??$/iu', trim($text), $m)) {
            return [[
                'query_type' => 'at_risk_students',
                'subject_name' => isset($m[1]) ? trim($m[1]) : null,
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
            'school_stats' => $this->querySchoolStats($colegioId, (string) ($data['stat'] ?? 'teachers')),
            'school_courses' => $this->querySchoolCourses($colegioId),
            'school_teachers' => $this->querySchoolTeachers($colegioId),
            'grade_overview' => $this->queryGradeOverview($colegioId, (string) $data['grade']),
            'frequent_absentees' => $this->queryFrequentAbsentees($colegioId),
            'subject_at_risk' => $this->querySubjectAtRisk($colegioId, (string) $data['subject_name']),
            'at_risk_students' => $this->queryAtRiskStudents($colegioId, isset($data['subject_name']) ? (string) $data['subject_name'] : null),
            // Motor analítico seguro (solo lectura, scope colegio_id, salida Markdown).
            'class_performance' => $this->analytics->getClassPerformance(
                $colegioId,
                (string) $data['grade'],
                isset($data['section']) ? (string) $data['section'] : null,
                isset($data['subject_name']) ? (string) $data['subject_name'] : null,
            ),
            'student_performance' => $this->analytics->getStudentPerformance($colegioId, (string) $data['student_name']),
            'attendance' => $this->analytics->getAttendance(
                $colegioId,
                isset($data['grade']) ? (string) $data['grade'] : null,
                isset($data['section']) ? (string) $data['section'] : null,
                isset($data['student_name']) ? (string) $data['student_name'] : null,
                (int) ($data['days'] ?? 30),
            ),
            'rankings' => $this->analytics->getRankings(
                $colegioId,
                (string) ($data['metric'] ?? 'average'),
                isset($data['grade']) ? (string) $data['grade'] : null,
                isset($data['section']) ? (string) $data['section'] : null,
                isset($data['subject_name']) ? (string) $data['subject_name'] : null,
                (int) ($data['limit'] ?? 5),
            ),
            'trends' => $this->analytics->getTrends(
                $colegioId,
                (string) ($data['metric'] ?? 'average'),
                (int) ($data['weeks'] ?? 4),
            ),
            'compare_grades' => $this->analytics->compareGrades(
                $colegioId,
                (string) $data['grade'],
                (string) $data['grade_b'],
                isset($data['subject_name']) ? (string) $data['subject_name'] : null,
            ),
            'students_list' => $this->analytics->getStudents(
                $colegioId,
                isset($data['grade']) ? (string) $data['grade'] : null,
                isset($data['section']) ? (string) $data['section'] : null,
            ),
            'section_counts' => $this->analytics->getSectionCounts($colegioId),
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

    private function querySchoolStats(int $colegioId, string $stat): array
    {
        return match ($stat) {
            'teachers' => [
                'message' => 'Tienes '.User::where('role', 'profesor')->where('colegio_id', $colegioId)->count().' profesor(es) registrado(s).',
                'data' => ['teachers_count' => User::where('role', 'profesor')->where('colegio_id', $colegioId)->count()],
            ],
            'students' => [
                'message' => 'Hay '.Student::where('colegio_id', $colegioId)->count().' alumno(s) en la nómina del colegio.',
                'data' => ['students_count' => Student::where('colegio_id', $colegioId)->count()],
            ],
            default => [
                'message' => 'El colegio tiene '.Course::where('colegio_id', $colegioId)->count().' curso(s) registrado(s).',
                'data' => ['courses_count' => Course::where('colegio_id', $colegioId)->count()],
            ],
        };
    }

    private function querySchoolCourses(int $colegioId): array
    {
        $courses = Course::query()
            ->where('colegio_id', $colegioId)
            ->withCount('students')
            ->orderBy('grade')
            ->orderBy('subject_name')
            ->get(['id', 'subject_name', 'grade', 'section']);

        return [
            'message' => 'El colegio tiene '.$courses->count().' curso(s): '.$courses->map(fn ($c) => "{$c->subject_name} {$c->grade}".($c->section ? " sección {$c->section}" : ''))->implode(', ').'.',
            'data' => ['courses' => $courses],
        ];
    }

    private function querySchoolTeachers(int $colegioId): array
    {
        $teachers = User::query()
            ->where('colegio_id', $colegioId)
            ->where('role', 'profesor')
            ->withCount(['courses' => fn ($query) => $query->where('colegio_id', $colegioId)])
            ->orderBy('name')
            ->get(['id', 'name']);

        return [
            'message' => 'Hay '.$teachers->count().' profesor(es).'.($teachers->isNotEmpty() ? ' '.$teachers->map(fn ($t) => "{$t->name} ({$t->courses_count} curso(s))")->implode(', ').'.' : ''),
            'data' => ['teachers' => $teachers],
        ];
    }

    private function queryGradeOverview(int $colegioId, string $grade): array
    {
        $courses = Course::query()
            ->where('colegio_id', $colegioId)
            ->whereRaw('LOWER(grade) = ?', [mb_strtolower($grade)])
            ->withCount('students')
            ->orderBy('subject_name')
            ->get(['id', 'subject_name', 'grade', 'section']);

        $courseIds = $courses->pluck('id');
        $students = $courseIds->isNotEmpty()
            ? Student::query()
                ->where('students.colegio_id', $colegioId)
                ->join('course_student', 'students.id', '=', 'course_student.student_id')
                ->whereIn('course_student.course_id', $courseIds->all())
                ->distinct()
                ->count('students.id')
            : 0;

        $average = null;
        if ($courseIds->isNotEmpty()) {
            $average = Grade::query()
                ->join('activities', 'grades.activity_id', '=', 'activities.id')
                ->whereIn('activities.course_id', $courseIds->all())
                ->where('grades.colegio_id', $colegioId)
                ->avg('grades.score');
        }

        $msg = "{$grade} grado tiene {$students} alumno(s) en ".$courses->count().' curso(s).';
        if ($average !== null) {
            $msg .= ' Promedio de notas: '.number_format((float) $average, 1).'.';
        }

        return [
            'message' => $msg,
            'data' => [
                'grade' => $grade,
                'students_count' => $students,
                'courses_count' => $courses->count(),
                'average_score' => $average !== null ? round((float) $average, 1) : null,
                'courses' => $courses,
            ],
        ];
    }

    private function queryFrequentAbsentees(int $colegioId): array
    {
        $rows = Attendance::query()
            ->select('students.name')
            ->selectRaw('count(*) as total')
            ->join('students', 'attendances.student_id', '=', 'students.id')
            ->where('attendances.colegio_id', $colegioId)
            ->where('attendances.status', Attendance::STATUS_ABSENT)
            ->groupBy('students.id', 'students.name')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        if ($rows->isEmpty()) {
            return [
                'message' => 'No hay faltas de asistencia registradas.',
                'data' => ['absentees' => []],
            ];
        }

        $labels = $rows->map(fn ($row) => "{$row->name} ({$row->total})")->implode(', ');

        return [
            'message' => 'Alumnos con más faltas: '.$labels.'.',
            'data' => ['absentees' => $rows],
        ];
    }

    private function querySubjectAtRisk(int $colegioId, string $subjectName): array
    {
        $subjectKey = mb_strtolower(trim($subjectName));
        $courseIds = Course::query()
            ->where('colegio_id', $colegioId)
            ->whereRaw('LOWER(subject_name) like ?', ['%'.$subjectKey.'%'])
            ->pluck('id');

        if ($courseIds->isEmpty()) {
            return [
                'message' => "No encontré cursos de {$subjectName} en tu colegio.",
                'data' => ['students' => []],
            ];
        }

        $rows = Grade::query()
            ->select('students.name')
            ->join('students', 'grades.student_id', '=', 'students.id')
            ->join('activities', 'grades.activity_id', '=', 'activities.id')
            ->whereIn('activities.course_id', $courseIds->all())
            ->where('grades.colegio_id', $colegioId)
            ->whereNotNull('grades.score')
            ->selectRaw('avg(grades.score) as promedio, count(grades.id) as cantidad')
            ->groupBy('students.id', 'students.name')
            ->orderBy('promedio')
            ->limit(8)
            ->get();

        if ($rows->isEmpty()) {
            return [
                'message' => "No tengo calificaciones registradas para calcular rendimiento en {$subjectName}.",
                'data' => ['students' => []],
            ];
        }

        $labels = $rows->map(fn ($row) => "{$row->name} (prom. {$row->promedio})")->implode(', ');

        return [
            'message' => "Alumnos con menor rendimiento en {$subjectName}: ".$labels.'.',
            'data' => ['students' => $rows],
        ];
    }

    private function queryAtRiskStudents(int $colegioId, ?string $subjectName): array
    {
        if ($subjectName !== null && trim($subjectName) !== '') {
            return $this->querySubjectAtRisk($colegioId, $subjectName);
        }

        $rows = Grade::query()
            ->select('students.name', 'courses.subject_name', 'courses.grade')
            ->join('students', 'grades.student_id', '=', 'students.id')
            ->join('activities', 'grades.activity_id', '=', 'activities.id')
            ->join('courses', 'activities.course_id', '=', 'courses.id')
            ->where('grades.colegio_id', $colegioId)
            ->whereNotNull('grades.score')
            ->selectRaw('avg(grades.score) as promedio')
            ->groupBy('students.id', 'students.name', 'courses.subject_name', 'courses.grade')
            ->orderBy('promedio')
            ->limit(8)
            ->get();

        if ($rows->isEmpty()) {
            return [
                'message' => 'No tengo calificaciones suficientes para identificar bajo rendimiento.',
                'data' => ['students' => []],
            ];
        }

        $labels = $rows->map(fn ($row) => "{$row->name} en {$row->subject_name} {$row->grade} (prom. {$row->promedio})")->implode(', ');

        return [
            'message' => 'Alumnos con menor rendimiento registrado: '.$labels.'.',
            'data' => ['students' => $rows],
        ];
    }

    private function extractTeacherName(string $text): ?string
    {
        $patterns = [
            '/con\s+(?:el\s+|la\s+)?profesor(?:a)?\s+(.+)$/iu',
            '/profesor(?:a)?\s+de\s+[A-Za-zÁÉÍÓÚáéíóúÑñ]+\s+llamad[oa]\s+([A-Za-zÁÉÍÓÚáéíóúÑñ][A-Za-zÁÉÍÓÚáéíóúÑñ\s]{1,80}?)(?:\s+(?:y|para|con|tambien|también)|,|\.|$)/iu',
            '/profesor(?:a)?\s+(?:llamad[oa]\s+)?([A-Za-zÁÉÍÓÚáéíóúÑñ]+(?:\s+[A-Za-zÁÉÍÓÚáéíóúÑñ]+){0,3}?)\s+(?:tambien|también|que\s+te|ademas|además|y\s+crea)/iu',
            '/llamad[oa]\s+([A-Za-zÁÉÍÓÚáéíóúÑñ][A-Za-zÁÉÍÓÚáéíóúÑñ\s]{1,80}?)(?:\s+(?:y|para|con|de\s+[1-6]|tambien|también)|,|\.|$)/iu',
            '/nombre(?:\s+es)?\s+([A-Za-zÁÉÍÓÚáéíóúÑñ][A-Za-zÁÉÍÓÚáéíóúÑñ\s]{1,80}?)(?:\s+(?:y|para|tambien|también)|,|\.|$)/iu',
            '/profesor(?:a)?\s+de\s+[A-Za-zÁÉÍÓÚáéíóúÑñ]+\s+(?:llamad[oa]\s+)?(.+?)(?:\s+(?:y\s+|para\s+|con\s+|tambien|también)|,|\.|$)/iu',
            '/profesor(?:a)?\s+(.+?)(?:\s+(?:donde|al\s+que|con\s+la|con\s+el|y\s+quiero|y\s+as[ií]gna|y\s+agrega|y\s+crea|que\s+crea|para\s+as[ií]gna|tambien|también|dara|dará)|,|\.|$)/iu',
            '/(?:as[ií]gna(?:le)?|agrega(?:le)?|asignar)\s+(?:los\s+cursos\s+|las\s+materias\s+)?(?:a\s+)?([A-Za-zÁÉÍÓÚáéíóúÑñ][A-Za-zÁÉÍÓÚáéíóúÑñ\s]{1,80})$/iu',
            '/^(.+?)\s+(?:dara|dará|asigna)/iu',
            '/(?:as[ií]gna(?:le)?|agrega(?:le)?|asignar|dara|dará)\s+(?:a\s+|al\s+)?([A-Za-zÁÉÍÓÚáéíóúÑñ][A-Za-zÁÉÍÓÚáéíóúÑñ\s]{1,80}?)(?:\s+(?:la\s+materia|el\s+curso|la\s+asignatura|robotica|rob[oó]tica|matematic|ingl[eé]s|lenguaje|ciencias|historia|fisica|quimica|biologia|geografia|de\s+[1-6]|para\s+el|del\s+curso)|,|\.|$)/iu',
            '/^(?:a\s+|al\s+)([A-Za-zÁÉÍÓÚáéíóúÑñ][A-Za-zÁÉÍÓÚáéíóúÑñ\s]{1,80}?)(?:\s+(?:as[ií]gna(?:le)?|dara|dará|agrega(?:le)?))/iu',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $m)) {
                $name = $this->sanitizePersonName((string) $m[1]);
                if ($name) {
                    return $name;
                }
            }
        }

        return $this->sanitizePersonName($this->extractNamedPersonAfterRole($text, 'profesor'));
    }

    /**
     * Extrae uno o varios nombres de profesor de un mismo mensaje,
     * separando conectores tipo "y al profesor", "y profesor", ", al profesor".
     *
     * @return array<int,string>
     */
    private function extractTeacherNames(string $text): array
    {
        $pattern = '/(?:a\s+)?(?:al\s+|a la\s+|el\s+|la\s+)?(?:profesor(?:a)?|docente|maestr[oa])\s+'
            .'([A-Za-zÁÉÍÓÚáéíóúÑñ][A-Za-zÁÉÍÓÚáéíóúÑñ\s\'-]{0,120}?)'
            .'(?=\s+(?:(?:y\s+|,\s*)?(?:a\s+)?(?:al\s+|a la\s+|el\s+|la\s+)?(?:profesor(?:a)?|docente|maestr[oa])'
            .'|de\s+[1-6](?:ro|er|do|to|°|º)?\s*(?:grado)?'
            .'|(?:de|para|en|del)\s+(?:el\s+|la\s+)?(?:curso|grado|materia|asignatura|seccion|sección))\b|$)/iu';

        if (! preg_match_all($pattern, $text, $matches)) {
            return [];
        }

        $names = [];
        foreach ($matches[1] as $raw) {
            $name = $this->sanitizePersonName($raw);
            if ($name !== null && ! $this->isGenericPersonLabel($name)) {
                $names[] = $name;
            }
        }

        return array_values(array_unique($names));
    }

    private function extractNamedPersonAfterRole(string $text, string $role): ?string
    {
        $rolePattern = $role === 'profesor'
            ? 'profesor(?:a)?'
            : ($role === 'alumno' ? 'alumnos?' : 'estudiantes?');

        if (! preg_match('/(?:al?\s+)?'.$rolePattern.'\s+(.+)$/iu', $text, $m)) {
            return null;
        }

        $name = trim((string) $m[1]);
        $name = trim(preg_replace('/[.!?]+$/', '', $name) ?? $name);
        $name = trim(preg_replace('/^(?:al|a la|el|la|los|las)\s+/iu', '', $name) ?? $name);
        $name = preg_replace(
            '/\s+(?:en el|en la|al curso|de primer|de 1|en 1|a\s+[1-6]|a\s+(?:primer|segundo|tercer|cuarto|quinto|sexto)|y as[ií]gna|y inscr[ií]be|y matr[ií]cula|y crea|tambien|también|que te|y\s+(?:a\s+)?(?:al|el|la)\s+(?:profesor(?:a)?|docente|maestr[oa])).*$/iu',
            '',
            $name
        ) ?? $name;

        return $this->sanitizePersonName($name);
    }

    private function isGenericPersonLabel(string $value): bool
    {
        $normalized = $this->normalizedText($value);

        return (bool) preg_match('/^(?:los|las|todos|todas|que hay|existentes?|registrados?|del colegio|de la escuela)(?:\s|$)/', $normalized)
            || (bool) preg_match('/\b(?:que hay|existentes?|registrados?)\b/', $normalized);
    }

    /**
     * @param  array<int,array{intent:string,data:array}>  $actions
     * @return array<int,array{intent:string,data:array}>
     */
    private function enrichActionsFromText(array $actions, string $text): array
    {
        $knownSubject = $this->extractKnownSubject($text);
        $subject = $knownSubject
            ?? $this->extractSubject($text)
            ?? $this->extractSubjectFromCoursePrompt($text)
            ?? $this->extractSubjectFromDeletePrompt($text);
        if ($subject && ! $this->isValidCourseSubject((string) $subject)) {
            $subject = $knownSubject;
        }
        $grades = $this->extractGrades($text);
        $teacher = $this->sanitizePersonName($this->extractTeacherName($text));

        foreach ($actions as &$action) {
            $intent = (string) ($action['intent'] ?? '');
            $data = (array) ($action['data'] ?? []);
            if (! empty($data['teacher_name'])) {
                $data['teacher_name'] = $this->sanitizePersonName((string) $data['teacher_name']) ?? $data['teacher_name'];
            }
            if (in_array($intent, ['create_teacher', 'create_course', 'assign_teacher'], true)) {
                if (empty($data['subject_name']) && $subject) {
                    $data['subject_name'] = $subject;
                }
                if (empty($data['grades']) && $grades !== []) {
                    $data['grades'] = $grades;
                }
                if (empty($data['teacher_name']) && $teacher) {
                    $data['teacher_name'] = $teacher;
                }
            }
            if (in_array($intent, ['create_students_batch', 'enroll_students_course', 'unenroll_students_course', 'delete_student'], true)) {
                if (empty($data['grade']) && ($grades[0] ?? null) && $intent !== 'delete_student') {
                    $data['grade'] = $grades[0];
                }
                if (empty($data['subject_name']) && $subject && $intent !== 'delete_student') {
                    $data['subject_name'] = $subject;
                }
                $parsedNames = $intent === 'delete_student' ? [] : $this->extractStudentNames($text);
                $existingNames = collect($data['names'] ?? [])
                    ->map(fn ($name) => $this->sanitizePersonName((string) $name))
                    ->filter()
                    ->unique()
                    ->values();
                if ($parsedNames !== [] && (count($parsedNames) > $existingNames->count() || $existingNames->isEmpty())) {
                    $data['names'] = $parsedNames;
                } elseif ($existingNames->isNotEmpty()) {
                    $data['names'] = $existingNames->all();
                }
                if (! empty($data['student_name'])) {
                    $data['student_name'] = $this->sanitizePersonName((string) $data['student_name']) ?? $data['student_name'];
                }
            }
            $action['data'] = $data;
        }
        unset($action);

        return $actions;
    }

    private function sanitizePersonName(?string $name): ?string
    {
        $name = app(PersonNameSanitizer::class)->cleanTeacher($name);
        if ($name === null) {
            return null;
        }

        $name = preg_replace(
            '/\s+(?:y\s+)?(?:agrega(?:lo|le|s)?|as[ií]gna(?:lo|le)?|crea(?:r)?(?:s|me|les)?|quiero|donde|con\s+(?:los|las|el|la)|cursos?|materias?|asignaturas?).*$/iu',
            '',
            $name
        ) ?? $name;
        $name = preg_replace(
            '/\s+(?:y\s+|,\s*)?(?:a\s+)?(?:al\s+|a la\s+|el\s+|la\s+)?(?:profesor(?:a)?|docente|maestr[oa])\b.*$/iu',
            '',
            $name
        ) ?? $name;
        $name = trim($name, " \t\n\r\0\x0B,.");
        if ($name === '' || $this->isGenericPersonLabel($name) || mb_strlen($name) > 80) {
            return null;
        }

        return app(PersonNameSanitizer::class)->titleCase($name);
    }

    private function extractKnownSubject(?string $text): ?string
    {
        if ($text === null || trim($text) === '') {
            return null;
        }

        $normalized = $this->normalizedText($text);
        $aliases = [
            'ingles' => 'Inglés',
            'matematica' => 'Matemática',
            'matematicas' => 'Matemática',
            'lenguaje' => 'Lenguaje',
            'lengua' => 'Lengua',
            'ciencias' => 'Ciencias',
            'historia' => 'Historia',
            'geografia' => 'Geografía',
            'fisica' => 'Física',
            'quimica' => 'Química',
            'biologia' => 'Biología',
            'educacion fisica' => 'Educación Física',
            'robotica' => 'Robótica',
            'computacion' => 'Computación',
        ];
        foreach ($aliases as $alias => $canonical) {
            if (preg_match('/\b'.preg_quote($alias, '/').'\b/u', $normalized)) {
                return $canonical;
            }
        }

        return null;
    }

    private function utteranceMentionsCourses(string $text): bool
    {
        $value = $this->normalizedText($text);

        return (bool) preg_match('/\b(?:curso|materia|asignatura|grado|ingles|matematic)\b/', $value)
            || $this->extractGrades($text) !== [];
    }

    private function extractSubjectFromDeletePrompt(string $text): ?string
    {
        if (preg_match('/(?:curso|asignatura|materia)s?\s+(?:de\s+|del\s+)?([A-Za-zÁÉÍÓÚáéíóúÑñ][A-Za-zÁÉÍÓÚáéíóúÑñ\s]{1,60})$/iu', $text, $m)) {
            $subject = trim((string) $m[1]);
            $subject = trim(preg_replace('/\s+(?:de\s+)?[1-6](?:ro|er|do|to|°|º|ero)?\s*(?:grado)?.*$/iu', '', $subject) ?? $subject);
            if ($this->isValidCourseSubject($subject)) {
                return $this->titleCaseSubject($subject);
            }
        }

        return null;
    }

    private function hasDeleteVerb(string $value): bool
    {
        return (bool) preg_match('/\b(?:elimina(?:r)?|borra(?:r)?|quita(?:r)?|remover|remueve|limpia(?:r)?|cancel(?:a|ar|es)?)\b/', $value);
    }

    private function isMassPeopleTarget(string $value): bool
    {
        return (bool) preg_match('/\b(?:todos?|todas?|los|las)\s+(?:los\s+|las\s+)?(?:profesores|profesoras|docentes|alumnos|estudiantes)\b/', $value)
            || (bool) preg_match('/\b(?:profesores|profesoras|docentes|alumnos|estudiantes)\s+(?:que hay|existentes?|registrados?)\b/', $value);
    }

    private function isMassCourseTarget(string $value): bool
    {
        if (preg_match('/\b(?:curso|asignatura|materia)\s+(?:de|del)\b/', $value)) {
            return false;
        }

        return (bool) preg_match('/\b(?:todos?|todas?|los|las)\s+(?:los\s+|las\s+)?(?:cursos|asignaturas|materias)\b/', $value)
            || (bool) preg_match('/\b(?:cursos|asignaturas|materias)\s+(?:que hay|existentes?|registrados?)\b/', $value);
    }

    private function extractSubjectFromCoursePrompt(string $text): ?string
    {
        if (preg_match('/\b(?:primer|primero|segundo|tercer|tercero|cuarto|quinto|sexto|[1-6](?:ro|ero|do|to|er)?)\s*grado\s+de\s+([A-Za-zÁÉÍÓÚáéíóúÑñ][A-Za-zÁÉÍÓÚáéíóúÑñ\s]{1,40}?)(?:\s+(?:con|para|y|[:\-]|\.|$)|$)/iu', $text, $m)) {
            $subject = trim((string) $m[1]);
            $known = $this->extractKnownSubject($subject) ?? $this->titleCaseSubject($subject);
            if ($this->isValidCourseSubject($known)) {
                return $known;
            }
        }

        // "cursos de inglés" / "materia de ingles" en cualquier parte del pedido.
        if (preg_match('/(?:cursos?|materias?|asignaturas?)\s+de\s+([A-Za-zÁÉÍÓÚáéíóúÑñ][A-Za-zÁÉÍÓÚáéíóúÑñ]{2,40})/iu', $text, $m)) {
            $subject = trim((string) $m[1]);
            $known = $this->extractKnownSubject($subject) ?? $this->titleCaseSubject($subject);
            if ($this->isValidCourseSubject($known)) {
                return $known;
            }
        }

        // Forma 1: "los cursos de: 1ero, 2do...6to grado de INGLES" (lista de grados antes de la materia)
        if (preg_match('/(?:curso|cursos|cursso|asignatura|materia)s?\s+de\s*:?\s*([0-9].*?)\s+grado\s+de\s+([A-Za-zÁÉÍÓÚáéíóúÑñ][A-Za-zÁÉÍÓÚáéíóúÑñ\s]*?)(?:\s+(?:para|en|y|,|con)|\s*$)/iu', $text, $m)) {
            $subject = trim((string) $m[2]);
            if ($this->isValidCourseSubject($subject)) {
                return $this->titleCaseSubject($subject);
            }
        }

        // Forma 2: "Crea Matemática para 4.º, 5.º y 6.º." o "Crea curso de Matemática para 4to" (verbo crear al inicio y materia antes de "para")
        if (preg_match('/^(?:cre(?:a|ar|arles|es|e|o)|creame)\s+(?:el\s+)?(?:curso\s+de\s+|cursos?\s+|cursso\s+|asignatura\s+|materia\s+)?([A-Za-zÁÉÍÓÚáéíóúÑñ][A-Za-zÁÉÍÓÚáéíóúÑñ\s]{2,60}?)\s+(?:para|en|de|del)\s+[1-6](?:ro|er|do|to|°|º|ero)?\s*(?:grado)?\b/iu', $text, $m)) {
            $subject = trim((string) $m[1]);
            if ($this->isValidCourseSubject($subject)) {
                return $this->titleCaseSubject($subject);
            }
        }

        // Localizamos "curso/asignatura de" en el texto ORIGINAL para conservar
        // acentos y manejar variantes tipográficas ("cursso") sin desfases de índice.
        if (! preg_match('/(?:curso|cursso|asignatura)\s+de\s+(.+?)$/iu', $text, $m)) {
            return null;
        }
        $rest = trim((string) $m[1]);

        // Si el grado aparece antes de la materia ("1er grado de matematicas"),
        // lo descartamos: "1er grado de" -> "".
        $rest = preg_replace('/^(?:al\s+)?(?:primer|primero|segundo|tercer|tercero|cuarto|quinto|sexto|[1-6](?:ro|ero|do|to|er|º|°)?)\s*grado\s+(?:de\s+)?/iu', '', $rest) ?? $rest;

        // Cortamos en conectores/palabras reservadas y delimitadores de lista (:, -) (conserva acentos del original).
        $rest = preg_split('/\s*(?:[:\-]|\s+(?:para|en|del|de|al|a\s+la|con|seccion|sección|y|nivel)\s+)/iu', $rest)[0] ?? $rest;
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

    private function isValidCourseSubject(string $subject): bool
    {
        $subject = trim($subject);
        if (mb_strlen($subject) < 2 || mb_strlen($subject) > 60) {
            return false;
        }

        return ! preg_match('/\b(?:grado|curso|asignatura|profesor|profesora|alumno|estudiante|docente|materia|cursso)\b/iu', $subject)
            && ! preg_match('/(ingles de|\bde\s+[1-6])$/iu', $subject);
    }

    private function titleCaseSubject(string $subject): string
    {
        return mb_convert_case(trim($subject), MB_CASE_TITLE, 'UTF-8');
    }

    private function extractSubject(string $text): ?string
    {
        $patterns = [
            '/(?:profesor(?:a)?|docente)\s+de\s+([A-Za-zÁÉÍÓÚáéíóúÑñ][A-Za-zÁÉÍÓÚáéíóúÑñ]{2,40})(?:\s+llamad|\s+y\s+|,|\.|$)/iu',
            '/(?:as[ií]gna(?:le)?|dara|dará)\s+([A-Za-zÁÉÍÓÚáéíóúÑñ\s]{3,50})\s+(?:de|del|para|en)\s+/u',
            '/(?:as[ií]gna(?:le)?|dara|dará)\s+([A-Za-zÁÉÍÓÚáéíóúÑñ\s]{3,50})(?:,|\.|$)/u',
            '/(?:materia|asignatura)\s+de\s+([A-Za-zÁÉÍÓÚáéíóúÑñ][A-Za-zÁÉÍÓÚáéíóúÑñ\s]{1,50}?)(?:\s+(?:con|para|de|en|a)\s+|,|\.|$)/iu',
            '/(?:agrega(?:le)?|asigna(?:le)?)\s+(?:la\s+materia\s+|las\s+materias\s+)?de?\s*([A-Za-zÁÉÍÓÚáéíóúÑñ][A-Za-zÁÉÍÓÚáéíóúÑñ\s]{1,50}?)(?:\s+(?:de|para|en|a)\s+|,|\.|$)/iu',
            '/(?:cursos?|materias?|asignaturas?)\s+de\s+([A-Za-zÁÉÍÓÚáéíóúÑñ][A-Za-zÁÉÍÓÚáéíóúÑñ]{2,40})/iu',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $m)) {
                $raw = trim($m[1]);

                return $this->extractKnownSubject($raw) ?? trim($raw);
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
        $value = strtr($value, [
            'primer grado' => '1ro grado',
            'primero' => '1ro',
            'segundo' => '2do',
            'tercero' => '3ro',
            'tercer grado' => '3ro grado',
            'cuarto' => '4to',
            'quinto' => '5to',
            'sexto' => '6to',
            '1ero' => '1ro',
            '3ero' => '3ro',
        ]);

        if (preg_match('/([1-6])(?:ro|ero|er|°|º|do|to)?\s*\.{2,}\s*([1-6])(?:to|do|ro|ero|er|°|º)?/u', $value, $m)) {
            $from = (int) $m[1];
            $to = (int) $m[2];
            if ($from <= $to) {
                return collect(range($from, $to))->map(fn ($n) => $this->formatGrade((int) $n))->all();
            }
        }

        if (preg_match('/de\s+([1-6])(?:ro|ero|er|°|º|do|to)?\s*(?:grado)?\s+a(?:l)?\s+([1-6])(?:to|do|ro|ero|er|°|º)?/u', $value, $m)) {
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
        $value = strtr($value, [
            'primer grado' => '1ro grado',
            'primero' => '1ro',
            'segundo grado' => '2do grado',
            'segundo' => '2do',
            'tercer grado' => '3ro grado',
            'tercero' => '3ro',
            'cuarto grado' => '4to grado',
            'cuarto' => '4to',
            'quinto grado' => '5to grado',
            'quinto' => '5to',
            'sexto grado' => '6to grado',
            'sexto' => '6to',
            '1ero' => '1ro',
            '3ero' => '3ro',
        ]);

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
        // Lista después de "alumnos/estudiantes ... :" aunque haya contexto de grado/sección/materia.
        if (preg_match('/(?:alumnos?|estudiantes?)\b[^:]{0,180}[:\-]\s*(.+)$/ius', $text, $m)) {
            $names = $this->splitAndSanitizeNames(trim((string) $m[1]));
            if ($names !== []) {
                return $names;
            }
        }

        // Último ":" del mensaje si lo que sigue parece una lista (comas o "y").
        if (preg_match('/:\s*([A-Za-zÁÉÍÓÚáéíóúÑñ][^:]{2,})$/u', $text, $m)) {
            $chunk = trim((string) $m[1]);
            if (preg_match('/,|\s+y\s+/u', $chunk)) {
                $names = $this->splitAndSanitizeNames($chunk);
                if (count($names) >= 2) {
                    return $names;
                }
            }
        }

        $verb = '(?:agrega|agregar|matricula|matricular|inscribe|inscribir|crea|crear|crees|cree|creo|creame)';

        if (preg_match('/'.$verb.'\s+(?:a\s+|al\s+)?(?:los\s+|las\s+)?(?:siguientes\s+)?(?:alumnos?|estudiantes?)\b[^:]*[:\-]\s*(.+)$/iu', $text, $m)) {
            $names = $this->splitAndSanitizeNames(trim((string) $m[1]));
            if ($names !== []) {
                return $names;
            }
        }

        if (preg_match('/'.$verb.'\s+(?:a\s+|al\s+)?(?:los\s+|las\s+)?(?:siguientes\s+)?(?:alumnos?|estudiantes?)\s+(.+?)(?:\s+(?:para(?:\s+(?:el|la|[1-6]))?|al\s+(?:curso|grado)|en\s+(?:el|la|la\s+secci)|a\s+la|del\s+(?:curso|grado)))\b/iu', $text, $m)) {
            $names = $this->splitAndSanitizeNames(trim((string) $m[1]));
            if ($names !== []) {
                return $names;
            }
        }

        $single = $this->sanitizePersonName($this->extractNamedPersonAfterRole($text, 'alumno'))
            ?? $this->sanitizePersonName($this->extractNamedPersonAfterRole($text, 'estudiante'));
        if ($single) {
            return [$single];
        }

        if (preg_match('/'.$verb.'\s+(?:a|al)\s+(?:alumno|estudiante)\s+(.+?)\s+(?:y\s+)?(?:al|a la|en|asigna|inscribe|matricula)\s+/iu', $text, $m)) {
            $single = $this->sanitizePersonName(trim($m[1]));
            if ($single) {
                return [$single];
            }
        }

        if (! preg_match('/'.$verb.'\s+a?\s+(.+?)\s+(?:al|a la|en)\s+/iu', $text, $m)) {
            return [];
        }

        return $this->splitAndSanitizeNames(trim((string) $m[1]));
    }

    /**
     * @return array<int,string>
     */
    private function splitAndSanitizeNames(string $raw): array
    {
        $raw = preg_replace('/^(?:alumno|estudiante|a\s+|al\s+|el\s+|la\s+|los\s+|las\s+|siguientes\s+|para\s+(?:el|la)\s+)\s*/iu', '', $raw) ?? $raw;
        $raw = preg_replace('/^(?:para\s+(?:el|la)\s+(?:curso|grado|materia|asignatura)\s+(?:de\s+)?)\s*/iu', '', $raw) ?? $raw;
        $raw = preg_replace('/\s+y\s+/iu', ',', $raw) ?? $raw;

        return collect(explode(',', $raw))
            ->map(fn ($name) => $this->sanitizePersonName(trim($name)))
            ->filter(fn ($name) => $name !== null && mb_strlen($name) >= 2)
            ->unique()
            ->values()
            ->all();
    }

    private function extractSection(string $text): ?string
    {
        if (preg_match('/secci[oó]n\s+([A-Za-z0-9]+)/iu', $text, $m)) {
            $raw = mb_strtolower(trim((string) $m[1]));
            if (preg_match('/^(?:de|del|el|la|los|las|grado|curso)$/u', $raw)) {
                return null;
            }

            return strtoupper(trim((string) $m[1]));
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
        $match = app(PersonNameMatcher::class)->resolveTeacher($colegioId, $teacherName);

        if ($match->isAmbiguous() || $match->isNone()) {
            throw ValidationException::withMessages([
                'teacher' => $match->message ?? 'No encontré al profesor indicado en este colegio.',
            ]);
        }

        /** @var User $teacher */
        $teacher = $match->model;

        return $teacher;
    }

    private function resolveStudentForQuery(int $colegioId, string $studentName): Student
    {
        $match = app(PersonNameMatcher::class)->resolveStudent($colegioId, $studentName);

        if ($match->isAmbiguous() || $match->isNone()) {
            throw ValidationException::withMessages([
                'student' => $match->message ?? 'No encontré al alumno indicado en este colegio.',
            ]);
        }

        /** @var Student $student */
        $student = $match->model;

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
            'unenroll_students_course',
            'unassign_teacher',
            'update_course',
            'update_student',
            'delete_teacher',
            'delete_teacher_invite',
            'delete_all_teachers',
            'delete_course',
            'delete_all_courses',
            'delete_student',
        ], true);
    }

    /**
     * Reconoce respuestas cortas de confirmación ("sí", "sí, créalos", "confirmo")
     * para completar la acción pendiente guardada en sesión.
     */
    private function isAffirmativeText(string $text): bool
    {
        $value = $this->affirmativeNormalized($text);
        if ($value === '') {
            return false;
        }

        $short = ['si', 'ok', 'okay', 'sip', 'dale', 'adelante', 'confirmo', 'confirmado', 'confirmar', 'proceder', 'procede', 'listo', 'hazlo', 'crealos', 'crealo', 'yes', 'yep', 'claro', 'correcto', 'afirmativo', 'exacto', 'de acuerdo', 'deacuerdo', 'por supuesto', 'claro que si', 'siguiente', 'eso es'];
        if (in_array($value, $short, true)) {
            return true;
        }

        if (preg_match('/^si(?:\s+(crealos|crealos igualmente|crearlos|confirmo|confirmado|dale|adelante|hazlo|por favor|proceder|procede))?$/u', $value)) {
            return true;
        }

        return false;
    }

    private function affirmativeNormalized(string $text): string
    {
        $value = mb_strtolower(trim($text));
        // Remover comillas, apóstrofes, signos de exclamación y puntuación accidental.
        $value = preg_replace('/[\'\"`´’‘¡!¿?*.,;:\-]/u', ' ', $value) ?? $value;
        // Normalizar acentos para que "sí" sea equivalente a "si".
        $value = strtr($value, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
        ]);

        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    private function isNegativeText(string $text): bool
    {
        $value = $this->normalizedText($text);
        $value = trim(preg_replace('/[.!?]+$/', '', $value) ?? $value);

        return (bool) preg_match('/^(?:no|cancelar?|cancela|olvidalo|dejalo|detente|mejor no)$/', $value);
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

        // Frases introductorias que no aportan a la intención.
        $value = preg_replace('/^(?:yo\s+)?quiero\s+que\s*/u', '', $value) ?? $value;
        $value = preg_replace('/^(?:quiero|necesito|deber[íi]as)\s+/u', '', $value) ?? $value;
        $value = preg_replace('/\bpor favor\b/u', '', $value) ?? $value;

        // Variantes tipográficas comunes que deben interpretarse igual.
        $value = preg_replace('/\bcurs+o\b/', 'curso', $value) ?? $value;
        $value = preg_replace('/\b1(?:er|ero)?\s*grado\b/', '1er grado', $value) ?? $value;

        return $value;
    }
}

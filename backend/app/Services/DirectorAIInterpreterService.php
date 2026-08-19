<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Student;
use App\Models\TeacherInvite;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DirectorAIInterpreterService
{
    /**
     * @param  array<int,array{role:string,content:string}>  $conversation
     * @return array{actions:array<int,array{intent:string,data:array}>,message:?string,clarification:?string}|null
     */
    public function interpret(User $director, string $text, array $conversation, array $memory): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        $messages = [[
            'role' => 'system',
            'content' => $this->systemPrompt($director, $memory),
        ]];

        foreach (array_slice($conversation, -16) as $turn) {
            if (! is_array($turn)) {
                continue;
            }
            $role = $turn['role'] ?? '';
            $content = trim((string) ($turn['content'] ?? ''));
            if (in_array($role, ['user', 'assistant'], true) && $content !== '') {
                $messages[] = ['role' => $role, 'content' => mb_substr($content, 0, 4000)];
            }
        }

        if ($messages === [] || ($messages[array_key_last($messages)]['role'] ?? '') !== 'user') {
            $messages[] = ['role' => 'user', 'content' => $text];
        }

        try {
            $response = Http::timeout(45)
                ->withToken((string) config('services.openai.key'))
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => (string) config('services.openai.director_model', 'gpt-4o-mini'),
                    'temperature' => 0.1,
                    'tool_choice' => 'auto',
                    'parallel_tool_calls' => true,
                    'tools' => $this->toolDefinitions(),
                    'messages' => $messages,
                ]);

            if ($response->failed()) {
                Log::warning('Director AI interpreter unavailable', [
                    'director_id' => $director->id,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $message = (array) $response->json('choices.0.message', []);
            $actions = [];
            foreach ((array) ($message['tool_calls'] ?? []) as $call) {
                $name = (string) data_get($call, 'function.name', '');
                $arguments = data_get($call, 'function.arguments', []);
                if (is_string($arguments)) {
                    $arguments = json_decode($arguments, true);
                }
                if (! is_array($arguments) || ! in_array($name, $this->allowedIntents(), true)) {
                    continue;
                }
                $actions[] = [
                    'intent' => $name,
                    'data' => $this->normalizeArguments($name, $arguments),
                ];
            }

            $content = trim((string) ($message['content'] ?? ''));
            if ($actions === []) {
                return [
                    'actions' => [],
                    'message' => $content !== '' ? $content : null,
                    'clarification' => $content !== '' ? $content : null,
                ];
            }

            return [
                'actions' => $this->coalesceActions($actions),
                'message' => $content !== '' ? $content : null,
                'clarification' => null,
            ];
        } catch (\Throwable $e) {
            Log::warning('Director AI interpreter failed; using local fallback', [
                'director_id' => $director->id,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function enabled(): bool
    {
        if (! config('services.openai.director_enabled', true)) {
            return false;
        }
        if (app()->environment('testing') && ! config('services.openai.director_test_enabled', false)) {
            return false;
        }

        $key = trim((string) config('services.openai.key'));

        return $key !== '' && ! str_contains($key, 'your_openai');
    }

    private function systemPrompt(User $director, array $memory): string
    {
        $colegioId = (int) $director->colegio_id;
        $teachers = User::query()
            ->where('colegio_id', $colegioId)
            ->where('role', 'profesor')
            ->orderBy('name')
            ->limit(60)
            ->pluck('name')
            ->all();
        $invites = TeacherInvite::query()
            ->where('colegio_id', $colegioId)
            ->whereNull('claimed_at')
            ->whereNull('revoked_at')
            ->orderBy('name')
            ->limit(60)
            ->get(['name', 'invite_code'])
            ->map(fn ($invite) => $invite->name.' ('.$invite->invite_code.')')
            ->all();
        $courses = Course::query()
            ->where('colegio_id', $colegioId)
            ->orderBy('subject_name')
            ->orderBy('grade')
            ->limit(100)
            ->get(['subject_name', 'grade', 'section'])
            ->map(fn ($course) => trim($course->subject_name.' '.$course->grade.' '.($course->section ?? '')))
            ->all();
        $students = Student::query()
            ->where('colegio_id', $colegioId)
            ->orderBy('name')
            ->limit(100)
            ->get(['name', 'grade', 'section'])
            ->map(fn ($student) => trim($student->name.' '.$student->grade.' '.($student->section ?? '')))
            ->all();

        $school = json_encode(compact('teachers', 'invites', 'courses', 'students'), JSON_UNESCAPED_UNICODE);
        $memoryJson = json_encode($memory, JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
Eres el asistente del director de un colegio. Hablas español natural, como Gemini o ChatGPT: entiendes
pedidos largos, errores, "créalo", "agrégale" y referencias al turno anterior. Laravel autoriza y ejecuta;
tú debes llamar herramientas siempre que el usuario pida crear, asignar, matricular, actualizar, eliminar o consultar.

Reglas:
1. Si el pedido es operativo, OBLIGATORIO llamar herramientas. No describas el plan en texto sin tools.
2. Si piden crear un profesor Y además cursos/materia/grados, usa UNA sola create_teacher con teacher_name, subject_name y grades. No dejes subject_name vacío.
3. "Crea al profesor X, crea los cursos de inglés de 1ero a 6to y agrégaselos" = create_teacher(X, Inglés, [1ro..6to]).
4. Un profesor no registrado puede ser una invitación pendiente; asígnale por nombre.
5. Convierte primer/1ero a 1ro, segundo a 2do, tercero/3ero a 3ro, cuarto a 4to, quinto a 5to, sexto a 6to.
6. teacher_name es SOLO el nombre de la persona (ej. "Yovanny Andrade"), nunca "y agrégalo a esos cursos".
7. Usa la memoria para "créalo", "agrégale", "esos cursos", "ese profesor".
8. Si falta un dato indispensable, pregunta breve y no llames tools. Si el pedido ya trae materia y grados, no preguntes.
9. Consultas → query_academic. Eliminar → la herramienta delete_*. Habla con el director si solo saluda o pide ayuda.

Memoria conversacional: {$memoryJson}
Datos visibles del colegio: {$school}
PROMPT;
    }

    /**
     * @return array<int,array{type:string,function:array}>
     */
    private function toolDefinitions(): array
    {
        $defs = [
            'create_teacher' => [
                'description' => 'Crear/invitar profesor y opcionalmente asignarle una materia en varios grados.',
                'properties' => [
                    'teacher_name' => ['type' => 'string'],
                    'subject_name' => ['type' => ['string', 'null']],
                    'grades' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'section' => ['type' => ['string', 'null']],
                ],
                'required' => ['teacher_name'],
            ],
            'create_course' => [
                'description' => 'Crear uno o varios cursos/materias, opcionalmente asignados a un profesor o invitación.',
                'properties' => [
                    'subject_name' => ['type' => 'string'],
                    'grades' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'section' => ['type' => ['string', 'null']],
                    'teacher_name' => ['type' => ['string', 'null']],
                ],
                'required' => ['subject_name', 'grades'],
            ],
            'assign_teacher' => [
                'description' => 'Asignar una materia y grados a un profesor registrado o invitación pendiente.',
                'properties' => [
                    'teacher_name' => ['type' => 'string'],
                    'subject_name' => ['type' => 'string'],
                    'grades' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'section' => ['type' => ['string', 'null']],
                ],
                'required' => ['teacher_name', 'subject_name', 'grades'],
            ],
            'create_students_batch' => [
                'description' => 'Crear uno o varios alumnos en un grado y sección.',
                'properties' => [
                    'names' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'grade' => ['type' => 'string'],
                    'section' => ['type' => ['string', 'null']],
                ],
                'required' => ['names', 'grade'],
            ],
            'enroll_students_course' => [
                'description' => 'Matricular alumnos existentes en un curso.',
                'properties' => [
                    'names' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'subject_name' => ['type' => 'string'],
                    'grade' => ['type' => 'string'],
                    'section' => ['type' => ['string', 'null']],
                ],
                'required' => ['names', 'subject_name', 'grade'],
            ],
            'unenroll_students_course' => [
                'description' => 'Desmatricular alumnos de un curso sin eliminarlos del colegio.',
                'properties' => [
                    'names' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'subject_name' => ['type' => 'string'],
                    'grade' => ['type' => 'string'],
                    'section' => ['type' => ['string', 'null']],
                ],
                'required' => ['names', 'subject_name', 'grade'],
            ],
            'unassign_teacher' => [
                'description' => 'Desasignar cursos de un profesor o invitación sin eliminar el profesor ni los cursos.',
                'properties' => [
                    'teacher_name' => ['type' => 'string'],
                    'subject_name' => ['type' => ['string', 'null']],
                    'grades' => ['type' => 'array', 'items' => ['type' => 'string']],
                ],
                'required' => ['teacher_name'],
            ],
            'update_course' => [
                'description' => 'Modificar nombre de materia, grado o sección de un curso existente.',
                'properties' => [
                    'subject_name' => ['type' => 'string'],
                    'grade' => ['type' => 'string'],
                    'section' => ['type' => ['string', 'null']],
                    'new_subject_name' => ['type' => ['string', 'null']],
                    'new_grade' => ['type' => ['string', 'null']],
                    'new_section' => ['type' => ['string', 'null']],
                ],
                'required' => ['subject_name', 'grade'],
            ],
            'update_student' => [
                'description' => 'Modificar nombre, grado o sección de un alumno; también sirve para moverlo de grado.',
                'properties' => [
                    'student_name' => ['type' => 'string'],
                    'new_name' => ['type' => ['string', 'null']],
                    'new_grade' => ['type' => ['string', 'null']],
                    'new_section' => ['type' => ['string', 'null']],
                ],
                'required' => ['student_name'],
            ],
            'delete_teacher' => [
                'description' => 'Eliminar un profesor específico.',
                'properties' => ['teacher_name' => ['type' => 'string']],
                'required' => ['teacher_name'],
            ],
            'delete_all_teachers' => [
                'description' => 'Eliminar todos los profesores del colegio.',
                'properties' => [],
                'required' => [],
            ],
            'delete_course' => [
                'description' => 'Eliminar cursos de una materia, opcionalmente grado y sección.',
                'properties' => [
                    'subject_name' => ['type' => 'string'],
                    'grade' => ['type' => ['string', 'null']],
                    'section' => ['type' => ['string', 'null']],
                ],
                'required' => ['subject_name'],
            ],
            'delete_all_courses' => [
                'description' => 'Eliminar todos los cursos del colegio.',
                'properties' => [],
                'required' => [],
            ],
            'delete_student' => [
                'description' => 'Eliminar un alumno específico de la nómina.',
                'properties' => ['student_name' => ['type' => 'string']],
                'required' => ['student_name'],
            ],
            'query_academic' => [
                'description' => 'Consultar profesores, alumnos, cursos, notas, faltas o rendimiento.',
                'properties' => [
                    'query_type' => [
                        'type' => 'string',
                        'enum' => [
                            'teacher_overview', 'teacher_courses', 'teacher_students_grade',
                            'student_subject_overview', 'student_absences', 'student_evaluations',
                            'school_stats', 'school_courses', 'school_teachers', 'grade_overview',
                            'frequent_absentees', 'subject_at_risk', 'at_risk_students',
                        ],
                    ],
                    'teacher_name' => ['type' => ['string', 'null']],
                    'student_name' => ['type' => ['string', 'null']],
                    'subject_name' => ['type' => ['string', 'null']],
                    'grade' => ['type' => ['string', 'null']],
                    'stat' => ['type' => ['string', 'null'], 'enum' => ['teachers', 'students', 'courses', null]],
                ],
                'required' => ['query_type'],
            ],
        ];

        return collect($defs)->map(function ($definition, $name) {
            return [
                'type' => 'function',
                'function' => [
                    'name' => $name,
                    'description' => $definition['description'],
                    'strict' => false,
                    'parameters' => [
                        'type' => 'object',
                        'properties' => $definition['properties'],
                        'required' => $definition['required'],
                        'additionalProperties' => false,
                    ],
                ],
            ];
        })->values()->all();
    }

    private function allowedIntents(): array
    {
        return [
            'create_teacher', 'create_course', 'assign_teacher',
            'create_students_batch', 'enroll_students_course', 'unenroll_students_course',
            'unassign_teacher', 'update_course', 'update_student',
            'delete_teacher', 'delete_all_teachers', 'delete_course',
            'delete_all_courses', 'delete_student', 'query_academic',
        ];
    }

    private function normalizeArguments(string $intent, array $arguments): array
    {
        if (isset($arguments['grades']) && is_array($arguments['grades'])) {
            $arguments['grades'] = collect($arguments['grades'])
                ->map(fn ($grade) => $this->normalizeGrade((string) $grade))
                ->filter()
                ->unique()
                ->values()
                ->all();
        }
        if (isset($arguments['grade']) && is_string($arguments['grade'])) {
            $arguments['grade'] = $this->normalizeGrade($arguments['grade']);
        }
        if (isset($arguments['new_grade']) && is_string($arguments['new_grade'])) {
            $arguments['new_grade'] = $this->normalizeGrade($arguments['new_grade']);
        }
        if ($intent === 'create_teacher') {
            $arguments['expires_in_days'] = 30;
        }

        return $arguments;
    }

    private function normalizeGrade(string $grade): string
    {
        $value = mb_strtolower(trim($grade));
        $value = strtr($value, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
        ]);
        foreach ([
            1 => ['primer', 'primero', '1', '1ro', '1ero'],
            2 => ['segundo', '2', '2do'],
            3 => ['tercer', 'tercero', '3', '3ro', '3ero'],
            4 => ['cuarto', '4', '4to'],
            5 => ['quinto', '5', '5to'],
            6 => ['sexto', '6', '6to'],
        ] as $number => $aliases) {
            foreach ($aliases as $alias) {
                if (str_contains($value, $alias)) {
                    return match ($number) {
                        1 => '1ro', 2 => '2do', 3 => '3ro',
                        4 => '4to', 5 => '5to', 6 => '6to',
                    };
                }
            }
        }

        return trim($grade);
    }

    /**
     * Collapse redundant create_course/assign_teacher calls into create_teacher.
     *
     * @param  array<int,array{intent:string,data:array}>  $actions
     * @return array<int,array{intent:string,data:array}>
     */
    private function coalesceActions(array $actions): array
    {
        $teacherIndexes = [];
        foreach ($actions as $index => $action) {
            if (($action['intent'] ?? '') === 'create_teacher') {
                $teacherIndexes[] = $index;
            }
        }
        if (count($teacherIndexes) !== 1) {
            return array_values($actions);
        }

        $index = $teacherIndexes[0];
        $teacherName = mb_strtolower(trim((string) ($actions[$index]['data']['teacher_name'] ?? '')));
        foreach ($actions as $otherIndex => $other) {
            if ($otherIndex === $index || ! in_array($other['intent'] ?? '', ['create_course', 'assign_teacher'], true)) {
                continue;
            }
            $assignee = mb_strtolower(trim((string) ($other['data']['teacher_name'] ?? '')));
            if ($assignee !== '' && $teacherName !== '' && $assignee !== $teacherName) {
                continue;
            }
            if (empty($actions[$index]['data']['subject_name'])) {
                $actions[$index]['data']['subject_name'] = $other['data']['subject_name'] ?? null;
            }
            $actions[$index]['data']['grades'] = array_values(array_unique(array_filter(array_merge(
                (array) ($actions[$index]['data']['grades'] ?? []),
                (array) ($other['data']['grades'] ?? []),
            ))));
            if (empty($actions[$index]['data']['section']) && ! empty($other['data']['section'])) {
                $actions[$index]['data']['section'] = $other['data']['section'];
            }
            unset($actions[$otherIndex]);
        }

        return array_values($actions);
    }

    /**
     * @param  array<int,array{success?:bool,message?:string}>  $results
     */
    public function narrate(string $userText, array $results, bool $pendingConfirmation = false): string
    {
        $fallback = $this->composeReply($results, $pendingConfirmation);
        if ($pendingConfirmation || ! $this->enabled()) {
            return $fallback;
        }

        try {
            $response = Http::timeout(20)
                ->withToken((string) config('services.openai.key'))
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => (string) config('services.openai.director_model', 'gpt-4o-mini'),
                    'temperature' => 0.35,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Eres el asistente del director de un colegio. Responde en español, breve y natural, como Gemini. Confirma solo lo que ya ocurrió. No inventes códigos, nombres ni cantidades que no estén en el resultado. Si algo falló, dilo claro y ofrece el siguiente paso.',
                        ],
                        [
                            'role' => 'user',
                            'content' => json_encode([
                                'pedido' => $userText,
                                'resultado' => $results,
                            ], JSON_UNESCAPED_UNICODE),
                        ],
                    ],
                ]);
            $content = trim((string) $response->json('choices.0.message.content', ''));

            return $content !== '' ? $content : $fallback;
        } catch (\Throwable $e) {
            Log::debug('Director AI narrate fallback', ['message' => $e->getMessage()]);

            return $fallback;
        }
    }

    /**
     * @param  array<int,array{success?:bool,message?:string}>  $results
     */
    public function composeReply(array $results, bool $pendingConfirmation = false): string
    {
        $messages = collect($results)->pluck('message')->filter()->map(fn ($msg) => trim((string) $msg))->values();
        if ($pendingConfirmation) {
            return $messages->count() === 1
                ? (string) $messages->first()
                : "Preparé {$messages->count()} acciones:\n- ".$messages->implode("\n- ");
        }

        $ok = collect($results)->filter(fn ($row) => ($row['success'] ?? true) !== false)->pluck('message')->filter();
        $fail = collect($results)->filter(fn ($row) => ($row['success'] ?? true) === false)->pluck('message')->filter();
        if ($ok->isEmpty()) {
            return $fail->isNotEmpty()
                ? 'No pude completar eso. '.$fail->implode(' ')
                : 'No pude completar la operación.';
        }

        $text = $ok->implode(' ');
        if ($fail->isNotEmpty()) {
            $text .= ' Quedó pendiente: '.$fail->implode(' ');
        }

        return $text;
    }
}

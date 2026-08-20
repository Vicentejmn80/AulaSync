<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DirectorAIInterpreterService
{
    public function __construct(
        private SchoolRosterContextService $rosterContext,
    ) {}

    /**
     * @param  array<int,array{role:string,content:string}>  $conversation
     * @return array{actions:array<int,array{intent:string,data:array}>,message:?string,clarification:?string}|null
     */
    public function interpret(User $director, string $text, array $conversation, array $memory): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        $director->loadMissing('colegio');

        $messages = [[
            'role' => 'system',
            'content' => $this->systemPrompt($director, $memory),
        ]];

        foreach (array_slice($conversation, -32) as $turn) {
            if (! is_array($turn)) {
                continue;
            }
            $role = $turn['role'] ?? '';
            $content = trim((string) ($turn['content'] ?? $turn['text'] ?? ''));
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
                    'temperature' => 0.7,
                    'top_p' => 0.9,
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
        $roster = $this->rosterContext->markdownForDirector($director);
        $memoryJson = json_encode($memory, JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
Eres Nova, el asistente inteligente, amigable y cercano de School Planner AI / AulaSync. Acompañas al director
como un colega de confianza: cálido, claro y proactivo, al estilo Gemini. Hablas español natural. Usas el historial
de la conversación para no repetir preguntas ni perder el hilo.

REGLA DEL SÁNDWICH (obligatoria en CADA respuesta al director, también al confirmar o al narrar un resultado):
a) Apertura amigable y cálida, con 1 emoji sutil (🏫 📚 ✨ 😊). Sin exagerar.
b) El dato o la acción, exacto y directo. Si preguntan "cuántos alumnos y cuántos profesores", responde AMBOS números
   usando el resumen de abajo. Si piden un código DOC- o NV-, búscalo en las listas y dilo.
c) Cierre proactivo: ofrece el siguiente paso útil (crear, editar, matricular, consultar, invitar, eliminar).

PROHIBIDO (tono robótico): "no se obtuvo información", "consulte con el área correspondiente", "no tengo permisos",
"no pude encontrar datos", "error de consulta". Si algo no está en las listas, responde con empatía
("todavía no veo a esa persona en el colegio") y ofrece crearla o invitarla en el acto.

Consultas de roster (códigos DOC-/NV-, conteos del resumen de abajo, quién es, qué cursos tiene): responde SOLO con texto, sin tools.
Mutaciones (crear, asignar, matricular, actualizar, eliminar): OBLIGATORIO llamar herramientas. Laravel ejecuta.
Notas, promedios, faltas, rankings, comparaciones y tendencias: NUNCA las inventes ni las leas del roster; usa query_academic.

ANALÍTICA EN TIEMPO REAL (OBLIGATORIO usar query_academic):
- "¿Cómo van los de 4to A?", "¿Quién tiene mejor promedio?", "¿Quién tiene más faltas?", "Compara 2do con 4to",
  "tendencia de notas", "¿cómo va Carlos?" (sin materia) → llama query_academic con el query_type adecuado
  (class_performance, student_performance, rankings, trends, compare_grades, attendance).
- Los datos de notas, promedios y faltas NO están en el roster de abajo: la única forma de saberlos es query_academic.
- El resultado viene en Markdown con tablas y rankings (1º, 2º, 3º). Preséntalo tal cual, con el sándwich.
- CERO ALUCINACIONES: si el resultado no menciona un alumno, grado, curso o nota, NO lo inventes ni lo des por sentado.
  Di con claridad qué no encontraste ("todavía no veo datos de 4to A") y ofrece el siguiente paso (crear el curso,
  cargar notas, revisar la asistencia).

Reglas operativas:
1. MULTI-INTENT: si el mensaje trae VARIAS órdenes, llama TODAS las tools en paralelo. Nunca te quedes solo con la primera.
   Ejemplo: crear profesor + asignarle materia/grados + crear alumno en un curso = create_teacher + create_students_batch + enroll_students_course.
2. Crear profesor + sus cursos/materia/grados (sin otro actor) = UNA create_teacher con teacher_name, subject_name y grades.
3. primer/1ero→1ro, segundo→2do, tercero/3ero→3ro, cuarto→4to, quinto→5to, sexto→6to.
4. NOMBRES PROPIOS ESTRICTOS: teacher_name, student_name y names son SOLO el nombre de la persona
   (ej. "Mariano", "Mariano García", "Laureano Márquez"). PROHIBIDO incluir muletillas o conectores:
   "que te dije", "el que te mencioné", "también", "además", "llamado", "de la materia", "profesor",
   "alumno", "crea a", "también crea a", "en el curso", "y agrégalo".
   "crea al profesor mariano tambien que te dije" → teacher_name = "Mariano".
5. LISTAS DE ALUMNOS: si hay dos puntos o una lista con comas/"y", names es el ARRAY completo
   (["Carlos Duarte","Fermin Lopez","Enrique Quesada"]). NUNCA uses como nombre "en la sección",
   "para el", "siguientes alumnos", "curso", "grado" ni la materia.
   "quiero que crees a los siguientes alumnos en la seccion de 2do grado de computacion: carlos duarte, fermin lopez, enrique quesada"
   → create_students_batch names=["Carlos Duarte","Fermin Lopez","Enrique Quesada"] grade=2do subject_name=Computación.
6. "Crea al alumno X" → create_students_batch. Nunca create_course por mencionar "curso".
7. "Crea al alumno X y asígnalo al curso de Y grado Z" → create_students_batch + enroll_students_course.
8. Usa la memoria y el historial para "créalo", "agrégale", "esos cursos". Laravel arma el resumen de confirmación.

Memoria conversacional: {$memoryJson}

Datos del colegio (tiempo real):
{$roster}
PROMPT;
    }

    /**
     * @return array<int,array{type:string,function:array}>
     */
    private function toolDefinitions(): array
    {
        $defs = [
            'create_teacher' => [
                'description' => 'Crear/invitar profesor y opcionalmente asignarle una materia en varios grados. teacher_name SOLO el nombre propio (sin "también", "que te dije", "llamado" ni la materia).',
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
                'description' => 'Crear uno o varios alumnos. names es un ARRAY de nombres propios completos (ej. ["Carlos Duarte","Fermin Lopez"]). Nunca "en la sección", "para el", "siguientes" ni la materia.',
                'properties' => [
                    'names' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'grade' => ['type' => 'string'],
                    'section' => ['type' => ['string', 'null']],
                    'subject_name' => ['type' => ['string', 'null']],
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
                'description' => 'Eliminar un profesor registrado específico. No usar para cancelar invitaciones; para eso usa delete_teacher_invite.',
                'properties' => ['teacher_name' => ['type' => 'string']],
                'required' => ['teacher_name'],
            ],
            'delete_teacher_invite' => [
                'description' => 'Cancelar/revocar una invitación DOC- pendiente de un profesor. NO elimina el profesor registrado. teacher_name SOLO el nombre propio.',
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
                'description' => 'Consultar profesores, alumnos, cursos, notas, faltas o rendimiento. Para analítica en tiempo real (rendimiento por grado/sección, ranking de promedios o faltas, comparar grados, tendencias) usa los query_type class_performance, student_performance, attendance, rankings, trends o compare_grades.',
                'properties' => [
                    'query_type' => [
                        'type' => 'string',
                        'enum' => [
                            'teacher_overview', 'teacher_courses', 'teacher_students_grade',
                            'student_subject_overview', 'student_absences', 'student_evaluations',
                            'school_stats', 'school_courses', 'school_teachers', 'grade_overview',
                            'frequent_absentees', 'subject_at_risk', 'at_risk_students',
                            'class_performance', 'student_performance', 'attendance',
                            'rankings', 'trends', 'compare_grades', 'students_list',
                        ],
                    ],
                    'teacher_name' => ['type' => ['string', 'null']],
                    'student_name' => ['type' => ['string', 'null']],
                    'subject_name' => ['type' => ['string', 'null']],
                    'grade' => ['type' => ['string', 'null']],
                    'grade_b' => ['type' => ['string', 'null']],
                    'section' => ['type' => ['string', 'null']],
                    'metric' => ['type' => ['string', 'null'], 'enum' => ['average', 'absences', null]],
                    'limit' => ['type' => ['integer', 'null']],
                    'days' => ['type' => ['integer', 'null']],
                    'weeks' => ['type' => ['integer', 'null']],
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
            'delete_teacher', 'delete_teacher_invite', 'delete_all_teachers', 'delete_course',
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
        if (isset($arguments['grade_b']) && is_string($arguments['grade_b'])) {
            $arguments['grade_b'] = $this->normalizeGrade($arguments['grade_b']);
        }
        if (isset($arguments['new_grade']) && is_string($arguments['new_grade'])) {
            $arguments['new_grade'] = $this->normalizeGrade($arguments['new_grade']);
        }
        if ($intent === 'create_teacher') {
            $arguments['expires_in_days'] = 30;
        }

        $sanitizer = app(PersonNameSanitizer::class);
        foreach (['teacher_name', 'student_name'] as $key) {
            if (! empty($arguments[$key]) && is_string($arguments[$key])) {
                $arguments[$key] = $sanitizer->displayName($arguments[$key]) ?: $arguments[$key];
            }
        }
        if (isset($arguments['names']) && is_array($arguments['names'])) {
            $arguments['names'] = collect($arguments['names'])
                ->map(function ($name) use ($sanitizer) {
                    $clean = $sanitizer->clean((string) $name);

                    return $clean ? $sanitizer->titleCase($clean) : null;
                })
                ->filter()
                ->values()
                ->all();
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
                    'temperature' => 0.7,
                    'top_p' => 0.9,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Eres Nova, asistente cercano del director. Aplica la regla del sándwich: emoji cálido + dato exacto (solo lo que está en el resultado, sin inventar códigos ni cantidades) + oferta de siguiente paso. Prohibido tono robótico.',
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
            $clean = $messages
                ->map(fn ($msg) => trim((string) preg_replace('/\s*Responde ["\']sí["\'] para confirmar\.?$/iu', '', $msg)))
                ->filter()
                ->values();
            if ($clean->count() <= 1) {
                $body = (string) $clean->first();

                return "✨ {$body}\nResponde 'sí' para confirmar.";
            }

            $lines = $clean->map(function ($msg, $index) {
                $msg = trim((string) preg_replace('/^Voy a\s+/iu', '', $msg));

                return ($index + 1).'. '.$msg;
            });

            return "✨ Voy a realizar las siguientes acciones:\n".$lines->implode("\n")."\nResponde 'sí' para confirmar.";
        }

        $ok = collect($results)->filter(fn ($row) => ($row['success'] ?? true) !== false)->pluck('message')->filter();
        $fail = collect($results)->filter(fn ($row) => ($row['success'] ?? true) === false)->pluck('message')->filter();
        if ($ok->isEmpty()) {
            $detail = $fail->isNotEmpty() ? $fail->implode(' ') : 'algo no cuadró en este paso';

            return "😊 No pude completar eso todavía: {$detail} Si quieres, lo reintentamos o lo creamos juntos.";
        }

        $text = '🏫 '.$ok->implode(' ');
        if ($fail->isNotEmpty()) {
            $text .= ' Quedó pendiente: '.$fail->implode(' ');
        }

        return $text.' ¿Seguimos con otra consulta, una matrícula o un docente nuevo?';
    }
}

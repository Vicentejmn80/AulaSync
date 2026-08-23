<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Agente de datos del director: elige herramientas backend, las ejecuta
 * con el colegio_id autenticado y redacta la respuesta solo con esos resultados.
 *
 * El LLM no inventa datos ni ejecuta SQL. colegio_id del request se descarta.
 */
class DirectorDataAgentService
{
    public const SESSION_KEY = 'director_data_agent_session';

    public const TOOLS = [
        'get_students',
        'get_student',
        'get_courses',
        'get_teachers',
        'get_grades',
        'get_attendance',
        'get_evaluations',
        'get_assignments',
        'get_student_performance',
        'get_course_performance',
        'compare_courses',
        'get_at_risk_students',
        'get_declining_students',
        'get_academic_trends',
        'generate_school_report',
        'get_rankings',
        'get_section_counts',
        'query_academic',
    ];

    public function __construct(
        private DirectorAnalyticsQueryService $analytics,
        private ProductTelemetry $telemetry,
    ) {}

    public static function isDataTool(string $name): bool
    {
        return in_array($name, self::TOOLS, true);
    }

    /**
     * @param  array<int,array{intent?:string,data?:array}>  $actions
     */
    public function areExclusiveDataActions(array $actions): bool
    {
        if ($actions === []) {
            return false;
        }

        return collect($actions)->every(fn ($action) => self::isDataTool((string) ($action['intent'] ?? '')));
    }

    public function looksLikeMutation(string $text): bool
    {
        $value = $this->normalized($text);

        return (bool) preg_match(
            '/\b(?:crea(?:r|me|lo|los)?|invita|elimina|borra|quitar|remover|matricula|inscribe|asigna(?:le|lo)?|desmatricula|actualiza|edita|mueve|cambia)\b/u',
            $value
        ) && ! preg_match('/\b(?:informe|resumen|compara|tendencia|asistencia|rendimiento|promedio)\b/u', $value);
    }

    public function looksLikeDataQuery(string $text): bool
    {
        if ($this->looksLikeMutation($text)) {
            return false;
        }

        $value = $this->normalized($text);

        return (bool) preg_match(
            '/(?:como va|como van|como le va|como esta|como estan|quien|quienes|cuantos|cuantas|que alumnos|que cursos|que profesores|que evaluaciones|que tareas|compara|tendencia|tendencias|evolucion|ranking|informe|resumen|resume|estado academico|asistencia|faltas|promedio|rendimiento|preocup|problemas|atencion|destacado|bajo rendimiento|este mes|mi curso|mi colegio|dame|le va a|evaluaciones|tareas|diagnostico|investiga|empeor|impresion|por que|que tienen en comun|preocupante|en que materia|quien es su profesor|prepara(?:me)?)/u',
            $value
        ) || (bool) preg_match('/\btop\s+\d/u', $value)
        || $this->looksLikeFollowUp($value);
    }

    public function looksLikeFollowUp(string $text): bool
    {
        $value = $this->normalized($text);

        return (bool) preg_match(
            '/^(?:por que|y (?:eso|ahora|el|la)|cual(?: es)?|en que materia|quien es su profesor|que tienen en comun|el mas preocupante|explica|profundiza|y su profesor)\b/u',
            $value
        );
    }

    public function isOutOfScope(string $text): bool
    {
        $value = $this->normalized($text);

        return (bool) preg_match(
            '/\b(?:clima|receta|chiste|futbol|bitcoin|traduce|codigo python|pelicula)\b/u',
            $value
        );
    }

    /**
     * @param  array<string,mixed>  $screenContext
     * @param  array<int,array{intent:string,data:array}>|null  $preplanned
     * @return array{tools:array<int,array{tool:string,args:array}>,intent:string,clarification:?string,wants_opinion:bool}
     */
    public function plan(string $text, array $screenContext = [], ?array $preplanned = null, array $memory = []): array
    {
        $context = $this->sanitizeContext($screenContext);
        $wantsOpinion = $this->wantsOpinion($text);

        if (is_array($preplanned) && $preplanned !== []) {
            $tools = [];
            foreach ($preplanned as $action) {
                $name = (string) ($action['intent'] ?? '');
                if (! self::isDataTool($name)) {
                    continue;
                }
                $tools[] = [
                    'tool' => $name,
                    'args' => $this->sanitizeArgs((array) ($action['data'] ?? [])),
                ];
            }
            if ($tools !== []) {
                return [
                    'tools' => $this->applyContext($tools, $text, $context),
                    'intent' => $tools[0]['tool'],
                    'clarification' => null,
                    'wants_opinion' => $wantsOpinion,
                ];
            }
        }

        if ($this->isOutOfScope($text)) {
            return [
                'tools' => [],
                'intent' => 'out_of_scope',
                'clarification' => 'Puedo ayudarte con datos reales de tu colegio: estudiantes, cursos, notas, asistencia, evaluaciones y tendencias. ¿Sobre qué curso o indicador quieres consultar?',
                'wants_opinion' => false,
            ];
        }

        $planned = $this->planFromText($text, $context, $memory);
        $planned['tools'] = $this->applyContext($planned['tools'], $text, $context);
        $planned['wants_opinion'] = $wantsOpinion || in_array($planned['intent'] ?? '', ['diagnose_school', 'school_concerns', 'executive_report', 'investigate'], true);

        return $planned;
    }

    /**
     * Plan local listo: no hace falta llamar al LLM para elegir tools.
     * Las consultas poco claras se dejan al intérprete como orquestador.
     *
     * @param  array{tools?:array,clarification?:?string,intent?:string}  $plan
     */
    public function localPlanIsReady(array $plan): bool
    {
        if (($plan['intent'] ?? '') === 'unclear') {
            return false;
        }

        return ($plan['tools'] ?? []) !== [] || filled($plan['clarification'] ?? null);
    }

    /**
     * @param  array{tools:array<int,array{tool:string,args:array}>,intent:string,clarification:?string,wants_opinion:bool}  $plan
     * @param  callable(array):array|null  $legacyQuery
     * @return array{success:bool,message:string,actions:array<int,array>,intent:string,tools:array<int,string>,needs_clarification:bool}
     */
    public function answer(User $director, string $text, array $plan, ?callable $legacyQuery = null): array
    {
        $started = hrtime(true);
        $sessionId = $this->sessionId();

        // ── Cost control por colegio (100 consultas/día, 30/min ya via throttle) ──
        $costKey = 'director_data_cost:'.(int) $director->colegio_id.':'.now()->format('Y-m-d');
        $dailyCount = (int) Cache::get($costKey, 0);
        if ($dailyCount >= 100) {
            $this->record($director, $plan['intent'] ?? 'rate_limited', [], 'failed', $started, $sessionId, 'daily_limit_exceeded', $text);
            return [
                'success' => false,
                'needs_clarification' => false,
                'message' => 'Has alcanzado el límite diario de consultas de datos (100/día por colegio). Intenta mañana o contacta a soporte.',
                'actions' => [],
                'intent' => 'rate_limited',
                'tools' => [],
                'duration_ms' => $this->elapsedMs($started),
            ];
        }
        Cache::put($costKey, $dailyCount + 1, now()->endOfDay());

        if (($plan['intent'] ?? '') === 'explain_from_memory') {
            $composed = $this->composeFollowUp($text, (array) ($plan['focus'] ?? []));
            $this->record($director, 'explain_from_memory', [], 'success', $started, $sessionId, null, $text);

            return [
                'success' => true,
                'needs_clarification' => false,
                'message' => $composed,
                'actions' => [],
                'intent' => 'explain_from_memory',
                'tools' => [],
                'duration_ms' => $this->elapsedMs($started),
                'focus' => $plan['focus'] ?? [],
            ];
        }

        if (($plan['clarification'] ?? null) && ($plan['tools'] ?? []) === []) {
            $this->record($director, $plan['intent'] ?? 'clarify', [], 'unresolved', $started, $sessionId, 'needs_clarification', $text);

            return [
                'success' => false,
                'needs_clarification' => true,
                'message' => (string) $plan['clarification'],
                'actions' => [],
                'intent' => (string) ($plan['intent'] ?? 'clarify'),
                'tools' => [],
                'duration_ms' => $this->elapsedMs($started),
            ];
        }

        $actions = [];
        $used = [];
        $knownStudent = null;
        $ranAtRisk = false;
        foreach ($plan['tools'] as $call) {
            $tool = (string) $call['tool'];
            $args = $this->sanitizeArgs((array) ($call['args'] ?? []));
            if ($tool === 'get_declining_students' && $ranAtRisk) {
                continue;
            }
            if ($tool === 'get_attendance' && empty($args['student_name']) && is_string($knownStudent) && $knownStudent !== '') {
                $args['student_name'] = $knownStudent;
            }
            $used[] = $tool;
            try {
                $result = $this->execute($director, $tool, $args, $legacyQuery);
                $actions[] = [
                    'success' => true,
                    'action_type' => $tool,
                    'message' => $result['message'] ?? 'Consulta completada.',
                    'data' => $result['data'] ?? [],
                ];
                if ($tool === 'get_student_performance' && is_string($result['data']['student'] ?? null)) {
                    $knownStudent = (string) $result['data']['student'];
                }
                if ($tool === 'get_at_risk_students') {
                    $ranAtRisk = true;
                }
            } catch (\Throwable $e) {
                Log::error('Director data agent tool failed', [
                    'director_id' => $director->id,
                    'colegio_id' => $director->colegio_id,
                    'tool' => $tool,
                    'error' => $e->getMessage(),
                ]);
                $this->record($director, $plan['intent'] ?? $tool, $used, 'failed', $started, $sessionId, $this->errorCode($e), $text);

                return [
                    'success' => true,
                    'needs_clarification' => false,
                    'message' => $this->friendlyFailure($e),
                    'actions' => [[
                        'success' => true,
                        'action_type' => $tool,
                        'message' => 'No hay datos suficientes para responder con certeza.',
                        'data' => [],
                    ]],
                    'intent' => (string) ($plan['intent'] ?? $tool),
                    'tools' => $used,
                    'duration_ms' => $this->elapsedMs($started),
                ];
            }
        }

        $intent = (string) ($plan['intent'] ?? ($used[0] ?? 'query'));
        $composed = $this->compose($text, $actions, (bool) ($plan['wants_opinion'] ?? false), $intent);
        $this->record($director, $intent, $used, 'success', $started, $sessionId, null, $text);

        return [
            'success' => true,
            'needs_clarification' => false,
            'message' => $composed,
            'actions' => $actions,
            'intent' => $intent,
            'tools' => $used,
            'duration_ms' => $this->elapsedMs($started),
            'focus' => $this->extractFocus($actions, $intent, $text, (array) ($plan['focus'] ?? [])),
            'report_ready' => $intent === 'executive_report' || $this->wantsExecutiveReport($text),
        ];
    }

    /**
     * @param  callable(array):array|null  $legacyQuery
     * @return array{message:string,data:array}
     */
    public function execute(User $director, string $tool, array $args, ?callable $legacyQuery = null): array
    {
        $colegioId = (int) $director->colegio_id;
        if ($colegioId <= 0) {
            return [
                'message' => 'Tu usuario de director no está vinculado a un colegio, así que no puedo consultar datos institucionales.',
                'data' => [],
            ];
        }

        $args = $this->sanitizeArgs($args);

        if ($tool === 'query_academic') {
            if (! is_callable($legacyQuery)) {
                return [
                    'message' => 'No pude resolver esa consulta académica.',
                    'data' => [],
                ];
            }

            return $legacyQuery($args);
        }

        return match ($tool) {
            'get_students' => $this->analytics->getStudents(
                $colegioId,
                $this->str($args, 'grade'),
                $this->str($args, 'section'),
            ),
            'get_student' => $this->analytics->getStudent($colegioId, (string) ($args['student_name'] ?? '')),
            'get_courses' => $this->analytics->getCourses(
                $colegioId,
                $this->str($args, 'grade'),
                $this->str($args, 'section'),
                $this->str($args, 'subject_name') ?? $this->str($args, 'subject'),
            ),
            'get_teachers' => $this->analytics->getTeachers($colegioId),
            'get_grades' => $this->analytics->getGrades(
                $colegioId,
                $this->str($args, 'grade'),
                $this->str($args, 'section'),
                $this->str($args, 'student_name'),
                $this->str($args, 'subject_name') ?? $this->str($args, 'subject'),
            ),
            'get_attendance' => $this->analytics->getAttendance(
                $colegioId,
                $this->str($args, 'grade'),
                $this->str($args, 'section'),
                $this->str($args, 'student_name'),
                (int) ($args['days'] ?? 30),
            ),
            'get_evaluations' => $this->analytics->getEvaluations(
                $colegioId,
                $this->str($args, 'grade'),
                $this->str($args, 'section'),
                $this->str($args, 'subject_name') ?? $this->str($args, 'subject'),
            ),
            'get_assignments' => $this->analytics->getAssignments(
                $colegioId,
                $this->str($args, 'grade'),
                $this->str($args, 'section'),
                $this->str($args, 'subject_name') ?? $this->str($args, 'subject'),
                ! empty($args['pending_only']),
            ),
            'get_student_performance' => $this->analytics->getStudentPerformance(
                $colegioId,
                (string) ($args['student_name'] ?? ''),
                $this->str($args, 'subject_name') ?? $this->str($args, 'subject'),
            ),
            'get_course_performance' => $this->analytics->getCoursePerformance(
                $colegioId,
                (string) ($args['grade'] ?? ''),
                $this->str($args, 'section'),
                $this->str($args, 'subject_name') ?? $this->str($args, 'subject'),
            ),
            'compare_courses' => $this->analytics->compareCourses(
                $colegioId,
                (string) ($args['grade'] ?? $args['grade_a'] ?? ''),
                (string) ($args['grade_b'] ?? ''),
                $this->str($args, 'section') ?? $this->str($args, 'section_a'),
                $this->str($args, 'section_b'),
                $this->str($args, 'subject_name') ?? $this->str($args, 'subject'),
            ),
            'get_at_risk_students' => $this->analytics->getAtRiskStudents(
                $colegioId,
                $this->str($args, 'grade'),
                $this->str($args, 'section'),
                $this->str($args, 'subject_name') ?? $this->str($args, 'subject'),
                isset($args['threshold']) ? (float) $args['threshold'] : 60,
            ),
            'get_declining_students' => $this->analytics->getDecliningStudents(
                $colegioId,
                $this->str($args, 'grade'),
                $this->str($args, 'section'),
            ),
            'get_academic_trends' => $this->analytics->getAcademicTrends(
                $colegioId,
                (string) ($args['metric'] ?? 'average'),
                (int) ($args['weeks'] ?? 4),
            ),
            'generate_school_report' => $this->analytics->generateSchoolReport(
                $colegioId,
                $this->str($args, 'grade'),
                $this->str($args, 'section'),
            ),
            'get_rankings' => $this->analytics->getRankings(
                $colegioId,
                (string) ($args['metric'] ?? 'average'),
                $this->str($args, 'grade'),
                $this->str($args, 'section'),
                $this->str($args, 'subject_name') ?? $this->str($args, 'subject'),
                (int) ($args['limit'] ?? 5),
            ),
            'get_section_counts' => $this->analytics->getSectionCounts($colegioId),
            default => [
                'message' => 'No reconozco esa consulta.',
                'data' => [],
            ],
        };
    }

    /**
     * @param  array<int,array{success?:bool,message?:string,data?:array}>  $actions
     */
    public function compose(string $text, array $actions, bool $wantsOpinion, string $intent = ''): string
    {
        $facts = collect($actions)
            ->pluck('message')
            ->filter(fn ($msg) => is_string($msg) && trim($msg) !== '')
            ->values();

        if ($facts->isEmpty()) {
            return "**Hechos**\nNo hay datos suficientes en tu colegio para responder esa consulta.\n\n**Análisis**\nNo hay base suficiente para un análisis. Cuando existan notas o asistencia podré señalar riesgos.";
        }

        if ($intent === 'diagnose_school' || $this->wantsDiagnosis($text)) {
            return $this->composeDiagnosis($actions);
        }
        if ($intent === 'executive_report' || $this->wantsExecutiveReport($text)) {
            return $this->composeExecutiveReport($text, $actions);
        }
        $insight = $this->composeStudentInsight($text, $actions);
        if ($insight !== null) {
            return $insight;
        }

        $hasEmpty = collect($actions)->every(function ($action) {
            $data = (array) ($action['data'] ?? []);

            return $this->looksEmpty($data);
        });

        $conclusion = $this->executiveConclusion($actions, (string) $facts->first());
        $body = $this->compactFacts($actions);
        $classification = $this->classify($actions, $hasEmpty);
        $analysis = $this->analysisFromFacts($text, $actions, $wantsOpinion, $hasEmpty);

        $out = $conclusion."\n\n**Hechos**\n".$body;
        if ($classification) {
            $out .= "\n\n**Estado:** ".$classification;
        }
        if ($analysis !== null && $analysis !== '') {
            $out .= "\n\n**Análisis**\n".$analysis;
        }

        return $out;
    }

    /**
     * @param  array<int,array{data?:array,message?:string,action_type?:string}>  $actions
     */
    private function executiveConclusion(array $actions, string $fallback): string
    {
        foreach ($actions as $action) {
            $data = (array) ($action['data'] ?? []);
            $tool = (string) ($action['action_type'] ?? '');
            if (isset($data['class_avg_pct'])) {
                $avg = (float) $data['class_avg_pct'];
                $n = is_countable($data['students'] ?? null) ? count($data['students']) : 0;
                $scope = trim(($data['grade'] ?? '').(isset($data['section']) && $data['section'] ? ' '.$data['section'] : ''));

                return ($scope !== '' ? $scope : 'El grupo')." tiene promedio {$avg}%".($n > 0 ? " ({$n} alumno(s) con notas)." : '.');
            }
            if (isset($data['overall_avg_pct'])) {
                $name = is_string($data['student'] ?? null) ? $data['student'] : 'El alumno';

                return "{$name} tiene promedio general {$data['overall_avg_pct']}%.";
            }
            if ($tool === 'get_at_risk_students' && isset($data['students']) && is_countable($data['students'])) {
                $n = collect($data['students'])->map(function ($row) {
                    return is_array($row) ? ($row['name'] ?? '') : ($row->name ?? '');
                })->filter()->unique()->count();

                return $n === 0
                    ? 'Ningún alumno aparece por debajo del umbral de riesgo con los datos actuales.'
                    : ($n === 1 ? 'Hay 1 alumno que requiere seguimiento por bajo rendimiento.' : "Hay {$n} alumnos que requieren seguimiento por bajo rendimiento.");
            }
            if (isset($data['verdict']) && is_string($data['verdict']) && $data['verdict'] !== '') {
                return $data['verdict'];
            }
            if (isset($data['ranking']) && is_countable($data['ranking']) && count($data['ranking']) > 0) {
                $first = $data['ranking'][0] ?? (is_object($data['ranking']) ? $data['ranking']->first() : null);
                $name = is_array($first) ? ($first['name'] ?? null) : (is_object($first) ? ($first->name ?? null) : null);
                $metric = $data['metric'] ?? 'average';
                if (is_string($name) && $name !== '') {
                    return $metric === 'absences'
                        ? "{$name} encabeza el listado de faltas."
                        : "{$name} encabeza el ranking de promedios.";
                }
            }
        }

        return $this->firstSentence($fallback);
    }

    /**
     * @param  array<int,array{data?:array,message?:string,action_type?:string}>  $actions
     */
    private function compactFacts(array $actions): string
    {
        $blocks = [];
        foreach ($actions as $action) {
            $line = $this->compactFactBlock($action);
            if ($line !== '') {
                $blocks[] = $line;
            }
        }

        return $blocks !== [] ? implode("\n", $blocks) : 'No hay datos suficientes en tu colegio para responder esa consulta.';
    }

    /**
     * @param  array{data?:array,message?:string,action_type?:string}  $action
     */
    private function compactFactBlock(array $action): string
    {
        $data = (array) ($action['data'] ?? []);
        $tool = (string) ($action['action_type'] ?? '');
        $message = trim((string) ($action['message'] ?? ''));

        if (isset($data['students']) && is_countable($data['students']) && in_array($tool, ['get_course_performance', 'get_at_risk_students', 'get_declining_students', 'get_attendance'], true)) {
            $names = collect($data['students'])->take(5)->map(function ($row) use ($tool) {
                $name = is_array($row) ? ($row['name'] ?? '') : ($row->name ?? '');
                if ($name === '') {
                    return null;
                }
                if ($tool === 'get_attendance') {
                    $abs = is_array($row) ? ($row['absences'] ?? null) : ($row->absences ?? null);

                    return $abs !== null ? "{$name} ({$abs} falta(s))" : $name;
                }
                $avg = is_array($row) ? ($row['avg_pct'] ?? $row['recent_avg'] ?? null) : ($row->avg_pct ?? $row->recent_avg ?? null);

                return $avg !== null ? "{$name} (".round((float) $avg, 1).'%)' : $name;
            })->filter()->unique()->values();
            $total = collect($data['students'])->map(function ($row) {
                return is_array($row) ? ($row['name'] ?? '') : ($row->name ?? '');
            })->filter()->unique()->count();
            $extra = $total > 5 ? ' y '.($total - 5).' más' : '';
            $prefix = match ($tool) {
                'get_at_risk_students' => 'En riesgo: ',
                'get_declining_students' => 'Bajaron: ',
                'get_attendance' => 'Asistencia: ',
                default => 'Alumnos: ',
            };

            return $names->isEmpty()
                ? $this->stripTechnical($this->withoutTable($message))
                : $prefix.$names->implode(', ').$extra.'.';
        }

        if (isset($data['overall_avg_pct']) && isset($data['subjects'])) {
            $subjects = collect($data['subjects'])->map(function ($row) {
                $name = is_array($row) ? ($row['subject_name'] ?? '') : ($row->subject_name ?? '');
                $avg = is_array($row) ? ($row['avg_pct'] ?? null) : ($row->avg_pct ?? null);

                return $name !== '' && $avg !== null ? "{$name} ".round((float) $avg, 1).'%' : null;
            })->filter()->values();

            return $subjects->isEmpty()
                ? $this->stripTechnical($this->withoutTable($message))
                : 'Por materia: '.$subjects->implode('; ').'.';
        }

        if ($tool === 'get_teachers' && isset($data['teachers']) && is_countable($data['teachers'])) {
            $lines = collect($data['teachers'])->take(12)->map(function ($row) {
                $name = is_array($row) ? ($row['name'] ?? '') : ($row->name ?? '');
                $courses = is_array($row) ? ($row['course_names'] ?? null) : ($row->course_names ?? null);
                $count = is_array($row) ? ($row['courses_count'] ?? null) : ($row->courses_count ?? null);
                if ($name === '') {
                    return null;
                }
                if (is_string($courses) && $courses !== '') {
                    return "{$name}: {$courses}";
                }

                return $count !== null ? "{$name} ({$count} curso(s))" : $name;
            })->filter();

            return $lines->isEmpty()
                ? $this->stripTechnical($this->withoutTable($message))
                : $lines->map(fn ($line) => '- '.$line)->implode("\n");
        }

        if (isset($data['students_count']) && $this->looksEmpty($data) && ! isset($data['class_avg_pct'])) {
            return $this->stripTechnical($this->withoutTable($message));
        }

        return $this->stripTechnical($this->withoutTable($message));
    }

    private function withoutTable(string $text): string
    {
        $cut = preg_split('/\n(?=\|)/', $text, 2);

        return trim((string) ($cut[0] ?? $text));
    }

    private function stripTechnical(string $text): string
    {
        $clean = preg_replace('/\b(?:tool|query|sql|eloquent|openai|endpoint|json)\b/iu', '', $text) ?? $text;

        return trim((string) preg_replace('/\s+/u', ' ', $clean));
    }

    private function classify(array $actions, bool $hasEmpty): ?string
    {
        if ($hasEmpty) {
            return null;
        }

        $rank = ['crítico' => 3, 'requiere atención' => 2, 'buen estado' => 1];
        $worst = null;
        foreach ($actions as $action) {
            $label = $this->classifyAction($action);
            if ($label === null) {
                continue;
            }
            if ($worst === null || $rank[$label] > $rank[$worst]) {
                $worst = $label;
            }
        }

        return match ($worst) {
            'crítico' => 'Crítico',
            'requiere atención' => 'Requiere atención',
            'buen estado' => 'Buen estado',
            default => null,
        };
    }

    /**
     * @param  array{data?:array,action_type?:string}  $action
     */
    private function classifyAction(array $action): ?string
    {
        $data = (array) ($action['data'] ?? []);
        $tool = (string) ($action['action_type'] ?? '');

        if (isset($data['class_avg_pct'])) {
            $avg = (float) $data['class_avg_pct'];
            if ($avg < 55) {
                return 'crítico';
            }
            if ($avg < 70) {
                return 'requiere atención';
            }

            return 'buen estado';
        }
        if (isset($data['overall_avg_pct'])) {
            $avg = (float) $data['overall_avg_pct'];
            if ($avg < 55) {
                return 'crítico';
            }
            if ($avg < 70) {
                return 'requiere atención';
            }

            return 'buen estado';
        }
        if ($tool === 'get_at_risk_students' && isset($data['students']) && is_countable($data['students']) && isset($data['threshold'])) {
            $avgs = collect($data['students'])->map(function ($row) {
                return is_array($row) ? ($row['avg_pct'] ?? null) : ($row->avg_pct ?? null);
            })->filter(fn ($avg) => $avg !== null);
            if ($avgs->isEmpty()) {
                return null;
            }
            $lowest = (float) $avgs->min();
            if ($lowest < 55 || $avgs->count() >= 5) {
                return 'crítico';
            }

            return 'requiere atención';
        }
        if (isset($data['comparison']['a']['avg_pct'], $data['comparison']['b']['avg_pct'])
            && $data['comparison']['a']['avg_pct'] !== null
            && $data['comparison']['b']['avg_pct'] !== null) {
            $a = (float) $data['comparison']['a']['avg_pct'];
            $b = (float) $data['comparison']['b']['avg_pct'];
            if (min($a, $b) < 55) {
                return 'crítico';
            }
            if (abs($a - $b) > 15 || min($a, $b) < 70) {
                return 'requiere atención';
            }

            return 'buen estado';
        }
        if ($tool === 'generate_school_report' && isset($data['performance']['class_avg_pct'])) {
            $avg = (float) $data['performance']['class_avg_pct'];
            if ($avg < 55) {
                return 'crítico';
            }
            if ($avg < 70) {
                return 'requiere atención';
            }

            return 'buen estado';
        }

        return null;
    }

    /**
     * @return array<string,mixed>
     */
    public function sanitizeContext(array $context): array
    {
        unset($context['colegio_id'], $context['school_id'], $context['tenant_id']);

        $grade = $context['grade'] ?? $context['grado'] ?? null;
        $section = $context['section'] ?? $context['seccion'] ?? null;
        $subject = $context['subject'] ?? $context['subject_name'] ?? $context['materia'] ?? null;
        $name = $context['name'] ?? $context['course_name'] ?? null;

        if ((! $grade || ! $section) && is_string($name)) {
            $parsed = $this->parseGradeSection($name);
            $grade = $grade ?: $parsed['grade'];
            $section = $section ?: $parsed['section'];
        }

        return array_filter([
            'grade' => is_string($grade) ? $this->normalizeGrade($grade) : null,
            'section' => is_string($section) ? strtoupper(trim($section)) : null,
            'subject' => is_string($subject) ? trim($subject) : null,
            'course_id' => isset($context['id']) ? (int) $context['id'] : (isset($context['course_id']) ? (int) $context['course_id'] : null),
        ], fn ($v) => $v !== null && $v !== '' && $v !== 0);
    }

    /**
     * @return array<string,mixed>
     */
    public function sanitizeArgs(array $args): array
    {
        unset($args['colegio_id'], $args['school_id'], $args['tenant_id']);

        return $args;
    }

    public function toolDefinitions(): array
    {
        $commonFilters = [
            'grade' => ['type' => ['string', 'null']],
            'section' => ['type' => ['string', 'null']],
            'subject_name' => ['type' => ['string', 'null']],
            'student_name' => ['type' => ['string', 'null']],
        ];

        $defs = [
            'get_students' => ['Listar alumnos del colegio del director.', ['grade', 'section']],
            'get_student' => ['Obtener un alumno por nombre.', ['student_name']],
            'get_courses' => ['Listar cursos del colegio.', ['grade', 'section', 'subject_name']],
            'get_teachers' => ['Listar profesores del colegio.', []],
            'get_grades' => ['Listar calificaciones reales.', ['grade', 'section', 'subject_name', 'student_name']],
            'get_attendance' => ['Consultar asistencia y faltas.', ['grade', 'section', 'student_name', 'days']],
            'get_evaluations' => ['Listar evaluaciones registradas.', ['grade', 'section', 'subject_name']],
            'get_assignments' => ['Listar tareas registradas.', ['grade', 'section', 'subject_name']],
            'get_student_performance' => ['Rendimiento de un alumno.', ['student_name']],
            'get_course_performance' => ['Rendimiento de un grado/sección.', ['grade', 'section', 'subject_name']],
            'compare_courses' => ['Comparar dos cursos o grados.', ['grade', 'grade_b', 'section', 'section_b', 'subject_name']],
            'get_at_risk_students' => ['Alumnos con bajo rendimiento.', ['grade', 'section', 'subject_name']],
            'get_declining_students' => ['Alumnos que bajaron su promedio.', ['grade', 'section']],
            'get_academic_trends' => ['Tendencias de notas o faltas.', ['metric', 'weeks']],
            'generate_school_report' => ['Informe académico del colegio o un curso.', ['grade', 'section']],
            'get_rankings' => ['Ranking por promedio o faltas.', ['metric', 'grade', 'section', 'limit']],
        ];

        return collect($defs)->map(function ($definition, $name) use ($commonFilters) {
            [$description, $keys] = $definition;
            $properties = [];
            foreach ($keys as $key) {
                $properties[$key] = $commonFilters[$key] ?? match ($key) {
                    'days', 'weeks', 'limit' => ['type' => ['integer', 'null']],
                    'metric' => ['type' => ['string', 'null'], 'enum' => ['average', 'absences', null]],
                    'grade_b', 'section_b' => ['type' => ['string', 'null']],
                    default => ['type' => ['string', 'null']],
                };
            }

            return [
                'type' => 'function',
                'function' => [
                    'name' => $name,
                    'description' => $description.' Nunca inventes datos. colegio_id lo pone el backend.',
                    'strict' => false,
                    'parameters' => [
                        'type' => 'object',
                        'properties' => $properties,
                        'required' => [],
                        'additionalProperties' => false,
                    ],
                ],
            ];
        })->values()->all();
    }

    /**
     * @param  array<string,mixed>  $context
     * @return array{tools:array<int,array{tool:string,args:array}>,intent:string,clarification:?string,wants_opinion:bool}
     */
    private function planFromText(string $text, array $context, array $memory = []): array
    {
        $value = $this->normalized($text);
        $pair = $this->extractComparedCourses($text);
        $grade = $this->extractGrade($text);
        $section = $this->extractSection($text);
        $subject = $this->extractSubject($text);

        $followUp = $this->planFollowUp($text, $memory);
        if ($followUp !== null) {
            return $followUp;
        }

        if ($this->wantsDiagnosis($text)) {
            return [
                'tools' => [['tool' => 'generate_school_report', 'args' => []]],
                'intent' => 'diagnose_school',
                'clarification' => null,
                'wants_opinion' => true,
            ];
        }

        if ($this->wantsExecutiveReport($text)) {
            return [
                'tools' => [['tool' => 'generate_school_report', 'args' => array_filter([
                    'grade' => $grade,
                    'section' => $section,
                ])]],
                'intent' => 'executive_report',
                'clarification' => null,
                'wants_opinion' => true,
            ];
        }

        if (preg_match('/\b(?:investig|empeor|impresion|puedes revisar|que esta pasando)\b/u', $value)) {
            if ($grade) {
                return [
                    'tools' => [
                        ['tool' => 'get_course_performance', 'args' => array_filter(['grade' => $grade, 'section' => $section, 'subject_name' => $subject])],
                        ['tool' => 'get_at_risk_students', 'args' => array_filter(['grade' => $grade, 'section' => $section])],
                        ['tool' => 'get_attendance', 'args' => array_filter(['grade' => $grade, 'section' => $section])],
                    ],
                    'intent' => 'investigate',
                    'clarification' => null,
                    'wants_opinion' => true,
                ];
            }

            return [
                'tools' => [['tool' => 'generate_school_report', 'args' => []]],
                'intent' => 'diagnose_school',
                'clarification' => null,
                'wants_opinion' => true,
            ];
        }

        if (preg_match('/\b(?:informe|resumen|resume|estado academico)\b/u', $value)) {
            return $this->pack('generate_school_report', [
                'grade' => $grade,
                'section' => $section,
            ]);
        }

        if (preg_match('/problemas\s+en\s+/u', $value) && $subject) {
            return $this->pack('query_academic', [
                'query_type' => 'subject_at_risk',
                'subject_name' => $subject,
            ]);
        }

        if (preg_match('/\b(?:preocup|problemas?|atencion|necesitan atencion|deberia preocuparme)\b/u', $value)) {
            return [
                'tools' => [
                    ['tool' => 'get_at_risk_students', 'args' => array_filter([
                        'grade' => $grade,
                        'section' => $section,
                        'subject_name' => $subject,
                        'threshold' => $this->extractThreshold($text),
                    ])],
                    ['tool' => 'get_attendance', 'args' => array_filter(['grade' => $grade, 'section' => $section])],
                ],
                'intent' => 'school_concerns',
                'clarification' => null,
                'wants_opinion' => true,
            ];
        }

        if (preg_match('/\b(?:bajado|bajo|bajaron)\s+(?:su\s+)?promedio\b/u', $value)) {
            return $this->pack('get_declining_students', ['grade' => $grade, 'section' => $section]);
        }

        // Consultas combinadas: estudiante + asistencia en una sola pregunta (debe ir antes de asistencia sola)
        if (preg_match('/como va.*asistencia|asistencia.*como va/u', $value) && $this->extractStudentName($text)) {
            $name = $this->extractStudentName($text);
            return [
                'tools' => [
                    ['tool' => 'get_student_performance', 'args' => ['student_name' => $name]],
                    ['tool' => 'get_attendance', 'args' => ['student_name' => $name]],
                ],
                'intent' => 'combined_student',
                'clarification' => null,
                'wants_opinion' => false,
            ];
        }

        if (preg_match('/\brendimiento de\b|\ble va a\b/u', $value)) {
            $name = $this->extractStudentName($text);
            if ($name) {
                return $this->pack('get_student_performance', array_filter([
                    'student_name' => $name,
                    'subject_name' => $subject,
                ]));
            }
        }

        if (preg_match('/\basistencia\b/u', $value)) {
            if (! $grade && ! ($context['grade'] ?? null)) {
                return $this->pack('get_attendance', []);
            }

            return $this->pack('get_attendance', ['grade' => $grade, 'section' => $section]);
        }

        if ($pair && ($pair[0]['grade'] ?? null) && ($pair[1]['grade'] ?? null)) {
            return $this->pack('compare_courses', [
                'grade' => $pair[0]['grade'],
                'section' => $pair[0]['section'],
                'grade_b' => $pair[1]['grade'],
                'section_b' => $pair[1]['section'],
                'subject_name' => $subject,
            ]);
        }

        if (preg_match('/\b(?:tendencia|tendencias|evolucion)\b/u', $value)) {
            return $this->pack('get_academic_trends', [
                'metric' => preg_match('/falta|asistencia/u', $value) ? 'absences' : 'average',
            ]);
        }

        if (preg_match('/mejor\s+(?:promedio|rendimiento)|mas destacado|mejor alumno|primer lugar|quien es el estudiante|ranking de promedios|ranking promedios/u', $value)) {
            $top = $this->extractLimit($text);

            return $this->pack('get_rankings', [
                'metric' => 'average',
                'limit' => $top ?? (preg_match('/el estudiante|mas destacado|mejor alumno/u', $value) ? 1 : 5),
                'grade' => $grade,
                'section' => $section,
                'subject_name' => $subject,
            ]);
        }

        if (preg_match('/quien ha faltado|quienes han faltado|quien esta faltando/u', $value)) {
            return $this->pack('query_academic', ['query_type' => 'frequent_absentees']);
        }
        if (preg_match('/mas faltas|ranking de faltas/u', $value)) {
            return $this->pack('get_rankings', [
                'metric' => 'absences',
                'grade' => $grade,
                'section' => $section,
            ]);
        }

        if (preg_match('/bajo rendimiento|alumnos en riesgo/u', $value)) {
            return $this->pack('get_at_risk_students', [
                'grade' => $grade,
                'section' => $section,
                'subject_name' => $subject,
                'threshold' => $this->extractThreshold($text),
            ]);
        }

        if (preg_match('/como va(?:\s+el|\s+la)?\s+profesor/u', $value)) {
            return $this->pack('query_academic', ['query_type' => 'teacher_overview', 'teacher_name' => $this->after($text, 'profesor')]);
        }

        if (preg_match('/cursos tiene asignad/u', $value)) {
            return $this->pack('query_academic', ['query_type' => 'teacher_courses', 'teacher_name' => $this->after($text, 'profesor')]);
        }

        if (preg_match('/cuantas faltas tiene/u', $value)) {
            return $this->pack('query_academic', ['query_type' => 'student_absences', 'student_name' => $this->after($text, 'tiene')]);
        }

        if (preg_match('/\bevaluaciones\b/u', $value)) {
            return $this->pack('get_evaluations', ['grade' => $grade, 'section' => $section, 'subject_name' => $subject]);
        }

        if (preg_match('/\btareas?\b/u', $value)) {
            return $this->pack('get_assignments', array_filter([
                'grade' => $grade,
                'section' => $section,
                'subject_name' => $subject,
                'pending_only' => preg_match('/pendiente/u', $value) ? true : null,
            ]));
        }

        if (preg_match('/cuantos profesores/u', $value)) {
            return $this->pack('query_academic', ['query_type' => 'school_stats', 'stat' => 'teachers']);
        }
        if (preg_match('/cuantos (?:alumnos|estudiantes)/u', $value) && preg_match('/cada seccion|por seccion/u', $value)) {
            return $this->pack('get_section_counts', []);
        }
        if (preg_match('/cuantos (?:alumnos|estudiantes)/u', $value) && $grade) {
            return $this->pack('query_academic', ['query_type' => 'grade_overview', 'grade' => $grade]);
        }
        if (preg_match('/cuantos (?:alumnos|estudiantes)/u', $value)) {
            return $this->pack('query_academic', ['query_type' => 'school_stats', 'stat' => 'students']);
        }
        if (preg_match('/que profesores/u', $value)) {
            return $this->pack('get_teachers', []);
        }
        if (preg_match('/cuantos cursos/u', $value)) {
            return $this->pack('query_academic', ['query_type' => 'school_stats', 'stat' => 'courses']);
        }
        if (preg_match('/que cursos/u', $value)) {
            return $this->pack('query_academic', ['query_type' => 'school_courses']);
        }

        if (preg_match('/que (?:alumnos|estudiantes) hay/u', $value) && $grade) {
            return $this->pack('get_students', ['grade' => $grade, 'section' => $section]);
        }

        if (preg_match('/como va(?:n)?|como esta(?:n)?|rendimiento|como van los/u', $value)) {
            if ($grade) {
                return $this->pack('get_course_performance', [
                    'grade' => $grade,
                    'section' => $section,
                    'subject_name' => $subject,
                ]);
            }
            if ($this->refersToSelectedCourse($text) && ! empty($context['grade'])) {
                return $this->pack('get_course_performance', [
                    'grade' => $context['grade'],
                    'section' => $context['section'] ?? null,
                    'subject_name' => $subject,
                ]);
            }
            if ($this->refersToSelectedCourse($text) && empty($context['grade'])) {
                return [
                    'tools' => [],
                    'intent' => 'needs_course',
                    'clarification' => 'Dime el curso (por ejemplo 4to A) o selecciónalo en la pantalla para consultar su estado.',
                    'wants_opinion' => false,
                ];
            }
            if (preg_match('/como va\s+(.+?)\s*[?¿.!]*$/u', $value, $m)) {
                $target = trim((string) $m[1], " \t¿?!.¡");
                if ($this->extractGrade($target)) {
                    return $this->pack('get_course_performance', [
                        'grade' => $this->extractGrade($target),
                        'section' => $this->extractSection($text),
                    ]);
                }

                return $this->pack('get_student_performance', ['student_name' => $target]);
            }
        }

        if ($grade) {
            return $this->pack('get_course_performance', [
                'grade' => $grade,
                'section' => $section,
                'subject_name' => $subject,
            ]);
        }

        return [
            'tools' => [],
            'intent' => 'unclear',
            'clarification' => 'Puedo consultar notas, asistencia, cursos o un informe del colegio. ¿Sobre qué grado, sección o alumno quieres saber?',
            'wants_opinion' => false,
        ];
    }

    /**
     * @param  array<int,array{tool:string,args:array}>  $tools
     * @param  array<string,mixed>  $context
     * @return array<int,array{tool:string,args:array}>
     */
    private function applyContext(array $tools, string $text, array $context): array
    {
        if ($context === [] || ! $this->refersToSelectedCourse($text) && $this->extractGrade($text)) {
            return $tools;
        }

        if (! $this->refersToSelectedCourse($text) && $this->extractGrade($text)) {
            return $tools;
        }

        return collect($tools)->map(function ($call) use ($context, $text) {
            $args = $call['args'];
            $needsScope = in_array($call['tool'], [
                'get_course_performance', 'get_attendance', 'get_students', 'get_grades',
                'get_evaluations', 'get_assignments', 'get_at_risk_students',
                'generate_school_report', 'get_declining_students', 'get_rankings',
            ], true);
            if ($needsScope && empty($args['grade']) && ! empty($context['grade']) && ($this->refersToSelectedCourse($text) || $this->extractGrade($text) === null)) {
                $args['grade'] = $context['grade'];
                $args['section'] = $args['section'] ?? ($context['section'] ?? null);
                if (empty($args['subject_name']) && $this->extractSubject($text)) {
                    $args['subject_name'] = $context['subject'] ?? $this->extractSubject($text);
                }
            }
            $call['args'] = $args;

            return $call;
        })->all();
    }

    private function refersToSelectedCourse(string $text): bool
    {
        $value = $this->normalized($text);

        return (bool) preg_match('/\b(?:mi curso|este curso|el curso|esta seccion|este grado)\b/u', $value);
    }

    /**
     * @param  array<string,mixed>  $args
     * @return array{tools:array<int,array{tool:string,args:array}>,intent:string,clarification:?string,wants_opinion:bool}
     */
    private function pack(string $tool, array $args): array
    {
        return [
            'tools' => [['tool' => $tool, 'args' => array_filter($args, fn ($v) => $v !== null && $v !== '')]],
            'intent' => $tool,
            'clarification' => null,
            'wants_opinion' => false,
        ];
    }

    public function wantsDiagnosis(string $text): bool
    {
        $value = $this->normalized($text);

        return (bool) preg_match(
            '/como esta(?:n)?\s+(?:mi |el )?colegio|diagnostico(?: del colegio)?|estado general del colegio|que esta pasando en (?:mi |el )?colegio/u',
            $value
        );
    }

    public function wantsExecutiveReport(string $text): bool
    {
        $value = $this->normalized($text);

        return (bool) preg_match('/\b(?:informe|resumen)\b/u', $value)
            && (bool) preg_match('/\b(?:reunion|profesores|ejecutivo|prepara(?:me)?|para la reunion)\b/u', $value);
    }

    /**
     * @param  array<string,mixed>  $memory
     * @return array{tools:array,intent:string,clarification:?string,wants_opinion:bool,focus?:array}|null
     */
    private function planFollowUp(string $text, array $memory): ?array
    {
        $focus = (array) ($memory['focus'] ?? []);
        if ($focus === [] && empty($memory['student_name']) && empty($memory['student_names']) && empty($memory['last_intent'])) {
            return null;
        }
        if (! $this->looksLikeFollowUp($text) && ! preg_match('/\b(?:por que|tienen en comun|mas preocupante|en que materia|su profesor|caso mas)\b/u', $this->normalized($text))) {
            return null;
        }

        $value = $this->normalized($text);
        $student = $this->extractStudentName($text)
            ?: ($focus['student_name'] ?? $memory['student_name'] ?? ((array) ($memory['student_names'] ?? []))[0] ?? null);
        $atRisk = (array) ($focus['at_risk'] ?? []);
        if (! $student && $atRisk !== []) {
            $student = $this->worstRiskName($atRisk);
        }

        if (preg_match('/tienen en comun|que tienen/u', $value)) {
            return [
                'tools' => [],
                'intent' => 'explain_from_memory',
                'clarification' => null,
                'wants_opinion' => true,
                'focus' => $focus + ['follow_up' => 'common'],
            ];
        }

        if (preg_match('/por que/u', $value)) {
            return [
                'tools' => [],
                'intent' => 'explain_from_memory',
                'clarification' => null,
                'wants_opinion' => true,
                'focus' => $focus + ['follow_up' => 'why', 'student_name' => $student],
            ];
        }

        if (preg_match('/mas preocupante|caso mas|el peor/u', $value)) {
            if (! is_string($student) || $student === '') {
                return null;
            }

            return [
                'tools' => [
                    ['tool' => 'get_student_performance', 'args' => ['student_name' => $student]],
                    ['tool' => 'get_attendance', 'args' => ['student_name' => $student]],
                ],
                'intent' => 'combined_student',
                'clarification' => null,
                'wants_opinion' => true,
                'focus' => $focus + ['student_name' => $student, 'follow_up' => 'worst'],
            ];
        }

        if (preg_match('/en que materia|materia.*peor|peor.*materia/u', $value)) {
            if (! is_string($student) || $student === '') {
                return null;
            }

            return [
                'tools' => [['tool' => 'get_student_performance', 'args' => ['student_name' => $student]]],
                'intent' => 'get_student_performance',
                'clarification' => null,
                'wants_opinion' => true,
                'focus' => $focus + ['student_name' => $student, 'follow_up' => 'subject'],
            ];
        }

        if (preg_match('/quien es su profesor|su profesor/u', $value)) {
            if (! is_string($student) || $student === '') {
                return null;
            }

            return [
                'tools' => [['tool' => 'get_student_performance', 'args' => ['student_name' => $student]]],
                'intent' => 'get_student_performance',
                'clarification' => null,
                'wants_opinion' => true,
                'focus' => $focus + ['student_name' => $student, 'follow_up' => 'teacher'],
            ];
        }

        return null;
    }

    /**
     * @param  array<int,array{data?:array,action_type?:string}>  $actions
     * @param  array<string,mixed>  $previous
     * @return array<string,mixed>
     */
    public function extractFocus(array $actions, string $intent, string $text, array $previous = []): array
    {
        $focus = $previous;
        $focus['intent'] = $intent;
        $focus['grade'] = $this->extractGrade($text) ?? ($previous['grade'] ?? null);
        $focus['section'] = $this->extractSection($text) ?? ($previous['section'] ?? null);

        foreach ($actions as $action) {
            $data = (array) ($action['data'] ?? []);
            $tool = (string) ($action['action_type'] ?? '');
            if (is_string($data['student'] ?? null)) {
                $focus['student_name'] = $data['student'];
                $focus['kind'] = 'student';
            }
            if (isset($data['grade'])) {
                $focus['grade'] = $data['grade'] ?: $focus['grade'];
            }
            if (isset($data['section'])) {
                $focus['section'] = $data['section'] ?: $focus['section'];
            }
            if ($tool === 'get_at_risk_students' || isset($data['threshold'])) {
                $rows = $this->rowsOf($data['students'] ?? []);
                if ($rows !== []) {
                    $focus['kind'] = 'at_risk';
                    $focus['at_risk'] = $rows;
                    $focus['student_names'] = array_values(array_unique(array_column($rows, 'name')));
                    $focus['student_name'] = $this->worstRiskName($rows) ?? ($focus['student_name'] ?? null);
                }
            }
            if ($tool === 'get_attendance') {
                $focus['attendance'] = $this->rowsOf($data['students'] ?? []);
            }
            if (isset($data['subjects'])) {
                $focus['subjects'] = $this->rowsOf($data['subjects']);
                $focus['worst_subject'] = $data['worst_subject'] ?? ($focus['worst_subject'] ?? null);
                $focus['worst_teacher'] = $data['worst_teacher'] ?? ($focus['worst_teacher'] ?? null);
            }
            if (isset($data['at_risk']['students'])) {
                $rows = $this->rowsOf($data['at_risk']['students']);
                $focus['at_risk'] = $rows;
                $focus['kind'] = $focus['kind'] ?? 'school';
            }
        }

        return array_filter($focus, fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @param  mixed  $rows
     * @return array<int,array<string,mixed>>
     */
    private function rowsOf(mixed $rows): array
    {
        return collect($rows)->map(function ($row) {
            if (is_array($row)) {
                return $row;
            }
            if (is_object($row)) {
                return [
                    'name' => $row->name ?? null,
                    'avg_pct' => $row->avg_pct ?? null,
                    'subject_name' => $row->subject_name ?? null,
                    'grade' => $row->grade ?? null,
                    'absences' => $row->absences ?? null,
                    'teacher_name' => $row->teacher_name ?? null,
                ];
            }

            return [];
        })->filter()->values()->all();
    }

    /**
     * @param  array<int,array<string,mixed>>  $rows
     */
    private function worstRiskName(array $rows): ?string
    {
        $sorted = collect($rows)->sortBy(fn ($row) => (float) ($row['avg_pct'] ?? 100))->first();

        return is_array($sorted) && ! empty($sorted['name']) ? (string) $sorted['name'] : null;
    }

    /**
     * @param  array<int,array{data?:array,action_type?:string,message?:string}>  $actions
     */
    private function composeDiagnosis(array $actions): string
    {
        $report = $this->reportData($actions);
        $avg = $report['school_avg_pct'] ?? ($report['performance']['class_avg_pct'] ?? null);
        $riskRows = $this->rowsOf($report['at_risk']['students'] ?? []);
        $riskNames = collect($riskRows)->pluck('name')->filter()->unique();
        $attendance = collect($this->rowsOf($report['attendance']['students'] ?? []));
        $absenceTotal = (int) $attendance->sum(fn ($row) => (int) ($row['absences'] ?? 0));
        $records = (int) $attendance->sum(fn ($row) => (int) ($row['records'] ?? 0));
        $criticalSubject = $report['critical_subject'] ?? collect($riskRows)
            ->groupBy(fn ($row) => $row['subject_name'] ?? 'sin materia')
            ->sortByDesc(fn ($group) => $group->count())
            ->keys()
            ->first();
        $priority = $report['priority_scope'] ?? collect($riskRows)
            ->groupBy(fn ($row) => trim(($row['grade'] ?? '').' '.($row['section'] ?? '')))
            ->sortByDesc(fn ($group) => $group->count())
            ->keys()
            ->first();

        $hasEmpty = $avg === null && $riskNames->isEmpty() && (int) ($report['students']['count'] ?? 0) === 0;
        if ($hasEmpty) {
            return "Aún no hay suficientes datos para diagnosticar el colegio.\n\n**Hechos**\nCuando existan notas y asistencia podré señalar riesgos, materias críticas y una prioridad.";
        }

        $label = $this->classifyDiagnosis($avg, $riskNames->count());
        $lines = [];
        $lines[] = $avg !== null ? "Promedio general: {$avg}%." : 'Promedio general: todavía no hay suficientes notas.';
        $lines[] = $riskNames->isEmpty()
            ? 'Ningún estudiante aparece por debajo del umbral de riesgo.'
            : $riskNames->count().' estudiante(s) requieren atención.';
        if ($records > 0) {
            $presentRate = max(0, min(100, round((1 - ($absenceTotal / max(1, $records))) * 100, 1)));
            $lines[] = "Asistencia reciente: {$presentRate}% de registros en los últimos 30 días ({$absenceTotal} falta(s)).";
        } else {
            $lines[] = 'Asistencia: todavía no hay registros de los últimos 30 días.';
        }
        if (is_string($criticalSubject) && $criticalSubject !== '' && $criticalSubject !== 'sin materia' && $riskNames->isNotEmpty()) {
            $lines[] = "{$criticalSubject} concentra la mayor cantidad de estudiantes con bajo rendimiento.";
        }
        if (! empty($report['trends']['trend'])) {
            $lines[] = 'Hay una tendencia de promedios en las últimas semanas; conviene contrastarla con los cursos en riesgo.';
        }

        $out = "**Estado general:** {$label}\n\n".implode("\n", $lines);
        if (is_string($priority) && trim($priority) !== '') {
            $out .= "\n\n**Prioridad:** revisar {$priority}.";
        }

        return $out;
    }

    /**
     * @param  array<int,array{data?:array,message?:string}>  $actions
     */
    private function composeExecutiveReport(string $text, array $actions): string
    {
        $report = $this->reportData($actions);
        $scope = $this->extractGrade($text);
        $section = $this->extractSection($text);
        $title = trim(($scope ?? 'Colegio').($section ? ' '.$section : ''));
        $avg = $report['school_avg_pct'] ?? ($report['performance']['class_avg_pct'] ?? null);
        $riskRows = $this->rowsOf($report['at_risk']['students'] ?? []);
        $top = collect($this->rowsOf($report['performance']['students'] ?? $report['performance']['ranking'] ?? []))
            ->sortByDesc(fn ($row) => (float) ($row['avg_pct'] ?? 0))
            ->take(3);
        $attendance = $this->rowsOf($report['attendance']['students'] ?? []);
        $label = $this->classifyDiagnosis($avg, collect($riskRows)->pluck('name')->unique()->count());

        $destacados = $top->map(function ($row) {
            $name = $row['name'] ?? 'Alumno';
            $avg = isset($row['avg_pct']) ? round((float) $row['avg_pct'], 1).'%' : '';

            return trim($name.' '.$avg);
        })->filter()->implode(', ');
        $riesgo = collect($riskRows)->map(function ($row) {
            $name = $row['name'] ?? '';
            $avg = isset($row['avg_pct']) ? round((float) $row['avg_pct'], 1).'%' : '';
            $subject = $row['subject_name'] ?? '';

            return trim($name.($avg !== '' ? " ({$avg}" : '').($subject !== '' ? " en {$subject}" : '').($avg !== '' ? ')' : ''));
        })->filter()->unique()->take(6)->implode(', ');

        $lines = [
            "**Informe de rendimiento — {$title}**",
            '',
            "**Estado:** {$label}",
            '**Rendimiento general:** '.($avg !== null ? $avg.'%.' : 'aún no hay suficientes notas.'),
            '**Estudiantes destacados:** '.($destacados !== '' ? $destacados.'.' : 'sin suficientes notas para destacar.'),
            '**Estudiantes en riesgo:** '.($riesgo !== '' ? $riesgo.'.' : 'ninguno por debajo del umbral con los datos actuales.'),
            '**Asistencia:** '.(count($attendance) > 0
                ? count($attendance).' alumno(s) con registro en los últimos 30 días.'
                : 'sin registros recientes.'),
            '**Observación:** este informe usa solo registros reales del colegio; si falta una materia o la asistencia, no se inventa.',
            '**Recomendación:** '.($riesgo !== ''
                ? 'Llevar a la reunión los casos en riesgo y acordar un plan con el docente de la materia más débil.'
                : 'Mantener el seguimiento semanal de notas y asistencia.'),
        ];

        return implode("\n", $lines);
    }

    /**
     * @param  array<string,mixed>  $focus
     */
    private function composeFollowUp(string $text, array $focus): string
    {
        $kind = $focus['follow_up'] ?? 'why';
        $risk = collect($this->rowsOf($focus['at_risk'] ?? []));
        $attendance = collect($this->rowsOf($focus['attendance'] ?? []));

        if ($kind === 'common') {
            if ($risk->isEmpty()) {
                return 'No tengo un listado previo de alumnos en riesgo en esta conversación. Pregúntame primero quiénes necesitan atención.';
            }
            $subjects = $risk->pluck('subject_name')->filter()->countBy()->sortDesc();
            $grades = $risk->map(fn ($row) => trim(($row['grade'] ?? '').' '.($row['section'] ?? '')))->filter()->countBy()->sortDesc();
            $parts = [];
            if ($subjects->isNotEmpty()) {
                $parts[] = 'la materia más común es '.$subjects->keys()->first();
            }
            if ($grades->isNotEmpty()) {
                $parts[] = 'el grupo más repetido es '.$grades->keys()->first();
            }

            return $parts === []
                ? 'El patrón común es el promedio bajo; no comparten el mismo curso o materia con los datos actuales.'
                : 'Lo que tienen en común: '.implode(' y ', $parts).'.';
        }

        if ($risk->isEmpty() && $attendance->isEmpty()) {
            return 'Necesito primero un listado de tu colegio (por ejemplo quiénes necesitan atención) para explicarte el porqué.';
        }

        $names = $risk->pluck('name')->filter()->unique();
        $absent = $attendance->filter(fn ($row) => (int) ($row['absences'] ?? 0) > 0)->pluck('name')->filter();
        $overlap = $names->intersect($absent);
        $reason = 'Principalmente por bajo rendimiento';
        if ($overlap->isNotEmpty()) {
            $reason .= ' y asistencia';
        }

        $examples = $risk->take(3)->map(function ($row) use ($attendance) {
            $name = $row['name'] ?? 'Un alumno';
            $avg = isset($row['avg_pct']) ? round((float) $row['avg_pct'], 1).'%' : 'sin promedio';
            $abs = $attendance->firstWhere('name', $name)['absences'] ?? null;

            return $name.' ('.$avg.($abs !== null ? ", {$abs} falta(s)" : '').')';
        })->implode('; ');

        return $reason.'. Casos: '.$examples.'.';
    }

    /**
     * @param  array<int,array{data?:array,action_type?:string}>  $actions
     * @return array<string,mixed>
     */
    private function reportData(array $actions): array
    {
        foreach ($actions as $action) {
            $data = (array) ($action['data'] ?? []);
            if (isset($data['at_risk']) || isset($data['performance']) || isset($data['school_avg_pct'])) {
                return $data;
            }
        }

        $merged = [];
        foreach ($actions as $action) {
            $tool = (string) ($action['action_type'] ?? '');
            $data = (array) ($action['data'] ?? []);
            if ($tool === 'get_at_risk_students') {
                $merged['at_risk'] = $data;
            } elseif ($tool === 'get_attendance') {
                $merged['attendance'] = $data;
            } elseif ($tool === 'get_course_performance') {
                $merged['performance'] = $data;
                $merged['school_avg_pct'] = $data['class_avg_pct'] ?? null;
            }
        }

        return $merged;
    }

    private function classifyDiagnosis(?float $avg, int $riskCount): string
    {
        if ($avg !== null && $avg < 55) {
            return 'Crítico';
        }
        if ($riskCount >= 5) {
            return 'Crítico';
        }
        if (($avg !== null && $avg < 70) || $riskCount >= 1) {
            return 'Requiere atención';
        }
        if ($avg !== null && $avg >= 70 && $riskCount === 0) {
            return 'Buen estado';
        }

        return 'Sin datos suficientes';
    }

    /**
     * @param  array<int,array{data?:array}>  $actions
     */
    private function composeStudentInsight(string $text, array $actions): ?string
    {
        $value = $this->normalized($text);
        $student = null;
        $worstSubject = null;
        $worstTeacher = null;
        $overall = null;
        $absences = null;
        foreach ($actions as $action) {
            $data = (array) ($action['data'] ?? []);
            if (is_string($data['student'] ?? null)) {
                $student = $data['student'];
                $overall = $data['overall_avg_pct'] ?? $overall;
                $worstSubject = $data['worst_subject'] ?? $worstSubject;
                $worstTeacher = $data['worst_teacher']
                    ?? collect($this->rowsOf($data['subjects'] ?? []))->pluck('teacher_name')->filter()->first()
                    ?? $worstTeacher;
            }
            if (isset($data['students']) && ($action['action_type'] ?? '') === 'get_attendance') {
                $row = collect($this->rowsOf($data['students']))->first();
                $absences = $row['absences'] ?? $absences;
            }
        }
        if ($student === null) {
            return null;
        }
        if (preg_match('/en que materia|materia.*peor|peor.*materia/u', $value)) {
            return $worstSubject
                ? "{$student} está peor en {$worstSubject}."
                : "{$student} no tiene una materia con notas suficientes para señalar la más débil.";
        }
        if (preg_match('/quien es su profesor|su profesor/u', $value)) {
            return $worstTeacher
                ? "El profesor de {$student} en ".($worstSubject ?: 'su materia más débil')." es {$worstTeacher}."
                : "No tengo el profesor asignado a las materias de {$student} en los registros actuales.";
        }
        if (preg_match('/mas preocupante|caso mas|el peor/u', $value)) {
            $bits = array_filter([
                $overall !== null ? "promedio {$overall}%" : null,
                $absences !== null ? "{$absences} falta(s) en los últimos 30 días" : null,
                $worstSubject ? "peor materia: {$worstSubject}" : null,
            ]);

            return "El caso más preocupante es {$student}".($bits !== [] ? ': '.implode(', ', $bits).'.' : '.');
        }

        return null;
    }

    /**
     * @param  array<int,array{data?:array,message?:string}>  $actions
     */
    private function analysisFromFacts(string $text, array $actions, bool $wantsOpinion, bool $hasEmpty): ?string
    {
        if ($hasEmpty && collect($actions)->every(fn ($a) => $this->looksEmpty((array) ($a['data'] ?? [])))) {
            return 'No hay base suficiente para un análisis. Cuando existan notas o asistencia podré señalar riesgos.';
        }

        $note = null;
        $recommendation = null;
        foreach ($actions as $action) {
            $data = (array) ($action['data'] ?? []);
            $tool = (string) ($action['action_type'] ?? '');
            if (isset($data['class_avg_pct'])) {
                $avg = (float) $data['class_avg_pct'];
                $note = $avg < 60
                    ? 'El promedio del grupo está por debajo de 60%.'
                    : ($avg < 70
                        ? 'El promedio está justo: hay margen para mejorar sin que el grupo esté en crisis.'
                        : 'El promedio del grupo es sólido.');
                if ($avg < 70) {
                    $recommendation = 'Revisa con el docente un plan de apoyo para quienes están más abajo.';
                }
            }
            if (! empty($data['students']) && $tool === 'get_at_risk_students') {
                $note = $note ?? 'Hay alumnos con promedio bajo.';
                $recommendation = $recommendation ?? 'Conversá con el docente y acordá un plan de recuperación para esos casos.';
            }
            if (! empty($data['students']) && $tool === 'get_declining_students') {
                $note = $note ?? 'Hay alumnos que bajaron su promedio.';
                $recommendation = $recommendation ?? 'Revisá asistencia y las últimas evaluaciones de esos alumnos.';
            }
            if (! empty($data['verdict'])) {
                $note = $note ?? (string) $data['verdict'];
            }
            if (isset($data['overall_avg_pct'])) {
                $avg = (float) $data['overall_avg_pct'];
                $note = $note ?? ($avg < 70
                    ? 'El rendimiento individual está por debajo de lo esperado.'
                    : 'El rendimiento individual está en un rango estable.');
                if ($avg < 70) {
                    $recommendation = $recommendation ?? 'Pedile al docente un seguimiento puntual de este alumno.';
                }
            }
            if (isset($data['performance']['class_avg_pct'])) {
                $avg = (float) $data['performance']['class_avg_pct'];
                $note = $note ?? ($avg < 70
                    ? 'El promedio del grupo pide seguimiento.'
                    : 'El promedio del grupo es sólido.');
                if ($avg < 70) {
                    $recommendation = $recommendation ?? 'Revisa con el docente un plan de apoyo para quienes están más abajo.';
                }
            }
        }

        $wants = $wantsOpinion || $this->wantsOpinion($text);
        if ($note === null && ! $wants) {
            return 'Los números anteriores son registros reales del colegio.';
        }
        if ($note === null) {
            return 'Con los hechos disponibles no hay una alerta adicional clara.';
        }

        if ($wants && $recommendation) {
            return $note.' Recomendación: '.$recommendation;
        }

        return $note;
    }

    private function looksEmpty(array $data): bool
    {
        if (isset($data['class_avg_pct']) || isset($data['overall_avg_pct']) || isset($data['performance']['class_avg_pct'])) {
            return false;
        }
        if (isset($data['students']['count'])) {
            return (int) $data['students']['count'] === 0;
        }

        $signals = 0;
        $empty = 0;
        foreach (['students', 'teachers', 'courses', 'grades', 'evaluations', 'assignments', 'ranking', 'trend', 'comparison'] as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }
            $signals++;
            $value = $data[$key];
            if (is_countable($value) ? count($value) === 0 : empty($value)) {
                $empty++;
            }
        }

        return $signals > 0 && $empty === $signals;
    }

    private function wantsOpinion(string $text): bool
    {
        $value = $this->normalized($text);

        return (bool) preg_match('/\b(?:preocup|problemas?|recomien|opinion|deberia|ves|atencion|informe|resumen)\b/u', $value);
    }

    private function firstSentence(string $text): string
    {
        $plain = trim((string) preg_replace('/\s+/', ' ', explode("\n", $text)[0]));

        return $plain !== '' ? $plain : 'Consulta completada con los datos disponibles.';
    }

    /**
     * @return array{0:array{grade:?string,section:?string},1:array{grade:?string,section:?string}}|null
     */
    private function extractComparedCourses(string $text): ?array
    {
        if (! preg_match('/compara(?:r|me)?\s+(.+?)\s+(?:con|y|vs\.?|versus)\s+(.+?)\s*[?¿.!]*$/iu', trim($text), $m)) {
            return null;
        }

        return [
            $this->parseGradeSection($m[1]),
            $this->parseGradeSection($m[2]),
        ];
    }

    /**
     * @return array{grade:?string,section:?string}
     */
    public function parseGradeSection(string $text): array
    {
        return [
            'grade' => $this->extractGrade($text),
            'section' => $this->extractSection($text),
        ];
    }

    public function extractGrade(string $text): ?string
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

        if (preg_match('/\b(7mo|8vo|9no|10mo|11vo|12vo)\b/u', $value, $m)) {
            return $m[1];
        }

        if (preg_match('/([1-6])(?:ro|ero|er|do|to|°|º)/u', $value, $m)) {
            return $this->normalizeGrade((string) $m[1]);
        }

        return null;
    }

    public function extractSection(string $text): ?string
    {
        if (preg_match('/secci[oó]n\s+([A-Za-z0-9]+)/iu', $text, $m)) {
            $raw = mb_strtolower(trim((string) $m[1]));
            if (preg_match('/^(?:de|del|el|la|los|las|grado|curso)$/u', $raw)) {
                return null;
            }

            return strtoupper(trim((string) $m[1]));
        }

        if (preg_match('/(?:[1-6](?:ro|ero|do|to|er|°|º)?|7mo|8vo|9no|10mo|11vo|12vo)(?:\s*grado)?\s+([A-Za-z])\b/u', $text, $m)) {
            return strtoupper($m[1]);
        }

        return null;
    }

    private function extractLimit(string $text): ?int
    {
        if (preg_match('/\btop\s+(\d{1,2})\b/iu', $text, $m)) {
            return max(1, min(20, (int) $m[1]));
        }

        return null;
    }

    private function extractThreshold(string $text): int
    {
        if (preg_match('/(?:menor|menos|debajo|bajo)\s+(?:a|de)\s+(\d{1,3})/iu', $text, $m)) {
            return max(1, min(100, (int) $m[1]));
        }

        return 60;
    }

    private function friendlyFailure(\Throwable $e): string
    {
        $raw = mb_strtolower($e->getMessage());
        if (str_contains($raw, 'timeout') || str_contains($raw, 'timed out') || str_contains($raw, 'curl error 28')) {
            return 'La consulta tardó más de lo esperado. Inténtalo de nuevo en un momento.';
        }

        return 'No pude completar esa consulta con los datos disponibles. Prueba con un curso, un alumno o un indicador concreto.';
    }

    private function errorCode(\Throwable $e): string
    {
        $raw = mb_strtolower($e->getMessage());
        if (str_contains($raw, 'timeout') || str_contains($raw, 'timed out') || str_contains($raw, 'curl error 28')) {
            return 'timeout';
        }
        if (str_contains($raw, 'openai') || str_contains($raw, 'api.openai')) {
            return 'openai_failed';
        }

        return 'tool_failed';
    }

    private function elapsedMs(int $started): int
    {
        return (int) ((hrtime(true) - $started) / 1_000_000);
    }

    public function extractStudentName(string $text): ?string
    {
        $norm = $this->normalized($text);
        $markers = ['rendimiento de', 'le va a', 'como va', 'como le va', 'va a'];
        foreach ($markers as $marker) {
            $mNorm = $this->normalized($marker);
            if (preg_match('/'.preg_quote($mNorm, '/').'\s+([a-z]+(?:\s+[a-z]+){0,2})/u', $norm, $m)) {
                $raw = trim($m[1]);
                // Filtrar si es grado
                if (preg_match('/^(?:1ro|2do|3ro|4to|5to|6to|grado|curso|seccion)/u', $raw)) {
                    continue;
                }
                // Title case desde normalizado
                $parts = explode(' ', $raw);
                $parts = array_map(fn($p) => mb_convert_case($p, MB_CASE_TITLE, 'UTF-8'), $parts);
                $name = implode(' ', $parts);
                // Eliminar palabras de cola
                $name = preg_replace('/\s+(y|en|con|de|del|que).*$/iu', '', $name);
                return trim($name);
            }
        }
        return null;
    }

    private function extractSubject(string $text): ?string
    {
        $normalized = $this->normalized($text);
        $aliases = [
            'ingles' => 'Inglés',
            'matematica' => 'Matemática',
            'lenguaje' => 'Lenguaje',
            'ciencias' => 'Ciencias',
            'computacion' => 'Computación',
            'robotica' => 'Robótica',
        ];
        foreach ($aliases as $alias => $canonical) {
            if (preg_match('/\b'.preg_quote($alias, '/').'\b/u', $normalized)) {
                return $canonical;
            }
        }

        return null;
    }

    private function normalizeGrade(string $grade): string
    {
        $value = $this->normalized($grade);
        foreach ([
            1 => ['1', '1ro', '1ero', 'primer'],
            2 => ['2', '2do', 'segundo'],
            3 => ['3', '3ro', '3ero', 'tercer'],
            4 => ['4', '4to', 'cuarto'],
            5 => ['5', '5to', 'quinto'],
            6 => ['6', '6to', 'sexto'],
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

    private function after(string $text, string $marker): string
    {
        if (preg_match('/'.$marker.'\s+(?:a\s+|el\s+|la\s+)?(.+?)\s*[?¿.!]*$/iu', $text, $m)) {
            return trim((string) $m[1]);
        }

        return trim($text);
    }

    private function normalized(string $text): string
    {
        $value = mb_strtolower($text);
        $value = strtr($value, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
        ]);

        return (string) preg_replace('/\s+/u', ' ', $value);
    }

    private function str(array $args, string $key): ?string
    {
        $value = $args[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    private function sessionId(): string
    {
        $current = session(self::SESSION_KEY);
        if (! is_string($current) || $current === '') {
            $current = (string) Str::uuid();
            session([self::SESSION_KEY => $current]);
        }

        return $current;
    }

    /**
     * @param  array<int,string>  $tools
     */
    private function record(
        User $director,
        string $intent,
        array $tools,
        string $status,
        int $started,
        string $sessionId,
        ?string $error = null,
        ?string $question = null,
    ): void {
        $duration = (int) ((hrtime(true) - $started) / 1_000_000);
        $this->telemetry->record([
            'user' => $director,
            'source' => 'director_data_agent',
            'event' => 'director_data_query',
            'action' => $intent,
            'category' => 'academic',
            'status' => $status,
            'duration_ms' => $duration,
            'error_code' => $error,
            'meta' => [
                'role' => $director->role,
                'intent' => $intent,
                'tools' => implode(',', $tools),
                'session_id' => $sessionId,
                'success' => $status === 'success' ? 1 : 0,
                'question' => $question ? mb_substr($question, 0, 200) : null,
                'colegio_id' => $director->colegio_id,
            ],
        ]);
    }
}

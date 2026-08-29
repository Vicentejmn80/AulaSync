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
        'get_teacher_invite_code',
        'verify_teacher',
        'verify_student',
        'get_grades',
        'get_attendance',
        'get_evaluations',
        'get_assignments',
        'get_student_performance',
        'get_course_performance',
        'compare_courses',
        'get_school_health',
        'get_trend_analysis',
        'get_risk_analysis',
        'get_cause_analysis',
        'get_smart_recommendations',
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
        private DirectorConversationContextService $conversationContext,
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
        $isMutation = (bool) preg_match(
            '/\b(?:crea(?:r|me|lo|los|s)?|cree(?:s)?|agrega(?:r)?|modifica(?:r)?|invita|elimina|borrar|borra|quita(?:r)?|remover|matricula|inscribe|asigna(?:le|lo)?|desmatricula|sincroniza(?:r)?|actualiza|edita|mueve|cambia|aumenta(?:r)?|disminu(?:ye|ir)|incrementa|reduce|cancel[ae]|anula|va a dar)\b/u',
            $value
        ) || (
            (bool) preg_match('/\b(?:profesor|docente|maestro)s?\s+llamad/u', $value)
            && (bool) preg_match('/\b(?:tiene|hay|nuevo|nueva|dara|dará|imparte|enseña)\b/u', $value)
        );

        return $isMutation && ! preg_match('/\b(?:informe|resumen|compara|tendencia|asistencia|rendimiento|promedio)\b/u', $value);
    }

    /**
     * Routing por intención (profesores, alumnos, notas, verificación), no por una frase exacta.
     *
     * @return array{intent:string,agent:string,subject?:string}
     */
    public function detectIntent(string $text): array
    {
        $value = $this->normalized($text);

        if ($this->looksLikeMutation($text)) {
            return ['intent' => 'mutation', 'agent' => 'crud'];
        }

        if ($this->looksLikeTeacherOfStudentQuery($text)) {
            return ['intent' => 'teacher_of_student', 'agent' => 'data_agent'];
        }

        if ($this->looksLikeExistenceVerification($text)) {
            $subject = preg_match('/\b(?:alumno|estudiante)s?\b/u', $value)
                && ! preg_match('/\b(?:profesor|docente|maestro)s?\b/u', $value)
                ? 'student'
                : 'teacher';

            return ['intent' => 'verification', 'agent' => 'data_agent', 'subject' => $subject];
        }

        if ($this->looksLikeTeacherRosterQuery($text)) {
            return ['intent' => 'professors', 'agent' => 'data_agent'];
        }

        if ($this->looksLikeInviteCodeQuery($text)) {
            return ['intent' => 'invite_code', 'agent' => 'data_agent'];
        }

        if (preg_match('/\b(?:alumnos?|estudiantes?)\b/u', $value)
            && ! preg_match('/\b(?:profesores?|docentes?|maestros?)\b/u', $value)
            && (
                preg_match('/\b(?:listado|qui[eé]nes?|cu[aá]ntos|nombres?|como se llama|hay|tenemos|dime|dame)\b/u', $value)
                || $this->looksLikeRosterListQuery($text)
            )) {
            return ['intent' => 'students', 'agent' => 'data_agent'];
        }

        if (preg_match('/\b(?:notas?|calificaciones?|rendimiento|promedio|como va|como van)\b/u', $value)
            && ! $this->looksLikeSchoolNameQuery($text)) {
            return ['intent' => 'grades', 'agent' => 'data_agent'];
        }

        if (preg_match('/\b(?:revisa|verifica|confirm[ae])\b/u', $value)
            && preg_match('/\b(?:profesor|alumno|estudiante|docente)\b/u', $value)) {
            return ['intent' => 'verification', 'agent' => 'data_agent'];
        }

        if (preg_match('/\b(?:informe|reporte|resumen|panorama|como estamos)\b/u', $value)) {
            return ['intent' => 'report', 'agent' => 'data_agent'];
        }

        if ($this->looksLikeSchoolNameQuery($text)
            || $this->looksLikeMostAdvancedCourseQuery($text)
            || $this->looksLikeFollowUp($text)
            || $this->looksLikeConversationalQuery($text)
            || $this->looksLikeAcademicInquiry($text)
            || $this->looksLikeDataQuery($text)
            || $this->isOutOfScope($text)) {
            return ['intent' => 'query', 'agent' => 'data_agent'];
        }

        return ['intent' => 'unknown', 'agent' => 'llm_fallback'];
    }

    /**
     * Decisión de routing (para logs de producción y tests).
     *
     * @return array{
     *   prompt:string,
     *   intent:string,
     *   mutation:bool,
     *   data_query:bool,
     *   follow_up:bool,
     *   conversational:bool,
     *   academic_inquiry:bool,
     *   extracted_grade:?string,
     *   extracted_section:?string,
     *   use_data_agent:bool,
     *   agent:string,
     *   reason:string
     * }
     */
    public function routeDecision(string $text): array
    {
        $detected = $this->detectIntent($text);
        $mutation = ($detected['intent'] ?? '') === 'mutation';
        $data = $this->looksLikeDataQuery($text);
        $follow = $this->looksLikeFollowUp($text);
        $conv = $this->looksLikeConversationalQuery($text);
        $academic = $this->looksLikeAcademicInquiry($text);
        $grade = $this->extractGrade($text);
        $section = $this->extractSection($text);
        $useData = ($detected['agent'] ?? '') === 'data_agent';

        return [
            'prompt' => $text,
            'intent' => $detected['intent'] ?? 'unknown',
            'mutation' => $mutation,
            'data_query' => $data,
            'follow_up' => $follow,
            'conversational' => $conv,
            'academic_inquiry' => $academic,
            'extracted_grade' => $grade,
            'extracted_section' => $section,
            'use_data_agent' => $useData,
            'agent' => $useData ? 'director_data' : 'mutation_interpreter',
            'reason' => $detected['intent'] ?? 'unknown',
        ];
    }

    /**
     * "quiero saber las notas de 2do A", "sus notas", "esos alumnos".
     */
    public function looksLikeAcademicInquiry(string $text): bool
    {
        if ($this->looksLikeMutation($text)) {
            return false;
        }

        $value = $this->normalized($text);

        return (bool) preg_match(
            '/\b(?:notas?|calificaciones?|quiero saber|saber las|saber los|sus notas|sus calificaciones|esos alumnos|esas alumnas|como van esos|como van sus)\b/u',
            $value
        );
    }

    /**
     * Consulta de datos o follow-up: nunca debe caer al menú CRUD.
     */
    public function shouldUseDataAgent(string $text): bool
    {
        return ($this->detectIntent($text)['agent'] ?? '') === 'data_agent';
    }

    public function looksLikeDataQuery(string $text): bool
    {
        if ($this->looksLikeMutation($text)) {
            return false;
        }

        $value = $this->normalized($text);

        return (bool) preg_match(
            '/(?:como va|como van|como le va|como esta|como estan|como estamos|quien|quienes|cuantos|cuantas|que alumnos|que cursos|que profesores|que evaluaciones|que tareas|compara|tendencia|tendencias|evolucion|ranking|informe|resumen|resume|estado academico|asistencia|faltas|promedio|rendimiento|notas|calificaciones|quiero saber|preocup|problemas|atencion|destacado|bajo rendimiento|este mes|esta semana|priorizar|prioridad|mi curso|mi colegio|dame|dime|le va a|evaluaciones|tareas|diagnostico|investiga|empeor|impresion|por que|que tienen en comun|preocupante|en que materia|quien es su profesor|con que profesor|quien (?:le )?da|quien tiene|prepara(?:me)?|nombr|listame|listado|nomina|como se llama|nombre del colegio|curso mas avanzado|grado mas alto|grado mas avanzado|ultimo grado|todos los alumnos|todos los estudiantes|nombre de todos|nombres de los|abecedario|alfabet|a\s*-\s*z|esos alumnos|sus notas)/u',
            $value
        ) || (bool) preg_match('/\btop\s+\d/u', $value)
        || $this->looksLikeFollowUp($text)
        || $this->looksLikeRosterListQuery($text)
        || $this->looksLikeSchoolNameQuery($text)
        || $this->looksLikeMostAdvancedCourseQuery($text)
        || $this->looksLikeConversationalQuery($text);
    }

    public function looksLikeConversationalQuery(string $text): bool
    {
        $value = $this->normalized($text);
        if ($this->looksLikeMutation($text)) {
            return false;
        }

        if (preg_match('/\b(?:ellos|ellas|ese alumno|esa alumna|esos alumnos|esas alumnas|ese curso|esa materia|los de|las de|y los|y las|sus notas|sus calificaciones)\b/u', $value)) {
            return true;
        }

        return (bool) preg_match(
            '/como estamos|que esta pasando|con que profesor|quien (?:le )?da\b|como se llama(?:n)?/u',
            $value
        );
    }

    public function looksLikeFollowUp(string $text): bool
    {
        $value = trim($this->normalized($text), " \t\n\r\0\x0B¿?¡!.");

        return (bool) preg_match(
            '/^(?:por que|y (?:eso|ahora|el|la|los|las|ellos|ellas|su|le)|cual(?: es| de ellos| de ellas)?|en que materia|quien (?:es su profesor|le da|da)|que tienen en comun|el mas preocupante|explica|profundiza|y su profesor|nombr|listame|cuales son|quienes son|dime (?:los|las)|ese alumno|esa alumna|ese curso|ellos|ellas)\b/u',
            $value
        ) || (bool) preg_match('/\b(?:nombr|cuales son|quienes son|dime (?:los )?nombres|ese alumno|esos alumnos|esas alumnas|ese curso|ellos|ellas|sus notas|como van esos|como van sus)\b/u', $value)
        || (bool) preg_match('/^y\s+\p{L}{2,}/u', $value);
    }

    public function looksLikeSchoolNameQuery(string $text): bool
    {
        $value = $this->normalized($text);

        return (bool) preg_match(
            '/como se llama (?:mi |el )?colegio|nombre del colegio|cual es (?:mi |el )?colegio|como se llama mi institucion/u',
            $value
        );
    }

    public function looksLikeMostAdvancedCourseQuery(string $text): bool
    {
        $value = $this->normalized($text);

        return (bool) preg_match(
            '/curso mas avanzado|grado mas (?:alto|avanzado)|curso mas alto|ultimo grado|nivel mas alto|grado mayor/u',
            $value
        );
    }

    public function looksLikeRosterListQuery(string $text): bool
    {
        $value = $this->normalized($text);
        if (preg_match('/\bcuantos\b/u', $value) && ! preg_match('/\b(?:nombr|nombre|list|cuales|quienes)\b/u', $value)) {
            return false;
        }

        return (bool) preg_match(
            '/(?:como se llama(?:n)?|nombre|nombres|nombr|listame|listado|dame (?:el |los )?nombre|muestrame|dime (?:el |los )?(?:nombre|alumnos|estudiantes)).*(?:alumnos|estudiantes|colegio|grado)|(?:todos|toda)s? (?:los )?(?:alumnos|estudiantes)(?:\s|$|\?|del|de todos|en)/u',
            $value
        ) || (bool) preg_match(
            '/(?:alumnos|estudiantes).*(?:todos los grados|del colegio|en general|en la nomina)|quienes son (?:los )?(?:alumnos|estudiantes)|(?:alumnos|estudiantes) (?:de|del)|dime los de|los alumnos de/u',
            $value
        );
    }

    /**
     * @param  array<string,mixed>  $memory
     */
    public function looksLikeRosterFollowUp(string $text, array $memory = []): bool
    {
        $value = $this->normalized($text);
        if ($this->looksLikeRosterListQuery($text)) {
            return false;
        }
        if (! preg_match('/\b(?:nombr|listame|cuales son|quienes son|dime (?:los )?nombres|los nombres)\b/u', $value)) {
            return false;
        }

        $focus = (array) ($memory['focus'] ?? []);

        return ($focus['kind'] ?? '') === 'student_count'
            || isset($focus['students_count'])
            || ($memory['last_intent'] ?? '') === 'query_academic'
            || preg_match('/\b(?:alumnos|estudiantes|nomina)\b/u', $value);
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
        $last = $this->conversationContext->snapshot($memory !== [] ? $memory : null);
        $lastGrade = $last['last_grade'] ?? null;
        $lastSection = $last['last_section'] ?? null;
        $lastStudent = $last['last_student'] ?? null;
        $lastSubject = $last['last_subject'] ?? null;
        $valueNorm = $this->normalized($text);
        $trimNorm = trim($valueNorm, " \t\n\r\0\x0B¿?¡!.");
        $isPureQuienes = in_array($trimNorm, ['quienes', 'quienes son', 'cuales son', 'quienes somos', 'y quienes'], true);
        if ($isPureQuienes || $trimNorm === 'quienes') {
            if ($lastGrade) {
                return $this->pack('get_students', ['grade' => $lastGrade, 'section' => $lastSection]);
            }
            if (($last['last_students'] ?? []) !== []) {
                return $this->pack('get_students', []);
            }

            return [
                'tools' => [],
                'intent' => 'needs_course',
                'clarification' => '¿Sobre qué curso quieres saber? Por ejemplo dime 4to A.',
                'wants_opinion' => false,
            ];
        }
        if (preg_match('/^y\s+(.+)$/u', $trimNorm, $andMatch)) {
            $hint = trim((string) $andMatch[1], " \t¿?¡!.");
            if (! preg_match('/^(?:eso|ahora|el|la|los|las|ellos|ellas|su|le|si|no|los de|las de|ese|esa|esos|esas|este|esta|estos|estas)\b/u', $hint)) {
                $named = $this->matchRememberedStudent($hint, $last);
                if ($named !== null) {
                    return $this->pack('get_student_performance', ['student_name' => $named]);
                }
                $guess = mb_convert_case($hint, MB_CASE_TITLE, 'UTF-8');
                if (mb_strlen($guess) >= 3 && ! $this->extractGrade($text)) {
                    return $this->pack('get_student_performance', ['student_name' => $guess]);
                }
            }
        }
        if (preg_match('/cual.*peor.*(?:promedio|rendimiento|asistencia)|peor promedio|peor rendimiento|cual (?:de ellos )?tiene peor/iu', $valueNorm)) {
            $metric = preg_match('/asistencia|faltas/u', $valueNorm) ? 'absences' : 'average';
            if ($lastGrade || $lastStudent || ($last['last_students'] ?? []) !== []) {
                return $this->pack('get_rankings', [
                    'metric' => $metric,
                    'grade' => $lastGrade,
                    'section' => $lastSection,
                    'limit' => 5,
                    'sort' => $metric === 'average' ? 'avg_asc' : 'absences_desc',
                ]);
            }
        }
        if (preg_match('/^por que$/u', $trimNorm)) {
            $atRisk = (array) (($last['focus']['at_risk'] ?? []));
            if ($atRisk !== [] || ($last['focus']['kind'] ?? '') === 'at_risk') {
                return [
                    'tools' => [],
                    'intent' => 'explain_from_memory',
                    'clarification' => null,
                    'wants_opinion' => true,
                    'focus' => (array) ($last['focus'] ?? []) + ['follow_up' => 'why', 'student_name' => $lastStudent],
                ];
            }
            if ($lastStudent) {
                return $this->pack('get_student_performance', ['student_name' => $lastStudent]);
            }
            if ($lastGrade) {
                return $this->pack('get_at_risk_students', ['grade' => $lastGrade, 'section' => $lastSection]);
            }
        }
        if (preg_match('/en que materia.*peor/u', $valueNorm)) {
            if ($lastStudent) {
                return $this->pack('get_student_performance', ['student_name' => $lastStudent]);
            }
            if ($lastGrade) {
                return $this->pack('get_at_risk_students', ['grade' => $lastGrade, 'section' => $lastSection]);
            }
        }
        if (preg_match('/quien (?:es su profesor|le da)|quien da esa materia|su profesor/u', $valueNorm)) {
            if ($lastStudent) {
                return $this->pack('get_student_performance', array_filter([
                    'student_name' => $lastStudent,
                    'subject_name' => $this->extractSubject($text) ?? $lastSubject,
                ]));
            }
            if ($this->extractSubject($text) ?? $lastSubject) {
                return $this->pack('get_courses', ['subject_name' => $this->extractSubject($text) ?? $lastSubject]);
            }
        }

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

        $planned = $this->planFromText($text, $context, $last);
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

    private function tryLlmPlan(User $director, string $text, array $plan): ?array
    {
        if (app()->runningUnitTests()) {
            return null;
        }
        $key = trim((string) config('services.openai.key'));
        if ($key === '' || str_contains($key, 'your_openai')) {
            return null;
        }
        try {
            $memory = $this->conversationContext->snapshot();
            $memoryBrief = json_encode([
                'last_student' => $memory['last_student'] ?? null,
                'last_students' => array_slice((array) ($memory['last_students'] ?? []), 0, 12),
                'last_grade' => $memory['last_grade'] ?? null,
                'last_section' => $memory['last_section'] ?? null,
                'last_subject' => $memory['last_subject'] ?? null,
                'last_teacher' => $memory['last_teacher'] ?? null,
                'last_intent' => $memory['last_intent'] ?? null,
                'sort' => $memory['sort'] ?? null,
            ], JSON_UNESCAPED_UNICODE);
            $response = \Illuminate\Support\Facades\Http::timeout(12)
                ->withToken($key)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => (string) config('services.openai.director_model', 'gpt-4o-mini'),
                    'temperature' => 0.2,
                    'top_p' => 0.95,
                    'tool_choice' => 'auto',
                    'parallel_tool_calls' => true,
                    'tools' => $this->toolDefinitions(),
                    'messages' => [
                        ['role' => 'system', 'content' => 'Eres el orquestador inteligente de AulaSync para directores. Tu trabajo es decidir SOLO qué tools consultar (no redactar la respuesta final). Piensa como asesor estratégico: identifica la intención real, elige datos mínimos necesarios y evita ejecutar herramientas de más. Reglas: 1) Nunca inventes datos. 2) Resuelve pronombres con el contexto (él, ellos, ese alumno, los de 1ro). 3) Elige 1-4 herramientas como máximo. 4) Si preguntan panorama general, prioriza get_school_health y complementa con get_smart_recommendations. 5) Si preguntan quién necesita atención, prioriza get_risk_analysis y get_smart_recommendations. 6) Si preguntan por qué bajó un curso/alumno, prioriza get_trend_analysis + get_cause_analysis. 7) colegio_id siempre lo pone el backend.'],
                        ['role' => 'user', 'content' => "Pregunta: {$text}\nContexto conversacional: {$memoryBrief}"],
                    ],
                ]);
            if ($response->failed()) {
                return null;
            }
            $msg = $response->json('choices.0.message', []);
            $calls = $msg['tool_calls'] ?? [];
            if (empty($calls)) {
                return null;
            }
            $tools = [];
            foreach ($calls as $call) {
                $name = $call['function']['name'] ?? data_get($call, 'function.name', '');
                $argsRaw = $call['function']['arguments'] ?? data_get($call, 'function.arguments', '{}');
                $args = is_string($argsRaw) ? json_decode($argsRaw, true) : (is_array($argsRaw) ? $argsRaw : []);
                if (! is_array($args) || ! self::isDataTool((string) $name)) {
                    continue;
                }
                $tools[] = ['tool' => (string) $name, 'args' => $this->sanitizeArgs($args)];
            }
            if ($tools === []) {
                return null;
            }
            return [
                'tools' => $tools,
                'intent' => $tools[0]['tool'],
                'clarification' => null,
                'wants_opinion' => false,
            ];
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('DirectorDataAgent LLM fallback failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Redacción final con LLM. Nunca inventa: solo usa resultados de tools.
     * En tests unitarios se omite para no hacer las pruebas no deterministas.
     *
     * @param  array<int,array{action_type?:string,message?:string,data?:array}>  $actions
     */
    private function tryLlmCompose(User $director, string $text, array $actions, string $intent): ?string
    {
        if (app()->runningUnitTests()) {
            return null;
        }
        $key = trim((string) config('services.openai.key'));
        if ($key === '' || str_contains($key, 'your_openai')) {
            return null;
        }

        $memory = $this->conversationContext->snapshot();
        $facts = $this->factsForLlm($actions);
        $style = $this->wantsStrategicAdvisory($text) || $intent === 'diagnose_school'
            ? ($this->wantsExecutiveReport($text) || $this->wantsStructuredFormat($text) ? 'informe' : 'panorama')
            : 'factual';

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(12)
                ->withToken($key)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => (string) config('services.openai.director_model', 'gpt-4o-mini'),
                    'temperature' => 0.3,
                    'max_tokens' => $style === 'informe' ? 700 : ($style === 'factual' ? 90 : 350),
                    'messages' => [
                        ['role' => 'system', 'content' => $this->synthesisSystemPrompt($style)],
                        ['role' => 'user', 'content' => json_encode([
                            'pregunta' => $text,
                            'contexto' => [
                                'last_student' => $memory['last_student'] ?? null,
                                'last_students' => array_slice((array) ($memory['last_students'] ?? []), 0, 12),
                                'last_grade' => $memory['last_grade'] ?? null,
                                'last_section' => $memory['last_section'] ?? null,
                                'last_subject' => $memory['last_subject'] ?? null,
                                'last_teacher' => $memory['last_teacher'] ?? null,
                            ],
                            'resultados' => $facts,
                        ], JSON_UNESCAPED_UNICODE)],
                    ],
                ]);
            if ($response->failed()) {
                return null;
            }
            $content = trim((string) $response->json('choices.0.message.content', ''));
            if ($content === '' || $this->llmMentionsUnknownEntities($content, $actions)) {
                return null;
            }

            return $content;
        } catch (\Throwable $e) {
            Log::warning('DirectorDataAgent LLM compose failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function synthesisSystemPrompt(string $style): string
    {
        $format = match ($style) {
            'informe' => 'Estructura de informe ejecutivo con subtítulos, riesgos y plan de acción.',
            'panorama' => 'Respuesta tipo panorama del colegio, orientada a toma de decisiones.',
            'factual' => 'factual_lookup_mode: 1 o 2 oraciones, solo el dato pedido. Sin recomendaciones ni cierre estratégico.',
            default => 'Respuesta conversacional natural, breve cuando aplique y accionable cuando haya riesgos.',
        };

        $roleLine = $style === 'factual'
            ? 'Estás en factual_lookup_mode: responde SOLO el dato pedido, en 1 o 2 oraciones. PROHIBIDO recomendaciones, hipótesis, riesgos, oportunidades o análisis de marketing.'
            : 'Estás en strategic_advisory_mode: puedes interpretar patrones y recomendar porque el director pidió análisis, diagnóstico, informe, recomendaciones o la salud del colegio.';

        return <<<PROMPT
Eres "AulaSync", asistente inteligente del director escolar. Redactas la respuesta final en español.

{$roleLine}

Reglas:
1. Nunca inventes alumnos, notas, profesores, cursos, asistencia ni ningún dato.
2. Usa únicamente los resultados de herramientas que se te entregan.
3. Si un dato no existe, di que no está disponible.
4. No menciones tools, SQL, backend, agentes ni arquitectura interna.
5. No digas que los números son registros reales ni menciones verificaciones internas.
6. En factual_lookup_mode no interpretes ni agregues "siguiente paso". En strategic_advisory_mode sí puedes interpretar.
7. Conecta patrones SOLO en strategic_advisory_mode (notas, asistencia, tendencia, riesgo) sin afirmar causalidad absoluta.
8. Si detectas riesgo, incluye recomendación concreta SOLO en strategic_advisory_mode.
9. Si el usuario pregunta "qué hacer", entrega acciones priorizadas y justificadas.
10. Si el usuario pregunta "por qué", entrega hipótesis basadas en datos y explícitalas como hipótesis.
11. Mantén tono natural, cálido y profesional, como conversación con un director.
12. Sé ultra-conciso en preguntas simples (factual_lookup_mode) y más detallado solo en diagnósticos e informes.
13. Evita repetir plantillas rígidas; adapta la respuesta a la intención.
14. Cierra con pregunta de seguimiento SOLO en strategic_advisory_mode.
15. {$format}

Ejemplo de estilo esperado para panorama:
"Panorama general: el promedio está bajando y eso merece atención esta semana. Lo más urgente es revisar 2do A y contactar a las familias de los alumnos en riesgo. Si quieres, te preparo una agenda de acciones por prioridad."

Ejemplo de estilo esperado para riesgo:
"Identifico alumnos en riesgo por combinación de promedio bajo y ausencias. Recomendación alta: reunión con padres en los casos críticos; recomendación media: refuerzo focalizado en la materia más débil."

Ejemplo de estilo esperado para causa:
"Con los datos actuales, la caída parece asociarse a una baja reciente de notas junto con faltas. Es una hipótesis, no una certeza. Sugiero validar con el docente y ajustar plan de apoyo esta semana."
PROMPT;
    }

    /**
     * @param  array<int,array{action_type?:string,message?:string,data?:array}>  $actions
     * @return array<int,array{tool:string,message:string,data:array}>
     */
    private function factsForLlm(array $actions): array
    {
        return collect($actions)->map(function ($action) {
            return [
                'tool' => (string) ($action['action_type'] ?? ''),
                'message' => $this->stripTechnical($this->withoutTable((string) ($action['message'] ?? ''))),
                'data' => $this->publicFactData((array) ($action['data'] ?? [])),
            ];
        })->all();
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    private function publicFactData(array $data): array
    {
        unset($data['colegio_id'], $data['school_id'], $data['tenant_id'], $data['id'], $data['email'], $data['family_code'], $data['teacher_id'], $data['student_id'], $data['course_id']);
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->publicFactData($value);
            }
        }

        return $data;
    }

    /**
     * @param  array<int,array{data?:array,message?:string}>  $actions
     */
    private function llmMentionsUnknownEntities(string $reply, array $actions): bool
    {
        $known = collect($actions)->flatMap(function ($action) {
            $data = (array) ($action['data'] ?? []);
            $names = [];
            foreach (['student', 'school_name', 'worst_subject', 'worst_teacher', 'teacher_name'] as $key) {
                if (is_string($data[$key] ?? null)) {
                    $names[] = $data[$key];
                }
            }
            foreach (['students', 'ranking', 'teachers', 'courses'] as $listKey) {
                foreach ((array) ($data[$listKey] ?? []) as $row) {
                    if (is_array($row) && isset($row['name'])) {
                        $names[] = $row['name'];
                    } elseif (is_object($row) && isset($row->name)) {
                        $names[] = $row->name;
                    } elseif (is_string($row)) {
                        $names[] = $row;
                    }
                }
            }

            return $names;
        })->filter()->map(fn ($name) => mb_strtolower((string) $name))->unique()->values();

        if ($known->isEmpty()) {
            return false;
        }

        preg_match_all('/\b[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+(?:\s+[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+)+\b/u', $reply, $matches);
        foreach ($matches[0] as $candidate) {
            $needle = mb_strtolower($candidate);
            if (! $known->contains(fn ($name) => str_contains($name, $needle) || str_contains($needle, $name))) {
                if (! preg_match('/\b(?:colegio|matematica|ingles|lenguaje|ciencias)\b/u', $this->normalized($candidate))) {
                    return true;
                }
            }
        }

        return false;
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
        $trace = [];
        $trace[] = ['event' => 'query_received', 'at' => now()->toIso8601String(), 'duration_ms' => 0];
        $trace[] = ['event' => 'context_resolved', 'at' => now()->toIso8601String(), 'colegio_id' => $director->colegio_id, 'role' => $director->role];
        $planner = isset($plan['planner']) ? $plan['planner'] : 'deterministic';
        $trace[] = ['event' => 'planner_used', 'at' => now()->toIso8601String(), 'planner' => $planner, 'intent' => $plan['intent'] ?? 'unknown', 'tools' => array_column($plan['tools'] ?? [], 'tool')];

        // ── Cost control por colegio (100 consultas/día, 30/min ya via throttle) ──
        $costKey = 'director_data_cost:'.(int) $director->colegio_id.':'.now()->format('Y-m-d');
        $dailyCount = (int) Cache::get($costKey, 0);
        if ($dailyCount >= 100) {
            $trace[] = ['event' => 'rate_limited', 'at' => now()->toIso8601String()];
            $this->record($director, $plan['intent'] ?? 'rate_limited', [], 'failed', $started, $sessionId, 'daily_limit_exceeded', $text);
            return [
                'success' => false,
                'needs_clarification' => false,
                'message' => 'Has alcanzado el límite diario de consultas de datos (100/día por colegio). Intenta mañana o contacta a soporte.',
                'actions' => [],
                'intent' => 'rate_limited',
                'tools' => [],
                'duration_ms' => $this->elapsedMs($started),
                'trace' => $trace,
                'timeline' => $this->timelineFromTools([]),
            ];
        }
        Cache::put($costKey, $dailyCount + 1, now()->endOfDay());

        if (($plan['intent'] ?? '') === 'explain_from_memory') {
            $trace[] = ['event' => 'synthesis_started', 'at' => now()->toIso8601String()];
            $composed = $this->composeFollowUp($text, (array) ($plan['focus'] ?? []));
            $trace[] = ['event' => 'response_generated', 'at' => now()->toIso8601String(), 'duration_ms' => $this->elapsedMs($started)];
            $this->record($director, 'explain_from_memory', [], 'success', $started, $sessionId, null, $text);

            return [
                'success' => true,
                'needs_clarification' => false,
                'message' => $composed,
                'actions' => [],
                'intent' => 'explain_from_memory',
                'tools' => [],
                'duration_ms' => $this->elapsedMs($started),
                'trace' => $trace,
                'timeline' => $this->timelineFromTools([]),
                'focus' => $plan['focus'] ?? [],
            ];
        }

        // Fase 3: Fallback LLM si plan local no es confiable (baja confianza)
        if (! $this->localPlanIsReady($plan)) {
            $llmPlan = $this->tryLlmPlan($director, $text, $plan);
            if ($llmPlan !== null && ! empty($llmPlan['tools'])) {
                $plan = $llmPlan;
            }
        }

        if (($plan['clarification'] ?? null) && ($plan['tools'] ?? []) === []) {
            $trace[] = ['event' => 'needs_clarification', 'at' => now()->toIso8601String(), 'clarification' => $plan['clarification']];
            $this->record($director, $plan['intent'] ?? 'clarify', [], 'unresolved', $started, $sessionId, 'needs_clarification', $text);

            return [
                'success' => false,
                'needs_clarification' => true,
                'message' => (string) $plan['clarification'],
                'actions' => [],
                'intent' => (string) ($plan['intent'] ?? 'clarify'),
                'tools' => [],
                'duration_ms' => $this->elapsedMs($started),
                'trace' => $trace,
                'timeline' => $this->timelineFromTools([]),
            ];
        }

        $actions = [];
        $used = [];
        $knownStudent = null;
        $ranAtRisk = false;
        $trace[] = ['event' => 'planner_used', 'at' => now()->toIso8601String(), 'tools' => array_column($plan['tools'] ?? [], 'tool')];
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
            $trace[] = ['event' => 'tool_started', 'at' => now()->toIso8601String(), 'tool' => $tool];
            try {
                $result = $this->execute($director, $tool, $args, $legacyQuery);
                $trace[] = ['event' => 'tool_completed', 'at' => now()->toIso8601String(), 'tool' => $tool];
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
                $trace[] = ['event' => 'tool_failed', 'at' => now()->toIso8601String(), 'tool' => $tool, 'error' => $this->errorCode($e)];
                Log::error('Director data agent tool failed', [
                    'director_id' => $director->id,
                    'colegio_id' => $director->colegio_id,
                    'tool' => $tool,
                    'error' => $e->getMessage(),
                ]);
                $this->record($director, $plan['intent'] ?? $tool, $used, 'failed', $started, $sessionId, $this->errorCode($e), $text);

                $trace[] = ['event' => 'synthesis_started', 'at' => now()->toIso8601String()];
                $trace[] = ['event' => 'response_generated', 'at' => now()->toIso8601String(), 'duration_ms' => $this->elapsedMs($started)];
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
                    'trace' => $trace,
                    'timeline' => $this->timelineFromTools($used),
                ];
            }
        }

        $trace[] = ['event' => 'synthesis_started', 'at' => now()->toIso8601String()];
        $intent = (string) ($plan['intent'] ?? ($used[0] ?? 'query'));
        $composed = $this->tryLlmCompose($director, $text, $actions, $intent)
            ?? $this->compose($text, $actions, (bool) ($plan['wants_opinion'] ?? false), $intent);
        $trace[] = ['event' => 'response_generated', 'at' => now()->toIso8601String(), 'duration_ms' => $this->elapsedMs($started)];
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
            'trace' => $trace,
            'timeline' => $this->timelineFromTools($used),
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
                $this->str($args, 'sort'),
            ),
            'get_student' => $this->analytics->getStudent($colegioId, (string) ($args['student_name'] ?? '')),
            'get_courses' => $this->analytics->getCourses(
                $colegioId,
                $this->str($args, 'grade'),
                $this->str($args, 'section'),
                $this->str($args, 'subject_name') ?? $this->str($args, 'subject'),
            ),
            'get_teachers' => $this->analytics->getTeachers($colegioId),
            'get_teacher_invite_code' => $this->analytics->getTeacherInviteCode(
                $colegioId,
                (string) ($args['teacher_name'] ?? ''),
            ),
            'verify_teacher' => $this->analytics->verifyTeacher($colegioId, (string) ($args['teacher_name'] ?? $args['name'] ?? '')),
            'verify_student' => $this->analytics->verifyStudent($colegioId, (string) ($args['student_name'] ?? $args['name'] ?? '')),
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
            'get_school_health' => $this->analytics->getSchoolHealth($colegioId),
            'get_trend_analysis' => $this->analytics->getTrendAnalysis(
                $colegioId,
                $this->str($args, 'grade'),
                $this->str($args, 'section'),
                (int) ($args['weeks'] ?? 4),
            ),
            'get_risk_analysis' => $this->analytics->getRiskAnalysis(
                $colegioId,
                $this->str($args, 'grade'),
                $this->str($args, 'section'),
            ),
            'get_cause_analysis' => $this->analytics->getCauseAnalysis(
                $colegioId,
                (string) ($args['grade'] ?? ''),
                $this->str($args, 'section'),
                $this->str($args, 'student_name'),
            ),
            'get_smart_recommendations' => $this->analytics->getSmartRecommendations(
                $colegioId,
                $this->str($args, 'grade'),
                $this->str($args, 'section'),
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
                $this->str($args, 'sort'),
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
            return 'No hay datos suficientes en tu colegio para responder esa consulta. Cuando existan notas o asistencia podré señalar riesgos.';
        }

        if ($this->isSimpleCountQuery($text, $actions) || $this->isSimpleLookup($text, $actions)) {
            $msg = trim((string) $facts->first());
            if (preg_match('/Hay\s+\d+\s+alumnos?\b[^.()]*\.?/iu', $msg, $m)) {
                $countLine = rtrim(trim($m[0]), '.').'.';
                if ($this->extractSort($text)) {
                    return $this->withoutTable($msg);
                }

                return $countLine;
            }
            if (preg_match('/Tu colegio se llama\b.+/iu', $msg, $m)) {
                return rtrim(trim($m[0]), '.').'.';
            }

            return $this->withoutTable($msg);
        }

        if ($intent === 'executive_report' || $this->wantsExecutiveReport($text) || $this->wantsStructuredFormat($text)) {
            return $this->composeExecutiveReport($text, $actions);
        }
        if ($intent === 'diagnose_school' || $this->wantsDiagnosis($text)) {
            return $this->composeDiagnosis($actions);
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
        $analysis = $wantsOpinion ? $this->analysisFromFacts($text, $actions, true, $hasEmpty) : null;

        $out = trim($conclusion);
        if ($body !== '' && ! str_contains($out, $body)) {
            $out .= ($out !== '' ? "\n\n" : '').$body;
        }
        if ($analysis !== null && $analysis !== '' && $this->wantsOpinion($text)) {
            $out .= "\n\n".$analysis;
        }

        return $out;
    }

    private function isSimpleCountQuery(string $text, array $actions): bool
    {
        $value = $this->normalized($text);
        if ($this->extractSort($text) || $this->extractGrade($text)) {
            return false;
        }
        if (! preg_match('/\bcuantos\s+(?:alumnos|estudiantes|profesores|cursos)\b/u', $value)) {
            return false;
        }
        if (preg_match('/\b(?:tenemos|hay|tiene el colegio|hay en)\b/u', $value) && count($actions) === 1) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<int,array{action_type?:string,data?:array}>  $actions
     */
    private function isSimpleLookup(string $text, array $actions): bool
    {
        if (count($actions) !== 1) {
            return false;
        }
        $tool = (string) ($actions[0]['action_type'] ?? '');
        if ($this->wantsStructuredFormat($text) || $this->wantsDiagnosis($text) || $this->wantsExecutiveReport($text)) {
            return false;
        }

        return in_array($tool, ['query_academic', 'get_student', 'get_students', 'get_teachers', 'get_courses', 'verify_teacher', 'verify_student'], true)
            || $this->looksLikeSchoolNameQuery($text);
    }

    private function wantsStructuredFormat(string $text): bool
    {
        $value = $this->normalized($text);

        return (bool) preg_match('/\b(?:informe|reporte|resumen ejecutivo|analisis detallado|para la reunion)\b/u', $value);
    }

    private function wantsStrategicAdvisory(string $text): bool
    {
        $value = $this->normalized($text);

        return $this->wantsExecutiveReport($text)
            || $this->wantsStructuredFormat($text)
            || $this->wantsDiagnosis($text)
            || (bool) preg_match('/\b(?:analisis|diagnostico|informe|reporte|recomendacion(?:es)?|salud del colegio|panorama|oportunidades|que (?:debo |deberia )?hacer)\b/u', $value);
    }

    private function looksLikeTeacherOfStudentQuery(string $text): bool
    {
        $value = $this->normalized($text);

        return (bool) preg_match(
            '/(?:con que profesor|que profesor (?:le |lo )?da|quien (?:le )?da|profesor de|esta(?:n)? con (?:el )?profesor)/u',
            $value
        );
    }

    public function looksLikeTeacherRosterQuery(string $text): bool
    {
        $value = $this->normalized($text);
        if ($this->looksLikeMutation($text) || $this->looksLikeTeacherOfStudentQuery($text)) {
            return false;
        }

        return (bool) preg_match(
            '/(?:como se llama(?:n)?|nombres? de|listado de|listame|quienes(?: son)?|cuales son|que|dime|dame|muestrame)\s+(?:los )?(?:profesores|docentes|maestros)\b/u',
            $value
        ) || (bool) preg_match(
            '/\b(?:profesores|docentes|maestros)\s+(?:hay|tenemos|tengo|estan|del colegio|de mi colegio)\b/u',
            $value
        ) || (bool) preg_match(
            '/\bquien (?:enseña|imparte)\b/u',
            $value
        );
    }

    private function looksLikeInviteCodeQuery(string $text): bool
    {
        $value = $this->normalized($text);
        if ($this->looksLikeMutation($text)) {
            return false;
        }

        if (! preg_match('/\b(?:codigo|cod(?:igo)?|doc-?|invitaci[oó]n)\b/u', $value)) {
            return false;
        }

        return (bool) preg_match('/\b(?:profesor|docente|maestro)\b/u', $value)
            || (bool) preg_match('/\b(?:de|del)\s+[a-záéíóúñ]+(?:\s+[a-záéíóúñ]+){0,2}\b/u', $value)
            || str_contains($value, 'doc-');
    }

    private function extractTeacherNameForInviteCode(string $text): ?string
    {
        if (preg_match('/(?:codigo|invitaci[oó]n)\s+(?:de|del)\s+(?:profesor|docente|maestro)?\s*([a-záéíóúñ]+(?:\s+[a-záéíóúñ]+){0,3})/iu', $text, $m)) {
            return $this->titlePersonName((string) $m[1]);
        }
        if (preg_match('/(?:profesor|docente|maestro)\s+([a-záéíóúñ]+(?:\s+[a-záéíóúñ]+){0,3})/iu', $text, $m)) {
            return $this->titlePersonName((string) $m[1]);
        }

        return null;
    }

    public function looksLikeExistenceVerification(string $text): bool
    {
        $value = trim($this->normalized($text), " \t\n\r\0\x0B¿?¡!.");
        if ($this->looksLikeMutation($text) || $this->looksLikeTeacherOfStudentQuery($text)) {
            return false;
        }
        if (preg_match('/^(?:quien|quienes|cual|cuales|como|cuantos|cuantas)\b/u', $value)) {
            return false;
        }

        $mentionsRole = (bool) preg_match('/\b(?:profesor|docente|maestro|alumno|estudiante)s?\b/u', $value);
        if (! $mentionsRole) {
            return false;
        }

        if (preg_match('/\b(?:revisa|verifica|confirm[ae]|chequea|comprueba|revisame|verificame)\b/u', $value)) {
            return true;
        }

        return (bool) preg_match('/\bes (?:un |una )?(?:profesor|docente|maestro|alumno|estudiante)\b/u', $value);
    }

    public function extractPersonToVerify(string $text): ?string
    {
        $value = trim($this->normalized($text), " \t\n\r\0\x0B¿?¡!.");
        if (preg_match('/(?:revisa|verifica|confirm[ae]|chequea|comprueba|revisame|verificame)(?:\s+si)?\s+(.+?)\s+es (?:un |el |una |la )?(?:profesor|docente|maestro|alumno|estudiante)/u', $value, $m)) {
            return $this->titlePersonName((string) $m[1]);
        }
        if (preg_match('/^(.+?)\s+es (?:un |el |una |la )?(?:profesor|docente|maestro|alumno|estudiante)\b/u', $value, $m)) {
            return $this->titlePersonName((string) $m[1]);
        }

        return null;
    }

    private function titlePersonName(string $raw): ?string
    {
        $raw = trim(preg_replace('/\b(?:si|que|el|la|los|las|al|a|un|una|me)\b/u', ' ', $this->normalized($raw)) ?? $raw);
        $raw = trim(preg_replace('/\s+/u', ' ', $raw) ?? $raw);
        if ($raw === '' || preg_match('/^(?:profesor|docente|maestro|alumno|estudiante)s?$/u', $raw)) {
            return null;
        }
        $parts = array_map(fn ($p) => mb_convert_case($p, MB_CASE_TITLE, 'UTF-8'), explode(' ', $raw));

        return implode(' ', $parts);
    }

    private function looksLikeGradeRosterFollowUp(string $text): bool
    {
        $value = $this->normalized($text);

        $value = trim($this->normalized($text), " \t\n\r\0\x0B¿?¡!.");

        return (bool) preg_match(
            '/^(?:y |entonces )?(?:dime )?(?:los|las|alumnos|estudiantes) de\b/u',
            $value
        );
    }

    public function extractSort(string $text): ?string
    {
        $value = $this->normalized($text);
        if (preg_match('/z\s*-\s*a|de la z|al reves|descendente/u', $value)) {
            return 'name_desc';
        }
        if (preg_match('/abecedario|alfabet|a\s*-\s*z|de la a|inicio del abecedario|orden(?:ados?)? (?:alfabet|por nombre)/u', $value)) {
            return 'name_asc';
        }
        if (preg_match('/menor promedio|peor promedio|peor rendimiento/u', $value) && preg_match('/\b(?:orden|primero|empieza)\b/u', $value)) {
            return 'avg_asc';
        }
        if (preg_match('/mayor promedio|mejor promedio/u', $value) && preg_match('/\b(?:orden|primero|empieza)\b/u', $value)) {
            return 'avg_desc';
        }
        if (preg_match('/mas faltas/u', $value) && preg_match('/\b(?:orden|primero|empieza)\b/u', $value)) {
            return 'absences_desc';
        }
        if (preg_match('/menos faltas/u', $value) && preg_match('/\b(?:orden|primero|empieza)\b/u', $value)) {
            return 'absences_asc';
        }

        return null;
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
            if (isset($data['school_name']) && is_string($data['school_name'])) {
                return "Tu colegio se llama {$data['school_name']}.";
            }
            if (isset($data['top_grade']) && is_string($data['top_grade'])) {
                return "El grado más avanzado registrado es {$data['top_grade']}.";
            }
            if ($tool === 'get_students' && isset($data['students']) && is_countable($data['students'])) {
                $count = count($data['students']);

                return $count === 1 ? 'Hay 1 alumno en la lista.' : "Hay {$count} alumnos en la lista.";
            }
            if (isset($data['students_count'])) {
                return "Hay {$data['students_count']} alumno(s) en la nómina del colegio.";
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

            $line = $names->isEmpty()
                ? $this->stripTechnical($this->withoutTable($message))
                : $prefix.$names->implode(', ').$extra.'.';
            $rosterCount = (int) ($data['students_count'] ?? 0);
            if ($tool === 'get_course_performance' && $rosterCount > 0) {
                $line = "Hay {$rosterCount} alumno(s). ".$line;
            }
            $missing = collect($data['students_without_grades'] ?? [])
                ->map(fn ($row) => is_string($row) ? $row : (is_array($row) ? ($row['name'] ?? '') : ($row->name ?? '')))
                ->filter()
                ->unique()
                ->values();
            if ($missing->isNotEmpty()) {
                $line .= ' Sin notas: '.$missing->implode(', ').'.';
            }

            return $line;
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

        if ($tool === 'get_students' && isset($data['students']) && is_countable($data['students'])) {
            $lines = collect($data['students'])->map(function ($row) {
                $name = is_array($row) ? ($row['name'] ?? '') : ($row->name ?? '');
                $grade = is_array($row) ? ($row['grade'] ?? '') : ($row->grade ?? '');
                $section = is_array($row) ? ($row['section'] ?? '') : ($row->section ?? '');
                $scope = trim($grade.($section ? ' / '.$section : ''));

                return $name !== '' ? ($scope !== '' ? "{$name} ({$scope})" : $name) : null;
            })->filter()->values();

            return $lines->isEmpty()
                ? $this->stripTechnical($this->withoutTable($message))
                : 'Alumnos: '.$lines->implode('; ').'.';
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
            'get_students' => ['Listar alumnos del colegio del director.', ['grade', 'section', 'sort']],
            'get_student' => ['Obtener un alumno por nombre.', ['student_name']],
            'get_courses' => ['Listar cursos del colegio.', ['grade', 'section', 'subject_name']],
            'get_teachers' => ['Listar profesores del colegio.', []],
            'get_teacher_invite_code' => ['Obtener el código DOC- de invitación de un profesor específico.', ['teacher_name']],
            'verify_teacher' => ['Verificar si una persona está registrada como profesor.', ['teacher_name']],
            'verify_student' => ['Verificar si una persona está registrada como alumno.', ['student_name']],
            'get_grades' => ['Listar calificaciones reales.', ['grade', 'section', 'subject_name', 'student_name']],
            'get_attendance' => ['Consultar asistencia y faltas.', ['grade', 'section', 'student_name', 'days']],
            'get_evaluations' => ['Listar evaluaciones registradas.', ['grade', 'section', 'subject_name']],
            'get_assignments' => ['Listar tareas registradas.', ['grade', 'section', 'subject_name']],
            'get_student_performance' => ['Rendimiento de un alumno.', ['student_name']],
            'get_course_performance' => ['Rendimiento de un grado/sección.', ['grade', 'section', 'subject_name']],
            'compare_courses' => ['Comparar dos cursos o grados.', ['grade', 'grade_b', 'section', 'section_b', 'subject_name']],
            'get_school_health' => ['Panorama general del colegio con métricas clave.', []],
            'get_trend_analysis' => ['Análisis de evolución temporal por curso o colegio.', ['grade', 'section', 'weeks']],
            'get_risk_analysis' => ['Análisis de alumnos en riesgo por notas, caída y faltas.', ['grade', 'section']],
            'get_cause_analysis' => ['Hipótesis de causa para caídas de rendimiento.', ['grade', 'section', 'student_name']],
            'get_smart_recommendations' => ['Recomendaciones accionables priorizadas según los datos.', ['grade', 'section']],
            'get_at_risk_students' => ['Alumnos con bajo rendimiento.', ['grade', 'section', 'subject_name']],
            'get_declining_students' => ['Alumnos que bajaron su promedio.', ['grade', 'section']],
            'get_academic_trends' => ['Tendencias de notas o faltas.', ['metric', 'weeks']],
            'generate_school_report' => ['Informe académico del colegio o un curso.', ['grade', 'section']],
            'get_rankings' => ['Ranking por promedio o faltas.', ['metric', 'grade', 'section', 'limit', 'sort']],
        ];

        return collect($defs)->map(function ($definition, $name) use ($commonFilters) {
            [$description, $keys] = $definition;
            $properties = [];
            foreach ($keys as $key) {
                $properties[$key] = $commonFilters[$key] ?? match ($key) {
                    'days', 'weeks', 'limit' => ['type' => ['integer', 'null']],
                    'metric' => ['type' => ['string', 'null'], 'enum' => ['average', 'absences', null]],
                    'sort' => ['type' => ['string', 'null'], 'enum' => ['name_asc', 'name_desc', 'avg_asc', 'avg_desc', 'absences_asc', 'absences_desc', null]],
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
        $refersToThose = (bool) preg_match('/\b(?:esos|esas|ellos|ellas|sus notas|sus calificaciones)\b/u', $value);
        if ($grade === null && $refersToThose) {
            $grade = $memory['last_grade'] ?? $memory['grades'][0] ?? null;
            $section = $section ?: ($memory['last_section'] ?? $memory['section'] ?? null);
        }

        $followUp = $this->planFollowUp($text, $memory);
        if ($followUp !== null) {
            return $followUp;
        }

        $detected = $this->detectIntent($text);
        if (($detected['intent'] ?? '') === 'professors') {
            return $this->pack('get_teachers', []);
        }
        if (($detected['intent'] ?? '') === 'invite_code') {
            $teacher = $this->extractTeacherNameForInviteCode($text);
            if (! $teacher) {
                return [
                    'tools' => [],
                    'intent' => 'needs_teacher_name',
                    'clarification' => '¿De qué profesor necesitas el código de invitación?',
                    'wants_opinion' => false,
                ];
            }

            return $this->pack('get_teacher_invite_code', ['teacher_name' => $teacher]);
        }
        if (($detected['intent'] ?? '') === 'verification') {
            $person = $this->extractPersonToVerify($text);
            if ($person === null || $person === '') {
                return [
                    'tools' => [],
                    'intent' => 'needs_name',
                    'clarification' => '¿A quién quieres verificar? Dime el nombre completo.',
                    'wants_opinion' => false,
                ];
            }
            if (($detected['subject'] ?? 'teacher') === 'student') {
                return $this->pack('verify_student', ['student_name' => $person]);
            }

            return $this->pack('verify_teacher', ['teacher_name' => $person]);
        }

        $sort = $this->extractSort($text);
        $teacherQuestion = $this->looksLikeTeacherOfStudentQuery($text);
        if ($teacherQuestion) {
            $name = $this->extractStudentName($text)
                ?: ($memory['last_student'] ?? $memory['student_name'] ?? null);
            if (is_string($name) && $name !== '') {
                return $this->pack('get_student', ['student_name' => $name]);
            }
            if ($subject) {
                return $this->pack('get_courses', ['subject_name' => $subject, 'grade' => $grade, 'section' => $section]);
            }
        }

        if ($this->looksLikeGradeRosterFollowUp($text) && $grade) {
            return $this->pack('get_students', array_filter([
                'grade' => $grade,
                'section' => $section,
                'sort' => $sort,
            ]));
        }

        if ($this->looksLikeSchoolNameQuery($text)) {
            return $this->pack('query_academic', ['query_type' => 'school_info']);
        }

        if ($this->looksLikeMostAdvancedCourseQuery($text)) {
            return $this->pack('query_academic', ['query_type' => 'most_advanced_course']);
        }

        if ($this->looksLikeRosterListQuery($text)) {
            return $this->pack('get_students', array_filter([
                'grade' => $grade,
                'section' => $section,
                'sort' => $sort,
            ]));
        }

        if (preg_match('/\b(?:notas|calificaciones)\b/u', $value)) {
            $scopeGrade = $grade ?: ($memory['last_grade'] ?? null);
            $scopeSection = $section ?: ($memory['last_section'] ?? null);
            if ($scopeGrade) {
                return $this->pack('get_course_performance', array_filter([
                    'grade' => $scopeGrade,
                    'section' => $scopeSection,
                    'subject_name' => $subject,
                ]));
            }
            $names = (array) ($memory['last_students'] ?? $memory['student_names'] ?? []);
            if ($names !== []) {
                return $this->pack('get_course_performance', array_filter([
                    'grade' => $memory['last_grade'] ?? null,
                    'section' => $memory['last_section'] ?? null,
                ]));
            }
        }

        if ($this->wantsDiagnosis($text)) {
            return [
                'tools' => [['tool' => 'generate_school_report', 'args' => []]],
                'intent' => 'diagnose_school',
                'clarification' => null,
                'wants_opinion' => true,
            ];
        }

        if (preg_match('/\b(?:salud\s+del\s+colegio|panorama\s+general|estado\s+general)\b/u', $value)) {
            return [
                'tools' => [
                    ['tool' => 'get_school_health', 'args' => []],
                    ['tool' => 'get_smart_recommendations', 'args' => []],
                ],
                'intent' => 'school_health',
                'clarification' => null,
                'wants_opinion' => true,
            ];
        }

        if (preg_match('/\b(?:priorizar|prioridad|que debo hacer esta semana|esta semana)\b/u', $value)) {
            return [
                'tools' => [
                    ['tool' => 'get_school_health', 'args' => []],
                    ['tool' => 'get_risk_analysis', 'args' => array_filter(['grade' => $grade, 'section' => $section])],
                    ['tool' => 'get_smart_recommendations', 'args' => array_filter(['grade' => $grade, 'section' => $section])],
                ],
                'intent' => 'weekly_priorities',
                'clarification' => null,
                'wants_opinion' => true,
            ];
        }

        if (preg_match('/\bpor\s+que\b.*\b(?:bajo|bajo|baj[oó]|cayo|cay[oó]|empeor)\b/u', $value) && $grade) {
            return [
                'tools' => [
                    ['tool' => 'get_trend_analysis', 'args' => array_filter(['grade' => $grade, 'section' => $section, 'weeks' => 8])],
                    ['tool' => 'get_cause_analysis', 'args' => array_filter(['grade' => $grade, 'section' => $section, 'student_name' => $this->extractStudentName($text)])],
                    ['tool' => 'get_smart_recommendations', 'args' => array_filter(['grade' => $grade, 'section' => $section])],
                ],
                'intent' => 'cause_analysis',
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
                    ['tool' => 'get_risk_analysis', 'args' => array_filter(['grade' => $grade, 'section' => $section])],
                    ['tool' => 'get_smart_recommendations', 'args' => array_filter(['grade' => $grade, 'section' => $section])],
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
        if (preg_match('/cuantos (?:alumnos|estudiantes)/u', $value) && $sort) {
            return $this->pack('get_students', array_filter([
                'grade' => $grade,
                'section' => $section,
                'sort' => $sort,
            ]));
        }
        if (preg_match('/cuantos (?:alumnos|estudiantes)/u', $value) && $grade) {
            return $this->pack('get_students', array_filter([
                'grade' => $grade,
                'section' => $section,
                'sort' => $sort,
            ]));
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

        if (preg_match('/como va(?:n)?|como esta(?:n)?|rendimiento|como van los/u', $value)
            && ! $this->wantsDiagnosis($text)) {
            $student = $this->extractStudentName($text);
            if ($student) {
                return $this->pack('get_student_performance', array_filter([
                    'student_name' => $student,
                    'subject_name' => $subject,
                ]));
            }
            if ($grade) {
                return $this->pack('get_course_performance', [
                    'grade' => $grade,
                    'section' => $section,
                    'subject_name' => $subject,
                ]);
            }
            $memoryGrade = $memory['last_grade'] ?? null;
            if ($memoryGrade && preg_match('/\b(?:esos|ellas|ellos|sus notas|esos alumnos|como van)\b/u', $value)) {
                return $this->pack('get_course_performance', [
                    'grade' => $memoryGrade,
                    'section' => $section ?: ($memory['last_section'] ?? null),
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
            if (preg_match('/como (?:va|esta)\s+(.+?)\s*[?¿.!]*$/u', $value, $m)) {
                $target = trim((string) $m[1], " \t¿?!.¡");
                if ($this->extractGrade($target)) {
                    return $this->pack('get_course_performance', [
                        'grade' => $this->extractGrade($target),
                        'section' => $this->extractSection($text),
                    ]);
                }
                if (! preg_match('/\b(?:mi curso|el curso|el colegio|la asistencia)\b/u', $target)) {
                    return $this->pack('get_student_performance', ['student_name' => $target]);
                }
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
                'get_trend_analysis', 'get_risk_analysis', 'get_cause_analysis', 'get_smart_recommendations',
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
            '/como esta(?:n)?\s+(?:mi |el )?colegio|como estamos|diagnostico(?: del colegio)?|estado general del colegio|que esta pasando(?: en (?:mi |el )?colegio)?|que pasa en (?:mi |el )?colegio|panorama (?:del |de (?:mi |el ))?colegio/u',
            $value
        ) && ! preg_match('/\bmi curso\b/u', $value);
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
        if ($this->looksLikeRosterFollowUp($text, $memory)) {
            return $this->pack('get_students', array_filter([
                'grade' => $focus['grade'] ?? $memory['last_grade'] ?? null,
                'section' => $focus['section'] ?? $memory['last_section'] ?? null,
                'sort' => $this->extractSort($text) ?: ($memory['sort'] ?? null),
            ]));
        }

        if ($this->looksLikeGradeRosterFollowUp($text)) {
            $grade = $this->extractGrade($text) ?: ($memory['last_grade'] ?? $focus['grade'] ?? null);
            if ($grade) {
                return $this->pack('get_students', array_filter([
                    'grade' => $grade,
                    'section' => $this->extractSection($text) ?: ($memory['last_section'] ?? $focus['section'] ?? null),
                    'sort' => $this->extractSort($text) ?: ($memory['sort'] ?? null),
                ]));
            }
        }

        if ($focus === [] && empty($memory['last_student']) && empty($memory['student_name']) && empty($memory['student_names']) && empty($memory['last_students']) && empty($memory['last_intent'])) {
            return null;
        }
        if (! $this->looksLikeFollowUp($text) && ! preg_match('/\b(?:por que|tienen en comun|mas preocupante|en que materia|su profesor|caso mas|ellos|ese alumno|ese curso|los de)\b/u', $this->normalized($text))) {
            return null;
        }

        $value = $this->normalized($text);
        $student = $this->extractStudentName($text)
            ?: ($focus['student_name'] ?? $memory['last_student'] ?? $memory['student_name'] ?? ((array) ($memory['last_students'] ?? $memory['student_names'] ?? []))[0] ?? null);
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

        if (preg_match('/quien (?:es su profesor|le da)|su profesor|quien da esa materia/u', $value)) {
            $subject = $this->extractSubject($text) ?: ($memory['last_subject'] ?? $focus['worst_subject'] ?? $focus['subject'] ?? null);
            if (is_string($student) && $student !== '') {
                return [
                    'tools' => [['tool' => 'get_student_performance', 'args' => array_filter([
                        'student_name' => $student,
                        'subject_name' => $subject,
                    ])]],
                    'intent' => 'get_student_performance',
                    'clarification' => null,
                    'wants_opinion' => true,
                    'focus' => $focus + ['student_name' => $student, 'follow_up' => 'teacher', 'subject' => $subject],
                ];
            }
            if (is_string($subject) && $subject !== '') {
                return $this->pack('get_courses', ['subject_name' => $subject]);
            }
        }

        if (preg_match('/ese alumno|esa alumna|como esta(?:n)?(?: el| ella)?$/u', $value) && is_string($student) && $student !== '') {
            if (preg_match('/asistencia|faltas/u', $value)) {
                return $this->pack('get_attendance', ['student_name' => $student]);
            }

            return $this->pack('get_student_performance', ['student_name' => $student]);
        }

        if (preg_match('/ese curso|ese grado/u', $value)) {
            $grade = $memory['last_grade'] ?? $focus['grade'] ?? null;
            if ($grade) {
                if (preg_match('/quienes|alumnos|estudiantes|nombres/u', $value)) {
                    return $this->pack('get_students', [
                        'grade' => $grade,
                        'section' => $memory['last_section'] ?? $focus['section'] ?? null,
                    ]);
                }

                return $this->pack('get_course_performance', [
                    'grade' => $grade,
                    'section' => $memory['last_section'] ?? $focus['section'] ?? null,
                ]);
            }
        }

        if (preg_match('/\bellos\b|\bellas\b|esos alumnos|esas alumnas|sus notas|como van (?:esos|ellas|ellos|sus)/u', $value)) {
            $grade = $this->extractGrade($text) ?: ($memory['last_grade'] ?? $focus['grade'] ?? null);
            $section = $this->extractSection($text) ?: ($memory['last_section'] ?? $focus['section'] ?? null);
            if (preg_match('/asistencia|faltas/u', $value)) {
                return $this->pack('get_attendance', array_filter([
                    'grade' => $grade,
                    'section' => $section,
                ]));
            }
            if (preg_match('/notas|calificacion|como va|rendimiento|promedio/u', $value)) {
                return $this->pack('get_course_performance', array_filter([
                    'grade' => $grade,
                    'section' => $section,
                ]));
            }
            if (preg_match('/peor/u', $value)) {
                return $this->pack('get_rankings', array_filter([
                    'metric' => 'average',
                    'grade' => $grade,
                    'section' => $section,
                    'limit' => 5,
                    'sort' => 'avg_asc',
                ]));
            }

            return $this->pack('get_students', array_filter([
                'grade' => $grade,
                'section' => $section,
            ]));
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
        $focus['sort'] = $this->extractSort($text) ?? ($previous['sort'] ?? null);
        $focus['subject'] = $this->extractSubject($text) ?? ($previous['subject'] ?? $previous['worst_subject'] ?? null);

        foreach ($actions as $action) {
            $data = (array) ($action['data'] ?? []);
            $tool = (string) ($action['action_type'] ?? '');
            if (is_string($data['student'] ?? null)) {
                $focus['student_name'] = $data['student'];
                $focus['kind'] = 'student';
            }
            if (is_array($data['student'] ?? null) && ! empty($data['student']['name'])) {
                $focus['student_name'] = (string) $data['student']['name'];
                $focus['kind'] = 'student';
                $focus['grade'] = $data['student']['grade'] ?? $focus['grade'];
                $focus['section'] = $data['student']['section'] ?? $focus['section'];
            }
            if (! empty($data['teachers']) && is_array($data['teachers'])) {
                $firstTeacher = $data['teachers'][0] ?? null;
                $focus['teacher'] = is_string($firstTeacher) ? $firstTeacher : (is_array($firstTeacher) ? ($firstTeacher['name'] ?? null) : null);
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
            if ($tool === 'get_students' && isset($data['students'])) {
                $focus['kind'] = 'student_roster';
                $focus['student_names'] = collect($this->rowsOf($data['students']))->pluck('name')->filter()->values()->all();
            }
            if (in_array($tool, ['get_course_performance', 'get_grades'], true)) {
                $roster = (array) ($data['roster_names'] ?? []);
                $without = is_array($data['students_without_grades'] ?? null)
                    ? $data['students_without_grades']
                    : collect($data['students_without_grades'] ?? [])->all();
                $with = collect($this->rowsOf($data['students'] ?? []))->pluck('name')->filter()->all();
                $names = array_values(array_unique(array_filter(array_merge($roster, $without, $with))));
                if ($names !== []) {
                    $focus['student_names'] = $names;
                    $focus['kind'] = $focus['kind'] ?? 'course';
                }
                if (isset($data['students_count'])) {
                    $focus['students_count'] = (int) $data['students_count'];
                }
            }
            if ($tool === 'query_academic' && isset($data['students_count'])) {
                $focus['kind'] = 'student_count';
                $focus['students_count'] = (int) $data['students_count'];
            }
            if (isset($data['school_name'])) {
                $focus['kind'] = 'school_info';
                $focus['school_name'] = $data['school_name'];
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

        $enrollment = (int) ($report['students']['count'] ?? 0);
        $hasEmpty = $avg === null && $riskNames->isEmpty() && $enrollment === 0;
        if ($hasEmpty) {
            return 'Todavía no hay suficientes datos para decirte cómo está el colegio. Cuando existan notas o asistencia podré señalar riesgos.';
        }

        $parts = [];
        $parts[] = $enrollment > 0
            ? "En general, el colegio tiene {$enrollment} alumnos registrados."
            : 'En general, todavía no hay alumnos registrados.';
        $parts[] = $avg !== null
            ? "El rendimiento disponible muestra un promedio general de {$avg}%."
            : 'Aún no hay suficientes calificaciones para hablar de rendimiento.';
        if ($records > 0) {
            $presentRate = max(0, min(100, round((1 - ($absenceTotal / max(1, $records))) * 100, 1)));
            $parts[] = "En asistencia, el {$presentRate}% de los registros recientes están presentes ({$absenceTotal} falta(s) en 30 días).";
        } else {
            $parts[] = 'No hay registros de asistencia recientes.';
        }
        if ($riskNames->isNotEmpty()) {
            $watch = $riskNames->take(3)->implode(', ');
            $extra = $riskNames->count() > 3 ? ' y otros' : '';
            $parts[] = "Lo que más vigilaría ahora es a {$watch}{$extra}.";
        } else {
            $parts[] = 'Con los datos actuales no aparece un grupo urgente en riesgo.';
        }
        if (is_string($criticalSubject) && $criticalSubject !== '' && $criticalSubject !== 'sin materia' && $riskNames->isNotEmpty()) {
            $parts[] = "{$criticalSubject} concentra varios de esos casos.";
        }
        if (is_string($priority) && trim($priority) !== '' && $riskNames->isNotEmpty()) {
            $parts[] = "Conviene revisar {$priority}.";
        }

        return implode(' ', $parts);
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
        $avg = $report['performance']['class_avg_pct'] ?? ($report['school_avg_pct'] ?? null);
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
        if (preg_match('/^por que$|por que\b/u', $value) && $overall !== null && (float) $overall < 60) {
            return "{$student} presenta bajo rendimiento, por debajo de lo esperado (promedio {$overall}%).";
        }
        if (preg_match('/quien es su profesor|su profesor|quien le da|con que profesor/u', $value)) {
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
                    ? 'El alumno presenta bajo rendimiento, por debajo de lo esperado.'
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
            '1er grado' => '1ro grado',
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
        $markers = [
            'con que profesor esta', 'con que profesor estan', 'profesor de',
            'rendimiento de', 'le va a', 'como le va a', 'como le va',
            'como esta', 'como estan', 'como va', 'va a',
        ];
        foreach ($markers as $marker) {
            $mNorm = $this->normalized($marker);
            if (preg_match('/'.preg_quote($mNorm, '/').'\s+([a-z]+(?:\s+[a-z]+){0,2})/u', $norm, $m)) {
                $raw = trim((string) $m[1]);
                if (preg_match('/^(?:1ro|2do|3ro|4to|5to|6to|grado|curso|seccion|mi|el|la|los|las|tu|su|colegio|asistencia|rendimiento)/u', $raw)) {
                    continue;
                }
                $parts = explode(' ', $raw);
                $parts = array_map(fn ($p) => mb_convert_case($p, MB_CASE_TITLE, 'UTF-8'), $parts);
                $name = implode(' ', $parts);
                $name = preg_replace('/\s+(y|en|con|de|del|que|en el|en la).*$/iu', '', $name);

                return trim((string) $name) !== '' ? trim((string) $name) : null;
            }
        }

        return null;
    }

    /**
     * @param  array<string,mixed>  $memory
     */
    private function matchRememberedStudent(string $hint, array $memory): ?string
    {
        $needle = $this->normalized($hint);
        $names = array_values(array_filter(array_map(
            'strval',
            array_merge(
                (array) ($memory['last_students'] ?? []),
                (array) ($memory['student_names'] ?? []),
                array_filter([$memory['last_student'] ?? null, $memory['student_name'] ?? null]),
                (array) (($memory['focus']['student_names'] ?? [])),
            )
        )));
        foreach ($names as $name) {
            $hay = $this->normalized($name);
            if ($hay === $needle || str_contains($hay, $needle) || str_contains($needle, $hay)) {
                return $name;
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

    private function timelineFromTools(array $tools): array
    {
        $map = [
            'get_students' => 'Revisando lista de alumnos',
            'get_student' => 'Consultando ficha del alumno',
            'get_courses' => 'Revisando cursos',
            'get_teachers' => 'Consultando plantel docente',
            'verify_teacher' => 'Verificando si es profesor',
            'verify_student' => 'Verificando si es alumno',
            'get_grades' => 'Analizando calificaciones',
            'get_attendance' => 'Revisando asistencia',
            'get_evaluations' => 'Consultando evaluaciones',
            'get_assignments' => 'Revisando tareas',
            'get_student_performance' => 'Analizando rendimiento del alumno',
            'get_course_performance' => 'Evaluando rendimiento del curso',
            'compare_courses' => 'Comparando cursos',
            'get_at_risk_students' => 'Detectando alumnos en riesgo',
            'get_declining_students' => 'Analizando tendencias de notas',
            'get_academic_trends' => 'Revisando tendencias',
            'generate_school_report' => 'Generando informe ejecutivo',
            'get_rankings' => 'Calculando ranking',
            'get_section_counts' => 'Contando alumnos por sección',
            'query_academic' => 'Consultando datos académicos',
        ];
        $steps = [];
        foreach ($tools as $tool) {
            $steps[] = $map[$tool] ?? 'Procesando '.str_replace('_',' ',$tool);
        }
        if ($steps === []) {
            $steps[] = 'Analizando tu pregunta';
        }
        $steps[] = 'Preparando análisis';
        return $steps;
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

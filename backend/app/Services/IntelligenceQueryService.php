<?php

namespace App\Services;

use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * Consulta controlada en lenguaje natural para "Inteligencia AulaSync".
 * La IA nunca responde con conocimiento propio: elige una herramienta de
 * consulta estructurada (o el parser local cuando está desactivada) y la
 * respuesta se construye SOLO con datos reales del profesor. Fuera de ese
 * alcance, se rechaza la pregunta.
 */
class IntelligenceQueryService
{
    private const QUERY_TYPES = ['group_status', 'best_performers', 'needs_attention', 'difficulty_areas', 'attendance', 'student_summary'];

    public function __construct(private IntelligenceAnalyticsService $analytics) {}

    /**
     * @return array{message: string, data: array<string, mixed>, query_type: string}
     */
    public function answer(User $teacher, string $text, ?int $courseId = null): array
    {
        $text = trim($text);

        if ($text === '') {
            return $this->refusal();
        }

        if ($this->isInstitutionalQuestion($text)) {
            return $this->institutionalRefusal();
        }

        $parsed = $this->interpretWithModel($teacher, $text);

        if ($parsed === null) {
            $parsed = $this->localParse($teacher, $text);
        }

        return $this->dispatch($teacher, $parsed, $courseId);
    }

    /**
     * Intenta enrutar la pregunta con el modelo (herramientas estructuradas).
     * Devuelve null si la IA está desactivada o falla (se usa el parser local).
     *
     * @return array{query_type: string, student_name?: string}|null
     */
    private function interpretWithModel(User $teacher, string $text): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        try {
            $response = Http::timeout(30)
                ->withToken((string) config('services.openai.key'))
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => (string) config('services.openai.intelligence_model', 'gpt-4o-mini'),
                    'temperature' => 0,
                    'tool_choice' => 'auto',
                    'tools' => [[
                        'type' => 'function',
                        'function' => [
                            'name' => 'query_intelligence',
                            'description' => 'Consulta datos reales de los cursos, alumnos, notas y asistencia del profesor. Úsala SIEMPRE en lugar de responder con conocimiento propio.',
                            'parameters' => [
                                'type' => 'object',
                                'properties' => [
                                    'query_type' => [
                                        'type' => 'string',
                                        'enum' => self::QUERY_TYPES,
                                    ],
                                    'student_name' => [
                                        'type' => 'string',
                                        'description' => 'Nombre del alumno (solo para student_summary).',
                                    ],
                                ],
                                'required' => ['query_type'],
                                'additionalProperties' => false,
                            ],
                        ],
                    ]],
                    'messages' => [
                        ['role' => 'system', 'content' => $this->systemPrompt()],
                        ['role' => 'user', 'content' => $text],
                    ],
                ]);

            $name = (string) $response->json('choices.0.message.tool_calls.0.function.name', '');

            if ($response->failed() || $name !== 'query_intelligence') {
                return null;
            }

            $arguments = json_decode((string) $response->json('choices.0.message.tool_calls.0.function.arguments', '{}'), true);
            $queryType = (string) ($arguments['query_type'] ?? '');

            if (! in_array($queryType, self::QUERY_TYPES, true)) {
                return null;
            }

            return [
                'query_type' => $queryType,
                'student_name' => isset($arguments['student_name']) ? trim((string) $arguments['student_name']) : null,
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
Eres el consultor de datos de "Inteligencia AulaSync" para profesores.
NUNCA respondes con conocimiento propio ni generalidades educativas.
NO consultes nómina institucional, profesores, rankings del colegio ni rendimiento global.
Solo usas datos pedagógicos de los cursos del docente autenticado.
Para cada pregunta eliges EXACTAMENTE una herramienta de consulta:

- group_status: estado general del grupo (¿cómo está la clase?, ¿cómo van?)
- best_performers: mejores rendimientos (¿quién tiene mejor promedio?, rankings)
- needs_attention: estudiantes que requieren atención (riesgo, bajos promedios, ausencias)
- difficulty_areas: áreas/temas/actividades con más dificultades (promedios más bajos)
- attendance: asistencia, faltas y ausencias
- student_summary: detalle de un alumno concreto (requiere student_name)

Si la pregunta no encaja en ninguna herramienta, elige group_status.
Si mencionan a un alumno por su nombre, usa student_summary con ese nombre.
PROMPT;
    }

    /**
     * @return array{query_type: string, student_name?: string|null}
     */
    private function localParse(User $teacher, string $text): array
    {
        $value = $this->fold($text);

        $studentName = $this->detectStudent($teacher, $value);
        if ($studentName !== null) {
            return ['query_type' => 'student_summary', 'student_name' => $studentName];
        }

        if (preg_match('/mejor(es)? (rendimiento|promedio|nota)|quien (es|tiene) el mejor|primer lugar|top \d|ranking|los mejores/u', $value)) {
            return ['query_type' => 'best_performers'];
        }

        if (preg_match('/necesita(n)? atencion|requiere(n)? atencion|en riesgo|atrasad|reprobad|van mal|bajo rendimiento|bajando de rendimiento/u', $value)) {
            return ['query_type' => 'needs_attention'];
        }

        if (preg_match('/(area|tema|actividad|materia|contenido|evaluacion|examen).*(dificultad|dificil|peor|mas bajo|cuesta)|(dificultad|dificil|peor|mas bajo|cuesta).*(area|tema|actividad|materia|contenido|evaluacion|examen)/u', $value)) {
            return ['query_type' => 'difficulty_areas'];
        }

        if (preg_match('/asistencia|faltas|ausencia|ausente|no asist|inasistencia/u', $value)) {
            return ['query_type' => 'attendance'];
        }

        if (preg_match('/como (esta|va|van|van las)|estado del grupo|resumen del grupo|panorama|como vamos|reporte del grupo/u', $value)) {
            return ['query_type' => 'group_status'];
        }

        return ['query_type' => 'unknown'];
    }

    private function isInstitutionalQuestion(string $text): bool
    {
        $value = $this->fold($text);

        return (bool) preg_match(
            '/todos los (alumnos|estudiantes) del colegio|nomina (completa|del colegio)|cuantos profesores|listado de profesores|ranking institucional|rendimiento (del colegio|institucional)|estadisticas del colegio|todos los cursos del colegio/u',
            $value
        );
    }

    /**
     * Detecta si el texto menciona a un alumno inscrito en los cursos del
     * profesor. Devuelve el nombre, null si no hay mención, o "?" si hay
     * varios alumnos distintos que coinciden.
     */
    private function detectStudent(User $teacher, string $foldedText): ?string
    {
        $courseIds = $teacher->courses()->pluck('courses.id')->all();

        if ($courseIds === []) {
            return null;
        }

        $students = Student::whereHas('courses', fn ($query) => $query->whereIn('courses.id', $courseIds))
            ->where('students.colegio_id', $teacher->colegio_id)
            ->get(['id', 'name']);

        $matches = [];
        foreach ($students as $student) {
            $needle = $this->fold($student->name);
            if ($needle !== '' && str_contains($foldedText, $needle)) {
                $matches[$student->id] = $student->name;
            }
        }

        if (count($matches) === 1) {
            return (string) reset($matches);
        }

        if (count($matches) > 1) {
            return '?'.implode('|', array_values($matches));
        }

        return null;
    }

    /**
     * @param  array{query_type: string, student_name?: string|null}  $parsed
     * @return array{message: string, data: array<string, mixed>, query_type: string}
     */
    private function dispatch(User $teacher, array $parsed, ?int $courseId): array
    {
        $queryType = $parsed['query_type'];

        if ($queryType === 'student_summary') {
            $studentName = (string) ($parsed['student_name'] ?? '');
            if ($studentName === '' || $studentName === '?') {
                return $this->refusal();
            }

            if (str_starts_with($studentName, '?')) {
                $names = explode('|', mb_substr($studentName, 1));

                return [
                    'message' => 'Encontré varios alumnos con nombres parecidos: '.implode(', ', $names).'. ¿A cuál te refieres?',
                    'data' => ['candidates' => $names],
                    'query_type' => 'disambiguation',
                ];
            }

            $summary = $this->analytics->studentSummary($teacher, $courseId, $studentName);

            if (! ($summary['found'] ?? false)) {
                return [
                    'message' => (string) ($summary['message'] ?? 'No encontré a ese alumno.'),
                    'data' => $summary,
                    'query_type' => 'student_summary',
                ];
            }

            return [
                'message' => $this->studentMessage($summary),
                'data' => $summary,
                'query_type' => 'student_summary',
            ];
        }

        if ($queryType === 'best_performers') {
            $rows = $this->analytics->bestPerformers($teacher, $courseId, 5);

            if ($rows === []) {
                return $this->noData('Todavía no hay calificaciones registradas en tus cursos para hacer un ranking.');
            }

            return [
                'message' => "🏆 **Mejores rendimientos**\n\n".$this->table(
                    ['#', 'Alumno', 'Promedio'],
                    collect($rows)->map(fn ($row, $index) => [
                        (string) ($index + 1), $row['name'], $row['avg_pct'].'% ('.$row['graded'].' nota'.($row['graded'] === 1 ? '' : 's').')',
                    ])->all()
                ),
                'data' => ['students' => $rows],
                'query_type' => 'best_performers',
            ];
        }

        if ($queryType === 'needs_attention') {
            $rows = $this->analytics->atRisk($teacher, $courseId);

            if ($rows === []) {
                return [
                    'message' => '✅ Ningún estudiante requiere atención especial ahora mismo según los datos registrados.',
                    'data' => ['students' => []],
                    'query_type' => 'needs_attention',
                ];
            }

            return [
                'message' => "⚠️ **Estudiantes que requieren atención**\n\n".$this->table(
                    ['Alumno', 'Promedio', 'Motivo'],
                    collect($rows)->map(fn ($row) => [
                        $row['name'],
                        $row['avg_pct'] !== null ? $row['avg_pct'].'%' : 'sin notas',
                        implode('; ', $row['reasons']),
                    ])->all()
                ),
                'data' => ['students' => $rows],
                'query_type' => 'needs_attention',
            ];
        }

        if ($queryType === 'difficulty_areas') {
            $rows = $this->analytics->difficultyAreas($teacher, $courseId);

            if ($rows === []) {
                return $this->noData('Necesito al menos 3 calificaciones por actividad para detectar áreas con dificultades.');
            }

            return [
                'message' => "📉 **Áreas con más dificultades** (promedio más bajo)\n\n".$this->table(
                    ['Actividad', 'Curso', 'Promedio'],
                    collect($rows)->map(fn ($row) => [$row['title'], $row['subject'], $row['avg_pct'].'% ('.$row['graded'].' notas)'])->all()
                ),
                'data' => ['activities' => $rows],
                'query_type' => 'difficulty_areas',
            ];
        }

        if ($queryType === 'attendance') {
            $block = $this->analytics->attendanceBlock($teacher, $courseId, 30);

            if ($block['total'] === 0) {
                return $this->noData('Todavía no hay registros de asistencia en tus cursos.');
            }

            $lines = "🗓️ **Asistencia (últimos 30 días)**: {$block['rate']}% de presencia ({$block['absent']} ausencias en {$block['total']} registros).";
            if ($block['top_absentees'] !== []) {
                $lines .= "\n\n".$this->table(
                    ['Alumno', 'Ausencias'],
                    collect($block['top_absentees'])->map(fn ($row) => [$row['name'], (string) $row['absences']])->all()
                );
            }

            return ['message' => $lines, 'data' => $block, 'query_type' => 'attendance'];
        }

        if ($queryType === 'group_status') {
            $summary = $this->analytics->groupSummary($teacher, $courseId);

            return [
                'message' => $this->groupMessage($summary),
                'data' => $summary,
                'query_type' => 'group_status',
            ];
        }

        return $this->refusal();
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function groupMessage(array $summary): string
    {
        if (! ($summary['has_data'] ?? false)) {
            return '📊 '.($summary['label'] ? $summary['label'].': ' : '').'todavía no hay calificaciones registradas. Importa tus notas o registra calificaciones para que pueda darte una visión del grupo.';
        }

        $performance = $summary['performance'];
        $lines = [
            '📊 **'.$summary['label'].'**',
            '',
            "Promedio general: **{$performance['avg_pct']}%** con {$performance['graded_students']} alumno(s) evaluado(s).",
            "Distribución: {$performance['distribution']['high']} en alto rendimiento (≥70%), {$performance['distribution']['mid']} en desarrollo (50–69%) y {$performance['distribution']['low']} requieren apoyo (<50%).",
        ];

        if ($performance['top'] !== []) {
            $lines[] = '';
            $lines[] = $this->table(
                ['Alumno', 'Promedio'],
                collect($performance['top'])->map(fn ($row) => [$row['name'], $row['avg_pct'].'%'])->all()
            );
        }

        if ($summary['attention'] !== []) {
            $lines[] = '';
            $lines[] = '⚠️ '.count($summary['attention']).' alumno(s) requieren atención (pregúntame «¿quiénes necesitan atención?» para el detalle).';
        }

        if (($summary['attendance']['rate'] ?? null) !== null) {
            $lines[] = '';
            $lines[] = "🗓️ Asistencia (30 días): {$summary['attendance']['rate']}%.";
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function studentMessage(array $summary): string
    {
        $student = $summary['student'];
        $lines = ['👩‍🎓 **'.$student['name'].'**'.($student['grade'] ? ' ('.$student['grade'].($student['section'] ? ' / '.$student['section'] : '').')' : ''), ''];

        if ($summary['avg_pct'] === null) {
            $lines[] = 'Todavía no tiene calificaciones registradas en tus cursos.';

            if ($summary['absences_30d'] > 0) {
                $lines[] = "Inasistencias (30 días): {$summary['absences_30d']}.";
            }

            return implode("\n", $lines);
        }

        $lines[] = "Promedio: **{$summary['avg_pct']}%** ({$summary['grades_count']} calificaciones).";

        if ($summary['recent_grades'] !== []) {
            $lines[] = '';
            $lines[] = $this->table(
                ['Actividad', 'Fecha', 'Nota'],
                collect($summary['recent_grades'])->map(fn ($grade) => [
                    $grade['activity'],
                    $grade['date'] ?? '—',
                    $grade['pct'] !== null ? $grade['score'].'/'.$grade['max_score'].' ('.$grade['pct'].'%)' : (string) $grade['score'],
                ])->all()
            );
        }

        if ($summary['absences_30d'] > 0) {
            $lines[] = '';
            $lines[] = "🗓️ Inasistencias (30 días): {$summary['absences_30d']}.";
        }

        if ($summary['attention_reasons'] !== []) {
            $lines[] = '';
            $lines[] = '⚠️ Atención: '.implode('; ', $summary['attention_reasons']).'.';
        }

        return implode("\n", $lines);
    }

    /**
     * @return array{message: string, data: array<string, mixed>, query_type: string}
     */
    private function refusal(): array
    {
        return [
            'message' => "Solo respondo con los datos reales de tus cursos en AulaSync. Puedo decirte, por ejemplo:\n\n- «¿Cómo está 4to A?» — estado general del grupo\n- «¿Qué estudiantes necesitan atención?»\n- «¿Quién tiene mejor rendimiento?»\n- «¿Qué área presenta más dificultades?»\n- «¿Cómo va Ana Ruiz?» — detalle de un alumno",
            'data' => [],
            'query_type' => 'refusal',
        ];
    }

    /**
     * @return array{message: string, data: array<string, mixed>, query_type: string}
     */
    private function institutionalRefusal(): array
    {
        return [
            'message' => 'Esa consulta es institucional. Solo el director puede ver la nómina completa, profesores o el rendimiento del colegio. Puedo ayudarte con la planificación, actividades y el grupo de tus propios cursos.',
            'data' => [],
            'query_type' => 'institutional_refusal',
        ];
    }

    /**
     * @return array{message: string, data: array<string, mixed>, query_type: string}
     */
    private function noData(string $message): array
    {
        return ['message' => '📊 '.$message, 'data' => [], 'query_type' => 'no_data'];
    }

    private function enabled(): bool
    {
        if (! config('services.openai.intelligence_enabled', true)) {
            return false;
        }

        if (app()->environment('testing') && ! config('services.openai.intelligence_test_enabled', false)) {
            return false;
        }

        $key = trim((string) config('services.openai.key'));

        return $key !== '' && ! str_contains($key, 'your_openai');
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, string>>  $rows
     */
    private function table(array $headers, array $rows): string
    {
        $lines = ['| '.implode(' | ', $headers).' |', '|'.implode('|', array_fill(0, count($headers), '---')).'|'];

        foreach ($rows as $row) {
            $lines[] = '| '.implode(' | ', array_map(fn ($cell) => str_replace('|', '\\|', (string) $cell), $row)).' |';
        }

        return implode("\n", $lines);
    }

    private function fold(string $value): string
    {
        $value = mb_strtolower(trim($value));

        return strtr($value, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            '¿' => '', '?' => '', '¡' => '', '!' => '',
        ]);
    }
}

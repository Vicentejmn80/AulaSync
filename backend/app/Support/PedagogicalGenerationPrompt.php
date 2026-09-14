<?php

namespace App\Support;

/**
 * Instrucciones pedagógicas compartidas para generar clases
 * (chatbot docente, planificador masivo y AI Lab).
 */
class PedagogicalGenerationPrompt
{
    /** @var array<int, string> */
    private const TECHNIQUES = [
        'mímica',
        'juego de rol',
        'actividad digital interactiva',
        'debate rápido',
        'estaciones de trabajo',
        'reto en equipo',
    ];

    public static function rules(string $methodology, string $headersLine = ''): string
    {
        $methodology = LessonTemplate::normalize($methodology);
        $headersLine = $headersLine !== '' ? $headersLine : LessonTemplate::promptLine($methodology);
        $label = LessonTemplate::label($methodology);

        return implode("\n", [
            'REGLAS PEDAGÓGICAS ESTRICTAS (generación de clases y planificaciones):',
            'Metodología activa: «'.$label.'» (parámetro methodology='.$methodology.').',
            'Encabezados Markdown EXACTOS, en este orden: '.$headersLine.'.',
            '',
            '1) VARIABILIDAD Y CREATIVIDAD (PROHIBIDO TEXTO GENÉRICO):',
            '- PROHIBIDO repetir exactamente la misma estructura de texto o frases cliché como «pregunta disparadora», «se explicitan saberes previos» o «exposición ordenada para el cuaderno» en clases consecutivas.',
            '- Cada clase debe ser única, dinámica, entretenida y práctica.',
            '- Incorpora técnicas distintas entre sesiones: mímica, juegos de rol, actividades digitales interactivas, debates rápidos, estaciones de trabajo o retos en equipo.',
            '- No copies un molde y solo cambies el tema: cambia el enganche, la actividad central y el cierre.',
            '',
            '2) SECUENCIACIÓN LÓGICA DEL TEMA:',
            '- PRIMERA clase (Introducción): NO asumas que los alumnos ya dominan el contenido. El inicio despierta curiosidad, rompe el hielo o presenta un enigma/asombro.',
            '- Clase INTERMEDIA (Práctica/Desarrollo): inicio = repaso activo de 3 minutos; el desarrollo es 80% práctico o colaborativo.',
            '- Clase FINAL (Consolidación): céntrate en juegos de fijación, síntesis o producción de los estudiantes.',
            '- Si generas varias clases del mismo tema, asigna esos roles en secuencia (1ª = introducción, intermedias = práctica, última = consolidación).',
            '',
            self::methodologyBlock($methodology),
            '',
            '4) FORMATO Y TONO:',
            '- Instrucciones claras, directas y accionables para el docente (qué hace, con qué material, en cuántos minutos).',
            '- Incluye ejemplos concretos de lo que dice o hace el profesor y los estudiantes.',
            '- Ejemplo de tono (adapta al tema, no copies este caso): «El docente muestra un batidor y los alumnos hacen la mímica del movimiento; luego en parejas explican qué creen que ocurre.»',
            '- Español de aula, sin jerga vacía ni relleno burocrático.',
        ]);
    }

    public static function methodologyBlock(string $methodology): string
    {
        $methodology = LessonTemplate::normalize($methodology);

        $classic = implode("\n", [
            '3) ADAPTACIÓN ESTRICTA A LA METODOLOGÍA SELECCIONADA:',
            '- Modelo Clásico: Inicio (Enganche activo) → Desarrollo (Construcción/Práctica) → Cierre (Consolidación/Metacognición).',
            '- En INICIO: enganche vivo (objeto, gesto, dilema breve), no un discurso.',
            '- En DESARROLLO: los alumnos construyen o practican; el docente modela poco y circula.',
            '- En CIERRE: evidencia rápida de aprendizaje + metacognición concreta (qué pueden hacer ahora).',
        ]);

        return match ($methodology) {
            LessonTemplate::CONSTRUCTIVIST => implode("\n", [
                '3) ADAPTACIÓN ESTRICTA A LA METODOLOGÍA SELECCIONADA — Modelo 5E:',
                '- Enganchar (Engage / ACTIVACIÓN): asombro, conflicto cognitivo o fenómeno inesperado. Cero lección frontal.',
                '- Explorar (Explore / EXPLORACIÓN): los alumnos prueban, observan o manipulan antes de la definición.',
                '- Explicar (Explain / EXPLICACIÓN): se nombra el concepto con evidencia de la exploración.',
                '- Elaborar (Elaborate / APLICACIÓN): transfieren a un caso nuevo o reto.',
                '- Evaluar (Evaluate / EVALUACIÓN): evidencia visible (producto, gesto, frase, ticket) sin examen formal salvo que se pida.',
            ]),
            LessonTemplate::PROJECT => implode("\n", [
                '3) ADAPTACIÓN ESTRICTA A LA METODOLOGÍA SELECCIONADA — Aprendizaje Basado en Retos / Proyectos (ABR/ABP):',
                '- Planteamiento del Reto (DESAFÍO): problema auténtico, audiencia real o producto que importa.',
                '- Investigación/Acción (INVESTIGACIÓN + CREACIÓN): buscan, prueban, prototipan; el docente es coach, no expositor.',
                '- Presentación de Solución (PRESENTACIÓN + REFLEXIÓN): muestran el producto y explican criterios de calidad.',
            ]),
            LessonTemplate::DIRECT => implode("\n", [
                '3) ADAPTACIÓN ESTRICTA A LA METODOLOGÍA SELECCIONADA — Instrucción Directa (con enganche activo):',
                '- MOTIVACIÓN = Enganche activo (no recitar objetivos).',
                '- PRESENTACIÓN = modelado breve y visible (el docente hace 1 ejemplo en voz alta).',
                '- PRÁCTICA GUIADA = 80% del tiempo: alumnos hacen, docente corrige en circulación.',
                '- CIERRE REFLEXIVO = consolidación/metacognición con evidencia de los estudiantes.',
            ]),
            default => $classic,
        };
    }

    /**
     * @return 'introduccion'|'practica'|'consolidacion'
     */
    public static function sessionRole(int $index, int $count): string
    {
        $index = max(0, $index);
        $count = max(1, $count);

        if ($index === 0) {
            return 'introduccion';
        }
        if ($index === $count - 1 || $count === 2) {
            return 'consolidacion';
        }

        return match ($index % 3) {
            0 => 'introduccion',
            2 => 'consolidacion',
            default => 'practica',
        };
    }

    public static function technique(int $index): string
    {
        $pool = self::TECHNIQUES;
        $i = $index % count($pool);

        return $pool[$i >= 0 ? $i : 0];
    }

    /**
     * Markdown de respaldo (sin LLM) con variedad por índice y rol de secuencia.
     */
    public static function fallbackLessonMarkdown(
        string $topic,
        string $methodology,
        int $sessionIndex = 0,
        int $sessionCount = 1,
    ): string {
        $methodology = LessonTemplate::normalize($methodology);
        $topic = trim($topic) !== '' ? trim($topic) : 'el tema del curso';
        $role = self::sessionRole($sessionIndex, $sessionCount);
        $technique = self::technique($sessionIndex);
        $phases = self::fallbackPhases($topic, $role, $technique, $methodology);

        return LessonTemplate::build($phases, $methodology);
    }

    /**
     * @return array<string, string>
     */
    private static function fallbackPhases(string $topic, string $role, string $technique, string $methodology): array
    {
        $start = self::startBeat($topic, $role, $technique);
        $core = self::coreBeat($topic, $role, $technique);
        $end = self::endBeat($topic, $role, $technique);

        return match ($methodology) {
            LessonTemplate::DIRECT => [
                'motivacion' => $start,
                'presentacion' => "El docente modela UN caso de {$topic} en voz alta (máximo 4 minutos): dice qué observa, qué decide y por qué. Los alumnos imitan el gesto o anotan solo 3 palabras clave, no un párrafo copiado.",
                'practica' => $core,
                'cierre_reflexivo' => $end,
            ],
            LessonTemplate::CONSTRUCTIVIST => [
                'activacion' => $start,
                'exploracion' => "Sin definición previa, los alumnos exploran {$topic} con {$technique}: prueban, comparan y registran 2 evidencias (dibujo, dato o frase).",
                'explicacion' => "Recién ahora se nombra el concepto. El docente toma 2 evidencias del grupo y las convierte en la idea de {$topic}. Un alumno reformula con sus palabras.",
                'aplicacion' => $core,
                'evaluacion' => $end,
            ],
            LessonTemplate::PROJECT => [
                'desafio' => "Reto auténtico sobre {$topic}: «¿Cómo le explicarían esto a alguien que lo necesita hoy?». {$start}",
                'investigacion' => "Equipos reúnen 3 pistas reales (objeto, ejemplo del barrio, captura digital) sobre {$topic}. El docente solo hace preguntas, no dicta la respuesta.",
                'creacion' => $core,
                'presentacion' => "Cada equipo muestra su solución de {$topic} en 45 segundos (gesto, prototipo o consigna). La audiencia hace 1 pregunta útil.",
                'reflexion' => $end,
            ],
            default => [
                'inicio' => $start,
                'desarrollo' => $core,
                'cierre' => $end,
            ],
        };
    }

    private static function startBeat(string $topic, string $role, string $technique): string
    {
        if ($role === 'introduccion') {
            return match ($technique) {
                'mímica' => "El docente entra con un objeto cotidiano ligado a {$topic} y pide mímica: los alumnos representan lo que creen que ocurre, sin nombrar el concepto. Nadie asume que ya lo dominan; el asombro abre la clase.",
                'juego de rol' => "Rompehielo: 30 segundos en rol («eres un detective / un chef / un periodista») frente a un enigma de {$topic}. El docente no explica todavía: solo pregunta «¿qué extraño ves aquí?».",
                'actividad digital interactiva' => "Se proyecta una imagen o clip de 20 segundos sobre {$topic} y los alumnos votan con 1 gesto (sí/no/duda). El docente recoge 3 ocurrencias raras y las deja en el aire como misterio.",
                'debate rápido' => "Dilema de 1 minuto sobre {$topic} (dos afirmaciones opuestas en la pizarra). Cada alumno se mueve a un lado del salón. Todavía no hay «respuesta correcta».",
                'estaciones de trabajo' => "Tres mesas con pistas (objeto, foto, frase) sobre {$topic}. Rotan 40 segundos cada una y anotan una pregunta, no una definición.",
                default => "Reto relámpago: en equipos de 3, inventan un título absurdo para {$topic} a partir de un objeto sorpresa. El docente celebra la rareza y guarda las ideas para volver a ellas al final.",
            };
        }

        if ($role === 'practica') {
            return "Repaso activo de 3 minutos sobre {$topic}: un alumno hace un gesto o muestra un ejemplo, el resto completa la idea en una frase. Luego se entra de lleno a la práctica con {$technique}.";
        }

        return "Apertura de consolidación: el docente pide un producto mínimo de {$topic} (gesto, esquema o frase) antes de hablar. Con {$technique} se fija lo aprendido, no se vuelve a explicar desde cero.";
    }

    private static function coreBeat(string $topic, string $role, string $technique): string
    {
        $practical = $role === 'practica'
            ? "El 80% del tiempo es práctico o colaborativo. El docente circula y corrige en voz baja."
            : "La actividad central cambia respecto a la clase anterior: hoy el motor es {$technique}.";

        return match ($technique) {
            'mímica' => "Los alumnos representan pasos de {$topic} con el cuerpo; un compañero «lee» la mímica y nombra el proceso. {$practical} Ejemplo: el docente muestra el movimiento y dice «congela»: el grupo justifica qué parte de {$topic} está ocurriendo.",
            'juego de rol' => "Parejas en rol (quien explica / quien usa el concepto) resuelven un caso real de {$topic}. {$practical} El docente pasa por los equipos y pide: «demuéstramelo, no me lo recites».",
            'actividad digital interactiva' => "Usan un tablero, formulario o simulación breve para decidir sobre {$topic} y ver el efecto al instante. {$practical} Cada dupla debe poder decir qué cambió y por qué.",
            'debate rápido' => "En 4 minutos, dos equipos defienden usos distintos de {$topic} con un ejemplo concreto cada uno. {$practical} Un secretario anota el mejor argumento, no un resumen largo.",
            'estaciones de trabajo' => "Tres estaciones sobre {$topic}: manipular, resolver, crear. Rotan. {$practical} En cada mesa dejan una evidencia (resultado, foto o frase).",
            default => "Reto en equipo: producir un mini-artefacto (cartel, prototipo, consigna o demo de 20 segundos) que demuestre {$topic}. {$practical} Criterio visible: se entiende sin que el docente hable.",
        };
    }

    private static function endBeat(string $topic, string $role, string $technique): string
    {
        if ($role === 'consolidacion') {
            return "Juego de fijación o producción: los estudiantes crean la síntesis de {$topic} (mapa, consigna, demo o ticket hecho por ellos). El docente no dicta el cierre; solo pide: «muéstrenme que ya pueden usarlo». Técnica de cierre: {$technique}.";
        }

        if ($role === 'introduccion') {
            return "Cierre de asombro: vuelven al enigma inicial de {$topic} y cambian su primera idea. Un alumno dice en voz alta qué duda queda para la próxima clase. Nada de copiar un resumen genérico.";
        }

        return "Metacognición concreta (1 minuto): cada alumno nombra 1 cosa que ya puede hacer con {$topic} y 1 error que corrigió hoy. El docente recoge 2 ejemplos del grupo y anuncia el puente práctico de la siguiente sesión.";
    }
}

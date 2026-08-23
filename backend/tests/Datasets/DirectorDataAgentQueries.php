<?php

/**
 * Dataset de 25 consultas reales de director para evaluación del Director Data Agent.
 * Cada caso define: pregunta, tools esperados, datos mínimos en respuesta, categoría.
 * Usado para evaluación manual y automatizada. Nunca debe inventar datos.
 */
return [
    // ── Rendimiento de cursos (4) ──────────────────────────────────────
    [
        'id' => 1,
        'category' => 'rendimiento_cursos',
        'question' => '¿Cómo va 4to A?',
        'expected_tools' => ['get_course_performance'],
        'min_data' => ['students', 'class_avg_pct'],
        'needs' => 'Ranking de alumnos de 4to A con promedio general. Si no hay alumnos/notas, mensaje "No hay alumnos registrados..."',
    ],
    [
        'id' => 2,
        'category' => 'rendimiento_cursos',
        'question' => '¿Cómo va 2do B en Matemática?',
        'expected_tools' => ['get_course_performance'],
        'min_data' => ['students'],
        'needs' => 'Filtrar por grado 2do, sección B, materia Matemática.',
    ],
    [
        'id' => 3,
        'category' => 'rendimiento_cursos',
        'question' => '¿Cómo va 1ro?',
        'expected_tools' => ['get_course_performance'],
        'min_data' => ['students'],
        'needs' => 'Sin sección, debe agrupar todo 1ro.',
    ],
    [
        'id' => 4,
        'category' => 'rendimiento_cursos',
        'question' => '¿Cómo va 5to en general?',
        'expected_tools' => ['get_course_performance'],
        'min_data' => ['students'],
        'needs' => 'Solo grado, sin sección.',
    ],
    // ── Rendimiento individual (3) ─────────────────────────────────────
    [
        'id' => 5,
        'category' => 'rendimiento_individual',
        'question' => '¿Cómo va Ana Ruiz?',
        'expected_tools' => ['get_student_performance'],
        'min_data' => ['overall_avg_pct', 'subjects'],
        'needs' => 'Buscar alumno por nombre, tabla por materia + promedio general.',
    ],
    [
        'id' => 6,
        'category' => 'rendimiento_individual',
        'question' => 'Dame el rendimiento de Carlos Pérez en Matemática',
        'expected_tools' => ['get_student_performance'],
        'min_data' => ['subjects'],
        'needs' => 'Mismo tool pero filtrar materia en análisis.',
    ],
    [
        'id' => 7,
        'category' => 'rendimiento_individual',
        'question' => '¿Cómo le va a Luis Mora?',
        'expected_tools' => ['get_student_performance'],
        'min_data' => ['overall_avg_pct'],
        'needs' => 'Nombre con variación, matcher fuzzy debe resolverlo.',
    ],
    // ── Rankings (2) ───────────────────────────────────────────────────
    [
        'id' => 8,
        'category' => 'rankings',
        'question' => '¿Quién es el estudiante con mejor rendimiento?',
        'expected_tools' => ['get_rankings'],
        'min_data' => ['ranking'],
        'needs' => 'limit 1, metric average, tabla puesto 1º.',
    ],
    [
        'id' => 9,
        'category' => 'rankings',
        'question' => 'Ranking de promedios de 4to A top 5',
        'expected_tools' => ['get_rankings'],
        'min_data' => ['ranking'],
        'needs' => 'grade 4to, section A, limit 5, orden DESC avg_pct.',
    ],
    // ── Asistencia (2) ─────────────────────────────────────────────────
    [
        'id' => 10,
        'category' => 'asistencia',
        'question' => '¿Cómo está la asistencia de 2do A?',
        'expected_tools' => ['get_attendance'],
        'min_data' => ['students'],
        'needs' => 'Faltas/tardanzas últimos 30 días, tabla por alumno.',
    ],
    [
        'id' => 11,
        'category' => 'asistencia',
        'question' => '¿Quiénes tienen más faltas este mes?',
        'expected_tools' => ['get_rankings'],
        'min_data' => ['ranking'],
        'needs' => 'Metric absences, no promedio.',
    ],
    // ── Alumnos en riesgo (2) ──────────────────────────────────────────
    [
        'id' => 12,
        'category' => 'alumnos_riesgo',
        'question' => '¿Quiénes necesitan atención?',
        'expected_tools' => ['get_at_risk_students', 'get_attendance', 'get_declining_students'],
        'min_data' => ['students'],
        'needs' => 'Combinada: bajo promedio + faltas + declive. Mínimo 2 tools.',
    ],
    [
        'id' => 13,
        'category' => 'alumnos_riesgo',
        'question' => 'Alumnos en riesgo de 4to con promedio menor a 60',
        'expected_tools' => ['get_at_risk_students'],
        'min_data' => ['students'],
        'needs' => 'Filtrar grado 4to, threshold 60, mensaje con %',
    ],
    // ── Tendencias (2) ─────────────────────────────────────────────────
    [
        'id' => 14,
        'category' => 'tendencias',
        'question' => '¿Qué tendencias encuentras este mes?',
        'expected_tools' => ['get_academic_trends'],
        'min_data' => ['trend'],
        'needs' => 'Semanas 4, metric average, tabla semana/promedio.',
    ],
    [
        'id' => 15,
        'category' => 'tendencias',
        'question' => '¿Quién ha bajado su promedio?',
        'expected_tools' => ['get_declining_students'],
        'min_data' => ['students'],
        'needs' => 'Comparar primera mitad vs segunda mitad calificaciones, drop >=5 pts.',
    ],
    // ── Comparación de cursos (2) ──────────────────────────────────────
    [
        'id' => 16,
        'category' => 'comparacion',
        'question' => 'Compara 2do A y 4to A.',
        'expected_tools' => ['compare_courses'],
        'min_data' => ['comparison', 'verdict'],
        'needs' => 'Tabla Alumnos/Cursos/Promedio/Faltas + veredicto Lidera X por Y pts.',
    ],
    [
        'id' => 17,
        'category' => 'comparacion',
        'question' => 'Compara 1ro y 3ro en Matemática',
        'expected_tools' => ['compare_courses'],
        'min_data' => ['comparison'],
        'needs' => 'Filtrar subject Matemática, comparar solo esa materia.',
    ],
    // ── Profesores (1) ─────────────────────────────────────────────────
    [
        'id' => 18,
        'category' => 'profesores',
        'question' => '¿Qué profesores tenemos y qué cursos dan?',
        'expected_tools' => ['get_teachers'],
        'min_data' => ['teachers'],
        'needs' => 'Lista profesores con cursos_count.',
    ],
    // ── Evaluaciones (1) ───────────────────────────────────────────────
    [
        'id' => 19,
        'category' => 'evaluaciones',
        'question' => '¿Qué evaluaciones hay en 4to A?',
        'expected_tools' => ['get_evaluations'],
        'min_data' => ['evaluations'],
        'needs' => 'Filtrar grado 4to sección A, tabla título/estado.',
    ],
    // ── Tareas (1) ─────────────────────────────────────────────────────
    [
        'id' => 20,
        'category' => 'tareas',
        'question' => '¿Qué tareas hay pendientes en 2do?',
        'expected_tools' => ['get_assignments'],
        'min_data' => ['assignments'],
        'needs' => 'Filtrar grade 2do, tareas tipo tarea/homework.',
    ],
    // ── Informes (2) ───────────────────────────────────────────────────
    [
        'id' => 21,
        'category' => 'informes',
        'question' => 'Dame un informe de rendimiento de 4to A.',
        'expected_tools' => ['generate_school_report'],
        'min_data' => ['students', 'performance'],
        'needs' => 'Informe ejecutivo con matrícula, cursos, promedio, tendencias.',
    ],
    [
        'id' => 22,
        'category' => 'informes',
        'question' => 'Resume el estado académico del colegio.',
        'expected_tools' => ['generate_school_report'],
        'min_data' => ['students', 'teachers'],
        'needs' => 'Sin grado, informe de todo el colegio.',
    ],
    // ── Consultas combinadas (1) ───────────────────────────────────────
    [
        'id' => 23,
        'category' => 'combinada',
        'question' => '¿Cómo va Ana Ruiz y qué asistencia tiene?',
        'expected_tools' => ['get_student_performance', 'get_attendance'],
        'min_data' => ['subjects', 'students'],
        'needs' => 'Dos tools en paralelo, combinar rendimiento + asistencia.',
    ],
    // ── Casos borde: inexistentes, sin datos, ambiguas (2) ─────────────
    [
        'id' => 24,
        'category' => 'borde',
        'question' => '¿Cómo va 9no Z?',
        'expected_tools' => ['get_course_performance'],
        'min_data' => [],
        'needs' => 'No hay alumnos en ese grado, debe responder "No hay alumnos registrados..." sin inventar.',
    ],
    [
        'id' => 25,
        'category' => 'borde',
        'question' => '¿Cómo está mi curso?',
        'expected_tools' => [],
        'min_data' => [],
        'needs' => 'Sin contexto UI ni grado, debe pedir aclaración "Dime el curso (ej. 4to A)..." con needs_clarification=true.',
    ],
    // ── Extra: ambigua y fuera de alcance (para robustez) ──────────────
    [
        'id' => 26,
        'category' => 'borde',
        'question' => '¿Cuál es el clima hoy?',
        'expected_tools' => [],
        'min_data' => [],
        'needs' => 'Fuera de alcance, debe responder con mensaje de ayuda sin inventar datos del colegio.',
    ],
];

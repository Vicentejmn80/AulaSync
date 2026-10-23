<?php

/*
|--------------------------------------------------------------------------
| Experiencia interactiva — D-Future Summit
|--------------------------------------------------------------------------
|
| Videos originales, con fondo natural (SHA-256 idéntico a los de la raíz):
| idle       Character_typing_on_laptop_20261020193712.mp4
| wave       Purple_robot_waving_with_laptop_20261020185721.mp4
| celebrate  Character_celebrating_with_confetti_20261020194433.mp4
|
| No se usan los .webm generados para quitar el fondo.
|
| Los premios exclusivos (taller, acceso, sesión, auditoría) solo entran
| en la ruleta si EXPERIENCIA_PREMIOS_EXCLUSIVOS=true. Así no se anuncian
| como premio confirmado mientras no haya cupo real.
|
*/

return [
    'idle_video' => 'videos/experiencia/robot-escribiendo.mp4',
    'wave_video' => 'videos/experiencia/robot-saludando.mp4',
    'celebrate_video' => 'videos/experiencia/robot-festejando.mp4',
    'stage_background' => '#f6f1ff',
    'exclusive_prizes' => filter_var(env('EXPERIENCIA_PREMIOS_EXCLUSIVOS', false), FILTER_VALIDATE_BOOL),
    'notify_email' => env('EXPERIENCIA_NOTIFY_EMAIL', ''),
    'kiosk' => [
        'tabletId' => env('EXPERIENCIA_TABLET_ID', 'tablet-1'),
        'webhookUrl' => env('EXPERIENCIA_WEBHOOK_URL', ''),
        'pin' => env('EXPERIENCIA_ADMIN_PIN', '2580'),
        'volume' => (float) env('EXPERIENCIA_VOLUME', 0.85),
        'idleMs' => 45000,
        'questionMs' => 15000,
        'questionsUrl' => '/data/questions.json',
    ],
    'prizes' => [
        [
            'id' => 'kit-ia-docentes',
            'kind' => 'digital',
            'weight' => 30,
            'icon' => '✨',
            'short' => 'Kit IA',
            'name' => 'Kit digital de IA para docentes',
            'description' => 'Prompts y recursos para preparar clases, crear evaluaciones, diseñar rúbricas y reducir tareas repetitivas.',
            'condition' => 'Recurso digital. Te lo enviamos al correo o al teléfono que indiques.',
            'color' => '#6C5CE7',
        ],
        [
            'id' => 'kit-plantillas',
            'kind' => 'digital',
            'weight' => 25,
            'icon' => '📋',
            'short' => 'Plantillas',
            'name' => 'Kit de plantillas docentes',
            'description' => 'Plantillas prácticas para planificación, evaluaciones, rúbricas y organización del trabajo académico.',
            'condition' => 'Recurso digital. Te lo enviamos al correo o al teléfono que indiques.',
            'color' => '#7C3AED',
        ],
        [
            'id' => 'guia-ia-colegios',
            'kind' => 'digital',
            'weight' => 20,
            'icon' => '📘',
            'short' => 'Guía IA',
            'name' => 'Guía exclusiva de IA para colegios',
            'description' => 'Ideas prácticas para aplicar inteligencia artificial en la gestión escolar y reducir el trabajo administrativo.',
            'condition' => 'Recurso digital. Te lo enviamos al correo o al teléfono que indiques.',
            'color' => '#8B5CF6',
        ],
        [
            'id' => 'taller-grupal',
            'kind' => 'exclusive',
            'weight' => 12,
            'quota' => (int) env('EXPERIENCIA_QUOTA_TALLER', 8),
            'icon' => '🎯',
            'short' => 'Taller',
            'name' => 'Taller grupal exclusivo de IA para docentes',
            'description' => 'Invitación a un taller práctico para usar IA en el trabajo educativo.',
            'condition' => 'Sujeto a cupo. Te confirmamos fecha y disponibilidad antes de agendar. No es una plaza garantizada hasta esa confirmación.',
            'color' => '#E8A317',
        ],
        [
            'id' => 'acceso-anticipado',
            'kind' => 'exclusive',
            'weight' => 8,
            'quota' => (int) env('EXPERIENCIA_QUOTA_ACCESO', 20),
            'icon' => '🚀',
            'short' => 'Anticipado',
            'name' => 'Acceso anticipado a AulaSync',
            'description' => 'Oportunidad de conocer funciones nuevas y participar en las pruebas de la plataforma, según la disponibilidad del programa piloto.',
            'condition' => 'Depende del cupo del piloto. Te escribimos para confirmar si hay un lugar disponible.',
            'color' => '#D97706',
        ],
        [
            'id' => 'auditoria-administrativa',
            'kind' => 'exclusive',
            'weight' => 3,
            'quota' => (int) env('EXPERIENCIA_QUOTA_AUDITORIA', 2),
            'icon' => '🔎',
            'short' => 'Auditoría',
            'name' => 'Auditoría administrativa gratuita para tu colegio',
            'description' => 'Revisión inicial de procesos administrativos para detectar tareas repetitivas, cuellos de botella y oportunidades de automatización con IA.',
            'condition' => 'Cupo muy limitado. Es una revisión inicial, no una consultoría continua, y solo se agenda si hay disponibilidad.',
            'color' => '#B45309',
        ],
        [
            'id' => 'sesion-directivos',
            'kind' => 'exclusive',
            'weight' => 2,
            'quota' => (int) env('EXPERIENCIA_QUOTA_SESION', 2),
            'icon' => '🤝',
            'short' => 'Sesión',
            'name' => 'Sesión personalizada de IA para directivos',
            'description' => 'Sesión para identificar procesos administrativos que podrían automatizarse y explorar oportunidades de mejora en el colegio.',
            'condition' => 'Cupo muy limitado y sujeto a agenda. Te confirmamos si podemos realizarla antes de darla por ganada.',
            'color' => '#9A3412',
        ],
    ],
];

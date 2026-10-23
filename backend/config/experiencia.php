<?php

/*
|--------------------------------------------------------------------------
| Experiencia interactiva — D-Future Summit
|--------------------------------------------------------------------------
|
| idle_video       Robot escribiendo.  Character_typing_on_laptop_20261020193712.mp4
| wave_video       Robot saludando.    Purple_robot_waving_with_laptop_20261020185721.mp4
| celebrate_video  Robot festejando.   Character_celebrating_with_confetti_20261020194433.mp4
|
| Los mp4 originales se conservan. Si existen los .webm con alfa, el quiosco
| los usa en Chrome y Android. El .mov con alfa es para Safari/iPad.
|
*/

return [
    'idle_video' => 'videos/experiencia/robot-escribiendo.mp4',
    'idle_webm' => 'videos/experiencia/robot-escribiendo.webm',
    'idle_mov' => 'videos/experiencia/robot-escribiendo.mov',
    'idle_poster' => 'videos/experiencia/aulo-espera.png',
    'wave_video' => 'videos/experiencia/robot-saludando.mp4',
    'wave_webm' => 'videos/experiencia/robot-saludando.webm',
    'wave_mov' => 'videos/experiencia/robot-saludando.mov',
    'celebrate_video' => 'videos/experiencia/robot-festejando.mp4',
    'celebrate_webm' => 'videos/experiencia/robot-festejando.webm',
    'celebrate_mov' => 'videos/experiencia/robot-festejando.mov',
    'stage_background' => '#f6f1ff',
    'kiosk' => [
        'tabletId' => env('EXPERIENCIA_TABLET_ID', 'tablet-1'),
        'webhookUrl' => env('EXPERIENCIA_WEBHOOK_URL', ''),
        'pin' => env('EXPERIENCIA_ADMIN_PIN', '2580'),
        'volume' => (float) env('EXPERIENCIA_VOLUME', 0.85),
        'idleMs' => 45000,
        'questionMs' => 15000,
        'questionsUrl' => '/data/questions.json',
        'prizesUrl' => '/data/prizes.json',
    ],
];

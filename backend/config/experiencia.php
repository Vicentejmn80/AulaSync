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
| Los tres viven en backend/public/videos/experiencia/.
| El fondo de los clips es blanco. stage_background debe seguir ese color.
|
*/

return [
    'idle_video' => 'videos/experiencia/robot-escribiendo.mp4',
    'wave_video' => 'videos/experiencia/robot-saludando.mp4',
    'celebrate_video' => 'videos/experiencia/robot-festejando.mp4',
    'stage_background' => '#ffffff',
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

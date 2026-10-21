<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExperienciaIdleTest extends TestCase
{
    public function test_public_footer_links_to_the_idle_screen(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Experiencia interactiva', false);
        $response->assertSee('href="'.route('experiencia').'"', false);
        $response->assertSee('Iniciar Sesión', false);
    }

    public function test_idle_screen_loops_typing_and_prepares_the_wave(): void
    {
        $response = $this->get('/experiencia');

        $response->assertOk();
        $response->assertSee('data-state="idle"', false);
        $response->assertSee('Toca para jugar y gana un premio', false);
        $response->assertSee('Acepto que AulaSync me contacte por WhatsApp', false);
        $response->assertSee('questions.json', false);
        $response->assertSee('prizes.json', false);
        $response->assertSee('Eres Profesor?', false);
        $response->assertSee('Eres Director?', false);
        $response->assertSee('¡Girar Ruleta!', false);
        $response->assertSee('Reclamar mi premio', false);
        $response->assertSee('id="experiencia-idle-video"', false);
        $response->assertSee('id="experiencia-wave-video"', false);
        $response->assertSee('id="experiencia-celebrate-video"', false);
        $response->assertSee('videos/experiencia/robot-escribiendo.mp4', false);
        $response->assertSee('videos/experiencia/robot-saludando.mp4', false);
        $response->assertSee('videos/experiencia/robot-festejando.mp4', false);
        $response->assertSee('loop', false);
        $response->assertSee('muted', false);
        $response->assertSee('autoplay', false);
        $response->assertSee('playsinline', false);
        $response->assertDontSee('name="email"', false);
    }

    public function test_idle_screen_shows_a_fallback_when_the_videos_are_missing(): void
    {
        config([
            'experiencia.idle_video' => null,
            'experiencia.wave_video' => null,
            'experiencia.celebrate_video' => null,
        ]);

        $response = $this->get('/experiencia');

        $response->assertOk();
        $response->assertSee('data-state="idle"', false);
        $response->assertSee('config/experiencia.php', false);
        $response->assertDontSee('id="experiencia-idle-video"', false);
    }

    public function test_idle_screen_plays_the_configured_video_when_the_file_exists(): void
    {
        $relative = 'videos/experiencia/idle-test-fixture.mp4';
        $absolute = public_path($relative);

        if (! is_dir(dirname($absolute))) {
            mkdir(dirname($absolute), 0777, true);
        }

        file_put_contents($absolute, 'fixture');
        config([
            'experiencia.idle_video' => $relative,
            'experiencia.wave_video' => null,
            'experiencia.celebrate_video' => null,
        ]);

        try {
            $response = $this->get('/experiencia');

            $response->assertOk();
            $response->assertSee('id="experiencia-idle-video"', false);
            $response->assertSee('/'.$relative, false);
            $response->assertDontSee('id="experiencia-wave-video"', false);
        } finally {
            @unlink($absolute);
        }
    }

    public function test_login_route_still_loads(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Iniciar Sesión', false);
    }
}

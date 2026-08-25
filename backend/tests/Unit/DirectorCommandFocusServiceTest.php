<?php

namespace Tests\Unit;

use App\Services\DirectorCommandFocusService;
use Tests\TestCase;

class DirectorCommandFocusServiceTest extends TestCase
{
    public function test_highlights_necesito_que_and_create_verb_in_rambling_text(): void
    {
        $text = 'Hola buenos días, espero que estés bien, mira el colegio está funcionando normal, '
            .'pero necesito que crees a los siguientes profesores: Jorge Alarcon (ingles de 1ro a 6to grado).';

        $focus = app(DirectorCommandFocusService::class)->extract($text);

        $this->assertContains('necesito que', $focus['cues']);
        $this->assertNotEmpty($focus['verbs']);
        $this->assertStringContainsStringIgnoringCase('crees a los siguientes profesores', $focus['working']);
        $this->assertStringContainsStringIgnoringCase('Jorge Alarcon', $focus['working']);
        $this->assertStringNotContainsStringIgnoringCase('espero que estés bien', $focus['working']);
        $this->assertStringContainsString('ÓRDENES CLAVE', $focus['for_model']);
    }

    public function test_keeps_short_direct_commands_intact(): void
    {
        $text = 'Crea al profesor Vicente Maduro y asígnale Inglés de 1ro a 6to';
        $focus = app(DirectorCommandFocusService::class)->extract($text);

        $this->assertStringContainsStringIgnoringCase('Vicente Maduro', $focus['working']);
        $this->assertStringContainsStringIgnoringCase('Inglés', $focus['working']);
    }

    public function test_captures_modify_add_delete_increase_decrease_verbs(): void
    {
        $text = 'Oye, más tarde hablamos, ahora quiero que modifiques el curso de física, agregues un alumno, elimines la falta y aumentes el cupo.';
        $focus = app(DirectorCommandFocusService::class)->extract($text);

        $this->assertContains('quiero que', $focus['cues']);
        $working = mb_strtolower($focus['working']);
        $this->assertStringContainsString('modifiques', $working);
        $this->assertTrue(
            str_contains($working, 'agregues') || str_contains($working, 'agrega'),
            $focus['working']
        );
    }
}

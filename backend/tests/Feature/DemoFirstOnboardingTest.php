<?php

namespace Tests\Feature;

use App\Models\DemoRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoFirstOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_has_demo_form_and_no_public_register(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Solicitar Demo', false);
        $response->assertSee('Iniciar Sesión', false);
        $response->assertSee('https://formspree.io/f/mjybqkok', false);
        $response->assertSee('name="nombre"', false);
        $response->assertSee('name="apellido"', false);
        $response->assertSee('name="email"', false);
        $response->assertSee('name="telefono"', false);
        $response->assertSee('name="nombre_colegio"', false);
        $response->assertSee('name="estado_region"', false);
        $response->assertSee('¡Gracias! Tu solicitud ha sido enviada. Te contactaremos pronto.', false);
        $response->assertDontSee('url(\'register\')');
        $response->assertDontSee('Crear Cuenta');
        $response->assertDontSee('Regístrate aquí');
    }

    public function test_demo_request_is_stored_locally(): void
    {
        $this->postJson('/solicitar-demo', [
            'nombre' => 'María',
            'apellido' => 'Pérez',
            'email' => 'maria@colegio.test',
            'telefono' => '04141234567',
            'nombre_colegio' => 'U.E. Demo',
            'estado_region' => 'Miranda',
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('demo_requests', [
            'name' => 'María',
            'last_name' => 'Pérez',
            'email' => 'maria@colegio.test',
            'school_name' => 'U.E. Demo',
            'estado_region' => 'Miranda',
        ]);
        $this->assertSame(1, DemoRequest::count());
    }
}

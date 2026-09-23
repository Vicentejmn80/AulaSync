<?php

namespace Tests\Feature;

use App\Models\Colegio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherHubPerformanceUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_login_link_does_not_wait_on_prefetch(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('>Iniciar Sesión</a>', false);
        $response->assertDontSee('data-login-link', false);
        $response->assertDontSee('pointer-events: none', false);
        $response->assertSee('touch-action: manipulation', false);
        $response->assertSee('href="'.url('/login').'"', false);
        $response->assertDontSee('speculationrules', false);
        $response->assertDontSee('rel="prefetch"', false);
        $this->assertStringNotContainsString('rel=prefetch', (string) $response->headers->get('Link'));

        $worker = file_get_contents(public_path('sw.js'));
        $this->assertStringContainsString('navigationPreload', $worker);
        $this->assertStringContainsString("cache: \"no-store\"", $worker);
        $this->assertStringContainsString('"/login"', $worker);
    }

    public function test_login_shows_optimistic_skeleton_and_touch_targets(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('id="sync-overlay"', false);
        $response->assertSee('as.login.optimistic', false);
        $response->assertSee('autocomplete="username"', false);
        $response->assertSee('inputmode="email"', false);
        $response->assertSee('min-height: 48px', false);
    }

    public function test_teacher_login_returns_without_blocking_on_invite_claim(): void
    {
        $user = User::factory()->create([
            'role' => 'profesor',
            'onboarding_completed' => true,
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect('/teacher/hub');
        $this->assertFalse((bool) session('teacher.hub.claimed'));

        $this->post('/logout');

        $ajax = $this->postJson('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $ajax->assertOk();
        $ajax->assertJsonPath('redirect', '/teacher/hub');
    }

    public function test_teacher_hub_keeps_web_visuals_and_scrolls_on_mobile(): void
    {
        $colegio = Colegio::create([
            'name' => 'Colegio Móvil',
            'invite_code' => 'MOV-2001',
            'codes_pin' => Colegio::hashPinFromInvite('MOV-2001'),
        ]);

        $teacher = User::factory()->create([
            'role' => 'profesor',
            'colegio_id' => $colegio->id,
            'onboarding_completed' => true,
        ]);

        $first = $this->actingAs($teacher)->get('/teacher/hub');

        $first->assertOk();
        $first->assertSee('fa-bars', false);
        $first->assertSee('calendar-grid', false);
        $first->assertSee('Menú de navegación', false);
        $first->assertDontSee('teacher-thumb-nav', false);
        $first->assertDontSee('calendar-agenda', false);
        $first->assertSee('hydrateFromCache()', false);
        $first->assertSee('Promise.all([sidebarPromise', false);
        $first->assertSee('overflow-x: auto', false);
        $first->assertHeader('Cache-Control');
        $this->assertStringContainsString('private', strtolower((string) $first->headers->get('Cache-Control')));
        $this->assertTrue(session('teacher.hub.bootstrapped'));

        $second = $this->actingAs($teacher)->get('/teacher/hub');
        $second->assertOk();
        $this->assertTrue(session('teacher.hub.bootstrapped'));
    }
}

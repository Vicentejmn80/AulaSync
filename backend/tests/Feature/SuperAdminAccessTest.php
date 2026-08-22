<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_onboarding_page_renders_without_named_api_routes(): void
    {
        $user = User::factory()->create([
            'role' => 'profesor',
            'onboarding_completed' => false,
        ]);

        $this->actingAs($user)
            ->get('/onboarding')
            ->assertOk()
            ->assertSee('/api/validate-school-code', false)
            ->assertSee('/api/validate-family-code', false)
            ->assertDontSee('Route [api.validate-school-code] not defined');
    }

    public function test_vicente_login_is_promoted_to_super_admin_and_skips_onboarding(): void
    {
        $user = User::factory()->create([
            'email' => 'vicentejmn80@gmail.com',
            'role' => 'director',
            'onboarding_completed' => false,
            'password' => 'password',
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect('/super-admin');
        $this->assertSame('super_admin', $user->fresh()->role);
        $this->assertTrue($user->fresh()->onboarding_completed);
    }

    public function test_super_admin_can_open_panel_and_is_redirected_away_from_onboarding(): void
    {
        $user = User::factory()->create([
            'role' => 'super_admin',
            'onboarding_completed' => false,
        ]);

        $this->actingAs($user)->get('/super-admin')->assertOk();
        $this->actingAs($user)->get('/onboarding')->assertRedirect('/super-admin');
        $this->actingAs($user)->get('/dashboard')->assertRedirect('/super-admin');
    }
}

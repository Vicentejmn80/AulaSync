<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Colegio;
use App\Models\Course;
use App\Models\Evaluation;
use App\Models\ProductEvent;
use App\Models\User;
use App\Services\ProductTelemetry;
use App\Services\SuperAdminAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_uses_real_counts_and_hides_from_teachers(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'onboarding_completed' => true, 'last_login_at' => now()]);
        $director = User::factory()->create(['role' => 'director', 'onboarding_completed' => true, 'last_login_at' => now()]);
        $teacher = User::factory()->create(['role' => 'profesor', 'onboarding_completed' => true]);
        $colegio = Colegio::create([
            'name' => 'Colegio Central',
            'invite_code' => 'CEN-SA02',
            'codes_pin' => Colegio::hashPinFromInvite('CEN-SA02'),
            'director_user_id' => $director->id,
        ]);
        $director->update(['colegio_id' => $colegio->id]);
        $teacher->update(['colegio_id' => $colegio->id]);
        $course = Course::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => $teacher->id,
            'subject_name' => 'Matemáticas',
            'grade' => '4to',
            'section' => 'A',
            'school_year' => '2026-2027',
            'invite_code' => 'SA-MATH',
        ]);

        Activity::create([
            'teacher_id' => $teacher->id,
            'course_id' => $course->id,
            'colegio_id' => $colegio->id,
            'title' => 'Clase real',
            'type' => 'clase',
        ]);

        app(ProductTelemetry::class)->record([
            'user' => $director,
            'source' => 'director_ai',
            'event' => 'ai_action',
            'action' => 'query_academic',
            'status' => 'success',
            'duration_ms' => 120,
        ]);

        $this->actingAs($admin)->get('/super-admin')
            ->assertOk()
            ->assertSee('Colegios registrados')
            ->assertSee('1');

        $this->actingAs($admin)->get('/super-admin/usage')
            ->assertOk()
            ->assertSee('Hizo una consulta académica');

        $this->actingAs($admin)->get('/super-admin/insights')
            ->assertOk()
            ->assertSee('Hallazgos');

        $this->actingAs($admin)->get('/super-admin/colegios/'.$colegio->id)
            ->assertOk()
            ->assertSee('Colegio Central')
            ->assertSee($director->name);

        $this->actingAs($teacher)->get('/super-admin')->assertRedirect();
        $this->assertDatabaseHas('product_events', [
            'action' => 'query_academic',
            'colegio_id' => $colegio->id,
        ]);
        $this->assertNull(ProductEvent::first()?->meta['prompt'] ?? null);
    }

    public function test_usage_and_insights_count_ai_evaluations_without_boolean_integer_sql(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'onboarding_completed' => true]);
        $teacher = User::factory()->create(['role' => 'profesor', 'onboarding_completed' => true]);
        Evaluation::create([
            'teacher_id' => $teacher->id,
            'title' => 'Parcial con IA',
            'status' => 'published',
            'generated_by_ai' => true,
        ]);

        $usage = app(SuperAdminAnalyticsService::class)->usage(
            app(SuperAdminAnalyticsService::class)->filters([])
        );
        $this->assertSame(1, $usage['evaluaciones_ia']);

        $this->actingAs($admin)->get('/super-admin/usage')->assertOk()->assertSee('Evaluaciones con IA');
        $this->actingAs($admin)->get('/super-admin/insights')->assertOk()->assertSee('Hallazgos');
    }

    public function test_login_writes_last_seen_and_does_not_store_prompt_text(): void
    {
        $user = User::factory()->create([
            'role' => 'profesor',
            'onboarding_completed' => true,
            'password' => 'password',
        ]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect();
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas('product_events', ['event' => 'login', 'user_id' => $user->id]);
    }
}

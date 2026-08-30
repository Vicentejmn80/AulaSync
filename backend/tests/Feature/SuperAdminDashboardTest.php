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
use Illuminate\Support\Facades\DB;
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
            ->assertSee('Colegio Central')
            ->assertSee('school-card')
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

    public function test_overview_counts_sessions_in_last_fifteen_minutes_with_user_id(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'onboarding_completed' => true]);
        $teacher = User::factory()->create(['role' => 'profesor', 'onboarding_completed' => true]);

        DB::table('sessions')->insert([
            [
                'id' => 'sess-new',
                'user_id' => $teacher->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'phpunit',
                'payload' => 'x',
                'last_activity' => now()->subMinutes(5)->timestamp,
            ],
            [
                'id' => 'sess-old',
                'user_id' => $teacher->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'phpunit',
                'payload' => 'x',
                'last_activity' => now()->subMinutes(20)->timestamp,
            ],
            [
                'id' => 'sess-anon',
                'user_id' => null,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'phpunit',
                'payload' => 'x',
                'last_activity' => now()->subMinutes(1)->timestamp,
            ],
        ]);

        $overview = app(SuperAdminAnalyticsService::class)->overview(
            app(SuperAdminAnalyticsService::class)->filters([])
        );

        $this->assertSame(1, $overview['sesiones_activas']);
        $this->actingAs($admin)->get('/super-admin')->assertOk()->assertSee('Sesiones abiertas (ult. 15 min)');
    }

    public function test_overview_active_roles_use_logs_in_selected_period(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'onboarding_completed' => true]);
        $director = User::factory()->create(['role' => 'director', 'onboarding_completed' => true, 'colegio_id' => null]);
        $teacher = User::factory()->create(['role' => 'profesor', 'onboarding_completed' => true, 'colegio_id' => null]);

        ProductEvent::create([
            'user_id' => $director->id,
            'role' => 'director',
            'source' => 'director_data_agent',
            'event' => 'ai_action',
            'action' => 'get_students',
            'status' => 'success',
            'created_at' => now()->subDays(2),
        ]);
        ProductEvent::create([
            'user_id' => $teacher->id,
            'role' => 'profesor',
            'source' => 'teacher_ai',
            'event' => 'ai_action',
            'action' => 'createActivity',
            'status' => 'success',
            'created_at' => now()->subDays(1),
        ]);

        $filters = app(SuperAdminAnalyticsService::class)->filters([
            'from' => now()->subDays(7)->toDateString(),
            'to' => now()->toDateString(),
        ]);
        $overview = app(SuperAdminAnalyticsService::class)->overview($filters);

        $this->assertSame(1, $overview['directores_activos']);
        $this->assertSame(1, $overview['docentes_activos']);
        $this->actingAs($admin)->get('/super-admin')->assertOk();
    }

    public function test_school_dossiers_keep_metrics_per_colegio(): void
    {
        $alpha = Colegio::create([
            'name' => 'Colegio Alpha',
            'invite_code' => 'ALP-0001',
            'codes_pin' => Colegio::hashPinFromInvite('ALP-0001'),
        ]);
        $beta = Colegio::create([
            'name' => 'Colegio Beta',
            'invite_code' => 'BET-0001',
            'codes_pin' => Colegio::hashPinFromInvite('BET-0001'),
        ]);
        $directorA = User::factory()->create([
            'role' => 'director',
            'colegio_id' => $alpha->id,
            'onboarding_completed' => true,
            'last_login_at' => now(),
        ]);
        $directorB = User::factory()->create([
            'role' => 'director',
            'colegio_id' => $beta->id,
            'onboarding_completed' => true,
            'last_login_at' => now(),
        ]);

        ProductEvent::create([
            'user_id' => $directorA->id,
            'colegio_id' => $alpha->id,
            'role' => 'director',
            'source' => 'director_ai',
            'event' => 'ai_action',
            'action' => 'create_teacher',
            'status' => 'success',
        ]);
        ProductEvent::create([
            'user_id' => $directorB->id,
            'colegio_id' => $beta->id,
            'role' => 'director',
            'source' => 'director_data_agent',
            'event' => 'ai_action',
            'action' => 'get_students',
            'status' => 'success',
        ]);

        $analytics = app(SuperAdminAnalyticsService::class);
        $dossiers = $analytics->schoolDossiers($analytics->filters([]), 'usage');
        $alphaUsage = $dossiers->firstWhere('name', 'Colegio Alpha')['usage'];
        $betaUsage = $dossiers->firstWhere('name', 'Colegio Beta')['usage'];

        $this->assertTrue($alphaUsage['mas_usadas']->contains(fn ($row) => $row->action === 'create_teacher'));
        $this->assertFalse($alphaUsage['mas_usadas']->contains(fn ($row) => $row->action === 'get_students'));
        $this->assertTrue($betaUsage['mas_usadas']->contains(fn ($row) => $row->action === 'get_students'));
        $this->assertFalse($betaUsage['mas_usadas']->contains(fn ($row) => $row->action === 'create_teacher'));

        $this->actingAs(User::factory()->create(['role' => 'super_admin', 'onboarding_completed' => true]))
            ->get('/super-admin/usage')
            ->assertOk()
            ->assertSee('Colegio Alpha')
            ->assertSee('Colegio Beta')
            ->assertSee('Crear docente')
            ->assertSee('Consultó la lista de alumnos');
    }
}

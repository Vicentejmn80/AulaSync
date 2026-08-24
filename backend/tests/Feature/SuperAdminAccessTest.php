<?php

namespace Tests\Feature;

use App\Models\Colegio;
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

    public function test_super_admin_dashboard_lists_users_and_can_enter_a_school(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'onboarding_completed' => true,
        ]);
        $director = User::factory()->create([
            'role' => 'director',
            'onboarding_completed' => true,
        ]);
        $colegio = Colegio::create([
            'name' => 'Colegio Central',
            'invite_code' => 'CEN-SA01',
            'codes_pin' => Colegio::hashPinFromInvite('CEN-SA01'),
            'director_user_id' => $director->id,
        ]);
        $director->update(['colegio_id' => $colegio->id]);

        $this->actingAs($admin)->get('/super-admin')
            ->assertOk()
            ->assertSee('Overview');

        $this->actingAs($admin)->get('/super-admin/schools')
            ->assertOk()
            ->assertSee('Colegio Central');

        $this->actingAs($admin)->get('/super-admin/users')
            ->assertOk()
            ->assertSee($director->email);

        $this->actingAs($admin)
            ->post('/super-admin/colegios/'.$colegio->id.'/enter')
            ->assertRedirect('/director/dashboard');

        $this->assertSame($colegio->id, (int) $admin->fresh()->colegio_id);
    }

    public function test_teacher_cannot_open_super_admin_users(): void
    {
        $teacher = User::factory()->create([
            'role' => 'profesor',
            'onboarding_completed' => true,
        ]);

        $this->actingAs($teacher)->get('/super-admin/users')->assertRedirect();
    }

    public function test_super_admin_can_delete_course_teacher_and_student(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'onboarding_completed' => true,
        ]);
        $director = User::factory()->create([
            'role' => 'director',
            'onboarding_completed' => true,
        ]);
        $teacher = User::factory()->create([
            'role' => 'profesor',
            'onboarding_completed' => true,
            'name' => 'Prof. Delete',
        ]);
        $colegio = Colegio::create([
            'name' => 'Colegio Borrar',
            'invite_code' => 'DEL-SA01',
            'codes_pin' => Colegio::hashPinFromInvite('DEL-SA01'),
            'director_user_id' => $director->id,
        ]);
        $director->update(['colegio_id' => $colegio->id]);
        $teacher->update(['colegio_id' => $colegio->id]);

        $course = \App\Models\Course::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => $teacher->id,
            'subject_name' => 'Historia',
            'grade' => '3ro',
            'section' => 'A',
            'school_year' => '2026-2027',
            'invite_code' => 'DEL-HIS',
        ]);
        $student = \App\Models\Student::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => $teacher->id,
            'name' => 'Ana Borrar',
            'grade' => '3ro',
            'section' => 'A',
            'family_code' => 'FAM-DEL1',
        ]);
        $course->students()->attach($student->id);

        $this->actingAs($admin)
            ->delete(route('super-admin.colegios.cursos.destroy', [$colegio, $course]))
            ->assertRedirect();
        $this->assertDatabaseMissing('courses', ['id' => $course->id]);
        $this->assertDatabaseMissing('course_student', ['student_id' => $student->id]);

        $this->actingAs($admin)
            ->delete(route('super-admin.colegios.alumnos.destroy', [$colegio, $student]))
            ->assertRedirect();
        $this->assertDatabaseMissing('students', ['id' => $student->id]);

        $this->actingAs($admin)
            ->delete(route('super-admin.colegios.profesores.destroy', [$colegio, $teacher]))
            ->assertRedirect();
        $this->assertDatabaseMissing('users', ['id' => $teacher->id]);
    }

    public function test_teacher_cannot_delete_school_entities(): void
    {
        $teacher = User::factory()->create([
            'role' => 'profesor',
            'onboarding_completed' => true,
        ]);
        $director = User::factory()->create(['role' => 'director', 'onboarding_completed' => true]);
        $colegio = Colegio::create([
            'name' => 'Colegio Cerrado',
            'invite_code' => 'DEL-NO01',
            'codes_pin' => Colegio::hashPinFromInvite('DEL-NO01'),
            'director_user_id' => $director->id,
        ]);
        $course = \App\Models\Course::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => $teacher->id,
            'subject_name' => 'Arte',
            'grade' => '1ro',
            'section' => 'A',
            'school_year' => '2026-2027',
            'invite_code' => 'DEL-ART',
        ]);

        $this->actingAs($teacher)
            ->delete(route('super-admin.colegios.cursos.destroy', [$colegio, $course]))
            ->assertRedirect();
        $this->assertDatabaseHas('courses', ['id' => $course->id]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Colegio;
use App\Models\Course;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FounderCenterManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_edits_the_same_user_and_the_new_email_inherits_the_account(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'onboarding_completed' => true,
        ]);
        $director = User::factory()->create([
            'name' => 'Director Viejo',
            'email' => 'viejo@colegio.edu',
            'role' => 'director',
            'password' => 'password',
            'onboarding_completed' => true,
        ]);

        $this->actingAs($admin)->patch('/super-admin/users/'.$director->id, [
            'name' => 'Director Nuevo',
            'email' => 'nuevo@colegio.edu',
            'role' => 'director',
            'password' => 'clave-nueva',
            'confirm_sensitive' => '1',
            'onboarding_completed' => '1',
        ])->assertRedirect();

        $fresh = User::query()->find($director->id);
        $this->assertNotNull($fresh);
        $this->assertSame('Director Nuevo', $fresh->name);
        $this->assertSame('nuevo@colegio.edu', $fresh->email);
        $this->assertSame('director', $fresh->role);
        $this->assertTrue(Hash::check('clave-nueva', $fresh->password));
        $this->assertDatabaseHas('founder_audit_logs', [
            'action' => 'user.updated',
            'target_id' => $director->id,
            'actor_user_id' => $admin->id,
        ]);

        $this->post('/logout');

        $this->post('/login', [
            'email' => 'viejo@colegio.edu',
            'password' => 'clave-nueva',
        ]);
        $this->assertGuest();

        $this->post('/login', [
            'email' => 'nuevo@colegio.edu',
            'password' => 'clave-nueva',
        ]);
        $this->assertAuthenticatedAs($fresh);
    }

    public function test_email_or_password_change_requires_confirmation(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'onboarding_completed' => true,
        ]);
        $director = User::factory()->create([
            'email' => 'viejo@colegio.edu',
            'role' => 'director',
            'onboarding_completed' => true,
        ]);

        $this->actingAs($admin)->patch('/super-admin/users/'.$director->id, [
            'name' => $director->name,
            'email' => 'nuevo@colegio.edu',
            'role' => 'director',
        ])->assertSessionHas('error');

        $this->assertSame('viejo@colegio.edu', $director->fresh()->email);
    }

    public function test_school_backup_and_delete_remove_the_school_without_deleting_super_admin(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'onboarding_completed' => true,
        ]);
        $director = User::factory()->create([
            'role' => 'director',
            'onboarding_completed' => true,
            'email' => 'director-borrar@colegio.edu',
        ]);
        $colegio = Colegio::create([
            'name' => 'Colegio Para Borrar',
            'invite_code' => 'DEL-FULL',
            'codes_pin' => Colegio::hashPinFromInvite('DEL-FULL'),
            'director_user_id' => $director->id,
        ]);
        $director->update(['colegio_id' => $colegio->id]);
        $admin->update(['colegio_id' => $colegio->id]);

        $course = Course::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => $director->id,
            'subject_name' => 'Historia',
            'grade' => '3ro',
            'section' => 'A',
            'school_year' => '2026-2027',
            'invite_code' => 'DEL-HIS2',
        ]);
        $student = Student::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => $director->id,
            'name' => 'Ana Borrar',
            'grade' => '3ro',
            'section' => 'A',
            'family_code' => 'FAM-DEL2',
        ]);

        $this->actingAs($admin)
            ->get('/super-admin/colegios/'.$colegio->id.'/respaldo')
            ->assertOk()
            ->assertHeader('content-disposition')
            ->assertJsonPath('colegio.name', 'Colegio Para Borrar')
            ->assertJsonPath('students.0.name', 'Ana Borrar')
            ->assertJsonMissingPath('users.0.password');

        $this->actingAs($admin)
            ->delete('/super-admin/colegios/'.$colegio->id, ['confirm_name' => 'otro nombre'])
            ->assertSessionHas('error');
        $this->assertDatabaseHas('colegios', ['id' => $colegio->id]);

        $this->actingAs($admin)
            ->delete('/super-admin/colegios/'.$colegio->id, ['confirm_name' => 'Colegio Para Borrar'])
            ->assertRedirect('/super-admin/schools');

        $this->assertDatabaseMissing('colegios', ['id' => $colegio->id]);
        $this->assertDatabaseMissing('users', ['id' => $director->id]);
        $this->assertDatabaseMissing('courses', ['id' => $course->id]);
        $this->assertDatabaseMissing('students', ['id' => $student->id]);
        $this->assertNotNull(User::query()->find($admin->id));
        $this->assertNull($admin->fresh()->colegio_id);
        $this->assertDatabaseHas('founder_audit_logs', [
            'action' => 'school.deleted',
            'target_id' => $colegio->id,
            'actor_user_id' => $admin->id,
        ]);
    }

    public function test_director_cannot_delete_a_school_or_edit_users_from_founder_center(): void
    {
        $director = User::factory()->create([
            'role' => 'director',
            'onboarding_completed' => true,
        ]);
        $other = User::factory()->create([
            'role' => 'profesor',
            'onboarding_completed' => true,
        ]);
        $colegio = Colegio::create([
            'name' => 'Colegio Protegido',
            'invite_code' => 'PROT-01',
            'codes_pin' => Colegio::hashPinFromInvite('PROT-01'),
        ]);

        $this->actingAs($director)
            ->delete('/super-admin/colegios/'.$colegio->id, ['confirm_name' => $colegio->name])
            ->assertRedirect('/login');

        $this->actingAs($director)
            ->patch('/super-admin/users/'.$other->id, [
                'name' => 'Hack',
                'email' => 'hack@colegio.edu',
                'role' => 'director',
                'confirm_sensitive' => '1',
            ])
            ->assertRedirect('/login');

        $this->assertDatabaseHas('colegios', ['id' => $colegio->id]);
        $this->assertSame($other->email, $other->fresh()->email);
    }
}

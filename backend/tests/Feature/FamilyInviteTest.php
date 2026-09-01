<?php

namespace Tests\Feature;

use App\Models\Colegio;
use App\Models\FamilyInvite;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FamilyInviteTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_student_returns_a_reusable_family_link(): void
    {
        [$director] = $this->school();

        $response = $this->actingAs($director)->postJson(route('director.gestion.students.store'), [
            'name' => 'Angel Marin',
            'grade' => '3ro',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('family_invite.name', 'Angel Marin');

        $link = $response->json('family_invite.invitation_link');
        $this->assertStringContainsString('/familia/unirse?', $link);
        $this->assertStringContainsString('code=FAM-', $link);
        $this->assertDatabaseCount('family_invites', 1);
    }

    public function test_a_sibling_reuses_the_same_family_invite(): void
    {
        [$director] = $this->school();

        $first = $this->actingAs($director)->postJson(route('director.gestion.students.store'), [
            'name' => 'Angel Marin',
            'grade' => '3ro',
        ])->json('student');

        $second = $this->actingAs($director)->postJson(route('director.gestion.students.store'), [
            'name' => 'Ana Marin',
            'grade' => '1ro',
            'sibling_student_id' => $first['id'],
        ]);

        $second->assertOk();
        $this->assertSame(
            $this->actingAs($director)->getJson(route('director.gestion.students.family-invite', $first['id']))->json('family_invite.invite_code'),
            $second->json('family_invite.invite_code')
        );
        $this->assertDatabaseCount('family_invites', 1);
        $this->assertCount(2, $second->json('family_invite.students'));
    }

    public function test_parent_registers_from_the_link_and_sees_the_child(): void
    {
        [$director, $colegio] = $this->school();
        $created = $this->actingAs($director)->postJson(route('director.gestion.students.store'), [
            'name' => 'Angel Marin',
            'grade' => '3ro',
        ])->json('family_invite');

        $this->post('/logout');

        $this->get($created['invitation_link'])
            ->assertOk()
            ->assertSee('Angel Marin')
            ->assertSee('Crear cuenta');

        $this->post(route('familia.join.store'), [
            'school' => $colegio->invite_code,
            'code' => $created['invite_code'],
            'name' => 'Mamá Marin',
            'email' => 'mama.marin@test.com',
            'password' => 'secreto123',
            'password_confirmation' => 'secreto123',
        ])->assertRedirect(route('representante.dashboard'));

        $this->assertAuthenticated();
        $parent = User::query()->where('email', 'mama.marin@test.com')->first();
        $this->assertSame('representante', $parent->role);
        $this->assertTrue((bool) $parent->onboarding_completed);
        $this->assertTrue($parent->representedStudents()->where('name', 'Angel Marin')->exists());

        $this->get(route('representante.dashboard'))
            ->assertOk()
            ->assertSee('Angel Marin');
    }

    public function test_existing_parent_gains_the_sibling_with_the_same_link(): void
    {
        [$director, $colegio] = $this->school();
        $angel = $this->actingAs($director)->postJson(route('director.gestion.students.store'), [
            'name' => 'Angel Marin',
            'grade' => '3ro',
        ])->json();

        $this->post('/logout');
        $this->post(route('familia.join.store'), [
            'school' => $colegio->invite_code,
            'code' => $angel['family_invite']['invite_code'],
            'name' => 'Papá Marin',
            'email' => 'papa.marin@test.com',
            'password' => 'secreto123',
            'password_confirmation' => 'secreto123',
        ])->assertRedirect(route('representante.dashboard'));

        $this->post('/logout');
        $this->actingAs($director)->postJson(route('director.gestion.students.store'), [
            'name' => 'Sofia Marin',
            'grade' => '1ro',
            'sibling_student_id' => $angel['student']['id'],
        ])->assertOk();

        $invite = FamilyInvite::query()->first();
        $parent = User::query()->where('email', 'papa.marin@test.com')->first();

        $this->actingAs($parent)
            ->get('/familia/unirse?school='.$colegio->invite_code.'&code='.$invite->invite_code)
            ->assertRedirect(route('representante.dashboard'));

        $this->assertTrue($parent->fresh()->representedStudents()->where('name', 'Sofia Marin')->exists());
        $this->assertSame(2, $parent->fresh()->representedStudents()->count());
    }

    /**
     * @return array{0:User,1:Colegio}
     */
    private function school(): array
    {
        $colegio = Colegio::create([
            'name' => 'Colegio Familia',
            'invite_code' => 'FAM-SCH1',
            'codes_pin' => Colegio::hashPinFromInvite('FAM-SCH1'),
        ]);
        $director = User::factory()->create([
            'role' => 'director',
            'colegio_id' => $colegio->id,
            'onboarding_completed' => true,
            'name' => 'Dir Familia',
        ]);

        return [$director, $colegio];
    }
}

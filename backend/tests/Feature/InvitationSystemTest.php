<?php

namespace Tests\Feature;

use App\Mail\TeacherInvitationMail;
use App\Models\Colegio;
use App\Models\Course;
use App\Models\Invitation;
use App\Models\TeacherInvite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InvitationSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_register_stays_disabled(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', [
            'name' => 'X',
            'email' => 'x@test.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();
    }

    public function test_super_admin_can_create_school_and_director_magic_link(): void
    {
        Mail::fake();
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'onboarding_completed' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('super-admin.schools.store'), [
                'name' => 'Colegio Token',
                'director_email' => 'director.token@colegio.test',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('colegios', ['name' => 'Colegio Token']);
        $this->assertDatabaseHas('invitations', [
            'email' => 'director.token@colegio.test',
            'role' => 'director',
        ]);

        $invitation = Invitation::query()->where('email', 'director.token@colegio.test')->first();
        $this->assertNotNull($invitation);
        $this->assertTrue($invitation->isPending());

        $this->get('/accept-invitation/'.$invitation->token)
            ->assertOk()
            ->assertSee('director.token@colegio.test')
            ->assertSee('readonly', false);
    }

    public function test_expired_or_unknown_token_shows_error_page(): void
    {
        $this->get('/accept-invitation/not-a-real-token')->assertOk()->assertSee('Este enlace ya no sirve');

        $invitation = Invitation::create([
            'email' => 'old@colegio.test',
            'role' => 'director',
            'token' => Invitation::makeToken(),
            'expires_at' => now()->subHour(),
        ]);

        $this->get('/accept-invitation/'.$invitation->token)
            ->assertOk()
            ->assertSee('Este enlace ya no sirve');
    }

    public function test_accepting_invitation_creates_user_logs_in_and_allows_later_login(): void
    {
        Mail::fake();
        $colegio = Colegio::create([
            'name' => 'Colegio Aceptar',
            'invite_code' => 'ACC-0001',
            'codes_pin' => Colegio::hashPinFromInvite('ACC-0001'),
        ]);
        $invitation = Invitation::create([
            'email' => 'ana.director@colegio.test',
            'role' => 'director',
            'colegio_id' => $colegio->id,
            'token' => Invitation::makeToken(),
            'expires_at' => now()->addHours(48),
        ]);

        $this->post('/accept-invitation', [
            'token' => $invitation->token,
            'name' => 'Ana Director',
            'password' => 'secreto123',
            'password_confirmation' => 'secreto123',
        ])->assertRedirect(route('onboarding'));

        $this->assertAuthenticated();
        $user = User::query()->where('email', 'ana.director@colegio.test')->first();
        $this->assertNotNull($user);
        $this->assertSame('director', $user->role);
        $this->assertSame($colegio->id, (int) $user->colegio_id);
        $this->assertFalse((bool) $user->onboarding_completed);
        $this->assertNotNull($invitation->fresh()->accepted_at);
        $this->assertSame($user->id, (int) $colegio->fresh()->director_user_id);

        $this->get('/onboarding')->assertOk();

        $this->post('/logout');
        $this->assertGuest();

        $this->post('/login', [
            'email' => 'ana.director@colegio.test',
            'password' => 'secreto123',
        ])->assertRedirect('/onboarding');
        $this->assertAuthenticated();
    }

    public function test_director_can_invite_teacher_and_teacher_reaches_hub_then_login(): void
    {
        Mail::fake();
        $colegio = Colegio::create([
            'name' => 'Colegio Docente',
            'invite_code' => 'DOC-SCH1',
            'codes_pin' => Colegio::hashPinFromInvite('DOC-SCH1'),
        ]);
        $director = User::factory()->create([
            'role' => 'director',
            'colegio_id' => $colegio->id,
            'onboarding_completed' => true,
        ]);

        $this->actingAs($director)
            ->post(route('director.profesores.invite-link'), [
                'email' => 'profe.token@colegio.test',
            ])
            ->assertRedirect(route('director.profesores'));

        $invitation = Invitation::query()->where('email', 'profe.token@colegio.test')->first();
        $this->assertSame('profesor', $invitation->role);

        $this->post('/logout');

        $this->post('/accept-invitation', [
            'token' => $invitation->token,
            'name' => 'Profe Token',
            'password' => 'secreto123',
            'password_confirmation' => 'secreto123',
        ])->assertRedirect('/teacher/hub');

        $this->assertAuthenticated();
        $this->assertSame('profesor', auth()->user()->role);

        $this->post('/logout');
        $this->post('/login', [
            'email' => 'profe.token@colegio.test',
            'password' => 'secreto123',
        ])->assertRedirect('/teacher/hub');
    }

    public function test_gestion_teacher_invite_sends_mail_and_onboarding_activates_account(): void
    {
        Mail::fake();
        $colegio = Colegio::create([
            'name' => 'Colegio Onboarding',
            'invite_code' => 'ONB-1001',
            'codes_pin' => Colegio::hashPinFromInvite('ONB-1001'),
        ]);
        $director = User::factory()->create([
            'role' => 'director',
            'colegio_id' => $colegio->id,
            'onboarding_completed' => true,
        ]);
        $course = Course::create([
            'colegio_id' => $colegio->id,
            'subject_name' => 'Matemática',
            'grade' => '1ro',
            'section' => 'A',
            'invite_code' => 'CUR-MAT-1A',
        ]);

        $payload = $this->actingAs($director)
            ->postJson(route('director.gestion.teachers.store'), [
                'name' => 'Carlos Baute',
                'email' => 'carlos@email.com',
                'course_ids' => [$course->id],
            ])
            ->assertOk()
            ->json();

        $this->assertNotEmpty($payload['invite']['invite_code']);
        $this->assertStringStartsWith('DOC-', $payload['invite']['invite_code']);
        $this->assertNotEmpty($payload['invite']['invitation_link']);
        $this->assertStringContainsString('/onboarding/profesor?token=', $payload['invite']['invitation_link']);

        $invitation = Invitation::query()->where('email', 'carlos@email.com')->first();
        $this->assertNotNull($invitation);
        $this->assertSame(64, strlen((string) $invitation->token));
        $this->assertTrue($invitation->expires_at->greaterThan(now()->addDays(6)));
        $this->assertTrue($invitation->expires_at->lessThan(now()->addDays(8)));
        $this->assertSame('Carlos Baute', $invitation->name);

        Mail::assertSent(TeacherInvitationMail::class, function (TeacherInvitationMail $mail) {
            return $mail->hasTo('carlos@email.com')
                && $mail->envelope()->subject === '📚 Bienvenido a AulaSync - Activa tu cuenta';
        });

        $this->post('/logout');

        $this->get('/onboarding/profesor?token='.$invitation->token)
            ->assertOk()
            ->assertSee('Activa tu cuenta de profesor')
            ->assertSee('Carlos Baute')
            ->assertSee('carlos@email.com');

        $this->post(route('onboarding.teacher.store'), [
            'token' => $invitation->token,
            'name' => 'Carlos Baute',
            'password' => 'corta',
            'password_confirmation' => 'corta',
        ])->assertSessionHasErrors('password');

        $this->post(route('onboarding.teacher.store'), [
            'token' => $invitation->token,
            'name' => 'Carlos Baute',
            'password' => 'secreto123',
            'password_confirmation' => 'secreto123',
        ])->assertRedirect('/teacher/hub');

        $this->assertAuthenticated();
        $teacher = User::query()->where('email', 'carlos@email.com')->first();
        $this->assertNotNull($teacher);
        $this->assertSame('profesor', $teacher->role);
        $this->assertTrue((bool) $teacher->onboarding_completed);
        $this->assertSame($teacher->id, $course->fresh()->teacher_id);
        $this->assertNotNull(TeacherInvite::query()->where('email', 'carlos@email.com')->first()?->claimed_at);

        $usedToken = $invitation->token;
        $this->post('/logout');

        $this->post(route('onboarding.teacher.store'), [
            'token' => $usedToken,
            'name' => 'Carlos Baute',
            'password' => 'secreto123',
            'password_confirmation' => 'secreto123',
        ])->assertSessionHasErrors('token');

        $this->post('/login', [
            'email' => 'carlos@email.com',
            'password' => 'secreto123',
        ])->assertRedirect('/teacher/hub');
        $this->get('/teacher/hub')->assertOk();
    }

    public function test_director_can_resend_expired_teacher_invitation(): void
    {
        Mail::fake();
        $colegio = Colegio::create([
            'name' => 'Colegio Reenvio',
            'invite_code' => 'REE-1001',
            'codes_pin' => Colegio::hashPinFromInvite('REE-1001'),
        ]);
        $director = User::factory()->create([
            'role' => 'director',
            'colegio_id' => $colegio->id,
            'onboarding_completed' => true,
        ]);

        $this->actingAs($director)
            ->postJson(route('director.gestion.teachers.store'), [
                'name' => 'Ana Rojas',
                'email' => 'ana.rojas@email.com',
            ])
            ->assertOk();

        $invitation = Invitation::query()->where('email', 'ana.rojas@email.com')->first();
        $oldToken = $invitation->token;
        $invitation->update(['expires_at' => now()->subDay()]);
        $invite = TeacherInvite::query()->where('email', 'ana.rojas@email.com')->first();

        $this->actingAs($director)
            ->postJson(route('director.gestion.teachers.resend-invitation', $invite))
            ->assertOk()
            ->assertJsonPath('success', true);

        $fresh = $invitation->fresh();
        $this->assertNotSame($oldToken, $fresh->token);
        $this->assertTrue($fresh->expires_at->greaterThan(now()->addDays(6)));
        Mail::assertSent(TeacherInvitationMail::class, 2);
    }
}

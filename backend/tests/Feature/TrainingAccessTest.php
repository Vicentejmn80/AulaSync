<?php

namespace Tests\Feature;

use App\Models\Colegio;
use App\Models\TrainingEnrollment;
use App\Models\TrainingLesson;
use App\Models\TrainingProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrainingAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_open_training(): void
    {
        $program = $this->program();

        $this->get('/formacion')->assertRedirect('/login');
        $this->get('/formacion/'.$program->slug)->assertRedirect('/login');
    }

    public function test_user_without_enrollment_gets_forbidden(): void
    {
        $program = $this->program();
        $user = $this->learner();

        $this->actingAs($user)->get('/formacion/'.$program->slug)->assertForbidden();
    }

    public function test_enrolled_user_can_watch_and_complete_in_order(): void
    {
        [$program, $lessons] = $this->programWithLessons();
        $user = $this->learner();
        $this->enroll($user, $program);

        $this->actingAs($user)
            ->get('/formacion/'.$program->slug)
            ->assertOk()
            ->assertSee('Inteligencia Artificial para Docentes')
            ->assertSee('Comenzar')
            ->assertSee('Bloqueada');

        $this->actingAs($user)
            ->get('/formacion/'.$program->slug.'/clases/'.$lessons[1]->id)
            ->assertForbidden();

        $this->actingAs($user)
            ->get('/formacion/'.$program->slug.'/clases/'.$lessons[0]->id)
            ->assertOk()
            ->assertSee('youtube-nocookie.com/embed/aaaaaaaaaaa', false);

        $this->actingAs($user)
            ->post('/formacion/'.$program->slug.'/clases/'.$lessons[0]->id.'/completar')
            ->assertRedirect();

        $this->actingAs($user)
            ->get('/formacion/'.$program->slug.'/clases/'.$lessons[1]->id)
            ->assertOk();
    }

    public function test_revoked_enrollment_loses_access(): void
    {
        $program = $this->program();
        $user = $this->learner();
        $enrollment = $this->enroll($user, $program);
        $enrollment->update(['status' => 'revoked']);

        $this->actingAs($user)->get('/formacion/'.$program->slug)->assertForbidden();
    }

    public function test_other_school_cannot_open_a_private_program(): void
    {
        $school = Colegio::create([
            'name' => 'Colegio A',
            'invite_code' => 'AAA-1001',
            'codes_pin' => Colegio::hashPinFromInvite('AAA-1001'),
        ]);
        $other = Colegio::create([
            'name' => 'Colegio B',
            'invite_code' => 'BBB-1001',
            'codes_pin' => Colegio::hashPinFromInvite('BBB-1001'),
        ]);
        $program = $this->program($school->id);
        $user = $this->learner(['colegio_id' => $other->id]);
        TrainingEnrollment::create([
            'user_id' => $user->id,
            'training_program_id' => $program->id,
            'status' => 'active',
            'started_at' => now(),
        ]);

        $this->actingAs($user)->get('/formacion/'.$program->slug)->assertForbidden();
    }

    public function test_formation_only_user_cannot_open_the_saas_dashboard(): void
    {
        $user = $this->learner(['saas_access' => false, 'role' => 'formacion']);

        $this->actingAs($user)->get('/teacher/hub')->assertRedirect('/formacion');
        $this->actingAs($user)->get('/dashboard')->assertRedirect('/formacion');
        $this->actingAs($user)->get('/director/dashboard')->assertRedirect('/formacion');
    }

    public function test_saas_user_with_enrollment_can_use_both(): void
    {
        [$program] = $this->programWithLessons();
        $user = User::factory()->create([
            'role' => 'profesor',
            'onboarding_completed' => true,
            'saas_access' => true,
        ]);
        $this->enroll($user, $program);

        $this->actingAs($user)->get('/formacion/'.$program->slug)->assertOk();
        $hub = $this->actingAs($user)->get('/teacher/hub');
        $this->assertFalse($hub->isRedirect(url('/formacion')));
    }

    public function test_login_sends_formation_only_users_to_formacion(): void
    {
        $user = $this->learner([
            'saas_access' => false,
            'role' => 'formacion',
            'password' => 'password',
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect('/formacion');
    }

    private function learner(array $extra = []): User
    {
        return User::factory()->create($extra + [
            'role' => 'formacion',
            'onboarding_completed' => true,
            'saas_access' => true,
        ]);
    }

    private function program(?int $colegioId = null): TrainingProgram
    {
        return TrainingProgram::create([
            'colegio_id' => $colegioId,
            'title' => 'Inteligencia Artificial para Docentes',
            'slug' => 'ia-para-docentes'.($colegioId ? '-'.$colegioId : ''),
            'description' => 'Taller práctico.',
            'status' => 'published',
        ]);
    }

    /**
     * @return array{0: TrainingProgram, 1: array<int, TrainingLesson>}
     */
    private function programWithLessons(): array
    {
        $program = $this->program();
        $lessons = [];
        foreach ([
            ['Introducción', 'https://www.youtube.com/watch?v=aaaaaaaaaaa', 18],
            ['IA aplicada', 'https://vimeo.com/123456', 24],
            ['Construir soluciones', null, 20],
        ] as $i => [$title, $url, $minutes]) {
            $lessons[] = $program->lessons()->create([
                'title' => $title,
                'video_url' => $url,
                'duration_minutes' => $minutes,
                'position' => $i + 1,
                'status' => 'published',
            ]);
        }

        return [$program, $lessons];
    }

    private function enroll(User $user, TrainingProgram $program): TrainingEnrollment
    {
        return TrainingEnrollment::create([
            'user_id' => $user->id,
            'training_program_id' => $program->id,
            'status' => 'active',
            'started_at' => now(),
        ]);
    }
}

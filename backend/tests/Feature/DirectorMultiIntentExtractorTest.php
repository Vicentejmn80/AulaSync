<?php

namespace Tests\Feature;

use App\Models\Colegio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectorMultiIntentExtractorTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_teacher_and_enrolls_students_in_single_phrase(): void
    {
        [$director, $colegio] = $this->directorContext();

        $text = 'Crea el profesor de matemáticas llamado Vicente José. '
            .'Él es el profesor de matemáticas desde 1ro hasta 6to grado. '
            .'Agrega a los alumnos Carlos Gutiérrez y Salvador Pérez a su materia de matemáticas, '
            .'que ambos son de 3ro.';

        $response = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => $text,
        ]);

        $response->assertOk()
            ->assertJsonPath('requires_confirmation', true);

        $pending = $response->json('pending_actions');
        $intents = collect($pending)->pluck('intent')->all();

        $this->assertCount(2, $pending);
        $this->assertContains('create_teacher', $intents);
        $this->assertContains('enroll_students_course', $intents);

        $teacherAction = collect($pending)->firstWhere('intent', 'create_teacher');
        $this->assertSame('Vicente José', $teacherAction['data']['teacher_name']);
        $this->assertSame('Matemática', $teacherAction['data']['subject_name']);
        $this->assertSame(['1ro', '2do', '3ro', '4to', '5to', '6to'], $teacherAction['data']['grades']);

        $enrollAction = collect($pending)->firstWhere('intent', 'enroll_students_course');
        $this->assertSame(['Carlos Gutiérrez', 'Salvador Pérez'], $enrollAction['data']['names']);
        $this->assertSame('3ro', $enrollAction['data']['grade']);
        $this->assertSame('Matemática', $enrollAction['data']['subject_name']);

        // Los alumnos NUNCA deben aparecer como profesores.
        $teacherNames = collect($pending)
            ->where('intent', 'create_teacher')
            ->pluck('data.teacher_name')
            ->all();
        $this->assertNotContains('Carlos Gutiérrez', $teacherNames);
        $this->assertNotContains('Salvador Pérez', $teacherNames);
    }

    /**
     * @return array{0:User,1:Colegio}
     */
    private function directorContext(): array
    {
        $director = User::factory()->create([
            'role' => 'director',
            'onboarding_completed' => true,
        ]);
        $colegio = Colegio::create([
            'name' => 'Colegio Central',
            'invite_code' => 'CEN-9999',
            'codes_pin' => Colegio::hashPinFromInvite('CEN-9999'),
            'director_user_id' => $director->id,
        ]);
        $director->update(['colegio_id' => $colegio->id]);

        return [$director->fresh(), $colegio];
    }
}

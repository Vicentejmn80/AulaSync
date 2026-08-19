<?php

namespace Tests\Feature;

use App\Models\Colegio;
use App\Models\Course;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectorAICommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_director_can_create_teacher_invite_with_assignments_via_ai(): void
    {
        [$director, $colegio] = $this->directorContext();

        $draft = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'Crea al profesor Vicente Maduro y asígnale Inglés de 1ro a 3ro.',
        ]);

        $draft->assertOk()
            ->assertJsonPath('requires_confirmation', true);

        $pending = $draft->json('pending_actions');
        $execute = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'confirmed' => true,
            'pending_actions' => $pending,
        ]);

        $execute->assertOk();
        $this->assertTrue(
            (bool) $execute->json('actions.0.success'),
            json_encode($execute->json(), JSON_UNESCAPED_UNICODE)
        );

        $this->assertDatabaseHas('teacher_invites', [
            'colegio_id' => $colegio->id,
            'name' => 'Vicente Maduro',
        ]);
        $this->assertSame(3, Course::where('colegio_id', $colegio->id)->count());
    }

    public function test_ai_rejects_unknown_grades_until_director_confirms_structure_change(): void
    {
        [$director, $colegio] = $this->directorContext();
        $teacher = User::factory()->create([
            'role' => 'profesor',
            'colegio_id' => $colegio->id,
            'onboarding_completed' => true,
        ]);

        Student::create([
            'teacher_id' => $director->id,
            'colegio_id' => $colegio->id,
            'name' => 'Alumno Base',
            'grade' => '1ro',
            'section' => 'A',
            'family_code' => 'NV-BASE-01',
        ]);

        $response = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => "{$teacher->name} dará Matemática de 1ro a 6to.",
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
        $this->assertStringContainsString('No encontré estos grados', (string) $response->json('message'));
    }

    public function test_director_can_create_students_batch_and_report_duplicates(): void
    {
        [$director, $colegio] = $this->directorContext();

        Student::create([
            'teacher_id' => $director->id,
            'colegio_id' => $colegio->id,
            'name' => 'Carlos José',
            'grade' => '3ro',
            'section' => null,
            'family_code' => 'NV-EXIST-01',
        ]);

        $draft = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'Agrega a Carlos José, Juan Carlos y María al 3er grado.',
        ]);
        $draft->assertOk()->assertJsonPath('requires_confirmation', true);

        $execute = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'confirmed' => true,
            'pending_actions' => $draft->json('pending_actions'),
        ]);

        $execute->assertOk();
        $this->assertTrue(
            (bool) $execute->json('actions.0.success'),
            json_encode($execute->json(), JSON_UNESCAPED_UNICODE)
        );

        $this->assertDatabaseHas('students', [
            'colegio_id' => $colegio->id,
            'name' => 'Juan Carlos',
            'grade' => '3ro',
        ]);
        $this->assertDatabaseHas('students', [
            'colegio_id' => $colegio->id,
            'name' => 'María',
            'grade' => '3ro',
        ]);
        $this->assertContains('Carlos José', $execute->json('actions.0.data.duplicates'));
    }

    public function test_director_can_create_course_and_enroll_students_to_course(): void
    {
        [$director, $colegio] = $this->directorContext();
        User::factory()->create([
            'name' => 'María Gómez',
            'role' => 'profesor',
            'colegio_id' => $colegio->id,
            'onboarding_completed' => true,
        ]);

        Student::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => $director->id,
            'name' => 'Luis Pérez',
            'grade' => '4to',
            'section' => 'A',
            'family_code' => 'FAM-1',
        ]);
        Student::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => $director->id,
            'name' => 'Marta Ruiz',
            'grade' => '4to',
            'section' => 'A',
            'family_code' => 'FAM-2',
        ]);

        $createDraft = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'Crea curso de Matemática para 4to grado sección A y asígnalo a profesora María Gómez.',
        ]);
        $createDraft->assertOk()->assertJsonPath('requires_confirmation', true);

        $createExec = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'confirmed' => true,
            'pending_actions' => $createDraft->json('pending_actions'),
        ]);
        $createExec->assertOk()->assertJsonPath('actions.0.success', true);

        $enrollDraft = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'Inscribe a Luis Pérez y Marta Ruiz en curso de Matemática de 4to grado sección A.',
        ]);
        $enrollDraft->assertOk(
            json_encode($enrollDraft->json(), JSON_UNESCAPED_UNICODE)
        )->assertJsonPath('requires_confirmation', true);

        $enrollExec = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'confirmed' => true,
            'pending_actions' => $enrollDraft->json('pending_actions'),
        ]);
        $enrollExec->assertOk()->assertJsonPath('actions.0.success', true);

        $course = Course::where('colegio_id', $colegio->id)
            ->where('subject_name', 'Matemática')
            ->where('grade', '4to')
            ->firstOrFail();
        $this->assertSame(2, $course->students()->count());
    }

    public function test_director_can_query_teacher_academic_status_without_confirmation_step(): void
    {
        [$director, $colegio] = $this->directorContext();
        $teacher = User::factory()->create([
            'name' => 'Carlos Pérez',
            'role' => 'profesor',
            'colegio_id' => $colegio->id,
            'onboarding_completed' => true,
        ]);
        Course::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => $teacher->id,
            'subject_name' => 'Matemática',
            'grade' => '4to',
            'section' => 'A',
            'school_year' => '2026-2027',
            'invite_code' => 'CURSO-MAT-4TO',
        ]);

        $response = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => '¿Cómo va el profesor Carlos Pérez?',
        ]);

        $response->assertOk(
            json_encode($response->json(), JSON_UNESCAPED_UNICODE)
        )
            ->assertJsonPath('actions.0.success', true);
        $this->assertNull($response->json('requires_confirmation'));
    }

    public function test_director_cannot_use_teacher_chat_endpoint(): void
    {
        [$director] = $this->directorContext();

        $response = $this->actingAs($director)->post('/ai/command', [
            'prompt' => 'hola',
        ]);

        $response->assertStatus(302);
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
            'invite_code' => 'COC-1001',
            'codes_pin' => Colegio::hashPinFromInvite('COC-1001'),
            'director_user_id' => $director->id,
        ]);
        $director->update(['colegio_id' => $colegio->id]);

        return [$director->fresh(), $colegio];
    }
}

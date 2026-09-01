<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Colegio;
use App\Models\Course;
use App\Models\Student;
use App\Models\Tarea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherLessonAiLabTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_lab_returns_three_homework_proposals(): void
    {
        [$teacher, , $activity] = $this->classroom();

        $response = $this->actingAs($teacher)->postJson(route('teacher.tareas.generate'), [
            'activity_id' => $activity->id,
        ]);

        $response->assertOk()->assertJsonPath('success', true);
        $ideas = $response->json('ideas');
        $this->assertIsArray($ideas);
        $this->assertCount(3, $ideas);
        $this->assertNotSame($ideas[0]['enfoque'], $ideas[1]['enfoque']);
    }

    public function test_clicking_a_proposal_saves_it_as_the_official_lesson_task(): void
    {
        [$teacher, , $activity] = $this->classroom();

        $this->actingAs($teacher)->postJson(route('teacher.tareas.store'), [
            'activity_id' => $activity->id,
            'titulo' => 'Práctica: Fracciones',
            'descripcion' => 'Cinco ejercicios guiados.',
            'fecha_entrega' => '2026-09-02',
            'puntos' => 20,
            'mirror_activity' => true,
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('tareas', [
            'actividad_id' => $activity->id,
            'titulo' => 'Práctica: Fracciones',
        ]);
        $this->assertSame(1, Tarea::query()->where('actividad_id', $activity->id)->count());
    }

    public function test_nee_adaptation_is_saved_with_student_name(): void
    {
        [$teacher, $course, $activity, $student] = $this->classroom();

        $this->actingAs($teacher)->postJson(route('teacher.activities.nee_generate', $activity), [
            'nee_type' => 'TDAH',
            'student_id' => $student->id,
        ])->assertOk()->assertJsonPath('student_name', 'Carlos Gutiérrez');

        $save = $this->actingAs($teacher)->postJson(route('teacher.activities.nee_save', $activity), [
            'nee_type' => 'TDAH',
            'nee_adaptation' => 'Segmenta la clase en bloques de 10 minutos para Carlos.',
            'student_id' => $student->id,
        ]);

        $save->assertOk()
            ->assertJsonPath('nee_student_id', $student->id)
            ->assertJsonPath('nee_student_name', 'Carlos Gutiérrez');

        $activity->refresh();
        $this->assertSame($student->id, $activity->nee_student_id);
        $this->assertSame('TDAH', $activity->nee_type);
    }

    public function test_chatbot_assigns_homework_to_the_open_lesson(): void
    {
        [$teacher, , $activity] = $this->classroom();

        $response = $this->actingAs($teacher)->postJson(route('ai.command'), [
            'prompt' => 'genera una tarea para esta clase',
            'screen_context' => [
                'type' => 'activity',
                'id' => $activity->id,
                'course_id' => $activity->course_id,
                'title' => $activity->title,
            ],
        ]);

        $response->assertOk();
        $this->assertTrue((bool) $response->json('success') || (bool) $response->json('any_success'));
        $this->assertDatabaseHas('tareas', ['actividad_id' => $activity->id]);
        $this->assertStringContainsString('tarea', mb_strtolower((string) $response->json('message')));
    }

    public function test_chatbot_saves_nee_for_a_named_student(): void
    {
        [$teacher, , $activity, $student] = $this->classroom();

        $response = $this->actingAs($teacher)->postJson(route('ai.command'), [
            'prompt' => 'genera una adaptación NEE de TDAH para Carlos Gutiérrez',
            'screen_context' => [
                'type' => 'activity',
                'id' => $activity->id,
                'course_id' => $activity->course_id,
            ],
        ]);

        $response->assertOk();
        $activity->refresh();
        $this->assertSame('TDAH', $activity->nee_type);
        $this->assertSame($student->id, $activity->nee_student_id);
        $this->assertNotEmpty($activity->nee_adaptation);
        $this->assertStringContainsString('Carlos', (string) $response->json('message'));
    }

    /**
     * @return array{0:User,1:Course,2:Activity,3:Student}
     */
    private function classroom(): array
    {
        $colegio = Colegio::create([
            'name' => 'Colegio Lab',
            'invite_code' => 'LAB-1001',
            'codes_pin' => Colegio::hashPinFromInvite('LAB-1001'),
        ]);
        $teacher = User::factory()->create([
            'role' => 'profesor',
            'colegio_id' => $colegio->id,
            'onboarding_completed' => true,
            'name' => 'Prof. Lab',
        ]);
        $course = Course::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => $teacher->id,
            'subject_name' => 'Matemática',
            'grade' => '3ro',
            'section' => 'A',
            'school_year' => '2026-2027',
            'invite_code' => 'MAT-LAB',
        ]);
        $activity = Activity::create([
            'teacher_id' => $teacher->id,
            'course_id' => $course->id,
            'colegio_id' => $colegio->id,
            'title' => 'Fracciones equivalentes',
            'description' => "**INICIO**\nActivación.\n\n**DESARROLLO**\nPráctica.\n\n**CIERRE**\nCierre.",
            'type' => 'clase',
            'due_date' => '2026-09-01',
            'max_score' => 20,
        ]);
        $student = Student::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => $teacher->id,
            'name' => 'Carlos Gutiérrez',
            'grade' => '3ro',
            'section' => 'A',
            'family_code' => 'FAM-CG'.uniqid(),
        ]);
        $course->students()->attach($student->id);

        return [$teacher, $course, $activity, $student];
    }
}

<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Colegio;
use App\Models\Course;
use App\Models\Evaluation;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RepresentanteFamilyHubTest extends TestCase
{
    use RefreshDatabase;

    public function test_family_calendar_includes_topic_weight_and_teacher(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10 09:00:00'));

        [$parent, $student, $teacher, $course] = $this->familyClassroom();

        $month = $this->actingAs($parent)
            ->getJson(route('representante.api.calendario', ['estudiante' => $student->id, 'month' => '2026-09']))
            ->assertOk()
            ->json('calendar');

        $this->assertSame('2026-09', $month['month']);
        $this->assertGreaterThanOrEqual(2, $month['total_events']);

        $task = collect($month['events']['2026-09-18'] ?? [])->firstWhere('type', 'task');
        $this->assertNotNull($task);
        $this->assertSame('Guía de fracciones', $task['title']);
        $this->assertSame(20.0, (float) $task['weight_percentage']);
        $this->assertSame(20.0, (float) $task['max_score']);
        $this->assertSame('Matemática', $task['course']);
        $this->assertSame('Prof. Díaz', $task['teacher']);
        $this->assertSame('#F59E0B', $task['color']);
        $this->assertSame($course->id, $task['course_id']);
        $this->assertGreaterThan(220, mb_strlen($task['description']));
        $this->assertStringContainsString('Resolver las páginas 12 a 14.', $task['description']);
        $this->assertStringContainsString('Incluye planteo y justificación', $task['description']);
        $this->assertSame('Traer el cuaderno de Matemática.', $task['notes']);
        $this->assertStringContainsString('Traer el cuaderno de Matemática.', $task['body']);

        $eval = collect($month['events']['2026-09-22'] ?? [])->firstWhere('type', 'evaluation');
        $this->assertNotNull($eval);
        $this->assertSame('Parcial de álgebra', $eval['title']);
        $this->assertSame('Ecuaciones de primer grado', $eval['topic']);
        $this->assertSame(25.0, (float) $eval['total_points']);
        $this->assertSame(30.0, (float) $eval['weight_percentage']);
        $this->assertSame('Prof. Díaz', $eval['teacher']);
        $this->assertSame('Llegar 10 minutos antes. Solo lápiz y calculadora.', $eval['instructions']);
        $this->assertStringContainsString('Examen escrito de 10 ítems.', $eval['description']);
    }

    public function test_family_summary_exposes_global_kpis_and_reminder_items(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10 09:00:00'));

        [$parent, $student] = $this->familyClassroom();

        $summary = $this->actingAs($parent)
            ->getJson(route('representante.api.resumen', $student->id))
            ->assertOk()
            ->json('summary');

        $this->assertSame(1, $summary['courses_count']);
        $this->assertArrayHasKey('percent', $summary['attendance']);
        $this->assertArrayHasKey('absences', $summary['attendance']);
        $this->assertSame(1, $summary['pending_tasks']['count']);
        $this->assertSame('Guía de fracciones', $summary['pending_tasks']['items'][0]['title']);
        $this->assertSame(20.0, (float) $summary['pending_tasks']['items'][0]['weight_percentage']);
        $this->assertSame(1, $summary['evaluations']['count']);
        $this->assertSame('Ecuaciones de primer grado', $summary['evaluations']['items'][0]['topic']);
    }

    public function test_family_hub_renders_theme_picker_and_calendar_shell(): void
    {
        [$parent] = $this->familyClassroom();

        $this->actingAs($parent)
            ->get(route('representante.dashboard'))
            ->assertOk()
            ->assertSee('Colores del tema')
            ->assertSee('Calendario académico')
            ->assertSee('Próximas entregas')
            ->assertSee('Ausencias del mes')
            ->assertSee('Reportar ausencia')
            ->assertSee('Ver boletín')
            ->assertSee('Escribirle a un docente')
            ->assertSee('Toca una tarjeta para leer el detalle completo')
            ->assertSee('fam-backdrop')
            ->assertDontSee('class="overlay"', false)
            ->assertDontSee('class="modal"', false);
    }

    /**
     * @return array{0:User,1:Student,2:User,3:Course}
     */
    private function familyClassroom(): array
    {
        $colegio = Colegio::create([
            'name' => 'Colegio Familia Hub',
            'invite_code' => 'FAM-HUB1',
            'codes_pin' => Colegio::hashPinFromInvite('FAM-HUB1'),
        ]);
        $teacher = User::factory()->create([
            'role' => 'profesor',
            'colegio_id' => $colegio->id,
            'onboarding_completed' => true,
            'name' => 'Prof. Díaz',
        ]);
        $parent = User::factory()->create([
            'role' => 'representante',
            'colegio_id' => $colegio->id,
            'onboarding_completed' => true,
            'name' => 'Representante Marin',
        ]);
        $student = Student::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => $teacher->id,
            'name' => 'Ángel Marín',
            'grade' => '3ro',
            'section' => 'A',
        ]);
        $parent->representedStudents()->attach($student->id, ['relationship' => 'padre']);

        $course = Course::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => $teacher->id,
            'subject_name' => 'Matemática',
            'grade' => '3ro',
            'section' => 'A',
            'school_year' => '2026-2027',
            'invite_code' => 'MAT-3A',
        ]);
        $student->courses()->attach($course->id);

        Activity::create([
            'teacher_id' => $teacher->id,
            'course_id' => $course->id,
            'colegio_id' => $colegio->id,
            'title' => 'Guía de fracciones',
            'description' => 'Resolver las páginas 12 a 14. Incluye planteo y justificación de cada ejercicio, con evidencia del procedimiento y una reflexión breve al final de la guía para la familia. '.str_repeat('El docente revisará el cuaderno y la claridad del razonamiento. ', 6),
            'notes' => 'Traer el cuaderno de Matemática.',
            'due_date' => '2026-09-18',
            'type' => Activity::TYPE_TAREA,
            'is_homework' => true,
            'max_score' => 20,
            'weight_percentage' => 20,
        ]);

        $examActivity = Activity::create([
            'teacher_id' => $teacher->id,
            'course_id' => $course->id,
            'colegio_id' => $colegio->id,
            'title' => 'Parcial de álgebra',
            'due_date' => '2026-09-22',
            'type' => Activity::TYPE_ACTIVIDAD,
            'max_score' => 25,
            'weight_percentage' => 30,
        ]);

        Evaluation::create([
            'teacher_id' => $teacher->id,
            'course_id' => $course->id,
            'colegio_id' => $colegio->id,
            'activity_id' => $examActivity->id,
            'title' => 'Parcial de álgebra',
            'topic' => 'Ecuaciones de primer grado',
            'description' => 'Examen escrito de 10 ítems.',
            'instructions' => 'Llegar 10 minutos antes. Solo lápiz y calculadora.',
            'status' => 'published',
            'scheduled_at' => '2026-09-22 08:00:00',
            'total_points' => 25,
            'passing_score' => 12,
        ]);

        return [$parent, $student, $teacher, $course];
    }

    public function test_parent_and_teacher_can_exchange_private_messages(): void
    {
        [$parent, $student, $teacher, $course] = $this->familyClassroom();

        $started = $this->actingAs($parent)
            ->postJson(route('representante.api.mensajes.start'), [
                'estudiante_id' => $student->id,
                'course_id' => $course->id,
                'body' => 'Hola profesor, ¿qué entra en el parcial de álgebra?',
            ])
            ->assertOk()
            ->json();

        $threadId = $started['thread_id'];
        $this->assertNotEmpty($threadId);

        $threads = $this->actingAs($teacher)
            ->getJson(route('teacher.communication.threads'))
            ->assertOk()
            ->json('threads');

        $familyThread = collect($threads)->firstWhere('id', $threadId);
        $this->assertNotNull($familyThread);
        $this->assertSame('representante', $familyThread['contact_role']);
        $this->assertTrue((bool) $familyThread['is_family']);
        $this->assertGreaterThanOrEqual(1, (int) $familyThread['unread']);
        $this->assertTrue(
            collect($familyThread['messages'])->contains(
                fn ($message) => str_contains((string) $message['body'], 'parcial de álgebra')
            )
        );

        $this->actingAs($teacher)
            ->postJson(route('teacher.communication.messages.send', $threadId), [
                'body' => 'Hola, entra ecuaciones de primer grado y los ejercicios de la guía.',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $thread = $this->actingAs($parent)
            ->getJson(route('representante.api.mensajes.show', [
                'thread' => $threadId,
                'estudiante_id' => $student->id,
            ]))
            ->assertOk()
            ->json('thread');

        $bodies = collect($thread['messages'])->pluck('body');
        $this->assertTrue($bodies->contains('Hola profesor, ¿qué entra en el parcial de álgebra?'));
        $this->assertTrue($bodies->contains('Hola, entra ecuaciones de primer grado y los ejercicios de la guía.'));
        $this->assertTrue(collect($thread['messages'])->contains(fn ($m) => $m['mine'] === false));
    }
}

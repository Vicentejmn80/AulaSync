<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Colegio;
use App\Models\Course;
use App\Models\Planificacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherPlanningWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_historial_does_not_show_class_style_button(): void
    {
        [$teacher] = $this->teacherWithCourse();

        $this->actingAs($teacher)
            ->get(route('historial'))
            ->assertOk()
            ->assertDontSee('Estilo de clase')
            ->assertSee('Campo de planificaciones')
            ->assertSee('Nueva Planificación');
    }

    public function test_historial_lists_each_class_inside_a_plan_card(): void
    {
        [$teacher, $course] = $this->teacherWithCourse();

        Planificacion::create([
            'user_id' => $teacher->id,
            'colegio_id' => $teacher->colegio_id,
            'tema' => 'Plan mensual Septiembre 2026 · Biología 4to',
            'objetivo' => 'Fotosíntesis',
            'slug' => 'plan-bio-sept',
            'status' => 'aprobado',
            'payload' => [
                'type' => 'manual_plan',
                'course_id' => $course->id,
                'sessions' => [
                    ['date' => '2026-09-07', 'title' => 'Fotosíntesis: teoría', 'inicio' => 'Activación', 'desarrollo' => 'Laboratorio'],
                    ['date' => '2026-09-08', 'title' => 'Rayos solares', 'inicio' => 'Pregunta', 'desarrollo' => 'Práctica'],
                ],
            ],
        ]);

        $this->actingAs($teacher)
            ->get(route('historial'))
            ->assertOk()
            ->assertSee('Fotosíntesis: teoría')
            ->assertSee('Rayos solares')
            ->assertSee('2 clases');
    }

    public function test_manual_planner_stores_template_phases_on_activities(): void
    {
        [$teacher, $course] = $this->teacherWithCourse();

        $response = $this->actingAs($teacher)->postJson(route('teacher.planner.store'), [
            'course_id' => $course->id,
            'lesson_template' => 'constructivista',
            'sessions' => [[
                'date' => '2026-09-10',
                'title' => 'Explorar la fotosíntesis',
                'phases' => [
                    'activacion' => 'Pregunta provocadora sobre la luz.',
                    'exploracion' => 'Observan hojas al sol y a la sombra.',
                    'explicacion' => 'Formalizan cloroplasto y glucosa.',
                    'aplicacion' => 'Diseñan un mini experimento.',
                    'evaluacion' => 'Salida oral de 3 ideas clave.',
                ],
            ]],
        ]);

        $response->assertOk()->assertJsonPath('success', true);

        $activity = Activity::query()->first();
        $this->assertNotNull($activity);
        $this->assertSame('Explorar la fotosíntesis', $activity->title);
        $this->assertStringContainsString('**ACTIVACIÓN**', (string) $activity->description);
        $this->assertStringContainsString('**EXPLORACIÓN**', (string) $activity->description);
        $this->assertSame('2026-09-10', optional($activity->due_date)?->toDateString() ?: (string) $activity->due_date);

        $plan = Planificacion::query()->first();
        $this->assertSame('constructivista', $plan->payload['lesson_template'] ?? null);
    }

    public function test_generate_endpoint_fails_gracefully_without_openai_key(): void
    {
        config(['services.openai.key' => null]);
        [$teacher, $course] = $this->teacherWithCourse();

        $this->actingAs($teacher)
            ->postJson(route('teacher.planner.generate'), [
                'prompt' => 'Planifica 4 clases de fotosíntesis para tercero',
                'course_id' => $course->id,
                'lesson_template' => 'clasica',
                'session_count' => 4,
                'start_date' => '2026-09-01',
            ])
            ->assertOk()
            ->assertJsonPath('success', false);
    }

    /**
     * @return array{0:User,1:Course}
     */
    private function teacherWithCourse(): array
    {
        $colegio = Colegio::create([
            'name' => 'Colegio Plan',
            'invite_code' => 'PLN-1001',
            'codes_pin' => Colegio::hashPinFromInvite('PLN-1001'),
        ]);

        $teacher = User::factory()->create([
            'role' => 'profesor',
            'colegio_id' => $colegio->id,
            'onboarding_completed' => true,
            'name' => 'Docente Plan',
        ]);

        $course = Course::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => $teacher->id,
            'subject_name' => 'Biología',
            'grade' => '3ro',
            'section' => 'A',
            'school_year' => '2026-2027',
            'invite_code' => 'BIO-PLAN',
        ]);

        return [$teacher, $course];
    }
}

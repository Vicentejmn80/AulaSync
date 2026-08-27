<?php

namespace Tests\Feature;

use App\Models\Colegio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DirectorActionPlannerFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.openai.key' => 'test-key',
            'services.openai.director_enabled' => true,
            'services.openai.director_test_enabled' => true,
        ]);
    }

    public function test_assign_two_teachers_in_one_phrase_with_planner(): void
    {
        [$director, $colegio] = $this->directorContext();
        $lopez = User::factory()->create([
            'name' => 'Mariano López',
            'role' => 'profesor',
            'colegio_id' => $colegio->id,
            'onboarding_completed' => true,
        ]);
        $guevara = User::factory()->create([
            'name' => 'Mariano Guevara',
            'role' => 'profesor',
            'colegio_id' => $colegio->id,
            'onboarding_completed' => true,
        ]);

        $this->fakePlannerPlan([
            'status' => 'pending',
            'actions' => [
                [
                    'id' => 'a1',
                    'type' => 'assign_teacher',
                    'entity' => 'teacher',
                    'params' => [
                        'teacher_name' => 'Mariano López',
                        'subject_name' => 'Robótica',
                        'grades' => ['1ro', '2do', '3ro', '4to', '5to', '6to'],
                        'student_name' => null,
                        'names' => null,
                        'grade' => null,
                        'section' => null,
                        'new_grade' => null,
                        'new_section' => null,
                        'new_name' => null,
                        'operation' => null,
                        'all_in_grade' => null,
                        'invite_code' => null,
                    ],
                    'status' => 'pending',
                    'missing_slots' => [],
                    'depends_on' => [],
                    'confirmation_required' => true,
                ],
                [
                    'id' => 'a2',
                    'type' => 'assign_teacher',
                    'entity' => 'teacher',
                    'params' => [
                        'teacher_name' => 'Mariano Guevara',
                        'subject_name' => 'Lenguaje',
                        'grades' => ['1ro', '2do', '3ro', '4to', '5to', '6to'],
                        'student_name' => null,
                        'names' => null,
                        'grade' => null,
                        'section' => null,
                        'new_grade' => null,
                        'new_section' => null,
                        'new_name' => null,
                        'operation' => null,
                        'all_in_grade' => null,
                        'invite_code' => null,
                    ],
                    'status' => 'pending',
                    'missing_slots' => [],
                    'depends_on' => [],
                    'confirmation_required' => true,
                ],
            ],
            'summary' => 'Voy a asignar Robótica a Mariano López y Lenguaje a Mariano Guevara.',
            'requires_confirmation' => true,
            'all_or_nothing' => false,
        ]);

        $response = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'asignale a mariano lopez robotica de 1ero a 6to y a mariano guevara lenguaje de 1ero a 6to',
        ]);

        $response->assertOk()
            ->assertJsonPath('requires_confirmation', true);

        $pending = $response->json('pending_actions');
        $teacherActions = collect($pending)->where('intent', 'assign_teacher')->values()->all();
        $this->assertCount(2, $teacherActions);
        $this->assertSame('Mariano López', $teacherActions[0]['data']['teacher_name']);
        $this->assertSame('Mariano Guevara', $teacherActions[1]['data']['teacher_name']);

        // Confirmar
        $execute = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'confirmed' => true,
            'pending_actions' => $pending,
        ]);
        $execute->assertOk();

        $this->assertDatabaseHas('courses', [
            'teacher_id' => $lopez->id,
            'subject_name' => 'Robótica',
            'colegio_id' => $colegio->id,
        ]);
        $this->assertDatabaseHas('courses', [
            'teacher_id' => $guevara->id,
            'subject_name' => 'Lenguaje',
            'colegio_id' => $colegio->id,
        ]);
    }

    public function test_planner_shows_summary_and_executes_with_yes(): void
    {
        [$director, $colegio] = $this->directorContext();

        $this->fakePlannerPlan([
            'status' => 'pending',
            'actions' => [
                [
                    'id' => 'a1',
                    'type' => 'create_teacher',
                    'entity' => 'teacher',
                    'params' => [
                        'teacher_name' => 'Vicente José',
                        'subject_name' => 'Matemática',
                        'grades' => ['1ro', '2do', '3ro', '4to', '5to', '6to'],
                        'student_name' => null,
                        'names' => null,
                        'grade' => null,
                        'section' => null,
                        'new_grade' => null,
                        'new_section' => null,
                        'new_name' => null,
                        'operation' => null,
                        'all_in_grade' => null,
                        'invite_code' => null,
                    ],
                    'status' => 'pending',
                    'missing_slots' => [],
                    'depends_on' => [],
                    'confirmation_required' => true,
                ],
                [
                    'id' => 'a2',
                    'type' => 'enroll_students_course',
                    'entity' => 'student',
                    'params' => [
                        'names' => ['Carlos Gutiérrez', 'Salvador Pérez'],
                        'subject_name' => 'Matemática',
                        'grade' => '3ro',
                        'section' => null,
                        'teacher_name' => null,
                        'student_name' => null,
                        'new_grade' => null,
                        'new_section' => null,
                        'new_name' => null,
                        'operation' => null,
                        'all_in_grade' => null,
                        'invite_code' => null,
                    ],
                    'status' => 'pending',
                    'missing_slots' => [],
                    'depends_on' => [],
                    'confirmation_required' => true,
                ],
            ],
            'summary' => 'Voy a hacer:\n1. Crear profesor Vicente José.\n2. Matricular a Carlos Gutiérrez y Salvador Pérez.',
            'requires_confirmation' => true,
            'all_or_nothing' => false,
        ]);

        $response = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'Crea el profesor de matemáticas llamado Vicente José. Agrega a los alumnos Carlos Gutiérrez y Salvador Pérez a su materia de matemáticas, que ambos son de 3ro.',
        ]);

        $response->assertOk()
            ->assertJsonPath('requires_confirmation', true);

        $message = (string) $response->json('message');
        $this->assertStringContainsStringIgnoringCase('Voy a hacer', $message);
        $this->assertStringContainsStringIgnoringCase('Vicente José', $message);
        $this->assertStringContainsStringIgnoringCase('Carlos Gutiérrez', $message);

        $pending = $response->json('pending_actions');
        $this->assertCount(2, $pending);

        // Confirmar con "sí"
        $execute = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'sí',
        ]);
        $execute->assertOk();

        $this->assertDatabaseHas('teacher_invites', [
            'colegio_id' => $colegio->id,
            'name' => 'Vicente José',
        ]);
    }

    public function test_planner_asks_for_missing_grade(): void
    {
        [$director] = $this->directorContext();

        $this->fakePlannerPlan([
            'status' => 'needs_info',
            'actions' => [
                [
                    'id' => 'a1',
                    'type' => 'create_students_batch',
                    'entity' => 'student',
                    'params' => [
                        'names' => ['Vicente'],
                        'grade' => null,
                        'section' => null,
                        'subject_name' => null,
                        'teacher_name' => null,
                        'student_name' => null,
                        'new_grade' => null,
                        'new_section' => null,
                        'new_name' => null,
                        'operation' => null,
                        'all_in_grade' => null,
                        'invite_code' => null,
                    ],
                    'status' => 'needs_info',
                    'missing_slots' => [
                        [
                            'name' => 'grade',
                            'description' => '¿En qué grado va Vicente?',
                            'required' => true,
                            'value' => null,
                            'source' => 'user',
                        ],
                    ],
                    'depends_on' => [],
                    'confirmation_required' => true,
                ],
            ],
            'summary' => 'Voy a crear a Vicente.',
            'requires_confirmation' => true,
            'all_or_nothing' => false,
        ]);

        $response = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'Crea al alumno Vicente',
        ]);

        $response->assertOk()
            ->assertJsonPath('needs_clarification', true);

        $this->assertStringContainsString(
            '¿En qué grado va Vicente?',
            (string) $response->json('message')
        );
        $this->assertNotNull($response->json('pending_plan'));
    }

    public function test_multi_action_plan_with_slots_asks_then_executes(): void
    {
        [$director, $colegio] = $this->directorContext();

        $this->fakePlannerPlan([
            'status' => 'needs_info',
            'actions' => [
                [
                    'id' => 'a1',
                    'type' => 'create_teacher',
                    'entity' => 'teacher',
                    'params' => [
                        'teacher_name' => 'Vicente José',
                        'subject_name' => 'Matemática',
                        'grades' => ['1ro', '2do', '3ro', '4to', '5to', '6to'],
                        'student_name' => null,
                        'names' => null,
                        'grade' => null,
                        'section' => null,
                        'new_grade' => null,
                        'new_section' => null,
                        'new_name' => null,
                        'operation' => null,
                        'all_in_grade' => null,
                        'invite_code' => null,
                    ],
                    'status' => 'pending',
                    'missing_slots' => [],
                    'depends_on' => [],
                    'confirmation_required' => true,
                ],
                [
                    'id' => 'a2',
                    'type' => 'create_students_batch',
                    'entity' => 'student',
                    'params' => [
                        'names' => ['Carlos'],
                        'grade' => null,
                        'section' => null,
                        'subject_name' => null,
                        'teacher_name' => null,
                        'student_name' => null,
                        'new_grade' => null,
                        'new_section' => null,
                        'new_name' => null,
                        'operation' => null,
                        'all_in_grade' => null,
                        'invite_code' => null,
                    ],
                    'status' => 'needs_info',
                    'missing_slots' => [
                        [
                            'name' => 'grade',
                            'description' => '¿En qué grado va Carlos?',
                            'required' => true,
                            'value' => null,
                            'source' => 'user',
                        ],
                    ],
                    'depends_on' => [],
                    'confirmation_required' => true,
                ],
            ],
            'summary' => 'Voy a crear al profesor Vicente José y al alumno Carlos.',
            'requires_confirmation' => true,
            'all_or_nothing' => false,
        ]);

        // Primer mensaje: faltan datos.
        $first = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'Crea al profesor Vicente José de Matemática de 1ro a 6to y crea al alumno Carlos',
        ]);
        $first->assertOk()
            ->assertJsonPath('needs_clarification', true);
        $this->assertStringContainsString('¿En qué grado va Carlos?', (string) $first->json('message'));

        // Segundo mensaje: proporciona el grado.
        $second = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => '3ro',
        ]);
        $second->assertOk()
            ->assertJsonPath('requires_confirmation', true);

        $pending = $second->json('pending_actions');
        $this->assertCount(2, $pending);
        $this->assertSame('create_teacher', $pending[0]['intent']);
        $this->assertSame('create_students_batch', $pending[1]['intent']);
        $this->assertSame('3ro', $pending[1]['data']['grade']);

        // Confirmar con "sí".
        $execute = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'sí',
        ]);
        $execute->assertOk();

        $this->assertDatabaseHas('teacher_invites', [
            'colegio_id' => $colegio->id,
            'name' => 'Vicente José',
        ]);
    }

    private function fakePlannerPlan(array $plan): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => json_encode($plan, JSON_UNESCAPED_UNICODE),
                    ],
                ]],
            ]),
        ]);
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
            'invite_code' => 'CEN-'.uniqid(),
            'codes_pin' => Colegio::hashPinFromInvite('CEN-'.uniqid()),
            'director_user_id' => $director->id,
        ]);
        $director->update(['colegio_id' => $colegio->id]);

        return [$director->fresh(), $colegio];
    }
}

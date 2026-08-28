<?php

namespace Tests\Feature;

use App\Models\Colegio;
use App\Models\Course;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class DirectorActionPlanExecutionTest extends TestCase
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

    public function test_plan_executes_multiple_actions_successfully(): void
    {
        [$director, $colegio] = $this->directorContext();

        $this->fakePlannerPlan([
            'status' => 'pending',
            'actions' => [
                $this->createTeacherAction('Vicente José', 'Matemática', ['1ro', '2do']),
                $this->createTeacherAction('Georgina Pérez', 'Lenguaje', ['3ro', '4to']),
            ],
            'summary' => 'Voy a hacer:\n1. Crear profesor Vicente José.\n2. Crear profesora Georgina Pérez.',
            'requires_confirmation' => true,
            'all_or_nothing' => false,
        ]);

        $response = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'Crea a Vicente José de Matemática de 1ro y 2do, y a Georgina Pérez de Lenguaje de 3ro y 4to',
        ]);

        $response->assertOk()
            ->assertJsonPath('requires_confirmation', true);

        $execute = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'sí',
        ]);
        $execute->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('teacher_invites', [
            'colegio_id' => $colegio->id,
            'name' => 'Vicente José',
        ]);
        $this->assertDatabaseHas('teacher_invites', [
            'colegio_id' => $colegio->id,
            'name' => 'Georgina Pérez',
        ]);
    }

    public function test_all_or_nothing_rolls_back_everything_on_failure(): void
    {
        [$director, $colegio] = $this->directorContext();

        $pending = [
            [
                'intent' => 'create_students_batch',
                'data' => [
                    'names' => ['Alumno Rollback'],
                    'grade' => '2do',
                    'section' => 'A',
                ],
            ],
            [
                'intent' => 'assign_teacher',
                'data' => [
                    'teacher_name' => 'Profesor Inexistente',
                    'subject_name' => 'Matemática',
                    'grades' => ['2do'],
                ],
            ],
        ];

        $execute = $this->withSession([
            'director_ai_pending_actions' => $pending,
            'director_ai_pending_meta' => ['all_or_nothing' => true],
        ])->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'sí',
        ]);

        $execute->assertStatus(422)
            ->assertJsonPath('success', false);
        $this->assertStringContainsString('revirtieron todos los cambios', (string) $execute->json('message'));

        $this->assertDatabaseMissing('students', [
            'colegio_id' => $colegio->id,
            'name' => 'Alumno Rollback',
        ]);
    }

    public function test_best_effort_continues_after_one_action_fails(): void
    {
        [$director, $colegio] = $this->directorContext();

        $pending = [
            [
                'intent' => 'create_students_batch',
                'data' => [
                    'names' => ['Carlos Parcial'],
                    'grade' => '2do',
                    'section' => 'A',
                ],
            ],
            [
                'intent' => 'assign_teacher',
                'data' => [
                    'teacher_name' => 'Profesor Inexistente',
                    'subject_name' => 'Matemática',
                    'grades' => ['2do'],
                ],
            ],
        ];

        $execute = $this->withSession([
            'director_ai_pending_actions' => $pending,
            'director_ai_pending_meta' => ['all_or_nothing' => false],
        ])->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'sí',
        ]);

        $execute->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('any_success', true);

        $actions = $execute->json('actions');
        $this->assertCount(2, $actions);
        $this->assertTrue($actions[0]['success']);
        $this->assertFalse($actions[1]['success']);

        $this->assertDatabaseHas('students', [
            'colegio_id' => $colegio->id,
            'name' => 'Carlos Parcial',
        ]);
    }

    public function test_create_students_batch_supports_multiple_grades_in_one_action(): void
    {
        [$director, $colegio] = $this->directorContext();
        $teacher = User::factory()->create([
            'role' => 'profesor',
            'colegio_id' => $colegio->id,
            'name' => 'Profesora Matemática',
            'onboarding_completed' => true,
        ]);

        $this->fakePlannerPlan([
            'status' => 'pending',
            'actions' => [
                [
                    'id' => (string) Str::uuid(),
                    'type' => 'create_students_batch',
                    'entity' => 'student',
                    'params' => [
                        'students_data' => [
                            ['name' => 'Juan Pérez', 'grade' => '1ro'],
                            ['name' => 'María Gómez', 'grade' => '3ro', 'subject_name' => 'Matemática', 'teacher_name' => 'Profesora Matemática'],
                            ['name' => 'Sofía Ruiz', 'grade' => '3ro', 'subject_name' => 'Matemática', 'teacher_name' => 'Profesora Matemática'],
                        ],
                    ],
                    'status' => 'pending',
                    'missing_slots' => [],
                    'depends_on' => [],
                    'confirmation_required' => true,
                ],
            ],
            'summary' => 'Voy a crear a Juan Pérez en 1ro, y a María Gómez y Sofía Ruiz en 3ro con Matemática.',
            'requires_confirmation' => true,
            'all_or_nothing' => false,
        ]);

        $response = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'crea a Juan Pérez en 1ro, y a María Gómez y Sofía Ruiz en 3ro con Matemática con la Profesora Matemática',
        ]);
        $response->assertOk()->assertJsonPath('requires_confirmation', true);

        $execute = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'sí',
        ]);
        $execute->assertOk()->assertJsonPath('success', true);

        $juan = Student::query()->where('colegio_id', $colegio->id)->where('name', 'Juan Pérez')->firstOrFail();
        $maria = Student::query()->where('colegio_id', $colegio->id)->where('name', 'María Gómez')->firstOrFail();
        $sofia = Student::query()->where('colegio_id', $colegio->id)->where('name', 'Sofía Ruiz')->firstOrFail();

        $this->assertSame('1ro', $juan->grade);
        $this->assertSame('3ro', $maria->grade);
        $this->assertSame('3ro', $sofia->grade);

        $this->assertSame(0, $juan->courses()->count());

        $mathCourses = Course::query()
            ->where('colegio_id', $colegio->id)
            ->where('subject_name', 'Matemática')
            ->where('grade', '3ro')
            ->get();
        $this->assertCount(1, $mathCourses, 'No debe duplicar el curso de Matemática 3ro aunque dos alumnos compartan grado y materia.');
        $this->assertTrue($maria->courses()->where('courses.id', $mathCourses->first()->id)->exists());
        $this->assertTrue($sofia->courses()->where('courses.id', $mathCourses->first()->id)->exists());
    }

    public function test_create_students_batch_legacy_names_and_grade_format_still_works(): void
    {
        [$director, $colegio] = $this->directorContext();

        $this->fakePlannerPlan([
            'status' => 'pending',
            'actions' => [
                $this->createStudentsBatchAction(['Carlos Legado', 'Ana Legado'], '2do'),
            ],
            'summary' => 'Voy a crear a Carlos Legado y Ana Legado en 2do.',
            'requires_confirmation' => true,
            'all_or_nothing' => false,
        ]);

        $response = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'crea a Carlos Legado y Ana Legado en 2do',
        ]);
        $response->assertOk()->assertJsonPath('requires_confirmation', true);

        $execute = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'sí',
        ]);
        $execute->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('students', [
            'colegio_id' => $colegio->id,
            'name' => 'Carlos Legado',
            'grade' => '2do',
        ]);
        $this->assertDatabaseHas('students', [
            'colegio_id' => $colegio->id,
            'name' => 'Ana Legado',
            'grade' => '2do',
        ]);
    }

    public function test_unknown_action_type_is_rejected(): void
    {
        [$director] = $this->directorContext();

        $this->fakePlannerPlan([
            'status' => 'pending',
            'actions' => [
                [
                    'id' => 'a1',
                    'type' => 'destroy_everything',
                    'entity' => 'general',
                    'params' => [],
                    'status' => 'pending',
                    'missing_slots' => [],
                    'depends_on' => [],
                    'confirmation_required' => true,
                ],
            ],
            'summary' => 'Voy a destruir todo.',
            'requires_confirmation' => true,
            'all_or_nothing' => false,
        ]);

        $response = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'Destruye todo',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
        $this->assertStringContainsStringIgnoringCase('no está soportada', (string) $response->json('message'));
    }

    public function test_missing_required_params_are_rejected(): void
    {
        [$director] = $this->directorContext();

        $this->fakePlannerPlan([
            'status' => 'pending',
            'actions' => [
                [
                    'id' => 'a1',
                    'type' => 'create_teacher',
                    'entity' => 'teacher',
                    'params' => [
                        'subject_name' => 'Matemática',
                    ],
                    'status' => 'pending',
                    'missing_slots' => [],
                    'depends_on' => [],
                    'confirmation_required' => true,
                ],
            ],
            'summary' => 'Voy a crear profesor.',
            'requires_confirmation' => true,
            'all_or_nothing' => false,
        ]);

        $response = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'Crea profesor de Matemática',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
        $this->assertStringContainsStringIgnoringCase('teacher_name', (string) $response->json('message'));
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

    private function createTeacherAction(string $name, string $subject, array $grades): array
    {
        return [
            'id' => (string) Str::uuid(),
            'type' => 'create_teacher',
            'entity' => 'teacher',
            'params' => [
                'teacher_name' => $name,
                'subject_name' => $subject,
                'grades' => $grades,
            ],
            'status' => 'pending',
            'missing_slots' => [],
            'depends_on' => [],
            'confirmation_required' => true,
        ];
    }

    private function createStudentsBatchAction(array $names, string $grade): array
    {
        return [
            'id' => (string) Str::uuid(),
            'type' => 'create_students_batch',
            'entity' => 'student',
            'params' => [
                'names' => $names,
                'grade' => $grade,
            ],
            'status' => 'pending',
            'missing_slots' => [],
            'depends_on' => [],
            'confirmation_required' => true,
        ];
    }

    private function assignTeacherAction(string $name, string $subject, array $grades): array
    {
        return [
            'id' => (string) Str::uuid(),
            'type' => 'assign_teacher',
            'entity' => 'teacher',
            'params' => [
                'teacher_name' => $name,
                'subject_name' => $subject,
                'grades' => $grades,
            ],
            'status' => 'pending',
            'missing_slots' => [],
            'depends_on' => [],
            'confirmation_required' => true,
        ];
    }
}

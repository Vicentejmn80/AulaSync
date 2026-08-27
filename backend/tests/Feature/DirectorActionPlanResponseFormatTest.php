<?php

namespace Tests\Feature;

use App\Models\Colegio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DirectorActionPlanResponseFormatTest extends TestCase
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

    public function test_confirmation_response_includes_full_action_plan(): void
    {
        [$director] = $this->directorContext();
        $actionId = 'a1_'.uniqid();

        $this->fakePlannerPlan([
            'id' => 'plan_'.uniqid(),
            'status' => 'pending',
            'actions' => [
                [
                    'id' => $actionId,
                    'type' => 'create_teacher',
                    'entity' => 'teacher',
                    'params' => [
                        'teacher_name' => 'Vicente José',
                        'subject_name' => 'Matemática',
                        'grades' => ['1ro', '2do'],
                    ],
                    'status' => 'pending',
                    'missing_slots' => [],
                    'depends_on' => [],
                    'confirmation_required' => true,
                ],
            ],
            'summary' => 'Voy a crear al profesor Vicente José.',
            'requires_confirmation' => true,
            'all_or_nothing' => true,
        ]);

        $response = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'Crea al profesor Vicente José de Matemática de 1ro y 2do',
        ]);

        $response->assertOk()
            ->assertJsonPath('requires_confirmation', true)
            ->assertJsonPath('action_plan.summary', 'Voy a crear al profesor Vicente José.')
            ->assertJsonPath('action_plan.actions.0.id', $actionId)
            ->assertJsonPath('action_plan.actions.0.type', 'create_teacher')
            ->assertJsonPath('action_plan.actions.0.params.teacher_name', 'Vicente José');
    }

    public function test_slot_response_includes_full_action_plan(): void
    {
        [$director] = $this->directorContext();
        $actionId = 'a1_'.uniqid();

        $this->fakePlannerPlan([
            'id' => 'plan_'.uniqid(),
            'status' => 'needs_info',
            'actions' => [
                [
                    'id' => $actionId,
                    'type' => 'create_students_batch',
                    'entity' => 'student',
                    'params' => [
                        'names' => ['Carlos'],
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
            'summary' => 'Voy a crear al alumno Carlos.',
            'requires_confirmation' => true,
            'all_or_nothing' => false,
        ]);

        $response = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'Crea al alumno Carlos en el colegio',
        ]);

        $response->assertOk()
            ->assertJsonPath('needs_clarification', true)
            ->assertJsonPath('action_plan.status', 'needs_info')
            ->assertJsonPath('action_plan.actions.0.id', $actionId)
            ->assertJsonPath('action_plan.actions.0.missing_slots.0.name', 'grade')
            ->assertJsonPath('next_slot.slot', 'grade');
    }

    public function test_legacy_response_does_not_include_action_plan(): void
    {
        [$director] = $this->directorContext();

        config([
            'services.openai.director_enabled' => false,
            'services.openai.director_test_enabled' => false,
        ]);

        $response = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'Crea al profesor Vicente José de Matemática de 1ro y 2do',
        ]);

        $response->assertOk()
            ->assertJsonPath('requires_confirmation', true);
        $this->assertArrayNotHasKey('action_plan', (array) $response->json());
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
}

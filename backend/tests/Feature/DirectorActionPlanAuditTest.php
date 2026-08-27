<?php

namespace Tests\Feature;

use App\Models\Colegio;
use App\Models\DirectorAiOperationLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DirectorActionPlanAuditTest extends TestCase
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

    public function test_each_action_creates_audit_log_with_plan_and_action_ids(): void
    {
        [$director, $colegio] = $this->directorContext();
        $actionId1 = 'a1_'.uniqid();
        $actionId2 = 'a2_'.uniqid();

        $this->fakePlannerPlan([
            'id' => 'plan_'.uniqid(),
            'status' => 'pending',
            'actions' => [
                [
                    'id' => $actionId1,
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
                [
                    'id' => $actionId2,
                    'type' => 'create_teacher',
                    'entity' => 'teacher',
                    'params' => [
                        'teacher_name' => 'Georgina Pérez',
                        'subject_name' => 'Lenguaje',
                        'grades' => ['3ro', '4to'],
                    ],
                    'status' => 'pending',
                    'missing_slots' => [],
                    'depends_on' => [],
                    'confirmation_required' => true,
                ],
            ],
            'summary' => 'Voy a crear dos profesores.',
            'requires_confirmation' => true,
            'all_or_nothing' => true,
        ]);

        $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'Crea a Vicente José y Georgina Pérez',
        ]);

        $this->assertDatabaseCount('director_ai_operation_logs', 2);

        $first = DirectorAiOperationLog::query()
            ->where('director_user_id', $director->id)
            ->where('action_id', $actionId1)
            ->first();

        $this->assertNotNull($first);
        $this->assertSame('create_teacher', $first->intent);
        $this->assertSame('pending_confirmation', $first->status);
        $this->assertNotNull($first->action_plan_id);

        $second = DirectorAiOperationLog::query()
            ->where('director_user_id', $director->id)
            ->where('action_id', $actionId2)
            ->first();

        $this->assertNotNull($second);
        $this->assertSame($first->action_plan_id, $second->action_plan_id);

        $execute = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'sí',
        ]);
        $execute->assertOk();

        $first->refresh();
        $second->refresh();
        $this->assertSame('verified', $first->status);
        $this->assertSame('verified', $second->status);
    }

    public function test_failed_action_logs_error_payload(): void
    {
        [$director] = $this->directorContext();

        $pending = [
            [
                'intent' => 'assign_teacher',
                'data' => [
                    'teacher_name' => 'Inexistente',
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

        $execute->assertStatus(422);

        $log = DirectorAiOperationLog::query()
            ->where('director_user_id', $director->id)
            ->where('intent', 'assign_teacher')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('failed', $log->status);
        $this->assertNotNull($log->error_payload);
        $this->assertArrayHasKey('message', $log->error_payload ?? []);
    }

    public function test_skipped_action_is_logged_as_skipped(): void
    {
        [$director, $colegio] = $this->directorContext();
        $planId = 'plan_'.uniqid();
        $skippedActionId = 'a_skip_'.uniqid();

        $this->fakePlannerPlan([
            'id' => $planId,
            'status' => 'pending',
            'actions' => [
                [
                    'id' => $skippedActionId,
                    'type' => 'delete_teacher',
                    'entity' => 'teacher',
                    'params' => [
                        'teacher_name' => 'Salvador',
                    ],
                    'status' => 'skipped',
                    'missing_slots' => [],
                    'depends_on' => [],
                    'confirmation_required' => false,
                ],
                [
                    'id' => 'a_ok_'.uniqid(),
                    'type' => 'create_teacher',
                    'entity' => 'teacher',
                    'params' => [
                        'teacher_name' => 'Vicente José',
                        'subject_name' => 'Matemática',
                        'grades' => ['1ro'],
                    ],
                    'status' => 'pending',
                    'missing_slots' => [],
                    'depends_on' => [],
                    'confirmation_required' => true,
                ],
            ],
            'summary' => 'Voy a saltar una acción y crear un profesor.',
            'requires_confirmation' => true,
            'all_or_nothing' => true,
        ]);

        $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'Crea al profesor Vicente José',
        ]);

        $skippedLog = DirectorAiOperationLog::query()
            ->where('director_user_id', $director->id)
            ->where('action_id', $skippedActionId)
            ->first();

        $this->assertNotNull($skippedLog);
        $this->assertSame('delete_teacher', $skippedLog->intent);
        $this->assertSame('skipped', $skippedLog->status);
        $this->assertNotNull($skippedLog->action_plan_id);
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

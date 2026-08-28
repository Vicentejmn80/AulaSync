<?php

namespace Tests\Feature;

use App\Models\Colegio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Prioridad 0 — Integridad del mensaje final.
 *
 * El mensaje post-ejecución debe construirse exclusivamente a partir de los
 * resultados reales de ejecución, nunca del texto crudo del usuario.
 */
class DirectorFinalMessageIntegrityTest extends TestCase
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

    public function test_final_message_only_describes_executed_actions(): void
    {
        [$director, $colegio] = $this->directorContext();

        $narratePayloads = [];

        Http::fake(function ($request) use (&$narratePayloads) {
            $body = json_decode($request->body(), true);
            $messages = $body['messages'] ?? [];
            $lastUser = '';
            foreach ($messages as $msg) {
                if (($msg['role'] ?? '') === 'user') {
                    $lastUser = (string) ($msg['content'] ?? '');
                }
            }

            // La llamada de narración se distingue porque NO usa response_format
            // (el planificador usa json_schema).
            if (! isset($body['response_format'])) {
                $narratePayloads[] = $lastUser;

                return Http::response([
                    'choices' => [[
                        'message' => [
                            'role' => 'assistant',
                            'content' => '✅ Profesor Vicente José creado exitosamente.',
                        ],
                    ]],
                ]);
            }

            // Planificador: UNA sola acción (crear profesor). El texto original
            // menciona también alumnos, pero el plan confirmado NO los incluye.
            return Http::response([
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => json_encode([
                            'id' => 'plan_integrity',
                            'status' => 'pending',
                            'actions' => [
                                [
                                    'id' => 'a1',
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
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                ]],
            ]);
        });

        $draft = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'Crea al profesor Vicente José de Matemática y agrega a los alumnos Carlos Gutiérrez y Salvador Pérez al curso',
        ]);
        $draft->assertOk()->assertJsonPath('requires_confirmation', true);

        $execute = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'sí',
        ]);
        $execute->assertOk()->assertJsonPath('success', true);

        // 1. El payload de narración NUNCA contiene el texto crudo del usuario.
        foreach ($narratePayloads as $payload) {
            $this->assertStringNotContainsString('Carlos', $payload);
            $this->assertStringNotContainsString('Salvador', $payload);
            $decoded = json_decode($payload, true);
            $this->assertIsArray($decoded);
            $this->assertArrayHasKey('executed_results', $decoded);
            $this->assertArrayNotHasKey('pedido', $decoded);
        }

        // 2. El mensaje final no menciona entidades que no se ejecutaron.
        $message = (string) $execute->json('message');
        $this->assertStringNotContainsString('Carlos', $message);
        $this->assertStringNotContainsString('Salvador', $message);

        // 3. La acción real sí se ejecutó.
        $this->assertDatabaseHas('teacher_invites', [
            'colegio_id' => $colegio->id,
            'name' => 'Vicente José',
        ]);
    }

    public function test_final_message_fallback_compose_reply_uses_only_results(): void
    {
        [$director, $colegio] = $this->directorContext();

        // Sin OpenAI: narrate cae a composeReply, que solo usa $results.
        config([
            'services.openai.director_enabled' => false,
            'services.openai.director_test_enabled' => false,
        ]);

        $pending = [[
            'intent' => 'create_teacher',
            'data' => [
                'teacher_name' => 'Vicente José',
                'subject_name' => 'Matemática',
                'grades' => ['1ro'],
            ],
        ]];

        $execute = $this->withSession([
            'director_ai_pending_actions' => $pending,
            'director_ai_pending_meta' => ['all_or_nothing' => true],
        ])->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'sí',
        ]);

        $execute->assertOk();

        $message = (string) $execute->json('message');
        $this->assertStringNotContainsString('Carlos', $message);
        $this->assertStringNotContainsString('Salvador', $message);

        $this->assertDatabaseHas('teacher_invites', [
            'colegio_id' => $colegio->id,
            'name' => 'Vicente José',
        ]);
    }
}

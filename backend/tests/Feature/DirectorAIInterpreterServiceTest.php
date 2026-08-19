<?php

namespace Tests\Feature;

use App\Models\Colegio;
use App\Models\User;
use App\Services\DirectorAIInterpreterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DirectorAIInterpreterServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_structured_interpreter_builds_compound_teacher_plan_and_normalizes_grades(): void
    {
        config([
            'services.openai.key' => 'test-key',
            'services.openai.director_enabled' => true,
            'services.openai.director_test_enabled' => true,
        ]);
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'tool_calls' => [
                            [
                                'type' => 'function',
                                'function' => [
                                    'name' => 'create_teacher',
                                    'arguments' => json_encode(['teacher_name' => 'Jason David']),
                                ],
                            ],
                            [
                                'type' => 'function',
                                'function' => [
                                    'name' => 'create_course',
                                    'arguments' => json_encode([
                                        'subject_name' => 'Inglés',
                                        'grades' => ['primer grado', 'sexto grado'],
                                        'teacher_name' => 'Jason David',
                                    ]),
                                ],
                            ],
                        ],
                    ],
                ]],
            ]),
        ]);

        $director = User::factory()->create(['role' => 'director', 'onboarding_completed' => true]);
        $colegio = Colegio::create([
            'name' => 'Colegio Central',
            'invite_code' => 'CEN-1001',
            'codes_pin' => Colegio::hashPinFromInvite('CEN-1001'),
            'director_user_id' => $director->id,
        ]);
        $director->update(['colegio_id' => $colegio->id]);

        $result = app(DirectorAIInterpreterService::class)->interpret(
            $director->fresh(),
            'Crea a Jason y dale inglés de primero a sexto',
            [],
            [],
        );

        $this->assertCount(1, $result['actions']);
        $this->assertSame('create_teacher', $result['actions'][0]['intent']);
        $this->assertSame('Jason David', $result['actions'][0]['data']['teacher_name']);
        $this->assertSame('Inglés', $result['actions'][0]['data']['subject_name']);
        $this->assertSame(['1ro', '6to'], $result['actions'][0]['data']['grades']);

        Http::assertSent(fn ($request) => $request['tools'][0]['type'] === 'function'
            && count($request['tools']) >= 10);
    }

    public function test_interpreter_merges_course_tools_even_without_teacher_name_on_course(): void
    {
        config([
            'services.openai.key' => 'test-key',
            'services.openai.director_enabled' => true,
            'services.openai.director_test_enabled' => true,
        ]);
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'tool_calls' => [
                            [
                                'type' => 'function',
                                'function' => [
                                    'name' => 'create_teacher',
                                    'arguments' => json_encode(['teacher_name' => 'Yovanny Andrade']),
                                ],
                            ],
                            [
                                'type' => 'function',
                                'function' => [
                                    'name' => 'create_course',
                                    'arguments' => json_encode([
                                        'subject_name' => 'Inglés',
                                        'grades' => ['1ero', '2do', '3ero', '4to', '5to', '6to'],
                                    ]),
                                ],
                            ],
                        ],
                    ],
                ]],
            ]),
        ]);

        $director = User::factory()->create(['role' => 'director', 'onboarding_completed' => true]);
        $colegio = Colegio::create([
            'name' => 'Colegio Central',
            'invite_code' => 'CEN-1002',
            'codes_pin' => Colegio::hashPinFromInvite('CEN-1002'),
            'director_user_id' => $director->id,
        ]);
        $director->update(['colegio_id' => $colegio->id]);

        $result = app(DirectorAIInterpreterService::class)->interpret(
            $director->fresh(),
            'crea al profesor yovanny andrade y los cursos de ingles de 1ero a 6to',
            [],
            [],
        );

        $this->assertCount(1, $result['actions']);
        $this->assertSame('create_teacher', $result['actions'][0]['intent']);
        $this->assertSame('Yovanny Andrade', $result['actions'][0]['data']['teacher_name']);
        $this->assertSame('Inglés', $result['actions'][0]['data']['subject_name']);
        $this->assertSame(['1ro', '2do', '3ro', '4to', '5to', '6to'], $result['actions'][0]['data']['grades']);
    }

    public function test_system_prompt_includes_school_roster_and_nova_lookup_rules(): void
    {
        config([
            'services.openai.key' => 'test-key',
            'services.openai.director_enabled' => true,
            'services.openai.director_test_enabled' => true,
        ]);
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'El código de José Martínez es DOC-ABCD.',
                    ],
                ]],
            ]),
        ]);

        $director = User::factory()->create(['role' => 'director', 'onboarding_completed' => true]);
        $colegio = Colegio::create([
            'name' => 'Colegio Central',
            'invite_code' => 'CEN-1003',
            'codes_pin' => Colegio::hashPinFromInvite('CEN-1003'),
            'director_user_id' => $director->id,
        ]);
        $director->update(['colegio_id' => $colegio->id]);

        \App\Models\TeacherInvite::create([
            'colegio_id' => $colegio->id,
            'created_by' => $director->id,
            'name' => 'José Martínez',
            'email' => 'jose@colegio.edu',
            'invite_code' => 'DOC-ABCD',
        ]);

        app(DirectorAIInterpreterService::class)->interpret(
            $director->fresh(),
            'dame el código de José Martínez',
            [],
            [],
        );

        Http::assertSent(function ($request) {
            $system = (string) data_get($request, 'messages.0.content');

            return ($request['temperature'] ?? null) == 0.7
                && ($request['top_p'] ?? null) == 0.9
                && str_contains($system, 'Eres Nova')
                && str_contains($system, 'REGLA DEL SÁNDWICH')
                && str_contains($system, 'Total de alumnos:')
                && str_contains($system, 'Total de profesores activos:')
                && str_contains($system, 'Colegio Central')
                && str_contains($system, 'José Martínez')
                && str_contains($system, 'DOC-ABCD');
        });
    }
}

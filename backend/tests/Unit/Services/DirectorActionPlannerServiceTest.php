<?php

namespace Tests\Unit\Services;

use App\Models\Colegio;
use App\Models\User;
use App\Services\DirectorActionPlannerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DirectorActionPlannerServiceTest extends TestCase
{
    use RefreshDatabase;

    private DirectorActionPlannerService $planner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->planner = app(DirectorActionPlannerService::class);
    }

    public function test_plan_complex_phrase_with_three_actions(): void
    {
        config([
            'services.openai.key' => 'test-key',
            'services.openai.director_enabled' => true,
            'services.openai.director_test_enabled' => true,
        ]);

        $this->fakeOpenAiPlan([
            'status' => 'pending',
            'actions' => [
                [
                    'id' => 'a1',
                    'type' => 'create_students_batch',
                    'entity' => 'student',
                    'params' => [
                        'names' => ['Vicente', 'Georgina'],
                        'grade' => '3ro',
                        'section' => 'A',
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
                    'status' => 'pending',
                    'missing_slots' => [],
                    'depends_on' => [],
                    'confirmation_required' => true,
                ],
                [
                    'id' => 'a2',
                    'type' => 'update_student',
                    'entity' => 'student',
                    'params' => [
                        'student_name' => 'Carlos',
                        'new_grade' => '4to',
                        'new_section' => 'B',
                        'names' => null,
                        'grade' => null,
                        'section' => null,
                        'subject_name' => null,
                        'teacher_name' => null,
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
            'summary' => 'Voy a crear a Vicente y Georgina en 3ro A y cambiar a Carlos a 4to B.',
            'requires_confirmation' => true,
            'all_or_nothing' => false,
        ]);

        $director = $this->makeDirector();
        $plan = $this->planner->plan($director, 'crea a Vicente y Georgina en 3ro A, cambia a Carlos a 4to B');

        $this->assertSame('pending', $plan['status']);
        $this->assertCount(2, $plan['actions']);
        $this->assertSame('create_students_batch', $plan['actions'][0]['type']);
        $this->assertSame(['Vicente', 'Georgina'], $plan['actions'][0]['params']['names']);
        $this->assertSame('update_student', $plan['actions'][1]['type']);
        $this->assertSame('Carlos', $plan['actions'][1]['params']['student_name']);
    }

    public function test_plan_with_dependencies(): void
    {
        config([
            'services.openai.key' => 'test-key',
            'services.openai.director_enabled' => true,
            'services.openai.director_test_enabled' => true,
        ]);

        $this->fakeOpenAiPlan([
            'status' => 'pending',
            'actions' => [
                [
                    'id' => 'a1',
                    'type' => 'create_course',
                    'entity' => 'teacher',
                    'params' => [
                        'subject_name' => 'Robótica',
                        'grade' => '3ro',
                        'section' => 'A',
                        'teacher_name' => null,
                        'names' => null,
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
                [
                    'id' => 'a2',
                    'type' => 'enroll_students_course',
                    'entity' => 'student',
                    'params' => [
                        'names' => ['Vicente'],
                        'subject_name' => 'Robótica',
                        'grade' => '3ro',
                        'section' => 'A',
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
                    'depends_on' => ['a1'],
                    'confirmation_required' => true,
                ],
            ],
            'summary' => 'Voy a crear el curso de Robótica 3ro A y matricular a Vicente.',
            'requires_confirmation' => true,
            'all_or_nothing' => false,
        ]);

        $director = $this->makeDirector();
        $plan = $this->planner->plan($director, 'crea Robótica 3ro A y matricula a Vicente');

        $this->assertCount(2, $plan['actions']);
        $this->assertSame(['a1'], $plan['actions'][1]['depends_on']);
    }

    public function test_plan_fallback_when_openai_unavailable(): void
    {
        config([
            'services.openai.key' => '',
            'services.openai.director_enabled' => false,
        ]);

        $director = $this->makeDirector();
        $plan = $this->planner->plan(
            $director,
            'Crea el profesor de matemáticas llamado Vicente José. Agrega a los alumnos Carlos Gutiérrez y Salvador Pérez a su materia de matemáticas, que ambos son de 3ro.'
        );

        $this->assertCount(2, $plan['actions']);
        $this->assertSame('create_teacher', $plan['actions'][0]['type']);
        $this->assertSame('Vicente José', $plan['actions'][0]['params']['teacher_name']);
        $this->assertSame('enroll_students_course', $plan['actions'][1]['type']);
        $this->assertSame(['Carlos Gutiérrez', 'Salvador Pérez'], $plan['actions'][1]['params']['names']);
    }

    public function test_plan_missing_slots_ask_for_info(): void
    {
        config([
            'services.openai.key' => 'test-key',
            'services.openai.director_enabled' => true,
            'services.openai.director_test_enabled' => true,
        ]);

        $this->fakeOpenAiPlan([
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

        $director = $this->makeDirector();
        $plan = $this->planner->plan($director, 'Crea a Vicente');

        $this->assertSame('needs_info', $plan['status']);
        $this->assertCount(1, $plan['actions']);
        $this->assertCount(1, $plan['actions'][0]['missing_slots']);
        $this->assertSame('grade', $plan['actions'][0]['missing_slots'][0]['name']);
        $this->assertSame('¿En qué grado va Vicente?', $plan['actions'][0]['missing_slots'][0]['description']);
    }

    public function test_system_prompt_contains_many_shot_for_mixed_teacher_and_students_flow(): void
    {
        $director = $this->makeDirector();
        $reflection = new \ReflectionClass($this->planner);
        $method = $reflection->getMethod('systemPrompt');
        $method->setAccessible(true);

        $prompt = (string) $method->invoke($this->planner, $director, []);

        $this->assertStringContainsString('Crea al profesor Junior Vázquez como profesor de biología de 1ro a 6to', $prompt);
        $this->assertStringContainsString('"type": "create_courses_batch"', $prompt);
        $this->assertStringContainsString('"type": "create_students_batch"', $prompt);
        $this->assertStringContainsString('Nunca conviertas "de segundo/de tercero/para cuarto" en nombres de persona', $prompt);
        $this->assertStringContainsString('factual_lookup_mode', $prompt);
        $this->assertStringContainsString('sync_all_enrollments', $prompt);
        $this->assertStringContainsString('subject_name=null y agrega missing_slots', $prompt);
        $this->assertStringContainsString('de 2do grado a 6to grado', $prompt);
        $this->assertStringContainsString('NUNCA solo a los extremos', $prompt);
    }

    public function test_plan_expands_grade_range_when_llm_returns_only_endpoints(): void
    {
        config([
            'services.openai.key' => 'test-key',
            'services.openai.director_enabled' => true,
            'services.openai.director_test_enabled' => true,
        ]);

        $this->fakeOpenAiPlan([
            'status' => 'pending',
            'actions' => [[
                'id' => 'a1',
                'type' => 'create_teacher',
                'entity' => 'teacher',
                'params' => [
                    'teacher_name' => 'Carlos Gutiérrez',
                    'subject_name' => 'Biología',
                    'grades' => ['2do', '6to'],
                    'student_name' => null,
                    'names' => null,
                    'grade' => null,
                    'courses_data' => null,
                    'students_data' => null,
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
            ]],
            'summary' => 'Voy a hacer:\n1. Crear profesor Carlos Gutiérrez',
            'requires_confirmation' => true,
            'all_or_nothing' => false,
        ]);

        $director = $this->makeDirector();
        $plan = $this->planner->plan(
            $director,
            'Quiero que creas al profesor Carlos Gutiérrez, que va a ser el profesor de biología de 2do grado a 6to grado.'
        );

        $this->assertSame(['2do', '3ro', '4to', '5to', '6to'], $plan['actions'][0]['params']['grades']);
        $this->assertStringContainsString('2do, 3ro, 4to, 5to, 6to', $plan['summary']);
    }

    public function test_plan_marks_missing_subject_for_assign_teacher_when_generic(): void
    {
        config([
            'services.openai.key' => 'test-key',
            'services.openai.director_enabled' => true,
            'services.openai.director_test_enabled' => true,
        ]);

        $this->fakeOpenAiPlan([
            'status' => 'pending',
            'actions' => [[
                'id' => 'a1',
                'type' => 'assign_teacher',
                'entity' => 'teacher',
                'params' => [
                    'teacher_name' => 'Jose Marrero',
                    'subject_name' => 'cursos',
                    'grades' => ['3ro'],
                    'student_name' => null,
                    'names' => null,
                    'grade' => null,
                    'courses_data' => null,
                    'students_data' => null,
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
            ]],
            'summary' => 'Asignar cursos.',
            'requires_confirmation' => true,
            'all_or_nothing' => false,
        ]);

        $director = $this->makeDirector();
        $plan = $this->planner->plan($director, 'asigna a Jose a 3ro');

        $this->assertSame('needs_info', $plan['status']);
        $this->assertSame('needs_info', $plan['actions'][0]['status']);
        $this->assertSame('subject_name', $plan['actions'][0]['missing_slots'][0]['name']);
        $this->assertNull($plan['actions'][0]['params']['subject_name']);
    }

    private function fakeOpenAiPlan(array $plan): void
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

    private function makeDirector(): User
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

        return $director->fresh();
    }
}

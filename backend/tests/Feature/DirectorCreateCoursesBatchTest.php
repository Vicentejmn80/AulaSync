<?php

namespace Tests\Feature;

use App\Models\Colegio;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class DirectorCreateCoursesBatchTest extends TestCase
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

    // FIXTURE SINTÉTICO: respuesta de OpenAI construida a mano, no una captura real.
    private function fakePlannerPlan(array $plan): void
    {
        Http::fake([
            'api.openai.com*' => Http::response([
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => json_encode($plan, JSON_UNESCAPED_UNICODE),
                    ],
                ]],
            ]),
        ]);
    }

    private function createCoursesBatchAction(array $coursesData): array
    {
        return [
            'id' => (string) Str::uuid(),
            'type' => 'create_courses_batch',
            'entity' => 'teacher',
            'params' => [
                'courses_data' => $coursesData,
            ],
            'status' => 'pending',
            'missing_slots' => [],
            'depends_on' => [],
            'confirmation_required' => true,
        ];
    }

    public function test_batch_creates_multiple_subjects_and_grades_without_teacher(): void
    {
        [$director, $colegio] = $this->directorContext();

        $coursesData = [
            ['subject_name' => 'Matemática', 'grades' => ['3ro', '4to'], 'section' => null, 'teacher_name' => null],
            ['subject_name' => 'Lenguaje', 'grades' => ['3ro', '4to'], 'section' => null, 'teacher_name' => null],
            ['subject_name' => 'Biología', 'grades' => ['3ro', '4to'], 'section' => null, 'teacher_name' => null],
        ];

        $this->fakePlannerPlan([
            'status' => 'pending',
            'actions' => [$this->createCoursesBatchAction($coursesData)],
            'summary' => 'Voy a crear 6 cursos.',
            'requires_confirmation' => true,
            'all_or_nothing' => false,
        ]);

        $response = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'crea matemática, lenguaje y biología para 3ro y 4to',
        ]);

        $response->assertOk()->assertJsonPath('requires_confirmation', true);

        $execute = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'sí',
        ]);
        $execute->assertOk()->assertJsonPath('success', true);

        $this->assertSame(6, Course::where('colegio_id', $colegio->id)->count());
        $this->assertSame(0, Course::where('colegio_id', $colegio->id)->whereNotNull('teacher_id')->count());

        foreach (['Matemática', 'Lenguaje', 'Biología'] as $subject) {
            $this->assertDatabaseHas('courses', [
                'colegio_id' => $colegio->id,
                'subject_name' => $subject,
                'grade' => '3ro',
                'teacher_id' => null,
            ]);
            $this->assertDatabaseHas('courses', [
                'colegio_id' => $colegio->id,
                'subject_name' => $subject,
                'grade' => '4to',
                'teacher_id' => null,
            ]);
            $this->assertDatabaseHas('materias', [
                'colegio_id' => $colegio->id,
                'name' => $subject,
            ]);
        }
    }

    public function test_batch_is_idempotent_on_repeat(): void
    {
        [$director] = $this->directorContext();

        $coursesData = [
            ['subject_name' => 'Historia', 'grades' => ['1ro', '2do'], 'section' => null, 'teacher_name' => null],
        ];

        $this->fakePlannerPlan([
            'status' => 'pending',
            'actions' => [$this->createCoursesBatchAction($coursesData)],
            'summary' => 'Voy a crear 2 cursos.',
            'requires_confirmation' => true,
            'all_or_nothing' => false,
        ]);

        $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'crea historia para 1ro y 2do',
        ]);
        $this->actingAs($director)->postJson(route('director.ai.command'), ['prompt' => 'sí'])
            ->assertJsonPath('success', true);

        $this->assertSame(2, Course::where('subject_name', 'Historia')->count());

        // Repetir la misma orden no debe duplicar filas.
        $this->fakePlannerPlan([
            'status' => 'pending',
            'actions' => [$this->createCoursesBatchAction($coursesData)],
            'summary' => 'Voy a crear 2 cursos.',
            'requires_confirmation' => true,
            'all_or_nothing' => false,
        ]);

        $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'crea historia para 1ro y 2do',
        ]);
        $this->actingAs($director)->postJson(route('director.ai.command'), ['prompt' => 'sí'])
            ->assertJsonPath('success', true);

        $this->assertSame(2, Course::where('subject_name', 'Historia')->count());
    }

    public function test_batch_rejects_entire_lot_when_teacher_name_unknown(): void
    {
        [$director, $colegio] = $this->directorContext();

        $coursesData = [
            ['subject_name' => 'Química', 'grades' => ['5to'], 'section' => null, 'teacher_name' => null],
            ['subject_name' => 'Física', 'grades' => ['5to'], 'section' => null, 'teacher_name' => 'Zzz Inexistente'],
        ];

        $this->fakePlannerPlan([
            'status' => 'pending',
            'actions' => [$this->createCoursesBatchAction($coursesData)],
            'summary' => 'Voy a crear 2 cursos.',
            'requires_confirmation' => true,
            'all_or_nothing' => false,
        ]);

        $response = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'crea química para 5to y física para 5to con el profesor Zzz Inexistente',
        ]);

        // El profesor inexistente debe rechazarse antes de escribir nada del lote.
        $response->assertStatus(422);
        $this->assertSame(0, Course::where('colegio_id', $colegio->id)->count());
    }

    public function test_batch_is_scoped_to_directors_own_colegio(): void
    {
        [$directorA, $colegioA] = $this->directorContext();
        [$directorB, $colegioB] = $this->directorContext();

        $coursesData = [
            ['subject_name' => 'Arte', 'grades' => ['2do'], 'section' => null, 'teacher_name' => null],
        ];

        $this->fakePlannerPlan([
            'status' => 'pending',
            'actions' => [$this->createCoursesBatchAction($coursesData)],
            'summary' => 'Voy a crear 1 curso.',
            'requires_confirmation' => true,
            'all_or_nothing' => false,
        ]);

        $this->actingAs($directorA)->postJson(route('director.ai.command'), [
            'prompt' => 'crea el curso de arte para 2do grado',
        ]);
        $this->actingAs($directorA)->postJson(route('director.ai.command'), ['prompt' => 'sí'])
            ->assertJsonPath('success', true);

        $this->assertSame(1, Course::where('colegio_id', $colegioA->id)->where('subject_name', 'Arte')->count());
        $this->assertSame(0, Course::where('colegio_id', $colegioB->id)->where('subject_name', 'Arte')->count());
    }
}

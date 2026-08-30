<?php

namespace Tests\Feature;

use App\Models\Colegio;
use App\Models\Course;
use App\Models\Evaluation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TeacherEvaluationDateParsingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-08-30 10:00:00'));
        config(['services.openai.key' => null]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_spanish_weekday_and_month_date_is_taken_from_the_first_message(): void
    {
        [$teacher] = $this->teacherWithBiology();

        $first = $this->actingAs($teacher)->postJson(route('ai.command'), [
            'prompt' => 'Créame una evaluación de la fotosíntesis para el miércoles 16 de septiembre.',
        ]);

        $first->assertOk()
            ->assertJsonPath('requires_followup', true)
            ->assertJsonPath('data.draft.due_date', '2026-09-16');

        $missing = $first->json('data.missing');
        $this->assertIsArray($missing);
        $this->assertNotContains('date', $missing);
        $this->assertStringNotContainsString('fecha tendrá la evaluación', (string) $first->json('message'));
    }

    public function test_followup_accepts_16_de_septiembre_del_2026(): void
    {
        [$teacher] = $this->teacherWithBiology();

        $this->actingAs($teacher)->postJson(route('ai.command'), [
            'prompt' => 'Créame una evaluación de la fotosíntesis.',
        ])->assertOk()->assertJsonPath('requires_followup', true);

        $followup = $this->actingAs($teacher)->postJson(route('ai.command'), [
            'prompt' => 'Para el miércoles 16 de septiembre del 2026.',
        ]);

        $followup->assertOk()
            ->assertJsonPath('data.draft.due_date', '2026-09-16');

        $missing = $followup->json('data.missing') ?? [];
        $this->assertNotContains('date', $missing);
        $this->assertStringNotContainsString('fecha tendrá la evaluación', (string) $followup->json('message'));
    }

    public function test_real_chat_creates_evaluation_on_wednesday_16_september_2026(): void
    {
        [$teacher, $course] = $this->teacherWithBiology();

        $first = $this->actingAs($teacher)->postJson(route('ai.command'), [
            'prompt' => 'Créame una evaluación de la fotosíntesis para el miércoles 16 de septiembre.',
        ]);
        $first->assertOk()->assertJsonPath('data.draft.due_date', '2026-09-16');

        $second = $this->actingAs($teacher)->postJson(route('ai.command'), [
            'prompt' => 'Para el curso de biología de 3ro, para el miércoles 16 de septiembre, ponle una ponderación de 20%.',
        ]);

        $second->assertOk();
        $this->assertNotTrue((bool) $second->json('requires_followup'));
        $this->assertStringNotContainsString('fecha tendrá la evaluación', (string) $second->json('message'));

        $evaluation = Evaluation::query()->where('course_id', $course->id)->first();
        $this->assertNotNull($evaluation);
        $this->assertSame('2026-09-16', optional($evaluation->scheduled_at)?->toDateString());
    }

    /**
     * @return array{0:User,1:Course}
     */
    private function teacherWithBiology(): array
    {
        $colegio = Colegio::create([
            'name' => 'Colegio Fotosíntesis',
            'invite_code' => 'FOT-1001',
            'codes_pin' => Colegio::hashPinFromInvite('FOT-1001'),
        ]);
        $teacher = User::factory()->create([
            'role' => 'profesor',
            'colegio_id' => $colegio->id,
            'onboarding_completed' => true,
        ]);
        $course = Course::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => $teacher->id,
            'subject_name' => 'Biología',
            'grade' => '3ro',
            'section' => 'A',
            'school_year' => '2026-2027',
            'invite_code' => 'BIO-3',
        ]);

        return [$teacher, $course];
    }
}

<?php

namespace Tests\Feature;

use App\Models\Colegio;
use App\Models\Course;
use App\Models\Student;
use App\Models\User;
use App\Services\DirectorDataAgentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectorDataAgentIntentRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_name_and_verification_intents_are_data_queries(): void
    {
        $agent = app(DirectorDataAgentService::class);

        $names = $agent->detectIntent('como se llaman los profesores');
        $this->assertSame('professors', $names['intent'], json_encode($names));
        $this->assertSame('data_agent', $names['agent']);

        $check = $agent->detectIntent('revisa si Vicente Maduro es profesor');
        $this->assertSame('verification', $check['intent'], json_encode($check));
        $this->assertSame('data_agent', $check['agent']);
        $this->assertSame('Vicente Maduro', $agent->extractPersonToVerify('revisa si Vicente Maduro es profesor'));

        $mutation = $agent->detectIntent('Crea al profesor Vicente Maduro');
        $this->assertSame('mutation', $mutation['intent']);
        $this->assertSame('crud', $mutation['agent']);
    }

    public function test_como_se_llaman_los_profesores_lists_teachers_not_crud_menu(): void
    {
        [$director, , $teacher] = $this->seedSchool();

        $response = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'como se llaman los profesores',
        ]);

        $response->assertOk();
        $message = (string) $response->json('message');
        $this->assertStringNotContainsString('Puedo crear y eliminar profesores', $message);
        $this->assertSame('director_data', $response->json('agent'));
        $this->assertSame('professors', $response->json('routing.intent'));
        $this->assertContains('get_teachers', $response->json('tools'));
        $this->assertStringContainsString($teacher->name, json_encode($response->json(), JSON_UNESCAPED_UNICODE));
    }

    public function test_revisa_si_name_es_profesor_confirms_registered_teacher(): void
    {
        [$director, , $teacher] = $this->seedSchool();

        $response = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'revisa si '.$teacher->name.' es profesor',
        ]);

        $response->assertOk();
        $message = (string) $response->json('message');
        $this->assertStringNotContainsString('Puedo crear y eliminar profesores', $message);
        $this->assertContains('verify_teacher', $response->json('tools'));
        $this->assertTrue((bool) $response->json('actions.0.data.exists'));
        $this->assertStringContainsString('profesor', mb_strtolower($message));
        $this->assertStringContainsString($teacher->name, json_encode($response->json(), JSON_UNESCAPED_UNICODE));
    }

    public function test_revisa_si_student_es_profesor_says_no(): void
    {
        [$director] = $this->seedSchool();

        $response = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'revisa si Javier Mendez es profesor',
        ]);

        $response->assertOk();
        $this->assertStringNotContainsString('Puedo crear y eliminar profesores', (string) $response->json('message'));
        $this->assertContains('verify_teacher', $response->json('tools'));
        $this->assertFalse((bool) $response->json('actions.0.data.exists'));
        $this->assertStringContainsString('alumno', mb_strtolower((string) $response->json('message')));
    }

    public function test_teacher_of_student_is_not_stolen_by_professor_roster(): void
    {
        [$director, , $teacher] = $this->seedSchool();

        $response = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => '¿Con qué profesor está Javier Mendez?',
        ]);

        $response->assertOk();
        $this->assertContains($response->json('tools')[0] ?? '', ['get_student', 'get_student_performance']);
        $this->assertStringContainsString($teacher->name, (string) $response->json('message'));
    }

    /**
     * @return array{0:User,1:Colegio,2:User}
     */
    private function seedSchool(): array
    {
        $director = User::factory()->create([
            'role' => 'director',
            'onboarding_completed' => true,
        ]);
        $colegio = Colegio::create([
            'name' => 'Colegio QA',
            'invite_code' => 'COC-INT1',
            'codes_pin' => Colegio::hashPinFromInvite('COC-INT1'),
            'director_user_id' => $director->id,
        ]);
        $director->update(['colegio_id' => $colegio->id]);

        $teacher = User::factory()->create([
            'role' => 'profesor',
            'colegio_id' => $colegio->id,
            'onboarding_completed' => true,
            'name' => 'Vicente Maduro',
        ]);
        $course = Course::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => $teacher->id,
            'subject_name' => 'Matemática',
            'grade' => '1ro',
            'section' => 'A',
            'school_year' => '2026-2027',
            'invite_code' => 'CUR-INT'.uniqid(),
        ]);
        $student = Student::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => $teacher->id,
            'name' => 'Javier Mendez',
            'grade' => '1ro',
            'section' => 'A',
            'family_code' => 'FAM-JAV'.uniqid(),
        ]);
        $course->students()->syncWithoutDetaching([$student->id]);

        return [$director->fresh(), $colegio, $teacher];
    }
}

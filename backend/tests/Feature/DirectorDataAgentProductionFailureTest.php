<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Colegio;
use App\Models\Course;
use App\Models\Grade;
use App\Models\Student;
use App\Models\User;
use App\Services\DirectorDataAgentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectorDataAgentProductionFailureTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_phrase_notas_de_2do_is_a_data_query_not_crud_menu(): void
    {
        [$director] = $this->seedSecondA();
        $prompt = 'quiero saber las notas de 2do grado A';

        $decision = app(DirectorDataAgentService::class)->routeDecision($prompt);
        fwrite(STDERR, "\nROUTING ".$prompt.' => '.json_encode($decision, JSON_UNESCAPED_UNICODE)."\n");

        $this->assertFalse($decision['mutation'], json_encode($decision));
        $this->assertTrue($decision['use_data_agent'], json_encode($decision));
        $this->assertSame('director_data', $decision['agent']);
        $this->assertSame('2do', $decision['extracted_grade']);
        $this->assertSame('A', $decision['extracted_section']);

        $response = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => $prompt,
        ]);

        $response->assertOk();
        $message = (string) $response->json('message');
        $this->assertStringNotContainsString('Puedo crear y eliminar profesores', $message);
        $this->assertSame('director_data', $response->json('agent'));
        $this->assertContains('get_course_performance', $response->json('tools'));
        $payload = json_encode($response->json(), JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('Jose Aular', $payload);
        $this->assertStringContainsString('Rodrigo Meza', $payload);
        $this->assertStringContainsString('Pepe Sol', $payload);
    }

    public function test_real_eight_turn_conversation_from_production_failure(): void
    {
        [$director] = $this->seedSecondA();
        $crud = 'Puedo crear y eliminar profesores';

        $t1 = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => '¿Cuántos alumnos hay en 2do A?',
        ]);
        $t1->assertOk();
        $this->assertStringNotContainsString($crud, (string) $t1->json('message'));
        $this->assertMatchesRegularExpression('/3/u', (string) $t1->json('message').json_encode($t1->json()));
        $this->assertStringContainsString('Pepe Sol', json_encode($t1->json(), JSON_UNESCAPED_UNICODE));

        $t2 = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => '¿Quiénes son?',
        ]);
        $t2->assertOk();
        $p2 = json_encode($t2->json(), JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('Jose Aular', $p2);
        $this->assertStringContainsString('Rodrigo Meza', $p2);
        $this->assertStringContainsString('Pepe Sol', $p2);
        $this->assertStringNotContainsString($crud, $p2);

        $t3 = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'quiero saber las notas de 2do grado A',
        ]);
        $t3->assertOk();
        $p3 = json_encode($t3->json(), JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString($crud, (string) $t3->json('message'));
        $this->assertContains('get_course_performance', $t3->json('tools'));
        $this->assertStringContainsString('Jose Aular', $p3);
        $this->assertStringContainsString('Rodrigo Meza', $p3);
        $this->assertStringContainsString('Pepe Sol', $p3);
        $this->assertMatchesRegularExpression('/3/u', $p3);

        $t4 = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'como van esos alumnos?',
        ]);
        $t4->assertOk();
        $p4 = json_encode($t4->json(), JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString($crud, (string) $t4->json('message'));
        $this->assertContains('get_course_performance', $t4->json('tools'));
        $this->assertStringContainsString('Jose Aular', $p4);
        $this->assertStringContainsString('Rodrigo Meza', $p4);

        $t5 = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'como van sus notas?',
        ]);
        $t5->assertOk();
        $p5 = json_encode($t5->json(), JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString($crud, (string) $t5->json('message'));
        $this->assertContains('get_course_performance', $t5->json('tools'));
        $this->assertStringContainsString('Jose Aular', $p5);
        $this->assertStringContainsString('Rodrigo Meza', $p5);

        $t6 = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => '¿Y Pepe?',
        ]);
        $t6->assertOk();
        $this->assertStringNotContainsString($crud, (string) $t6->json('message'));
        $this->assertStringContainsString('Pepe', json_encode($t6->json(), JSON_UNESCAPED_UNICODE));

        $t7 = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => '¿Cuántos alumnos hay en 2do A?',
        ]);
        $t7->assertOk();
        $p7 = json_encode($t7->json(), JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('Pepe Sol', $p7);
        $this->assertStringContainsString('Jose Aular', $p7);
        $this->assertStringContainsString('Rodrigo Meza', $p7);
        $this->assertMatchesRegularExpression('/3/u', $p7);

        $t8 = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'Prepárame un informe de 2do A para la reunión.',
        ]);
        $t8->assertOk();
        $this->assertStringNotContainsString($crud, (string) $t8->json('message'));
        $this->assertTrue((bool) $t8->json('report_ready') || str_contains((string) $t8->json('message'), 'Informe'));
    }

    /**
     * @return array{0:User,1:Colegio,2:User}
     */
    private function seedSecondA(): array
    {
        $director = User::factory()->create([
            'role' => 'director',
            'onboarding_completed' => true,
        ]);
        $colegio = Colegio::create([
            'name' => 'Colegio QA',
            'invite_code' => 'COC-P401',
            'codes_pin' => Colegio::hashPinFromInvite('COC-P401'),
            'director_user_id' => $director->id,
        ]);
        $director->update(['colegio_id' => $colegio->id]);

        $teacher = User::factory()->create([
            'role' => 'profesor',
            'colegio_id' => $colegio->id,
            'onboarding_completed' => true,
            'name' => 'Prof. 2doA',
        ]);
        $course = Course::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => $teacher->id,
            'subject_name' => 'Matemática',
            'grade' => '2do',
            'section' => 'A',
            'school_year' => '2026-2027',
            'invite_code' => 'CUR-2DOA'.uniqid(),
        ]);

        $jose = $this->makeStudent($colegio, $teacher, 'Jose Aular', '2do', 'A');
        $rodrigo = $this->makeStudent($colegio, $teacher, 'Rodrigo Meza', '2do', 'A');
        $pepe = $this->makeStudent($colegio, $teacher, 'Pepe Sol', '2do', 'A');
        $course->students()->syncWithoutDetaching([$jose->id, $rodrigo->id, $pepe->id]);

        foreach ([['Jose Aular', $jose, 16], ['Rodrigo Meza', $rodrigo, 12]] as [$name, $student, $score]) {
            $activity = Activity::create([
                'teacher_id' => $teacher->id,
                'course_id' => $course->id,
                'title' => 'Evaluación '.$name,
                'max_score' => 20,
            ]);
            Grade::create([
                'activity_id' => $activity->id,
                'student_id' => $student->id,
                'colegio_id' => $colegio->id,
                'score' => $score,
                'status' => 'published',
            ]);
        }

        return [$director->fresh(), $colegio, $teacher];
    }

    private function makeStudent(Colegio $colegio, User $teacher, string $name, string $grade, string $section): Student
    {
        return Student::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => $teacher->id,
            'name' => $name,
            'grade' => $grade,
            'section' => $section,
            'family_code' => 'FAM-'.strtoupper(preg_replace('/[^A-Za-z]/', '', $name)).uniqid(),
        ]);
    }
}

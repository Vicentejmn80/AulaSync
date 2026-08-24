<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Attendance;
use App\Models\Colegio;
use App\Models\Course;
use App\Models\Grade;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectorDataAgentConversationTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_name_is_a_short_natural_answer(): void
    {
        [$director] = $this->seedSchool();

        $response = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => '¿Cómo se llama mi colegio?',
        ]);

        $response->assertOk();
        $message = (string) $response->json('message');
        $this->assertStringContainsString('Colegio QA', $message);
        $this->assertStringNotContainsString('**Hechos**', $message);
        $this->assertStringNotContainsString('**Análisis**', $message);
        $this->assertStringNotContainsString('Puedo crear y eliminar', $message);
    }

    public function test_student_count_is_a_short_natural_answer(): void
    {
        [$director] = $this->seedSchool();

        $response = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => '¿Cuántos alumnos hay?',
        ]);

        $response->assertOk();
        $message = (string) $response->json('message');
        $this->assertMatchesRegularExpression('/Hay\s+3\s+alumnos/u', $message);
        $this->assertStringNotContainsString('**Hechos**', $message);
        $this->assertStringNotContainsString('Puedo crear y eliminar', $message);
    }

    public function test_lists_all_student_names(): void
    {
        [$director] = $this->seedSchool();

        $response = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => '¿Cómo se llaman todos los alumnos?',
        ]);

        $response->assertOk();
        $payload = json_encode($response->json(), JSON_UNESCAPED_UNICODE);
        $this->assertContains('get_students', $response->json('tools'));
        $this->assertStringContainsString('Javier Mendez', $payload);
        $this->assertStringContainsString('Ana Ruiz', $payload);
        $this->assertStringContainsString('Carlos Pérez', $payload);
        $this->assertStringNotContainsString('Puedo crear y eliminar', $payload);
    }

    public function test_asks_which_teacher_has_javier(): void
    {
        [$director, , $teacher] = $this->seedSchool();

        $response = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => '¿Con qué profesor está Javier Mendez?',
        ]);

        $response->assertOk();
        $message = (string) $response->json('message');
        $this->assertStringContainsString($teacher->name, $message);
        $this->assertStringNotContainsString('Puedo crear y eliminar profesores', $message);
        $this->assertContains($response->json('tools')[0] ?? '', ['get_student', 'get_student_performance']);
    }

    public function test_how_are_we_is_a_school_panorama(): void
    {
        [$director] = $this->seedSchool();

        $response = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => '¿Cómo estamos?',
        ]);

        $response->assertOk();
        $message = (string) $response->json('message');
        $this->assertFalse((bool) $response->json('needs_clarification'));
        $this->assertContains('generate_school_report', $response->json('tools'));
        $this->assertStringContainsString('alumnos', mb_strtolower($message));
        $this->assertStringNotContainsString('Dime el curso', $message);
        $this->assertStringNotContainsString('**Hechos**', $message);
        $this->assertStringNotContainsString('Puedo crear y eliminar', $message);
    }

    public function test_who_needs_attention_stays_on_data_agent(): void
    {
        [$director] = $this->seedSchool();

        $response = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => '¿Quiénes necesitan atención?',
        ]);

        $response->assertOk();
        $this->assertContains('get_at_risk_students', $response->json('tools'));
        $this->assertStringNotContainsString('Puedo crear y eliminar', (string) $response->json('message'));
    }

    public function test_students_of_first_grade_then_second_grade_follow_up(): void
    {
        [$director] = $this->seedSchool();

        $first = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => '¿Quiénes son los alumnos de 1ro?',
        ]);
        $first->assertOk();
        $this->assertContains('get_students', $first->json('tools'));
        $this->assertStringContainsString('Javier Mendez', json_encode($first->json(), JSON_UNESCAPED_UNICODE));
        $this->assertStringNotContainsString('Puedo crear y eliminar', (string) $first->json('message'));

        $second = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => '¿Y los de 2do?',
        ]);
        $second->assertOk();
        $this->assertContains('get_students', $second->json('tools'));
        $payload = json_encode($second->json(), JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('Carlos Pérez', $payload);
        $this->assertStringNotContainsString('Puedo crear y eliminar', $payload);
    }

    public function test_worst_average_why_subject_and_teacher_follow_up(): void
    {
        [$director, , $teacher] = $this->seedSchool();

        $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => '¿Quiénes necesitan atención?',
        ])->assertOk();

        $worst = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => '¿Quién tiene peor promedio?',
        ]);
        $worst->assertOk();
        $this->assertStringContainsString('Javier Mendez', (string) $worst->json('message'));

        $why = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => '¿Por qué?',
        ]);
        $why->assertOk();
        $this->assertStringContainsString('bajo rendimiento', mb_strtolower((string) $why->json('message')));

        $subject = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => '¿En qué materia está peor?',
        ]);
        $subject->assertOk();
        $this->assertStringContainsString('Matemática', (string) $subject->json('message'));

        $whoTeaches = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => '¿Quién le da esa materia?',
        ]);
        $whoTeaches->assertOk();
        $this->assertStringContainsString($teacher->name, (string) $whoTeaches->json('message'));
        $this->assertStringNotContainsString('Puedo crear y eliminar', (string) $whoTeaches->json('message'));
    }

    public function test_alphabetical_order_instruction_is_honored(): void
    {
        [$director] = $this->seedSchool();

        $response = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => '¿Cuántos alumnos hay en el colegio? Empieza con el nombre más cercano al inicio del abecedario.',
        ]);

        $response->assertOk();
        $this->assertContains('get_students', $response->json('tools'));
        $message = (string) $response->json('message');
        $anaPos = mb_stripos($message, 'Ana Ruiz');
        $carlosPos = mb_stripos($message, 'Carlos Pérez');
        $javierPos = mb_stripos($message, 'Javier Mendez');
        $this->assertNotFalse($anaPos);
        $this->assertNotFalse($carlosPos);
        $this->assertNotFalse($javierPos);
        $this->assertLessThan($carlosPos, $anaPos);
        $this->assertLessThan($javierPos, $carlosPos);
    }

    public function test_ellos_follow_up_keeps_the_current_set(): void
    {
        [$director] = $this->seedSchool();

        $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => '¿Quiénes son los alumnos de 1ro?',
        ])->assertOk();

        $response = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => '¿Cuál de ellos tiene peor asistencia?',
        ]);

        $response->assertOk();
        $tools = $response->json('tools');
        $this->assertTrue(
            in_array('get_attendance', $tools, true) || in_array('get_rankings', $tools, true),
            json_encode($tools)
        );
        $this->assertStringNotContainsString('Puedo crear y eliminar', (string) $response->json('message'));
    }

    public function test_ese_alumno_follow_up_uses_last_student(): void
    {
        [$director] = $this->seedSchool();

        $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => '¿Cómo está Javier Mendez?',
        ])->assertOk();

        $response = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => '¿Y ese alumno qué asistencia tiene?',
        ]);

        $response->assertOk();
        $payload = json_encode($response->json(), JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('Javier', $payload);
        $this->assertStringNotContainsString('Puedo crear y eliminar', $payload);
    }

    public function test_ese_curso_follow_up_uses_last_grade(): void
    {
        [$director] = $this->seedSchool();

        $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => '¿Cómo va 1ro A?',
        ])->assertOk();

        $response = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => '¿Y ese curso quiénes son?',
        ]);

        $response->assertOk();
        $payload = json_encode($response->json(), JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('Javier Mendez', $payload);
        $this->assertStringNotContainsString('Puedo crear y eliminar', $payload);
    }

    public function test_ambiguous_course_still_asks_for_clarification(): void
    {
        [$director] = $this->seedSchool();

        $response = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => '¿Cómo está mi curso?',
        ]);

        $this->assertTrue((bool) $response->json('needs_clarification'));
        $this->assertStringContainsString('curso', mb_strtolower((string) $response->json('message')));
    }

    public function test_out_of_scope_does_not_invent_school_data(): void
    {
        [$director] = $this->seedSchool();

        $response = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => '¿Cuál es el clima hoy?',
        ]);

        $payload = json_encode($response->json(), JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('Javier Mendez', $payload);
        $this->assertStringNotContainsString('90%', $payload);
    }

    public function test_injected_colegio_id_is_ignored(): void
    {
        [$director] = $this->seedSchool();
        [, $other] = $this->directorContext('Colegio Extraño', 'COC-X001');
        $this->seedClass($other, '1ro', 'A', [['Eva Inventada', 20]]);

        $response = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => '¿Cómo se llaman todos los alumnos?',
            'colegio_id' => $other->id,
            'screen_context' => ['colegio_id' => $other->id],
        ]);

        $payload = json_encode($response->json(), JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('Javier Mendez', $payload);
        $this->assertStringNotContainsString('Eva Inventada', $payload);
    }

    public function test_teacher_cannot_use_director_ai(): void
    {
        [$director, $colegio] = $this->seedSchool();
        $teacher = User::factory()->create([
            'role' => 'profesor',
            'colegio_id' => $colegio->id,
            'onboarding_completed' => true,
        ]);

        $this->actingAs($teacher)->postJson(route('director.ai.command'), [
            'prompt' => '¿Cómo estamos?',
        ])->assertForbidden();

        $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => '¿Cómo estamos?',
        ])->assertOk();
    }

    public function test_mutation_with_valid_csrf_reaches_confirmation(): void
    {
        [$director] = $this->seedSchool();

        $response = $this->actingAs($director)
            ->withSession(['_token' => 'valid-director-csrf'])
            ->withHeaders(['X-CSRF-TOKEN' => 'valid-director-csrf'])
            ->postJson(route('director.ai.command'), [
                'prompt' => 'Crea al alumno Andrés Pérez en 1ro',
            ]);

        $this->assertNotEquals(419, $response->status());
        $response->assertOk();
        $this->assertTrue(
            (bool) $response->json('requires_confirmation')
            || str_contains(mb_strtolower((string) $response->json('message')), 'andrés')
            || str_contains(mb_strtolower((string) $response->json('message')), 'andres')
        );
    }

    public function test_mutation_without_valid_csrf_is_rejected_when_not_in_unit_tests(): void
    {
        [$director] = $this->seedSchool();
        $previous = $this->app['env'];
        $this->app['env'] = 'local';

        try {
            $response = $this->actingAs($director)
                ->withSession(['_token' => 'valid-director-csrf'])
                ->withHeaders(['X-CSRF-TOKEN' => 'token-invalido'])
                ->postJson(route('director.ai.command'), [
                    'prompt' => 'Crea al alumno Andrés Pérez en 1ro',
                ]);
            $response->assertStatus(419);
        } finally {
            $this->app['env'] = $previous;
        }
    }

    public function test_bubble_sends_csrf_credentials_and_handles_419(): void
    {
        $blade = file_get_contents(resource_path('views/components/ai-assistant-bubble.blade.php'));
        $this->assertStringContainsString("credentials: 'same-origin'", $blade);
        $this->assertStringContainsString('X-CSRF-TOKEN', $blade);
        $this->assertStringContainsString('X-Requested-With', $blade);
        $this->assertStringContainsString('res.status === 419', $blade);
        $this->assertStringContainsString('Tu sesión expiró', $blade);
        $this->assertStringContainsString('getCsrfToken()', $blade);
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
            'invite_code' => 'COC-QA04',
            'codes_pin' => Colegio::hashPinFromInvite('COC-QA04'),
            'director_user_id' => $director->id,
        ]);
        $director->update(['colegio_id' => $colegio->id]);

        [$teacher] = $this->seedClass($colegio, '1ro', 'A', [['Javier Mendez', 8]]);
        $this->seedClass($colegio, '2do', 'A', [['Carlos Pérez', 14]]);
        $this->seedClass($colegio, '4to', 'A', [['Ana Ruiz', 18]]);

        $javier = Student::query()->where('colegio_id', $colegio->id)->where('name', 'Javier Mendez')->firstOrFail();
        $course = Course::query()->where('colegio_id', $colegio->id)->where('grade', '1ro')->firstOrFail();
        Attendance::create([
            'colegio_id' => $colegio->id,
            'course_id' => $course->id,
            'student_id' => $javier->id,
            'teacher_id' => $teacher->id,
            'attended_on' => now()->subDay()->toDateString(),
            'status' => Attendance::STATUS_ABSENT,
        ]);

        return [$director->fresh(), $colegio, $teacher];
    }

    /**
     * @param  array<int,array{0:string,1:int}>  $students
     * @return array{0:User,1:Course,2:Student}
     */
    private function seedClass(Colegio $colegio, string $grade, string $section, array $students): array
    {
        $teacher = User::factory()->create([
            'role' => 'profesor',
            'colegio_id' => $colegio->id,
            'onboarding_completed' => true,
            'name' => 'Prof. '.$grade.$section,
        ]);
        $course = Course::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => $teacher->id,
            'subject_name' => 'Matemática',
            'grade' => $grade,
            'section' => $section,
            'school_year' => '2026-2027',
            'invite_code' => 'CUR-'.strtoupper($grade.$section.uniqid()),
        ]);
        $first = null;
        foreach ($students as [$name, $score]) {
            $student = Student::create([
                'colegio_id' => $colegio->id,
                'teacher_id' => $teacher->id,
                'name' => $name,
                'grade' => $grade,
                'section' => $section,
                'family_code' => 'FAM-'.strtoupper(preg_replace('/[^A-Za-z]/', '', $name)).uniqid(),
            ]);
            $first ??= $student;
            $activity = Activity::create([
                'teacher_id' => $teacher->id,
                'course_id' => $course->id,
                'title' => 'Evaluación',
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

        return [$teacher, $course, $first];
    }

    /**
     * @return array{0:User,1:Colegio}
     */
    private function directorContext(string $name = 'Colegio Extra', string $code = 'COC-EX01'): array
    {
        $director = User::factory()->create([
            'role' => 'director',
            'onboarding_completed' => true,
        ]);
        $colegio = Colegio::create([
            'name' => $name,
            'invite_code' => $code,
            'codes_pin' => Colegio::hashPinFromInvite($code),
            'director_user_id' => $director->id,
        ]);
        $director->update(['colegio_id' => $colegio->id]);

        return [$director->fresh(), $colegio];
    }
}

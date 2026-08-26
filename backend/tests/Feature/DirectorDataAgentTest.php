<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Attendance;
use App\Models\Colegio;
use App\Models\Course;
use App\Models\Grade;
use App\Models\ProductEvent;
use App\Models\Student;
use App\Models\User;
use App\Services\DirectorDataAgentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectorDataAgentTest extends TestCase
{
    use RefreshDatabase;

    public function test_director_can_query_own_school_course_status(): void
    {
        [$director, $colegio] = $this->directorContext();
        $this->seedClass($colegio, '4to', 'A', [
            ['Ana Ruiz', 18],
            ['Luis Mora', 14],
        ]);

        $response = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => '¿Cómo va 4to A?',
        ]);

        $response->assertOk()->assertJsonPath('actions.0.success', true);
        $message = (string) $response->json('actions.0.message');
        $this->assertStringContainsString('Ana Ruiz', $message);
        $this->assertStringContainsString('Luis Mora', $message);
        $this->assertStringNotContainsString('**Hechos**', (string) $response->json('message'));
        $this->assertStringNotContainsString('**Análisis**', (string) $response->json('message'));
        $this->assertStringNotContainsString('| Alumno |', (string) $response->json('message'));
        $this->assertContains('get_course_performance', $response->json('tools'));
        $this->assertNotNull($response->json('duration_ms'));
    }

    public function test_director_cannot_query_another_school_even_if_colegio_id_is_injected(): void
    {
        [$director, $colegio] = $this->directorContext('Colegio A', 'COC-A001');
        [, $other] = $this->directorContext('Colegio B', 'COC-B001');
        $this->seedClass($colegio, '4to', 'A', [['Ana Local', 18]]);
        $this->seedClass($other, '4to', 'A', [['Eva Externa', 20]]);

        $response = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => '¿Quién es el estudiante con mejor rendimiento?',
            'colegio_id' => $other->id,
            'screen_context' => ['colegio_id' => $other->id],
        ]);

        $response->assertOk();
        $payload = json_encode($response->json(), JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('Ana Local', $payload);
        $this->assertStringNotContainsString('Eva Externa', $payload);
    }

    public function test_teacher_cannot_use_director_data_agent(): void
    {
        [$director, $colegio] = $this->directorContext();
        $this->seedClass($colegio, '4to', 'A', [['Ana Ruiz', 18]]);
        $teacher = User::factory()->create([
            'role' => 'profesor',
            'colegio_id' => $colegio->id,
            'onboarding_completed' => true,
        ]);

        $this->actingAs($teacher)->postJson(route('director.ai.command'), [
            'prompt' => 'Dame un informe de rendimiento de 4to A.',
        ])->assertForbidden();

        $teacherChat = $this->actingAs($teacher)->postJson(route('ai.command'), [
            'prompt' => '¿Quiénes necesitan atención en todo el colegio?',
        ]);
        $teacherChat->assertOk();
        $this->assertStringNotContainsString('Ana Ruiz', json_encode($teacherChat->json()));
    }

    public function test_query_without_enough_data_is_honest(): void
    {
        [$director] = $this->directorContext();

        $response = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => '¿Cómo va 4to A?',
        ]);

        $response->assertOk()->assertJsonPath('actions.0.success', true);
        $this->assertSame([], $response->json('actions.0.data.students'));
        $this->assertStringContainsString('No hay alumnos registrados', (string) $response->json('actions.0.message'));
        $this->assertDoesNotMatchRegularExpression('/\*\*(Hechos|Análisis)\*\*/u', (string) $response->json('message'));
    }

    public function test_concern_query_combines_students_grades_and_attendance(): void
    {
        [$director, $colegio] = $this->directorContext();
        [$teacher, $course, $ana] = $this->seedClass($colegio, '4to', 'A', [['Ana Ruiz', 8]]);
        Attendance::create([
            'colegio_id' => $colegio->id,
            'course_id' => $course->id,
            'student_id' => $ana->id,
            'teacher_id' => $teacher->id,
            'attended_on' => now()->subDay()->toDateString(),
            'status' => Attendance::STATUS_ABSENT,
        ]);

        $response = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => '¿Quiénes necesitan atención?',
        ]);

        $response->assertOk();
        $tools = $response->json('tools');
        $this->assertContains('get_at_risk_students', $tools);
        $this->assertContains('get_attendance', $tools);
        $this->assertGreaterThanOrEqual(2, count($tools));
        $payload = json_encode($response->json(), JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('Ana Ruiz', $payload);
        $this->assertStringNotContainsString('Puedo crear y eliminar profesores', (string) $response->json('message'));
    }

    public function test_compare_two_courses_uses_compare_courses_tool(): void
    {
        [$director, $colegio] = $this->directorContext();
        $this->seedClass($colegio, '2do', 'A', [['Pepe Sol', 18]]);
        $this->seedClass($colegio, '4to', 'A', [['Ana Ruiz', 14]]);

        $response = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'Compara 2do A y 4to A.',
        ]);

        $response->assertOk();
        $this->assertContains('compare_courses', $response->json('tools'));
        $message = (string) $response->json('actions.0.message');
        $this->assertStringContainsString('Comparación 2do A vs 4to A', $message);
        $this->assertStringContainsString('90%', $message);
        $this->assertStringContainsString('70%', $message);
        $this->assertStringContainsString('Lidera 2do A', $message);
    }

    public function test_school_health_prompt_uses_school_health_tools(): void
    {
        [$director, $colegio] = $this->directorContext();
        $this->seedClass($colegio, '2do', 'A', [['Pepe Sol', 18]]);
        $this->seedClass($colegio, '4to', 'A', [['Ana Ruiz', 10]]);

        $response = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'Dame el panorama general de salud del colegio.',
        ]);

        $response->assertOk();
        $tools = $response->json('tools');
        $this->assertContains('get_school_health', $tools);
        $this->assertContains('get_smart_recommendations', $tools);
        $this->assertStringContainsString('Salud general', (string) $response->json('actions.0.message'));
    }

    public function test_priorities_prompt_returns_recommendations_tools(): void
    {
        [$director, $colegio] = $this->directorContext();
        [$teacher, $course, $ana] = $this->seedClass($colegio, '3ro', 'A', [['Pedro Gil', 9]]);
        Attendance::create([
            'colegio_id' => $colegio->id,
            'course_id' => $course->id,
            'student_id' => $ana->id,
            'teacher_id' => $teacher->id,
            'attended_on' => now()->subDay()->toDateString(),
            'status' => Attendance::STATUS_ABSENT,
        ]);

        $response = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => '¿Qué debo priorizar esta semana?',
        ]);

        $response->assertOk();
        $tools = $response->json('tools');
        $this->assertContains('get_risk_analysis', $tools);
        $this->assertContains('get_smart_recommendations', $tools);
        $this->assertStringContainsString('Recomendaciones', (string) $response->json('message'));
    }

    public function test_cause_prompt_uses_trend_and_cause_tools(): void
    {
        [$director, $colegio] = $this->directorContext();
        [$teacher, $course, $student] = $this->seedClass($colegio, '2do', 'A', [['Rodrigo Meza', 16]]);

        $oldActivity = Activity::create([
            'teacher_id' => $teacher->id,
            'course_id' => $course->id,
            'title' => 'Diagnóstico inicial',
            'max_score' => 20,
        ]);
        $oldGrade = Grade::create([
            'activity_id' => $oldActivity->id,
            'student_id' => $student->id,
            'colegio_id' => $colegio->id,
            'score' => 18,
            'status' => 'published',
        ]);
        $oldGrade->forceFill(['created_at' => now()->subDays(45)])->save();

        $response = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => '¿Por qué bajó 2do A?',
        ]);

        $response->assertOk();
        $tools = $response->json('tools');
        $this->assertContains('get_trend_analysis', $tools);
        $this->assertContains('get_cause_analysis', $tools);
        $payload = json_encode($response->json(), JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('Posibles causas', $payload);
    }

    public function test_example_queries_use_named_tools_and_do_not_invent_data(): void
    {
        [$director, $colegio] = $this->directorContext();
        $this->seedClass($colegio, '4to', 'A', [['Ana Ruiz', 18], ['Luis Mora', 12]]);
        $this->seedClass($colegio, '2do', 'A', [['Pepe Sol', 16]]);

        $cases = [
            '¿Quién es el estudiante con mejor rendimiento?' => 'get_rankings',
            '¿Cómo está la asistencia de 2do A?' => 'get_attendance',
            'Dame un informe de rendimiento de 4to A.' => 'generate_school_report',
            '¿Qué problemas ves en mi colegio?' => 'get_at_risk_students',
            '¿Qué debería preocuparme como director?' => 'get_at_risk_students',
            'Resume el estado académico del colegio.' => 'generate_school_report',
            '¿Qué tendencias encuentras este mes?' => 'get_academic_trends',
        ];

        foreach ($cases as $prompt => $tool) {
            $response = $this->actingAs($director)->postJson(route('director.ai.command'), [
                'prompt' => $prompt,
            ]);
            $response->assertOk(json_encode($response->json(), JSON_UNESCAPED_UNICODE));
            $this->assertIsArray($response->json('tools'), $prompt.' '.json_encode($response->json(), JSON_UNESCAPED_UNICODE));
            $this->assertContains($tool, $response->json('tools'), $prompt);
            $this->assertStringNotContainsString('Eva Inventada', json_encode($response->json()));
        }
    }

    public function test_declining_average_query(): void
    {
        [$director, $colegio] = $this->directorContext();
        [$teacher, $course, $ana] = $this->seedClass($colegio, '4to', 'A', [['Ana Ruiz', 18]]);
        $old = Activity::create([
            'teacher_id' => $teacher->id,
            'course_id' => $course->id,
            'title' => 'Primera',
            'max_score' => 20,
        ]);
        $grade = Grade::create([
            'activity_id' => $old->id,
            'student_id' => $ana->id,
            'colegio_id' => $colegio->id,
            'score' => 19,
            'status' => 'published',
        ]);
        $grade->forceFill(['created_at' => now()->subDays(20)])->save();

        $response = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => '¿Quién ha bajado su promedio?',
        ]);

        $response->assertOk();
        $this->assertContains('get_declining_students', $response->json('tools'));
        $this->assertStringContainsString('Ana Ruiz', (string) $response->json('actions.0.message'));
    }

    public function test_ambiguous_course_without_context_asks_for_clarification(): void
    {
        [$director] = $this->directorContext();

        $response = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => '¿Cómo está mi curso?',
        ]);

        $response->assertOk();
        $this->assertTrue((bool) $response->json('needs_clarification'));
        $this->assertStringContainsString('curso', mb_strtolower((string) $response->json('message')));
    }

    public function test_selected_course_context_is_used_for_mi_curso(): void
    {
        [$director, $colegio] = $this->directorContext();
        $this->seedClass($colegio, '4to', 'A', [['Ana Ruiz', 18]]);

        $response = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => '¿Cómo está mi curso?',
            'screen_context' => [
                'grade' => '4to',
                'section' => 'A',
                'subject' => 'Matemática',
            ],
        ]);

        $response->assertOk()->assertJsonPath('actions.0.success', true);
        $this->assertStringContainsString('Ana Ruiz', (string) $response->json('actions.0.message'));
    }

    public function test_out_of_scope_question_does_not_invent_school_data(): void
    {
        [$director, $colegio] = $this->directorContext();
        $this->seedClass($colegio, '4to', 'A', [['Ana Ruiz', 18]]);

        $response = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => '¿Cuál es el clima hoy?',
        ]);

        $this->assertTrue(
            (bool) $response->json('needs_clarification') || str_contains((string) $response->json('message'), 'colegio')
        );
        $this->assertStringNotContainsString('Ana Ruiz', json_encode($response->json()));
    }

    public function test_existing_class_performance_query_still_works(): void
    {
        [$director, $colegio] = $this->directorContext();
        $this->seedClass($colegio, '4to', 'A', [['Ana Ruiz', 18], ['Luis Mora', 14]]);

        $response = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => '¿Cómo van los de 4to A?',
        ]);

        $response->assertOk()->assertJsonPath('actions.0.success', true);
        $this->assertStringContainsString('Rendimiento de 4to sección A', (string) $response->json('actions.0.message'));
    }

    public function test_agent_records_privacy_conscious_telemetry(): void
    {
        [$director, $colegio] = $this->directorContext();
        $this->seedClass($colegio, '4to', 'A', [['Ana Ruiz', 18]]);

        $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => '¿Cómo va 4to A?',
        ])->assertOk();

        $this->assertDatabaseHas('product_events', [
            'user_id' => $director->id,
            'colegio_id' => $colegio->id,
            'role' => 'director',
            'source' => 'director_data_agent',
            'event' => 'director_data_query',
            'status' => 'success',
        ]);
        $event = ProductEvent::query()->where('source', 'director_data_agent')->first();
        $this->assertNotNull($event);
        $this->assertNotNull($event->duration_ms);
        $this->assertSame('get_course_performance', $event->meta['tools'] ?? null);
        $this->assertArrayNotHasKey('prompt', $event->meta ?? []);
        $this->assertArrayHasKey('session_id', $event->meta ?? []);
    }

    public function test_injected_colegio_id_is_stripped_from_tool_args(): void
    {
        $agent = app(DirectorDataAgentService::class);
        $clean = $agent->sanitizeArgs([
            'grade' => '4to',
            'colegio_id' => 999,
            'school_id' => 888,
        ]);

        $this->assertSame('4to', $clean['grade']);
        $this->assertArrayNotHasKey('colegio_id', $clean);
        $this->assertArrayNotHasKey('school_id', $clean);
    }

    public function test_school_diagnosis_returns_executive_priority(): void
    {
        [$director, $colegio] = $this->directorContext();
        $this->seedClass($colegio, '4to', 'A', [['Ana Ruiz', 18], ['Luis Mora', 8]]);

        $response = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => '¿Cómo está mi colegio?',
        ]);

        $response->assertOk();
        $message = (string) $response->json('message');
        $this->assertStringContainsString('alumnos registrados', mb_strtolower($message));
        $this->assertStringContainsString('promedio general', mb_strtolower($message));
        $this->assertStringNotContainsString('**Hechos**', $message);
        $this->assertContains('generate_school_report', $response->json('tools'));
        $this->assertStringNotContainsString('Eva Inventada', $message);
    }

    public function test_follow_up_investigation_uses_conversation_memory(): void
    {
        [$director, $colegio] = $this->directorContext();
        [$teacher] = $this->seedClass($colegio, '4to', 'A', [['Ana Ruiz', 18], ['Luis Mora', 8]]);

        $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => '¿Quiénes necesitan atención?',
        ])->assertOk();

        $why = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => '¿Por qué?',
        ]);
        $why->assertOk();
        $this->assertStringContainsString('bajo rendimiento', mb_strtolower((string) $why->json('message')));

        $worst = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => '¿Cuál es el caso más preocupante?',
        ]);
        $worst->assertOk();
        $this->assertStringContainsString('Luis Mora', (string) $worst->json('message'));

        $subject = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => '¿En qué materia está peor?',
        ]);
        $subject->assertOk();
        $this->assertStringContainsString('Matemática', (string) $subject->json('message'));

        $teacherReply = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => '¿Quién es su profesor?',
        ]);
        $teacherReply->assertOk();
        $this->assertStringContainsString($teacher->name, (string) $teacherReply->json('message'));
    }

    public function test_meeting_report_is_structured_and_does_not_invent(): void
    {
        [$director, $colegio] = $this->directorContext();
        $this->seedClass($colegio, '4to', 'A', [['Ana Ruiz', 18], ['Luis Mora', 8]]);

        $response = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'Prepárame un informe del rendimiento de 4to A para la reunión de profesores.',
        ]);

        $response->assertOk();
        $message = (string) $response->json('message');
        $this->assertStringContainsString('Informe de rendimiento', $message);
        $this->assertStringContainsString('Estudiantes en riesgo', $message);
        $this->assertStringContainsString('Recomendación', $message);
        $this->assertTrue((bool) $response->json('report_ready'));
        $this->assertStringNotContainsString('Eva Inventada', $message);
    }

    public function test_investigative_prompt_uses_data_tools_instead_of_clarifying(): void
    {
        [$director, $colegio] = $this->directorContext();
        $this->seedClass($colegio, '3ro', 'A', [['Pedro Gil', 10]]);

        $response = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'Tengo la impresión de que 3ro está empeorando, ¿puedes investigar?',
        ]);

        $response->assertOk();
        $this->assertContains('get_course_performance', $response->json('tools'));
        $this->assertStringContainsString('Pedro Gil', json_encode($response->json(), JSON_UNESCAPED_UNICODE));
        $this->assertFalse((bool) $response->json('needs_clarification'));
    }

    public function test_student_count_follow_up_lists_names(): void
    {
        [$director, $colegio] = $this->directorContext();
        $this->seedClass($colegio, '4to', 'A', [['Ana Ruiz', 18], ['Luis Mora', 14]]);

        $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => '¿Cuántos alumnos hay en mi colegio?',
        ])->assertOk();

        $response = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'Nombralos y dime cuáles son',
        ]);

        $response->assertOk();
        $this->assertContains('get_students', $response->json('tools'));
        $payload = json_encode($response->json(), JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('Ana Ruiz', $payload);
        $this->assertStringContainsString('Luis Mora', $payload);
    }

    public function test_list_all_students_by_name_across_grades(): void
    {
        [$director, $colegio] = $this->directorContext();
        $this->seedClass($colegio, '4to', 'A', [['Ana Ruiz', 18]]);
        $this->seedClass($colegio, '2do', 'A', [['Pepe Sol', 16]]);

        $response = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'Dame el nombre de todos los alumnos del colegio, de todos los grados',
        ]);

        $response->assertOk();
        $this->assertContains('get_students', $response->json('tools'));
        $payload = json_encode($response->json(), JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('Ana Ruiz', $payload);
        $this->assertStringContainsString('Pepe Sol', $payload);
        $this->assertStringNotContainsString('Puedo crear y eliminar profesores', $payload);
    }

    public function test_director_can_ask_school_name_and_most_advanced_course(): void
    {
        [$director, $colegio] = $this->directorContext('Colegio Horizonte', 'COC-H001');
        $this->seedClass($colegio, '4to', 'A', [['Ana Ruiz', 18]]);
        $this->seedClass($colegio, '2do', 'A', [['Pepe Sol', 16]]);

        $nameResponse = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => '¿Cómo se llama mi colegio?',
        ]);
        $nameResponse->assertOk();
        $this->assertStringContainsString('Colegio Horizonte', (string) $nameResponse->json('message'));
        $this->assertStringNotContainsString('Puedo consultar notas', (string) $nameResponse->json('message'));

        $courseResponse = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => '¿Cuál es el curso más avanzado?',
        ]);
        $courseResponse->assertOk();
        $this->assertStringContainsString('4to', (string) $courseResponse->json('message'));
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
    private function directorContext(string $name = 'Colegio Central', string $code = 'COC-1001'): array
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

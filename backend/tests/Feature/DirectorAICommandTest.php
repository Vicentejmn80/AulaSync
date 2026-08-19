<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Attendance;
use App\Models\Colegio;
use App\Models\Course;
use App\Models\DirectorAiOperationLog;
use App\Models\Grade;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectorAICommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_director_can_create_teacher_invite_with_assignments_via_ai(): void
    {
        [$director, $colegio] = $this->directorContext();

        $draft = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'Crea al profesor Vicente Maduro y asígnale Inglés de 1ro a 3ro.',
        ]);

        $draft->assertOk()
            ->assertJsonPath('requires_confirmation', true);

        $pending = $draft->json('pending_actions');
        $execute = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'confirmed' => true,
            'pending_actions' => $pending,
        ]);

        $execute->assertOk();
        $this->assertTrue(
            (bool) $execute->json('actions.0.success'),
            json_encode($execute->json(), JSON_UNESCAPED_UNICODE)
        );

        $this->assertDatabaseHas('teacher_invites', [
            'colegio_id' => $colegio->id,
            'name' => 'Vicente Maduro',
        ]);
        $this->assertSame(3, Course::where('colegio_id', $colegio->id)->count());
    }

    public function test_missing_grades_do_not_block_and_are_created_after_confirmation(): void
    {
        [$director, $colegio] = $this->directorContext();
        $teacher = User::factory()->create([
            'role' => 'profesor',
            'colegio_id' => $colegio->id,
            'onboarding_completed' => true,
        ]);

        Student::create([
            'teacher_id' => $director->id,
            'colegio_id' => $colegio->id,
            'name' => 'Alumno Base',
            'grade' => '1ro',
            'section' => 'A',
            'family_code' => 'NV-BASE-01',
        ]);

        $draft = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => "{$teacher->name} dará Matemática de 1ro a 6to.",
        ]);

        $draft->assertOk()
            ->assertJsonPath('requires_confirmation', true);
        $this->assertStringContainsString(
            '2do, 3ro',
            (string) $draft->json('message')
        );
        $this->assertNotEmpty($draft->json('pending_actions.0.data.grades'));

        $execute = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'confirmed' => true,
            'pending_actions' => $draft->json('pending_actions'),
        ]);

        $execute->assertOk();
        $this->assertTrue(
            (bool) $execute->json('actions.0.success'),
            json_encode($execute->json(), JSON_UNESCAPED_UNICODE)
        );

        $this->assertSame(6, Course::where('colegio_id', $colegio->id)->count());
        $this->assertDatabaseHas('courses', [
            'colegio_id' => $colegio->id,
            'subject_name' => 'Matemática',
            'grade' => '6to',
        ]);
    }

    public function test_short_affirmative_completes_pending_action_without_new_prompt(): void
    {
        [$director, $colegio] = $this->directorContext();

        $draft = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'Crea al profesor Vicente Maduro y asígnale Inglés de 1ro a 3ro.',
        ]);
        $draft->assertOk()->assertJsonPath('requires_confirmation', true);

        $execute = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'sí, créalos',
        ]);

        $execute->assertOk();
        $this->assertTrue(
            (bool) $execute->json('actions.0.success'),
            json_encode($execute->json(), JSON_UNESCAPED_UNICODE)
        );
        $this->assertSame(3, Course::where('colegio_id', $colegio->id)->count());
    }

    public function test_director_can_create_course_with_greater_variants(): void
    {
        [$director, $colegio] = $this->directorContext();

        $draft = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'creame el cursso de 1er grado de matematicas',
        ]);

        $draft->assertOk()
            ->assertJsonPath('requires_confirmation', true)
            ->assertJsonPath('pending_actions.0.intent', 'create_course')
            ->assertJsonPath('pending_actions.0.data.grade', '1ro')
            ->assertJsonPath('pending_actions.0.data.subject_name', 'Matematicas');

        $execute = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'confirmed' => true,
            'pending_actions' => $draft->json('pending_actions'),
        ]);

        $execute->assertOk()->assertJsonPath('actions.0.success', true);
        $this->assertDatabaseHas('courses', [
            'colegio_id' => $colegio->id,
            'subject_name' => 'Matematicas',
            'grade' => '1ro',
        ]);
    }

    public function test_director_can_create_students_batch_and_report_duplicates(): void
    {
        [$director, $colegio] = $this->directorContext();

        Student::create([
            'teacher_id' => $director->id,
            'colegio_id' => $colegio->id,
            'name' => 'Carlos José',
            'grade' => '3ro',
            'section' => null,
            'family_code' => 'NV-EXIST-01',
        ]);

        $draft = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'Agrega a Carlos José, Juan Carlos y María al 3er grado.',
        ]);
        $draft->assertOk()->assertJsonPath('requires_confirmation', true);

        $execute = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'confirmed' => true,
            'pending_actions' => $draft->json('pending_actions'),
        ]);

        $execute->assertOk();
        $this->assertTrue(
            (bool) $execute->json('actions.0.success'),
            json_encode($execute->json(), JSON_UNESCAPED_UNICODE)
        );

        $this->assertDatabaseHas('students', [
            'colegio_id' => $colegio->id,
            'name' => 'Juan Carlos',
            'grade' => '3ro',
        ]);
        $this->assertDatabaseHas('students', [
            'colegio_id' => $colegio->id,
            'name' => 'María',
            'grade' => '3ro',
        ]);
        $this->assertContains('Carlos José', $execute->json('actions.0.data.duplicates'));
    }

    public function test_director_can_create_student_and_enroll_in_course_in_one_command(): void
    {
        [$director, $colegio] = $this->directorContext();

        Course::create([
            'teacher_id' => $director->id,
            'colegio_id' => $colegio->id,
            'subject_name' => 'Inglés',
            'grade' => '1ro',
            'section' => null,
            'school_year' => '2025-2026',
            'invite_code' => 'ING-1RO',
        ]);

        $draft = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'crea al alumno andres perez y asignalo al curso de primer grado de ingles',
        ]);

        $draft->assertOk()
            ->assertJsonPath('requires_confirmation', true);

        $pending = $draft->json('pending_actions');
        $this->assertCount(2, $pending);
        $this->assertSame('create_students_batch', $pending[0]['intent']);
        $this->assertSame('enroll_students_course', $pending[1]['intent']);
        $this->assertSame('andres perez', mb_strtolower((string) ($pending[0]['data']['names'][0] ?? '')));
        $this->assertSame('1ro', $pending[0]['data']['grade'] ?? null);
        $this->assertSame('Inglés', $pending[1]['data']['subject_name'] ?? null);

        $execute = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'confirmed' => true,
            'pending_actions' => $pending,
        ]);

        $execute->assertOk();
        $this->assertDatabaseHas('students', [
            'colegio_id' => $colegio->id,
            'grade' => '1ro',
        ]);
        $this->assertTrue(
            Student::query()
                ->where('colegio_id', $colegio->id)
                ->whereRaw('LOWER(name) = ?', ['andres perez'])
                ->exists()
        );
    }

    public function test_create_student_prompt_does_not_trigger_create_course(): void
    {
        [$director, $colegio] = $this->directorContext();

        Course::create([
            'teacher_id' => $director->id,
            'colegio_id' => $colegio->id,
            'subject_name' => 'Inglés',
            'grade' => '1ro',
            'section' => null,
            'school_year' => '2025-2026',
            'invite_code' => 'ING-1RO',
        ]);

        $draft = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'no, ya el curso esta, necesito que crees es al alumno andres perez para primer grado de ingles',
        ]);

        $draft->assertOk();
        $intents = collect($draft->json('pending_actions'))->pluck('intent')->all();
        $this->assertNotContains('create_course', $intents);
        $this->assertContains('create_students_batch', $intents);
    }

    public function test_director_can_create_course_and_enroll_students_to_course(): void
    {
        [$director, $colegio] = $this->directorContext();
        User::factory()->create([
            'name' => 'María Gómez',
            'role' => 'profesor',
            'colegio_id' => $colegio->id,
            'onboarding_completed' => true,
        ]);

        Student::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => $director->id,
            'name' => 'Luis Pérez',
            'grade' => '4to',
            'section' => 'A',
            'family_code' => 'FAM-1',
        ]);
        Student::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => $director->id,
            'name' => 'Marta Ruiz',
            'grade' => '4to',
            'section' => 'A',
            'family_code' => 'FAM-2',
        ]);

        $createDraft = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'Crea curso de Matemática para 4to grado sección A y asígnalo a profesora María Gómez.',
        ]);
        $createDraft->assertOk()->assertJsonPath('requires_confirmation', true);

        $createExec = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'confirmed' => true,
            'pending_actions' => $createDraft->json('pending_actions'),
        ]);
        $createExec->assertOk()->assertJsonPath('actions.0.success', true);

        $enrollDraft = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'Inscribe a Luis Pérez y Marta Ruiz en curso de Matemática de 4to grado sección A.',
        ]);
        $enrollDraft->assertOk(
            json_encode($enrollDraft->json(), JSON_UNESCAPED_UNICODE)
        )->assertJsonPath('requires_confirmation', true);

        $enrollExec = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'confirmed' => true,
            'pending_actions' => $enrollDraft->json('pending_actions'),
        ]);
        $enrollExec->assertOk();
        $this->assertTrue(
            (bool) $enrollExec->json('actions.0.success'),
            json_encode($enrollExec->json(), JSON_UNESCAPED_UNICODE)
        );

        $course = Course::where('colegio_id', $colegio->id)
            ->where('subject_name', 'Matemática')
            ->where('grade', '4to')
            ->firstOrFail();
        $this->assertSame(2, $course->students()->count());
    }

    public function test_director_can_query_teacher_academic_status_without_confirmation_step(): void
    {
        [$director, $colegio] = $this->directorContext();
        $teacher = User::factory()->create([
            'name' => 'Carlos Pérez',
            'role' => 'profesor',
            'colegio_id' => $colegio->id,
            'onboarding_completed' => true,
        ]);
        Course::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => $teacher->id,
            'subject_name' => 'Matemática',
            'grade' => '4to',
            'section' => 'A',
            'school_year' => '2026-2027',
            'invite_code' => 'CURSO-MAT-4TO',
        ]);

        $response = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => '¿Cómo va el profesor Carlos Pérez?',
        ]);

        $response->assertOk(
            json_encode($response->json(), JSON_UNESCAPED_UNICODE)
        )
            ->assertJsonPath('actions.0.success', true);
        $this->assertNull($response->json('requires_confirmation'));
    }

    public function test_director_can_create_courses_for_grade_range_phrase(): void
    {
        [$director, $colegio] = $this->directorContext();

        $draft = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'quiero que crees los cursos de: 1ero...6to grado de ingles',
        ]);

        $draft->assertOk()
            ->assertJsonPath('requires_confirmation', true)
            ->assertJsonPath('pending_actions.0.intent', 'create_course')
            ->assertJsonPath('pending_actions.0.data.subject_name', 'Ingles');

        $grades = $draft->json('pending_actions.0.data.grades');
        $this->assertSame(['1ro', '2do', '3ro', '4to', '5to', '6to'], $grades);

        $execute = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'confirmed' => true,
            'pending_actions' => $draft->json('pending_actions'),
        ]);

        $execute->assertOk()->assertJsonPath('actions.0.success', true);
        $this->assertSame(6, Course::where('colegio_id', $colegio->id)
            ->where('subject_name', 'Ingles')
            ->count());
        $this->assertDatabaseHas('courses', [
            'colegio_id' => $colegio->id,
            'subject_name' => 'Ingles',
            'grade' => '6to',
        ]);
    }

    public function test_director_can_create_course_with_subject_before_grade_list(): void
    {
        [$director, $colegio] = $this->directorContext();

        $draft = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'Crea Matemática para 4.º, 5.º y 6.º.',
        ]);

        $draft->assertOk()
            ->assertJsonPath('requires_confirmation', true)
            ->assertJsonPath('pending_actions.0.intent', 'create_course')
            ->assertJsonPath('pending_actions.0.data.subject_name', 'Matemática')
            ->assertJsonPath('pending_actions.0.data.grades', ['4to', '5to', '6to']);

        $execute = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'confirmed' => true,
            'pending_actions' => $draft->json('pending_actions'),
        ]);

        $execute->assertOk()->assertJsonPath('actions.0.success', true);
        $this->assertSame(3, Course::where('colegio_id', $colegio->id)
            ->where('subject_name', 'Matemática')
            ->count());
    }

    public function test_director_can_query_school_stats_and_courses(): void
    {
        [$director, $colegio] = $this->directorContext();

        User::factory()->create([
            'name' => 'Profesor Uno',
            'role' => 'profesor',
            'colegio_id' => $colegio->id,
            'onboarding_completed' => true,
        ]);
        Course::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => null,
            'subject_name' => 'Ingles',
            'grade' => '3ro',
            'section' => 'A',
            'school_year' => '2026-2027',
            'invite_code' => 'CURSO-ING-3',
        ]);

        $studentsResponse = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => '¿Cuántos profesores tengo?',
        ]);
        $studentsResponse->assertOk()->assertJsonPath('actions.0.success', true);
        $this->assertSame(1, $studentsResponse->json('actions.0.data.teachers_count'));

        $coursesResponse = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => '¿Qué cursos existen?',
        ]);
        $coursesResponse->assertOk()->assertJsonPath('actions.0.success', true);
        $this->assertSame(1, count($coursesResponse->json('actions.0.data.courses')));
        $this->assertNull($coursesResponse->json('requires_confirmation'));
    }

    public function test_director_can_query_frequent_absentees(): void
    {
        [$director, $colegio] = $this->directorContext();
        $teacher = User::factory()->create([
            'role' => 'profesor',
            'colegio_id' => $colegio->id,
            'onboarding_completed' => true,
        ]);
        $course = Course::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => $teacher->id,
            'subject_name' => 'Matemática',
            'grade' => '4to',
            'section' => 'A',
            'school_year' => '2026-2027',
            'invite_code' => 'CURSO-MAT-4TO',
        ]);
        $student = Student::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => $teacher->id,
            'name' => 'Carlos García',
            'grade' => '4to',
            'section' => 'A',
            'family_code' => 'FAM-ABS',
        ]);
        Attendance::create([
            'colegio_id' => $colegio->id,
            'course_id' => $course->id,
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'attended_on' => now()->subDay()->toDateString(),
            'status' => Attendance::STATUS_ABSENT,
        ]);

        $response = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => '¿Quién ha faltado más?',
        ]);

        $response->assertOk()->assertJsonPath('actions.0.success', true);
        $this->assertStringContainsString('Carlos García', (string) $response->json('actions.0.message'));
    }

    public function test_director_can_query_students_at_risk_in_subject(): void
    {
        [$director, $colegio] = $this->directorContext();
        $teacher = User::factory()->create([
            'role' => 'profesor',
            'colegio_id' => $colegio->id,
            'onboarding_completed' => true,
        ]);
        $course = Course::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => $teacher->id,
            'subject_name' => 'Matemática',
            'grade' => '5to',
            'section' => 'A',
            'school_year' => '2026-2027',
            'invite_code' => 'CURSO-MAT-5TO',
        ]);
        $student = Student::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => $teacher->id,
            'name' => 'María López',
            'grade' => '5to',
            'section' => 'A',
            'family_code' => 'FAM-RISK',
        ]);
        $activity = Activity::create([
            'teacher_id' => $teacher->id,
            'course_id' => $course->id,
            'title' => 'Examen parcial',
            'max_score' => 20,
        ]);
        Grade::create([
            'activity_id' => $activity->id,
            'student_id' => $student->id,
            'colegio_id' => $colegio->id,
            'score' => 8,
            'status' => 'published',
        ]);

        $response = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => '¿Qué alumnos tienen bajo rendimiento en Matemática?',
        ]);

        $response->assertOk()->assertJsonPath('actions.0.success', true);
        $this->assertStringContainsString('María López', (string) $response->json('actions.0.message'));
    }

    public function test_director_ai_operations_are_tenanted_and_audit_logged(): void
    {
        [$director, $colegio] = $this->directorContext();

        $draft = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'Crea curso de Matemática para 4to grado sección A.',
        ]);
        $draft->assertOk()->assertJsonPath('requires_confirmation', true);
        $logId = $draft->json('pending_actions.0.audit_log_id');
        $this->assertNotNull($logId);

        $log = DirectorAiOperationLog::findOrFail($logId);
        $this->assertSame('pending_confirmation', $log->status);
        $this->assertSame($colegio->id, $log->colegio_id);
        $this->assertSame('create_course', $log->intent);

        $execute = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'confirmed' => true,
            'pending_actions' => $draft->json('pending_actions'),
        ]);
        $execute->assertOk()->assertJsonPath('actions.0.success', true);

        $this->assertSame('verified', $log->fresh()->status);
        $this->assertNotNull($log->fresh()->executed_at);
        $this->assertNotNull($log->fresh()->verified_at);

        $otherDirector = User::factory()->create([
            'role' => 'director',
            'onboarding_completed' => true,
        ]);
        $otherColegio = Colegio::create([
            'name' => 'Colegio Norte',
            'invite_code' => 'CON-2002',
            'codes_pin' => Colegio::hashPinFromInvite('CON-2002'),
            'director_user_id' => $otherDirector->id,
        ]);
        $otherDirector->update(['colegio_id' => $otherColegio->id]);
        $otherDirector = $otherDirector->fresh();
        $otherDraft = $this->actingAs($otherDirector)->postJson(route('director.ai.command'), [
            'prompt' => 'Crea curso de Matemática para 5to grado sección A.',
        ]);
        $otherDraft->assertOk()->assertJsonPath('requires_confirmation', true);
        $this->actingAs($otherDirector)->postJson(route('director.ai.command'), [
            'confirmed' => true,
            'pending_actions' => $otherDraft->json('pending_actions'),
        ])->assertOk()->assertJsonPath('actions.0.success', true);

        $this->assertSame(1, Course::where('colegio_id', $colegio->id)->count());
        $this->assertSame(1, Course::where('colegio_id', $otherDirector->colegio_id)->count());
    }

    public function test_director_can_query_grade_overview(): void
    {
        [$director, $colegio] = $this->directorContext();

        $course = Course::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => null,
            'subject_name' => 'Matemática',
            'grade' => '4to',
            'section' => 'A',
            'school_year' => '2026-2027',
            'invite_code' => 'CURSO-MAT-4TO',
        ]);
        $student = Student::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => $director->id,
            'name' => 'Ana Ruiz',
            'grade' => '4to',
            'section' => 'A',
            'family_code' => 'FAM-GR',
        ]);
        $course->students()->attach($student->id);

        $response = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => '¿Cómo está 4to grado?',
        ]);

        $response->assertOk()->assertJsonPath('actions.0.success', true);
        $this->assertSame('4to', $response->json('actions.0.data.grade'));
        $this->assertSame(1, $response->json('actions.0.data.students_count'));
    }

    public function test_director_can_query_students_with_problems_in_subject(): void
    {
        [$director, $colegio] = $this->directorContext();
        $course = Course::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => null,
            'subject_name' => 'Matemática',
            'grade' => '5to',
            'section' => 'A',
            'school_year' => '2026-2027',
            'invite_code' => 'CURSO-MAT-5TO',
        ]);
        Student::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => $director->id,
            'name' => 'Pedro Torres',
            'grade' => '5to',
            'section' => 'A',
            'family_code' => 'FAM-PROB',
        ]);

        $response = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => '¿Quién está teniendo problemas en Matemática?',
        ]);

        $response->assertOk()->assertJsonPath('actions.0.success', true);
        $this->assertStringContainsString(
            'No tengo calificaciones',
            (string) $response->json('actions.0.message')
        );
    }

    public function test_director_can_query_school_teachers(): void
    {
        [$director, $colegio] = $this->directorContext();
        User::factory()->create([
            'name' => 'Profesor Uno',
            'role' => 'profesor',
            'colegio_id' => $colegio->id,
            'onboarding_completed' => true,
        ]);

        $response = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => '¿Qué profesores tengo?',
        ]);

        $response->assertOk()->assertJsonPath('actions.0.success', true);
        $this->assertSame(1, count($response->json('actions.0.data.teachers')));
        $this->assertNull($response->json('requires_confirmation'));
    }

    public function test_director_cannot_use_teacher_chat_endpoint(): void
    {
        [$director] = $this->directorContext();

        $response = $this->actingAs($director)->post('/ai/command', [
            'prompt' => 'hola',
        ]);

        $response->assertStatus(302);
    }

    public function test_director_can_delete_all_teachers_after_confirmation(): void
    {
        [$director, $colegio] = $this->directorContext();
        $teacher = User::factory()->create([
            'name' => 'Carlos Pérez',
            'role' => 'profesor',
            'colegio_id' => $colegio->id,
            'onboarding_completed' => true,
        ]);
        $course = Course::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => $teacher->id,
            'subject_name' => 'Inglés',
            'grade' => '3ro',
            'section' => 'A',
            'school_year' => '2026-2027',
            'invite_code' => 'CURSO-ING-3',
        ]);
        $student = Student::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => $teacher->id,
            'name' => 'Ana Ruiz',
            'grade' => '3ro',
            'section' => 'A',
            'family_code' => 'FAM-DEL',
        ]);

        $draft = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'elimina a los profesores que hay',
        ]);

        $draft->assertOk()
            ->assertJsonPath('requires_confirmation', true)
            ->assertJsonPath('pending_actions.0.intent', 'delete_all_teachers');

        $execute = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'sí',
        ]);

        $execute->assertOk();
        $this->assertTrue(
            (bool) $execute->json('actions.0.success'),
            json_encode($execute->json(), JSON_UNESCAPED_UNICODE)
        );
        $this->assertSame(0, User::where('colegio_id', $colegio->id)->where('role', 'profesor')->count());
        $this->assertDatabaseHas('courses', [
            'id' => $course->id,
            'colegio_id' => $colegio->id,
            'teacher_id' => null,
        ]);
        $this->assertDatabaseHas('students', [
            'id' => $student->id,
            'colegio_id' => $colegio->id,
            'teacher_id' => $director->id,
        ]);
    }

    public function test_director_can_delete_course_by_subject(): void
    {
        [$director, $colegio] = $this->directorContext();
        Course::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => null,
            'subject_name' => 'Matemática',
            'grade' => '4to',
            'section' => 'A',
            'school_year' => '2026-2027',
            'invite_code' => 'CURSO-MAT-4TO',
        ]);
        Course::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => null,
            'subject_name' => 'Inglés',
            'grade' => '4to',
            'section' => 'A',
            'school_year' => '2026-2027',
            'invite_code' => 'CURSO-ING-4TO',
        ]);

        $draft = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'borra el curso de matemáticas',
        ]);

        $draft->assertOk(
            json_encode($draft->json(), JSON_UNESCAPED_UNICODE)
        )
            ->assertJsonPath('requires_confirmation', true)
            ->assertJsonPath('pending_actions.0.intent', 'delete_course');

        $execute = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'confirmed' => true,
            'pending_actions' => $draft->json('pending_actions'),
        ]);

        $execute->assertOk()->assertJsonPath('actions.0.success', true);
        $this->assertDatabaseMissing('courses', [
            'colegio_id' => $colegio->id,
            'subject_name' => 'Matemática',
        ]);
        $this->assertDatabaseHas('courses', [
            'colegio_id' => $colegio->id,
            'subject_name' => 'Inglés',
        ]);
    }

    public function test_delete_teachers_is_limited_to_director_colegio(): void
    {
        [$director, $colegio] = $this->directorContext();
        User::factory()->create([
            'name' => 'Local Teacher',
            'role' => 'profesor',
            'colegio_id' => $colegio->id,
            'onboarding_completed' => true,
        ]);

        $otherDirector = User::factory()->create([
            'role' => 'director',
            'onboarding_completed' => true,
        ]);
        $otherColegio = Colegio::create([
            'name' => 'Colegio Norte',
            'invite_code' => 'CON-3003',
            'codes_pin' => Colegio::hashPinFromInvite('CON-3003'),
            'director_user_id' => $otherDirector->id,
        ]);
        $otherDirector->update(['colegio_id' => $otherColegio->id]);
        $otherTeacher = User::factory()->create([
            'name' => 'Remote Teacher',
            'role' => 'profesor',
            'colegio_id' => $otherColegio->id,
            'onboarding_completed' => true,
        ]);

        $draft = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'elimina a todos los profesores',
        ]);
        $draft->assertOk()->assertJsonPath('pending_actions.0.intent', 'delete_all_teachers');

        $this->actingAs($director)->postJson(route('director.ai.command'), [
            'confirmed' => true,
            'pending_actions' => $draft->json('pending_actions'),
        ])->assertOk()->assertJsonPath('actions.0.success', true);

        $this->assertDatabaseMissing('users', [
            'colegio_id' => $colegio->id,
            'role' => 'profesor',
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $otherTeacher->id,
            'colegio_id' => $otherColegio->id,
            'role' => 'profesor',
        ]);
    }

    public function test_reported_jason_conversation_creates_invite_and_assigns_english_first_to_sixth(): void
    {
        [$director, $colegio] = $this->directorContext();

        $create = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'crea al profesor Jason David',
        ]);
        $create->assertOk()->assertJsonPath('pending_actions.0.intent', 'create_teacher');

        $createExecution = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'créalo',
        ]);
        $createExecution->assertOk();
        $this->assertTrue(
            (bool) $createExecution->json('actions.0.success'),
            json_encode($createExecution->json(), JSON_UNESCAPED_UNICODE)
        );

        $assign = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'ahora agrégale la materia de inglés de primer grado a sexto grado a Jason David',
        ]);
        $assign->assertOk(
            json_encode($assign->json(), JSON_UNESCAPED_UNICODE)
        )
            ->assertJsonPath('requires_confirmation', true)
            ->assertJsonPath('pending_actions.0.intent', 'assign_teacher')
            ->assertJsonPath('pending_actions.0.data.grades', ['1ro', '2do', '3ro', '4to', '5to', '6to']);

        $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'sí',
        ])->assertOk()->assertJsonPath('actions.0.success', true);

        $this->assertDatabaseHas('teacher_invites', [
            'colegio_id' => $colegio->id,
            'name' => 'Jason David',
        ]);
        $this->assertSame(6, Course::query()
            ->where('colegio_id', $colegio->id)
            ->whereRaw('LOWER(subject_name) = ?', ['inglés'])
            ->count());
    }

    public function test_compound_prompt_creates_teacher_and_english_courses_in_one_confirmation(): void
    {
        [$director, $colegio] = $this->directorContext();

        $draft = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'crea al profesor yovanny andrade, crea los cursos de ingles de 1ero a 6to grado y agregalo a esos cursos',
        ]);

        $draft->assertOk(json_encode($draft->json(), JSON_UNESCAPED_UNICODE))
            ->assertJsonPath('requires_confirmation', true)
            ->assertJsonPath('pending_actions.0.intent', 'create_teacher')
            ->assertJsonPath('pending_actions.0.data.teacher_name', 'yovanny andrade')
            ->assertJsonPath('pending_actions.0.data.subject_name', 'Inglés')
            ->assertJsonPath('pending_actions.0.data.grades', ['1ro', '2do', '3ro', '4to', '5to', '6to']);
        $this->assertStringContainsString('Inglés', (string) $draft->json('message'));
        $this->assertStringNotContainsString('sin materias iniciales', (string) $draft->json('message'));

        $execute = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'sí',
        ]);
        $execute->assertOk();
        $this->assertTrue(
            (bool) $execute->json('actions.0.success'),
            json_encode($execute->json(), JSON_UNESCAPED_UNICODE)
        );
        $this->assertDatabaseHas('teacher_invites', [
            'colegio_id' => $colegio->id,
            'name' => 'yovanny andrade',
        ]);
        $this->assertSame(6, Course::query()
            ->where('colegio_id', $colegio->id)
            ->whereRaw('LOWER(subject_name) = ?', ['inglés'])
            ->count());
    }

    public function test_cancel_discards_pending_plan_without_changes(): void
    {
        [$director, $colegio] = $this->directorContext();

        $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'crea al profesor Jason David',
        ])->assertOk()->assertJsonPath('requires_confirmation', true);

        $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'mejor no',
        ])
            ->assertOk()
            ->assertJsonPath('cancelled', true);

        $this->assertDatabaseMissing('teacher_invites', [
            'colegio_id' => $colegio->id,
            'name' => 'Jason David',
        ]);
    }

    public function test_client_cannot_replace_canonical_pending_plan(): void
    {
        [$director, $colegio] = $this->directorContext();
        User::factory()->create([
            'role' => 'profesor',
            'colegio_id' => $colegio->id,
            'onboarding_completed' => true,
        ]);

        $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'crea al profesor Jason David',
        ])->assertOk()->assertJsonPath('requires_confirmation', true);

        $execute = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'confirmed' => true,
            'pending_actions' => [[
                'intent' => 'delete_all_teachers',
                'data' => ['count' => 1],
            ]],
        ]);

        $execute->assertOk()->assertJsonPath('actions.0.action_type', 'create_teacher');
        $this->assertDatabaseHas('teacher_invites', [
            'colegio_id' => $colegio->id,
            'name' => 'Jason David',
        ]);
        $this->assertSame(1, User::where('colegio_id', $colegio->id)->where('role', 'profesor')->count());
    }

    public function test_partial_teacher_name_ambiguity_requests_clarification(): void
    {
        [$director, $colegio] = $this->directorContext();
        foreach (['Carlos Pérez', 'Carlos Gómez'] as $name) {
            User::factory()->create([
                'name' => $name,
                'role' => 'profesor',
                'colegio_id' => $colegio->id,
                'onboarding_completed' => true,
            ]);
        }

        $response = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'Carlos dará Inglés de 1ro a 2do',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('needs_clarification', true);
        $this->assertStringContainsString('varias coincidencias', (string) $response->json('message'));
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
            'invite_code' => 'COC-1001',
            'codes_pin' => Colegio::hashPinFromInvite('COC-1001'),
            'director_user_id' => $director->id,
        ]);
        $director->update(['colegio_id' => $colegio->id]);

        return [$director->fresh(), $colegio];
    }
}

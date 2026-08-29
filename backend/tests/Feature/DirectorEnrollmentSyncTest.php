<?php

namespace Tests\Feature;

use App\Models\Colegio;
use App\Models\Course;
use App\Models\Student;
use App\Models\User;
use App\Services\DirectorActionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectorEnrollmentSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.openai.key' => 'test-key',
            'services.openai.director_enabled' => false,
            'services.openai.director_test_enabled' => false,
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

    public function test_create_students_batch_enrolls_in_existing_grade_courses(): void
    {
        [$director, $colegio] = $this->directorContext();

        $math = Course::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => null,
            'subject_name' => 'Matemática',
            'grade' => '3ro',
            'section' => null,
            'school_year' => '2026-2027',
            'invite_code' => 'CURSO-MAT-3RO',
        ]);
        $bio = Course::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => null,
            'subject_name' => 'Biología',
            'grade' => '3ro',
            'section' => null,
            'school_year' => '2026-2027',
            'invite_code' => 'CURSO-BIO-3RO',
        ]);
        Course::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => null,
            'subject_name' => 'Lenguaje',
            'grade' => '4to',
            'section' => null,
            'school_year' => '2026-2027',
            'invite_code' => 'CURSO-LEN-4TO',
        ]);

        $result = app(DirectorActionService::class)->createStudentsBatch($director, [
            'students_data' => [
                ['name' => 'Jason David', 'grade' => '3ro', 'section' => null, 'subject_name' => null, 'teacher_name' => null],
            ],
        ]);

        $student = $result['created']->first();
        $this->assertNotNull($student);
        $this->assertTrue($math->fresh()->students()->where('students.id', $student->id)->exists());
        $this->assertTrue($bio->fresh()->students()->where('students.id', $student->id)->exists());
        $this->assertSame(0, Course::where('invite_code', 'CURSO-LEN-4TO')->first()->students()->count());
    }

    public function test_create_courses_batch_enrolls_existing_students_of_that_grade(): void
    {
        [$director, $colegio] = $this->directorContext();

        $ana = Student::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => $director->id,
            'name' => 'Ana Ruiz',
            'grade' => '3ro',
            'section' => 'A',
            'family_code' => 'FAM-ANA',
        ]);
        Student::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => $director->id,
            'name' => 'Luis Mora',
            'grade' => '4to',
            'section' => 'A',
            'family_code' => 'FAM-LUIS',
        ]);

        $result = app(DirectorActionService::class)->createCoursesBatch($director, [
            'courses_data' => [
                ['subject_name' => 'Biología', 'grades' => ['3ro'], 'section' => null, 'teacher_name' => null],
            ],
        ]);

        $course = $result['courses']->first();
        $this->assertNotNull($course);
        $this->assertTrue($course->students()->where('students.id', $ana->id)->exists());
        $this->assertSame(1, $course->students()->count());
    }

    public function test_sync_all_enrollments_links_matching_grades_only(): void
    {
        [$director, $colegio] = $this->directorContext();

        $course = Course::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => null,
            'subject_name' => 'Historia',
            'grade' => '2do',
            'section' => null,
            'school_year' => '2026-2027',
            'invite_code' => 'CURSO-HIS-2DO',
        ]);
        $vicente = Student::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => $director->id,
            'name' => 'Vicente José',
            'grade' => '2do',
            'section' => null,
            'family_code' => 'FAM-VJ',
        ]);
        Student::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => $director->id,
            'name' => 'Valeria Navarro',
            'grade' => '5to',
            'section' => null,
            'family_code' => 'FAM-VN',
        ]);

        $result = app(DirectorActionService::class)->syncAllEnrollments($director);

        $this->assertSame(1, $result['links_created']);
        $this->assertTrue($course->fresh()->students()->where('students.id', $vicente->id)->exists());
        $this->assertSame(1, $course->fresh()->students()->count());
    }

    public function test_sync_command_from_chat_requires_confirmation_and_executes(): void
    {
        [$director, $colegio] = $this->directorContext();

        $course = Course::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => null,
            'subject_name' => 'Arte',
            'grade' => '1ro',
            'section' => null,
            'school_year' => '2026-2027',
            'invite_code' => 'CURSO-ART-1RO',
        ]);
        $student = Student::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => $director->id,
            'name' => 'Carlos Duarte',
            'grade' => '1ro',
            'section' => null,
            'family_code' => 'FAM-CD',
        ]);

        $draft = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'sincroniza las matriculas de todos los alumnos',
        ]);

        $draft->assertOk()->assertJsonPath('requires_confirmation', true);
        $this->assertSame('sync_all_enrollments', $draft->json('pending_actions.0.intent'));

        $execute = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'sí',
        ]);
        $execute->assertOk()->assertJsonPath('success', true);
        $this->assertTrue($course->fresh()->students()->where('students.id', $student->id)->exists());
    }

    public function test_uncertain_or_empty_extraction_clears_residual_pending_action(): void
    {
        [$director, $colegio] = $this->directorContext();

        $draft = $this->actingAs($director)->withSession([
            'director_ai_pending_actions' => [[
                'intent' => 'create_teacher',
                'data' => [
                    'teacher_name' => 'Josue Campos',
                    'subject_name' => 'Biología',
                    'grades' => ['3ro'],
                ],
            ]],
            'chat_pending' => [
                'action' => 'pending_action',
                'type' => 'confirmation',
                'total' => 1,
            ],
        ])->postJson(route('director.ai.command'), [
            'prompt' => 'xyzzy plugh no es una orden valida de nada',
        ]);

        $draft->assertJsonPath('needs_clarification', true);
        $this->assertFalse(session()->has('director_ai_pending_actions'));
        $this->assertFalse(session()->has('chat_pending'));

        $confirm = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'sí',
        ]);

        $this->assertDatabaseMissing('teacher_invites', [
            'colegio_id' => $colegio->id,
            'name' => 'Josue Campos',
        ]);
        $confirm->assertJsonMissingPath('actions.0.success');
    }

    public function test_empty_confirmed_payload_does_not_execute_residual_work(): void
    {
        [$director] = $this->directorContext();

        $response = $this->actingAs($director)->withSession([
            'director_ai_pending_actions' => [['intent' => '', 'data' => []]],
        ])->postJson(route('director.ai.command'), [
            'confirmed' => true,
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsStringIgnoringCase('no hay acciones pendientes', (string) $response->json('message'));
    }
}

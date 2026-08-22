<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Colegio;
use App\Models\Course;
use App\Models\Student;
use App\Models\TeacherInvite;
use App\Models\User;
use App\Services\StudentEnrollmentService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class SchoolAIAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_director_can_enroll_students_and_teacher_is_denied(): void
    {
        [$director, $teacher, $colegio] = $this->school();
        $student = $this->student($colegio->id, $director->id, 'Ana Ruiz');
        $own = $this->course($colegio->id, $teacher->id, 'Inglés', '3ro', 'OWN-3');

        $service = app(StudentEnrollmentService::class);
        $service->attachExisting($own, $student, $director);
        $this->assertTrue($own->students()->where('students.id', $student->id)->exists());

        $this->expectException(AuthorizationException::class);
        $service->attachExisting($own, $student, $teacher);
    }

    public function test_director_permissions_are_scoped_to_school_and_teacher_cannot_delete_roster(): void
    {
        [$director, $teacher, $colegio] = $this->school();
        [$otherDirector, $otherTeacher, $otherColegio] = $this->school('Colegio Norte', 'NOR-1002');
        $student = $this->student($colegio->id, $director->id, 'Ana Ruiz');
        $course = $this->course($colegio->id, $teacher->id, 'Inglés', '3ro', 'ING-3');
        $invite = TeacherInvite::create([
            'colegio_id' => $colegio->id,
            'created_by' => $director->id,
            'name' => 'Docente Pendiente',
            'invite_code' => 'DOC-TEST',
        ]);

        $this->assertTrue(Gate::forUser($director)->allows('delete', $student));
        $this->assertTrue(Gate::forUser($director)->allows('delete', $course));
        $this->assertTrue(Gate::forUser($director)->allows('manage', $invite));
        $this->assertFalse(Gate::forUser($teacher)->allows('delete', $student));
        $this->assertFalse(Gate::forUser($teacher)->allows('delete', $course));
        $this->assertFalse(Gate::forUser($otherDirector)->allows('delete', $student));
        $this->assertFalse(Gate::forUser($otherDirector)->allows('manage', $invite));
        $this->assertFalse(Gate::forUser($otherTeacher)->allows('view', $course));
        $this->assertNotSame($colegio->id, $otherColegio->id);
    }

    public function test_teacher_chatbot_rejects_client_injected_pending_actions(): void
    {
        [$director, $teacher, $colegio] = $this->school();
        $course = $this->course($colegio->id, $teacher->id, 'Inglés', '3ro', 'ING-3');

        $response = $this->actingAs($teacher)->postJson(route('ai.command'), [
            'confirmed' => true,
            'pending_actions' => [[
                'function' => [
                    'name' => 'deleteActivities',
                    'arguments' => json_encode([
                        'course_id' => $course->id,
                        'start_date' => now()->format('Y-m-d'),
                        'end_date' => now()->addWeek()->format('Y-m-d'),
                    ]),
                ],
            ]],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', fn ($message) => str_contains((string) $message, 'No encuentro una acción pendiente'));
    }

    public function test_teacher_delete_activities_with_foreign_course_does_not_leak_course_name(): void
    {
        [$director, $teacher, $colegio] = $this->school();
        $otherTeacher = User::factory()->create([
            'role' => 'profesor',
            'colegio_id' => $colegio->id,
            'onboarding_completed' => true,
        ]);
        $own = $this->course($colegio->id, $teacher->id, 'Inglés', '3ro', 'OWN-3');
        $foreign = $this->course($colegio->id, $otherTeacher->id, 'Matemática Confidencial', '3ro', 'FOR-3');
        Activity::create([
            'teacher_id' => $teacher->id,
            'course_id' => $own->id,
            'colegio_id' => $colegio->id,
            'title' => 'Prueba de inglés',
            'due_date' => '2026-01-05',
            'type' => 'actividad',
            'max_score' => 100,
        ]);

        $response = $this->actingAs($teacher)
            ->withSession(['nova_last_delete_args' => [
                'course_id' => $foreign->id,
                'start_date' => '2026-01-01',
                'end_date' => '2026-01-10',
            ]])
            ->postJson(route('ai.command'), [
                'confirmed' => true,
            ]);

        $response->assertStatus(200);
        $message = (string) ($response->json('actions.0.message') ?? '');

        $this->assertStringNotContainsString('Matemática Confidencial', $message);
        $this->assertDatabaseHas('activities', ['teacher_id' => $teacher->id, 'course_id' => $own->id]);
    }

    public function test_teacher_delete_activities_scopes_course_name_read_to_own_course(): void
    {
        [$director, $teacher, $colegio] = $this->school();
        $own = $this->course($colegio->id, $teacher->id, 'Inglés', '3ro', 'OWN-3');
        Activity::create([
            'teacher_id' => $teacher->id,
            'course_id' => $own->id,
            'colegio_id' => $colegio->id,
            'title' => 'Prueba de inglés',
            'due_date' => '2026-01-05',
            'type' => 'actividad',
            'max_score' => 100,
        ]);

        $response = $this->actingAs($teacher)
            ->withSession(['nova_last_delete_args' => [
                'course_id' => $own->id,
                'start_date' => '2026-01-01',
                'end_date' => '2026-01-10',
            ]])
            ->postJson(route('ai.command'), [
                'confirmed' => true,
            ]);

        $response->assertStatus(200);
        $message = (string) ($response->json('actions.0.message') ?? '');

        $this->assertStringContainsString('Inglés', $message);
        $this->assertDatabaseMissing('activities', ['teacher_id' => $teacher->id, 'course_id' => $own->id]);
    }

    public function test_teacher_enroll_and_import_endpoints_are_forbidden(): void
    {
        [$director, $teacher, $colegio] = $this->school();
        $student = $this->student($colegio->id, $director->id, 'Ana Ruiz');
        $course = $this->course($colegio->id, $teacher->id, 'Inglés', '3ro', 'ING-3');

        $this->actingAs($teacher)
            ->postJson(route('teacher.api.courses.enroll', $course), ['student_id' => $student->id])
            ->assertStatus(403);

        $this->actingAs($teacher)
            ->postJson(route('api.students.create'), ['name' => 'Ana Ruiz', 'course_id' => $course->id])
            ->assertStatus(403);

        $this->actingAs($teacher)
            ->postJson(route('teacher.courses.import_students', $course), ['names' => "Ana Ruiz\nLuis Pérez"])
            ->assertStatus(403);

        $this->assertFalse($course->students()->where('students.id', $student->id)->exists());
        $this->assertDatabaseMissing('students', ['name' => 'Luis Pérez', 'colegio_id' => $colegio->id]);
    }

    public function test_teacher_chatbot_cannot_enroll_students(): void
    {
        [$director, $teacher, $colegio] = $this->school();
        $student = $this->student($colegio->id, $director->id, 'Ana Ruiz');
        $course = $this->course($colegio->id, $teacher->id, 'Inglés', '3ro', 'ING-3');

        $response = $this->actingAs($teacher)
            ->withSession(['nova_pending_actions' => [[
                'function' => [
                    'name' => 'registerStudent',
                    'arguments' => [
                        'names' => ['Ana Ruiz'],
                        'course_id' => $course->id,
                        'grade' => '3ro',
                    ],
                ],
            ]]])
            ->postJson(route('ai.command'), ['confirmed' => true]);

        $response->assertOk();
        $this->assertFalse((bool) $response->json('actions.0.success'));
        $this->assertStringContainsString('director', (string) ($response->json('actions.0.message') ?? ''));
        $this->assertFalse($course->students()->where('students.id', $student->id)->exists());
    }

    public function test_director_can_still_enroll_in_own_school_only(): void
    {
        [$director, $teacher, $colegio] = $this->school();
        [$otherDirector, $otherTeacher, $otherColegio] = $this->school('Colegio Norte', 'NOR-1002');
        $student = $this->student($colegio->id, $director->id, 'Ana Ruiz');
        $course = $this->course($colegio->id, $teacher->id, 'Inglés', '3ro', 'ING-3');
        $foreignCourse = $this->course($otherColegio->id, $otherTeacher->id, 'Inglés', '3ro', 'NOR-ING');

        $service = app(StudentEnrollmentService::class);
        $service->attachExisting($course, $student, $director);
        $this->assertTrue($course->students()->where('students.id', $student->id)->exists());

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->attachExisting($foreignCourse, $student, $director);
    }

    /**
     * @return array{0:User,1:User,2:Colegio}
     */
    private function school(string $name = 'Colegio Central', string $code = 'CEN-1001'): array
    {
        $director = User::factory()->create(['role' => 'director', 'onboarding_completed' => true]);
        $colegio = Colegio::create([
            'name' => $name,
            'invite_code' => $code,
            'codes_pin' => Colegio::hashPinFromInvite($code),
            'director_user_id' => $director->id,
        ]);
        $director->update(['colegio_id' => $colegio->id]);
        $teacher = User::factory()->create([
            'role' => 'profesor',
            'colegio_id' => $colegio->id,
            'onboarding_completed' => true,
        ]);

        return [$director->fresh(), $teacher, $colegio];
    }

    private function student(int $colegioId, int $ownerId, string $name): Student
    {
        return Student::create([
            'colegio_id' => $colegioId,
            'teacher_id' => $ownerId,
            'name' => $name,
            'grade' => '3ro',
            'section' => 'A',
            'family_code' => 'FAM-'.strtoupper(substr(md5($name), 0, 8)),
        ]);
    }

    private function course(
        int $colegioId,
        int $teacherId,
        string $subject,
        string $grade,
        string $inviteCode,
    ): Course {
        return Course::create([
            'colegio_id' => $colegioId,
            'teacher_id' => $teacherId,
            'subject_name' => $subject,
            'grade' => $grade,
            'section' => 'A',
            'school_year' => '2026-2027',
            'invite_code' => $inviteCode,
        ]);
    }
}

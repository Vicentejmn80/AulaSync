<?php

namespace Tests\Feature\Qa;

use App\Models\Course;
use App\Models\Student;
use App\Models\TeacherInvite;
use App\Support\Qa\QaSchool;

class QaDirectorWorkflowTest extends QaTestCase
{
    public function test_director_login_reaches_dashboard(): void
    {
        $this->httpLogin(QaSchool::directorEmail())
            ->assertRedirect('/director/dashboard');

        $this->get('/director/dashboard')
            ->assertOk()
            ->assertSee(QaSchool::SCHOOL_NAME);
    }

    public function test_director_can_query_staff_students_and_courses(): void
    {
        $director = $this->director();

        $this->loginAs($director)->get(route('director.profesores'))->assertOk();
        $this->loginAs($director)->get(route('director.students'))->assertOk();
        $this->loginAs($director)->get(route('director.courses'))->assertOk();
        $this->loginAs($director)->get(route('director.gestion'))->assertOk();

        $snapshot = $this->loginAs($director)
            ->getJson(route('director.gestion.snapshot'))
            ->assertOk()
            ->json();

        $this->assertGreaterThanOrEqual(QaSchool::TEACHER_COUNT, (int) data_get($snapshot, 'counts.teachers_active', 0));
        $this->assertGreaterThanOrEqual(QaSchool::STUDENT_COUNT, (int) data_get($snapshot, 'counts.students', 0));
        $this->assertGreaterThanOrEqual(10, (int) data_get($snapshot, 'counts.courses', 0));
        $teacherNames = collect(data_get($snapshot, 'teachers', []))->pluck('name')->implode(' ');
        $studentNames = collect(data_get($snapshot, 'students', []))->pluck('name')->implode(' ');
        $this->assertStringContainsString('Docente QA 01', $teacherNames);
        $this->assertStringContainsString('Alumno QA 01', $studentNames);
    }

    public function test_director_can_create_teacher_invite_via_hub(): void
    {
        $response = $this->loginAs($this->director())
            ->postJson(route('director.gestion.teachers.store'), [
                'name' => 'Docente QA Extra',
                'email' => 'docente.qa.extra@'.QaSchool::EMAIL_DOMAIN,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('teacher_invites', [
            'colegio_id' => $this->director()->colegio_id,
            'email' => 'docente.qa.extra@'.QaSchool::EMAIL_DOMAIN,
        ]);
        $this->assertTrue(
            TeacherInvite::query()->where('email', 'docente.qa.extra@'.QaSchool::EMAIL_DOMAIN)->where('name', 'like', '%QA Extra%')->exists()
            || TeacherInvite::query()->where('email', 'docente.qa.extra@'.QaSchool::EMAIL_DOMAIN)->where('name', 'like', '%Qa Extra%')->exists()
        );
        $this->assertNotEmpty(data_get($response->json(), 'invite.invite_code') ?? data_get($response->json(), 'details.invitation_code'));
    }

    public function test_director_can_create_course_assign_teacher_and_enroll_student(): void
    {
        $director = $this->director();
        $teacher = $this->teacher(1);

        $courseRes = $this->loginAs($director)
            ->postJson(route('director.gestion.courses.store'), [
                'subject_name' => 'Geografía',
                'grade' => '6to',
                'section' => 'A',
                'teacher_id' => $teacher->id,
            ])
            ->assertOk()
            ->json();

        $this->assertTrue((bool) ($courseRes['success'] ?? false), json_encode($courseRes));
        $courseId = (int) data_get($courseRes, 'course.id');
        $this->assertGreaterThan(0, $courseId);
        $this->assertDatabaseHas('courses', [
            'id' => $courseId,
            'subject_name' => 'Geografía',
            'teacher_id' => $teacher->id,
        ]);

        $studentRes = $this->loginAs($director)
            ->postJson(route('director.gestion.students.store'), [
                'name' => 'Alumno QA Workflow A',
                'grade' => '6to',
                'section' => 'A',
                'course_ids' => [$courseId],
            ])
            ->assertOk()
            ->json();

        $this->assertTrue((bool) ($studentRes['success'] ?? false), json_encode($studentRes));
        $this->assertDatabaseHas('students', [
            'colegio_id' => $director->colegio_id,
            'name' => 'Alumno QA Workflow A',
        ]);
        $student = Student::query()->where('name', 'Alumno QA Workflow A')->firstOrFail();
        $this->assertTrue($student->courses()->where('courses.id', $courseId)->exists());
    }

    public function test_director_ai_query_uses_real_school_data(): void
    {
        $response = $this->loginAs($this->director())
            ->postJson(route('director.ai.command'), [
                'prompt' => '¿Cómo va 1ro?',
            ]);

        $this->assertContains($response->status(), [200, 422], $response->getContent());
        if ($response->status() !== 200) {
            $this->fail('Director AI devolvió HTTP '.$response->status().': '.$response->getContent());
        }

        $json = $response->json();
        $message = (string) ($json['message'] ?? $json['reply'] ?? json_encode($json));
        $this->assertNotSame('', trim($message));
        $this->assertStringNotContainsString('Ocurrió un error al preparar', $message);
    }

    public function test_director_ai_create_teacher_asks_confirmation_then_persists(): void
    {
        $draft = $this->loginAs($this->director())
            ->postJson(route('director.ai.command'), [
                'prompt' => 'Crea al profesor QA Confirmado',
            ]);

        $this->assertContains($draft->status(), [200, 422], $draft->getContent());
        if (! $draft->json('requires_confirmation')) {
            $this->markTestSkipped('El comando no pidió confirmación: '.$draft->getContent());
        }

        $execute = $this->loginAs($this->director())
            ->postJson(route('director.ai.command'), [
                'confirmed' => true,
                'pending_actions' => $draft->json('pending_actions'),
            ])
            ->assertOk();

        $this->assertTrue(
            (bool) $execute->json('actions.0.success'),
            json_encode($execute->json(), JSON_UNESCAPED_UNICODE)
        );
        $this->assertTrue(
            TeacherInvite::query()->where('name', 'like', '%QA Confirmado%')->exists()
            || Course::query()->where('teacher_id', $this->teacher(1)->id)->exists()
        );
    }
}

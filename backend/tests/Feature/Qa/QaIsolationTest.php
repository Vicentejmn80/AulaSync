<?php

namespace Tests\Feature\Qa;

use App\Models\Activity;
use App\Models\Course;
use App\Models\Student;
use App\Support\Qa\QaSchool;

class QaIsolationTest extends QaTestCase
{
    public function test_parent_cannot_read_another_child_in_same_school(): void
    {
        $otherChild = Student::query()->where('name', 'Alumno QA 21')->firstOrFail();

        $this->loginAs($this->parent(1))
            ->getJson(route('representante.api.resumen', $otherChild->id))
            ->assertStatus(403);

        $this->loginAs($this->parent(1))
            ->getJson(route('representante.api.calendario', $otherChild->id))
            ->assertStatus(403);
    }

    public function test_parent_cannot_access_other_school_student(): void
    {
        $foreign = Student::query()->where('name', 'Alumno QA Other')->firstOrFail();

        $this->loginAs($this->parent(1))
            ->getJson(route('representante.api.resumen', $foreign->id))
            ->assertStatus(403);
    }

    public function test_teacher_cannot_save_attendance_on_foreign_course(): void
    {
        $foreignCourse = Course::query()->where('invite_code', 'QA-OT-MAT-1RO')->firstOrFail();
        $foreignStudent = Student::query()->where('name', 'Alumno QA Other')->firstOrFail();

        $this->loginAs($this->teacher(1))
            ->postJson(route('teacher.attendance.save'), [
                'course_id' => $foreignCourse->id,
                'date' => now()->toDateString(),
                'entries' => [[
                    'student_id' => $foreignStudent->id,
                    'status' => 'present',
                ]],
            ])
            ->assertStatus(404);
    }

    public function test_teacher_cannot_grade_activity_from_other_school(): void
    {
        $activity = Activity::query()->where('title', 'Tarea QA Other')->firstOrFail();
        $student = Student::query()->where('name', 'Alumno QA Other')->firstOrFail();

        $this->loginAs($this->teacher(1))
            ->postJson(route('teacher.grades.store', $activity), [
                'grades' => [[
                    'student_id' => $student->id,
                    'score' => 20,
                ]],
            ])
            ->assertStatus(403);
    }

    public function test_director_pages_are_forbidden_to_teacher_and_parent(): void
    {
        $this->loginAs($this->teacher(1))
            ->getJson(route('director.gestion.snapshot'))
            ->assertStatus(403);

        $this->loginAs($this->parent(1))
            ->getJson(route('director.gestion.snapshot'))
            ->assertStatus(403);

        $this->loginAs($this->teacher(1))
            ->get(route('director.dashboard'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_teacher_hub_is_forbidden_to_parent(): void
    {
        $this->loginAs($this->parent(1))
            ->get(route('teacher.hub'))
            ->assertStatus(403)
            ->assertHeaderMissing('Location');
    }

    public function test_other_school_director_does_not_see_qa_school_students(): void
    {
        $otherDirector = \App\Models\User::query()->where('email', QaSchool::otherDirectorEmail())->firstOrFail();
        $page = $this->loginAs($otherDirector)
            ->get(route('director.students'))
            ->assertOk();

        $page->assertDontSee('Alumno QA 01');
        $page->assertSee('Alumno QA Other');
    }
}

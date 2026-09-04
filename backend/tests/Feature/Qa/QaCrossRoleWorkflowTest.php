<?php

namespace Tests\Feature\Qa;

use App\Models\Activity;
use App\Models\Attendance;
use App\Models\Course;
use App\Models\Grade;
use App\Models\Student;
use App\Support\Qa\QaSchool;
use Illuminate\Support\Str;

class QaCrossRoleWorkflowTest extends QaTestCase
{
    public function test_workflow_a_director_assignment_is_visible_to_teacher_and_parent(): void
    {
        $director = $this->director();
        $teacher = $this->teacher(5);

        $courseRes = $this->loginAs($director)
            ->postJson(route('director.gestion.courses.store'), [
                'subject_name' => 'Música',
                'grade' => '6to',
                'section' => 'B',
                'teacher_id' => $teacher->id,
            ])
            ->assertOk()
            ->json();
        $courseId = (int) data_get($courseRes, 'course.id');
        $this->assertGreaterThan(0, $courseId);

        $this->loginAs($director)
            ->postJson(route('director.gestion.students.store'), [
                'name' => 'Alumno QA Cadena A',
                'grade' => '6to',
                'section' => 'B',
                'course_ids' => [$courseId],
            ])
            ->assertOk();

        $student = Student::query()->where('name', 'Alumno QA Cadena A')->firstOrFail();
        $parent = $this->parent(1);
        $parent->representedStudents()->attach($student->id, ['relationship' => 'padre']);

        $this->loginAs($teacher)
            ->get(route('teacher.courses.index'))
            ->assertOk()
            ->assertSee('Música');

        $this->assertTrue($student->courses()->where('courses.id', $courseId)->exists());

        $kids = $this->loginAs($parent)
            ->getJson(route('representante.api.estudiantes'))
            ->assertOk()
            ->json('students');
        $this->assertTrue(collect($kids)->contains(fn ($row) => ($row['name'] ?? '') === 'Alumno QA Cadena A'));
    }

    public function test_workflow_b_activity_propagates_only_to_authorized_parent(): void
    {
        $activity = Activity::query()
            ->where('title', 'like', 'Tarea QA Matemática 1ro%')
            ->firstOrFail();
        $authorized = Student::query()->where('name', 'Alumno QA 01')->firstOrFail();
        $unauthorizedStudent = Student::query()->where('name', 'Alumno QA 17')->firstOrFail();

        $calendar = $this->loginAs($this->parent(1))
            ->getJson(route('representante.api.calendario', [
                'estudiante' => $authorized->id,
                'month' => now()->format('Y-m'),
            ]))
            ->assertOk()
            ->json('calendar');
        $titles = collect($calendar['events'] ?? [])->flatten(1)->pluck('title');
        $this->assertTrue(
            $titles->contains(fn ($title) => str_contains((string) $title, 'Tarea QA Matemática 1ro')),
            json_encode($titles, JSON_UNESCAPED_UNICODE)
        );

        $otherCalendar = $this->loginAs($this->parent(9))
            ->getJson(route('representante.api.calendario', [
                'estudiante' => $unauthorizedStudent->id,
                'month' => now()->format('Y-m'),
            ]))
            ->assertOk()
            ->json('calendar');
        $otherTitles = collect($otherCalendar['events'] ?? [])->flatten(1)->pluck('title');
        $this->assertFalse(
            $otherTitles->contains(fn ($title) => str_contains((string) $title, 'Tarea QA Matemática 1ro'))
        );

        $this->loginAs($this->parent(9))
            ->postJson(route('representante.api.ia.actividad', $activity->id), [
                'estudiante_id' => $unauthorizedStudent->id,
            ])
            ->assertStatus(404);
    }

    public function test_workflow_c_attendance_and_grade_visible_only_to_authorized_parent(): void
    {
        $teacher = $this->teacher(1);
        $course = Course::query()->where('teacher_id', $teacher->id)->where('grade', '1ro')->firstOrFail();
        $student = Student::query()->where('name', 'Alumno QA 01')->firstOrFail();
        $date = now()->addDays(2)->toDateString();

        $this->loginAs($teacher)->postJson(route('teacher.attendance.save'), [
            'course_id' => $course->id,
            'date' => $date,
            'entries' => [[
                'student_id' => $student->id,
                'status' => 'tardy',
                'note' => 'QA cadena C',
                'client_uuid' => (string) Str::uuid(),
            ]],
        ])->assertOk();

        $activity = Activity::query()
            ->where('course_id', $course->id)
            ->where('type', Activity::TYPE_TAREA)
            ->firstOrFail();

        $this->loginAs($teacher)->postJson(route('teacher.grades.store', $activity), [
            'grades' => [[
                'student_id' => $student->id,
                'score' => 19,
                'feedback' => 'QA cadena C',
            ]],
        ])->assertOk();

        $this->assertDatabaseHas('attendances', [
            'student_id' => $student->id,
            'status' => Attendance::STATUS_TARDY,
        ]);
        $this->assertTrue(
            Grade::query()->where('student_id', $student->id)->where('score', 19)->exists()
        );

        $summary = $this->loginAs($this->parent(1))
            ->getJson(route('representante.api.resumen', $student->id))
            ->assertOk()
            ->getContent();
        $this->assertNotSame('', $summary);

        $this->loginAs($this->parent(2))
            ->getJson(route('representante.api.resumen', $student->id))
            ->assertStatus(403);

        $this->loginAs($this->otherParent())
            ->getJson(route('representante.api.resumen', $student->id))
            ->assertStatus(403);
    }

    public function test_workflow_d_activity_explanation_rejects_foreign_student(): void
    {
        config()->set('services.openai.key', null);
        $activity = Activity::query()
            ->where('title', 'like', 'Tarea QA Matemática 1ro%')
            ->firstOrFail();
        $student = Student::query()->where('name', 'Alumno QA 01')->firstOrFail();
        $foreign = Student::query()->where('name', 'Alumno QA Other')->firstOrFail();

        $ok = $this->loginAs($this->parent(1))
            ->postJson(route('representante.api.ia.actividad', $activity->id), [
                'estudiante_id' => $student->id,
            ])
            ->assertOk()
            ->json();
        $this->assertTrue((bool) ($ok['success'] ?? false));
        $this->assertNotEmpty($ok['content'] ?? null);

        $this->loginAs($this->parent(1))
            ->postJson(route('representante.api.ia.actividad', $activity->id), [
                'estudiante_id' => $foreign->id,
            ])
            ->assertStatus(403);

        $this->loginAs($this->otherParent())
            ->postJson(route('representante.api.ia.actividad', $activity->id), [
                'estudiante_id' => $student->id,
            ])
            ->assertStatus(403);
    }
}

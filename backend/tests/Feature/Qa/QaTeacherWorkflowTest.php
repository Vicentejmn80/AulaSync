<?php

namespace Tests\Feature\Qa;

use App\Models\Activity;
use App\Models\Attendance;
use App\Models\Course;
use App\Models\Grade;
use App\Services\ProductTelemetry;
use App\Support\Qa\QaSchool;
use Illuminate\Support\Str;
use RuntimeException;

class QaTeacherWorkflowTest extends QaTestCase
{
    public function test_teacher_login_reaches_hub(): void
    {
        $this->httpLogin(QaSchool::teacherEmail(1))
            ->assertRedirect('/teacher/hub');

        $this->get('/teacher/hub')
            ->assertOk();
    }

    public function test_teacher_sees_own_courses_and_students(): void
    {
        $teacher = $this->teacher(1);

        $this->loginAs($teacher)->get(route('teacher.courses.index'))->assertOk()->assertSee('Matemática');
        $this->loginAs($teacher)->get(route('teacher.activities.index'))->assertOk();
        $this->loginAs($teacher)->get(route('teacher.evaluations.index'))->assertOk();
        $this->loginAs($teacher)->get(route('teacher.attendance.index'))->assertOk();
    }

    public function test_teacher_can_create_activity_and_it_persists(): void
    {
        $teacher = $this->teacher(1);
        $course = Course::query()->where('teacher_id', $teacher->id)->firstOrFail();

        $this->loginAs($teacher)
            ->post(route('teacher.activities.store'), [
                'course_id' => $course->id,
                'title' => 'Tarea QA Live',
                'description' => 'Creada por el workflow QA del docente.',
                'type' => 'actividad',
                'is_homework' => 1,
                'max_score' => 20,
                'weight_percentage' => 10,
                'due_date' => now()->addDays(4)->toDateString(),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('activities', [
            'teacher_id' => $teacher->id,
            'course_id' => $course->id,
            'title' => 'Tarea QA Live',
        ]);
    }

    public function test_teacher_can_create_evaluation_and_it_persists(): void
    {
        $teacher = $this->teacher(1);
        $course = Course::query()->where('teacher_id', $teacher->id)->firstOrFail();

        $res = $this->loginAs($teacher)
            ->postJson(route('teacher.evaluations.store'), [
                'title' => 'Parcial QA Live',
                'topic' => 'Números enteros',
                'course_id' => $course->id,
                'status' => 'published',
                'mode' => 'digital',
                'weight_percentage' => 15,
                'scheduled_at' => now()->addDays(10)->toDateString(),
                'questions' => [
                    [
                        'type' => 'open',
                        'text' => '¿Qué es un número entero?',
                        'points' => 5,
                    ],
                ],
            ])
            ->assertOk()
            ->json();

        $this->assertTrue((bool) ($res['success'] ?? false), json_encode($res));
        $this->assertDatabaseHas('evaluations', [
            'teacher_id' => $teacher->id,
            'title' => 'Parcial QA Live',
        ]);
    }

    public function test_teacher_can_save_attendance_and_grades(): void
    {
        $teacher = $this->teacher(1);
        $course = Course::query()->where('teacher_id', $teacher->id)->firstOrFail();
        $student = $course->students()->firstOrFail();
        $date = now()->addDay()->toDateString();

        $this->loginAs($teacher)
            ->postJson(route('teacher.attendance.save'), [
                'course_id' => $course->id,
                'date' => $date,
                'entries' => [[
                    'student_id' => $student->id,
                    'status' => 'absent',
                    'note' => 'QA live absence',
                    'client_uuid' => (string) Str::uuid(),
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('attendances', [
            'course_id' => $course->id,
            'student_id' => $student->id,
            'status' => Attendance::STATUS_ABSENT,
        ]);

        $activity = Activity::query()
            ->where('teacher_id', $teacher->id)
            ->where('course_id', $course->id)
            ->where('type', '!=', Activity::TYPE_CLASE)
            ->firstOrFail();

        $this->loginAs($teacher)
            ->postJson(route('teacher.grades.store', $activity), [
                'grades' => [[
                    'student_id' => $student->id,
                    'score' => 16,
                    'feedback' => 'QA live grade',
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertTrue(
            Grade::query()
                ->where('activity_id', $activity->id)
                ->where('student_id', $student->id)
                ->where('score', 16)
                ->exists()
        );
    }

    public function test_creating_activity_or_evaluation_does_not_return_500(): void
    {
        $teacher = $this->teacher(1);
        $course = Course::query()->where('teacher_id', $teacher->id)->firstOrFail();

        $this->loginAs($teacher)
            ->post(route('teacher.activities.store'), [
                'course_id' => $course->id,
                'title' => 'Tarea QA No 500',
                'description' => 'No debe devolver 500.',
                'type' => 'actividad',
                'is_homework' => 1,
                'max_score' => 20,
                'weight_percentage' => 10,
                'due_date' => now()->addDays(5)->toDateString(),
            ])
            ->assertRedirect(route('teacher.activities.index'))
            ->assertStatus(302);

        $this->assertDatabaseHas('activities', [
            'teacher_id' => $teacher->id,
            'title' => 'Tarea QA No 500',
        ]);

        $res = $this->loginAs($teacher)
            ->postJson(route('teacher.evaluations.store'), [
                'title' => 'Parcial QA No 500',
                'topic' => 'Números enteros',
                'course_id' => $course->id,
                'status' => 'published',
                'mode' => 'digital',
                'weight_percentage' => 15,
                'scheduled_at' => now()->addDays(11)->toDateString(),
                'questions' => [
                    [
                        'type' => 'open',
                        'text' => '¿Qué es un número entero?',
                        'points' => 5,
                    ],
                ],
            ]);

        $this->assertNotEquals(500, $res->status(), $res->getContent());
        $res->assertOk();
        $this->assertTrue((bool) $res->json('success'), $res->getContent());
        $this->assertDatabaseHas('evaluations', [
            'teacher_id' => $teacher->id,
            'title' => 'Parcial QA No 500',
        ]);
    }

    public function test_activity_create_rolls_back_when_post_insert_fails(): void
    {
        $this->mock(ProductTelemetry::class, function ($mock) {
            $mock->shouldReceive('record')->once()->andThrow(new RuntimeException('forced telemetry failure'));
        });

        $teacher = $this->teacher(1);
        $course = Course::query()->where('teacher_id', $teacher->id)->firstOrFail();

        $this->loginAs($teacher)
            ->post(route('teacher.activities.store'), [
                'course_id' => $course->id,
                'title' => 'Tarea QA Rollback',
                'description' => 'Debe revertirse.',
                'type' => 'actividad',
                'is_homework' => 1,
                'max_score' => 20,
                'weight_percentage' => 10,
                'due_date' => now()->addDays(6)->toDateString(),
            ])
            ->assertStatus(500);

        $this->assertDatabaseMissing('activities', [
            'teacher_id' => $teacher->id,
            'title' => 'Tarea QA Rollback',
        ]);
    }

    public function test_evaluation_create_rolls_back_when_post_insert_fails(): void
    {
        $this->mock(ProductTelemetry::class, function ($mock) {
            $mock->shouldReceive('record')->once()->andThrow(new RuntimeException('forced telemetry failure'));
        });

        $teacher = $this->teacher(1);
        $course = Course::query()->where('teacher_id', $teacher->id)->firstOrFail();

        $this->loginAs($teacher)
            ->postJson(route('teacher.evaluations.store'), [
                'title' => 'Parcial QA Rollback',
                'topic' => 'Números enteros',
                'course_id' => $course->id,
                'status' => 'published',
                'mode' => 'digital',
                'weight_percentage' => 15,
                'scheduled_at' => now()->addDays(12)->toDateString(),
                'questions' => [
                    [
                        'type' => 'open',
                        'text' => '¿Qué es un número entero?',
                        'points' => 5,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing('evaluations', [
            'teacher_id' => $teacher->id,
            'title' => 'Parcial QA Rollback',
        ]);
    }
}

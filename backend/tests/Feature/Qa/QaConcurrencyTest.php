<?php

namespace Tests\Feature\Qa;

use App\Models\Attendance;
use App\Models\Course;
use App\Models\Student;
use Illuminate\Support\Str;

class QaConcurrencyTest extends QaTestCase
{
    public function test_five_teachers_can_save_attendance_without_clobbering_each_other(): void
    {
        $date = now()->addDays(3)->toDateString();
        $saved = [];

        for ($i = 1; $i <= 5; $i++) {
            $teacher = $this->teacher($i);
            $course = Course::query()->where('teacher_id', $teacher->id)->firstOrFail();
            $student = $course->students()->orderBy('students.id')->firstOrFail();
            $status = $i % 2 === 0 ? Attendance::STATUS_ABSENT : Attendance::STATUS_PRESENT;

            $this->loginAs($teacher)
                ->postJson(route('teacher.attendance.save'), [
                    'course_id' => $course->id,
                    'date' => $date,
                    'entries' => [[
                        'student_id' => $student->id,
                        'status' => $status,
                        'note' => 'QA concurrent teacher '.$i,
                        'client_uuid' => (string) Str::uuid(),
                    ]],
                ])
                ->assertOk()
                ->assertJsonPath('success', true);

            $saved[] = [$course->id, $student->id, $status];
        }

        foreach ($saved as [$courseId, $studentId, $status]) {
            $this->assertDatabaseHas('attendances', [
                'course_id' => $courseId,
                'student_id' => $studentId,
                'status' => $status,
            ]);
        }

        $this->assertSame(
            5,
            Attendance::query()->whereDate('attended_on', $date)->where('note', 'like', 'QA concurrent teacher%')->count()
        );
    }

    public function test_ten_parents_can_query_their_own_children_without_leaking(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $parent = $this->parent($i);
            $own = Student::query()->where('name', 'Alumno QA '.str_pad((string) ((($i - 1) * 2) + 1), 2, '0', STR_PAD_LEFT))->firstOrFail();
            $foreign = Student::query()->where('name', 'Alumno QA Other')->firstOrFail();

            $kids = $this->loginAs($parent)
                ->getJson(route('representante.api.estudiantes'))
                ->assertOk()
                ->json('students');

            $names = collect($kids)->pluck('name');
            $this->assertTrue($names->contains($own->name), 'Parent '.$i.' missing own child');
            $this->assertFalse($names->contains('Alumno QA Other'));

            $this->loginAs($parent)
                ->getJson(route('representante.api.resumen', $own->id))
                ->assertOk();

            $this->loginAs($parent)
                ->getJson(route('representante.api.resumen', $foreign->id))
                ->assertStatus(403);
        }
    }
}

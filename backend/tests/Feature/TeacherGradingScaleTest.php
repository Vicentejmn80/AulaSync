<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Colegio;
use App\Models\Course;
use App\Models\Grade;
use App\Models\Student;
use App\Models\User;
use App\Support\GradingScale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherGradingScaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_zero_ten_and_twenty_save_even_if_activity_max_is_nine(): void
    {
        [$teacher, $course, $activity, $students] = $this->classroom();
        $activity->update(['max_score' => 9]);

        foreach ([
            [$students[0], 0],
            [$students[1], 10],
            [$students[2], 20],
        ] as [$student, $score]) {
            $this->actingAs($teacher)
                ->postJson(route('teacher.grades.quick_store', $activity), [
                    'student_id' => $student->id,
                    'score' => $score,
                ])
                ->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonPath('numeric_score', $score);
        }

        $this->assertSame(20, $activity->fresh()->max_score);
        $this->assertSame(0.0, (float) Grade::query()->where('student_id', $students[0]->id)->value('score'));
        $this->assertSame(10.0, (float) Grade::query()->where('student_id', $students[1]->id)->value('score'));
        $this->assertSame(20.0, (float) Grade::query()->where('student_id', $students[2]->id)->value('score'));
    }

    public function test_letter_scale_stores_numeric_averages(): void
    {
        [$teacher, $course, $activity, $students] = $this->classroom();

        $this->actingAs($teacher)
            ->patchJson(route('teacher.api.course.grading_scale', $course), [
                'grading_scale' => 'A-F',
            ])
            ->assertOk()
            ->assertJsonPath('grading_scale', 'A-F')
            ->assertJsonPath('grading_scale_max', 20);

        $this->actingAs($teacher)
            ->postJson(route('teacher.grades.quick_store', $activity), [
                'student_id' => $students[0]->id,
                'score' => 'A',
            ])
            ->assertOk()
            ->assertJsonPath('score', 'A')
            ->assertJsonPath('numeric_score', 20);

        $this->actingAs($teacher)
            ->postJson(route('teacher.grades.quick_store', $activity), [
                'student_id' => $students[1]->id,
                'score' => 'F',
            ])
            ->assertOk()
            ->assertJsonPath('numeric_score', 0);

        $this->assertSame(20.0, (float) Grade::query()->where('student_id', $students[0]->id)->value('score'));
        $this->assertSame(0.0, (float) Grade::query()->where('student_id', $students[1]->id)->value('score'));
        $this->assertSame('A', GradingScale::display('A-F', 20));
        $this->assertSame('F', GradingScale::display('A-F', 0));
    }

    /**
     * @return array{0:User,1:Course,2:Activity,3:array<int,Student>}
     */
    private function classroom(): array
    {
        $colegio = Colegio::create([
            'name' => 'Colegio Notas',
            'invite_code' => 'NOT-1001',
            'codes_pin' => Colegio::hashPinFromInvite('NOT-1001'),
        ]);

        $teacher = User::factory()->create([
            'role' => 'profesor',
            'colegio_id' => $colegio->id,
            'onboarding_completed' => true,
            'name' => 'Prof. Notas',
        ]);

        $course = Course::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => $teacher->id,
            'subject_name' => 'Matemática',
            'grade' => '3ro',
            'section' => 'A',
            'school_year' => '2026-2027',
            'invite_code' => 'MAT-NOTAS',
            'grading_scale' => '1-20',
        ]);

        $activity = Activity::create([
            'teacher_id' => $teacher->id,
            'course_id' => $course->id,
            'colegio_id' => $colegio->id,
            'title' => 'Quiz 1',
            'type' => 'actividad',
            'max_score' => 9,
            'weight_percentage' => 25,
        ]);

        $students = [];
        foreach (['Ángel Marín', 'Carlos Gutiérrez', 'Jason Hernández'] as $name) {
            $student = Student::create([
                'colegio_id' => $colegio->id,
                'teacher_id' => $teacher->id,
                'name' => $name,
                'grade' => '3ro',
                'section' => 'A',
                'family_code' => 'FAM-'.strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $name), 0, 6)).uniqid(),
            ]);
            $course->students()->attach($student->id);
            $students[] = $student;
        }

        return [$teacher, $course, $activity, $students];
    }
}

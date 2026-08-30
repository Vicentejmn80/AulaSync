<?php

namespace Tests\Feature;

use App\Models\Colegio;
use App\Models\Course;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherRosterSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_gestion_student_without_course_appears_on_teacher_hub(): void
    {
        [$director, $teacher, $course] = $this->sixthGradeClassroom();

        $this->actingAs($director)
            ->postJson(route('director.gestion.students.store'), [
                'name' => 'Camila Rivas',
                'grade' => '6to',
            ])
            ->assertOk()
            ->assertJsonPath('student.name', 'Camila Rivas');

        $this->assertTrue($course->fresh()->students()->where('name', 'Camila Rivas')->exists());

        $this->actingAs($teacher)
            ->getJson(route('teacher.api.courses'))
            ->assertOk()
            ->assertJsonPath('0.students_count', 1);

        $this->actingAs($teacher)
            ->getJson(route('teacher.api.course', $course))
            ->assertOk()
            ->assertJsonPath('students_count', 1)
            ->assertJsonPath('students.0.name', 'Camila Rivas');
    }

    public function test_teacher_hub_heals_seven_existing_sixth_grade_students(): void
    {
        [$director, $teacher, $course] = $this->sixthGradeClassroom();

        foreach (['Ana', 'Bruno', 'Carla', 'Diego', 'Elena', 'Fabio', 'Gina'] as $index => $name) {
            Student::create([
                'colegio_id' => $director->colegio_id,
                'teacher_id' => $director->id,
                'name' => $name.' Soto',
                'grade' => $index % 2 === 0 ? '6to' : 'sexto grado',
                'section' => 'A',
                'family_code' => 'FAM-6'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
            ]);
        }

        $this->assertSame(0, $course->fresh()->students()->count());

        $this->actingAs($teacher)
            ->getJson(route('teacher.api.stats'))
            ->assertOk()
            ->assertJsonPath('total_students', 7);

        $this->actingAs($teacher)
            ->getJson(route('teacher.api.course', $course))
            ->assertOk()
            ->assertJsonPath('students_count', 7);

        $this->assertSame(7, $course->fresh()->students()->count());
    }

    public function test_new_course_pulls_existing_sixth_grade_roster(): void
    {
        [$director, $teacher] = $this->school();

        foreach (['Hugo Diaz', 'Irene Paz'] as $name) {
            Student::create([
                'colegio_id' => $director->colegio_id,
                'teacher_id' => $director->id,
                'name' => $name,
                'grade' => '6to',
                'family_code' => 'FAM-'.substr(md5($name), 0, 6),
            ]);
        }

        $this->actingAs($director)
            ->postJson(route('director.gestion.courses.store'), [
                'subject_name' => 'Biología',
                'grade' => '6to',
                'teacher_id' => $teacher->id,
            ])
            ->assertOk();

        $course = Course::query()
            ->where('colegio_id', $director->colegio_id)
            ->where('subject_name', 'Biología')
            ->first();

        $this->assertNotNull($course);
        $this->assertSame($teacher->id, $course->teacher_id);
        $this->assertSame(2, $course->students()->count());
    }

    /**
     * @return array{0:User,1:User,2:Course}
     */
    private function sixthGradeClassroom(): array
    {
        [$director, $teacher, $colegio] = $this->school();
        $course = Course::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => $teacher->id,
            'subject_name' => 'Biología',
            'grade' => '6to',
            'section' => 'A',
            'school_year' => '2026-2027',
            'invite_code' => 'BIO-6A',
        ]);

        return [$director, $teacher, $course];
    }

    /**
     * @return array{0:User,1:User,2:Colegio}
     */
    private function school(): array
    {
        $director = User::factory()->create([
            'role' => 'director',
            'onboarding_completed' => true,
        ]);
        $colegio = Colegio::create([
            'name' => 'Colegio Sexto',
            'invite_code' => 'SEX-1001',
            'codes_pin' => Colegio::hashPinFromInvite('SEX-1001'),
            'director_user_id' => $director->id,
        ]);
        $director->update(['colegio_id' => $colegio->id]);
        $teacher = User::factory()->create([
            'role' => 'profesor',
            'colegio_id' => $colegio->id,
            'onboarding_completed' => true,
            'name' => 'Profesor Sexto',
        ]);

        return [$director->fresh(), $teacher, $colegio];
    }
}

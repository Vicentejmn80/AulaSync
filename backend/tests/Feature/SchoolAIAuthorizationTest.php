<?php

namespace Tests\Feature;

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

    public function test_teacher_can_enroll_existing_student_only_in_own_course(): void
    {
        [$director, $teacher, $colegio] = $this->school();
        $otherTeacher = User::factory()->create([
            'role' => 'profesor',
            'colegio_id' => $colegio->id,
            'onboarding_completed' => true,
        ]);
        $student = $this->student($colegio->id, $director->id, 'Ana Ruiz');
        $own = $this->course($colegio->id, $teacher->id, 'Inglés', '3ro', 'OWN-3');
        $other = $this->course($colegio->id, $otherTeacher->id, 'Matemática', '3ro', 'OTHER-3');

        $service = app(StudentEnrollmentService::class);
        $service->attachExisting($own, $student, $teacher);
        $this->assertTrue($own->students()->where('students.id', $student->id)->exists());

        $this->expectException(AuthorizationException::class);
        $service->attachExisting($other, $student, $teacher);
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

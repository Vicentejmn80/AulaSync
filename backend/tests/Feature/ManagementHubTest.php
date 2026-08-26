<?php

namespace Tests\Feature;

use App\Models\Colegio;
use App\Models\Course;
use App\Models\Student;
use App\Models\TeacherInvite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagementHubTest extends TestCase
{
    use RefreshDatabase;

    public function test_director_can_open_gestion_hub(): void
    {
        [$director] = $this->directorContext();

        $this->actingAs($director)
            ->get(route('director.gestion'))
            ->assertOk()
            ->assertSee('Gestión')
            ->assertSee('Resumen')
            ->assertSee('Profesores')
            ->assertSee('Alumnos')
            ->assertSee('1er grado')
            ->assertSee('Seleccionar todo');
    }

    public function test_snapshot_returns_counts_and_lists(): void
    {
        [$director, $colegio] = $this->directorContext();
        $teacher = User::factory()->create([
            'role' => 'profesor',
            'colegio_id' => $colegio->id,
            'name' => 'Ana Rojas',
            'onboarding_completed' => true,
        ]);
        Course::create([
            'teacher_id' => $teacher->id,
            'colegio_id' => $colegio->id,
            'subject_name' => 'Matemática',
            'grade' => '1ro',
            'invite_code' => 'CUR-MAT-1',
        ]);
        Student::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => $teacher->id,
            'name' => 'Luis Perez',
            'grade' => '1ro',
        ]);

        $this->actingAs($director)
            ->getJson(route('director.gestion.snapshot'))
            ->assertOk()
            ->assertJsonPath('counts.teachers_active', 1)
            ->assertJsonPath('counts.students', 1)
            ->assertJsonPath('counts.courses', 1)
            ->assertJsonPath('teachers.0.name', 'Ana Rojas')
            ->assertJsonPath('students.0.name', 'Luis Perez');
    }

    public function test_hub_can_invite_teacher_and_create_student_json(): void
    {
        [$director, $colegio] = $this->directorContext();

        $invite = $this->actingAs($director)
            ->postJson(route('director.gestion.teachers.store'), [
                'name' => 'Carlos Baute',
                'subject_name' => 'Música',
                'grades' => ['1ro', '2do'],
            ])
            ->assertOk()
            ->json();

        $this->assertTrue($invite['success']);
        $this->assertDatabaseHas('teacher_invites', [
            'colegio_id' => $colegio->id,
            'name' => 'Carlos Baute',
        ]);
        $this->assertSame(2, Course::where('colegio_id', $colegio->id)->where('subject_name', 'Música')->count());

        $this->actingAs($director)
            ->postJson(route('director.gestion.students.store'), [
                'name' => 'Marta Gomez',
                'grade' => '2do',
            ])
            ->assertOk()
            ->assertJsonPath('student.name', 'Marta Gomez');
    }

    public function test_assign_courses_to_teacher_and_update_student(): void
    {
        [$director, $colegio] = $this->directorContext();
        $teacher = User::factory()->create([
            'role' => 'profesor',
            'colegio_id' => $colegio->id,
            'name' => 'Vicente Maduro',
            'onboarding_completed' => true,
        ]);
        $course = Course::create([
            'colegio_id' => $colegio->id,
            'subject_name' => 'Inglés',
            'grade' => '3ro',
            'invite_code' => 'CUR-ING-3',
        ]);
        $student = Student::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => $teacher->id,
            'name' => 'Pedro Ruiz',
            'grade' => '1ro',
        ]);

        $this->actingAs($director)
            ->postJson(route('director.gestion.assign'), [
                'teacher_id' => $teacher->id,
                'course_ids' => [$course->id],
            ])
            ->assertOk();

        $this->assertSame($teacher->id, $course->fresh()->teacher_id);

        $this->actingAs($director)
            ->patchJson(route('director.gestion.students.update', $student), [
                'grade' => '3ro',
            ])
            ->assertOk();

        $this->assertSame('3ro', $student->fresh()->grade);
    }

    public function test_json_delete_teacher_invite(): void
    {
        [$director, $colegio] = $this->directorContext();
        $invite = TeacherInvite::create([
            'colegio_id' => $colegio->id,
            'created_by' => $director->id,
            'name' => 'Temporal',
            'invite_code' => 'DOC-TMP12',
            'expires_at' => now()->addDays(10),
        ]);

        $this->actingAs($director)
            ->deleteJson(route('director.profesores.invite.destroy', $invite))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('teacher_invites', ['id' => $invite->id]);
    }

    public function test_deleting_teacher_leaves_courses_orphan(): void
    {
        [$director, $colegio] = $this->directorContext();
        $teacher = User::factory()->create([
            'role' => 'profesor',
            'colegio_id' => $colegio->id,
            'name' => 'Vicente Maduro',
            'onboarding_completed' => true,
        ]);
        $course = Course::create([
            'teacher_id' => $teacher->id,
            'colegio_id' => $colegio->id,
            'subject_name' => 'Biología',
            'grade' => '1er grado',
            'invite_code' => 'CUR-BIO-1',
        ]);

        $this->actingAs($director)
            ->deleteJson(route('director.profesores.destroy', $teacher))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('users', ['id' => $teacher->id]);
        $this->assertDatabaseHas('courses', [
            'id' => $course->id,
            'teacher_id' => null,
            'subject_name' => 'Biología',
        ]);

        $this->actingAs($director)
            ->getJson(route('director.gestion.snapshot'))
            ->assertOk()
            ->assertJsonPath('courses.0.orphan', true);
    }

    public function test_hub_bulk_destroy_and_destroy_subject(): void
    {
        [$director, $colegio] = $this->directorContext();
        $teacher = User::factory()->create([
            'role' => 'profesor',
            'colegio_id' => $colegio->id,
            'name' => 'Ana Rojas',
            'onboarding_completed' => true,
        ]);
        $keep = Course::create([
            'teacher_id' => $teacher->id,
            'colegio_id' => $colegio->id,
            'subject_name' => 'Matemática',
            'grade' => '2do',
            'invite_code' => 'CUR-MAT-2',
        ]);
        Course::create([
            'teacher_id' => $teacher->id,
            'colegio_id' => $colegio->id,
            'subject_name' => 'Biología',
            'grade' => '1ro',
            'invite_code' => 'CUR-BIO-A',
        ]);
        Course::create([
            'teacher_id' => $teacher->id,
            'colegio_id' => $colegio->id,
            'subject_name' => 'Biología',
            'grade' => '1er grado',
            'section' => 'B',
            'invite_code' => 'CUR-BIO-B',
        ]);
        $student = Student::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => $teacher->id,
            'name' => 'Luis Perez',
            'grade' => '1ro',
        ]);

        $this->actingAs($director)
            ->postJson(route('director.gestion.courses.destroy-subject'), [
                'subject_name' => 'Biología',
                'grade' => '1ro',
            ])
            ->assertOk()
            ->assertJsonPath('deleted', 2);

        $this->assertDatabaseMissing('courses', ['subject_name' => 'Biología', 'colegio_id' => $colegio->id]);
        $this->assertDatabaseHas('courses', ['id' => $keep->id]);

        $this->actingAs($director)
            ->postJson(route('director.gestion.bulk-destroy'), [
                'teachers' => [$teacher->id],
                'students' => [$student->id],
            ])
            ->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $teacher->id]);
        $this->assertDatabaseMissing('students', ['id' => $student->id]);
        $this->assertDatabaseHas('courses', [
            'id' => $keep->id,
            'teacher_id' => null,
        ]);
    }

    public function test_dashboard_splits_resumen_and_gestion_without_setup_card_links(): void
    {
        [$director] = $this->directorContext();

        $this->actingAs($director)
            ->get(route('director.dashboard'))
            ->assertOk()
            ->assertSee('Resumen')
            ->assertSee('Gestión')
            ->assertSee('Ir a Gestión')
            ->assertDontSee('gestion?panel=', false);
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
            'name' => 'Colegio Hub',
            'invite_code' => 'HUB-1001',
            'codes_pin' => Colegio::hashPinFromInvite('HUB-1001'),
            'director_user_id' => $director->id,
        ]);
        $director->update(['colegio_id' => $colegio->id]);

        return [$director->fresh(), $colegio];
    }
}

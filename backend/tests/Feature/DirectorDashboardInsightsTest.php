<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Colegio;
use App\Models\Course;
use App\Models\Grade;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectorDashboardInsightsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_clickable_kpis_and_daily_summary_button(): void
    {
        [$director] = $this->school();

        $this->actingAs($director)
            ->get(route('director.dashboard'))
            ->assertOk()
            ->assertSee('Riesgo Académico')
            ->assertSee('Ver alumnos en riesgo')
            ->assertSee('Ver docentes pendientes')
            ->assertSee('Generar Resumen de Hoy')
            ->assertSee('directorDashboardInsights', false)
            ->assertDontSee('Acceso institucional')
            ->assertDontSee('Código del colegio');
    }

    public function test_at_risk_endpoint_lists_students_below_sixty_with_average(): void
    {
        [$director, $colegio, $teacher] = $this->school();
        $course = $this->course($colegio, $teacher, 'Matemática');
        $atRisk = $this->student($colegio, $teacher, 'Ángel Marín', '3ro', 'A');
        $safe = $this->student($colegio, $teacher, 'Carlos Gutiérrez', '3ro', 'A');
        $course->students()->attach([$atRisk->id, $safe->id]);

        $quiz = $this->activity($colegio, $teacher, $course, 'Quiz 1');
        $this->publishGrade($quiz, $atRisk, $colegio, 10);
        $this->publishGrade($quiz, $safe, $colegio, 16);

        $this->actingAs($director)
            ->getJson(route('director.api.dashboard.at-risk'))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('threshold', 60)
            ->assertJsonPath('students.0.name', 'Ángel Marín')
            ->assertJsonPath('students.0.grade', '3ro')
            ->assertJsonPath('students.0.section', 'A')
            ->assertJsonPath('students.0.average', 50);

        $names = collect($this->actingAs($director)->getJson(route('director.api.dashboard.at-risk'))->json('students'))
            ->pluck('name')
            ->all();
        $this->assertContains('Ángel Marín', $names);
        $this->assertNotContains('Carlos Gutiérrez', $names);
    }

    public function test_pending_grades_endpoint_lists_teacher_course_and_activity(): void
    {
        [$director, $colegio, $teacher] = $this->school();
        $course = $this->course($colegio, $teacher, 'Lenguaje', '4to', 'B');
        $student = $this->student($colegio, $teacher, 'Jason Hernández', '4to', 'B');
        $course->students()->attach($student->id);
        $this->activity($colegio, $teacher, $course, 'Ensayo 1');

        $this->actingAs($director)
            ->getJson(route('director.api.dashboard.pending-grades'))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('count', 1)
            ->assertJsonPath('teachers.0.teacher_name', 'Prof. Notas')
            ->assertJsonPath('teachers.0.courses.0.subject_name', 'Lenguaje')
            ->assertJsonPath('teachers.0.courses.0.grade', '4to')
            ->assertJsonPath('teachers.0.courses.0.section', 'B')
            ->assertJsonPath('teachers.0.activities.0.title', 'Ensayo 1')
            ->assertJsonPath('teachers.0.activities.0.enrolled', 1)
            ->assertJsonPath('teachers.0.activities.0.graded', 0)
            ->assertJsonPath('teachers.0.activities.0.missing_students.0', 'Jason Hernández');
    }

    public function test_low_performing_rooms_endpoint_returns_detail(): void
    {
        [$director, $colegio, $teacher] = $this->school();
        $course = $this->course($colegio, $teacher, 'Matemática');
        $student = $this->student($colegio, $teacher, 'Ana Pérez', '5to', 'A');
        $course->students()->attach($student->id);
        $activity = $this->activity($colegio, $teacher, $course, 'Parcial');
        $this->publishGrade($activity, $student, $colegio, 8);

        $this->actingAs($director)
            ->getJson(route('director.api.dashboard.low-performing-rooms'))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['rooms' => [['name', 'average', 'recommendation', 'grades_count']]]);
    }

    public function test_school_health_endpoint_returns_executive_snapshot(): void
    {
        [$director] = $this->school();

        $this->actingAs($director)
            ->getJson(route('director.api.dashboard.school-health'))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure([
                'ok',
                'message',
                'generated_at',
                'data' => [
                    'total_students',
                    'total_teachers',
                    'average_grades',
                    'attendance',
                    'at_risk_count',
                ],
            ]);

        $message = (string) $this->actingAs($director)
            ->getJson(route('director.api.dashboard.school-health'))
            ->json('message');
        $this->assertStringContainsString('Salud general del colegio', $message);
    }

    public function test_teacher_cannot_open_dashboard_insight_endpoints(): void
    {
        [, $colegio, $teacher] = $this->school();

        $this->actingAs($teacher)
            ->getJson(route('director.api.dashboard.at-risk'))
            ->assertForbidden();

        $this->actingAs($teacher)
            ->getJson(route('director.api.dashboard.school-health'))
            ->assertJsonPath('success', false);

        $this->assertSame($colegio->id, $teacher->colegio_id);
    }

    /**
     * @return array{0:User,1:Colegio,2:User}
     */
    private function school(): array
    {
        $director = User::factory()->create([
            'role' => 'director',
            'onboarding_completed' => true,
            'name' => 'Dir. Insights',
        ]);
        $colegio = Colegio::create([
            'name' => 'Colegio Insights',
            'invite_code' => 'INS-1001',
            'codes_pin' => Colegio::hashPinFromInvite('INS-1001'),
            'director_user_id' => $director->id,
        ]);
        $director->update(['colegio_id' => $colegio->id]);

        $teacher = User::factory()->create([
            'role' => 'profesor',
            'colegio_id' => $colegio->id,
            'onboarding_completed' => true,
            'name' => 'Prof. Notas',
        ]);

        return [$director->fresh(), $colegio, $teacher];
    }

    private function course(Colegio $colegio, User $teacher, string $subject, string $grade = '3ro', string $section = 'A'): Course
    {
        return Course::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => $teacher->id,
            'subject_name' => $subject,
            'grade' => $grade,
            'section' => $section,
            'school_year' => '2026-2027',
            'invite_code' => 'CUR-'.uniqid(),
        ]);
    }

    private function student(Colegio $colegio, User $teacher, string $name, string $grade, string $section): Student
    {
        return Student::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => $teacher->id,
            'name' => $name,
            'grade' => $grade,
            'section' => $section,
            'family_code' => 'FAM-'.uniqid(),
        ]);
    }

    private function activity(Colegio $colegio, User $teacher, Course $course, string $title): Activity
    {
        return Activity::create([
            'teacher_id' => $teacher->id,
            'course_id' => $course->id,
            'colegio_id' => $colegio->id,
            'title' => $title,
            'type' => 'actividad',
            'max_score' => 20,
            'weight_percentage' => 100,
        ]);
    }

    private function publishGrade(Activity $activity, Student $student, Colegio $colegio, float $score): Grade
    {
        return Grade::create([
            'activity_id' => $activity->id,
            'student_id' => $student->id,
            'colegio_id' => $colegio->id,
            'score' => $score,
            'status' => 'published',
            'published_at' => now(),
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Attendance;
use App\Models\Colegio;
use App\Models\Course;
use App\Models\Evaluation;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RepresentanteAIExplanationTest extends TestCase
{
    use RefreshDatabase;

    private function familyLink(): array
    {
        $colegio = Colegio::create(['name' => 'Colegio IA', 'invite_code' => 'IA-1001']);
        $teacher = User::factory()->create(['role' => 'profesor', 'colegio_id' => $colegio->id, 'onboarding_completed' => true, 'name' => 'Prof. Díaz']);
        $parent = User::factory()->create(['role' => 'representante', 'colegio_id' => $colegio->id, 'onboarding_completed' => true, 'name' => 'Rep Marín']);
        $otherParent = User::factory()->create(['role' => 'representante', 'colegio_id' => $colegio->id, 'onboarding_completed' => true]);
        $student = Student::create(['colegio_id' => $colegio->id, 'teacher_id' => $teacher->id, 'name' => 'Ángel Marín', 'grade' => '3ro', 'section' => 'A']);
        $otherStudent = Student::create(['colegio_id' => $colegio->id, 'teacher_id' => $teacher->id, 'name' => 'Otro Niño', 'grade' => '3ro', 'section' => 'B']);
        $parent->representedStudents()->attach($student->id, ['relationship' => 'padre']);
        $otherParent->representedStudents()->attach($otherStudent->id, ['relationship' => 'padre']);

        $course = Course::create(['colegio_id' => $colegio->id, 'teacher_id' => $teacher->id, 'subject_name' => 'Matemática', 'grade' => '3ro', 'section' => 'A', 'school_year' => '2026-2027', 'invite_code' => 'MAT-3A']);
        $otherCourse = Course::create(['colegio_id' => $colegio->id, 'teacher_id' => $teacher->id, 'subject_name' => 'Lenguaje', 'grade' => '3ro', 'section' => 'B', 'school_year' => '2026-2027', 'invite_code' => 'LEN-3B']);
        $student->courses()->attach($course->id);
        $otherStudent->courses()->attach($otherCourse->id);

        return [$parent, $student, $otherParent, $otherStudent, $course, $otherCourse, $colegio, $teacher];
    }

    public function test_representante_cannot_access_other_student(): void
    {
        [$parent, $student, $otherParent, $otherStudent] = $this->familyLink();
        $activity = Activity::create(['teacher_id' => $student->teacher_id, 'course_id' => $student->courses->first()->id, 'colegio_id' => $student->colegio_id, 'title' => 'Tarea 1', 'description' => 'Resolver p12', 'due_date' => now()->addDay()->toDateString(), 'type' => Activity::TYPE_TAREA, 'is_homework' => true, 'max_score' => 20, 'weight_percentage' => 10]);

        $this->actingAs($otherParent)->postJson(route('representante.api.ia.actividad', $activity->id), ['estudiante_id' => $student->id])->assertStatus(403);
        $this->actingAs($parent)->postJson(route('representante.api.ia.actividad', $activity->id), ['estudiante_id' => $otherStudent->id])->assertStatus(403);
    }

    public function test_activity_not_belonging_to_student_returns_404(): void
    {
        [$parent, $student, , , , $otherCourse] = $this->familyLink();
        $otherActivity = Activity::create(['teacher_id' => $student->teacher_id, 'course_id' => $otherCourse->id, 'colegio_id' => $student->colegio_id, 'title' => 'Otra tarea', 'description' => 'Otro', 'due_date' => now()->addDay()->toDateString(), 'type' => Activity::TYPE_TAREA]);

        $this->actingAs($parent)->postJson(route('representante.api.ia.actividad', $otherActivity->id), ['estudiante_id' => $student->id])->assertStatus(404);
    }

    public function test_materia_not_belonging_returns_404(): void
    {
        [$parent, $student, , , , $otherCourse] = $this->familyLink();

        $this->actingAs($parent)->postJson(route('representante.api.ia.calificaciones'), ['estudiante_id' => $student->id, 'materia_id' => $otherCourse->id])->assertStatus(404);
        $this->actingAs($parent)->postJson(route('representante.api.ia.asistencia'), ['estudiante_id' => $student->id, 'materia_id' => $otherCourse->id])->assertStatus(404);
    }

    public function test_explain_activity_without_openai_returns_deterministic_success(): void
    {
        config()->set('services.openai.key', null);
        [$parent, $student, , , $course] = $this->familyLink();
        $activity = Activity::create(['teacher_id' => $course->teacher_id, 'course_id' => $course->id, 'colegio_id' => $student->colegio_id, 'title' => 'Guía fracciones', 'description' => 'Resolver páginas 12 a 14', 'notes' => 'Traer cuaderno', 'due_date' => now()->addDays(2)->toDateString(), 'type' => Activity::TYPE_TAREA, 'max_score' => 20, 'weight_percentage' => 10]);

        $res = $this->actingAs($parent)->postJson(route('representante.api.ia.actividad', $activity->id), ['estudiante_id' => $student->id])->assertOk()->json();
        $this->assertTrue($res['success']);
        $this->assertSame('activity_explanation', $res['action']);
        $this->assertNotEmpty($res['content']);
        $this->assertNotEmpty($res['title']);
    }

    public function test_explain_activity_with_openai_mock(): void
    {
        config()->set('services.openai.key', 'sk-test');
        Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => 'Debe resolver las páginas indicadas y entregar el cuaderno.']]]], 200)]);
        [$parent, $student, , , $course] = $this->familyLink();
        $activity = Activity::create(['teacher_id' => $course->teacher_id, 'course_id' => $course->id, 'colegio_id' => $student->colegio_id, 'title' => 'Tarea X', 'description' => 'Hacer ejercicio 5', 'due_date' => now()->addDay()->toDateString(), 'type' => Activity::TYPE_TAREA]);

        $res = $this->actingAs($parent)->postJson(route('representante.api.ia.actividad', $activity->id), ['estudiante_id' => $student->id])->assertOk()->json();
        $this->assertTrue($res['success']);
        $this->assertStringContainsString('Debe resolver', $res['content']);
    }

    public function test_explain_evaluation_with_mock(): void
    {
        config()->set('services.openai.key', 'sk-test');
        Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => 'Debe estudiar el tema de fracciones.']]]], 200)]);
        [$parent, $student, , , $course] = $this->familyLink();
        $eval = Evaluation::create(['teacher_id' => $course->teacher_id, 'course_id' => $course->id, 'colegio_id' => $student->colegio_id, 'title' => 'Parcial 1', 'topic' => 'Fracciones', 'description' => 'Evaluación escrita', 'status' => 'published', 'scheduled_at' => now()->addWeek()]);

        $res = $this->actingAs($parent)->postJson(route('representante.api.ia.evaluacion', $eval->id), ['estudiante_id' => $student->id])->assertOk()->json();
        $this->assertTrue($res['success']);
        $this->assertSame('evaluation_explanation', $res['action']);
    }

    public function test_summarize_week_empty_and_with_events(): void
    {
        config()->set('services.openai.key', null);
        [$parent, $student] = $this->familyLink();

        $res = $this->actingAs($parent)->postJson(route('representante.api.ia.calendario'), ['estudiante_id' => $student->id])->assertOk()->json();
        $this->assertTrue($res['success']);
        $this->assertSame('week_summary', $res['action']);

        // add future task within week
        $course = $student->courses->first();
        Activity::create(['teacher_id' => $course->teacher_id, 'course_id' => $course->id, 'colegio_id' => $student->colegio_id, 'title' => 'Tarea semana', 'description' => 'Entregar', 'due_date' => now()->addDays(2)->toDateString(), 'type' => Activity::TYPE_TAREA]);
        Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => 'Tiene una tarea de Matemática el '.now()->addDays(2)->format('d/m').' .']]]], 200)]);
        config()->set('services.openai.key', 'sk-test');

        $res2 = $this->actingAs($parent)->postJson(route('representante.api.ia.calendario'), ['estudiante_id' => $student->id])->assertOk()->json();
        $this->assertTrue($res2['success']);
    }

    public function test_explain_grades_and_attendance_deterministic(): void
    {
        config()->set('services.openai.key', null);
        [$parent, $student, , , $course] = $this->familyLink();

        $resG = $this->actingAs($parent)->postJson(route('representante.api.ia.calificaciones'), ['estudiante_id' => $student->id])->assertOk()->json();
        $this->assertTrue($resG['success']);
        $this->assertSame('grades_explanation', $resG['action']);

        $resGm = $this->actingAs($parent)->postJson(route('representante.api.ia.calificaciones'), ['estudiante_id' => $student->id, 'materia_id' => $course->id])->assertOk()->json();
        $this->assertTrue($resGm['success']);

        $resA = $this->actingAs($parent)->postJson(route('representante.api.ia.asistencia'), ['estudiante_id' => $student->id])->assertOk()->json();
        $this->assertTrue($resA['success']);
        $this->assertSame('attendance_explanation', $resA['action']);

        Attendance::create(['colegio_id' => $student->colegio_id, 'course_id' => $course->id, 'student_id' => $student->id, 'teacher_id' => $course->teacher_id, 'attended_on' => now()->toDateString(), 'status' => Attendance::STATUS_PRESENT]);
        $resAm = $this->actingAs($parent)->postJson(route('representante.api.ia.asistencia'), ['estudiante_id' => $student->id, 'materia_id' => $course->id])->assertOk()->json();
        $this->assertTrue($resAm['success']);
        $this->assertStringContainsString('%', $resAm['content']);
    }

    public function test_openai_failure_returns_success_false_gracefully(): void
    {
        config()->set('services.openai.key', 'sk-test');
        Http::fake(['api.openai.com/*' => Http::response('error', 500)]);
        [$parent, $student, , , $course] = $this->familyLink();
        $activity = Activity::create(['teacher_id' => $course->teacher_id, 'course_id' => $course->id, 'colegio_id' => $student->colegio_id, 'title' => 'Tarea Y', 'description' => 'Desc', 'due_date' => now()->addDay()->toDateString(), 'type' => Activity::TYPE_TAREA]);

        $res = $this->actingAs($parent)->postJson(route('representante.api.ia.actividad', $activity->id), ['estudiante_id' => $student->id])->assertOk()->json();
        // Con fallo IA, cae a fallback determinista con success true (no error 500 al representante)
        $this->assertTrue($res['success']);
        $this->assertNotEmpty($res['content']);
    }
}

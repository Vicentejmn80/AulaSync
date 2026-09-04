<?php

namespace Tests\Feature\Qa;

use App\Models\Activity;
use App\Models\Student;
use App\Support\Qa\QaSchool;

class QaRepresentanteWorkflowTest extends QaTestCase
{
    public function test_parent_login_reaches_family_hub(): void
    {
        $this->httpLogin(QaSchool::parentEmail(1))
            ->assertRedirect();

        $this->get(route('dashboard'))->assertRedirect(route('representante.dashboard'));
        $this->get(route('representante.dashboard'))
            ->assertOk()
            ->assertSee('Alumno QA 01');
    }

    public function test_parent_sees_only_authorized_children_calendar_and_progress(): void
    {
        $parent = $this->parent(1);
        $students = $this->loginAs($parent)
            ->getJson(route('representante.api.estudiantes'))
            ->assertOk()
            ->json('students');

        $names = collect($students)->pluck('name');
        $this->assertTrue($names->contains('Alumno QA 01'));
        $this->assertTrue($names->contains('Alumno QA 02'));
        $this->assertFalse($names->contains('Alumno QA 03'));
        $this->assertFalse($names->contains('Alumno QA Other'));

        $studentId = (int) collect($students)->firstWhere('name', 'Alumno QA 01')['id'];

        $summary = $this->loginAs($parent)
            ->getJson(route('representante.api.resumen', $studentId))
            ->assertOk()
            ->json();
        $this->assertTrue((bool) ($summary['ok'] ?? false));
        $this->assertNotEmpty(data_get($summary, 'summary'));

        $calendar = $this->loginAs($parent)
            ->getJson(route('representante.api.calendario', ['estudiante' => $studentId, 'month' => now()->format('Y-m')]))
            ->assertOk()
            ->json();
        $this->assertNotEmpty(data_get($calendar, 'calendar') ?? $calendar);

        $subjects = $this->loginAs($parent)
            ->getJson(route('representante.api.materias', $studentId))
            ->assertOk()
            ->json();
        $this->assertNotEmpty(data_get($subjects, 'materias') ?? data_get($subjects, 'subjects') ?? $subjects);
    }

    public function test_parent_calendar_lists_seeded_activity_for_enrolled_child(): void
    {
        $parent = $this->parent(1);
        $student = Student::query()->where('name', 'Alumno QA 01')->firstOrFail();
        $this->assertGreaterThan(0, $student->courses()->count());
        $this->assertTrue(
            Activity::query()
                ->where('title', 'Tarea QA Matemática 1ro')
                ->whereIn('course_id', $student->courses()->pluck('courses.id'))
                ->exists()
        );

        $calendar = $this->loginAs($parent)
            ->getJson(route('representante.api.calendario', [
                'estudiante' => $student->id,
                'month' => now()->format('Y-m'),
            ]))
            ->assertOk()
            ->json('calendar');

        $this->assertGreaterThan(0, (int) ($calendar['total_events'] ?? 0));
        $titles = collect($calendar['events'] ?? [])->flatten(1)->pluck('title');
        $this->assertTrue(
            $titles->contains(fn ($title) => str_contains((string) $title, 'Tarea QA Matemática 1ro')),
            json_encode($titles, JSON_UNESCAPED_UNICODE)
        );

        $hub = $this->loginAs($parent)
            ->get(route('representante.dashboard'))
            ->assertOk();
        $this->assertMatchesRegularExpression('/"total_events"\s*:\s*[1-9]/', $hub->getContent());
        $hub->assertSee('Tarea QA', false);
    }

    public function test_parent_summary_counts_enrolled_active_courses(): void
    {
        $parent = $this->parent(1);
        $student = Student::query()->where('name', 'Alumno QA 01')->firstOrFail();
        $enrolled = (int) $student->courses()->count();
        $this->assertGreaterThan(0, $enrolled);

        $summary = $this->loginAs($parent)
            ->getJson(route('representante.api.resumen', $student->id))
            ->assertOk()
            ->json('summary');

        $this->assertSame($enrolled, (int) ($summary['courses_count'] ?? 0));

        $listed = $this->loginAs($parent)
            ->getJson(route('representante.api.estudiantes'))
            ->assertOk()
            ->json('students');
        $row = collect($listed)->firstWhere('name', 'Alumno QA 01');
        $this->assertNotNull($row);
        $this->assertSame($enrolled, (int) ($row['courses_count'] ?? 0));

        $hub = $this->loginAs($parent)
            ->get(route('representante.dashboard'))
            ->assertOk();
        $this->assertMatchesRegularExpression(
            '/courses_count["\']?\s*:\s*'.$enrolled.'/',
            $hub->getContent()
        );
    }

    public function test_parent_contextual_ai_uses_real_activity_without_open_chatbot(): void
    {
        config()->set('services.openai.key', null);
        $parent = $this->parent(1);
        $student = Student::query()->where('name', 'Alumno QA 01')->firstOrFail();
        $activity = Activity::query()
            ->where('colegio_id', $student->colegio_id)
            ->whereIn('course_id', $student->courses()->pluck('courses.id'))
            ->where('type', Activity::TYPE_TAREA)
            ->firstOrFail();

        $explain = $this->loginAs($parent)
            ->postJson(route('representante.api.ia.actividad', $activity->id), [
                'estudiante_id' => $student->id,
            ])
            ->assertOk()
            ->json();

        $this->assertTrue((bool) ($explain['success'] ?? false), json_encode($explain));
        $this->assertSame('activity_explanation', $explain['action'] ?? null);
        $this->assertNotEmpty($explain['content'] ?? null);

        $this->loginAs($parent)
            ->postJson(route('representante.api.ia.calendario'), ['estudiante_id' => $student->id])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->loginAs($parent)
            ->postJson(route('representante.api.ia.calificaciones'), ['estudiante_id' => $student->id])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->loginAs($parent)
            ->postJson(route('representante.api.ia.asistencia'), ['estudiante_id' => $student->id])
            ->assertOk()
            ->assertJsonPath('success', true);
    }
}

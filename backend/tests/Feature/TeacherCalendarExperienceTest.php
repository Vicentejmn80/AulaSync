<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Colegio;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TeacherCalendarExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_can_be_filtered_by_grade_and_returns_time_slots(): void
    {
        [$teacher, $course3ro, $course6to] = $this->teacherWithTwoCourses();

        Activity::create([
            'teacher_id' => $teacher->id,
            'course_id' => $course3ro->id,
            'title' => 'Fotosíntesis y cloroplastos',
            'due_date' => '2026-09-08',
            'type' => 'clase',
            'max_score' => 20,
        ]);
        Activity::create([
            'teacher_id' => $teacher->id,
            'course_id' => $course6to->id,
            'title' => 'Respiración celular',
            'due_date' => '2026-09-08',
            'type' => 'clase',
            'max_score' => 20,
        ]);

        $response = $this->actingAs($teacher)
            ->getJson(route('teacher.api.calendar', ['month' => '2026-09', 'grade' => '3ro']));

        $response->assertOk()
            ->assertJsonPath('selected_grade', '3ro')
            ->assertJsonPath('total_activities', 1);

        $this->assertContains('3ro', $response->json('grade_options'));
        $this->assertContains('6to', $response->json('grade_options'));
        $dayList = $response->json('activities_by_day.2026-09-08');
        $this->assertCount(1, $dayList);
        $this->assertSame('3ro', $dayList[0]['grade']);
        $this->assertSame('#7C3AED', $dayList[0]['grade_color']);
        $this->assertSame('07:00', $dayList[0]['time_label']);
        $this->assertSame('07:00-07:50', $dayList[0]['time_range']);
    }

    public function test_welcome_stats_include_today_list_grouped_by_grade(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-16 09:30:00'));
        try {
            [$teacher, $course3ro, $course6to] = $this->teacherWithTwoCourses();

            Activity::create([
                'teacher_id' => $teacher->id,
                'course_id' => $course3ro->id,
                'title' => 'Clase de apertura: fotosíntesis',
                'due_date' => '2026-09-16',
                'type' => 'clase',
                'max_score' => 20,
            ]);
            Activity::create([
                'teacher_id' => $teacher->id,
                'course_id' => $course3ro->id,
                'title' => 'Laboratorio de hojas',
                'due_date' => '2026-09-16',
                'type' => 'actividad',
                'max_score' => 20,
            ]);
            Activity::create([
                'teacher_id' => $teacher->id,
                'course_id' => $course6to->id,
                'title' => 'Evaluación breve',
                'due_date' => '2026-09-16',
                'type' => 'actividad',
                'max_score' => 20,
            ]);

            $stats = $this->actingAs($teacher)->getJson(route('teacher.api.stats'));
            $stats->assertOk();

            $today = $stats->json('today_grade_list');
            $this->assertIsArray($today);
            $this->assertCount(2, $today);
            $this->assertSame('3ro', $today[0]['grade']);
            $this->assertSame(2, $today[0]['count']);
            $this->assertSame('6to', $today[1]['grade']);
            $this->assertSame('07:00-07:50', $today[0]['items'][0]['time_range']);
            $this->assertSame('08:00-08:50', $today[0]['items'][1]['time_range']);
            $this->assertSame('#7C3AED', $today[0]['items'][0]['grade_color']);

            $next = $stats->json('next_activity');
            $this->assertSame('Clase de apertura: fotosíntesis', $next['title']);
            $this->assertSame('3ro', $next['grade']);
            $this->assertSame('#7C3AED', $next['grade_color']);
            $this->assertSame('07:00', $next['time_label']);
            $this->assertSame('Clase', $next['type_label']);

            $queue = $stats->json('upcoming_queue');
            $this->assertCount(3, $queue);
            $this->assertSame('Evaluación breve', $queue[2]['title']);
            $this->assertSame('#0891B2', $queue[2]['grade_color']);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_upcoming_queue_returns_the_next_five_activities(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-30 07:15:00'));
        try {
            [$teacher, $course3ro, $course6to] = $this->teacherWithTwoCourses();

            foreach ([
                ['Fotosíntesis: conceptos clave', $course3ro, '2026-08-31', 'clase'],
                ['Laboratorio de hojas', $course3ro, '2026-09-01', 'actividad'],
                ['Quiz de cloroplastos', $course6to, '2026-09-02', 'actividad'],
                ['Célula animal', $course3ro, '2026-09-03', 'clase'],
                ['Examen de unidad', $course6to, '2026-09-04', 'actividad'],
                ['Salida de campo', $course3ro, '2026-09-05', 'clase'],
            ] as [$title, $course, $date, $type]) {
                Activity::create([
                    'teacher_id' => $teacher->id,
                    'course_id' => $course->id,
                    'title' => $title,
                    'due_date' => $date,
                    'type' => $type,
                    'max_score' => 20,
                ]);
            }

            $stats = $this->actingAs($teacher)->getJson(route('teacher.api.stats'));
            $stats->assertOk();

            $queue = $stats->json('upcoming_queue');
            $this->assertCount(5, $queue);
            $this->assertSame('Fotosíntesis: conceptos clave', $stats->json('next_activity.title'));
            $this->assertSame('3ro', $stats->json('next_activity.grade'));
            $this->assertSame('07:00', $stats->json('next_activity.time_label'));
            $this->assertSame(['Fotosíntesis: conceptos clave', 'Laboratorio de hojas', 'Quiz de cloroplastos', 'Célula animal', 'Examen de unidad'], array_column($queue, 'title'));
            $this->assertNotContains('Salida de campo', array_column($queue, 'title'));
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * @return array{0:User,1:Course,2:Course}
     */
    private function teacherWithTwoCourses(): array
    {
        $colegio = Colegio::create([
            'name' => 'Colegio Agenda',
            'invite_code' => 'AGD-1001',
            'codes_pin' => Colegio::hashPinFromInvite('AGD-1001'),
        ]);

        $teacher = User::factory()->create([
            'role' => 'profesor',
            'colegio_id' => $colegio->id,
            'onboarding_completed' => true,
            'name' => 'Docente Agenda',
        ]);

        $course3ro = Course::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => $teacher->id,
            'subject_name' => 'Biología',
            'grade' => '3ro',
            'section' => 'A',
            'school_year' => '2026-2027',
            'invite_code' => 'BIO-3A',
        ]);
        $course6to = Course::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => $teacher->id,
            'subject_name' => 'Biología',
            'grade' => '6to',
            'section' => 'A',
            'school_year' => '2026-2027',
            'invite_code' => 'BIO-6A',
        ]);

        return [$teacher, $course3ro, $course6to];
    }

    public function test_teacher_can_reschedule_an_activity_to_a_specific_hour(): void
    {
        [$teacher, $course3ro] = $this->teacherWithTwoCourses();

        $activity = Activity::create([
            'teacher_id' => $teacher->id,
            'course_id' => $course3ro->id,
            'title' => 'Fotosíntesis: Teoría fundamental',
            'due_date' => '2026-09-07',
            'type' => 'clase',
            'max_score' => 20,
        ]);

        $move = $this->actingAs($teacher)->patchJson(
            route('teacher.api.activity.schedule', $activity),
            ['time' => '10:00']
        );

        $move->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('activity.time_label', '10:00')
            ->assertJsonPath('activity.time_range', '10:00-10:50')
            ->assertJsonPath('activity.scheduled_time', '10:00');

        $this->assertStringStartsWith('10:00', (string) $activity->fresh()->scheduled_time);

        $calendar = $this->actingAs($teacher)
            ->getJson(route('teacher.api.calendar', ['month' => '2026-09']));
        $calendar->assertOk();
        $dayList = $calendar->json('activities_by_day.2026-09-07');
        $this->assertSame('10:00', $dayList[0]['time_label']);
        $this->assertTrue($dayList[0]['scheduled']);
    }

    public function test_rescheduled_hour_is_kept_and_fallback_avoids_that_slot(): void
    {
        [$teacher, $course3ro, $course6to] = $this->teacherWithTwoCourses();

        $first = Activity::create([
            'teacher_id' => $teacher->id,
            'course_id' => $course3ro->id,
            'title' => 'Fotosíntesis: Teoría fundamental',
            'due_date' => '2026-09-07',
            'type' => 'clase',
            'max_score' => 20,
            'scheduled_time' => '10:00:00',
        ]);
        Activity::create([
            'teacher_id' => $teacher->id,
            'course_id' => $course6to->id,
            'title' => 'Rayos solares: Teoría fundamental',
            'due_date' => '2026-09-07',
            'type' => 'clase',
            'max_score' => 20,
        ]);

        $calendar = $this->actingAs($teacher)
            ->getJson(route('teacher.api.calendar', ['month' => '2026-09']));
        $calendar->assertOk();

        $dayList = collect($calendar->json('activities_by_day.2026-09-07'));
        $this->assertSame('10:00', $dayList->firstWhere('id', $first->id)['time_label']);
        $this->assertSame('07:00', $dayList->firstWhere('title', 'Rayos solares: Teoría fundamental')['time_label']);
    }

    public function test_another_teacher_cannot_reschedule_an_activity(): void
    {
        [$teacher, $course3ro] = $this->teacherWithTwoCourses();
        $activity = Activity::create([
            'teacher_id' => $teacher->id,
            'course_id' => $course3ro->id,
            'title' => 'Clase ajena',
            'due_date' => '2026-09-07',
            'type' => 'clase',
            'max_score' => 20,
        ]);

        $intruder = User::factory()->create([
            'role' => 'profesor',
            'colegio_id' => $teacher->colegio_id,
            'onboarding_completed' => true,
        ]);

        $this->actingAs($intruder)
            ->patchJson(route('teacher.api.activity.schedule', $activity), ['time' => '11:00'])
            ->assertForbidden();
    }
}


<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Models\Colegio;
use App\Models\Course;
use App\Models\Grade;
use App\Models\ReportCard;
use App\Models\ReportCardGrade;
use App\Models\Student;
use App\Models\User;
use App\Support\DatabaseBoolean;
use Illuminate\Database\PostgresConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use RuntimeException;
use Tests\TestCase;

class AcademicReportCardGenerateTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_card_grade_casts_is_manual_as_boolean(): void
    {
        $this->assertSame('boolean', (new ReportCardGrade)->getCasts()['is_manual']);
    }

    public function test_root_cause_laravel_binds_is_manual_false_as_integer_on_pgsql(): void
    {
        $connection = $this->postgresConnection();
        $builder = $connection->table('report_card_grades')
            ->where('report_card_id', 3)
            ->where('is_manual', false);

        $sql = $connection->getQueryGrammar()->compileDelete($builder);
        $bindings = $connection->prepareBindings($builder->getBindings());

        $this->assertSame(
            'delete from "report_card_grades" where "report_card_id" = ? and "is_manual" = ?',
            $sql
        );
        $this->assertSame([3, 0], $bindings);
        $this->assertSame('integer', gettype($bindings[1]));
    }

    public function test_pgsql_delete_compiles_is_manual_as_boolean_literal(): void
    {
        $connection = $this->postgresConnection();
        $builder = $connection->table('report_card_grades')
            ->where('report_card_id', 3)
            ->whereRaw(DatabaseBoolean::equals('is_manual', false, 'pgsql'));

        $sql = $connection->getQueryGrammar()->compileDelete($builder);
        $bindings = $connection->prepareBindings($builder->getBindings());

        $this->assertSame(
            'delete from "report_card_grades" where "report_card_id" = ? and is_manual = false',
            $sql
        );
        $this->assertSame([3], $bindings);
        $this->assertStringNotContainsString('is_manual = 0', $sql);
        $this->assertStringNotContainsString('is_manual = ?', $sql);
    }

    public function test_pgsql_insert_compiles_is_manual_as_boolean_literal(): void
    {
        $connection = $this->postgresConnection();

        foreach ([true, false] as $flag) {
            $builder = $connection->table('report_card_grades');
            $values = [[
                'report_card_id' => 3,
                'course_id' => 1,
                'course_name' => 'Matemática 3ro / A',
                'grade' => 80,
                'letter_grade' => 'B+',
                'is_manual' => DatabaseBoolean::bind($flag, 'pgsql'),
            ]];

            $sql = $connection->getQueryGrammar()->compileInsert($builder, $values);
            $bindings = $builder->cleanBindings(Arr::flatten($values, 1));
            $literal = $flag ? 'true' : 'false';

            $this->assertSame(
                'insert into "report_card_grades" ("report_card_id", "course_id", "course_name", "grade", "letter_grade", "is_manual") values (?, ?, ?, ?, ?, '.$literal.')',
                $sql
            );
            $this->assertSame([3, 1, 'Matemática 3ro / A', 80, 'B+'], $bindings);
        }
    }

    public function test_generate_boletas_creates_and_regenerates_without_boolean_integer_compare(): void
    {
        [$director, $period, $student] = $this->schoolWithGradedPeriod();

        $first = $this->actingAs($director)
            ->postJson(route('director.api.periods.generate', $period->id))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('generated', 1);

        $this->assertSame(1, ReportCard::count());
        $grade = ReportCardGrade::first();
        $this->assertNotNull($grade);
        $this->assertFalse($grade->is_manual);
        $this->assertSame(80.0, (float) $grade->grade);
        $this->assertSame('B+', $grade->letter_grade);

        $second = $this->actingAs($director)
            ->postJson(route('director.api.periods.generate', $period->id))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('generated', 1);

        $this->assertSame(1, ReportCard::count());
        $this->assertSame(1, ReportCardGrade::count());
        $this->assertFalse(ReportCardGrade::first()->is_manual);
        $this->assertSame($student->id, ReportCard::first()->student_id);
        $this->assertStringContainsString('Boletas generadas: 1', $first->json('message'));
        $this->assertStringContainsString('Boletas generadas: 1', $second->json('message'));
    }

    public function test_editing_a_grade_marks_it_manual_and_regenerate_keeps_the_flag_query_safe(): void
    {
        [$director, $period] = $this->schoolWithGradedPeriod();

        $this->actingAs($director)
            ->postJson(route('director.api.periods.generate', $period->id))
            ->assertOk();

        $card = ReportCard::first();
        $row = ReportCardGrade::first();

        $this->actingAs($director)
            ->putJson(route('director.api.report-cards.update', $card->id), [
                'grades' => [[
                    'course_id' => $row->course_id,
                    'grade' => 91,
                    'teacher_observations' => 'Ajuste del director',
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $row->refresh();
        $this->assertTrue($row->is_manual);
        $this->assertSame(91.0, (float) $row->grade);

        $this->actingAs($director)
            ->postJson(route('director.api.periods.generate', $period->id))
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    /**
     * @return array{0:User,1:AcademicPeriod,2:Student}
     */
    private function schoolWithGradedPeriod(): array
    {
        $director = User::factory()->create([
            'role' => 'director',
            'onboarding_completed' => true,
            'name' => 'Dir. Boletas',
        ]);
        $colegio = Colegio::create([
            'name' => 'Colegio Boletas',
            'invite_code' => 'BOL-1001',
            'codes_pin' => Colegio::hashPinFromInvite('BOL-1001'),
            'director_user_id' => $director->id,
        ]);
        $director->update(['colegio_id' => $colegio->id]);

        $teacher = User::factory()->create([
            'role' => 'profesor',
            'colegio_id' => $colegio->id,
            'onboarding_completed' => true,
            'name' => 'Prof. Matemática',
        ]);

        $course = Course::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => $teacher->id,
            'subject_name' => 'Matemática',
            'grade' => '3ro',
            'section' => 'A',
            'school_year' => '2026-2027',
            'invite_code' => 'MAT-BOLETAS',
        ]);

        $student = Student::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => $teacher->id,
            'name' => 'Ángel Marín',
            'grade' => '3ro',
            'section' => 'A',
            'family_code' => 'FAM-BOLETAS',
        ]);
        $course->students()->attach($student->id);

        $period = AcademicPeriod::create([
            'colegio_id' => $colegio->id,
            'name' => 'Primer lapso',
            'start_date' => '2026-09-01',
            'end_date' => '2026-12-15',
            'status' => 'active',
        ]);

        $activity = Activity::create([
            'teacher_id' => $teacher->id,
            'course_id' => $course->id,
            'colegio_id' => $colegio->id,
            'title' => 'Quiz 1',
            'type' => 'actividad',
            'max_score' => 20,
            'weight_percentage' => 100,
            'due_date' => '2026-10-15',
        ]);

        Grade::create([
            'activity_id' => $activity->id,
            'student_id' => $student->id,
            'colegio_id' => $colegio->id,
            'score' => 16,
        ]);

        return [$director->fresh(), $period, $student];
    }

    private function postgresConnection(): PostgresConnection
    {
        return new PostgresConnection(function () {
            throw new RuntimeException('Postgres PDO is not exercised in this test');
        }, 'aulasync', '', ['driver' => 'pgsql']);
    }
}

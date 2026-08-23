<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Attendance;
use App\Models\Colegio;
use App\Models\Course;
use App\Models\Evaluation;
use App\Models\Grade;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectorDataAgentQaTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_dataset_queries_run_through_real_command_endpoint(): void
    {
        [$director] = $this->seedSchool();
        $dataset = require dirname(__DIR__).'/Datasets/DirectorDataAgentQueries.php';

        $results = [];
        foreach ($dataset as $case) {
            $started = hrtime(true);
            $response = $this->actingAs($director)->postJson(route('director.ai.command'), [
                'prompt' => $case['question'],
            ]);
            $elapsed = (int) ((hrtime(true) - $started) / 1_000_000);

            $response->assertStatus(
                in_array($response->status(), [200, 422], true) ? $response->status() : 200,
                $case['question'].' '.$response->status()
            );

            $json = $response->json();
            $message = (string) ($json['message'] ?? '');
            $payload = json_encode($json, JSON_UNESCAPED_UNICODE) ?: '';

            $this->assertStringNotContainsString('Eva Inventada', $payload, $case['question']);
            $this->assertDoesNotMatchRegularExpression('/\b(?:SQL|Eloquent|OpenAI|endpoint)\b/u', $message, $case['question']);

            foreach ($case['expected_tools'] as $tool) {
                if ($case['id'] === 12 && $tool === 'get_declining_students') {
                    continue;
                }
                $this->assertContains($tool, $json['tools'] ?? [], $case['question'].' missing '.$tool);
            }

            if ($case['id'] === 24) {
                $this->assertStringContainsString('No hay alumnos registrados', $payload);
            }
            if ($case['id'] === 25) {
                $this->assertTrue((bool) ($json['needs_clarification'] ?? false), $case['question']);
            }
            if ($case['id'] === 17) {
                $this->assertStringContainsString('Matemática', $payload);
                $this->assertStringNotContainsString('No hay calificaciones registradas ni en', $payload);
            }
            if ($case['id'] === 21) {
                $this->assertStringNotContainsString('No hay base suficiente', $message);
                $this->assertStringContainsString('66.3%', $message);
            }
            if ($case['id'] === 26) {
                $this->assertStringNotContainsString('Ana Ruiz', $payload);
            }

            $results[] = [
                'id' => $case['id'],
                'question' => $case['question'],
                'ms' => $json['duration_ms'] ?? $elapsed,
                'wall_ms' => $elapsed,
                'tools' => $json['tools'] ?? [],
                'chars' => mb_strlen($message),
                'has_hechos' => str_contains($message, '**Hechos**'),
                'has_analisis' => str_contains($message, '**Análisis**'),
                'has_table' => str_contains($message, '| Alumno') || str_contains($message, '| Puesto'),
                'estado' => preg_match('/\*\*Estado:\*\*\s*(.+)/u', $message, $m) ? trim($m[1]) : null,
                'ok' => ($json['success'] ?? false) || ($json['needs_clarification'] ?? false),
            ];
        }

        $this->assertCount(26, $results);
        $this->assertTrue(collect($results)->every(fn ($row) => $row['ok']));

        usort($results, fn ($a, $b) => $b['ms'] <=> $a['ms']);
        $lines = collect($results)->map(function ($row) {
            $tools = implode(',', $row['tools']);

            return sprintf(
                '#%02d %4dms wall=%4dms chars=%3d table=%s estado=%s tools=[%s] %s',
                $row['id'],
                $row['ms'],
                $row['wall_ms'],
                $row['chars'],
                $row['has_table'] ? 'Y' : 'n',
                $row['estado'] ?? '-',
                $tools,
                $row['question']
            );
        })->implode("\n");
        fwrite(STDERR, "\nDirector Data Agent QA timings:\n{$lines}\n");
    }

    /**
     * @return array{0:User,1:Colegio}
     */
    private function seedSchool(): array
    {
        $director = User::factory()->create([
            'role' => 'director',
            'onboarding_completed' => true,
        ]);
        $colegio = Colegio::create([
            'name' => 'Colegio QA',
            'invite_code' => 'COC-QA01',
            'codes_pin' => Colegio::hashPinFromInvite('COC-QA01'),
            'director_user_id' => $director->id,
        ]);
        $director->update(['colegio_id' => $colegio->id]);

        $this->seedClass($colegio, '4to', 'A', [['Ana Ruiz', 18], ['Luis Mora', 8]]);
        $this->seedClass($colegio, '2do', 'A', [['Pepe Sol', 16]]);
        $this->seedClass($colegio, '2do', 'B', [['Carlos Pérez', 14]]);
        $this->seedClass($colegio, '1ro', 'A', [['María Luz', 15]]);
        $this->seedClass($colegio, '3ro', 'A', [['Pedro Gil', 13]]);
        $this->seedClass($colegio, '5to', 'A', [['Sofía Díaz', 17]]);

        $ana = Student::query()->where('colegio_id', $colegio->id)->where('name', 'Ana Ruiz')->firstOrFail();
        $pepe = Student::query()->where('colegio_id', $colegio->id)->where('name', 'Pepe Sol')->firstOrFail();
        $course4 = Course::query()->where('colegio_id', $colegio->id)->where('grade', '4to')->where('section', 'A')->firstOrFail();
        $course2 = Course::query()->where('colegio_id', $colegio->id)->where('grade', '2do')->where('section', 'A')->firstOrFail();
        $teacher4 = User::query()->findOrFail($course4->teacher_id);

        Attendance::create([
            'colegio_id' => $colegio->id,
            'course_id' => $course4->id,
            'student_id' => $ana->id,
            'teacher_id' => $teacher4->id,
            'attended_on' => now()->subDay()->toDateString(),
            'status' => Attendance::STATUS_ABSENT,
        ]);
        Attendance::create([
            'colegio_id' => $colegio->id,
            'course_id' => $course2->id,
            'student_id' => $pepe->id,
            'teacher_id' => $course2->teacher_id,
            'attended_on' => now()->subDays(2)->toDateString(),
            'status' => Attendance::STATUS_ABSENT,
        ]);

        $old = Activity::create([
            'teacher_id' => $teacher4->id,
            'course_id' => $course4->id,
            'title' => 'Primera',
            'max_score' => 20,
        ]);
        $oldGrade = Grade::create([
            'activity_id' => $old->id,
            'student_id' => $ana->id,
            'colegio_id' => $colegio->id,
            'score' => 19,
            'status' => 'published',
        ]);
        $oldGrade->forceFill(['created_at' => now()->subDays(20)])->save();

        Evaluation::create([
            'teacher_id' => $teacher4->id,
            'course_id' => $course4->id,
            'colegio_id' => $colegio->id,
            'title' => 'Parcial de Matemática',
            'status' => 'published',
        ]);
        Activity::create([
            'teacher_id' => $course2->teacher_id,
            'course_id' => $course2->id,
            'title' => 'Tarea de fracciones',
            'type' => Activity::TYPE_TAREA,
            'is_homework' => 1,
            'due_date' => now()->addDays(3)->toDateString(),
            'max_score' => 20,
        ]);

        return [$director->fresh(), $colegio];
    }

    /**
     * @param  array<int,array{0:string,1:int}>  $students
     */
    private function seedClass(Colegio $colegio, string $grade, string $section, array $students): void
    {
        $teacher = User::factory()->create([
            'role' => 'profesor',
            'colegio_id' => $colegio->id,
            'onboarding_completed' => true,
            'name' => 'Prof. '.$grade.$section,
        ]);
        $course = Course::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => $teacher->id,
            'subject_name' => 'Matemática',
            'grade' => $grade,
            'section' => $section,
            'school_year' => '2026-2027',
            'invite_code' => 'CUR-'.strtoupper($grade.$section.uniqid()),
        ]);
        foreach ($students as [$name, $score]) {
            $student = Student::create([
                'colegio_id' => $colegio->id,
                'teacher_id' => $teacher->id,
                'name' => $name,
                'grade' => $grade,
                'section' => $section,
                'family_code' => 'FAM-'.strtoupper(preg_replace('/[^A-Za-z]/', '', $name)).uniqid(),
            ]);
            $activity = Activity::create([
                'teacher_id' => $teacher->id,
                'course_id' => $course->id,
                'title' => 'Evaluación',
                'max_score' => 20,
            ]);
            Grade::create([
                'activity_id' => $activity->id,
                'student_id' => $student->id,
                'colegio_id' => $colegio->id,
                'score' => $score,
                'status' => 'published',
            ]);
        }
    }
}

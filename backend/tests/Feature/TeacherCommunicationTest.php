<?php

namespace Tests\Feature;

use App\Models\Colegio;
use App\Models\CommunicationThread;
use App\Models\Notification;
use App\Models\Student;
use App\Models\User;
use App\Support\DatabaseBoolean;
use Illuminate\Database\PostgresConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use RuntimeException;
use Tests\TestCase;

class TeacherCommunicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_message_flow_persists_ai_suggested_flag(): void
    {
        [$teacher, $parent, $student] = $this->teacherFamilyLink();

        $start = $this->actingAs($teacher)
            ->postJson(route('teacher.communication.threads.start'), [
                'student_id' => $student->id,
                'body' => 'Buenas tardes. Le comparto el resumen de la semana de Ángel.',
            ])
            ->assertOk()
            ->assertJsonPath('message.sender_role', 'teacher')
            ->assertJsonPath('message.ai_suggested', false);

        $thread = $start->json('thread.id');
        $this->assertSame(1, CommunicationThread::count());

        $this->actingAs($teacher)
            ->postJson(route('teacher.communication.messages.send', $thread), [
                'body' => 'Gracias por escribir. La evaluación está programada para el viernes.',
                'ai_suggested' => true,
            ])
            ->assertOk()
            ->assertJsonPath('message.ai_suggested', true);

        $this->actingAs($teacher)
            ->postJson(route('teacher.communication.messages.send', $thread), [
                'body' => 'Le confirmo que ya subí las notas a AulaSync.',
            ])
            ->assertOk()
            ->assertJsonPath('message.ai_suggested', false);

        $flags = CommunicationThread::first()->messages()->orderBy('id')->get()->pluck('ai_suggested')->all();

        $this->assertSame([false, true, false], $flags);
        $this->assertSame(3, Notification::where('user_id', $parent->id)->where('title', 'Nuevo mensaje del docente')->count());
        $this->assertSame('Representante Marín', CommunicationThread::first()->contact_name);
    }

    public function test_pgsql_insert_compiles_ai_suggested_as_boolean_literal(): void
    {
        $connection = $this->postgresConnection();

        foreach ([true, false] as $flag) {
            $builder = $connection->table('communication_messages');
            $values = [[
                'thread_id' => 1,
                'sender_role' => 'teacher',
                'body' => 'Mensaje de prueba',
                'ai_suggested' => DatabaseBoolean::bind($flag, 'pgsql'),
            ]];

            $sql = $connection->getQueryGrammar()->compileInsert($builder, $values);
            $bindings = $builder->cleanBindings(Arr::flatten($values, 1));

            $literal = $flag ? 'true' : 'false';

            $this->assertSame(
                'insert into "communication_messages" ("thread_id", "sender_role", "body", "ai_suggested") values (?, ?, ?, '.$literal.')',
                $sql
            );
            $this->assertSame([1, 'teacher', 'Mensaje de prueba'], $bindings);
        }
    }

    public function test_root_cause_laravel_prepared_bool_bindings_arrive_as_integers_on_pgsql(): void
    {
        $connection = $this->postgresConnection();

        $prepared = $connection->prepareBindings([1, 'teacher', 'Mensaje de prueba', false]);

        $this->assertSame([1, 'teacher', 'Mensaje de prueba', 0], $prepared);
        $this->assertSame('integer', gettype($prepared[3]));
    }

    private function postgresConnection(): PostgresConnection
    {
        return new PostgresConnection(function () {
            throw new RuntimeException('Postgres PDO is not exercised in this test');
        }, 'aulasync', '', ['driver' => 'pgsql']);
    }

    private function teacherFamilyLink(): array
    {
        $colegio = Colegio::create([
            'name' => 'Colegio Comunicación',
            'invite_code' => 'COM-1001',
        ]);
        $teacher = User::factory()->create([
            'role' => 'profesor',
            'colegio_id' => $colegio->id,
            'onboarding_completed' => true,
            'name' => 'Prof. Díaz',
        ]);
        $parent = User::factory()->create([
            'role' => 'representante',
            'colegio_id' => $colegio->id,
            'onboarding_completed' => true,
            'name' => 'Representante Marín',
        ]);
        $student = Student::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => $teacher->id,
            'name' => 'Ángel Marín',
            'grade' => '3ro',
            'section' => 'A',
        ]);
        $parent->representedStudents()->attach($student->id, ['relationship' => 'padre']);

        return [$teacher, $parent, $student];
    }
}

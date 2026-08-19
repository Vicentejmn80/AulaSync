<?php

namespace Tests\Feature;

use App\Models\Colegio;
use App\Models\Student;
use App\Models\User;
use App\Services\AiChatHistoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiChatHistoryAndStudentDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_history_survives_store_and_clears_on_logout(): void
    {
        [$director] = $this->directorContext();

        $this->actingAs($director)->postJson(route('ai.chat.history.store'), [
            'messages' => [
                ['role' => 'user', 'text' => 'Crea al alumno Andrés'],
                ['role' => 'assistant', 'text' => 'Confirma para continuar'],
            ],
        ])->assertOk()->assertJsonPath('success', true);

        $this->actingAs($director)->getJson(route('ai.chat.history'))
            ->assertOk()
            ->assertJsonPath('messages.0.text', 'Crea al alumno Andrés');

        $this->actingAs($director)->post(route('logout'));
        $this->assertSame([], app(AiChatHistoryService::class)->load($director->id));
    }

    public function test_director_can_delete_student_from_staff_page(): void
    {
        [$director, $colegio] = $this->directorContext();
        $student = Student::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => $director->id,
            'name' => 'Andres Perez',
            'grade' => '1ro',
            'family_code' => 'NV-DEL-UI',
        ]);

        $this->actingAs($director)
            ->delete(route('director.students.destroy', $student))
            ->assertRedirect(route('director.students'));

        $this->assertDatabaseMissing('students', ['id' => $student->id]);
    }

    private function directorContext(): array
    {
        $director = User::factory()->create([
            'role' => 'director',
            'onboarding_completed' => true,
        ]);
        $colegio = Colegio::create([
            'name' => 'Colegio Central',
            'invite_code' => 'COC-1001',
            'codes_pin' => Colegio::hashPinFromInvite('COC-1001'),
            'director_user_id' => $director->id,
        ]);
        $director->update(['colegio_id' => $colegio->id]);

        return [$director->fresh(), $colegio];
    }
}

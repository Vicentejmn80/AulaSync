<?php

namespace Tests\Feature;

use App\Models\Colegio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TeacherVoiceNoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_refresh_session_token(): void
    {
        $teacher = $this->teacher();

        $this->actingAs($teacher)
            ->getJson(route('ai.session'))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['token', 'expires_in_minutes']);
    }

    public function test_teacher_can_transcribe_a_voice_note(): void
    {
        $teacher = $this->teacher();
        config([
            'services.openai.key' => 'test-key',
            'services.openai.director_enabled' => true,
            'services.openai.director_test_enabled' => true,
        ]);
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'audio/transcriptions')) {
                return Http::response([
                    'text' => 'Tírame una planificación para el mes de septiembre del curso de tercero de biología para los días lunes y martes',
                ]);
            }

            return Http::response(['choices' => [['message' => ['content' => '']]]], 200);
        });

        $file = UploadedFile::fake()->createWithContent('nota.webm', str_repeat('x', 4096));

        $this->actingAs($teacher)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('ai.transcribe'), ['audio' => $file])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'transcript',
                'Tírame una planificación para el mes de septiembre del curso de 3ro de biología para los días lunes y martes'
            );
    }

    public function test_director_cannot_use_teacher_transcribe_route(): void
    {
        $director = User::factory()->create([
            'role' => 'director',
            'onboarding_completed' => true,
        ]);

        $file = UploadedFile::fake()->createWithContent('nota.webm', str_repeat('x', 4096));

        $this->actingAs($director)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('ai.transcribe'), ['audio' => $file])
            ->assertForbidden();
    }

    private function teacher(): User
    {
        $colegio = Colegio::create([
            'name' => 'Colegio Voz',
            'invite_code' => 'VOZ-1001',
            'codes_pin' => Colegio::hashPinFromInvite('VOZ-1001'),
        ]);
        $teacher = User::factory()->create([
            'role' => 'profesor',
            'colegio_id' => $colegio->id,
            'onboarding_completed' => true,
        ]);

        return $teacher;
    }
}

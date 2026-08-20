<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserSettings;
use App\Support\LessonTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectorOnboardingSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_director_onboarding_persists_default_lesson_template(): void
    {
        $user = User::factory()->create([
            'onboarding_completed' => false,
        ]);

        $response = $this->actingAs($user)->post(route('onboarding.save'), [
            'role' => 'director',
            'nombre_institucion' => 'Didactico',
            'cantidad_sedes' => 1,
            'periodo_academico' => '2026-2027',
            'cantidad_docentes' => 8,
            'modelo_pedagogico' => 'clasica',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('user_settings', [
            'user_id' => $user->id,
            'nombre_institucion' => 'Didactico',
            'lesson_template' => LessonTemplate::CLASSIC,
        ]);
        $this->assertNotNull($user->fresh()->settings?->lesson_template);
    }

    public function test_user_settings_never_save_null_lesson_template(): void
    {
        $user = User::factory()->create();

        $settings = UserSettings::create([
            'user_id' => $user->id,
            'lesson_template' => null,
            'nombre_institucion' => 'Didactico',
        ]);

        $this->assertSame(LessonTemplate::CLASSIC, $settings->lesson_template);
    }
}

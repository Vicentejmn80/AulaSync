<?php

namespace Tests\Feature;

use App\Models\Colegio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TmpReproTest extends TestCase
{
    use RefreshDatabase;

    public function test_caso_c_transcript(): void
    {
        $colegio = Colegio::create(['name' => 'Colegio Central', 'invite_code' => 'COC-1001']);
        $director = User::factory()->create([
            'role' => 'director',
            'colegio_id' => $colegio->id,
            'onboarding_completed' => true,
        ]);

        $res = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'creale al profesor Jorge Luis el curso de lenguaje de 1ro a 6to grado. el va a dar lenguaje',
        ]);

        fwrite(STDERR, "\nCASO C RESPONSE: ".json_encode($res->json(), JSON_UNESCAPED_UNICODE)."\n");

        $res2 = $this->actingAs($director)->postJson(route('director.ai.command'), [
            'prompt' => 'Crea al profesor Junior Vázquez como profesor de biología. Adicional, crea a los alumnos Jason David y Vicente José para el curso de 4to grado.',
        ]);
        fwrite(STDERR, "\nCASO PREVIO RESPONSE: ".json_encode($res2->json(), JSON_UNESCAPED_UNICODE)."\n");

        $this->assertTrue(true);
    }
}

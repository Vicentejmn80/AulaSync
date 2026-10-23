<?php

namespace Tests\Feature;

use App\Models\ExperienciaLead;
use App\Services\ExperienciaPrizeCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExperienciaContactTest extends TestCase
{
    use RefreshDatabase;

    public function test_spin_prize_is_the_prize_saved_with_the_contact(): void
    {
        config([
            'experiencia.exclusive_prizes' => false,
            'experiencia.notify_email' => '',
        ]);

        $spin = $this->postJson('/experiencia/giro')->assertOk();
        $prizeId = $spin->json('prize.id');

        $this->assertContains($prizeId, ['kit-ia-docentes', 'kit-plantillas', 'guia-ia-colegios']);

        $this->postJson('/experiencia/contacto', $this->payload($spin->json('spin_id')))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('premio', $spin->json('prize.name'));

        $this->assertDatabaseHas('experiencia_leads', [
            'email' => 'ana@colegio.edu',
            'cargo' => 'director',
            'premio_id' => $prizeId,
            'problema' => 'Boletines y planificaciones',
            'email_status' => 'skipped',
            'telegram_status' => 'skipped',
            'webhook_status' => 'skipped',
        ]);
    }

    public function test_contact_is_rejected_when_it_was_not_saved(): void
    {
        $this->postJson('/experiencia/contacto', $this->payload((string) Str::uuid(), ['email' => '']))
            ->assertStatus(422);

        $this->assertSame(0, ExperienciaLead::query()->count());
    }

    public function test_duplicate_email_does_not_create_another_row(): void
    {
        $spin = $this->postJson('/experiencia/giro')->assertOk();
        $payload = $this->payload($spin->json('spin_id'));

        $this->postJson('/experiencia/contacto', $payload)->assertOk();

        $again = $this->postJson('/experiencia/giro')->assertOk();
        $this->postJson('/experiencia/contacto', $this->payload($again->json('spin_id')))
            ->assertOk()
            ->assertJsonPath('duplicate', true);

        $this->assertSame(1, ExperienciaLead::query()->count());
    }

    public function test_failed_notification_keeps_the_saved_contact(): void
    {
        config([
            'experiencia.notify_email' => 'equipo@aulasync.test',
            'experiencia.kiosk.webhookUrl' => 'https://hooks.example/lead',
            'services.telegram.enabled' => true,
            'services.telegram.bot_token' => 'token',
            'services.telegram.chat_id' => '1',
        ]);
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => false], 500),
            'hooks.example/*' => Http::response('no', 500),
        ]);

        $spin = $this->postJson('/experiencia/giro')->assertOk();

        $this->postJson('/experiencia/contacto', $this->payload($spin->json('spin_id')))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('notifications.telegram', 'failed')
            ->assertJsonPath('notifications.webhook', 'failed');

        $this->assertDatabaseHas('experiencia_leads', [
            'email' => 'ana@colegio.edu',
            'email_status' => 'sent',
            'telegram_status' => 'failed',
            'webhook_status' => 'failed',
        ]);
    }

    public function test_exhausted_exclusive_quota_is_not_claimable(): void
    {
        config(['experiencia.exclusive_prizes' => true]);

        ExperienciaLead::query()->create([
            'spin_id' => (string) Str::uuid(),
            'tablet_id' => 'tablet-1',
            'nombre' => 'Ana',
            'colegio' => 'Colegio Central',
            'cargo' => 'director',
            'premio_id' => 'auditoria-administrativa',
            'premio_nombre' => 'Auditoría',
            'codigo' => 'AULA-TEST',
            'consentimiento' => true,
        ]);

        config(['experiencia.prizes' => $this->prizesWithAuditQuota(1)]);

        $prize = app(ExperienciaPrizeCatalog::class)->find('auditoria-administrativa');

        $this->assertFalse(app(ExperienciaPrizeCatalog::class)->hasQuota($prize));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(string $spinId, array $overrides = []): array
    {
        return array_merge([
            'spin_id' => $spinId,
            'nombre' => 'Ana',
            'apellido' => 'Pérez',
            'email' => 'ana@colegio.edu',
            'colegio' => 'Colegio Central',
            'cargo' => 'director',
            'problema' => 'Boletines y planificaciones',
            'consentimiento' => true,
        ], $overrides);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function prizesWithAuditQuota(int $quota): array
    {
        return array_map(function (array $prize) use ($quota): array {
            if ($prize['id'] === 'auditoria-administrativa') {
                $prize['quota'] = $quota;
            }

            return $prize;
        }, config('experiencia.prizes'));
    }
}

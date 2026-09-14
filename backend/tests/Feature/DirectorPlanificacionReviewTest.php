<?php

namespace Tests\Feature;

use App\Models\Colegio;
use App\Models\Notification;
use App\Models\Planificacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectorPlanificacionReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_director_review_page_renders_reject_modal_above_the_list(): void
    {
        [$director] = $this->schoolWithPendingPlan();

        $this->actingAs($director)
            ->get(route('director.planificaciones'))
            ->assertOk()
            ->assertSee('¿Rechazar esta planificación?', false)
            ->assertSee('Motivo del rechazo', false)
            ->assertSee('z-index: 12000', false)
            ->assertSee('Rechazar y notificar', false);
    }

    public function test_rejecting_without_a_reason_is_rejected(): void
    {
        [$director, , $plan] = $this->schoolWithPendingPlan();

        $this->actingAs($director)
            ->postJson(route('director.planificaciones.reject', $plan->id), [
                'feedback' => 'corto',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('feedback');

        $this->assertSame('pendiente', $plan->fresh()->status);
        $this->assertSame(0, Notification::query()->count());
    }

    public function test_director_can_reject_a_plan_and_teacher_is_notified_with_the_reason(): void
    {
        [$director, $teacher, $plan] = $this->schoolWithPendingPlan();
        $motivo = 'Falta alinear los objetivos con el currículo de 5to y el cierre no evalúa lo planificado.';

        $this->actingAs($director)
            ->postJson(route('director.planificaciones.reject', $plan->id), [
                'feedback' => $motivo,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 'rechazado');

        $plan->refresh();
        $this->assertSame('rechazado', $plan->status);
        $this->assertSame($motivo, $plan->payload['rechazo_feedback'] ?? null);
        $this->assertSame($motivo, $plan->payload['rechazo_motivo'] ?? null);

        $notification = Notification::query()->where('user_id', $teacher->id)->first();
        $this->assertNotNull($notification);
        $this->assertSame('Planificación rechazada', $notification->title);
        $this->assertStringContainsString($motivo, (string) $notification->message);
        $this->assertStringContainsString((string) $plan->id, (string) $notification->link);
    }

    public function test_teacher_historial_shows_rejection_reason_and_correction_action(): void
    {
        [, $teacher, $plan] = $this->schoolWithPendingPlan();
        $motivo = 'Completa las actividades de cierre con evidencia observable.';

        $plan->update([
            'status' => 'rechazado',
            'payload' => array_merge($plan->payload ?? [], [
                'rechazo_feedback' => $motivo,
                'rechazo_motivo' => $motivo,
            ]),
        ]);

        $this->actingAs($teacher)
            ->get(route('historial'))
            ->assertOk()
            ->assertSee('Motivo de Dirección', false)
            ->assertSee($motivo, false)
            ->assertSee('Corregir y reenviar', false);
    }

    public function test_teacher_planner_shows_director_reason_when_opening_a_rejected_plan(): void
    {
        [, $teacher, $plan] = $this->schoolWithPendingPlan();
        $motivo = 'Reescribe el inicio para activar saberes previos reales de 5to.';

        $plan->update([
            'status' => 'rechazado',
            'payload' => array_merge($plan->payload ?? [], [
                'rechazo_feedback' => $motivo,
                'rechazo_motivo' => $motivo,
            ]),
        ]);

        $this->actingAs($teacher)
            ->get(route('teacher.planner.show', ['id' => $plan->id]))
            ->assertOk()
            ->assertSee('Dirección rechazó este plan', false)
            ->assertSee($motivo, false)
            ->assertSee('Guardar y reenviar', false);
    }

    /**
     * @return array{0:User,1:User,2:Planificacion}
     */
    private function schoolWithPendingPlan(): array
    {
        $director = User::factory()->create([
            'role' => 'director',
            'onboarding_completed' => true,
            'name' => 'Dirección QA',
        ]);
        $colegio = Colegio::create([
            'name' => 'Colegio Revisión',
            'invite_code' => 'REV-1001',
            'codes_pin' => Colegio::hashPinFromInvite('REV-1001'),
            'director_user_id' => $director->id,
        ]);
        $director->update(['colegio_id' => $colegio->id]);

        $teacher = User::factory()->create([
            'role' => 'profesor',
            'colegio_id' => $colegio->id,
            'onboarding_completed' => true,
            'name' => 'Arturo Uslar',
        ]);

        $plan = Planificacion::create([
            'user_id' => $teacher->id,
            'colegio_id' => $colegio->id,
            'tema' => 'Plan mensual Septiembre 2026 · Matemática 5to',
            'objetivo' => 'Fracciones',
            'slug' => 'plan-mat-sept',
            'status' => 'pendiente',
            'payload' => [
                'type' => 'manual_plan',
                'course_name' => 'Matemática 5to',
                'sessions' => [
                    ['date' => '2026-09-14', 'title' => 'Fracciones propias', 'inicio' => 'Activación', 'desarrollo' => 'Práctica', 'cierre' => 'Salida'],
                ],
            ],
        ]);

        return [$director, $teacher, $plan];
    }
}

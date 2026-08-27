<?php

namespace App\Services;

class DirectorConfirmationManager
{
    private const PENDING_PLAN_SESSION_KEY = 'director_ai_pending_plan';

    /**
     * Guarda un ActionPlan completo en sesión.
     *
     * @param  array<string,mixed>  $plan
     * @param  array<string,mixed>  $legacySlots  Compatibilidad con planes antiguos (intent/data/slots).
     */
    public function startPlan(array $plan, array $legacySlots = []): void
    {
        if ($legacySlots !== [] && isset($plan['intent']) && ! isset($plan['actions'])) {
            $plan['slots'] = $legacySlots;
        }

        session([
            self::PENDING_PLAN_SESSION_KEY => [
                ...$plan,
                'status' => $plan['status'] ?? 'pending',
                'created_at' => now()->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
            ],
        ]);
    }

    public function hasPendingPlan(): bool
    {
        return session()->has(self::PENDING_PLAN_SESSION_KEY);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function getPlan(): ?array
    {
        $plan = session(self::PENDING_PLAN_SESSION_KEY);

        return is_array($plan) ? $plan : null;
    }

    /**
     * Agrega una acción al plan actual.
     *
     * @param  array<string,mixed>  $action
     */
    public function addAction(array $action): bool
    {
        $plan = $this->getPlan();
        if (! $plan) {
            return false;
        }

        $plan['actions'][] = $action;
        $plan['updated_at'] = now()->toIso8601String();
        session([self::PENDING_PLAN_SESSION_KEY => $plan]);

        return true;
    }

    /**
     * Actualiza el estado de una acción concreta.
     */
    public function updateActionStatus(string $actionId, string $status): bool
    {
        $plan = $this->getPlan();
        if (! $plan) {
            return false;
        }

        $found = false;
        foreach ($plan['actions'] as $index => $action) {
            if (($action['id'] ?? '') === $actionId) {
                $plan['actions'][$index]['status'] = $status;
                $found = true;
                break;
            }
        }

        if (! $found) {
            return false;
        }

        $plan['updated_at'] = now()->toIso8601String();
        session([self::PENDING_PLAN_SESSION_KEY => $plan]);

        return true;
    }

    /**
     * Llena un slot específico de una acción específica.
     */
    public function fillSlot(string $actionId, string $slotName, mixed $value): bool
    {
        $plan = $this->getPlan();
        if (! $plan) {
            return false;
        }

        // Compatibilidad con planes antiguos.
        if (isset($plan['intent']) && ! isset($plan['actions'])) {
            if (! array_key_exists($slotName, (array) ($plan['slots'] ?? []))) {
                return false;
            }
            $plan['slots'][$slotName] = $value;
            $plan['updated_at'] = now()->toIso8601String();
            session([self::PENDING_PLAN_SESSION_KEY => $plan]);

            return true;
        }

        $found = false;
        foreach ($plan['actions'] ?? [] as $index => $action) {
            if (($action['id'] ?? '') !== $actionId) {
                continue;
            }
            foreach ($action['missing_slots'] ?? [] as $slotIndex => $slot) {
                if (($slot['name'] ?? '') === $slotName) {
                    $plan['actions'][$index]['missing_slots'][$slotIndex]['value'] = $value;
                    $found = true;
                    break 2;
                }
            }
        }

        if (! $found) {
            return false;
        }

        $plan['updated_at'] = now()->toIso8601String();
        $this->recalculateStatus($plan);
        session([self::PENDING_PLAN_SESSION_KEY => $plan]);

        return true;
    }

    /**
     * Devuelve todos los slots pendientes con referencia a su acción.
     *
     * @return array<int,array{action_id:string,action_type:string,slot:string,description:string,required:bool,value:mixed}>
     */
    public function getPendingSlots(): array
    {
        $plan = $this->getPlan();
        if (! $plan) {
            return [];
        }

        // Compatibilidad con planes antiguos de una sola acción (intent/data/slots).
        if (isset($plan['intent']) && ! isset($plan['actions'])) {
            $slots = (array) ($plan['slots'] ?? []);
            $pending = [];
            foreach ($slots as $name => $value) {
                if ($value === null || $value === '') {
                    $pending[] = [
                        'action_id' => 'legacy',
                        'action_type' => (string) ($plan['intent'] ?? ''),
                        'slot' => (string) $name,
                        'description' => $this->slotDescription((string) $name),
                        'required' => true,
                        'value' => $value,
                    ];
                }
            }

            return $pending;
        }

        $pending = [];
        foreach ($plan['actions'] ?? [] as $action) {
            foreach ($action['missing_slots'] ?? [] as $slot) {
                if (($slot['value'] ?? null) === null || ($slot['value'] ?? '') === '') {
                    $pending[] = [
                        'action_id' => (string) ($action['id'] ?? ''),
                        'action_type' => (string) ($action['type'] ?? ''),
                        'slot' => (string) ($slot['name'] ?? ''),
                        'description' => (string) ($slot['description'] ?? ''),
                        'required' => (bool) ($slot['required'] ?? true),
                        'value' => $slot['value'] ?? null,
                    ];
                }
            }
        }

        return $pending;
    }

    private function slotDescription(string $slot): string
    {
        return match ($slot) {
            'grade' => '¿En qué grado?',
            'section' => '¿En qué sección?',
            'subject_name' => '¿De qué materia se trata?',
            default => "¿Cuál es el valor de {$slot}?",
        };
    }

    /**
     * @return array<int,string>
     *
     * @deprecated Usar getPendingSlots() para planes multi-acción.
     */
    public function missingSlots(): array
    {
        return collect($this->getPendingSlots())->pluck('slot')->unique()->values()->all();
    }

    public function isComplete(): bool
    {
        return $this->getPendingSlots() === [];
    }

    public function clear(): void
    {
        session()->forget(self::PENDING_PLAN_SESSION_KEY);
    }

    /**
     * Aplica los valores de slots completos a los params de cada acción y
     * recalcula el estado del plan.
     *
     * @param  array<string,mixed>  $plan
     */
    public function applySlots(array &$plan): void
    {
        foreach ($plan['actions'] ?? [] as $index => $action) {
            foreach ($action['missing_slots'] ?? [] as $slot) {
                $value = $slot['value'] ?? null;
                if ($value === null || $value === '') {
                    continue;
                }
                $name = $slot['name'] ?? '';
                if ($name !== '') {
                    $plan['actions'][$index]['params'][$name] = $value;
                }
            }

            $stillMissing = collect($action['missing_slots'] ?? [])
                ->contains(fn (array $slot) => ($slot['required'] ?? true)
                    && (($slot['value'] ?? null) === null || ($slot['value'] ?? '') === ''));

            $plan['actions'][$index]['status'] = $stillMissing ? 'needs_info' : 'pending';
        }

        $this->recalculateStatus($plan);
    }

    /**
     * @param  array<string,mixed>  $plan
     */
    private function recalculateStatus(array &$plan): void
    {
        $hasNeedsInfo = collect($plan['actions'] ?? [])
            ->contains(fn (array $a) => ($a['status'] ?? '') === 'needs_info');

        $plan['status'] = $hasNeedsInfo ? 'needs_info' : ($plan['status'] ?? 'pending');
        $plan['updated_at'] = now()->toIso8601String();
    }
}

<?php

namespace App\Services;

class DirectorConfirmationManager
{
    private const PENDING_PLAN_SESSION_KEY = 'director_ai_pending_plan';

    /**
     * @param  array<string,mixed>  $plan
     * @param  array<string,mixed>  $slots
     */
    public function startPlan(array $plan, array $slots): void
    {
        session([
            self::PENDING_PLAN_SESSION_KEY => [
                'intent' => (string) ($plan['intent'] ?? ''),
                'data' => (array) ($plan['data'] ?? []),
                'slots' => $slots,
                'status' => 'pending',
                'created_at' => now()->toIso8601String(),
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
        if (! is_array($plan)) {
            return null;
        }

        return $plan;
    }

    public function fillSlot(string $slotName, mixed $value): bool
    {
        $plan = $this->getPlan();
        if (! $plan) {
            return false;
        }
        if (! array_key_exists($slotName, (array) ($plan['slots'] ?? []))) {
            return false;
        }

        $plan['slots'][$slotName] = $value;
        session([self::PENDING_PLAN_SESSION_KEY => $plan]);

        return true;
    }

    public function isComplete(): bool
    {
        $plan = $this->getPlan();
        if (! $plan) {
            return false;
        }

        foreach ((array) ($plan['slots'] ?? []) as $value) {
            if ($value === null || $value === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int,string>
     */
    public function missingSlots(): array
    {
        $plan = $this->getPlan();
        if (! $plan) {
            return [];
        }

        $missing = [];
        foreach ((array) ($plan['slots'] ?? []) as $slot => $value) {
            if ($value === null || $value === '') {
                $missing[] = (string) $slot;
            }
        }

        return $missing;
    }

    public function clear(): void
    {
        session()->forget(self::PENDING_PLAN_SESSION_KEY);
    }
}

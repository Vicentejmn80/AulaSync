<?php

namespace App\Services;

use App\Models\ExperienciaLead;

class ExperienciaPrizeCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function definitions(): array
    {
        $prizes = config('experiencia.prizes', []);

        return is_array($prizes) ? array_values($prizes) : [];
    }

    /**
     * Premios que pueden aparecer en la ruleta.
     *
     * @return array<int, array<string, mixed>>
     */
    public function drawable(): array
    {
        $exclusive = (bool) config('experiencia.exclusive_prizes');

        return array_values(array_filter($this->definitions(), function (array $prize) use ($exclusive): bool {
            if (($prize['kind'] ?? '') === 'exclusive' && ! $exclusive) {
                return false;
            }

            return (int) ($prize['weight'] ?? 0) > 0;
        }));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function publicCatalog(): array
    {
        return array_map(fn (array $prize): array => $this->present($prize), $this->drawable());
    }

    /**
     * @return array<string, mixed>
     */
    public function pick(): array
    {
        $pool = array_values(array_filter($this->drawable(), fn (array $prize): bool => $this->hasQuota($prize)));

        if ($pool === []) {
            $pool = array_values(array_filter($this->definitions(), fn (array $prize): bool => ($prize['kind'] ?? '') === 'digital' && (int) ($prize['weight'] ?? 0) > 0));
        }

        $total = $this->totalWeight($pool);

        return $this->pickFrom($pool, random_int(1, max(1, $total)));
    }

    /**
     * @param  array<int, array<string, mixed>>  $pool
     * @return array<string, mixed>
     */
    public function pickFrom(array $pool, int $ticket): array
    {
        $total = $this->totalWeight($pool);
        $cursor = $total > 0 ? (($ticket - 1) % $total) + 1 : 1;

        foreach ($pool as $prize) {
            $cursor -= (int) $prize['weight'];
            if ($cursor <= 0) {
                return $prize;
            }
        }

        return $pool[0];
    }

    /**
     * @param  array<string, mixed>  $prize
     */
    public function hasQuota(array $prize): bool
    {
        if (! array_key_exists('quota', $prize)) {
            return true;
        }

        $quota = (int) $prize['quota'];
        if ($quota <= 0) {
            return false;
        }

        $used = ExperienciaLead::query()
            ->where('premio_id', $prize['id'])
            ->whereDate('created_at', now()->toDateString())
            ->count();

        return $used < $quota;
    }

    /**
     * @param  array<string, mixed>  $prize
     * @return array<string, mixed>
     */
    public function present(array $prize): array
    {
        return [
            'id' => $prize['id'],
            'kind' => $prize['kind'],
            'icon' => $prize['icon'],
            'short' => $prize['short'],
            'name' => $prize['name'],
            'description' => $prize['description'],
            'condition' => $prize['condition'],
            'color' => $prize['color'],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $pool
     */
    public function totalWeight(array $pool): int
    {
        return array_sum(array_map(fn (array $prize): int => (int) ($prize['weight'] ?? 0), $pool));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $id): ?array
    {
        foreach ($this->definitions() as $prize) {
            if (($prize['id'] ?? '') === $id) {
                return $prize;
            }
        }

        return null;
    }
}

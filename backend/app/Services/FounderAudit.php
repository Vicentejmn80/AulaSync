<?php

namespace App\Services;

use App\Models\FounderAuditLog;
use App\Models\User;
use Illuminate\Support\Collection;

class FounderAudit
{
    public function record(
        ?User $actor,
        string $action,
        string $summary,
        array $changes = [],
        ?string $targetType = null,
        ?int $targetId = null,
    ): void {
        FounderAuditLog::query()->create([
            'actor_user_id' => $actor?->id,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'summary' => mb_substr($summary, 0, 500),
            'changes' => $changes === [] ? null : $changes,
            'created_at' => now(),
        ]);
    }

    public function recent(int $limit = 20): Collection
    {
        return FounderAuditLog::query()
            ->with('actor:id,name,email')
            ->latest('id')
            ->limit($limit)
            ->get();
    }
}

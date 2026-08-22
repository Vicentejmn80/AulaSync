<?php

namespace App\Services;

use App\Models\ProductEvent;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Privacy-conscious product telemetry. Stores event, intent/category,
 * action, result and optional usage metrics. Never stores prompt or reply text.
 */
class ProductTelemetry
{
    public function record(array $payload): void
    {
        try {
            $user = $payload['user'] ?? null;
            $userId = $payload['user_id'] ?? ($user instanceof User ? $user->id : null);
            $role = $payload['role'] ?? ($user instanceof User ? $user->role : null);
            $colegioId = $payload['colegio_id'] ?? ($user instanceof User ? $user->colegio_id : null);

            ProductEvent::create([
                'user_id' => $userId,
                'colegio_id' => $colegioId,
                'role' => $role,
                'source' => (string) ($payload['source'] ?? 'system'),
                'event' => (string) ($payload['event'] ?? 'event'),
                'action' => isset($payload['action']) ? mb_substr((string) $payload['action'], 0, 80) : null,
                'category' => $payload['category'] ?? $this->categoryFor((string) ($payload['action'] ?? '')),
                'status' => (string) ($payload['status'] ?? 'success'),
                'duration_ms' => isset($payload['duration_ms']) ? (int) $payload['duration_ms'] : null,
                'error_code' => isset($payload['error_code']) ? mb_substr((string) $payload['error_code'], 0, 80) : null,
                'prompt_tokens' => $payload['prompt_tokens'] ?? null,
                'completion_tokens' => $payload['completion_tokens'] ?? null,
                'estimated_cost_usd' => $payload['estimated_cost_usd'] ?? null,
                'meta' => $this->sanitizeMeta($payload['meta'] ?? null),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('product_telemetry.failed', ['error' => $e->getMessage()]);
        }
    }

    public function categoryFor(string $action): string
    {
        $action = mb_strtolower($action);

        return match (true) {
            str_contains($action, 'plan') || str_contains($action, 'bulk') || $action === 'createactivity' => 'planning',
            str_contains($action, 'student') || str_contains($action, 'enroll') || str_contains($action, 'teacher') || str_contains($action, 'course') => 'roster',
            str_contains($action, 'grade') || str_contains($action, 'evaluat') || str_contains($action, 'query') => 'academic',
            str_contains($action, 'document') || str_contains($action, 'intelligence') => 'intelligence',
            str_contains($action, 'login') => 'auth',
            default => 'other',
        };
    }

    private function sanitizeMeta(mixed $meta): ?array
    {
        if (! is_array($meta)) {
            return null;
        }

        $blocked = ['prompt', 'message', 'raw_text', 'content', 'reply', 'arguments', 'input', 'output', 'extraction', 'review'];
        $clean = [];
        foreach ($meta as $key => $value) {
            if (in_array(mb_strtolower((string) $key), $blocked, true)) {
                continue;
            }
            if (is_scalar($value) || $value === null) {
                $clean[$key] = $value;
            }
        }

        return $clean === [] ? null : $clean;
    }
}

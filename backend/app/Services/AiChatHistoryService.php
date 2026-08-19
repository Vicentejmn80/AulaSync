<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class AiChatHistoryService
{
    public const SESSION_KEY = 'ai_chat_history';

    private const TTL_SECONDS = 86400;

    /**
     * @return array<int,array<string,mixed>>
     */
    public function load(?int $userId): array
    {
        if (! $userId) {
            return [];
        }

        $fromSession = session(self::SESSION_KEY);
        if (is_array($fromSession) && $fromSession !== []) {
            return $this->normalize($fromSession);
        }

        $fromCache = Cache::get($this->cacheKey($userId), []);

        return $this->normalize(is_array($fromCache) ? $fromCache : []);
    }

    /**
     * @param  array<int,mixed>  $messages
     * @return array<int,array<string,mixed>>
     */
    public function store(int $userId, array $messages): array
    {
        $normalized = $this->normalize($messages);
        session([self::SESSION_KEY => $normalized]);
        Cache::put($this->cacheKey($userId), $normalized, self::TTL_SECONDS);
        $this->rememberUserId($userId);

        return $normalized;
    }

    public function forget(?int $userId): void
    {
        session()->forget(self::SESSION_KEY);
        if ($userId) {
            Cache::forget($this->cacheKey($userId));
        }
    }

    public function pruneAll(): int
    {
        $ids = array_map('intval', Cache::get('ai_chat_history.keys', []));
        foreach ($ids as $id) {
            Cache::forget($this->cacheKey($id));
        }
        Cache::forget('ai_chat_history.keys');

        return count($ids);
    }

    private function rememberUserId(int $userId): void
    {
        $ids = array_map('intval', Cache::get('ai_chat_history.keys', []));
        $ids[] = $userId;
        Cache::put('ai_chat_history.keys', array_values(array_unique($ids)), self::TTL_SECONDS);
    }

    public function cacheKey(int $userId): string
    {
        return 'ai_chat_history.'.$userId;
    }

    /**
     * @param  array<int,mixed>  $messages
     * @return array<int,array<string,mixed>>
     */
    private function normalize(array $messages): array
    {
        return collect($messages)
            ->filter(fn ($item) => is_array($item) && isset($item['role']))
            ->map(function (array $item) {
                return [
                    'role' => (string) $item['role'],
                    'text' => (string) ($item['text'] ?? $item['content'] ?? ''),
                    'activity' => is_array($item['activity'] ?? null) ? $item['activity'] : null,
                ];
            })
            ->filter(fn (array $item) => in_array($item['role'], ['user', 'assistant', 'action', 'activity_created'], true))
            ->take(-40)
            ->values()
            ->all();
    }
}

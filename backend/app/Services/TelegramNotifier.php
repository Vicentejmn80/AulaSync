<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramNotifier
{
    public function isEnabled(): bool
    {
        $token = trim((string) config('services.telegram.bot_token'));
        $chatId = trim((string) config('services.telegram.chat_id'));

        return config('services.telegram.enabled', false)
            && $token !== ''
            && $chatId !== '';
    }

    public function sendMessage(string $message): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $token = config('services.telegram.bot_token');
        $chatId = config('services.telegram.chat_id');

        try {
            $response = Http::timeout(8)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ]);

            if (! $response->successful()) {
                Log::warning('telegram.send.failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('telegram.send.exception', [
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }
}

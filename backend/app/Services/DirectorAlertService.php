<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;

class DirectorAlertService
{
    public function __construct(
        private readonly TelegramNotifier $telegram
    ) {}

    /**
     * Notifica a todos los directores del colegio (in-app + Telegram opcional).
     */
    public function notifyDirectors(
        int $colegioId,
        string $title,
        string $message,
        string $link,
        ?string $telegramPrefix = null
    ): void {
        $directors = User::query()
            ->where('role', 'director')
            ->where('colegio_id', $colegioId)
            ->get(['id']);

        foreach ($directors as $director) {
            Notification::create([
                'user_id' => $director->id,
                'colegio_id' => $colegioId,
                'title' => $title,
                'message' => $message,
                'link' => $link,
            ]);
        }

        if ($directors->isNotEmpty()) {
            $this->sendTelegramAlert($title, $message, $link, $telegramPrefix);
        }
    }

    private function sendTelegramAlert(
        string $title,
        string $message,
        string $link,
        ?string $prefix = null
    ): void {
        if (! $this->telegram->isEnabled()) {
            return;
        }

        $appUrl = rtrim((string) config('app.url'), '/');
        $absoluteLink = str_starts_with($link, 'http') ? $link : $appUrl . $link;

        $lines = array_filter([
            $prefix ? "<b>{$prefix}</b>" : null,
            "<b>{$this->escapeHtml($title)}</b>",
            $this->escapeHtml($message),
            "<a href=\"{$absoluteLink}\">Abrir en Aulasync</a>",
        ]);

        $this->telegram->sendMessage(implode("\n\n", $lines));
    }

    private function escapeHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

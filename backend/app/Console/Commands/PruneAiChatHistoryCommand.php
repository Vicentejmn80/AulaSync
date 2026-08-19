<?php

namespace App\Console\Commands;

use App\Services\AiChatHistoryService;
use Illuminate\Console\Command;

class PruneAiChatHistoryCommand extends Command
{
    protected $signature = 'ai:prune-chat-history';

    protected $description = 'Elimina historiales efímeros del chatbot (sesión/caché de 24h).';

    public function handle(AiChatHistoryService $history): int
    {
        $count = $history->pruneAll();
        $this->info("Se limpiaron {$count} historial(es) del chatbot.");

        return self::SUCCESS;
    }
}

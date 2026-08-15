<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\SupabaseAuthService;
use Illuminate\Console\Command;

class SyncUsersToSupabaseAuth extends Command
{
    protected $signature = 'supabase:sync-users {--dry-run : List users without creating Auth accounts}';

    protected $description = 'Create Supabase Auth users for Laravel accounts missing supabase_id';

    public function handle(SupabaseAuthService $supabase): int
    {
        if (! $supabase->isConfigured()) {
            $this->error('Supabase Auth sync is disabled. Set SUPABASE_URL, SUPABASE_SERVICE_ROLE_KEY and SUPABASE_AUTH_SYNC=true in .env');

            return self::FAILURE;
        }

        $query = User::query()->whereNull('supabase_id')->orderBy('id');
        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('All Laravel users already have supabase_id.');

            return self::SUCCESS;
        }

        $this->info("Found {$total} user(s) without Supabase Auth link.");

        if ($this->option('dry-run')) {
            $query->get(['id', 'email', 'role'])->each(fn (User $u) => $this->line("  #{$u->id} {$u->email} ({$u->role})"));

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $synced = 0;
        foreach ($query->cursor() as $user) {
            if ($supabase->provisionExistingUser($user)) {
                $synced++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Linked {$synced}/{$total} users in Supabase Auth.");
        $this->comment('Backfilled users got a random Auth password. They should use “Olvidé mi contraseña” if you switch to Supabase login later. Laravel login is unchanged.');

        return self::SUCCESS;
    }
}

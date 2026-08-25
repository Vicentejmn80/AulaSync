<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResetFounderMetricsCommand extends Command
{
    protected $signature = 'founder:metrics-reset
        {--dry-run : Solo mostrar cuántos registros se borrarían}
        {--since= : Mantener datos desde esta fecha (YYYY-MM-DD) y borrar lo anterior}
        {--include-sessions : Incluir tabla sessions en la limpieza}';

    protected $description = 'Limpia historial de métricas/logs del Founder Center sin borrar usuarios ni colegios.';

    public function handle(): int
    {
        $sinceInput = $this->option('since');
        $since = null;

        if (is_string($sinceInput) && $sinceInput !== '') {
            try {
                $since = Carbon::parse($sinceInput)->startOfDay();
            } catch (\Throwable) {
                $this->error('Fecha inválida en --since. Usa formato YYYY-MM-DD.');

                return self::FAILURE;
            }
        }

        $targets = [
            ['table' => 'product_events', 'column' => 'created_at', 'label' => 'Eventos de producto'],
            ['table' => 'director_ai_operation_logs', 'column' => 'created_at', 'label' => 'Logs IA Director'],
            ['table' => 'failed_jobs', 'column' => 'failed_at', 'label' => 'Jobs fallidos'],
        ];

        if ($this->option('include-sessions')) {
            $targets[] = ['table' => 'sessions', 'column' => 'last_activity', 'label' => 'Sesiones'];
        }

        $total = 0;

        foreach ($targets as $target) {
            $table = $target['table'];
            $column = $target['column'];

            if (! Schema::hasTable($table)) {
                $this->warn("Saltando {$table}: no existe.");
                continue;
            }

            $query = DB::table($table);
            if ($since) {
                if ($column === 'last_activity') {
                    $query->where($column, '<', $since->timestamp);
                } else {
                    $query->where($column, '<', $since);
                }
            }

            $count = (clone $query)->count();
            $total += $count;

            if ($this->option('dry-run')) {
                $scope = $since ? "antes de {$since->toDateString()}" : 'todos';
                $this->line("[dry-run] {$target['label']}: {$count} registro(s) {$scope}.");
                continue;
            }

            $deleted = $query->delete();
            $this->info("{$target['label']}: {$deleted} registro(s) eliminados.");
        }

        if ($this->option('dry-run')) {
            $this->comment("Total a eliminar: {$total} registro(s).");
        } else {
            $this->info("Limpieza finalizada. Total eliminado: {$total} registro(s).");
        }

        return self::SUCCESS;
    }
}

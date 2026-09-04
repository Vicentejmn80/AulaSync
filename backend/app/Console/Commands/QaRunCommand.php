<?php

namespace App\Console\Commands;

use App\Support\Qa\QaReportWriter;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class QaRunCommand extends Command
{
    protected $signature = 'qa:run
        {--skip-reset : No reconstruir el colegio QA}
        {--skip-phpunit : Saltar tests HTTP/PHPUnit}
        {--skip-e2e : Saltar Playwright}';

    protected $description = 'Ejecuta la validación QA de AulaSync y escribe el reporte.';

    public function handle(QaReportWriter $writer): int
    {
        $phpunitOk = true;
        $e2eOk = true;

        if (! $this->option('skip-reset')) {
            $this->call('demo:reset');
        }

        if (! $this->option('skip-phpunit')) {
            $this->info('PHPUnit suite Qa…');
            $phpunitOk = $this->runProcess([
                PHP_BINARY,
                'artisan',
                'test',
                '--testsuite=Qa',
                '--testdox',
            ]);
        }

        if (! $this->option('skip-e2e')) {
            $this->info('Playwright E2E…');
            $e2eOk = $this->runProcess(
                $this->isWindows()
                    ? ['cmd', '/c', 'npx', 'playwright', 'test', '--config=e2e/playwright.config.ts']
                    : ['npx', 'playwright', 'test', '--config=e2e/playwright.config.ts']
            );
        }

        $resultsPath = storage_path('app/qa/results.json');
        $results = is_file($resultsPath)
            ? (json_decode((string) file_get_contents($resultsPath), true) ?: [])
            : [];
        if ($results !== []) {
            $this->line($writer->write($results));
        }

        return $phpunitOk && $e2eOk ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  list<string>  $command
     */
    private function runProcess(array $command): bool
    {
        $process = new Process($command, base_path());
        $process->setTimeout(1800);
        $process->run(function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });

        return $process->isSuccessful();
    }

    private function isWindows(): bool
    {
        return PHP_OS_FAMILY === 'Windows';
    }
}

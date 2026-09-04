<?php

namespace App\Console\Commands;

use App\Support\Qa\QaSchool;
use App\Support\Qa\QaSchoolEnvironment;
use Illuminate\Console\Command;

class DemoResetCommand extends Command
{
    protected $signature = 'demo:reset
        {--destroy-only : Solo elimina el colegio QA, no lo vuelve a crear}';

    protected $description = 'Destruye y reconstruye AulaSync QA School (solo datos de prueba).';

    public function handle(QaSchoolEnvironment $environment): int
    {
        $this->warn('Esto SOLO toca colegios AulaSync QA / emails @'.QaSchool::EMAIL_DOMAIN.'.');

        if ($this->option('destroy-only')) {
            $environment->destroy();
            $this->info('Entorno QA eliminado.');

            return self::SUCCESS;
        }

        $manifest = $environment->reset();
        $this->printAccounts($manifest);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function printAccounts(array $manifest): void
    {
        $this->newLine();
        $this->info('AulaSync QA School reconstruido.');
        $this->line('Colegio: '.$manifest['school']['name'].' ('.$manifest['school']['code'].')');
        $this->line('Contraseña de todas las cuentas QA: '.QaSchool::PASSWORD);
        $this->newLine();

        $this->table(
            ['Rol', 'Nombre', 'Email'],
            array_merge(
                [[
                    'director',
                    $manifest['director']['name'],
                    $manifest['director']['email'],
                ]],
                array_map(fn ($row) => ['profesor', $row['name'], $row['email']], $manifest['teachers']),
                array_map(fn ($row) => ['representante', $row['name'], $row['email']], array_slice($manifest['parents'], 0, 5)),
                [['representante', '…', 'representante.qa.06–20@'.QaSchool::EMAIL_DOMAIN]],
                [[
                    'director (otra escuela)',
                    $manifest['other']['director']['name'],
                    $manifest['other']['director']['email'],
                ]],
                [[
                    'profesor (otra escuela)',
                    $manifest['other']['teacher']['name'],
                    $manifest['other']['teacher']['email'],
                ]],
                [[
                    'representante (otra escuela)',
                    $manifest['other']['parent']['name'],
                    $manifest['other']['parent']['email'],
                ]],
            )
        );

        $this->line('Cuentas: storage/app/qa/accounts.json');
        $this->line('Alumnos: Alumno QA 01 … Alumno QA 40 (2 por representante).');
        $this->comment('Siguiente: php artisan qa:run   o   npm run test:e2e');
    }
}

<?php

namespace Database\Seeders;

use App\Support\Qa\QaSchoolEnvironment;
use Illuminate\Database\Seeder;

class QaSchoolSeeder extends Seeder
{
    public function run(): void
    {
        $manifest = app(QaSchoolEnvironment::class)->reset();
        $this->command?->info('QA School: '.$manifest['school']['name']);
        $this->command?->info('Director: '.$manifest['director']['email']);
    }
}

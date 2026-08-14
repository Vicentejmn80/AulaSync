<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * En MySQL, TEXT trunca a ~64 KB; LONGTEXT evita límites prácticos para descripciones Markdown largas.
     * SQLite almacena TEXT sin límite práctico; no requiere cambio.
     */
    public function up(): void
    {
        if (! Schema::hasTable('activities')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE activities MODIFY description LONGTEXT NULL');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('activities')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE activities MODIFY description TEXT NULL');
        }
    }
};

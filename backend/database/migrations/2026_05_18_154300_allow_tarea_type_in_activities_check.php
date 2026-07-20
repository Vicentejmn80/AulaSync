<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('activities') || ! Schema::hasColumn('activities', 'type')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE activities DROP CONSTRAINT IF EXISTS activities_type_check');
            DB::statement("ALTER TABLE activities ADD CONSTRAINT activities_type_check CHECK (type IN ('clase', 'actividad', 'tarea'))");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('activities') || ! Schema::hasColumn('activities', 'type')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::table('activities')
                ->where('type', 'tarea')
                ->update([
                    'type' => 'actividad',
                    'is_homework' => true,
                ]);

            DB::statement('ALTER TABLE activities DROP CONSTRAINT IF EXISTS activities_type_check');
            DB::statement("ALTER TABLE activities ADD CONSTRAINT activities_type_check CHECK (type IN ('clase', 'actividad'))");
        }
    }
};

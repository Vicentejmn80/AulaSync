<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('activities', 'type')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE activities MODIFY type VARCHAR(50) NOT NULL DEFAULT 'actividad'");
            return;
        }

        Schema::table('activities', function (Blueprint $table) {
            $table->string('type', 50)->default('actividad')->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('activities', 'type')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE activities MODIFY type ENUM('clase','actividad') NOT NULL DEFAULT 'actividad'");
            return;
        }

        Schema::table('activities', function (Blueprint $table) {
            $table->enum('type', ['clase', 'actividad'])->default('actividad')->change();
        });
    }
};

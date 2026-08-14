<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasInstitution = Schema::hasColumn('user_settings', 'nombre_institucion');
        $hasModel = Schema::hasColumn('user_settings', 'modelo_pedagogico');
        $hasAssignedSubjects = Schema::hasColumn('user_settings', 'materias_asignadas');

        Schema::table('user_settings', function (Blueprint $table) use ($hasInstitution, $hasModel, $hasAssignedSubjects) {
            if (! $hasInstitution) {
                $table->string('nombre_institucion')->nullable()->after('user_id');
            }
            if (! $hasModel) {
                $table->string('modelo_pedagogico')->nullable()->after('nombre_institucion');
            }
            if (! $hasAssignedSubjects) {
                $table->json('materias_asignadas')->nullable()->after('materias');
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_settings', function (Blueprint $table) {
            if (Schema::hasColumn('user_settings', 'nombre_institucion')) {
                $table->dropColumn('nombre_institucion');
            }
            if (Schema::hasColumn('user_settings', 'modelo_pedagogico')) {
                $table->dropColumn('modelo_pedagogico');
            }
            if (Schema::hasColumn('user_settings', 'materias_asignadas')) {
                $table->dropColumn('materias_asignadas');
            }
        });
    }
};


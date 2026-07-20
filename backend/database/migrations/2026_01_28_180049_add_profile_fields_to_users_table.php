<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nivel_educativo')->nullable()->after('email');
            $table->string('asignatura_principal')->nullable()->after('nivel_educativo');
            $table->json('horario_clases')->nullable()->after('asignatura_principal');
            $table->boolean('onboarding_completed')->default(false)->after('horario_clases');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['nivel_educativo', 'asignatura_principal', 'horario_clases', 'onboarding_completed']);
        });
    }
};

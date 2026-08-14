<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('user_settings', 'lesson_template')) {
                $table->string('lesson_template', 50)
                    ->default('clasica')
                    ->after('estilo_pedagogico')
                    ->comment('Estructura visual de clases: clasica | directa | constructivista');
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_settings', function (Blueprint $table) {
            if (Schema::hasColumn('user_settings', 'lesson_template')) {
                $table->dropColumn('lesson_template');
            }
        });
    }
};

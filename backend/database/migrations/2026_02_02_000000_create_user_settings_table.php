<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('materias')->nullable()->comment('Materias que imparte');
            $table->json('dias_clase')->nullable()->comment('Días de clase (lunes, martes, ...)');
            $table->string('nivel_educativo')->nullable();
            $table->json('cursos_grados')->nullable()->comment('Cursos o grados que imparte');
            $table->string('estilo_pedagogico')->nullable()->comment('Formato preferido: inicio_desarrollo_cierre, etc.');
            $table->string('tono')->nullable()->comment('amigable, formal, motivador');
            $table->unsignedTinyInteger('clases_semana')->nullable();
            $table->unsignedSmallInteger('duracion_clase_min')->nullable();
            $table->json('preferencias')->nullable()->comment('Otras preferencias (incluir, horarios, etc.)');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_settings');
    }
};

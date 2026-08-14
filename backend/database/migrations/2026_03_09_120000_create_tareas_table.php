<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tareas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actividad_id')
                ->constrained('activities')
                ->cascadeOnDelete();
            $table->string('titulo', 180);
            $table->text('descripcion')->nullable();
            $table->date('fecha_entrega')->nullable();
            $table->unsignedInteger('puntos')->default(20);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tareas');
    }
};

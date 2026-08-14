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
        Schema::create('manual_plannings', function (Blueprint $table) {
            $table->id();
            // Relación con usuarios (profesores) - Esta sí existe
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            
            // Relación con cursos - Esta sí existe
            $table->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete();
            
            // IMPORTANTE: subject_id como un número simple. 
            // NO agregamos ->constrained() para que no busque la tabla 'subjects' todavía.
            $table->unsignedBigInteger('subject_id')->nullable();
            
            $table->unsignedSmallInteger('month')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->json('sessions');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manual_plannings');
    }
};
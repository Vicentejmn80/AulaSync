<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')
                  ->constrained('users')
                  ->cascadeOnDelete()
                  ->comment('Profesor que registró al alumno');
            $table->string('name');
            $table->string('grade')->comment('Ej: 3ro Primaria');
            $table->string('section', 10)->nullable()->comment('Ej: A, B');
            $table->timestamps();

            $table->index(['teacher_id', 'grade', 'section']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};

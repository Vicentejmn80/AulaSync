<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')
                  ->constrained('users')
                  ->cascadeOnDelete();
            $table->string('subject_name')->comment('Ej: Matemáticas, Inglés');
            $table->string('grade')->comment('Ej: 3ro Primaria');
            $table->string('section', 10)->nullable()->comment('Ej: A, B');
            $table->string('school_year', 9)->default('2025-2026')->comment('Año escolar');
            $table->timestamps();

            $table->index(['teacher_id', 'grade', 'section']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};

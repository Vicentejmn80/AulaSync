<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('grades');

        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')
                  ->constrained('activities')
                  ->cascadeOnDelete();
            $table->foreignId('student_id')
                  ->constrained('students')
                  ->cascadeOnDelete();
            $table->decimal('score', 6, 2);
            $table->text('feedback_text')->nullable()
                  ->comment('Retroalimentación generada por IA o escrita por el profesor');
            $table->timestamps();

            $table->unique(['activity_id', 'student_id'], 'one_grade_per_student');
            $table->index(['student_id', 'activity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grades');
    }
};

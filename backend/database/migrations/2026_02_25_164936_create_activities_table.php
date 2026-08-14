<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop stub table created by a previous empty scaffolded migration
        Schema::dropIfExists('activities');

        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')
                  ->constrained('users')
                  ->cascadeOnDelete();
            $table->foreignId('course_id')
                  ->constrained('courses')
                  ->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('max_score')->default(20);
            $table->decimal('weight_percentage', 5, 2)->default(0)
                  ->comment('Porcentaje que representa dentro del período, ej: 25.00');
            $table->date('due_date')->nullable();
            $table->timestamps();

            $table->index(['course_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};

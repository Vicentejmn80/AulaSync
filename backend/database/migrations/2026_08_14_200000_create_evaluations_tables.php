<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete();
            $table->unsignedBigInteger('colegio_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('topic')->nullable();
            $table->string('mode', 20)->default('digital');
            $table->string('status', 20)->default('draft');
            $table->string('difficulty', 20)->nullable();
            $table->string('question_mix', 30)->nullable();
            $table->unsignedInteger('question_count')->default(0);
            $table->boolean('generated_by_ai')->default(false);
            $table->text('instructions')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->unsignedInteger('total_points')->default(0);
            $table->unsignedInteger('passing_score')->default(0);
            $table->json('rubric')->nullable();
            $table->json('physical_format')->nullable();
            $table->boolean('large_print')->default(false);
            $table->string('public_token', 64)->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('evaluation_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->constrained('evaluations')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('type', 30)->default('multiple_choice');
            $table->text('text');
            $table->json('options')->nullable();
            $table->text('correct_answer')->nullable();
            $table->unsignedInteger('points')->default(1);
            $table->string('topic')->nullable();
            $table->timestamps();
        });

        Schema::create('evaluation_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->constrained('evaluations')->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->string('student_name')->nullable();
            $table->json('answers')->nullable();
            $table->decimal('score', 6, 2)->nullable();
            $table->string('status', 20)->default('submitted');
            $table->json('ai_feedback')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_attempts');
        Schema::dropIfExists('evaluation_questions');
        Schema::dropIfExists('evaluations');
    }
};

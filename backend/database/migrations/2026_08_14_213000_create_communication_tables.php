<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('colegio_id')->nullable()->constrained('colegios')->nullOnDelete();
            $table->string('title');
            $table->longText('body');
            $table->json('targeting')->nullable();
            $table->json('attachments')->nullable();
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->enum('status', ['draft', 'scheduled', 'sent'])->default('draft');
            $table->timestamps();
        });

        Schema::create('communication_announcement_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained('communication_announcements')->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->string('recipient_name');
            $table->string('recipient_type')->default('student');
            $table->dateTime('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('communication_threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->string('contact_name');
            $table->string('contact_role')->default('estudiante');
            $table->text('last_message_preview')->nullable();
            $table->dateTime('last_message_at')->nullable();
            $table->timestamps();
            $table->index(['teacher_id', 'last_message_at']);
        });

        Schema::create('communication_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained('communication_threads')->cascadeOnDelete();
            $table->string('sender_role');
            $table->longText('body');
            $table->boolean('ai_suggested')->default(false);
            $table->dateTime('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('course_evaluation_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->timestamps();
        });

        Schema::create('course_evaluation_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('course_evaluation_plans')->cascadeOnDelete();
            $table->string('unit_name');
            $table->string('assessment_type');
            $table->decimal('weight_percentage', 5, 2)->default(0);
            $table->date('due_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_evaluation_plan_items');
        Schema::dropIfExists('course_evaluation_plans');
        Schema::dropIfExists('communication_messages');
        Schema::dropIfExists('communication_threads');
        Schema::dropIfExists('communication_announcement_reads');
        Schema::dropIfExists('communication_announcements');
    }
};


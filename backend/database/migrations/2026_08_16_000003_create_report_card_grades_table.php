<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_card_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_card_id')->constrained('report_cards')->cascadeOnDelete();
            $table->unsignedBigInteger('course_id');        // Snapshot reference
            $table->string('course_name', 180);             // Snapshot of name at generation time
            $table->decimal('grade', 6, 2);                 // Numeric average 0-100
            $table->string('letter_grade', 5)->nullable();  // A, B+, C+, D, F
            $table->text('teacher_observations')->nullable();
            $table->boolean('is_manual')->default(false);   // True if director edited
            $table->timestamps();

            $table->unique(['report_card_id', 'course_id'], 'one_grade_per_course');
            $table->index('course_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_card_grades');
    }
};

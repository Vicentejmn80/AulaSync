<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('academic_period_id')->constrained('academic_periods')->cascadeOnDelete();
            $table->foreignId('colegio_id')->constrained('colegios')->cascadeOnDelete();
            $table->string('status', 30)->default('draft'); // draft | published
            $table->text('observations')->nullable();        // Director's general note
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'academic_period_id'], 'one_report_card_per_period');
            $table->index(['colegio_id', 'status']);
            $table->index(['academic_period_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_cards');
    }
};

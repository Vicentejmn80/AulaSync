<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('colegio_id')->constrained('colegios')->cascadeOnDelete();
            $table->string('name', 120); // "1er Lapso 2025-2026"
            $table->date('start_date');
            $table->date('end_date');
            $table->date('report_card_due_date')->nullable();
            $table->string('status', 20)->default('active'); // active | closed
            $table->timestamps();

            $table->index(['colegio_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_periods');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('director_ai_operation_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('director_user_id');
            $table->unsignedBigInteger('colegio_id')->nullable();
            $table->string('intent', 80);
            $table->string('status', 40)->default('received');
            $table->json('input_payload')->nullable();
            $table->json('result_payload')->nullable();
            $table->json('error_payload')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['director_user_id', 'created_at']);
            $table->index(['colegio_id', 'intent']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('director_ai_operation_logs');
    }
};

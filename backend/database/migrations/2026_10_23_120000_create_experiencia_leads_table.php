<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('experiencia_leads', function (Blueprint $table) {
            $table->id();
            $table->uuid('spin_id')->unique();
            $table->string('tablet_id', 40);
            $table->string('rol', 40)->nullable();
            $table->string('nombre', 80);
            $table->string('apellido', 80)->nullable();
            $table->string('email', 160)->nullable()->index();
            $table->string('colegio', 160);
            $table->string('cargo', 40);
            $table->string('telefono', 24)->nullable();
            $table->string('problema', 500)->nullable();
            $table->string('premio_id', 80);
            $table->string('premio_nombre', 160);
            $table->string('premio_condicion', 300)->nullable();
            $table->string('codigo', 16);
            $table->string('puntaje', 16)->nullable();
            $table->boolean('consentimiento')->default(false);
            $table->string('email_status', 16)->default('skipped');
            $table->string('telegram_status', 16)->default('skipped');
            $table->string('webhook_status', 16)->default('skipped');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('experiencia_leads');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('family_invites', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('colegio_id');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('family_code', 20);
            $table->string('invite_code', 20)->unique();
            $table->timestamp('revoked_at')->nullable();
            $table->unsignedBigInteger('revoked_by')->nullable();
            $table->timestamps();

            $table->unique(['colegio_id', 'family_code']);
            $table->index('invite_code');
            $table->index('colegio_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('family_invites');
    }
};

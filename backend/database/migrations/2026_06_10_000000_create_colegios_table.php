<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('colegios', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('invite_code', 20)->unique();
            $table->foreignId('director_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('director_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('colegios');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('planificacions', function (Blueprint $table) {
            $table->id();
            // Relacionamos con el usuario
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            $table->string('tema');
            $table->text('objetivo');
            $table->string('slug')->nullable(); // El slug para la URL
            $table->json('payload');            // ¡Aquí es donde van tus sesiones!
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('planificacions');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intelligence_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete();
            $table->unsignedBigInteger('colegio_id')->nullable()->index();
            $table->string('original_name');
            $table->string('disk_path');
            $table->string('mime_type', 120)->nullable();
            $table->unsignedInteger('size_bytes')->default(0);
            $table->string('kind', 40)->default('otro');
            $table->string('status', 30)->default('uploaded');
            $table->decimal('confidence', 3, 2)->nullable();
            $table->json('extraction')->nullable();
            $table->json('review')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
            $table->index(['teacher_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intelligence_documents');
    }
};

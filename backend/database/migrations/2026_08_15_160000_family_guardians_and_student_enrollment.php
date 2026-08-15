<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (! Schema::hasColumn('students', 'document_id')) {
                $table->string('document_id', 40)->nullable()->after('section');
            }
            if (! Schema::hasColumn('students', 'birthdate')) {
                $table->date('birthdate')->nullable();
            }
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropUnique(['family_code']);
        });

        Schema::table('students', function (Blueprint $table) {
            $table->index(['colegio_id', 'family_code']);
        });

        if (! Schema::hasTable('guardian_student')) {
            Schema::create('guardian_student', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
                $table->string('relationship', 40)->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'student_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('guardian_student');

        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex(['colegio_id', 'family_code']);
            $table->unique('family_code');
            if (Schema::hasColumn('students', 'birthdate')) {
                $table->dropColumn('birthdate');
            }
            if (Schema::hasColumn('students', 'document_id')) {
                $table->dropColumn('document_id');
            }
        });
    }
};

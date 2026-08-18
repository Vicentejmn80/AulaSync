<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_invites', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('colegio_id');
            $table->unsignedBigInteger('created_by');
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('invite_code', 20)->unique();
            $table->json('course_ids')->nullable();
            $table->string('subject_name')->nullable();
            $table->string('grade')->nullable();
            $table->string('section', 10)->nullable();
            $table->unsignedBigInteger('claimed_by')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();

            $table->index('colegio_id');
            $table->index('invite_code');
        });

        if (Schema::hasTable('courses') && ! Schema::hasColumn('courses', 'invite_code')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->string('invite_code', 40)->nullable()->unique()->after('school_year');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('courses') && Schema::hasColumn('courses', 'invite_code')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->dropUnique(['invite_code']);
                $table->dropColumn('invite_code');
            });
        }

        Schema::dropIfExists('teacher_invites');
    }
};

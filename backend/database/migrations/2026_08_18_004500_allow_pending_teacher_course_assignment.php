<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('courses') && ! Schema::hasColumn('courses', 'teacher_invite_id')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->unsignedBigInteger('teacher_invite_id')->nullable()->after('teacher_id');
                $table->index('teacher_invite_id');
            });
        }

        if (Schema::hasTable('courses') && Schema::hasColumn('courses', 'teacher_id')) {
            $driver = Schema::getConnection()->getDriverName();
            if ($driver === 'pgsql') {
                DB::statement('ALTER TABLE courses ALTER COLUMN teacher_id DROP NOT NULL');
            } elseif ($driver === 'mysql') {
                Schema::table('courses', function (Blueprint $table) {
                    $table->dropForeign(['teacher_id']);
                });
                DB::statement('ALTER TABLE courses MODIFY teacher_id BIGINT UNSIGNED NULL');
                Schema::table('courses', function (Blueprint $table) {
                    $table->foreign('teacher_id')->references('id')->on('users')->nullOnDelete();
                });
            } elseif ($driver === 'sqlite') {
                Schema::table('courses', function (Blueprint $table) {
                    $table->unsignedBigInteger('teacher_id')->nullable()->change();
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('courses') && Schema::hasColumn('courses', 'teacher_invite_id')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->dropIndex(['teacher_invite_id']);
                $table->dropColumn('teacher_invite_id');
            });
        }
    }
};

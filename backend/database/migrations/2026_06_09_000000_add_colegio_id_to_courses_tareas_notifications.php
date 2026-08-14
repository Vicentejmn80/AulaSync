<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            if (! Schema::hasColumn('courses', 'colegio_id')) {
                $table->integer('colegio_id')->nullable()->after('teacher_id');
                $table->index('colegio_id');
            }
        });

        Schema::table('tareas', function (Blueprint $table) {
            if (! Schema::hasColumn('tareas', 'colegio_id')) {
                $table->integer('colegio_id')->nullable()->after('actividad_id');
                $table->index('colegio_id');
            }
        });

        Schema::table('notifications', function (Blueprint $table) {
            if (! Schema::hasColumn('notifications', 'colegio_id')) {
                $table->integer('colegio_id')->nullable()->after('user_id');
                $table->index('colegio_id');
            }
        });

        DB::statement("
            UPDATE courses
            SET colegio_id = (SELECT colegio_id FROM users WHERE users.id = courses.teacher_id)
            WHERE colegio_id IS NULL
        ");

        DB::statement("
            UPDATE tareas
            SET colegio_id = (SELECT a.colegio_id FROM activities a WHERE a.id = tareas.actividad_id)
            WHERE colegio_id IS NULL
        ");

        DB::statement("
            UPDATE notifications
            SET colegio_id = (SELECT u.colegio_id FROM users u WHERE u.id = notifications.user_id)
            WHERE colegio_id IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            if (Schema::hasColumn('courses', 'colegio_id')) {
                $table->dropIndex(['colegio_id']);
                $table->dropColumn('colegio_id');
            }
        });

        Schema::table('tareas', function (Blueprint $table) {
            if (Schema::hasColumn('tareas', 'colegio_id')) {
                $table->dropIndex(['colegio_id']);
                $table->dropColumn('colegio_id');
            }
        });

        Schema::table('notifications', function (Blueprint $table) {
            if (Schema::hasColumn('notifications', 'colegio_id')) {
                $table->dropIndex(['colegio_id']);
                $table->dropColumn('colegio_id');
            }
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'colegio_id')) {
                $table->integer('colegio_id')->nullable()->after('id');
            }
        });

        Schema::table('planificacions', function (Blueprint $table) {
            if (! Schema::hasColumn('planificacions', 'colegio_id')) {
                $table->integer('colegio_id')->nullable()->after('user_id');
            }
        });

        Schema::table('activities', function (Blueprint $table) {
            if (! Schema::hasColumn('activities', 'colegio_id')) {
                $table->integer('colegio_id')->nullable()->after('teacher_id');
            }
        });

        Schema::table('students', function (Blueprint $table) {
            if (! Schema::hasColumn('students', 'colegio_id')) {
                $table->integer('colegio_id')->nullable()->after('teacher_id');
            }
        });

        Schema::table('grades', function (Blueprint $table) {
            if (! Schema::hasColumn('grades', 'colegio_id')) {
                $table->integer('colegio_id')->nullable()->after('student_id');
            }
        });

        // Backfill using correlated subqueries (works on PostgreSQL and SQLite)
        DB::table('users')->whereNull('colegio_id')->update(['colegio_id' => 1]);

        DB::statement("
            UPDATE planificacions
            SET colegio_id = (SELECT colegio_id FROM users WHERE users.id = planificacions.user_id)
            WHERE colegio_id IS NULL
        ");

        DB::statement("
            UPDATE activities
            SET colegio_id = (SELECT colegio_id FROM users WHERE users.id = activities.teacher_id)
            WHERE colegio_id IS NULL
        ");

        DB::statement("
            UPDATE students
            SET colegio_id = (SELECT colegio_id FROM users WHERE users.id = students.teacher_id)
            WHERE colegio_id IS NULL
        ");

        DB::statement("
            UPDATE grades
            SET colegio_id = (SELECT colegio_id FROM activities WHERE activities.id = grades.activity_id)
            WHERE colegio_id IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            $table->dropColumn('colegio_id');
        });
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('colegio_id');
        });
        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn('colegio_id');
        });
        Schema::table('planificacions', function (Blueprint $table) {
            $table->dropColumn('colegio_id');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('colegio_id');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('grades')) {
            Schema::table('grades', function (Blueprint $table) {
                if (! Schema::hasColumn('grades', 'status')) {
                    $table->string('status', 20)->default('draft')->after('score');
                    $table->index('status');
                }

                if (! Schema::hasColumn('grades', 'published_at')) {
                    $table->timestamp('published_at')->nullable()->after('status');
                }
            });
        }

        if (Schema::hasTable('course_student')) {
            Schema::table('course_student', function (Blueprint $table) {
                if (! Schema::hasColumn('course_student', 'nota_actual')) {
                    $table->decimal('nota_actual', 8, 2)->default(0)->after('enrolled_at');
                }

                if (! Schema::hasColumn('course_student', 'promedio_acumulado')) {
                    $table->decimal('promedio_acumulado', 8, 2)->default(0)->after('nota_actual');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('grades')) {
            Schema::table('grades', function (Blueprint $table) {
                if (Schema::hasColumn('grades', 'published_at')) {
                    $table->dropColumn('published_at');
                }

                if (Schema::hasColumn('grades', 'status')) {
                    $table->dropIndex(['status']);
                    $table->dropColumn('status');
                }
            });
        }

        if (Schema::hasTable('course_student')) {
            Schema::table('course_student', function (Blueprint $table) {
                if (Schema::hasColumn('course_student', 'promedio_acumulado')) {
                    $table->dropColumn('promedio_acumulado');
                }

                if (Schema::hasColumn('course_student', 'nota_actual')) {
                    $table->dropColumn('nota_actual');
                }
            });
        }
    }
};

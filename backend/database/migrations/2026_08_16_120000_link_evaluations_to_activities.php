<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('evaluations') && ! Schema::hasColumn('evaluations', 'activity_id')) {
            Schema::table('evaluations', function (Blueprint $table) {
                $table->foreignId('activity_id')
                    ->nullable()
                    ->after('course_id')
                    ->constrained('activities')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('activities') && ! Schema::hasColumn('activities', 'evaluation_id')) {
            Schema::table('activities', function (Blueprint $table) {
                $table->foreignId('evaluation_id')
                    ->nullable()
                    ->after('course_id')
                    ->constrained('evaluations')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('evaluations') && Schema::hasColumn('evaluations', 'activity_id')) {
            Schema::table('evaluations', function (Blueprint $table) {
                $table->dropConstrainedForeignId('activity_id');
            });
        }

        if (Schema::hasTable('activities') && Schema::hasColumn('activities', 'evaluation_id')) {
            Schema::table('activities', function (Blueprint $table) {
                $table->dropConstrainedForeignId('evaluation_id');
            });
        }
    }
};

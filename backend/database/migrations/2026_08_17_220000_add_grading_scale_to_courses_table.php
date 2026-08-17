<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('courses') || Schema::hasColumn('courses', 'grading_scale')) {
            return;
        }

        Schema::table('courses', function (Blueprint $table) {
            $table->string('grading_scale', 8)->default('1-20')->after('school_year');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('courses') || ! Schema::hasColumn('courses', 'grading_scale')) {
            return;
        }

        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('grading_scale');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            if (! Schema::hasColumn('activities', 'nee_student_id')) {
                $table->unsignedBigInteger('nee_student_id')->nullable()->after('nee_adaptation');
            }
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            if (Schema::hasColumn('activities', 'nee_student_id')) {
                $table->dropColumn('nee_student_id');
            }
        });
    }
};

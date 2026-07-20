<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['id_curso', 'id_docente', 'id_modulo'] as $col) {
            if (Schema::hasColumn('activities', $col)) {
                Schema::table('activities', function ($table) use ($col) {
                    $table->unsignedBigInteger($col)->nullable()->change();
                });
            }
        }

        if (! Schema::hasColumn('activities', 'plan_block_id')) {
            Schema::table('activities', function ($table) {
                $table->unsignedBigInteger('plan_block_id')->nullable()->after('course_id');
            });
        }
    }

    public function down(): void
    {
        foreach (['id_curso', 'id_docente', 'id_modulo'] as $col) {
            if (Schema::hasColumn('activities', $col)) {
                Schema::table('activities', function ($table) use ($col) {
                    $table->unsignedBigInteger($col)->nullable(false)->change();
                });
            }
        }
    }
};

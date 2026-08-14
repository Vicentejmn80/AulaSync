<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            if (! Schema::hasColumn('activities', 'plan_block_id')) {
                $table->unsignedBigInteger('plan_block_id')->nullable()->after('course_id');
                $table->foreign('plan_block_id')
                      ->references('id')->on('planificacions')
                      ->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            if (Schema::hasColumn('activities', 'plan_block_id')) {
                $table->dropForeign(['plan_block_id']);
                $table->dropColumn('plan_block_id');
            }
        });
    }
};

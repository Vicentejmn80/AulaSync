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
        Schema::table('director_ai_operation_logs', function (Blueprint $table) {
            $table->string('action_plan_id', 64)->nullable()->after('id');
            $table->string('action_id', 64)->nullable()->after('action_plan_id');

            $table->index(['action_plan_id', 'action_id']);
            $table->index(['action_plan_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('director_ai_operation_logs', function (Blueprint $table) {
            $table->dropIndex(['action_plan_id', 'action_id']);
            $table->dropIndex(['action_plan_id', 'status']);
            $table->dropColumn(['action_plan_id', 'action_id']);
        });
    }
};

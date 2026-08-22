<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'last_login_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->timestamp('last_login_at')->nullable()->after('onboarding_completed');
                $table->index('last_login_at');
            });
        }

        Schema::create('product_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('colegio_id')->nullable()->index();
            $table->string('role', 32)->nullable();
            $table->string('source', 40);
            $table->string('event', 60);
            $table->string('action', 80)->nullable();
            $table->string('category', 40)->nullable();
            $table->string('status', 24)->default('success');
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('error_code', 80)->nullable();
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->decimal('estimated_cost_usd', 10, 6)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['created_at']);
            $table->index(['source', 'created_at']);
            $table->index(['action', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['colegio_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_events');

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'last_login_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex(['last_login_at']);
                $table->dropColumn('last_login_at');
            });
        }
    }
};

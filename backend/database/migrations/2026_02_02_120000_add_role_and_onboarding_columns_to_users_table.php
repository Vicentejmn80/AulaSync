<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasRole = Schema::hasColumn('users', 'role');
        $hasOnboardingCompleted = Schema::hasColumn('users', 'onboarding_completed');

        Schema::table('users', function (Blueprint $table) use ($hasRole, $hasOnboardingCompleted) {
            if (! $hasRole) {
                $table->enum('role', ['profesor', 'director'])->default('profesor')->after('email');
            }

            if (! $hasOnboardingCompleted) {
                $table->boolean('onboarding_completed')->default(false)->after('role');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role');
            });
        }
    }
};


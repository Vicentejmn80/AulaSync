<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('email', 'vicentejmn80@gmail.com')
            ->update([
                'role' => 'super_admin',
                'onboarding_completed' => DB::raw('true'),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('users')
            ->where('email', 'vicentejmn80@gmail.com')
            ->update([
                'role' => 'director',
                'updated_at' => now(),
            ]);
    }
};

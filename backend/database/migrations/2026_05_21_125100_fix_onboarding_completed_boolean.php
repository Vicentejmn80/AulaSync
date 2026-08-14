<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'onboarding_completed')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE users ALTER COLUMN onboarding_completed DROP DEFAULT');
            DB::statement('ALTER TABLE users ALTER COLUMN onboarding_completed TYPE boolean USING (onboarding_completed::boolean)');
            DB::statement('ALTER TABLE users ALTER COLUMN onboarding_completed SET DEFAULT false');
            return;
        }

        // For MySQL/SQLite, rely on schema builder where supported.
        Schema::table('users', function ($table) {
            $table->boolean('onboarding_completed')->default(false)->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'onboarding_completed')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE users ALTER COLUMN onboarding_completed DROP DEFAULT');
            DB::statement('ALTER TABLE users ALTER COLUMN onboarding_completed TYPE integer USING (CASE WHEN onboarding_completed THEN 1 ELSE 0 END)');
            DB::statement('ALTER TABLE users ALTER COLUMN onboarding_completed SET DEFAULT 0');
            return;
        }

        Schema::table('users', function ($table) {
            $table->integer('onboarding_completed')->default(0)->change();
        });
    }
};

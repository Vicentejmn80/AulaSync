<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $driver = Schema::getConnection()->getDriverName();

            if ($driver === 'pgsql') {
                DB::statement('ALTER TABLE activities ALTER COLUMN is_homework DROP DEFAULT');
                DB::statement('ALTER TABLE activities ALTER COLUMN is_homework TYPE smallint USING CASE WHEN is_homework THEN 1 ELSE 0 END');
                DB::statement('ALTER TABLE activities ALTER COLUMN is_homework SET DEFAULT 0');
                DB::statement('ALTER TABLE activities ALTER COLUMN is_homework SET NOT NULL');
            } elseif ($driver === 'mysql') {
                $table->boolean('is_homework')->default(false)->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $driver = Schema::getConnection()->getDriverName();

            if ($driver === 'pgsql') {
                DB::statement('ALTER TABLE activities ALTER COLUMN is_homework DROP DEFAULT');
                DB::statement('ALTER TABLE activities ALTER COLUMN is_homework TYPE boolean USING CASE WHEN is_homework = 1 THEN true ELSE false END');
                DB::statement('ALTER TABLE activities ALTER COLUMN is_homework SET DEFAULT false');
                DB::statement('ALTER TABLE activities ALTER COLUMN is_homework SET NOT NULL');
            } elseif ($driver === 'mysql') {
                $table->boolean('is_homework')->default(false)->change();
            }
        });
    }
};

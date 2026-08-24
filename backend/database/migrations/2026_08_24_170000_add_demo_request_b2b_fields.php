<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demo_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('demo_requests', 'last_name')) {
                $table->string('last_name')->nullable()->after('name');
            }
            if (! Schema::hasColumn('demo_requests', 'estado_region')) {
                $table->string('estado_region')->nullable()->after('school_size');
            }
        });
    }

    public function down(): void
    {
        Schema::table('demo_requests', function (Blueprint $table) {
            if (Schema::hasColumn('demo_requests', 'last_name')) {
                $table->dropColumn('last_name');
            }
            if (Schema::hasColumn('demo_requests', 'estado_region')) {
                $table->dropColumn('estado_region');
            }
        });
    }
};

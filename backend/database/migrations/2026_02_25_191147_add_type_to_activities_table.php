<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            if (! Schema::hasColumn('activities', 'type')) {
                // 'clase' = theoretical lesson content
                // 'actividad' = graded exercise / evaluation
                $table->enum('type', ['clase', 'actividad'])
                      ->default('actividad')
                      ->after('title');
            }
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            if (Schema::hasColumn('activities', 'type')) {
                $table->dropColumn('type');
            }
        });
    }
};

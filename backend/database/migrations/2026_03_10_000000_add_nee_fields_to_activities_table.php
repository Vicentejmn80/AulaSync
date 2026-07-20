<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            if (! Schema::hasColumn('activities', 'nee_type')) {
                $table->string('nee_type')->nullable()->after('is_homework');
            }
            if (! Schema::hasColumn('activities', 'nee_adaptation')) {
                $table->text('nee_adaptation')->nullable()->after('nee_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            if (Schema::hasColumn('activities', 'nee_adaptation')) {
                $table->dropColumn('nee_adaptation');
            }
            if (Schema::hasColumn('activities', 'nee_type')) {
                $table->dropColumn('nee_type');
            }
        });
    }
};

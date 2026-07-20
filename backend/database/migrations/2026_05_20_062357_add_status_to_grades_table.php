PHP
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
        Schema::table('grades', function (Blueprint $table) {
            // Solo agrega 'status' si NO existe
            if (!Schema::hasColumn('grades', 'status')) {
                $table->string('status')->default('draft')->after('score');
            }
            
            // Solo agrega 'published_at' si NO existe
            if (!Schema::hasColumn('grades', 'published_at')) {
                $table->timestamp('published_at')->nullable()->after('status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn('grades', 'status')) $columns[] = 'status';
            if (Schema::hasColumn('grades', 'published_at')) $columns[] = 'published_at';
            
            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
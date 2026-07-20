<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tareas', function (Blueprint $table) {
            if (! Schema::hasColumn('tareas', 'calificacion')) {
                $table->decimal('calificacion', 5, 2)->nullable()->after('puntos');
            }
            if (! Schema::hasColumn('tareas', 'feedback')) {
                $table->text('feedback')->nullable()->after('calificacion');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tareas', function (Blueprint $table) {
            if (Schema::hasColumn('tareas', 'feedback')) {
                $table->dropColumn('feedback');
            }
            if (Schema::hasColumn('tareas', 'calificacion')) {
                $table->dropColumn('calificacion');
            }
        });
    }
};

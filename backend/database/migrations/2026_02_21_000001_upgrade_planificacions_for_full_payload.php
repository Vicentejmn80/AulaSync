<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('planificacions')) {
            return;
        }

        Schema::table('planificacions', function (Blueprint $table) {
            if (! Schema::hasColumn('planificacions', 'user_id')) {
                // SQLite limita agregar FK en alter table; se agrega índice para mantener compatibilidad local.
                if (Schema::getConnection()->getDriverName() === 'sqlite') {
                    $table->unsignedBigInteger('user_id')->nullable()->index();
                } else {
                    $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                }
            }

            if (! Schema::hasColumn('planificacions', 'payload')) {
                $table->json('payload')->nullable();
            }

            if (! Schema::hasColumn('planificacions', 'slug')) {
                $table->string('slug')->nullable()->unique();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('planificacions')) {
            return;
        }

        Schema::table('planificacions', function (Blueprint $table) {
            if (Schema::hasColumn('planificacions', 'slug')) {
                $table->dropUnique(['slug']);
                $table->dropColumn('slug');
            }

            if (Schema::hasColumn('planificacions', 'payload')) {
                $table->dropColumn('payload');
            }

            if (Schema::hasColumn('planificacions', 'user_id')) {
                if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                    $table->dropConstrainedForeignId('user_id');
                } else {
                    $table->dropColumn('user_id');
                }
            }
        });
    }
};


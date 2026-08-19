<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('teacher_invites')) {
            return;
        }

        Schema::table('teacher_invites', function (Blueprint $table) {
            if (! Schema::hasColumn('teacher_invites', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('claimed_at');
                $table->index('expires_at');
            }
            if (! Schema::hasColumn('teacher_invites', 'revoked_at')) {
                $table->timestamp('revoked_at')->nullable()->after('expires_at');
                $table->index('revoked_at');
            }
            if (! Schema::hasColumn('teacher_invites', 'revoked_by')) {
                $table->unsignedBigInteger('revoked_by')->nullable()->after('revoked_at');
                $table->index('revoked_by');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('teacher_invites')) {
            return;
        }

        Schema::table('teacher_invites', function (Blueprint $table) {
            if (Schema::hasColumn('teacher_invites', 'revoked_by')) {
                $table->dropIndex(['revoked_by']);
                $table->dropColumn('revoked_by');
            }
            if (Schema::hasColumn('teacher_invites', 'revoked_at')) {
                $table->dropIndex(['revoked_at']);
                $table->dropColumn('revoked_at');
            }
            if (Schema::hasColumn('teacher_invites', 'expires_at')) {
                $table->dropIndex(['expires_at']);
                $table->dropColumn('expires_at');
            }
        });
    }
};

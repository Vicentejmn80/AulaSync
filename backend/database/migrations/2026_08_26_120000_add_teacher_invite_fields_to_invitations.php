<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invitations')) {
            return;
        }

        Schema::table('invitations', function (Blueprint $table) {
            if (! Schema::hasColumn('invitations', 'name')) {
                $table->string('name')->nullable()->after('email');
            }
            if (! Schema::hasColumn('invitations', 'teacher_invite_id')) {
                $table->unsignedBigInteger('teacher_invite_id')->nullable()->after('student_id');
                $table->index('teacher_invite_id');
            }
        });

        Schema::table('invitations', function (Blueprint $table) {
            $table->string('token', 64)->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('invitations')) {
            return;
        }

        Schema::table('invitations', function (Blueprint $table) {
            if (Schema::hasColumn('invitations', 'teacher_invite_id')) {
                $table->dropIndex(['teacher_invite_id']);
                $table->dropColumn('teacher_invite_id');
            }
            if (Schema::hasColumn('invitations', 'name')) {
                $table->dropColumn('name');
            }
        });
    }
};

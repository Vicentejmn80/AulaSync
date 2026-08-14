<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('role', 'teacher')
            ->update(['role' => 'profesor']);
    }

    public function down(): void
    {
        // No revertimos a "teacher": "profesor" es el estándar canónico del producto.
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('colegios', function (Blueprint $table) {
            if (! Schema::hasColumn('colegios', 'codes_pin')) {
                $table->string('codes_pin', 255)->nullable()->after('invite_code');
            }
        });

        if (! Schema::hasColumn('colegios', 'codes_pin')) {
            return;
        }

        $colegios = DB::table('colegios')->select('id', 'invite_code', 'codes_pin')->get();
        foreach ($colegios as $colegio) {
            if (! empty($colegio->codes_pin)) {
                continue;
            }

            $digits = preg_replace('/\D+/', '', (string) $colegio->invite_code) ?: '0000';
            $pin = substr($digits, -4);
            if (strlen($pin) < 4) {
                $pin = str_pad($pin, 4, '0', STR_PAD_LEFT);
            }

            DB::table('colegios')->where('id', $colegio->id)->update([
                'codes_pin' => Hash::make($pin),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('colegios', function (Blueprint $table) {
            if (Schema::hasColumn('colegios', 'codes_pin')) {
                $table->dropColumn('codes_pin');
            }
        });
    }
};

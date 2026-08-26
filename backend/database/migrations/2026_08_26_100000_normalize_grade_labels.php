<?php

use App\Support\GradeLabel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['courses', 'students'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'grade')) {
                continue;
            }

            $rows = DB::table($table)->select('id', 'grade')->whereNotNull('grade')->get();
            foreach ($rows as $row) {
                $canonical = GradeLabel::canonical((string) $row->grade);
                if ($canonical && $canonical !== $row->grade) {
                    DB::table($table)->where('id', $row->id)->update(['grade' => $canonical]);
                }
            }
        }
    }

    public function down(): void
    {
        // Canonical labels stay readable; no reverse mapping.
    }
};

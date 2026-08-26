<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('materias')) {
            Schema::create('materias', function (Blueprint $table) {
                $table->id();
                $table->foreignId('colegio_id')->constrained('colegios')->cascadeOnDelete();
                $table->string('name');
                $table->timestamps();
                $table->unique(['colegio_id', 'name']);
            });
        }

        if (Schema::hasTable('courses') && ! Schema::hasColumn('courses', 'materia_id')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->foreignId('materia_id')->nullable()->after('colegio_id')->constrained('materias')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('courses') || ! Schema::hasColumn('courses', 'materia_id')) {
            return;
        }

        $rows = DB::table('courses')
            ->select('id', 'colegio_id', 'subject_name')
            ->whereNotNull('subject_name')
            ->where('subject_name', '!=', '')
            ->get();

        $cache = [];
        foreach ($rows as $row) {
            $colegioId = (int) $row->colegio_id;
            $name = trim((string) $row->subject_name);
            if ($colegioId < 1 || $name === '') {
                continue;
            }
            $key = $colegioId.'|'.mb_strtolower($name);
            if (! isset($cache[$key])) {
                $existing = DB::table('materias')
                    ->where('colegio_id', $colegioId)
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                    ->value('id');
                if (! $existing) {
                    $existing = DB::table('materias')->insertGetId([
                        'colegio_id' => $colegioId,
                        'name' => $name,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                $cache[$key] = $existing;
            }

            DB::table('courses')->where('id', $row->id)->update(['materia_id' => $cache[$key]]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('courses') && Schema::hasColumn('courses', 'materia_id')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->dropConstrainedForeignId('materia_id');
            });
        }
        Schema::dropIfExists('materias');
    }
};

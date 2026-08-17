<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('course_evaluation_plans')) {
            return;
        }

        $this->mergeDuplicatePlans();

        if (! $this->hasUniqueIndex('course_evaluation_plans', 'course_evaluation_plans_teacher_course_unique')) {
            Schema::table('course_evaluation_plans', function (Blueprint $table) {
                $table->unique(['teacher_id', 'course_id'], 'course_evaluation_plans_teacher_course_unique');
            });
        }

        if (Schema::hasTable('course_evaluation_plan_items') && Schema::hasColumn('course_evaluation_plan_items', 'evaluation_id')) {
            Schema::table('course_evaluation_plan_items', function (Blueprint $table) {
                $table->dropConstrainedForeignId('evaluation_id');
            });
            Schema::table('course_evaluation_plan_items', function (Blueprint $table) {
                $table->foreignId('evaluation_id')->nullable()->after('plan_id')->constrained('evaluations')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('course_evaluation_plans') && $this->hasUniqueIndex('course_evaluation_plans', 'course_evaluation_plans_teacher_course_unique')) {
            Schema::table('course_evaluation_plans', function (Blueprint $table) {
                $table->dropUnique('course_evaluation_plans_teacher_course_unique');
            });
        }

        if (Schema::hasTable('course_evaluation_plan_items') && Schema::hasColumn('course_evaluation_plan_items', 'evaluation_id')) {
            Schema::table('course_evaluation_plan_items', function (Blueprint $table) {
                $table->dropConstrainedForeignId('evaluation_id');
            });
            Schema::table('course_evaluation_plan_items', function (Blueprint $table) {
                $table->foreignId('evaluation_id')->nullable()->after('plan_id')->constrained('evaluations')->nullOnDelete();
            });
        }
    }

    /**
     * Collapse duplicate plans (same teacher_id + course_id) into the oldest one,
     * moving all items across and de-duplicating items that point to the same evaluation.
     */
    private function mergeDuplicatePlans(): void
    {
        $groups = DB::table('course_evaluation_plans')
            ->select('teacher_id', 'course_id')
            ->groupBy('teacher_id', 'course_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($groups as $group) {
            $plans = DB::table('course_evaluation_plans')
                ->where('teacher_id', $group->teacher_id)
                ->where('course_id', $group->course_id)
                ->orderBy('created_at')
                ->orderBy('id')
                ->get();

            $primary = $plans->first();
            $duplicates = $plans->slice(1);

            foreach ($duplicates as $dup) {
                DB::table('course_evaluation_plan_items')
                    ->where('plan_id', $dup->id)
                    ->update(['plan_id' => $primary->id]);

                DB::table('course_evaluation_plans')->where('id', $dup->id)->delete();
            }

            $this->dedupeItemsByEvaluation((int) $primary->id);
        }
    }

    private function dedupeItemsByEvaluation(int $planId): void
    {
        $items = DB::table('course_evaluation_plan_items')
            ->where('plan_id', $planId)
            ->whereNotNull('evaluation_id')
            ->orderBy('id')
            ->get();

        $seen = [];
        foreach ($items as $item) {
            if (isset($seen[$item->evaluation_id])) {
                DB::table('course_evaluation_plan_items')->where('id', $item->id)->delete();

                continue;
            }
            $seen[$item->evaluation_id] = true;
        }
    }

    private function hasUniqueIndex(string $table, string $indexName): bool
    {
        try {
            $indexes = Schema::getIndexes($table);
        } catch (\Throwable) {
            return false;
        }

        foreach ($indexes as $index) {
            if (($index['name'] ?? null) === $indexName) {
                return true;
            }
        }

        return false;
    }
};

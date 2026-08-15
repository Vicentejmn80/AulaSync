<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rubrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete();
            $table->foreignId('evaluation_id')->nullable()->constrained('evaluations')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('task_type')->nullable();
            $table->enum('type', ['analytic', 'holistic', 'single_point'])->default('analytic');
            $table->json('levels')->nullable();
            $table->unsignedInteger('total_points')->default(100);
            $table->boolean('generated_by_ai')->default(false);
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->timestamps();
        });

        Schema::create('rubric_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rubric_id')->constrained('rubrics')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('name');
            $table->decimal('weight_percentage', 5, 2)->default(0);
            $table->json('descriptors')->nullable();
            $table->timestamps();
        });

        Schema::table('course_evaluation_plan_items', function (Blueprint $table) {
            if (! Schema::hasColumn('course_evaluation_plan_items', 'evaluation_id')) {
                $table->foreignId('evaluation_id')->nullable()->after('plan_id')->constrained('evaluations')->nullOnDelete();
            }
            if (! Schema::hasColumn('course_evaluation_plan_items', 'category')) {
                $table->string('category', 30)->nullable()->after('assessment_type');
            }
            if (! Schema::hasColumn('course_evaluation_plan_items', 'learning_outcome')) {
                $table->string('learning_outcome')->nullable()->after('notes');
            }
        });

        Schema::table('course_evaluation_plans', function (Blueprint $table) {
            if (! Schema::hasColumn('course_evaluation_plans', 'formative_weight')) {
                $table->decimal('formative_weight', 5, 2)->nullable()->after('summary');
            }
            if (! Schema::hasColumn('course_evaluation_plans', 'summative_weight')) {
                $table->decimal('summative_weight', 5, 2)->nullable()->after('formative_weight');
            }
            if (! Schema::hasColumn('course_evaluation_plans', 'status')) {
                $table->string('status', 20)->default('draft')->after('summative_weight');
            }
        });
    }

    public function down(): void
    {
        Schema::table('course_evaluation_plans', function (Blueprint $table) {
            foreach (['formative_weight', 'summative_weight', 'status'] as $col) {
                if (Schema::hasColumn('course_evaluation_plans', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('course_evaluation_plan_items', function (Blueprint $table) {
            if (Schema::hasColumn('course_evaluation_plan_items', 'evaluation_id')) {
                $table->dropConstrainedForeignId('evaluation_id');
            }
            foreach (['category', 'learning_outcome'] as $col) {
                if (Schema::hasColumn('course_evaluation_plan_items', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::dropIfExists('rubric_criteria');
        Schema::dropIfExists('rubrics');
    }
};

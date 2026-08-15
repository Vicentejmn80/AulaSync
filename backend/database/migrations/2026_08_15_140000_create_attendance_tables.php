<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attendance_reasons')) {
            Schema::create('attendance_reasons', function (Blueprint $table) {
                $table->id();
                $table->foreignId('colegio_id')->nullable()->constrained('colegios')->nullOnDelete();
                $table->string('code', 40);
                $table->string('label');
                $table->string('category', 20)->default('excused');
                $table->boolean('requires_comment')->default(false);
                $table->boolean('is_system')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['colegio_id', 'code']);
            });
        }

        if (! Schema::hasTable('attendances')) {
            Schema::create('attendances', function (Blueprint $table) {
                $table->id();
                $table->foreignId('colegio_id')->nullable()->constrained('colegios')->nullOnDelete();
                $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
                $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
                $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
                $table->date('attended_on');
                $table->string('status', 20)->default('present');
                $table->foreignId('reason_id')->nullable()->constrained('attendance_reasons')->nullOnDelete();
                $table->text('note')->nullable();
                $table->string('source', 20)->default('teacher');
                $table->uuid('client_uuid')->nullable()->unique();
                $table->timestamp('notified_at')->nullable();
                $table->timestamps();

                $table->unique(['student_id', 'course_id', 'attended_on']);
                $table->index(['colegio_id', 'attended_on', 'status']);
                $table->index(['teacher_id', 'attended_on']);
            });
        }

        if (! Schema::hasTable('absence_requests')) {
            Schema::create('absence_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('colegio_id')->nullable()->constrained('colegios')->nullOnDelete();
                $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
                $table->foreignId('parent_id')->constrained('users')->cascadeOnDelete();
                $table->string('kind', 20)->default('absence');
                $table->foreignId('reason_id')->nullable()->constrained('attendance_reasons')->nullOnDelete();
                $table->date('start_date');
                $table->date('end_date');
                $table->text('comment')->nullable();
                $table->string('status', 20)->default('pending');
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();

                $table->index(['student_id', 'start_date', 'end_date']);
                $table->index(['colegio_id', 'status']);
            });
        }

        $now = now();
        $reasons = [
            ['code' => 'illness', 'label' => 'Enfermedad', 'category' => 'excused', 'requires_comment' => false, 'sort_order' => 1],
            ['code' => 'medical', 'label' => 'Cita médica', 'category' => 'excused', 'requires_comment' => false, 'sort_order' => 2],
            ['code' => 'family', 'label' => 'Asunto familiar', 'category' => 'excused', 'requires_comment' => true, 'sort_order' => 3],
            ['code' => 'transport', 'label' => 'Transporte', 'category' => 'unexcused', 'requires_comment' => false, 'sort_order' => 4],
            ['code' => 'tardy_excused', 'label' => 'Retraso justificado', 'category' => 'tardy', 'requires_comment' => false, 'sort_order' => 5],
            ['code' => 'other', 'label' => 'Otro', 'category' => 'excused', 'requires_comment' => true, 'sort_order' => 6],
        ];

        foreach ($reasons as $reason) {
            $exists = DB::table('attendance_reasons')
                ->where('code', $reason['code'])
                ->whereNull('colegio_id')
                ->exists();

            if ($exists) {
                continue;
            }

            $requiresComment = $reason['requires_comment'] ? 'true' : 'false';
            DB::statement(
                "insert into attendance_reasons (code, label, category, requires_comment, sort_order, colegio_id, is_system, created_at, updated_at)
                 values (?, ?, ?, {$requiresComment}, ?, null, true, ?, ?)",
                [
                    $reason['code'],
                    $reason['label'],
                    $reason['category'],
                    $reason['sort_order'],
                    $now,
                    $now,
                ]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('absence_requests');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('attendance_reasons');
    }
};

<?php

namespace App\Services;

use App\Models\Colegio;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SchoolPurgeService
{
    /**
     * @return array<string, mixed>
     */
    public function export(Colegio $colegio): array
    {
        $colegioId = (int) $colegio->id;
        $userIds = $this->schoolUserIds($colegioId);
        $studentIds = $this->idsFrom('students', 'colegio_id', [$colegioId]);
        $courseIds = $this->idsFrom('courses', 'colegio_id', [$colegioId]);

        return [
            'exported_at' => now()->toIso8601String(),
            'note' => 'Respaldo de datos principales. No incluye contraseñas.',
            'colegio' => [
                'id' => $colegio->id,
                'name' => $colegio->name,
                'invite_code' => $colegio->invite_code,
                'director_user_id' => $colegio->director_user_id,
            ],
            'counts' => $this->counts($colegio),
            'users' => $this->rows('users', 'id', $userIds, ['password', 'remember_token', 'codes_pin']),
            'courses' => $this->rows('courses', 'id', $courseIds),
            'students' => $this->rows('students', 'id', $studentIds),
            'grades' => $this->rows('grades', 'colegio_id', [$colegioId]),
            'attendances' => $this->rows('attendances', 'colegio_id', [$colegioId]),
        ];
    }

    /**
     * @return array{usuarios: int, docentes: int, alumnos: int, cursos: int}
     */
    public function counts(Colegio $colegio): array
    {
        $colegioId = (int) $colegio->id;

        return [
            'usuarios' => User::query()->where('colegio_id', $colegioId)->where('role', '!=', 'super_admin')->count(),
            'docentes' => User::query()->where('colegio_id', $colegioId)->where('role', 'profesor')->count(),
            'alumnos' => Schema::hasTable('students')
                ? (int) DB::table('students')->where('colegio_id', $colegioId)->count()
                : 0,
            'cursos' => Schema::hasTable('courses')
                ? (int) DB::table('courses')->where('colegio_id', $colegioId)->count()
                : 0,
        ];
    }

    public function destroy(Colegio $colegio): void
    {
        $colegioId = (int) $colegio->id;

        User::query()
            ->where('colegio_id', $colegioId)
            ->where('role', 'super_admin')
            ->update(['colegio_id' => null]);

        $userIds = $this->schoolUserIds($colegioId);
        $studentIds = $this->idsFrom('students', 'colegio_id', [$colegioId]);
        $courseIds = $this->idsFrom('courses', 'colegio_id', [$colegioId]);
        $activityIds = $this->idsFrom('activities', 'course_id', $courseIds);
        if ($activityIds === [] && Schema::hasColumn('activities', 'colegio_id')) {
            $activityIds = $this->idsFrom('activities', 'colegio_id', [$colegioId]);
        }
        $evaluationIds = $this->idsFrom('evaluations', 'course_id', $courseIds);

        Schema::disableForeignKeyConstraints();

        try {
            DB::table('colegios')->where('id', $colegioId)->update(['director_user_id' => null]);
            if ($userIds !== []) {
                DB::table('colegios')->whereIn('director_user_id', $userIds)->update(['director_user_id' => null]);
            }

            $this->deleteIn('communication_messages', 'thread_id', $this->idsFrom('communication_threads', 'teacher_id', $userIds));
            $this->deleteIn('communication_threads', 'teacher_id', $userIds);
            $this->deleteIn('communication_threads', 'colegio_id', [$colegioId]);
            $this->deleteIn('communication_announcement_reads', 'announcement_id', $this->idsFrom('communication_announcements', 'teacher_id', $userIds));
            $this->deleteIn('communication_announcements', 'teacher_id', $userIds);
            $this->deleteIn('communication_announcements', 'colegio_id', [$colegioId]);

            $this->deleteIn('evaluation_attempts', 'evaluation_id', $evaluationIds);
            $this->deleteIn('evaluation_questions', 'evaluation_id', $evaluationIds);
            $this->deleteIn('evaluations', 'id', $evaluationIds);
            $this->deleteIn('evaluations', 'colegio_id', [$colegioId]);

            $this->deleteIn('grades', 'activity_id', $activityIds);
            $this->deleteIn('grades', 'colegio_id', [$colegioId]);
            $this->deleteIn('grade_audit_logs', 'student_id', $studentIds);
            $this->deleteIn('report_card_grades', 'student_id', $studentIds);
            $this->deleteIn('report_cards', 'student_id', $studentIds);
            $this->deleteIn('report_cards', 'colegio_id', [$colegioId]);

            $this->deleteIn('attendances', 'student_id', $studentIds);
            $this->deleteIn('attendances', 'colegio_id', [$colegioId]);
            $this->deleteIn('absence_requests', 'student_id', $studentIds);
            $this->deleteIn('absence_requests', 'colegio_id', [$colegioId]);

            $this->deleteIn('tareas', 'actividad_id', $activityIds);
            $this->deleteIn('tareas', 'colegio_id', [$colegioId]);
            $this->deleteIn('activities', 'id', $activityIds);
            $this->deleteIn('activities', 'colegio_id', [$colegioId]);

            $planIds = $this->idsFrom('course_evaluation_plans', 'course_id', $courseIds);
            $this->deleteIn('course_evaluation_plan_items', 'plan_id', $planIds);
            $this->deleteIn('course_evaluation_plans', 'course_id', $courseIds);

            $programIds = $this->idsFrom('training_programs', 'colegio_id', [$colegioId]);
            $lessonIds = $this->idsFrom('training_lessons', 'training_program_id', $programIds);
            $enrollmentIds = array_values(array_unique(array_merge(
                $this->idsFrom('training_enrollments', 'user_id', $userIds),
                $this->idsFrom('training_enrollments', 'training_program_id', $programIds),
            )));
            $this->deleteIn('training_progress', 'training_enrollment_id', $enrollmentIds);
            $this->deleteIn('training_progress', 'training_lesson_id', $lessonIds);
            $this->deleteIn('training_enrollments', 'id', $enrollmentIds);
            $this->deleteIn('training_lessons', 'id', $lessonIds);
            $this->deleteIn('training_programs', 'id', $programIds);

            $this->deleteIn('course_student', 'course_id', $courseIds);
            $this->deleteIn('guardian_student', 'student_id', $studentIds);
            $this->deleteIn('family_invites', 'colegio_id', [$colegioId]);
            $this->deleteIn('teacher_invites', 'colegio_id', [$colegioId]);
            $this->deleteIn('invitations', 'colegio_id', [$colegioId]);
            $this->deleteIn('notifications', 'user_id', $userIds);
            $this->deleteIn('notifications', 'colegio_id', [$colegioId]);
            $this->deleteIn('director_ai_operation_logs', 'director_user_id', $userIds);
            $this->deleteIn('director_ai_operation_logs', 'colegio_id', [$colegioId]);
            $this->deleteIn('intelligence_documents', 'teacher_id', $userIds);
            $this->deleteIn('intelligence_documents', 'colegio_id', [$colegioId]);
            $this->deleteIn('planificacions', 'user_id', $userIds);
            $this->deleteIn('planificacions', 'colegio_id', [$colegioId]);
            $this->deleteIn('subjects', 'course_id', $courseIds);
            $this->deleteIn('academic_periods', 'colegio_id', [$colegioId]);
            $this->deleteIn('attendance_reasons', 'colegio_id', [$colegioId]);
            $this->deleteIn('materias', 'colegio_id', [$colegioId]);
            $this->deleteIn('user_settings', 'user_id', $userIds);
            $this->deleteIn('product_events', 'colegio_id', [$colegioId]);
            $this->deleteIn('product_events', 'user_id', $userIds);
            $this->deleteIn('students', 'id', $studentIds);
            $this->deleteIn('courses', 'id', $courseIds);
            $this->deleteIn('users', 'id', $userIds);
            $this->deleteIn('colegios', 'id', [$colegioId]);
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    /**
     * @return list<int>
     */
    private function schoolUserIds(int $colegioId): array
    {
        return User::query()
            ->where('colegio_id', $colegioId)
            ->where('role', '!=', 'super_admin')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  list<int|string>  $ids
     * @param  list<string>  $hidden
     * @return list<array<string, mixed>>
     */
    private function rows(string $table, string $column, array $ids, array $hidden = []): array
    {
        if ($ids === [] || ! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return [];
        }

        return DB::table($table)
            ->whereIn($column, $ids)
            ->get()
            ->map(function ($row) use ($hidden) {
                $data = (array) $row;
                foreach ($hidden as $key) {
                    unset($data[$key]);
                }

                return $data;
            })
            ->all();
    }

    /**
     * @param  list<int|string>  $ids
     */
    private function deleteIn(string $table, string $column, array $ids): void
    {
        if ($ids === [] || ! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        DB::table($table)->whereIn($column, $ids)->delete();
    }

    /**
     * @param  list<int|string>  $ids
     * @return list<int>
     */
    private function idsFrom(string $table, string $column, array $ids): array
    {
        if ($ids === [] || ! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return [];
        }

        return DB::table($table)->whereIn($column, $ids)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }
}

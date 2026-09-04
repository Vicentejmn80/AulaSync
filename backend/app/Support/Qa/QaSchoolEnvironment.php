<?php

namespace App\Support\Qa;

use App\Models\Activity;
use App\Models\Attendance;
use App\Models\Colegio;
use App\Models\Course;
use App\Models\Evaluation;
use App\Models\EvaluationQuestion;
use App\Models\Grade;
use App\Models\Materia;
use App\Models\Student;
use App\Models\User;
use App\Services\FamilyInviteService;
use App\Support\DatabaseBoolean;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class QaSchoolEnvironment
{
    /**
     * @return array<string, mixed>
     */
    public function reset(): array
    {
        $this->destroy();

        return $this->seed();
    }

    public function destroy(): void
    {
        $colegioIds = Colegio::query()
            ->whereIn('invite_code', [QaSchool::SCHOOL_CODE, QaSchool::OTHER_SCHOOL_CODE])
            ->orWhere('name', 'like', 'AulaSync QA%')
            ->pluck('id')
            ->all();

        $userIds = User::query()
            ->where(function ($q) use ($colegioIds) {
                if ($colegioIds !== []) {
                    $q->whereIn('colegio_id', $colegioIds);
                }
                $q->orWhere('email', 'like', '%@'.QaSchool::EMAIL_DOMAIN);
            })
            ->pluck('id')
            ->all();

        if ($colegioIds === [] && $userIds === []) {
            return;
        }

        $studentIds = $colegioIds === []
            ? []
            : Student::query()->whereIn('colegio_id', $colegioIds)->pluck('id')->all();
        $courseIds = $colegioIds === []
            ? []
            : Course::query()->whereIn('colegio_id', $colegioIds)->pluck('id')->all();
        $activityIds = $courseIds === []
            ? []
            : Activity::query()->whereIn('course_id', $courseIds)->pluck('id')->all();
        $evaluationIds = $courseIds === []
            ? []
            : (Schema::hasTable('evaluations')
                ? Evaluation::query()->whereIn('course_id', $courseIds)->pluck('id')->all()
                : []);

        Schema::disableForeignKeyConstraints();

        try {
            if (Schema::hasTable('colegios') && $colegioIds !== []) {
                Colegio::query()->whereIn('id', $colegioIds)->update(['director_user_id' => null]);
            }

            $this->deleteIn('communication_messages', 'thread_id', $this->idsFrom('communication_threads', 'teacher_id', $userIds));
            $this->deleteIn('communication_threads', 'teacher_id', $userIds);
            $this->deleteIn('communication_announcement_reads', 'announcement_id', $this->idsFrom('communication_announcements', 'teacher_id', $userIds));
            $this->deleteIn('communication_announcements', 'teacher_id', $userIds);

            $this->deleteIn('evaluation_attempts', 'evaluation_id', $evaluationIds);
            $this->deleteIn('evaluation_questions', 'evaluation_id', $evaluationIds);
            $this->deleteIn('evaluations', 'id', $evaluationIds);

            $this->deleteIn('grades', 'activity_id', $activityIds);
            $this->deleteIn('grade_audit_logs', 'student_id', $studentIds);
            $this->deleteIn('report_card_grades', 'student_id', $studentIds);
            $this->deleteIn('report_cards', 'student_id', $studentIds);

            $this->deleteIn('attendances', 'student_id', $studentIds);
            $this->deleteIn('absence_requests', 'student_id', $studentIds);

            $this->deleteIn('tareas', 'actividad_id', $activityIds);
            $this->deleteIn('activities', 'id', $activityIds);

            $planIds = $this->idsFrom('course_evaluation_plans', 'course_id', $courseIds);
            $this->deleteIn('course_evaluation_plan_items', 'plan_id', $planIds);
            $this->deleteIn('course_evaluation_plans', 'course_id', $courseIds);

            $this->deleteIn('course_student', 'course_id', $courseIds);
            $this->deleteIn('guardian_student', 'student_id', $studentIds);
            $this->deleteIn('family_invites', 'colegio_id', $colegioIds);
            $this->deleteIn('teacher_invites', 'colegio_id', $colegioIds);
            $this->deleteIn('invitations', 'colegio_id', $colegioIds);
            $this->deleteIn('notifications', 'user_id', $userIds);
            $this->deleteIn('director_ai_operation_logs', 'director_user_id', $userIds);
            $this->deleteIn('director_ai_operation_logs', 'colegio_id', $colegioIds);
            $this->deleteIn('intelligence_documents', 'teacher_id', $userIds);
            $this->deleteIn('planificacions', 'user_id', $userIds);
            $this->deleteIn('subjects', 'course_id', $courseIds);
            $this->deleteIn('academic_periods', 'colegio_id', $colegioIds);
            $this->deleteIn('attendance_reasons', 'colegio_id', $colegioIds);
            $this->deleteIn('materias', 'colegio_id', $colegioIds);
            $this->deleteIn('user_settings', 'user_id', $userIds);
            $this->deleteIn('students', 'id', $studentIds);
            $this->deleteIn('courses', 'id', $courseIds);
            $this->deleteIn('users', 'id', $userIds);
            $this->deleteIn('colegios', 'id', $colegioIds);
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function seed(): array
    {
        return DB::transaction(function () {
            $school = $this->createSchool(QaSchool::SCHOOL_NAME, QaSchool::SCHOOL_CODE);
            $director = $this->createUser(
                'Director QA',
                QaSchool::directorEmail(),
                'director',
                $school->id
            );
            $school->update(['director_user_id' => $director->id]);

            $materias = $this->createMaterias($school);
            $teachers = $this->createTeachers($school);
            $courses = $this->createCourses($school, $teachers, $materias);
            [$students, $parents] = $this->createFamilies($school, $teachers, $courses);
            $this->createAcademicData($school, $teachers, $courses, $students);

            $other = $this->seedOtherSchool();

            $manifest = $this->manifest($school, $director, $teachers, $parents, $students, $courses, $other);
            $this->writeManifest($manifest);

            return $manifest;
        });
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    public function writeManifest(array $manifest): void
    {
        $dir = storage_path('app/qa');
        File::ensureDirectoryExists($dir);
        File::put(
            $dir.'/accounts.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function readManifest(): ?array
    {
        $path = storage_path('app/qa/accounts.json');
        if (! File::exists($path)) {
            return null;
        }

        $decoded = json_decode(File::get($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    private function createSchool(string $name, string $code): Colegio
    {
        return Colegio::create([
            'name' => $name,
            'invite_code' => $code,
            'codes_pin' => Colegio::hashPinFromInvite($code),
        ]);
    }

    private function createUser(string $name, string $email, string $role, int $colegioId, ?string $familyCode = null): User
    {
        return User::create([
            'name' => $name,
            'email' => $email,
            'password' => QaSchool::PASSWORD,
            'role' => $role,
            'colegio_id' => $colegioId,
            'onboarding_completed' => DatabaseBoolean::bind(true),
            'email_verified_at' => now(),
            'family_code' => $familyCode,
        ]);
    }

    /**
     * @return array<string, Materia>
     */
    private function createMaterias(Colegio $school): array
    {
        $names = ['Matemática', 'Lenguaje', 'Ciencias', 'Historia', 'Inglés'];
        $out = [];
        foreach ($names as $name) {
            $out[$name] = Materia::create([
                'colegio_id' => $school->id,
                'name' => $name,
            ]);
        }

        return $out;
    }

    /**
     * @return array<int, User>
     */
    private function createTeachers(Colegio $school): array
    {
        $teachers = [];
        foreach (QaSchool::teacherBlueprints() as $blueprint) {
            $teachers[$blueprint['index']] = $this->createUser(
                $blueprint['name'],
                QaSchool::teacherEmail($blueprint['index']),
                'profesor',
                $school->id
            );
        }

        return $teachers;
    }

    /**
     * @param  array<int, User>  $teachers
     * @param  array<string, Materia>  $materias
     * @return array<string, Course>
     */
    private function createCourses(Colegio $school, array $teachers, array $materias): array
    {
        $courses = [];
        foreach (QaSchool::teacherBlueprints() as $blueprint) {
            $teacher = $teachers[$blueprint['index']];
            foreach ($blueprint['subjects'] as $subject) {
                foreach ($blueprint['grades'] as $grade) {
                    $key = $subject.'|'.$grade;
                    $slug = Str::upper(Str::substr(Str::slug($subject), 0, 3)).'-'.$grade;
                    $courses[$key] = Course::create([
                        'teacher_id' => $teacher->id,
                        'colegio_id' => $school->id,
                        'materia_id' => $materias[$subject]->id ?? null,
                        'subject_name' => $subject,
                        'grade' => $grade,
                        'section' => QaSchool::SECTION,
                        'school_year' => QaSchool::SCHOOL_YEAR,
                        'invite_code' => 'QA-'.$slug,
                    ]);
                }
            }
        }

        return $courses;
    }

    /**
     * @param  array<int, User>  $teachers
     * @param  array<string, Course>  $courses
     * @return array{0: array<int, Student>, 1: array<int, User>}
     */
    private function createFamilies(Colegio $school, array $teachers, array $courses): array
    {
        $students = [];
        $parents = [];
        $families = app(FamilyInviteService::class);

        for ($parentIndex = 1; $parentIndex <= QaSchool::PARENT_COUNT; $parentIndex++) {
            $familyCode = QaSchool::familyCode($parentIndex);
            $firstStudent = (($parentIndex - 1) * 2) + 1;
            $secondStudent = $firstStudent + 1;

            $parent = $this->createUser(
                QaSchool::parentName($parentIndex),
                QaSchool::parentEmail($parentIndex),
                'representante',
                $school->id,
                $familyCode
            );
            $parents[$parentIndex] = $parent;

            foreach ([$firstStudent, $secondStudent] as $studentIndex) {
                $grade = QaSchool::studentGrade($studentIndex);
                $owningTeacher = $this->teacherForGrade($teachers, $grade);
                $student = Student::create([
                    'colegio_id' => $school->id,
                    'teacher_id' => $owningTeacher->id,
                    'name' => QaSchool::studentName($studentIndex),
                    'grade' => $grade,
                    'section' => QaSchool::SECTION,
                    'family_code' => $familyCode,
                ]);
                $parent->representedStudents()->attach($student->id, ['relationship' => 'padre']);

                foreach ($this->coursesForGrade($courses, $grade) as $course) {
                    $student->courses()->attach($course->id, ['enrolled_at' => now()]);
                }

                $families->ensureForStudent($student, $parent);
                $students[$studentIndex] = $student;
            }
        }

        return [$students, $parents];
    }

    /**
     * @param  array<int, User>  $teachers
     */
    private function teacherForGrade(array $teachers, string $grade): User
    {
        foreach (QaSchool::teacherBlueprints() as $blueprint) {
            if (in_array($grade, $blueprint['grades'], true)) {
                return $teachers[$blueprint['index']];
            }
        }

        return $teachers[5];
    }

    /**
     * @param  array<string, Course>  $courses
     * @return list<Course>
     */
    private function coursesForGrade(array $courses, string $grade): array
    {
        return array_values(array_filter(
            $courses,
            fn (Course $course) => $course->grade === $grade
        ));
    }

    /**
     * @param  array<int, User>  $teachers
     * @param  array<string, Course>  $courses
     * @param  array<int, Student>  $students
     */
    private function createAcademicData(Colegio $school, array $teachers, array $courses, array $students): void
    {
        foreach ($courses as $course) {
            $teacherId = (int) $course->teacher_id;
            $tarea = Activity::create([
                'teacher_id' => $teacherId,
                'course_id' => $course->id,
                'colegio_id' => $school->id,
                'title' => 'Tarea QA '.$course->subject_name.' '.$course->grade,
                'description' => 'Resolver la guía de '.$course->subject_name.' de '.$course->grade.'. Incluye procedimiento y justificación.',
                'notes' => 'Entregar en clase.',
                'due_date' => Carbon::now()->addDays(7)->toDateString(),
                'type' => Activity::TYPE_TAREA,
                'is_homework' => true,
                'max_score' => 20,
                'weight_percentage' => 20,
            ]);

            $examActivity = Activity::create([
                'teacher_id' => $teacherId,
                'course_id' => $course->id,
                'colegio_id' => $school->id,
                'title' => 'Evaluación QA '.$course->subject_name.' '.$course->grade,
                'description' => 'Evaluación escrita del tema en curso.',
                'due_date' => Carbon::now()->addDays(14)->toDateString(),
                'type' => Activity::TYPE_ACTIVIDAD,
                'max_score' => 20,
                'weight_percentage' => 30,
            ]);

            Activity::create([
                'teacher_id' => $teacherId,
                'course_id' => $course->id,
                'colegio_id' => $school->id,
                'title' => 'Clase QA '.$course->subject_name.' '.$course->grade,
                'description' => 'Sesión teórica de refuerzo.',
                'due_date' => Carbon::now()->addDays(2)->toDateString(),
                'type' => Activity::TYPE_CLASE,
                'max_score' => 20,
                'weight_percentage' => 0,
            ]);

            $evaluation = Evaluation::create([
                'teacher_id' => $teacherId,
                'course_id' => $course->id,
                'colegio_id' => $school->id,
                'activity_id' => $examActivity->id,
                'title' => 'Parcial QA '.$course->subject_name.' '.$course->grade,
                'topic' => $course->subject_name.' '.$course->grade,
                'description' => 'Evaluación de comprobación del tema.',
                'instructions' => 'Llegar 10 minutos antes.',
                'status' => 'published',
                'scheduled_at' => Carbon::now()->addDays(14)->setTime(8, 0),
                'total_points' => 20,
                'passing_score' => 10,
            ]);

            EvaluationQuestion::create([
                'evaluation_id' => $evaluation->id,
                'sort_order' => 1,
                'type' => 'open',
                'text' => 'Explica el concepto principal de '.$course->subject_name.'.',
                'points' => 10,
                'topic' => $course->subject_name,
            ]);

            $enrolled = $course->students()->get();
            foreach ($enrolled as $offset => $student) {
                $score = match ($offset % 3) {
                    0 => 18,
                    1 => 12,
                    default => 8,
                };
                Grade::create([
                    'activity_id' => $tarea->id,
                    'student_id' => $student->id,
                    'colegio_id' => $school->id,
                    'score' => $score,
                    'status' => 'published',
                    'published_at' => now(),
                    'feedback_text' => $score >= 12 ? 'Buen trabajo QA.' : 'Requiere refuerzo QA.',
                ]);
            }

            foreach ([0, 1, 2] as $dayOffset) {
                $date = Carbon::now()->subWeekdays($dayOffset)->toDateString();
                foreach ($enrolled as $offset => $student) {
                    $status = match (($offset + $dayOffset) % 5) {
                        0 => Attendance::STATUS_ABSENT,
                        1 => Attendance::STATUS_TARDY,
                        default => Attendance::STATUS_PRESENT,
                    };
                    Attendance::create([
                        'colegio_id' => $school->id,
                        'course_id' => $course->id,
                        'student_id' => $student->id,
                        'teacher_id' => $teacherId,
                        'attended_on' => $date,
                        'status' => $status,
                        'source' => 'teacher',
                        'client_uuid' => (string) Str::uuid(),
                    ]);
                }
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function seedOtherSchool(): array
    {
        $school = $this->createSchool(QaSchool::OTHER_SCHOOL_NAME, QaSchool::OTHER_SCHOOL_CODE);
        $director = $this->createUser('Director QA Other', QaSchool::otherDirectorEmail(), 'director', $school->id);
        $school->update(['director_user_id' => $director->id]);
        $teacher = $this->createUser('Docente QA Other', QaSchool::otherTeacherEmail(), 'profesor', $school->id);
        $parent = $this->createUser(
            'Representante QA Other',
            QaSchool::otherParentEmail(),
            'representante',
            $school->id,
            'FAM-QA-OT'
        );
        $student = Student::create([
            'colegio_id' => $school->id,
            'teacher_id' => $teacher->id,
            'name' => 'Alumno QA Other',
            'grade' => '1ro',
            'section' => 'A',
            'family_code' => 'FAM-QA-OT',
        ]);
        $parent->representedStudents()->attach($student->id, ['relationship' => 'padre']);
        $course = Course::create([
            'teacher_id' => $teacher->id,
            'colegio_id' => $school->id,
            'subject_name' => 'Matemática',
            'grade' => '1ro',
            'section' => 'A',
            'school_year' => QaSchool::SCHOOL_YEAR,
            'invite_code' => 'QA-OT-MAT-1RO',
        ]);
        $student->courses()->attach($course->id, ['enrolled_at' => now()]);
        $activity = Activity::create([
            'teacher_id' => $teacher->id,
            'course_id' => $course->id,
            'colegio_id' => $school->id,
            'title' => 'Tarea QA Other',
            'description' => 'Solo visible para la otra escuela.',
            'due_date' => Carbon::now()->addDays(5)->toDateString(),
            'type' => Activity::TYPE_TAREA,
            'is_homework' => true,
            'max_score' => 20,
            'weight_percentage' => 20,
        ]);
        app(FamilyInviteService::class)->ensureForStudent($student, $parent);

        return [
            'school' => ['id' => $school->id, 'name' => $school->name, 'code' => $school->invite_code],
            'director' => $this->accountPayload($director),
            'teacher' => $this->accountPayload($teacher),
            'parent' => $this->accountPayload($parent, ['Alumno QA Other']),
            'student' => ['id' => $student->id, 'name' => $student->name],
            'course_id' => $course->id,
            'activity_id' => $activity->id,
        ];
    }

    /**
     * @param  array<int, User>  $teachers
     * @param  array<int, User>  $parents
     * @param  array<int, Student>  $students
     * @param  array<string, Course>  $courses
     * @param  array<string, mixed>  $other
     * @return array<string, mixed>
     */
    private function manifest(
        Colegio $school,
        User $director,
        array $teachers,
        array $parents,
        array $students,
        array $courses,
        array $other
    ): array {
        $parentRows = [];
        foreach ($parents as $index => $parent) {
            $first = (($index - 1) * 2) + 1;
            $parentRows[] = $this->accountPayload($parent, [
                QaSchool::studentName($first),
                QaSchool::studentName($first + 1),
            ]) + [
                'student_ids' => [
                    $students[$first]->id,
                    $students[$first + 1]->id,
                ],
            ];
        }

        return [
            'password' => QaSchool::PASSWORD,
            'password_note' => 'Solo entorno QA. No usar en producción.',
            'school' => [
                'id' => $school->id,
                'name' => $school->name,
                'code' => $school->invite_code,
            ],
            'director' => $this->accountPayload($director),
            'teachers' => array_values(array_map(fn (User $t) => $this->accountPayload($t), $teachers)),
            'parents' => $parentRows,
            'students' => array_values(array_map(fn (Student $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'grade' => $s->grade,
                'section' => $s->section,
            ], $students)),
            'courses' => array_values(array_map(fn (Course $c) => [
                'id' => $c->id,
                'subject_name' => $c->subject_name,
                'grade' => $c->grade,
                'teacher_id' => $c->teacher_id,
            ], $courses)),
            'other' => $other,
            'counts' => [
                'teachers' => count($teachers),
                'parents' => count($parents),
                'students' => count($students),
                'courses' => count($courses),
            ],
        ];
    }

    /**
     * @param  list<string>  $studentNames
     * @return array<string, mixed>
     */
    private function accountPayload(User $user, array $studentNames = []): array
    {
        $row = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
        ];
        if ($studentNames !== []) {
            $row['students'] = $studentNames;
        }

        return $row;
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
     * @return list<int|string>
     */
    private function idsFrom(string $table, string $column, array $ids): array
    {
        if ($ids === [] || ! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return [];
        }

        return DB::table($table)->whereIn($column, $ids)->pluck('id')->all();
    }
}

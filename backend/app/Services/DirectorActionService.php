<?php

namespace App\Services;

use App\Helpers\InviteCodeHelper;
use App\Models\Course;
use App\Models\Student;
use App\Models\TeacherInvite;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DirectorActionService
{
    public function __construct(
        private StudentEnrollmentService $enrollmentService
    ) {}

    /**
     * @param array{
     *   teacher_name:string,
     *   email?:string|null,
     *   subject_name?:string|null,
     *   grades?:array<int,string>,
     *   section?:string|null,
     *   expires_in_days?:int|null
     * } $payload
     */
    public function createTeacherInviteWithAssignments(User $director, array $payload): array
    {
        $colegioId = (int) $director->colegio_id;
        if (! $colegioId) {
            throw ValidationException::withMessages([
                'colegio_id' => 'Tu usuario no está vinculado a un colegio.',
            ]);
        }

        return DB::transaction(function () use ($director, $payload, $colegioId) {
            $invite = TeacherInvite::create([
                'colegio_id' => $colegioId,
                'created_by' => $director->id,
                'name' => trim($payload['teacher_name']),
                'email' => isset($payload['email']) ? trim((string) $payload['email']) : null,
                'invite_code' => InviteCodeHelper::generateTeacherInvite(),
                'expires_at' => ! empty($payload['expires_in_days'])
                    ? now()->addDays((int) $payload['expires_in_days'])
                    : now()->addDays(30),
            ]);

            $courseIds = [];
            $createdCourses = [];
            $grades = collect($payload['grades'] ?? [])->filter()->values();
            $subject = trim((string) ($payload['subject_name'] ?? ''));
            $section = trim((string) ($payload['section'] ?? ''));
            $section = $section !== '' ? $section : null;

            if ($subject !== '' && $grades->isNotEmpty()) {
                foreach ($grades as $grade) {
                    $grade = trim((string) $grade);
                    if ($grade === '') {
                        continue;
                    }

                    $existing = Course::query()
                        ->where('colegio_id', $colegioId)
                        ->whereRaw('LOWER(subject_name) = ?', [mb_strtolower($subject)])
                        ->whereRaw('LOWER(grade) = ?', [mb_strtolower($grade)])
                        ->when($section, fn ($query) => $query->whereRaw('LOWER(COALESCE(section, ?)) = ?', ['', mb_strtolower($section)]))
                        ->where(function ($query) use ($invite) {
                            $query->where('teacher_invite_id', $invite->id)
                                ->orWhereNull('teacher_id');
                        })
                        ->first();

                    if ($existing) {
                        $existing->update([
                            'teacher_id' => null,
                            'teacher_invite_id' => $invite->id,
                        ]);
                        $courseIds[] = $existing->id;

                        continue;
                    }

                    $course = Course::create([
                        'teacher_id' => null,
                        'teacher_invite_id' => $invite->id,
                        'colegio_id' => $colegioId,
                        'subject_name' => $subject,
                        'grade' => $grade,
                        'section' => $section,
                        'school_year' => date('Y').'-'.(date('Y') + 1),
                        'invite_code' => InviteCodeHelper::generateCourseCode($subject, $grade, $section),
                    ]);
                    $courseIds[] = $course->id;
                    $createdCourses[] = $course;
                }
            }

            $courseIds = array_values(array_unique($courseIds));
            if ($courseIds !== []) {
                $invite->update([
                    'course_ids' => $courseIds,
                    'subject_name' => $subject !== '' ? $subject : null,
                    'grade' => $grades->first(),
                    'section' => $section,
                ]);
            }

            $verification = TeacherInvite::query()
                ->where('id', $invite->id)
                ->where('colegio_id', $colegioId)
                ->first();

            if (! $verification) {
                throw ValidationException::withMessages([
                    'invite' => 'No se pudo verificar la creación de la invitación.',
                ]);
            }

            $verifiedCourses = Course::query()
                ->where('colegio_id', $colegioId)
                ->where('teacher_invite_id', $invite->id)
                ->withCount('students')
                ->orderBy('grade')
                ->orderBy('subject_name')
                ->get(['id', 'subject_name', 'grade', 'section']);

            return [
                'invite' => $verification,
                'courses' => $verifiedCourses,
                'created_courses_count' => count($createdCourses),
            ];
        });
    }

    /**
     * @param array{
     *   teacher_name:string,
     *   subject_name:string,
     *   grades:array<int,string>,
     *   section?:string|null
     * } $payload
     */
    public function assignTeacherToGradesSubject(User $director, array $payload): array
    {
        $colegioId = (int) $director->colegio_id;
        $subject = trim((string) $payload['subject_name']);
        $section = trim((string) ($payload['section'] ?? ''));
        $section = $section !== '' ? $section : null;
        $grades = collect($payload['grades'])->map(fn ($g) => trim((string) $g))->filter()->unique()->values();

        if ($subject === '' || $grades->isEmpty()) {
            throw ValidationException::withMessages([
                'assignment' => 'Debes indicar materia y al menos un grado.',
            ]);
        }

        [$teacherId, $inviteId, $teacherLabel] = $this->resolveAssigneeByName($colegioId, $payload['teacher_name']);

        return DB::transaction(function () use ($colegioId, $subject, $section, $grades, $teacherId, $inviteId, $teacherLabel) {
            $courses = collect();

            foreach ($grades as $grade) {
                $course = Course::query()
                    ->where('colegio_id', $colegioId)
                    ->whereRaw('LOWER(subject_name) = ?', [mb_strtolower($subject)])
                    ->whereRaw('LOWER(grade) = ?', [mb_strtolower($grade)])
                    ->when($section, fn ($query) => $query->whereRaw('LOWER(COALESCE(section, ?)) = ?', ['', mb_strtolower($section)]))
                    ->first();

                if (! $course) {
                    $course = Course::create([
                        'teacher_id' => $teacherId,
                        'teacher_invite_id' => $inviteId,
                        'colegio_id' => $colegioId,
                        'subject_name' => $subject,
                        'grade' => $grade,
                        'section' => $section,
                        'school_year' => date('Y').'-'.(date('Y') + 1),
                        'invite_code' => InviteCodeHelper::generateCourseCode($subject, $grade, $section),
                    ]);
                } else {
                    $course->update([
                        'teacher_id' => $teacherId,
                        'teacher_invite_id' => $inviteId,
                    ]);
                }

                $courses->push($course);
            }

            if ($inviteId) {
                $invite = TeacherInvite::find($inviteId);
                if ($invite) {
                    $ids = collect($invite->course_ids ?? [])->merge($courses->pluck('id'))->unique()->values()->all();
                    $invite->update(['course_ids' => $ids]);
                }
            }

            $verified = Course::query()
                ->whereIn('id', $courses->pluck('id')->all())
                ->where('colegio_id', $colegioId)
                ->withCount('students')
                ->get(['id', 'subject_name', 'grade', 'section', 'teacher_id', 'teacher_invite_id']);

            if ($verified->count() !== $courses->count()) {
                throw ValidationException::withMessages([
                    'assignment' => 'No se pudo verificar una o más asignaciones.',
                ]);
            }

            return [
                'teacher_label' => $teacherLabel,
                'courses' => $verified,
            ];
        });
    }

    /**
     * @param array{
     *   names:array<int,string>,
     *   grade:string,
     *   section?:string|null,
     *   course_id?:int|null
     * } $payload
     */
    public function createStudentsBatch(User $director, array $payload): array
    {
        $colegioId = (int) $director->colegio_id;
        if (! $colegioId) {
            throw ValidationException::withMessages([
                'colegio_id' => 'Tu usuario no está vinculado a un colegio.',
            ]);
        }

        $grade = trim((string) $payload['grade']);
        $section = trim((string) ($payload['section'] ?? ''));
        $section = $section !== '' ? $section : null;
        $names = collect($payload['names'] ?? [])
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->unique()
            ->values();

        if ($grade === '' || $names->isEmpty()) {
            throw ValidationException::withMessages([
                'students' => 'Debes indicar nombres y grado para crear estudiantes.',
            ]);
        }

        $course = null;
        if (! empty($payload['course_id'])) {
            $course = Course::query()
                ->where('colegio_id', $colegioId)
                ->where('id', (int) $payload['course_id'])
                ->first();
        }

        $created = collect();
        $duplicates = [];

        DB::transaction(function () use ($names, $colegioId, $grade, $section, $director, $course, &$created, &$duplicates) {
            foreach ($names as $name) {
                $exists = Student::query()
                    ->where('colegio_id', $colegioId)
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                    ->whereRaw('LOWER(grade) = ?', [mb_strtolower($grade)])
                    ->whereRaw('LOWER(COALESCE(section, ?)) = ?', ['', mb_strtolower((string) $section)])
                    ->exists();

                if ($exists) {
                    $duplicates[] = $name;

                    continue;
                }

                $student = $this->enrollmentService->enroll($director, [
                    'name' => $name,
                    'grade' => $grade,
                    'section' => $section,
                    'family_mode' => 'new',
                ], $course);

                $created->push($student);
            }
        });

        $verified = Student::query()
            ->whereIn('id', $created->pluck('id')->all())
            ->where('colegio_id', $colegioId)
            ->orderBy('name')
            ->get(['id', 'colegio_id', 'name', 'grade', 'section', 'family_code']);

        if ($verified->count() !== $created->count()) {
            throw ValidationException::withMessages([
                'students' => 'No se pudieron verificar todos los estudiantes creados.',
            ]);
        }

        return [
            'created' => $verified,
            'duplicates' => $duplicates,
        ];
    }

    /**
     * @param array{
     *   subject_name:string,
     *   grades:array<int,string>,
     *   section?:string|null,
     *   teacher_name?:string|null
     * } $payload
     * @return array{
     *   courses:Collection<int,Course>,
     *   created_count:int,
     *   existing_count:int,
     *   teacher_label:?string
     * }
     */
    public function createCourses(User $director, array $payload): array
    {
        $colegioId = (int) $director->colegio_id;
        $subject = trim((string) $payload['subject_name']);
        $section = trim((string) ($payload['section'] ?? ''));
        $section = $section !== '' ? $section : null;
        $grades = collect($payload['grades'] ?? [])
            ->map(fn ($g) => trim((string) $g))
            ->filter()
            ->unique()
            ->values();

        if ($subject === '' || $grades->isEmpty()) {
            throw ValidationException::withMessages([
                'course' => 'Debes indicar materia y al menos un grado.',
            ]);
        }

        return DB::transaction(function () use ($colegioId, $subject, $section, $grades, $payload) {
            $teacherId = null;
            $inviteId = null;
            $teacherLabel = null;

            if (! empty($payload['teacher_name'])) {
                [$teacherId, $inviteId, $teacherLabel] = $this->resolveAssigneeByName($colegioId, (string) $payload['teacher_name']);
            }

            $courses = collect();
            $created = 0;
            $existing = 0;

            foreach ($grades as $grade) {
                $existingCourse = Course::query()
                    ->where('colegio_id', $colegioId)
                    ->whereRaw('LOWER(subject_name) = ?', [mb_strtolower($subject)])
                    ->whereRaw('LOWER(grade) = ?', [mb_strtolower($grade)])
                    ->when($section, fn ($query) => $query->whereRaw('LOWER(COALESCE(section, ?)) = ?', ['', mb_strtolower($section)]))
                    ->first();

                if ($existingCourse) {
                    $existingCourse->update([
                        'teacher_id' => $teacherId,
                        'teacher_invite_id' => $inviteId,
                    ]);
                    $courses->push($existingCourse->fresh());
                    $existing++;

                    continue;
                }

                $course = Course::create([
                    'teacher_id' => $teacherId,
                    'teacher_invite_id' => $inviteId,
                    'colegio_id' => $colegioId,
                    'subject_name' => $subject,
                    'grade' => $grade,
                    'section' => $section,
                    'school_year' => date('Y').'-'.(date('Y') + 1),
                    'invite_code' => InviteCodeHelper::generateCourseCode($subject, $grade, $section),
                ]);
                $courses->push($course);
                $created++;
            }

            if ($inviteId) {
                $invite = TeacherInvite::find($inviteId);
                if ($invite) {
                    $ids = collect($invite->course_ids ?? [])->merge($courses->pluck('id'))->unique()->values()->all();
                    $invite->update(['course_ids' => $ids]);
                }
            }

            $verified = Course::query()
                ->where('colegio_id', $colegioId)
                ->whereIn('id', $courses->pluck('id')->all())
                ->withCount('students')
                ->get(['id', 'subject_name', 'grade', 'section', 'teacher_id', 'teacher_invite_id', 'invite_code']);

            if ($verified->count() !== $courses->count()) {
                throw ValidationException::withMessages([
                    'course' => 'No se pudieron verificar todos los cursos creados.',
                ]);
            }

            return [
                'courses' => $verified,
                'created_count' => $created,
                'existing_count' => $existing,
                'teacher_label' => $teacherLabel,
            ];
        });
    }

    /**
     * @param array{
     *   subject_name:string,
     *   grade:string,
     *   section?:string|null,
     *   teacher_name?:string|null
     * } $payload
     */
    public function createCourse(User $director, array $payload): array
    {
        $colegioId = (int) $director->colegio_id;
        $subject = trim((string) $payload['subject_name']);
        $grade = trim((string) $payload['grade']);
        $section = trim((string) ($payload['section'] ?? ''));
        $section = $section !== '' ? $section : null;

        if ($subject === '' || $grade === '') {
            throw ValidationException::withMessages([
                'course' => 'Debes indicar materia y grado para crear el curso.',
            ]);
        }

        return DB::transaction(function () use ($colegioId, $subject, $grade, $section, $payload) {
            $teacherId = null;
            $inviteId = null;
            $teacherLabel = null;

            if (! empty($payload['teacher_name'])) {
                [$teacherId, $inviteId, $teacherLabel] = $this->resolveAssigneeByName($colegioId, (string) $payload['teacher_name']);
            }

            $existing = Course::query()
                ->where('colegio_id', $colegioId)
                ->whereRaw('LOWER(subject_name) = ?', [mb_strtolower($subject)])
                ->whereRaw('LOWER(grade) = ?', [mb_strtolower($grade)])
                ->whereRaw('LOWER(COALESCE(section, ?)) = ?', ['', mb_strtolower((string) $section)])
                ->first();

            if ($existing) {
                if ($teacherId !== null || $inviteId !== null) {
                    $existing->update([
                        'teacher_id' => $teacherId,
                        'teacher_invite_id' => $inviteId,
                    ]);
                }

                $course = $existing->fresh();
            } else {
                $course = Course::create([
                    'teacher_id' => $teacherId,
                    'teacher_invite_id' => $inviteId,
                    'colegio_id' => $colegioId,
                    'subject_name' => $subject,
                    'grade' => $grade,
                    'section' => $section,
                    'school_year' => date('Y').'-'.(date('Y') + 1),
                    'invite_code' => InviteCodeHelper::generateCourseCode($subject, $grade, $section),
                ]);
            }

            if ($inviteId) {
                $invite = TeacherInvite::find($inviteId);
                if ($invite) {
                    $ids = collect($invite->course_ids ?? [])->push($course->id)->unique()->values()->all();
                    $invite->update(['course_ids' => $ids]);
                }
            }

            $verified = Course::query()
                ->where('colegio_id', $colegioId)
                ->where('id', $course->id)
                ->withCount('students')
                ->first();

            if (! $verified) {
                throw ValidationException::withMessages([
                    'course' => 'No se pudo verificar el curso creado.',
                ]);
            }

            return [
                'course' => $verified,
                'teacher_label' => $teacherLabel,
                'was_existing' => (bool) $existing,
            ];
        });
    }

    /**
     * @param array{
     *   names:array<int,string>,
     *   subject_name:string,
     *   grade:string,
     *   section?:string|null
     * } $payload
     */
    public function enrollStudentsToCourse(User $director, array $payload): array
    {
        $colegioId = (int) $director->colegio_id;
        $subject = trim((string) $payload['subject_name']);
        $grade = trim((string) $payload['grade']);
        $section = trim((string) ($payload['section'] ?? ''));
        $section = $section !== '' ? $section : null;
        $names = collect($payload['names'] ?? [])
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->unique()
            ->values();

        if ($subject === '' || $grade === '' || $names->isEmpty()) {
            throw ValidationException::withMessages([
                'enroll' => 'Debes indicar alumnos, materia y grado para inscribir.',
            ]);
        }

        $course = $this->findCourseByAcademicKey($colegioId, $subject, $grade, $section);
        if (! $course) {
            throw ValidationException::withMessages([
                'course' => "No encontré el curso {$subject} de {$grade}".($section ? " sección {$section}" : '').'.',
            ]);
        }

        $enrolled = [];
        $missingStudents = [];
        $alreadyEnrolled = [];

        DB::transaction(function () use ($colegioId, $names, $course, &$enrolled, &$missingStudents, &$alreadyEnrolled) {
            foreach ($names as $name) {
                $student = Student::query()
                    ->where('colegio_id', $colegioId)
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                    ->first();

                if (! $student) {
                    $missingStudents[] = $name;

                    continue;
                }

                $isAlready = $course->students()->where('students.id', $student->id)->exists();
                if ($isAlready) {
                    $alreadyEnrolled[] = $student->name;

                    continue;
                }

                $this->enrollmentService->attachExisting($course, $student);
                $enrolled[] = $student->name;
            }
        });

        $verifiedCount = $course->fresh()->students()->count();

        return [
            'course' => $course->fresh(['teacher']),
            'enrolled' => $enrolled,
            'already_enrolled' => $alreadyEnrolled,
            'missing_students' => $missingStudents,
            'total_students_in_course' => $verifiedCount,
        ];
    }

    /**
     * @param array{
     *   operation:string,
     *   teacher_name?:string,
     *   invite_code?:string,
     *   expires_in_days?:int|null
     * } $payload
     */
    public function manageInviteCode(User $director, array $payload): array
    {
        $colegioId = (int) $director->colegio_id;
        $operation = $payload['operation'];
        $invite = $this->resolveInvite($colegioId, $payload['invite_code'] ?? null, $payload['teacher_name'] ?? null);

        if (! $invite) {
            throw ValidationException::withMessages([
                'invite' => 'No encontré una invitación DOC- con ese nombre o código.',
            ]);
        }

        if ($operation === 'query') {
            return ['invite' => $invite->fresh()];
        }

        throw ValidationException::withMessages([
            'operation' => 'En este MVP solo está disponible consultar códigos DOC-.',
        ]);
    }

    public function existingGradeKeys(int $colegioId): Collection
    {
        $courseGrades = Course::query()
            ->where('colegio_id', $colegioId)
            ->pluck('grade');
        $studentGrades = Student::query()
            ->where('colegio_id', $colegioId)
            ->pluck('grade');

        return $courseGrades
            ->merge($studentGrades)
            ->filter()
            ->map(fn ($grade) => $this->normalizeGradeKey((string) $grade))
            ->filter()
            ->unique()
            ->values();
    }

    public function normalizeGradeKey(string $grade): string
    {
        $raw = mb_strtolower(trim($grade));
        $raw = strtr($raw, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
        ]);
        $raw = preg_replace('/\s+/', '', $raw) ?? '';
        $raw = str_replace(['primero', '1ero', '1ro', '1°', '1º'], '1', $raw);
        $raw = str_replace(['segundo', '2do', '2°', '2º'], '2', $raw);
        $raw = str_replace(['tercero', '3ero', '3ro', '3°', '3º'], '3', $raw);
        $raw = str_replace(['cuarto', '4to', '4°', '4º'], '4', $raw);
        $raw = str_replace(['quinto', '5to', '5°', '5º'], '5', $raw);
        $raw = str_replace(['sexto', '6to', '6°', '6º'], '6', $raw);

        return preg_replace('/[^a-z0-9]/', '', $raw) ?? '';
    }

    /**
     * @return array{0:int|null,1:int|null,2:string}
     */
    private function resolveAssigneeByName(int $colegioId, string $name): array
    {
        $needle = mb_strtolower(trim($name));
        $teacher = User::query()
            ->where('colegio_id', $colegioId)
            ->where('role', 'profesor')
            ->whereRaw('LOWER(name) = ?', [$needle])
            ->first();

        if ($teacher) {
            return [$teacher->id, null, $teacher->name];
        }

        $invite = TeacherInvite::query()
            ->where('colegio_id', $colegioId)
            ->whereNull('claimed_by')
            ->whereNull('claimed_at')
            ->whereNull('revoked_at')
            ->whereRaw('LOWER(name) = ?', [$needle])
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();

        if ($invite) {
            return [null, $invite->id, $invite->name.' ('.$invite->invite_code.')'];
        }

        throw ValidationException::withMessages([
            'teacher' => 'No encontré un profesor o invitación activa con ese nombre.',
        ]);
    }

    private function resolveInvite(int $colegioId, ?string $inviteCode, ?string $teacherName): ?TeacherInvite
    {
        if ($inviteCode) {
            $normalized = InviteCodeHelper::normalize($inviteCode);

            return TeacherInvite::query()
                ->where('colegio_id', $colegioId)
                ->where('invite_code', $normalized)
                ->first();
        }

        if ($teacherName) {
            return TeacherInvite::query()
                ->where('colegio_id', $colegioId)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($teacherName))])
                ->latest('id')
                ->first();
        }

        return null;
    }

    private function findCourseByAcademicKey(int $colegioId, string $subject, string $grade, ?string $section): ?Course
    {
        return Course::query()
            ->where('colegio_id', $colegioId)
            ->whereRaw('LOWER(subject_name) = ?', [mb_strtolower($subject)])
            ->whereRaw('LOWER(grade) = ?', [mb_strtolower($grade)])
            ->whereRaw('LOWER(COALESCE(section, ?)) = ?', ['', mb_strtolower((string) $section)])
            ->first();
    }
}

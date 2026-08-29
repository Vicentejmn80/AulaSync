<?php

namespace App\Services;

use App\Helpers\InviteCodeHelper;
use App\Models\Course;
use App\Models\Invitation;
use App\Models\Materia;
use App\Models\Student;
use App\Models\TeacherInvite;
use App\Models\User;
use App\Support\GradeLabel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class DirectorActionService
{
    public function __construct(
        private StudentEnrollmentService $enrollmentService,
        private PersonNameMatcher $nameMatcher,
        private InvitationService $invitations,
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

        $result = DB::transaction(function () use ($director, $payload, $colegioId) {
            $sanitizer = app(PersonNameSanitizer::class);
            $rawName = trim((string) $payload['teacher_name']);
            $teacherName = $sanitizer->displayName($rawName) ?: $rawName;
            $invite = TeacherInvite::query()
                ->where('colegio_id', $colegioId)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($teacherName)])
                ->whereNull('claimed_by')
                ->whereNull('claimed_at')
                ->whereNull('revoked_at')
                ->where(function ($query) {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->latest('id')
                ->first();

            $email = isset($payload['email']) ? trim((string) $payload['email']) : '';
            $email = $email !== '' ? mb_strtolower($email) : null;

            if (! $invite) {
                $invite = TeacherInvite::create([
                    'colegio_id' => $colegioId,
                    'created_by' => $director->id,
                    'name' => $teacherName,
                    'email' => $email,
                    'invite_code' => InviteCodeHelper::generateTeacherInvite(),
                    'expires_at' => ! empty($payload['expires_in_days'])
                        ? now()->addDays((int) $payload['expires_in_days'])
                        : now()->addDays(30),
                ]);
            } elseif ($email) {
                $invite->update(['email' => $email]);
            }

            $courseIds = collect($invite->course_ids ?? [])
                ->merge($payload['course_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->all();
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

                    $existing = $this->findExistingCourse($colegioId, $subject, $grade, $section);
                    if ($existing) {
                        if ($this->courseOccupiedByOther($existing, null, $invite->id)) {
                            continue;
                        }
                        $existing->update([
                            'teacher_id' => null,
                            'teacher_invite_id' => $invite->id,
                        ]);
                        $courseIds[] = $existing->id;
                        continue;
                    }

                    $materia = $this->findOrCreateMateria($colegioId, $subject);
                    $course = Course::create([
                        'teacher_id' => null,
                        'teacher_invite_id' => $invite->id,
                        'colegio_id' => $colegioId,
                        'materia_id' => $materia->id,
                        'subject_name' => $materia->name,
                        'grade' => $grade,
                        'section' => $section,
                        'school_year' => date('Y').'-'.(date('Y') + 1),
                        'invite_code' => InviteCodeHelper::generateCourseCode($materia->name, $grade, $section),
                    ]);
                    $courseIds[] = $course->id;
                    $createdCourses[] = $course;
                    $this->enrollGradeStudentsInCourse($colegioId, $course, $director);
                }
            }

            if ($courseIds !== []) {
                $available = Course::query()
                    ->where('colegio_id', $colegioId)
                    ->whereIn('id', $courseIds)
                    ->get();
                foreach ($available as $course) {
                    if ($this->courseOccupiedByOther($course, null, $invite->id)) {
                        continue;
                    }
                    $course->update([
                        'teacher_id' => null,
                        'teacher_invite_id' => $invite->id,
                    ]);
                    $courseIds[] = $course->id;
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

        return $this->issueTeacherInvitationIfNeeded($director, $result);
    }

    /**
     * @param  array{invite:TeacherInvite,courses:Collection,created_courses_count:int}  $result
     * @return array{invite:TeacherInvite,courses:Collection,created_courses_count:int,invitation?:Invitation|null,mail_sent?:bool,invitation_warning?:string}
     */
    private function issueTeacherInvitationIfNeeded(User $director, array $result): array
    {
        $invite = $result['invite'];
        $email = trim((string) ($invite->email ?? ''));
        if ($email === '') {
            $result['invitation'] = null;
            $result['mail_sent'] = false;

            return $result;
        }

        $pending = Invitation::query()
            ->where('teacher_invite_id', $invite->id)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if ($pending) {
            $this->invitations->notify($pending);
            $result['invitation'] = $pending;
            $result['mail_sent'] = true;

            return $result;
        }

        try {
            $invitation = $this->invitations->issue([
                'email' => $email,
                'name' => $invite->display_name ?: $invite->name,
                'role' => Invitation::ROLE_DOCENTE,
                'colegio_id' => $invite->colegio_id,
                'teacher_invite_id' => $invite->id,
                'expires_in_days' => 7,
            ], $director);
            $result['invitation'] = $invitation;
            $result['mail_sent'] = true;
        } catch (ValidationException $e) {
            $warning = collect($e->errors())->flatten()->first() ?: 'No se pudo enviar el enlace de activación.';
            Log::warning('teacher_invite.magic_link_failed', [
                'invite_id' => $invite->id,
                'email' => $email,
                'error' => $warning,
            ]);
            $result['invitation'] = null;
            $result['mail_sent'] = false;
            $result['invitation_warning'] = $warning;
        }

        return $result;
    }

    /**
     * @param array{subject_name:string} $payload
     */
    public function createSubject(User $director, array $payload): array
    {
        $colegioId = $this->requireColegioId($director);
        $name = trim((string) ($payload['subject_name'] ?? $payload['name'] ?? ''));
        if ($name === '') {
            throw ValidationException::withMessages([
                'subject' => 'Indica el nombre de la materia.',
            ]);
        }

        $created = ! Materia::query()
            ->where('colegio_id', $colegioId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->exists();
        $materia = $this->findOrCreateMateria($colegioId, $name);

        return [
            'materia' => $materia,
            'created' => $created,
            'message' => $created
                ? 'Materia «'.$materia->name.'» agregada al catálogo.'
                : 'La materia «'.$materia->name.'» ya estaba en el catálogo.',
        ];
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

            $missing = [];
            foreach ($grades as $grade) {
                $course = $this->findExistingCourse($colegioId, $subject, $grade, $section);

                if (! $course) {
                    $materia = $this->findOrCreateMateria($colegioId, $subject);
                    $course = Course::create([
                        'teacher_id' => $teacherId,
                        'teacher_invite_id' => $inviteId,
                        'colegio_id' => $colegioId,
                        'materia_id' => $materia->id,
                        'subject_name' => $materia->name,
                        'grade' => $grade,
                        'section' => $section,
                        'school_year' => date('Y').'-'.(date('Y') + 1),
                        'invite_code' => InviteCodeHelper::generateCourseCode($materia->name, $grade, $section),
                    ]);
                    $courses->push($course);
                    continue;
                }
                if ($this->courseOccupiedByOther($course, $teacherId, $inviteId)) {
                    continue;
                }

                $course->update([
                    'teacher_id' => $teacherId,
                    'teacher_invite_id' => $inviteId,
                ]);
                $courses->push($course);
            }

            if ($courses->isEmpty()) {
                $hint = $missing !== []
                    ? 'No existen estos cursos: '.implode(', ', $missing).'. Créalos primero en la oferta académica.'
                    : 'Esos cursos ya tienen docente. No se duplican: reasigna desde Gestión o elige cursos libres.';
                throw ValidationException::withMessages([
                    'assignment' => $hint,
                ]);
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
    /**
     * @param array{
     *   students_data?:array<int,array{name:string,grade:string,section?:?string,subject_name?:?string,teacher_name?:?string}>,
     *   names?:array<int,string>,
     *   grade?:string,
     *   section?:?string,
     *   subject_name?:?string,
     *   teacher_name?:?string
     * } $payload
     * @return array{
     *   created:Collection<int,Student>,
     *   duplicates:array<int,string>,
     *   courses:array<int,array{grade:string,section:?string,course:?Course,enrolled_count:int,course_created:bool,placement_note:?string}>
     * }
     */
    public function createStudentsBatch(User $director, array $payload): array
    {
        $colegioId = $this->requireColegioId($director);
        $items = $this->normalizeStudentsData($payload);

        if ($items === []) {
            throw ValidationException::withMessages([
                'students' => 'Debes indicar nombres y grado para crear estudiantes.',
            ]);
        }

        // Agrupar por (grado, sección, materia, docente) para resolver o crear
        // el curso UNA sola vez por grupo, no por cada alumno del lote.
        $groups = [];
        foreach ($items as $item) {
            $key = implode('|', [
                mb_strtolower($item['grade']),
                mb_strtolower((string) $item['section']),
                mb_strtolower((string) $item['subject_name']),
                mb_strtolower((string) $item['teacher_name']),
            ]);
            $groups[$key]['grade'] ??= $item['grade'];
            $groups[$key]['section'] ??= $item['section'];
            $groups[$key]['subject_name'] ??= $item['subject_name'];
            $groups[$key]['teacher_name'] ??= $item['teacher_name'];
            $groups[$key]['students'][] = $item;
        }

        $created = collect();
        $duplicates = [];
        $courseGroups = [];

        DB::transaction(function () use ($groups, $colegioId, $director, &$created, &$duplicates, &$courseGroups) {
            foreach ($groups as $group) {
                $grade = $group['grade'];
                $section = $group['section'];
                $subject = $group['subject_name'];
                $teacherName = $group['teacher_name'];

                $course = null;
                $placementNote = null;
                $courseCreated = false;
                if ($subject !== null || $teacherName !== null) {
                    $placement = $this->resolveCourseForPlacement(
                        $colegioId,
                        $subject,
                        $grade,
                        $section,
                        $teacherName,
                        $director,
                        $subject !== null && $teacherName !== null,
                    );
                    $course = $placement['course'];
                    $placementNote = $placement['note'];
                    $courseCreated = $placement['created'];
                }

                $groupCreatedIds = [];
                foreach ($group['students'] as $student) {
                    $exists = Student::query()
                        ->where('colegio_id', $colegioId)
                        ->whereRaw('LOWER(name) = ?', [mb_strtolower($student['name'])])
                        ->whereRaw('LOWER(grade) = ?', [mb_strtolower($grade)])
                        ->whereRaw('LOWER(COALESCE(section, ?)) = ?', ['', mb_strtolower((string) $section)])
                        ->exists();

                    if ($exists) {
                        $duplicates[] = $student['name'];

                        continue;
                    }

                    $model = $this->enrollmentService->enroll($director, [
                        'name' => $student['name'],
                        'grade' => $grade,
                        'section' => $section,
                        'family_mode' => 'new',
                    ], $course);

                    $this->enrollStudentInGradeCourses($colegioId, $model, $director);
                    $created->push($model);
                    $groupCreatedIds[] = $model->id;
                }

                $enrolledCount = 0;
                if ($course && $groupCreatedIds !== []) {
                    $enrolledCount = $course->fresh()->students()
                        ->whereIn('students.id', $groupCreatedIds)
                        ->count();
                }

                $courseGroups[] = [
                    'grade' => $grade,
                    'section' => $section,
                    'course' => $course?->fresh(['teacher']),
                    'enrolled_count' => $enrolledCount,
                    'course_created' => $courseCreated,
                    'placement_note' => $placementNote,
                ];
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
            'courses' => $courseGroups,
        ];
    }

    /**
     * Adaptador de entrada de `createStudentsBatch`: acepta el formato canónico
     * `students_data` (un item por alumno, cada uno con su propio grado) y también
     * el formato legado plano `names`+`grade` compartido, que siguen enviando el
     * parser regex de AICommandController y DirectorIntentExtractorService.
     *
     * @return array<int,array{name:string,grade:string,section:?string,subject_name:?string,teacher_name:?string}>
     */
    private function normalizeStudentsData(array $payload): array
    {
        $sanitizer = app(PersonNameSanitizer::class);

        $raw = isset($payload['students_data']) && is_array($payload['students_data'])
            ? $payload['students_data']
            : collect((array) ($payload['names'] ?? []))
                ->map(fn ($name) => [
                    'name' => $name,
                    'grade' => $payload['grade'] ?? null,
                    'section' => $payload['section'] ?? null,
                    'subject_name' => $payload['subject_name'] ?? null,
                    'teacher_name' => $payload['teacher_name'] ?? null,
                ])
                ->all();

        $items = [];
        $seen = [];
        foreach ($raw as $item) {
            if (! is_array($item)) {
                continue;
            }

            $name = trim((string) ($item['name'] ?? ''));
            $clean = $sanitizer->clean($name);
            $name = $clean !== null && $clean !== '' ? $sanitizer->titleCase($clean) : $name;
            $grade = GradeLabel::canonical((string) ($item['grade'] ?? '')) ?? trim((string) ($item['grade'] ?? ''));
            $section = trim((string) ($item['section'] ?? ''));
            $section = $section !== '' ? $section : null;
            $subject = trim((string) ($item['subject_name'] ?? ''));
            $subject = $subject !== '' ? $subject : null;
            $teacher = trim((string) ($item['teacher_name'] ?? ''));
            $teacher = $teacher !== '' ? $teacher : null;

            if ($name === '' || $grade === '') {
                continue;
            }

            $key = implode('|', [mb_strtolower($name), mb_strtolower($grade), mb_strtolower((string) $section)]);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $items[] = [
                'name' => $name,
                'grade' => $grade,
                'section' => $section,
                'subject_name' => $subject,
                'teacher_name' => $teacher,
            ];
        }

        return $items;
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

        return DB::transaction(function () use ($colegioId, $subject, $section, $grades, $payload, $director) {
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
                $existingCourse = $this->findExistingCourse($colegioId, $subject, $grade, $section);

                if ($existingCourse) {
                    if ($teacherId !== null || $inviteId !== null) {
                        if (! $this->courseOccupiedByOther($existingCourse, $teacherId, $inviteId)) {
                            $existingCourse->update([
                                'teacher_id' => $teacherId,
                                'teacher_invite_id' => $inviteId,
                            ]);
                        }
                    }
                    $courses->push($existingCourse->fresh());
                    $existing++;

                    continue;
                }

                $materia = $this->findOrCreateMateria($colegioId, $subject);
                $course = Course::create([
                    'teacher_id' => $teacherId,
                    'teacher_invite_id' => $inviteId,
                    'colegio_id' => $colegioId,
                    'materia_id' => $materia->id,
                    'subject_name' => $materia->name,
                    'grade' => $grade,
                    'section' => $section,
                    'school_year' => date('Y').'-'.(date('Y') + 1),
                    'invite_code' => InviteCodeHelper::generateCourseCode($materia->name, $grade, $section),
                ]);
                $courses->push($course);
                $created++;
                $this->enrollGradeStudentsInCourse($colegioId, $course, $director);
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

        return DB::transaction(function () use ($colegioId, $subject, $grade, $section, $payload, $director) {
            $teacherId = null;
            $inviteId = null;
            $teacherLabel = null;

            if (! empty($payload['teacher_name'])) {
                [$teacherId, $inviteId, $teacherLabel] = $this->resolveAssigneeByName($colegioId, (string) $payload['teacher_name']);
            }

            $existing = $this->findExistingCourse($colegioId, $subject, $grade, $section);

            if ($existing) {
                if (($teacherId !== null || $inviteId !== null) && ! $this->courseOccupiedByOther($existing, $teacherId, $inviteId)) {
                    $existing->update([
                        'teacher_id' => $teacherId,
                        'teacher_invite_id' => $inviteId,
                    ]);
                }

                $course = $existing->fresh();
            } else {
                $materia = $this->findOrCreateMateria($colegioId, $subject);
                $course = Course::create([
                    'teacher_id' => $teacherId,
                    'teacher_invite_id' => $inviteId,
                    'colegio_id' => $colegioId,
                    'materia_id' => $materia->id,
                    'subject_name' => $materia->name,
                    'grade' => $grade,
                    'section' => $section,
                    'school_year' => date('Y').'-'.(date('Y') + 1),
                    'invite_code' => InviteCodeHelper::generateCourseCode($materia->name, $grade, $section),
                ]);
                $this->enrollGradeStudentsInCourse($colegioId, $course, $director);
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
                'created' => ! $existing,
            ];
        });
    }

    /**
     * Creación masiva de cursos: N materias × N grados en una sola transacción.
     * El docente es opcional por materia; un curso sin docente queda pendiente
     * de asignación (courses.teacher_id es nullable desde la migración
     * 2026_08_18_004500_allow_pending_teacher_course_assignment).
     *
     * @param  array{courses_data:array<int,array<string,mixed>>}  $payload
     * @return array{
     *   courses:Collection<int,Course>,
     *   created_count:int,
     *   existing_count:int,
     *   subjects_count:int,
     *   teacher_labels:array<int,string>
     * }
     */
    public function createCoursesBatch(User $director, array $payload): array
    {
        $colegioId = $this->requireColegioId($director);
        $items = $this->normalizeCoursesData($payload['courses_data'] ?? []);

        if ($items === []) {
            throw ValidationException::withMessages([
                'course' => 'Debes indicar al menos una materia con su grado.',
            ]);
        }

        // Pre-pass: resolver TODOS los docentes antes de escribir nada, para que
        // un nombre inexistente rechace el lote completo en vez de dejarlo a medias.
        $assignees = [];
        foreach ($items as $item) {
            if ($item['teacher_name'] === null) {
                continue;
            }
            $key = mb_strtolower($item['teacher_name']);
            if (! array_key_exists($key, $assignees)) {
                $assignees[$key] = $this->resolveAssigneeByName($colegioId, $item['teacher_name']);
            }
        }

        return DB::transaction(function () use ($colegioId, $items, $assignees, $director) {
            $courses = collect();
            $created = 0;
            $existing = 0;
            $inviteCourseIds = [];
            $teacherLabels = [];

            foreach ($items as $item) {
                [$teacherId, $inviteId, $teacherLabel] = $item['teacher_name'] !== null
                    ? $assignees[mb_strtolower($item['teacher_name'])]
                    : [null, null, null];

                if ($teacherLabel !== null) {
                    $teacherLabels[$teacherLabel] = true;
                }

                foreach ($item['grades'] as $grade) {
                    $existingCourse = $this->findExistingCourse($colegioId, $item['subject_name'], $grade, $item['section']);

                    if ($existingCourse) {
                        if (($teacherId !== null || $inviteId !== null)
                            && ! $this->courseOccupiedByOther($existingCourse, $teacherId, $inviteId)) {
                            $existingCourse->update([
                                'teacher_id' => $teacherId,
                                'teacher_invite_id' => $inviteId,
                            ]);
                        }
                        $courses->push($existingCourse->fresh());
                        $existing++;
                        if ($inviteId) {
                            $inviteCourseIds[$inviteId][] = $existingCourse->id;
                        }

                        continue;
                    }

                    $materia = $this->findOrCreateMateria($colegioId, $item['subject_name']);
                    $course = Course::create([
                        'teacher_id' => $teacherId,
                        'teacher_invite_id' => $inviteId,
                        'colegio_id' => $colegioId,
                        'materia_id' => $materia->id,
                        'subject_name' => $materia->name,
                        'grade' => $grade,
                        'section' => $item['section'],
                        'school_year' => date('Y').'-'.(date('Y') + 1),
                        'invite_code' => InviteCodeHelper::generateCourseCode($materia->name, $grade, $item['section']),
                    ]);
                    $courses->push($course);
                    $created++;
                    $this->enrollGradeStudentsInCourse($colegioId, $course, $director);
                    if ($inviteId) {
                        $inviteCourseIds[$inviteId][] = $course->id;
                    }
                }
            }

            // Una sola escritura por invitación, no una por curso.
            foreach ($inviteCourseIds as $inviteId => $ids) {
                $invite = TeacherInvite::find($inviteId);
                if ($invite) {
                    $invite->update([
                        'course_ids' => collect($invite->course_ids ?? [])->merge($ids)->unique()->values()->all(),
                    ]);
                }
            }

            $verified = Course::query()
                ->where('colegio_id', $colegioId)
                ->whereIn('id', $courses->pluck('id')->all())
                ->withCount('students')
                ->get(['id', 'colegio_id', 'subject_name', 'grade', 'section', 'teacher_id', 'teacher_invite_id', 'invite_code']);

            if ($verified->count() !== $courses->unique('id')->count()) {
                throw ValidationException::withMessages([
                    'course' => 'No se pudieron verificar todos los cursos creados.',
                ]);
            }

            return [
                'courses' => $verified,
                'created_count' => $created,
                'existing_count' => $existing,
                'subjects_count' => count($items),
                'teacher_labels' => array_keys($teacherLabels),
            ];
        });
    }

    /**
     * Normaliza `courses_data`: materia recortada, grados canónicos y sin
     * repetir, sección vacía => null. Descarta items sin materia o sin grados y
     * fusiona items de la misma materia + sección.
     *
     * @return array<int,array{subject_name:string,grades:array<int,string>,section:?string,teacher_name:?string}>
     */
    public function normalizeCoursesData(mixed $raw): array
    {
        $merged = [];

        foreach ((array) $raw as $item) {
            if (! is_array($item)) {
                continue;
            }

            $subject = trim((string) ($item['subject_name'] ?? ''));
            if ($subject === '') {
                continue;
            }

            $grades = collect($item['grades'] ?? [])
                ->map(fn ($grade) => GradeLabel::canonical((string) $grade))
                ->filter(fn (?string $grade) => $grade !== null && $grade !== '')
                ->unique()
                ->values()
                ->all();

            if ($grades === []) {
                continue;
            }

            $section = trim((string) ($item['section'] ?? ''));
            $section = $section !== '' ? $section : null;
            $teacher = trim((string) ($item['teacher_name'] ?? ''));
            $teacher = $teacher !== '' ? $teacher : null;

            $key = mb_strtolower($subject).'|'.mb_strtolower((string) $section);

            if (isset($merged[$key])) {
                $merged[$key]['grades'] = array_values(array_unique(array_merge($merged[$key]['grades'], $grades)));
                $merged[$key]['teacher_name'] ??= $teacher;

                continue;
            }

            $merged[$key] = [
                'subject_name' => $subject,
                'grades' => $grades,
                'section' => $section,
                'teacher_name' => $teacher,
            ];
        }

        return array_values($merged);
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
        $grade = trim((string) $payload['grade']);
        $section = trim((string) ($payload['section'] ?? ''));
        $section = $section !== '' ? $section : null;
        $teacherName = trim((string) ($payload['teacher_name'] ?? ''));
        $allInGrade = (bool) ($payload['all_in_grade'] ?? false);
        $names = collect($payload['names'] ?? [])
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->unique()
            ->values();
        $subjects = collect($payload['subject_names'] ?? [])
            ->push($payload['subject_name'] ?? '')
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->unique(fn ($name) => mb_strtolower($name))
            ->values();

        if ($subjects->isEmpty() || $grade === '' || ($names->isEmpty() && ! $allInGrade)) {
            throw ValidationException::withMessages([
                'enroll' => 'Debes indicar alumnos (o un grado completo), materia y grado para inscribir.',
            ]);
        }

        $missingCourses = [];
        $courses = collect();
        foreach ($subjects as $subject) {
            $placement = $this->resolveCourseForPlacement(
                $colegioId,
                $subject,
                $grade,
                $section,
                $teacherName !== '' ? $teacherName : null,
                $director,
                false,
            );
            if ($placement['course']) {
                $courses->push($placement['course']);
            } else {
                $missingCourses[] = trim($subject.' '.$grade.($section ? ' '.$section : ''));
            }
        }

        $courses = $courses->unique('id')->values();
        $course = $courses->first();
        if (! $course) {
            throw ValidationException::withMessages([
                'course' => 'No encontré estos cursos: '.implode(', ', $missingCourses).'. Créalos primero en la oferta académica.',
            ]);
        }

        if ($allInGrade) {
            $roster = Student::query()
                ->where('colegio_id', $colegioId)
                ->whereRaw('LOWER(grade) = ?', [mb_strtolower($grade)])
                ->when($section, fn ($q) => $q->whereRaw('LOWER(COALESCE(section, ?)) = ?', ['', mb_strtolower($section)]))
                ->orderBy('name')
                ->get();
            if ($roster->isEmpty()) {
                throw ValidationException::withMessages([
                    'enroll' => "No hay alumnos registrados en {$grade}".($section ? " sección {$section}" : '').' para inscribir.',
                ]);
            }

            $enrolled = [];
            $alreadyEnrolled = [];
            foreach ($roster as $student) {
                $attachedAny = false;
                $alreadyAll = true;
                foreach ($courses as $target) {
                    if ($target->students()->where('students.id', $student->id)->exists()) {
                        continue;
                    }
                    $alreadyAll = false;
                    $this->enrollmentService->attachExisting($target, $student, $director);
                    $attachedAny = true;
                }
                if ($attachedAny) {
                    $enrolled[] = $student->name;
                } elseif ($alreadyAll) {
                    $alreadyEnrolled[] = $student->name;
                }
            }

            return [
                'course' => $course->fresh(['teacher']),
                'enrolled' => $enrolled,
                'already_enrolled' => $alreadyEnrolled,
                'missing_students' => [],
                'missing_courses' => $missingCourses,
                'total_students_in_course' => $course->fresh()->students()->count(),
            ];
        }

        $enrolled = [];
        $missingStudents = [];
        $alreadyEnrolled = [];

        DB::transaction(function () use ($director, $colegioId, $names, $courses, &$enrolled, &$missingStudents, &$alreadyEnrolled) {
            foreach ($names as $name) {
                $student = Student::query()
                    ->where('colegio_id', $colegioId)
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                    ->first();

                if (! $student) {
                    $matches = Student::query()
                        ->where('colegio_id', $colegioId)
                        ->whereRaw('LOWER(name) like ?', ['%'.mb_strtolower($name).'%'])
                        ->get();
                    if ($matches->count() > 1) {
                        throw ValidationException::withMessages([
                            'student' => 'Encontré varios alumnos para '.$name.': '.$matches->pluck('name')->implode(', ').'.',
                        ]);
                    }
                    $student = $matches->first();
                }

                if (! $student) {
                    $missingStudents[] = $name;

                    continue;
                }

                $attachedAny = false;
                $alreadyAll = true;
                foreach ($courses as $target) {
                    if ($target->students()->where('students.id', $student->id)->exists()) {
                        continue;
                    }
                    $alreadyAll = false;
                    $this->enrollmentService->attachExisting($target, $student, $director);
                    $attachedAny = true;
                }

                if ($attachedAny) {
                    $enrolled[] = $student->name;
                } elseif ($alreadyAll) {
                    $alreadyEnrolled[] = $student->name;
                }
            }
        });

        $verifiedCount = $course->fresh()->students()->count();

        return [
            'course' => $course->fresh(['teacher']),
            'enrolled' => $enrolled,
            'already_enrolled' => $alreadyEnrolled,
            'missing_students' => $missingStudents,
            'missing_courses' => $missingCourses,
            'total_students_in_course' => $verifiedCount,
        ];
    }

    /**
     * @param array{
     *   names:array<int,string>,
     *   subject_name:string,
     *   grade:string,
     *   section?:string|null
     * } $payload
     */
    public function unenrollStudentsFromCourse(User $director, array $payload): array
    {
        $colegioId = $this->requireColegioId($director);
        $subject = trim((string) ($payload['subject_name'] ?? ''));
        $grade = trim((string) ($payload['grade'] ?? ''));
        $section = trim((string) ($payload['section'] ?? ''));
        $section = $section !== '' ? $section : null;
        $names = collect($payload['names'] ?? [])->map(fn ($name) => trim((string) $name))->filter()->unique()->values();

        if ($subject === '' || $grade === '' || $names->isEmpty()) {
            throw ValidationException::withMessages([
                'unenroll' => 'Debes indicar alumnos, materia y grado para desmatricular.',
            ]);
        }

        $course = $this->findCourseByAcademicKey($colegioId, $subject, $grade, $section);
        if (! $course) {
            throw ValidationException::withMessages([
                'course' => "No encontré el curso {$subject} de {$grade}".($section ? " sección {$section}" : '').'.',
            ]);
        }

        $removed = [];
        $missing = [];
        DB::transaction(function () use ($colegioId, $course, $names, &$removed, &$missing) {
            foreach ($names as $name) {
                $matches = Student::query()
                    ->where('colegio_id', $colegioId)
                    ->whereRaw('LOWER(name) like ?', ['%'.mb_strtolower($name).'%'])
                    ->get();
                if ($matches->count() !== 1) {
                    $missing[] = $name;

                    continue;
                }
                $student = $matches->first();
                if (! $course->students()->where('students.id', $student->id)->exists()) {
                    $missing[] = $name;

                    continue;
                }
                $course->students()->detach($student->id);
                $removed[] = $student->name;
            }
        });

        return [
            'course' => $course->fresh(),
            'unenrolled' => $removed,
            'missing_students' => $missing,
            'total_students_in_course' => $course->fresh()->students()->count(),
        ];
    }

    /**
     * @param array{
     *   teacher_name:string,
     *   subject_name?:string|null,
     *   grades?:array<int,string>
     * } $payload
     */
    public function unassignTeacher(User $director, array $payload): array
    {
        $colegioId = $this->requireColegioId($director);
        [$teacherId, $inviteId, $label] = $this->resolveAssigneeByName(
            $colegioId,
            (string) ($payload['teacher_name'] ?? ''),
        );
        $query = Course::query()->where('colegio_id', $colegioId);
        $teacherId
            ? $query->where('teacher_id', $teacherId)
            : $query->where('teacher_invite_id', $inviteId);

        if (! empty($payload['subject_name'])) {
            $query->whereRaw('LOWER(subject_name) like ?', [
                '%'.$this->subjectSearchStem((string) $payload['subject_name']).'%',
            ]);
        }
        $grades = collect($payload['grades'] ?? [])->filter()->map(fn ($grade) => mb_strtolower((string) $grade))->all();
        if ($grades !== []) {
            $query->whereIn(DB::raw('LOWER(grade)'), $grades);
        }

        $courses = $query->get(['id', 'subject_name', 'grade']);
        if ($courses->isEmpty()) {
            throw ValidationException::withMessages([
                'assignment' => "{$label} no tiene cursos que coincidan con la solicitud.",
            ]);
        }

        Course::query()
            ->where('colegio_id', $colegioId)
            ->whereIn('id', $courses->pluck('id')->all())
            ->update(['teacher_id' => null, 'teacher_invite_id' => null]);

        return [
            'message' => "Desasigné {$courses->count()} curso(s) de {$label}.",
            'data' => ['course_ids' => $courses->pluck('id')->all(), 'teacher_name' => $label],
        ];
    }

    /**
     * @param array{
     *   subject_name:string,
     *   grade:string,
     *   section?:string|null,
     *   new_subject_name?:string|null,
     *   new_grade?:string|null,
     *   new_section?:string|null
     * } $payload
     */
    public function updateCourse(User $director, array $payload): array
    {
        $colegioId = $this->requireColegioId($director);
        $course = $this->findCourseByAcademicKey(
            $colegioId,
            (string) ($payload['subject_name'] ?? ''),
            (string) ($payload['grade'] ?? ''),
            $payload['section'] ?? null,
        );
        if (! $course) {
            throw ValidationException::withMessages(['course' => 'No encontré el curso que deseas modificar.']);
        }

        $updates = array_filter([
            'subject_name' => $payload['new_subject_name'] ?? null,
            'grade' => $payload['new_grade'] ?? null,
            'section' => array_key_exists('new_section', $payload) ? $payload['new_section'] : null,
        ], fn ($value) => $value !== null && trim((string) $value) !== '');
        if ($updates === []) {
            throw ValidationException::withMessages(['course' => 'Indica qué dato del curso deseas cambiar.']);
        }

        $course->update($updates);

        return [
            'message' => 'Curso actualizado: '.$course->fresh()->subject_name.' '.$course->fresh()->grade.'.',
            'data' => ['course' => $course->fresh()->only(['id', 'subject_name', 'grade', 'section'])],
        ];
    }

    /**
     * @param array{
     *   student_name:string,
     *   new_name?:string|null,
     *   new_grade?:string|null,
     *   new_section?:string|null
     * } $payload
     */
    public function updateStudent(User $director, array $payload): array
    {
        $colegioId = $this->requireColegioId($director);
        $student = $this->resolveUniqueStudent($colegioId, (string) ($payload['student_name'] ?? ''));
        $updates = array_filter([
            'name' => $payload['new_name'] ?? null,
            'grade' => $payload['new_grade'] ?? null,
            'section' => array_key_exists('new_section', $payload) ? $payload['new_section'] : null,
        ], fn ($value) => $value !== null && trim((string) $value) !== '');
        if ($updates === []) {
            throw ValidationException::withMessages(['student' => 'Indica qué dato del alumno deseas cambiar.']);
        }

        $oldGrade = (string) $student->grade;
        $oldSection = $student->section;
        $student->update($updates);
        $student = $student->fresh();

        $moved = array_key_exists('grade', $updates) || array_key_exists('section', $updates);
        $reattached = 0;
        if ($moved) {
            $reattached = $this->relinkStudentCoursesAfterMove(
                $director,
                $student,
                $oldGrade,
                $oldSection,
            );
        }

        $place = trim($student->grade.($student->section ? ' / '.$student->section : ''));
        $moveNote = $moved
            ? " Quedó en {$place}".($reattached > 0 ? " e inscrito en {$reattached} curso(s) de ese grado." : '.')
            : '';

        return [
            'message' => 'Alumno actualizado: '.$student->name.'.'.$moveNote,
            'data' => [
                'student' => $student->only(['id', 'name', 'grade', 'section']),
                'courses_relinked' => $reattached,
            ],
        ];
    }

    /**
     * Validate and resolve a registered teacher or active pending invite within the director's school.
     *
     * @return array{0:int|null,1:int|null,2:string}
     */
    public function resolveAssigneeForDirector(User $director, string $name): array
    {
        return $this->resolveAssigneeByName($this->requireColegioId($director), $name);
    }

    /**
     * @param  array{teacher_name:string}  $payload
     */
    public function deleteTeacher(User $director, array $payload): array
    {
        $colegioId = $this->requireColegioId($director);
        $name = trim((string) ($payload['teacher_name'] ?? ''));
        if ($name === '') {
            throw ValidationException::withMessages([
                'teacher' => 'Indica el nombre del profesor a eliminar.',
            ]);
        }

        $match = $this->nameMatcher->resolveTeacher($colegioId, $name);
        if ($match->isNone()) {
            throw ValidationException::withMessages([
                'teacher' => $match->message ?? 'No encontré al profesor "'.$name.'" en este colegio.',
            ]);
        }
        if ($match->isAmbiguous()) {
            throw ValidationException::withMessages([
                'teacher' => $match->message,
            ]);
        }

        /** @var User $teacher */
        $teacher = $match->model;

        return DB::transaction(function () use ($director, $colegioId, $teacher) {
            $this->detachTeacherFromSchool($director, $colegioId, collect([$teacher]));

            $inviteMatch = $this->nameMatcher->resolveInvite($colegioId, $teacher->name);
            if ($inviteMatch->isUnique() && $inviteMatch->model instanceof TeacherInvite) {
                TeacherInvite::query()
                    ->where('colegio_id', $colegioId)
                    ->where('id', $inviteMatch->model->id)
                    ->delete();
            }

            $teacher->delete();

            $stillExists = User::query()
                ->where('colegio_id', $colegioId)
                ->where('id', $teacher->id)
                ->exists();
            if ($stillExists) {
                throw ValidationException::withMessages([
                    'teacher' => 'No se pudo verificar la eliminación del profesor.',
                ]);
            }

            return [
                'deleted_count' => 1,
                'deleted_names' => [$teacher->name],
            ];
        });
    }

    /**
     * Cancela una invitación DOC- pendiente. Nunca toca un profesor registrado.
     *
     * @param  array{teacher_name:string, invite_id?:int|null, invite_code?:string|null}  $payload
     */
    public function deleteTeacherInvite(User $director, array $payload): array
    {
        $colegioId = $this->requireColegioId($director);
        $inviteId = (int) ($payload['invite_id'] ?? 0);
        $name = trim((string) ($payload['teacher_name'] ?? ''));

        if ($inviteId) {
            $invite = TeacherInvite::query()
                ->where('colegio_id', $colegioId)
                ->where('id', $inviteId)
                ->first();
        } else {
            if ($name === '') {
                throw ValidationException::withMessages([
                    'teacher' => 'Indica el profesor de la invitación a cancelar.',
                ]);
            }

            $match = $this->nameMatcher->resolveInvite($colegioId, $name);
            if ($match->isNone()) {
                throw ValidationException::withMessages([
                    'teacher' => $match->message ?? 'No encontré una invitación pendiente con ese nombre.',
                ]);
            }
            if ($match->isAmbiguous()) {
                throw ValidationException::withMessages([
                    'teacher' => $match->message,
                ]);
            }

            /** @var TeacherInvite $invite */
            $invite = $match->model;
        }

        if (! $invite || (int) $invite->colegio_id !== $colegioId) {
            throw ValidationException::withMessages([
                'teacher' => 'No encontré la invitación pendiente indicada en este colegio.',
            ]);
        }

        return DB::transaction(function () use ($colegioId, $invite) {
            $label = $invite->name;
            $code = $invite->invite_code;

            Course::query()
                ->where('colegio_id', $colegioId)
                ->where('teacher_invite_id', $invite->id)
                ->update([
                    'teacher_invite_id' => null,
                    'teacher_id' => null,
                ]);

            TeacherInvite::query()
                ->where('colegio_id', $colegioId)
                ->where('id', $invite->id)
                ->delete();

            $stillExists = TeacherInvite::query()
                ->where('colegio_id', $colegioId)
                ->where('id', $invite->id)
                ->exists();
            if ($stillExists) {
                throw ValidationException::withMessages([
                    'teacher' => 'No se pudo verificar la cancelación de la invitación.',
                ]);
            }

            return [
                'deleted_count' => 1,
                'deleted_invites' => 1,
                'invite_label' => $label,
                'invite_code' => $code,
            ];
        });
    }

    /**
     * @param  array{}  $payload
     */
    public function deleteAllTeachers(User $director, array $payload = []): array
    {
        $colegioId = $this->requireColegioId($director);

        return DB::transaction(function () use ($director, $colegioId) {
            $teachers = User::query()
                ->where('colegio_id', $colegioId)
                ->where('role', 'profesor')
                ->get(['id', 'name']);

            $this->detachTeacherFromSchool($director, $colegioId, $teachers);
            $inviteCount = TeacherInvite::query()->where('colegio_id', $colegioId)->count();
            TeacherInvite::query()->where('colegio_id', $colegioId)->delete();

            $ids = $teachers->pluck('id')->all();
            if ($ids !== []) {
                User::query()
                    ->where('colegio_id', $colegioId)
                    ->where('role', 'profesor')
                    ->whereIn('id', $ids)
                    ->delete();
            }

            $remaining = User::query()
                ->where('colegio_id', $colegioId)
                ->where('role', 'profesor')
                ->count();
            if ($remaining > 0) {
                throw ValidationException::withMessages([
                    'teachers' => 'No se pudieron eliminar todos los profesores del colegio.',
                ]);
            }

            return [
                'deleted_count' => $teachers->count(),
                'deleted_names' => $teachers->pluck('name')->values()->all(),
                'deleted_invites' => $inviteCount,
            ];
        });
    }

    /**
     * @param  array{subject_name:string, grade?:string|null, section?:string|null}  $payload
     */
    public function deleteCourse(User $director, array $payload): array
    {
        $colegioId = $this->requireColegioId($director);
        $subject = trim((string) ($payload['subject_name'] ?? ''));
        $grade = isset($payload['grade']) ? trim((string) $payload['grade']) : '';
        $section = isset($payload['section']) ? trim((string) $payload['section']) : '';
        $section = $section !== '' ? $section : null;

        if ($subject === '') {
            throw ValidationException::withMessages([
                'course' => 'Indica la asignatura del curso a eliminar.',
            ]);
        }

        $subjectKey = $this->subjectSearchStem($subject);

        $query = Course::query()
            ->where('colegio_id', $colegioId)
            ->whereRaw('LOWER(subject_name) like ?', ['%'.$subjectKey.'%']);

        if ($grade !== '') {
            $query->whereRaw('LOWER(grade) = ?', [mb_strtolower($grade)]);
        }
        if ($section) {
            $query->whereRaw('LOWER(COALESCE(section, ?)) = ?', ['', mb_strtolower($section)]);
        }

        $courses = $query->get(['id', 'subject_name', 'grade', 'section']);
        if ($courses->isEmpty()) {
            $label = $subject.($grade !== '' ? " {$grade}" : '').($section ? " sección {$section}" : '');
            throw ValidationException::withMessages([
                'course' => "No encontré el curso {$label} en este colegio.",
            ]);
        }

        return $this->deleteCoursesCollection($colegioId, $courses);
    }

    /**
     * @param  array{}  $payload
     */
    public function deleteAllCourses(User $director, array $payload = []): array
    {
        $colegioId = $this->requireColegioId($director);
        $courses = Course::query()
            ->where('colegio_id', $colegioId)
            ->get(['id', 'subject_name', 'grade', 'section']);

        return $this->deleteCoursesCollection($colegioId, $courses);
    }

    /**
     * @param  array{student_name:string, student_id?:int|null}  $payload
     */
    public function deleteStudent(User $director, array $payload): array
    {
        $colegioId = $this->requireColegioId($director);
        $names = collect($payload['names'] ?? [])
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->values();
        $rawName = trim((string) ($payload['student_name'] ?? ''));
        if ($rawName !== '') {
            $names->push($rawName);
        }
        $names = $names->unique()->values();

        if (! empty($payload['student_id'])) {
            $student = Student::query()
                ->where('colegio_id', $colegioId)
                ->where('id', (int) $payload['student_id'])
                ->first();

            if (! $student) {
                throw ValidationException::withMessages([
                    'student' => 'No encontré al alumno indicado en este colegio.',
                ]);
            }

            return $this->deleteStudentRecord($colegioId, $student);
        }

        if ($names->isEmpty()) {
            throw ValidationException::withMessages([
                'student' => 'Indica el nombre del alumno a eliminar.',
            ]);
        }

        $deleted = [];
        $missing = [];
        foreach ($names as $name) {
            $match = $this->nameMatcher->resolveStudent($colegioId, $name);
            if (! $match->isUnique()) {
                $missing[] = $name.($match->message ? ' ('.$match->message.')' : '');

                continue;
            }
            /** @var Student $student */
            $student = $match->model;
            $result = $this->deleteStudentRecord($colegioId, $student);
            $deleted = array_merge($deleted, $result['deleted_names']);
        }

        if ($deleted === []) {
            throw ValidationException::withMessages([
                'student' => $missing !== []
                    ? implode(' ', $missing)
                    : 'No encontré a esos alumnos en este colegio.',
            ]);
        }

        return [
            'deleted_count' => count($deleted),
            'deleted_names' => $deleted,
            'missing_names' => $missing,
        ];
    }

    /**
     * @return array{deleted_count:int, deleted_names:array<int,string>}
     */
    private function deleteStudentRecord(int $colegioId, Student $student): array
    {
        return DB::transaction(function () use ($colegioId, $student) {
            $student->courses()->detach();
            $student->guardians()->detach();
            $student->delete();

            if (Student::query()->where('colegio_id', $colegioId)->where('id', $student->id)->exists()) {
                throw ValidationException::withMessages([
                    'student' => 'No se pudo verificar la eliminación del alumno.',
                ]);
            }

            return [
                'deleted_count' => 1,
                'deleted_names' => [$student->name],
            ];
        });
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
        return GradeLabel::key($grade);
    }

    /**
     * @return array{0:int|null,1:int|null,2:string}
     */
    private function resolveAssigneeByName(int $colegioId, string $name): array
    {
        $match = $this->nameMatcher->resolveTeacherOrInvite($colegioId, $name);

        if ($match->isAmbiguous() || $match->isNone()) {
            throw ValidationException::withMessages([
                'teacher' => $match->message ?? 'No encontré un profesor o invitación activa con ese nombre.',
            ]);
        }

        $model = $match->model;

        if ($model instanceof User) {
            return [$model->id, null, $match->label];
        }

        if ($model instanceof TeacherInvite) {
            return [null, $model->id, $match->label];
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
            $match = $this->nameMatcher->resolveInvite($colegioId, $teacherName);
            if ($match->isUnique() && $match->model instanceof TeacherInvite) {
                return $match->model;
            }
        }

        return null;
    }

    /**
     * Busca un curso por materia/grado/sección/profesor (coincidencias flexibles).
     * Si no existe y hay profesor + materia, puede crearlo.
     *
     * @return array{course:?Course, created:bool, note:?string}
     */
    public function resolveCourseForPlacement(
        int $colegioId,
        ?string $subject,
        string $grade,
        ?string $section,
        ?string $teacherName,
        User $director,
        bool $createIfMissing = false,
    ): array {
        $teacherId = null;
        $teacherLabel = null;
        if ($teacherName) {
            $match = $this->nameMatcher->resolveTeacher($colegioId, $teacherName);
            if ($match->isUnique()) {
                $teacherId = (int) $match->model->id;
                $teacherLabel = $match->model->name;
            } elseif ($match->isAmbiguous()) {
                throw ValidationException::withMessages([
                    'teacher' => $match->message ?? 'Hay varios profesores con ese nombre. Precisa cuál.',
                ]);
            }
        }

        $candidates = Course::query()
            ->where('colegio_id', $colegioId)
            ->whereRaw('LOWER(grade) = ?', [mb_strtolower($grade)])
            ->when($teacherId, fn ($q) => $q->where('teacher_id', $teacherId))
            ->get();

        if ($section) {
            $sectionKey = $this->academicKey($section);
            $sectioned = $candidates->filter(
                fn (Course $course) => $this->academicKey((string) $course->section) === $sectionKey
            );
            if ($sectioned->isNotEmpty()) {
                $candidates = $sectioned;
            }
        }

        if ($subject) {
            $subjectKey = $this->academicKey($subject);
            $bySubject = $candidates->filter(function (Course $course) use ($subjectKey) {
                $name = $this->academicKey((string) $course->subject_name);

                return $name === $subjectKey
                    || str_contains($name, $subjectKey)
                    || str_contains($subjectKey, $name);
            });
            if ($bySubject->isNotEmpty()) {
                $candidates = $bySubject;
            } elseif ($teacherId) {
                $candidates = collect();
            }
        }

        if ($candidates->count() === 1) {
            return ['course' => $candidates->first(), 'created' => false, 'note' => null];
        }

        if ($candidates->count() > 1) {
            $labels = $candidates->map(
                fn (Course $c) => $c->subject_name.' '.$c->grade.($c->section ? ' / '.$c->section : '')
            )->implode(', ');

            throw ValidationException::withMessages([
                'course' => "Encontré varios cursos que coinciden: {$labels}. Dime la sección o el profesor exacto.",
            ]);
        }

        if ($createIfMissing && $subject && $teacherId) {
            $materia = $this->findOrCreateMateria($colegioId, $subject);
            $course = Course::create([
                'teacher_id' => $teacherId,
                'colegio_id' => $colegioId,
                'materia_id' => $materia->id,
                'subject_name' => $materia->name,
                'grade' => $grade,
                'section' => $section,
                'school_year' => date('Y').'-'.(date('Y') + 1),
                'invite_code' => InviteCodeHelper::generateCourseCode($materia->name, $grade, $section),
            ]);

            return [
                'course' => $course,
                'created' => true,
                'note' => "No había curso de {$subject} en {$grade}".($teacherLabel ? " con {$teacherLabel}" : '').', así que lo creé y matriculé ahí.',
            ];
        }

        $hint = $subject
            ? "No encontré el curso de {$subject} en {$grade}".($section ? " sección {$section}" : '').($teacherLabel ? " con {$teacherLabel}" : '').'. El alumno queda en la nómina; créame el curso o dime el profesor para vincularlo.'
            : "No encontré un curso de {$grade}".($teacherLabel ? " para {$teacherLabel}" : '').' donde matricular.';

        return ['course' => null, 'created' => false, 'note' => $hint];
    }

    private function relinkStudentCoursesAfterMove(User $director, Student $student, string $oldGrade, ?string $oldSection): int
    {
        $colegioId = (int) $student->colegio_id;
        $newGradeKey = $this->academicKey((string) $student->grade);
        $newSectionKey = $this->academicKey((string) $student->section);

        $current = $student->courses()
            ->where('courses.colegio_id', $colegioId)
            ->get();

        foreach ($current as $course) {
            $sameGrade = $this->academicKey((string) $course->grade) === $newGradeKey;
            $sameSection = $newSectionKey === ''
                || $this->academicKey((string) $course->section) === $newSectionKey;
            if (! $sameGrade || ! $sameSection) {
                $student->courses()->detach($course->id);
            }
        }

        $targets = Course::query()
            ->where('colegio_id', $colegioId)
            ->whereRaw('LOWER(grade) = ?', [mb_strtolower((string) $student->grade)])
            ->when(
                $student->section,
                fn ($q) => $q->whereRaw('LOWER(COALESCE(section, ?)) = ?', ['', mb_strtolower((string) $student->section)])
            )
            ->get();

        $attached = 0;
        foreach ($targets as $course) {
            $this->enrollmentService->attachExisting($course, $student, $director);
            $attached++;
        }

        return $attached;
    }

    private function academicKey(?string $value): string
    {
        $value = mb_strtolower(trim((string) $value));

        return strtr($value, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
        ]);
    }

    public function findOrCreateMateria(int $colegioId, string $name): Materia
    {
        $name = trim($name);
        $existing = Materia::query()
            ->where('colegio_id', $colegioId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();
        if ($existing) {
            return $existing;
        }

        return Materia::create([
            'colegio_id' => $colegioId,
            'name' => $name,
        ]);
    }

    private function findExistingCourse(int $colegioId, string $subject, string $grade, ?string $section): ?Course
    {
        return $this->findCourseByAcademicKey($colegioId, $subject, $grade, $section);
    }

    private function courseOccupiedByOther(Course $course, ?int $teacherId, ?int $inviteId): bool
    {
        if ($teacherId && (int) $course->teacher_id === (int) $teacherId) {
            return false;
        }
        if ($inviteId && (int) $course->teacher_invite_id === (int) $inviteId) {
            return false;
        }
        if ($course->teacher_id || $course->teacher_invite_id) {
            // Los cursos asignados al director (role director) se consideran libres
            // para reasignar a un profesor real — el director no es docente.
            if ($course->teacher_id) {
                $owner = User::find($course->teacher_id);
                if ($owner && $owner->role === 'director') {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    private function findCourseByAcademicKey(int $colegioId, string $subject, string $grade, ?string $section): ?Course
    {
        $gradeKey = GradeLabel::key($grade);
        $sectionKey = $this->academicKey((string) $section);

        return Course::query()
            ->where('colegio_id', $colegioId)
            ->whereRaw('LOWER(subject_name) = ?', [mb_strtolower($subject)])
            ->get()
            ->first(function (Course $course) use ($gradeKey, $sectionKey) {
                if ($gradeKey === '' || GradeLabel::key($course->grade) !== $gradeKey) {
                    return false;
                }

                return $this->academicKey((string) $course->section) === $sectionKey;
            });
    }

    private function resolveUniqueStudent(int $colegioId, string $name): Student
    {
        $match = $this->nameMatcher->resolveStudent($colegioId, $name);

        if ($match->isAmbiguous() || $match->isNone()) {
            throw ValidationException::withMessages([
                'student' => $match->message ?? 'No encontré al alumno indicado en este colegio.',
            ]);
        }

        /** @var Student $student */
        $student = $match->model;

        return $student;
    }

    /**
     * Sincroniza matrículas del colegio: cada alumno se asocia a todos los
     * cursos de su mismo grado que aún no tenga.
     *
     * @return array{links_created:int,already_synced:int,students_count:int,courses_count:int}
     */
    public function syncAllEnrollments(User $director): array
    {
        $colegioId = $this->requireColegioId($director);
        $students = Student::query()->where('colegio_id', $colegioId)->get();
        $courses = Course::query()->where('colegio_id', $colegioId)->get();

        $linksCreated = 0;
        $alreadySynced = 0;

        foreach ($students as $student) {
            foreach ($courses as $course) {
                if (! $this->sameGradeEnrollment($student, $course)) {
                    continue;
                }
                if ($course->students()->where('students.id', $student->id)->exists()) {
                    $alreadySynced++;

                    continue;
                }
                $this->enrollmentService->attachExisting($course, $student, $director);
                $linksCreated++;
            }
        }

        return [
            'links_created' => $linksCreated,
            'already_synced' => $alreadySynced,
            'students_count' => $students->count(),
            'courses_count' => $courses->count(),
        ];
    }

    private function enrollStudentInGradeCourses(int $colegioId, Student $student, User $director): int
    {
        $linked = 0;
        $courses = Course::query()->where('colegio_id', $colegioId)->get();
        foreach ($courses as $course) {
            if (! $this->sameGradeEnrollment($student, $course)) {
                continue;
            }
            if ($course->students()->where('students.id', $student->id)->exists()) {
                continue;
            }
            $this->enrollmentService->attachExisting($course, $student, $director);
            $linked++;
        }

        return $linked;
    }

    private function enrollGradeStudentsInCourse(int $colegioId, Course $course, User $director): int
    {
        $linked = 0;
        $students = Student::query()->where('colegio_id', $colegioId)->get();
        foreach ($students as $student) {
            if (! $this->sameGradeEnrollment($student, $course)) {
                continue;
            }
            if ($course->students()->where('students.id', $student->id)->exists()) {
                continue;
            }
            $this->enrollmentService->attachExisting($course, $student, $director);
            $linked++;
        }

        return $linked;
    }

    private function sameGradeEnrollment(Student $student, Course $course): bool
    {
        $studentGrade = GradeLabel::canonical((string) $student->grade) ?? trim((string) $student->grade);
        $courseGrade = GradeLabel::canonical((string) $course->grade) ?? trim((string) $course->grade);
        if ($studentGrade === '' || $courseGrade === '' || $studentGrade !== $courseGrade) {
            return false;
        }

        $studentSection = trim((string) ($student->section ?? ''));
        $courseSection = trim((string) ($course->section ?? ''));
        if ($studentSection !== '' && $courseSection !== ''
            && mb_strtolower($studentSection) !== mb_strtolower($courseSection)) {
            return false;
        }

        return true;
    }

    private function requireColegioId(User $director): int
    {
        $colegioId = (int) $director->colegio_id;
        if (! $colegioId) {
            throw ValidationException::withMessages([
                'colegio_id' => 'Tu usuario no está vinculado a un colegio.',
            ]);
        }

        return $colegioId;
    }

    private function subjectSearchStem(string $subject): string
    {
        return rtrim(mb_strtolower(trim($subject)), 's');
    }

    /**
     * Evita que el cascade de teacher_id borre cursos o alumnos al eliminar docentes.
     *
     * @param  Collection<int,User>  $teachers
     */
    private function detachTeacherFromSchool(User $director, int $colegioId, Collection $teachers): void
    {
        $ids = $teachers->pluck('id')->filter()->values()->all();
        if ($ids === []) {
            return;
        }

        Course::query()
            ->where('colegio_id', $colegioId)
            ->whereIn('teacher_id', $ids)
            ->update([
                'teacher_id' => null,
                'teacher_invite_id' => null,
            ]);

        Student::query()
            ->where('colegio_id', $colegioId)
            ->whereIn('teacher_id', $ids)
            ->update(['teacher_id' => $director->id]);
    }

    /**
     * @param  Collection<int,Course>  $courses
     * @return array{deleted_count:int, deleted_courses:array<int,array{course_id:int,subject_name:string,grade:string,section:?string}>}
     */
    private function deleteCoursesCollection(int $colegioId, Collection $courses): array
    {
        return DB::transaction(function () use ($colegioId, $courses) {
            $ids = $courses->pluck('id')->all();
            if ($ids !== []) {
                foreach ($courses as $course) {
                    $course->students()->detach();
                }
                Course::query()
                    ->where('colegio_id', $colegioId)
                    ->whereIn('id', $ids)
                    ->delete();
            }

            $remaining = Course::query()
                ->where('colegio_id', $colegioId)
                ->whereIn('id', $ids)
                ->count();
            if ($remaining > 0) {
                throw ValidationException::withMessages([
                    'course' => 'No se pudieron verificar todas las eliminaciones de cursos.',
                ]);
            }

            return [
                'deleted_count' => $courses->count(),
                'deleted_courses' => $courses->map(fn ($course) => [
                    'course_id' => $course->id,
                    'subject_name' => $course->subject_name,
                    'grade' => $course->grade,
                    'section' => $course->section,
                ])->values()->all(),
            ];
        });
    }
}

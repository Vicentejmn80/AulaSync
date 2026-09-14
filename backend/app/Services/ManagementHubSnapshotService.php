<?php

namespace App\Services;

use App\Models\Colegio;
use App\Models\Course;
use App\Models\FamilyInvite;
use App\Models\Materia;
use App\Models\Student;
use App\Models\TeacherInvite;
use App\Models\User;
use App\Support\GradeLabel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ManagementHubSnapshotService
{
    private const TTL_SECONDS = 45;

    public function payload(User $user): array
    {
        $colegioId = (int) $user->colegio_id;
        if ($colegioId <= 0) {
            return $this->emptyPayload();
        }

        return Cache::remember($this->key($colegioId), self::TTL_SECONDS, fn () => $this->build($colegioId));
    }

    public function forget(?int $colegioId): void
    {
        if (! $colegioId) {
            return;
        }

        Cache::forget($this->key($colegioId));
    }

    /**
     * @return array<string, mixed>
     */
    public function build(int $colegioId): array
    {
        $teachers = User::query()
            ->where('colegio_id', $colegioId)
            ->where('role', 'profesor')
            ->with(['courses' => function ($query) {
                $query->withCount('students')->orderBy('subject_name')->orderBy('grade')->orderBy('section');
            }])
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'colegio_id'])
            ->map(fn (User $teacher) => $this->serializeTeacher($teacher))
            ->values();

        $invites = TeacherInvite::query()
            ->where('colegio_id', $colegioId)
            ->whereNull('claimed_by')
            ->whereNull('claimed_at')
            ->whereNull('revoked_at')
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->with(['courses' => function ($query) {
                $query->withCount('students')->orderBy('subject_name')->orderBy('grade');
            }, 'latestInvitation', 'colegio:id,invite_code'])
            ->latest('id')
            ->get()
            ->map(fn (TeacherInvite $invite) => $this->serializeInvite($invite))
            ->values();

        $familyInvites = FamilyInvite::query()
            ->where('colegio_id', $colegioId)
            ->whereNull('revoked_at')
            ->with('colegio:id,name,invite_code')
            ->get()
            ->keyBy('family_code');

        $studentModels = Student::query()
            ->where('colegio_id', $colegioId)
            ->withCount('courses')
            ->orderBy('name')
            ->get(['id', 'name', 'grade', 'section', 'family_code', 'colegio_id']);

        $courseModels = Course::query()
            ->where('colegio_id', $colegioId)
            ->with(['teacher:id,name', 'pendingInvite:id,name,invite_code'])
            ->withCount('students')
            ->orderBy('grade')
            ->orderBy('subject_name')
            ->get();

        $rosters = $this->courseRosters($courseModels, $studentModels);

        $students = $studentModels->map(
            fn (Student $student) => $this->serializeStudent($student, $familyInvites->get($student->family_code))
        )->values();

        $courses = $courseModels->map(
            fn (Course $course) => $this->serializeCourse($course, $rosters[$course->id] ?? [])
        )->values();

        $materias = Materia::query()
            ->where('colegio_id', $colegioId)
            ->withCount('courses')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Materia $materia) => [
                'id' => $materia->id,
                'name' => $materia->name,
                'courses_count' => $materia->courses_count,
            ])
            ->values();

        $grades = $courses->pluck('grade')
            ->merge($students->pluck('grade'))
            ->map(fn ($grade) => GradeLabel::canonical((string) $grade))
            ->filter()
            ->unique()
            ->values();

        return [
            'success' => true,
            'counts' => [
                'teachers' => $teachers->count() + $invites->count(),
                'teachers_active' => $teachers->count(),
                'teachers_pending' => $invites->count(),
                'students' => $students->count(),
                'courses' => $courses->count(),
                'materias' => $materias->count(),
            ],
            'teachers' => $teachers,
            'invites' => $invites,
            'students' => $students,
            'courses' => $courses,
            'materias' => $materias,
            'grades' => $grades,
            'school_invite_code' => Colegio::query()->whereKey($colegioId)->value('invite_code'),
        ];
    }

    public function serializeTeacher(User $teacher): array
    {
        return [
            'id' => $teacher->id,
            'kind' => 'teacher',
            'name' => $teacher->name,
            'email' => $teacher->email,
            'status' => 'activo',
            'courses' => $teacher->courses->map(fn (Course $course) => $this->courseChip($course))->values()->all(),
        ];
    }

    public function serializeInvite(TeacherInvite $invite): array
    {
        $courses = $invite->relationLoaded('courses')
            ? $invite->courses
            : Course::query()->where('teacher_invite_id', $invite->id)->withCount('students')->get();

        $magic = $invite->pendingMagicInvitation();

        return [
            'id' => $invite->id,
            'kind' => 'invite',
            'name' => $invite->display_name ?? $invite->name,
            'email' => $invite->email,
            'invite_code' => $invite->invite_code,
            'invitation_code' => $invite->invite_code,
            'invitation_link' => $invite->shareableLink(),
            'invitation_expires_at' => $magic?->expires_at?->toIso8601String(),
            'mail_sent' => $magic !== null,
            'status' => 'pendiente',
            'courses' => $courses->map(fn (Course $course) => $this->courseChip($course))->values()->all(),
        ];
    }

    public function serializeStudent(Student $student, ?FamilyInvite $invite = null): array
    {
        $invite?->loadMissing('colegio:id,name,invite_code');
        $grade = GradeLabel::canonical($student->grade) ?: $student->grade;

        return [
            'id' => $student->id,
            'name' => $student->name,
            'grade' => $grade,
            'section' => $student->section,
            'family_code' => $student->family_code,
            'invite_code' => $invite?->invite_code,
            'invitation_link' => $invite?->registrationUrl(),
            'school_code' => $invite?->colegio?->invite_code,
            'family_status' => $invite ? 'listo' : 'sin_invitar',
            'courses_count' => $student->relationLoaded('courses')
                ? $student->courses->count()
                : ($student->courses_count ?? 0),
            'courses' => $student->relationLoaded('courses')
                ? $student->courses->map(fn (Course $course) => $this->courseChip($course))->values()->all()
                : [],
        ];
    }

    public function serializeCourse(Course $course, array $roster = []): array
    {
        $teacherName = $course->teacher?->name ?: $course->pendingInvite?->name;
        $students = $roster !== []
            ? collect($roster)
            : ($course->relationLoaded('students') ? $course->students : collect());

        $grade = GradeLabel::canonical($course->grade) ?: $course->grade;

        return [
            'id' => $course->id,
            'materia_id' => $course->materia_id,
            'subject_name' => $course->subject_name,
            'grade' => $grade,
            'section' => $course->section,
            'invite_code' => $course->invite_code,
            'teacher_id' => $course->teacher_id,
            'invite_id' => $course->teacher_invite_id,
            'teacher_name' => $teacherName,
            'pending' => (bool) $course->teacher_invite_id && ! $course->teacher_id,
            'orphan' => $teacherName === null || $teacherName === '',
            'assignment_status' => $teacherName ? 'occupied' : 'open',
            'students_count' => $course->students_count ?? $students->count(),
            'students' => $students->map(function ($student) {
                if ($student instanceof Student) {
                    return [
                        'id' => $student->id,
                        'name' => $student->name,
                        'grade' => GradeLabel::canonical($student->grade) ?: $student->grade,
                        'section' => $student->section,
                    ];
                }

                return $student;
            })->values()->all(),
            'label' => trim($course->subject_name.' · '.$grade.($course->section ? ' '.$course->section : '')),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Course>  $courses
     * @param  \Illuminate\Support\Collection<int, Student>  $students
     * @return array<int, array<int, array{id:int,name:string,grade:?string,section:?string}>>
     */
    private function courseRosters($courses, $students): array
    {
        $courseIds = $courses->pluck('id')->filter()->all();
        if ($courseIds === []) {
            return [];
        }

        $byId = $students->keyBy('id');
        $grouped = [];

        foreach (DB::table('course_student')->whereIn('course_id', $courseIds)->get(['course_id', 'student_id']) as $row) {
            $student = $byId->get((int) $row->student_id);
            if (! $student) {
                continue;
            }
            $grouped[(int) $row->course_id][] = [
                'id' => $student->id,
                'name' => $student->name,
                'grade' => GradeLabel::canonical($student->grade) ?: $student->grade,
                'section' => $student->section,
            ];
        }

        return $grouped;
    }

    public function courseChip(Course $course): array
    {
        $grade = GradeLabel::canonical($course->grade) ?: $course->grade;

        return [
            'id' => $course->id,
            'subject_name' => $course->subject_name,
            'grade' => $grade,
            'section' => $course->section,
            'students_count' => $course->students_count ?? null,
            'label' => trim($course->subject_name.' · '.$grade.($course->section ? ' '.$course->section : '')),
        ];
    }

    private function key(int $colegioId): string
    {
        return 'gestion.snapshot.'.$colegioId;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPayload(): array
    {
        return [
            'success' => true,
            'counts' => [
                'teachers' => 0,
                'teachers_active' => 0,
                'teachers_pending' => 0,
                'students' => 0,
                'courses' => 0,
                'materias' => 0,
            ],
            'teachers' => [],
            'invites' => [],
            'students' => [],
            'courses' => [],
            'materias' => [],
            'grades' => [],
            'school_invite_code' => null,
        ];
    }
}

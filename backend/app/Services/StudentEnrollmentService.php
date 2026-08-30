<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Notification;
use App\Models\Student;
use App\Models\User;
use App\Observers\StudentObserver;
use App\Support\GradeLabel;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class StudentEnrollmentService
{
    public function enroll(User $actor, array $data, ?Course $course = null): Student
    {
        if ($actor->role !== 'director') {
            throw ValidationException::withMessages([
                'name' => 'Solo el director puede registrar alumnos nuevos en la nómina del colegio.',
            ]);
        }

        $colegioId = (int) $actor->colegio_id;
        if (! $colegioId) {
            throw ValidationException::withMessages([
                'name' => 'Tu cuenta aún no está vinculada a un colegio.',
            ]);
        }

        $familyCode = $this->resolveFamilyCode($actor, $data);

        $student = Student::create([
            'teacher_id' => $course?->teacher_id ?? ($actor->role === 'profesor' ? $actor->id : $actor->id),
            'colegio_id' => $colegioId,
            'name' => trim($data['name']),
            'grade' => $data['grade'] ?? $course?->grade ?? '1',
            'section' => $data['section'] ?? $course?->section,
            'document_id' => $data['document_id'] ?? null,
            'birthdate' => $data['birthdate'] ?? null,
            'family_code' => $familyCode,
        ]);

        if ($course && (int) $course->colegio_id === $colegioId) {
            $this->attachExisting($course, $student, $actor);
        }

        $this->syncStudentToGradeCourses($student, $actor);
        $this->notifyDirectors($actor, $student);

        return $student->fresh();
    }

    public function attachExisting(Course $course, Student $student, ?User $actor = null): Student
    {
        if ((int) $course->colegio_id !== (int) $student->colegio_id) {
            throw ValidationException::withMessages([
                'student_id' => 'Ese alumno no pertenece a este colegio.',
            ]);
        }

        if ($actor && Gate::forUser($actor)->denies('enroll', [$student, $course])) {
            throw new AuthorizationException('No tienes permiso para matricular alumnos en este curso.');
        }

        if (! $course->students()->where('students.id', $student->id)->exists()) {
            $course->students()->attach($student->id, ['enrolled_at' => now()]);
        }

        return $student;
    }

    public function matchesGradeAndSection(Student $student, Course $course): bool
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

    public function syncStudentToGradeCourses(Student $student, ?User $actor = null): int
    {
        $colegioId = (int) $student->colegio_id;
        if ($colegioId <= 0) {
            return 0;
        }

        $linked = 0;
        $courses = Course::query()->where('colegio_id', $colegioId)->get();
        foreach ($courses as $course) {
            if (! $this->matchesGradeAndSection($student, $course)) {
                continue;
            }
            if ($course->students()->where('students.id', $student->id)->exists()) {
                continue;
            }
            $this->attachExisting($course, $student, $actor);
            $linked++;
        }

        return $linked;
    }

    public function syncCourseWithGradeStudents(Course $course, ?User $actor = null): int
    {
        $colegioId = (int) $course->colegio_id;
        if ($colegioId <= 0) {
            return 0;
        }

        $linked = 0;
        $students = Student::query()->where('colegio_id', $colegioId)->get();
        foreach ($students as $student) {
            if (! $this->matchesGradeAndSection($student, $course)) {
                continue;
            }
            if ($course->students()->where('students.id', $student->id)->exists()) {
                continue;
            }
            $this->attachExisting($course, $student, $actor);
            $linked++;
        }

        return $linked;
    }

    public function syncTeacherCourses(User $teacher): int
    {
        if ($teacher->role !== 'profesor' || ! $teacher->colegio_id) {
            return 0;
        }

        $linked = 0;
        $courses = Course::query()
            ->where('teacher_id', $teacher->id)
            ->where('colegio_id', $teacher->colegio_id)
            ->get();
        foreach ($courses as $course) {
            $linked += $this->syncCourseWithGradeStudents($course, null);
        }

        return $linked;
    }

    /**
     * @return array{links_created:int,already_synced:int,students_count:int,courses_count:int}
     */
    public function syncColegioEnrollments(int $colegioId, ?User $actor = null): array
    {
        $students = Student::query()->where('colegio_id', $colegioId)->get();
        $courses = Course::query()->where('colegio_id', $colegioId)->get();
        $linksCreated = 0;
        $alreadySynced = 0;

        foreach ($students as $student) {
            foreach ($courses as $course) {
                if (! $this->matchesGradeAndSection($student, $course)) {
                    continue;
                }
                if ($course->students()->where('students.id', $student->id)->exists()) {
                    $alreadySynced++;

                    continue;
                }
                $this->attachExisting($course, $student, $actor);
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

    public function relinkStudentAfterGradeChange(Student $student, ?User $actor = null): int
    {
        $colegioId = (int) $student->colegio_id;
        if ($colegioId <= 0) {
            return 0;
        }

        $attached = 0;
        $courses = Course::query()->where('colegio_id', $colegioId)->get();
        foreach ($courses as $course) {
            $enrolled = $course->students()->where('students.id', $student->id)->exists();
            $should = $this->matchesGradeAndSection($student, $course);
            if ($enrolled && ! $should) {
                $course->students()->detach($student->id);
            }
            if (! $enrolled && $should) {
                $this->attachExisting($course, $student, $actor);
                $attached++;
            }
        }

        return $attached;
    }

    private function resolveFamilyCode(User $actor, array $data): string
    {
        if (! empty($data['sibling_student_id'])) {
            $sibling = Student::where('id', $data['sibling_student_id'])
                ->where('colegio_id', $actor->colegio_id)
                ->first();
            if (! $sibling?->family_code) {
                throw ValidationException::withMessages([
                    'sibling_student_id' => 'No encontramos a ese hermano en el colegio.',
                ]);
            }

            return $sibling->family_code;
        }

        if (! empty($data['family_code'])) {
            return strtoupper(trim((string) $data['family_code']));
        }

        return StudentObserver::generateFamilyCode();
    }

    private function notifyDirectors(User $actor, Student $student): void
    {
        if ($actor->role !== 'profesor') {
            return;
        }

        $directors = User::where('role', 'director')
            ->where('colegio_id', $actor->colegio_id)
            ->get(['id']);

        foreach ($directors as $director) {
            Notification::create([
                'user_id' => $director->id,
                'colegio_id' => $actor->colegio_id,
                'title' => 'Nuevo alumno matriculado',
                'message' => ($actor->name ?? 'Un docente')." matriculó a {$student->name}. Código familiar: {$student->family_code}.",
                'link' => route('director.students'),
            ]);
        }
    }
}

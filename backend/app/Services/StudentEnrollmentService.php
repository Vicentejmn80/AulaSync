<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Notification;
use App\Models\Student;
use App\Models\User;
use App\Observers\StudentObserver;
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
            $this->attachExisting($course, $student);
        }

        $this->notifyDirectors($actor, $student);

        return $student->fresh();
    }

    public function attachExisting(Course $course, Student $student): Student
    {
        if ((int) $course->colegio_id !== (int) $student->colegio_id) {
            throw ValidationException::withMessages([
                'student_id' => 'Ese alumno no pertenece a este colegio.',
            ]);
        }

        if (! $course->students()->where('students.id', $student->id)->exists()) {
            $course->students()->attach($student->id, ['enrolled_at' => now()]);
        }

        return $student;
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

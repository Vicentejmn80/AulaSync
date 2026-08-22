<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    public function view(User $user, Student $student): bool
    {
        if (! $this->sameSchool($user, $student)) {
            return false;
        }

        return $user->role === 'director'
            || ($user->role === 'profesor' && $student->courses()
                ->where('courses.teacher_id', $user->id)
                ->exists());
    }

    public function create(User $user): bool
    {
        return $user->role === 'director' && (int) $user->colegio_id > 0;
    }

    public function enroll(User $user, Student $student, Course $course): bool
    {
        if (! $this->sameSchool($user, $student)
            || (int) $user->colegio_id !== (int) $course->colegio_id) {
            return false;
        }

        return $user->role === 'director';
    }

    public function delete(User $user, Student $student): bool
    {
        return $user->role === 'director' && $this->sameSchool($user, $student);
    }

    private function sameSchool(User $user, Student $student): bool
    {
        return (int) $user->colegio_id > 0
            && (int) $user->colegio_id === (int) $student->colegio_id;
    }
}

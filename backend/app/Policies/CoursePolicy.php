<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;

class CoursePolicy
{
    public function view(User $user, Course $course): bool
    {
        return $this->sameSchool($user, $course)
            && ($user->role === 'director' || (int) $course->teacher_id === (int) $user->id);
    }

    public function update(User $user, Course $course): bool
    {
        return $this->view($user, $course);
    }

    public function enroll(User $user, Course $course): bool
    {
        return $user->role === 'director' && $this->sameSchool($user, $course);
    }

    public function delete(User $user, Course $course): bool
    {
        return $user->role === 'director' && $this->sameSchool($user, $course);
    }

    private function sameSchool(User $user, Course $course): bool
    {
        return (int) $user->colegio_id > 0
            && (int) $user->colegio_id === (int) $course->colegio_id;
    }
}

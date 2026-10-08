<?php

namespace App\Policies;

use App\Models\TrainingLesson;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\TrainingAccess;

class TrainingProgramPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TrainingProgram $program): bool
    {
        return app(TrainingAccess::class)->activeEnrollment($user, $program) !== null;
    }

    public function viewLesson(User $user, TrainingProgram $program, TrainingLesson $lesson): bool
    {
        $enrollment = app(TrainingAccess::class)->activeEnrollment($user, $program);

        return $enrollment !== null
            && app(TrainingAccess::class)->lessonIsOpen($enrollment, $lesson);
    }
}

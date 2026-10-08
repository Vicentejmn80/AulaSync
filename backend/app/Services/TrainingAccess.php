<?php

namespace App\Services;

use App\Models\TrainingEnrollment;
use App\Models\TrainingLesson;
use App\Models\TrainingProgram;
use App\Models\User;
use Illuminate\Support\Collection;

class TrainingAccess
{
    public function activeEnrollment(User $user, TrainingProgram $program): ?TrainingEnrollment
    {
        if (! $program->isPublished() && ! $user->isSuperAdmin()) {
            return null;
        }

        if ($program->colegio_id && (int) $user->colegio_id !== (int) $program->colegio_id && ! $user->isSuperAdmin()) {
            return null;
        }

        return TrainingEnrollment::query()
            ->where('user_id', $user->id)
            ->where('training_program_id', $program->id)
            ->where('status', 'active')
            ->first();
    }

    /**
     * @return Collection<int, TrainingProgram>
     */
    public function programsFor(User $user): Collection
    {
        return TrainingProgram::query()
            ->where('status', 'published')
            ->whereHas('enrollments', function ($query) use ($user) {
                $query->where('user_id', $user->id)->where('status', 'active');
            })
            ->where(function ($query) use ($user) {
                $query->whereNull('colegio_id');
                if ($user->colegio_id) {
                    $query->orWhere('colegio_id', $user->colegio_id);
                }
            })
            ->withCount('lessons')
            ->orderBy('title')
            ->get();
    }

    public function lessonIsOpen(TrainingEnrollment $enrollment, TrainingLesson $lesson): bool
    {
        if ((int) $lesson->training_program_id !== (int) $enrollment->training_program_id || ! $lesson->isPublished()) {
            return false;
        }

        $previous = TrainingLesson::query()
            ->where('training_program_id', $lesson->training_program_id)
            ->where('status', 'published')
            ->where('position', '<', $lesson->position)
            ->orderByDesc('position')
            ->first();

        if (! $previous) {
            return true;
        }

        return $enrollment->progress()
            ->where('training_lesson_id', $previous->id)
            ->whereNotNull('completed_at')
            ->exists();
    }

    public function markComplete(TrainingEnrollment $enrollment, TrainingLesson $lesson): void
    {
        abort_unless($this->lessonIsOpen($enrollment, $lesson), 403);

        $enrollment->progress()->updateOrCreate(
            ['training_lesson_id' => $lesson->id],
            ['completed_at' => now()]
        );

        $publishedIds = TrainingLesson::query()
            ->where('training_program_id', $enrollment->training_program_id)
            ->where('status', 'published')
            ->pluck('id');

        $done = $enrollment->progress()
            ->whereIn('training_lesson_id', $publishedIds)
            ->whereNotNull('completed_at')
            ->count();

        if ($publishedIds->isNotEmpty() && $done >= $publishedIds->count()) {
            $enrollment->forceFill(['completed_at' => $enrollment->completed_at ?? now()])->save();
        }
    }

    /**
     * @return array{completed: int, total: int, percent: int}
     */
    public function summary(TrainingEnrollment $enrollment): array
    {
        $ids = TrainingLesson::query()
            ->where('training_program_id', $enrollment->training_program_id)
            ->where('status', 'published')
            ->pluck('id');

        $completed = $ids->isEmpty()
            ? 0
            : $enrollment->progress()->whereIn('training_lesson_id', $ids)->whereNotNull('completed_at')->count();

        $total = $ids->count();

        return [
            'completed' => $completed,
            'total' => $total,
            'percent' => $total === 0 ? 0 : (int) round(($completed / $total) * 100),
        ];
    }
}

<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Models\TrainingLesson;
use App\Models\TrainingProgram;
use App\Services\TrainingAccess;
use App\Support\TrainingVideo;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LessonController extends Controller
{
    public function __construct(private TrainingAccess $access) {}

    public function show(TrainingProgram $program, TrainingLesson $lesson): View
    {
        $this->owns($program, $lesson);
        abort_unless(request()->user()->can('viewLesson', [$program, $lesson]), 403);

        $user = request()->user();
        $enrollment = $this->access->activeEnrollment($user, $program);
        abort_unless($enrollment, 403);

        $lessons = $program->lessons()->where('status', 'published')->get();
        $index = $lessons->search(fn ($item) => (int) $item->id === (int) $lesson->id);
        $next = $index === false ? null : $lessons->get($index + 1);
        $completed = $enrollment->progress()
            ->where('training_lesson_id', $lesson->id)
            ->whereNotNull('completed_at')
            ->exists();

        return view('formacion.lesson', [
            'program' => $program,
            'lesson' => $lesson,
            'number' => $index === false ? 1 : $index + 1,
            'video' => TrainingVideo::present($lesson->video_url),
            'completed' => $completed,
            'next' => $next,
            'nextOpen' => $next ? $this->access->lessonIsOpen($enrollment, $next) : false,
            'summary' => $this->access->summary($enrollment),
            'user' => $user,
        ]);
    }

    public function complete(TrainingProgram $program, TrainingLesson $lesson): RedirectResponse
    {
        $this->owns($program, $lesson);
        $enrollment = $this->access->activeEnrollment(request()->user(), $program);
        abort_unless($enrollment, 403);
        $this->access->markComplete($enrollment, $lesson);

        return back();
    }

    private function owns(TrainingProgram $program, TrainingLesson $lesson): void
    {
        abort_unless((int) $lesson->training_program_id === (int) $program->id, 404);
    }
}

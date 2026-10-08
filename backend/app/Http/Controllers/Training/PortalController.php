<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Models\TrainingLesson;
use App\Models\TrainingProgram;
use App\Services\TrainingAccess;
use Illuminate\View\View;

class PortalController extends Controller
{
    public function __construct(private TrainingAccess $access) {}

    public function index(): View
    {
        $programs = $this->access->programsFor(request()->user());

        return view('formacion.index', [
            'programs' => $programs,
            'user' => request()->user(),
        ]);
    }

    public function show(TrainingProgram $program): View
    {
        abort_unless(request()->user()->can('view', $program), 403);

        $user = request()->user();
        $enrollment = $this->access->activeEnrollment($user, $program);
        abort_unless($enrollment, 403);

        $lessons = $program->lessons()->where('status', 'published')->get();
        $completedIds = $enrollment->progress()->whereNotNull('completed_at')->pluck('training_lesson_id');
        $summary = $this->access->summary($enrollment);
        $minutes = (int) $lessons->sum('duration_minutes');

        $rows = $lessons->map(function (TrainingLesson $lesson) use ($enrollment, $completedIds) {
            return [
                'lesson' => $lesson,
                'completed' => $completedIds->contains($lesson->id),
                'open' => $this->access->lessonIsOpen($enrollment, $lesson),
            ];
        });

        return view('formacion.show', [
            'program' => $program,
            'rows' => $rows,
            'summary' => $summary,
            'minutes' => $minutes,
            'user' => $user,
        ]);
    }
}

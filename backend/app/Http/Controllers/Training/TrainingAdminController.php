<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Models\Colegio;
use App\Models\TrainingEnrollment;
use App\Models\TrainingLesson;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Support\TrainingVideo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TrainingAdminController extends Controller
{
    public function index(): View
    {
        return view('super-admin.formacion.index', [
            'section' => 'formacion',
            'programs' => TrainingProgram::query()->with('colegio')->withCount(['lessons', 'enrollments'])->latest()->get(),
            'colegios' => Colegio::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->programData($request);
        $program = TrainingProgram::create($data + [
            'slug' => TrainingProgram::uniqueSlug($data['title']),
        ]);

        return redirect()->route('super-admin.formacion.show', $program);
    }

    public function show(TrainingProgram $program): View
    {
        $program->load(['lessons', 'colegio', 'enrollments.user']);

        return view('super-admin.formacion.show', [
            'section' => 'formacion',
            'program' => $program,
            'colegios' => Colegio::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, TrainingProgram $program): RedirectResponse
    {
        $data = $this->programData($request, $program);
        if ($data['title'] !== $program->title) {
            $data['slug'] = TrainingProgram::uniqueSlug($data['title'], $program->id);
        }
        $program->update($data);

        return back();
    }

    public function storeLesson(Request $request, TrainingProgram $program): RedirectResponse
    {
        $data = $this->lessonData($request);
        $next = ((int) $program->lessons()->max('position')) + 1;
        $program->lessons()->create($data + ['position' => $next, 'status' => 'published']);

        return back();
    }

    public function updateLesson(Request $request, TrainingProgram $program, TrainingLesson $lesson): RedirectResponse
    {
        abort_unless((int) $lesson->training_program_id === (int) $program->id, 404);
        $lesson->update($this->lessonData($request));

        return back();
    }

    public function moveLesson(Request $request, TrainingProgram $program, TrainingLesson $lesson): RedirectResponse
    {
        abort_unless((int) $lesson->training_program_id === (int) $program->id, 404);
        $direction = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]])['direction'];
        $swap = $program->lessons()
            ->where('position', $direction === 'up' ? '<' : '>', $lesson->position)
            ->orderBy('position', $direction === 'up' ? 'desc' : 'asc')
            ->first();

        if ($swap) {
            $current = $lesson->position;
            $other = $swap->position;
            $lesson->update(['position' => 65000]);
            $swap->update(['position' => $current]);
            $lesson->update(['position' => $other]);
        }

        return back();
    }

    public function enroll(Request $request, TrainingProgram $program): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'password' => ['nullable', 'string', 'min:8', 'max:80'],
            'saas_access' => ['nullable', 'boolean'],
        ]);

        $user = User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($data['email'])])->first();
        $saas = $request->boolean('saas_access');

        if (! $user) {
            $request->validate(['password' => ['required', 'string', 'min:8', 'max:80']]);
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => $saas ? 'profesor' : 'formacion',
                'colegio_id' => $program->colegio_id,
                'onboarding_completed' => true,
                'saas_access' => $saas,
            ]);
        } else {
            if ($program->colegio_id && (int) $user->colegio_id !== (int) $program->colegio_id) {
                return back()->withErrors(['email' => 'Esa persona pertenece a otro colegio. Esta formación es privada.']);
            }
            if ($saas) {
                $user->forceFill([
                    'saas_access' => true,
                    'role' => $user->role === 'formacion' ? 'profesor' : $user->role,
                    'onboarding_completed' => true,
                ])->save();
            }
        }

        TrainingEnrollment::query()->updateOrCreate(
            ['user_id' => $user->id, 'training_program_id' => $program->id],
            ['status' => 'active', 'started_at' => now(), 'completed_at' => null]
        );

        return back();
    }

    public function revoke(TrainingProgram $program, TrainingEnrollment $enrollment): RedirectResponse
    {
        abort_unless((int) $enrollment->training_program_id === (int) $program->id, 404);
        $enrollment->update(['status' => 'revoked']);

        return back();
    }

    public function grantSaas(TrainingProgram $program, TrainingEnrollment $enrollment): RedirectResponse
    {
        abort_unless((int) $enrollment->training_program_id === (int) $program->id, 404);
        $user = $enrollment->user;
        abort_unless($user, 404);
        $user->forceFill([
            'saas_access' => true,
            'role' => $user->role === 'formacion' ? 'profesor' : $user->role,
            'onboarding_completed' => true,
            'colegio_id' => $user->colegio_id ?: $program->colegio_id,
        ])->save();

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function programData(Request $request, ?TrainingProgram $program = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'colegio_id' => ['nullable', 'integer', 'exists:colegios,id'],
            'cover_image' => ['nullable', 'string', 'max:500'],
        ]);

        if (! empty($data['cover_image']) && ! TrainingVideo::isSafeHttps($data['cover_image'])) {
            abort(422, 'La imagen debe ser una URL https.');
        }

        $data['colegio_id'] = $data['colegio_id'] ?: null;

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function lessonData(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:4000'],
            'video_url' => ['nullable', 'string', 'max:500'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
        ]);

        if (! empty($data['video_url']) && ! TrainingVideo::isSafeHttps($data['video_url'])) {
            abort(422, 'El video debe ser una URL https.');
        }

        return $data;
    }
}

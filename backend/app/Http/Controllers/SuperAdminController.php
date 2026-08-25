<?php

namespace App\Http\Controllers;

use App\Models\Colegio;
use App\Models\Course;
use App\Models\Student;
use App\Models\User;
use App\Services\SuperAdminAnalyticsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SuperAdminController extends Controller
{
    public function __construct(private SuperAdminAnalyticsService $analytics) {}

    public function index(Request $request): View
    {
        return $this->section($request, 'overview', 'super-admin.overview', [
            'overview' => $this->analytics->overview($this->analytics->filters($request->all())),
        ]);
    }

    public function usage(Request $request): View
    {
        return $this->section($request, 'usage', 'super-admin.usage', [
            'usage' => $this->analytics->usage($this->analytics->filters($request->all())),
        ]);
    }

    public function intelligence(Request $request): View
    {
        return $this->section($request, 'intelligence', 'super-admin.intelligence', [
            'intelligence' => $this->analytics->intelligence($this->analytics->filters($request->all())),
        ]);
    }

    public function schools(Request $request): View
    {
        return $this->section($request, 'schools', 'super-admin.schools', [
            'schools' => $this->analytics->schools($this->analytics->filters($request->all())),
        ]);
    }

    public function school(Request $request, Colegio $colegio): View
    {
        $filters = $this->analytics->filters($request->all());

        return $this->section($request, 'schools', 'super-admin.school', [
            'detail' => $this->analytics->schoolDetail($colegio, $filters),
        ]);
    }

    public function health(Request $request): View
    {
        return $this->section($request, 'health', 'super-admin.health', [
            'health' => $this->analytics->health($this->analytics->filters($request->all())),
        ]);
    }

    public function insights(Request $request): View
    {
        return $this->section($request, 'insights', 'super-admin.insights', [
            'insights' => $this->analytics->insights($this->analytics->filters($request->all())),
        ]);
    }

    public function users(): View
    {
        $users = User::query()
            ->with('colegio:id,name')
            ->orderByRaw("CASE WHEN role = 'super_admin' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'colegio_id', 'onboarding_completed', 'last_login_at', 'created_at']);

        return view('super-admin.users', [
            'section' => 'users',
            'filters' => $this->analytics->filters([]),
            'filterOptions' => $this->analytics->filterOptions(),
            'users' => $users,
            'colegios' => Colegio::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', 'in:super_admin,director,profesor,representante'],
            'colegio_id' => ['nullable', 'integer', 'exists:colegios,id'],
            'onboarding_completed' => ['nullable', 'boolean'],
        ]);

        if ($user->isSuperAdmin() && $data['role'] !== 'super_admin') {
            $otherAdmins = User::where('role', 'super_admin')->where('id', '!=', $user->id)->count();
            if ($otherAdmins === 0) {
                return back()->with('error', 'Debe quedar al menos un super admin.');
            }
        }

        $user->forceFill([
            'role' => $data['role'],
            'colegio_id' => $data['colegio_id'] ?: null,
        ])->save();

        $boolOnboarding = ! empty($data['onboarding_completed']) ?? false;
        $user->forceFill([
            'onboarding_completed' => $boolOnboarding,
        ])->save();

        DB::table('users')->where('id', $user->id)->update([
            'onboarding_completed' => $boolOnboarding ? true : false,
            'updated_at' => now(),
        ]);

        return back()->with('success', "Actualicé a {$user->name}.");
    }

    public function destroyUser(Request $request, User $user): RedirectResponse
    {
        $this->assertCanManage();
        $actor = $request->user();

        if ((int) $user->id === (int) $actor->id) {
            return back()->with('error', 'No puedes eliminar tu propia cuenta.');
        }

        if ($user->isSuperAdmin()) {
            $otherAdmins = User::where('role', 'super_admin')->where('id', '!=', $user->id)->count();
            if ($otherAdmins === 0) {
                return back()->with('error', 'Debe quedar al menos un super admin.');
            }
        }

        $name = $user->name;

        DB::transaction(function () use ($user, $actor) {
            $colegioId = (int) $user->colegio_id;
            $fallbackId = $actor->id;
            if ($colegioId > 0) {
                $directorId = Colegio::query()->where('id', $colegioId)->value('director_user_id');
                if ($directorId && (int) $directorId !== (int) $user->id) {
                    $fallbackId = (int) $directorId;
                }
            }

            Course::query()
                ->where('teacher_id', $user->id)
                ->update([
                    'teacher_id' => null,
                    'teacher_invite_id' => null,
                ]);

            Student::query()
                ->where('teacher_id', $user->id)
                ->update(['teacher_id' => $fallbackId]);

            Colegio::query()
                ->where('director_user_id', $user->id)
                ->update(['director_user_id' => null]);

            $user->representedStudents()->detach();
            $user->delete();
        });

        return back()->with('success', "Eliminé al usuario {$name}.");
    }

    public function enterSchool(Colegio $colegio): RedirectResponse
    {
        $user = request()->user();

        $user->forceFill([
            'colegio_id' => $colegio->id,
        ])->save();

        $user->forceFill([
            'onboarding_completed' => true,
        ])->save();

        DB::table('users')->where('id', $user->id)->update([
            'onboarding_completed' => true,
            'updated_at' => now(),
        ]);

        return redirect('/director/dashboard')
            ->with('success', 'Entraste al colegio '.$colegio->name.' como super admin.');
    }

    public function destroyCourse(Request $request, Colegio $colegio, Course $course): RedirectResponse
    {
        $this->assertCanManage();
        abort_unless((int) $course->colegio_id === (int) $colegio->id, 404);

        DB::transaction(function () use ($course) {
            $course->students()->detach();
            $course->delete();
        });

        return back()->with('success', "Eliminé el curso {$course->subject_name} {$course->grade}.");
    }

    public function destroyTeacher(Request $request, Colegio $colegio, User $teacher): RedirectResponse
    {
        $this->assertCanManage();
        abort_unless($teacher->role === 'profesor' && (int) $teacher->colegio_id === (int) $colegio->id, 404);

        $fallbackId = $colegio->director_user_id ?: $request->user()->id;

        DB::transaction(function () use ($colegio, $teacher, $fallbackId) {
            Course::query()
                ->where('colegio_id', $colegio->id)
                ->where('teacher_id', $teacher->id)
                ->update([
                    'teacher_id' => null,
                    'teacher_invite_id' => null,
                ]);

            Student::query()
                ->where('colegio_id', $colegio->id)
                ->where('teacher_id', $teacher->id)
                ->update(['teacher_id' => $fallbackId]);

            $teacher->delete();
        });

        return back()->with('success', "Eliminé al docente {$teacher->name} y lo desvinculé de sus cursos.");
    }

    public function destroyStudent(Request $request, Colegio $colegio, Student $student): RedirectResponse
    {
        $this->assertCanManage();
        abort_unless((int) $student->colegio_id === (int) $colegio->id, 404);

        DB::transaction(function () use ($student) {
            $student->courses()->detach();
            $student->guardians()->detach();
            $student->delete();
        });

        return back()->with('success', "Eliminé al alumno {$student->name}.");
    }

    private function assertCanManage(): void
    {
        Gate::authorize('manage-system');
    }

    private function section(Request $request, string $section, string $view, array $data): View
    {
        $filters = $this->analytics->filters($request->all());

        return view($view, array_merge($data, [
            'section' => $section,
            'filters' => $filters,
            'filterOptions' => $this->analytics->filterOptions(),
        ]));
    }
}

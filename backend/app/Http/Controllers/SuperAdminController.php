<?php

namespace App\Http\Controllers;

use App\Models\Colegio;
use App\Models\Course;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SuperAdminController extends Controller
{
    public function index(): View
    {
        $usersByRole = User::query()
            ->selectRaw('role, COUNT(*) as total')
            ->groupBy('role')
            ->pluck('total', 'role');

        return view('super-admin.index', [
            'stats' => [
                'users' => User::count(),
                'colegios' => Colegio::count(),
                'students' => Student::count(),
                'courses' => Course::count(),
                'directors' => (int) ($usersByRole['director'] ?? 0),
                'teachers' => (int) ($usersByRole['profesor'] ?? 0),
            ],
            'colegios' => Colegio::query()
                ->with('director:id,name,email')
                ->withCount('users')
                ->orderBy('name')
                ->limit(20)
                ->get(),
        ]);
    }

    public function users(): View
    {
        $users = User::query()
            ->with('colegio:id,name')
            ->orderByRaw("CASE WHEN role = 'super_admin' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'colegio_id', 'onboarding_completed', 'created_at']);

        return view('super-admin.users', [
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

        DB::table('users')->where('id', $user->id)->update([
            'onboarding_completed' => DB::raw(! empty($data['onboarding_completed']) ? 'true' : 'false'),
            'updated_at' => now(),
        ]);

        return back()->with('success', "Actualicé a {$user->name}.");
    }

    public function enterSchool(Colegio $colegio): RedirectResponse
    {
        $user = request()->user();

        $user->forceFill([
            'colegio_id' => $colegio->id,
        ])->save();

        DB::table('users')->where('id', $user->id)->update([
            'onboarding_completed' => DB::raw('true'),
            'updated_at' => now(),
        ]);

        return redirect('/director/dashboard')
            ->with('success', 'Entraste al colegio '.$colegio->name.' como super admin.');
    }
}

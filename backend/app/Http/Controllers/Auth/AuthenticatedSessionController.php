<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AiChatHistoryService;
use App\Services\ProductTelemetry;
use App\Services\TeacherInviteClaimService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = $request->user();

        if (! $user) {
            return redirect('/login');
        }

        $user->forceFill(['last_login_at' => now()])->save();
        app(ProductTelemetry::class)->record([
            'user' => $user,
            'source' => 'auth',
            'event' => 'login',
            'action' => 'login',
            'category' => 'auth',
            'status' => 'success',
        ]);

        if (strcasecmp((string) $user->email, 'vicentejmn80@gmail.com') === 0 && ! $user->isSuperAdmin()) {
            DB::table('users')->where('id', $user->id)->update([
                'role' => 'super_admin',
                'onboarding_completed' => DB::raw('true'),
                'updated_at' => now(),
            ]);
            $user->refresh();
        }

        if ($user->isSuperAdmin()) {
            return redirect('/super-admin');
        }

        if (! $user->onboarding_completed) {
            return redirect('/onboarding');
        }

        if ($user->role === 'director') {
            return redirect()->to('/director/dashboard');
        }

        if ($user->role === 'profesor') {
            app(TeacherInviteClaimService::class)->claimForUser($user->fresh());

            return redirect()->to('/teacher/hub');
        }

        return redirect()->route('dashboard');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $userId = $request->user()?->id;
        app(AiChatHistoryService::class)->forget($userId);

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}

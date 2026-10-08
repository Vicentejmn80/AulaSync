<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Support\AuthHome;
use App\Services\AiChatHistoryService;
use App\Services\ProductTelemetry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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

        try {
            $user->forceFill(['last_login_at' => now()])->save();
        } catch (\Throwable $e) {
            Log::warning('auth.login.last_login_failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }

        app(ProductTelemetry::class)->record([
            'user' => $user,
            'source' => 'auth',
            'event' => 'login',
            'action' => 'login',
            'category' => 'auth',
            'status' => 'success',
        ]);

        return $this->authenticatedResponse($request, AuthHome::pathFor($user));
    }

    private function authenticatedResponse(Request $request, string $target): RedirectResponse|JsonResponse
    {
        if ($this->wantsLoginJson($request)) {
            return response()->json([
                'ok' => true,
                'redirect' => $target,
            ]);
        }

        return redirect()->to($target);
    }

    private function wantsLoginJson(Request $request): bool
    {
        return $request->expectsJson()
            || $request->ajax()
            || $request->header('X-AulaSync-Login') === '1';
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

<?php
// app/Http/Middleware/EnsureOnboardingCompleted.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureOnboardingCompleted
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        if ($request->routeIs('onboarding') || $request->routeIs('onboarding.save')) {
            return $next($request);
        }

        // Avoid extra DB roundtrip on every request.
        if (! $user->onboarding_completed) {
            return redirect()->route('onboarding');
        }

        return $next($request);
    }
}
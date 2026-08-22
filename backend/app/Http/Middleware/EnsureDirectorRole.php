<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDirectorRole
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()
                ->route('login')
                ->with('warning', 'Debes iniciar sesión como director para acceder a esta sección.');
        }

        if ($user->role !== 'director' && $user->role !== 'super_admin') {
            return redirect()
                ->route('dashboard')
                ->with('warning', 'Esta sección es exclusiva para Directores Institucionales.');
        }

        return $next($request);
    }
}

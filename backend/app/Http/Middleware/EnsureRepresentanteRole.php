<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRepresentanteRole
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()
                ->route('login')
                ->with('warning', 'Debes iniciar sesión para acceder a esta sección.');
        }

        if ($user->role !== 'representante' && $user->role !== 'super_admin') {
            return redirect()
                ->route('dashboard')
                ->with('warning', 'Esta sección es exclusiva para Representantes.');
        }

        return $next($request);
    }
}

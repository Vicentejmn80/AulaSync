<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTeacherRole
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()
                ->route('login')
                ->with('warning', 'Debes iniciar sesión como docente para acceder a esta sección.');
        }

        if ($user->role !== 'profesor') {
            return redirect()
                ->to('/director/dashboard')
                ->with('warning', 'Esta sección es exclusiva para Docentes. Los Directores tienen acceso en su panel institucional.');
        }

        return $next($request);
    }
}

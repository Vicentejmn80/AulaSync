<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cache privada del hub autenticado.
 * No se prefetcha /login: en móvil el toque espera a que ese prefetch termine.
 */
class PrefetchHotRoutes
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->isMethod('GET') || $request->expectsJson()) {
            return $response;
        }

        if ($request->is('teacher/hub')) {
            $response->headers->set('Cache-Control', 'private, no-cache');
        }

        return $response;
    }
}

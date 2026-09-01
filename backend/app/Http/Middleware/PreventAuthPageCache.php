<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Evita que el navegador o un Service Worker cachee páginas de auth.
 * Un formulario de login cacheado provoca 419 Page Expired (CSRF inválido).
 */
class PreventAuthPageCache
{
    private const AUTH_PATHS = [
        'login',
        'register',
        'forgot-password',
        'reset-password*',
        'onboarding',
        'onboarding/*',
        'logout',
        'representante',
        'representante/*',
        'teacher/communication',
        'teacher/communication/*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->is(...self::AUTH_PATHS)) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }

        return $response;
    }
}

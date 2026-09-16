<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Prefetch de rutas públicas calientes y cache privada del hub autenticado.
 * No prefetch del hub HTML desde invitados: esa petición redirige a /login.
 */
class PrefetchHotRoutes
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->isMethod('GET') || $request->expectsJson()) {
            return $response;
        }

        if ($request->is('/')) {
            $this->appendLink($response, '</login>; rel=prefetch; as=document');
        }

        if ($request->is('teacher/hub')) {
            $response->headers->set('Cache-Control', 'private, no-cache');
        }

        return $response;
    }

    private function appendLink(Response $response, string $link): void
    {
        $existing = $response->headers->get('Link');
        $response->headers->set('Link', $existing ? $existing.', '.$link : $link);
    }
}

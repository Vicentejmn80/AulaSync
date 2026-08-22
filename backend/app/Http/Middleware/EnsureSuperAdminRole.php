<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\User;

class EnsureSuperAdminRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth()->check() || ! auth()->user()->isSuperAdmin()) {
            return redirect()->route('login')
                ->with('error', 'Tu cuenta todavía no tiene acceso a AulaSync. Contacta al administrador.');
        }

        return $next($request);
    }
}
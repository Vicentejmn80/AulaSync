<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            \App\Http\Middleware\PreventAuthPageCache::class,
        ]);

        $middleware->alias([
            'onboarding.completed' => \App\Http\Middleware\EnsureOnboardingCompleted::class,
            'role.director'        => \App\Http\Middleware\EnsureDirectorRole::class,
            'role.teacher'         => \App\Http\Middleware\EnsureTeacherRole::class,
            'role.representante'   => \App\Http\Middleware\EnsureRepresentanteRole::class,
            'role.super_admin'     => \App\Http\Middleware\EnsureSuperAdminRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

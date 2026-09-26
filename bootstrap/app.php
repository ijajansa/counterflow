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
        $middleware->alias([
            'tenant' => \App\Http\Middleware\ResolveTenant::class,
            'platform' => \App\Http\Middleware\PlatformAdmin::class,
        ]);
        $middleware->redirectUsersTo(fn () => route(auth()->user()?->is_platform_admin ? 'platform.dashboard' : 'business.dashboard'));
        $middleware->prependToPriorityList(\Illuminate\Routing\Middleware\SubstituteBindings::class, \App\Http\Middleware\ResolveTenant::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

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
    ->withCommands([
        \App\Console\Commands\InstallFidesPlatform::class,
        \App\Console\Commands\CreateFidesSuperAdmin::class,
        \App\Console\Commands\RegisterCurrentFidesCooperative::class,
        \App\Console\Commands\SeedDemoFidesCooperative::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(replace: [
            \Illuminate\Routing\Middleware\SubstituteBindings::class => \App\Http\Middleware\ResolveFidesTenant::class,
        ]);
        $middleware->alias([
            'password.changed' => \App\Http\Middleware\EnsurePasswordWasChanged::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'partner.auth'      => \App\Http\Middleware\PartnerAuthenticate::class,
            'partner.portal'    => \App\Http\Middleware\PartnerPortalAccess::class,
            'clinician.portal'  => \App\Http\Middleware\ClinicianPortalAccess::class,
            'role'              => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission'        => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'mfa'               => \App\Http\Middleware\MfaMiddleware::class,
            'force.reset'       => \App\Http\Middleware\ForcePasswordResetMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

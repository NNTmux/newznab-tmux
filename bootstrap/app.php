<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        \Laravel\Tinker\TinkerServiceProvider::class,
        \App\Providers\HorizonServiceProvider::class,
        \App\Providers\TelescopeServiceProvider::class,
        \Jrean\UserVerification\UserVerificationServiceProvider::class,
        \App\Providers\RouteServiceProvider::class,
        \App\Providers\UserServiceProvider::class,
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        // api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        // channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append([
            \Illuminate\Foundation\Http\Middleware\CheckForMaintenanceMode::class,
            \Monicahq\Cloudflare\Http\Middleware\TrustProxies::class,
            \App\Http\Middleware\ForceJsonOnAPI::class,
        ]);

        $middleware->web(\Illuminate\Session\Middleware\AuthenticateSession::class);

        $middleware->api([
            'throttle:60,1',
            'bindings',
        ]);

        $middleware->replace(\Illuminate\Foundation\Http\Middleware\TrimStrings::class, \App\Http\Middleware\TrimStrings::class);

        $middleware->replaceInGroup('web', \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class, \App\Http\Middleware\VerifyCsrfToken::class);

        $middleware->alias([
            '2fa' => \App\Http\Middleware\Google2FAMiddleware::class,
            'auth' => \App\Http\Middleware\Authenticate::class,
            'bindings' => \Illuminate\Routing\Middleware\SubstituteBindings::class,
            'clearance' => \App\Http\Middleware\ClearanceMiddleware::class,
            'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
            'isVerified' => \Jrean\UserVerification\Middleware\IsVerified::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

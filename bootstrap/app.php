<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        \Laravel\Tinker\TinkerServiceProvider::class,
        \Jrean\UserVerification\UserVerificationServiceProvider::class,
        \Creativeorange\Gravatar\GravatarServiceProvider::class,
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo('/');

        $middleware->validateCsrfTokens(except: [
            'failed',
            'admin/*',
            'btcpay/webhook',
            'logout',
            'register',
            'contact-us',
            'forgottenpassword',
            'login',
            'cart',
            'cart/*',
        ]);

        $middleware->append([
            \Illuminate\Foundation\Http\Middleware\CheckForMaintenanceMode::class,
            \App\Http\Middleware\ForceJsonOnAPI::class,
        ]);

        $middleware->replace(
            \Illuminate\Http\Middleware\TrustProxies::class,
            \Monicahq\Cloudflare\Http\Middleware\TrustProxies::class
        );

        $middleware->web([
            \Illuminate\Session\Middleware\AuthenticateSession::class,
            \App\Http\Middleware\TrustedDevice2FAMiddleware::class, // Add our new trusted device middleware
            \App\Http\Middleware\ContentSecurityPolicy::class, // Add CSP middleware for security
            \App\Http\Middleware\SetUserTimezone::class, // Set user timezone
        ]);

        $middleware->throttleApi('60,1');

        $middleware->alias([
            '2fa' => \App\Http\Middleware\Google2FAMiddleware::class,
            'bindings' => \Illuminate\Routing\Middleware\SubstituteBindings::class,
            'clearance' => \App\Http\Middleware\ClearanceMiddleware::class,
            'isVerified' => \Jrean\UserVerification\Middleware\IsVerified::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        \Sentry\Laravel\Integration::handles($exceptions);
    })->create();

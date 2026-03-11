<?php

use App\Http\Middleware\BlockAbusiveServices;
use App\Http\Middleware\ClearanceMiddleware;
use App\Http\Middleware\ContentSecurityPolicy;
use App\Http\Middleware\ForceJsonOnAPI;
use App\Http\Middleware\Google2FAMiddleware;
use App\Http\Middleware\NoCacheForAuthenticatedUsers;
use App\Http\Middleware\SetUserTimezone;
use App\Http\Middleware\TrustedDevice2FAMiddleware;
use Creativeorange\Gravatar\GravatarServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\CheckForMaintenanceMode;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Jrean\UserVerification\Middleware\IsVerified;
use Jrean\UserVerification\UserVerificationServiceProvider;
use Laravel\Tinker\TinkerServiceProvider;
use Sentry\Laravel\Integration;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        TinkerServiceProvider::class,
        UserVerificationServiceProvider::class,
        GravatarServiceProvider::class,
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
            CheckForMaintenanceMode::class,
            ForceJsonOnAPI::class,
            BlockAbusiveServices::class, // Block AIOStreams, Oracle Cloud, UsenetStreamer, Cloudflare WARP
        ]);

        $middleware->replace(
            TrustProxies::class,
            Monicahq\Cloudflare\Http\Middleware\TrustProxies::class
        );

        $middleware->web([
            AuthenticateSession::class,
            TrustedDevice2FAMiddleware::class, // Add our new trusted device middleware
            ContentSecurityPolicy::class, // Add CSP middleware for security
            SetUserTimezone::class, // Set user timezone
            NoCacheForAuthenticatedUsers::class, // Prevent Cloudflare/CDN caching of authenticated pages
        ]);

        $middleware->throttleApi('60,1');

        $middleware->alias([
            '2fa' => Google2FAMiddleware::class,
            'bindings' => SubstituteBindings::class,
            'clearance' => ClearanceMiddleware::class,
            'isVerified' => IsVerified::class,
            'permission' => PermissionMiddleware::class,
            'role' => RoleMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        Integration::handles($exceptions);
    })->create();

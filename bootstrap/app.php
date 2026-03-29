<?php

use App\Http\Middleware\BlockAbusiveServices;
use App\Http\Middleware\ClearanceMiddleware;
use App\Http\Middleware\ContentSecurityPolicy;
use App\Http\Middleware\EnsureAuthenticatedUsersAreVerified;
use App\Http\Middleware\ForceJsonOnAPI;
use App\Http\Middleware\Google2FAMiddleware;
use App\Http\Middleware\NoCacheForAuthenticatedUsers;
use App\Http\Middleware\SetUserTimezone;
use App\Http\Middleware\ThrottleApiRequestsByToken;
use App\Http\Middleware\TrustedDevice2FAMiddleware;
use App\Http\Middleware\TrustProxies as AppTrustProxies;
use Creativeorange\Gravatar\GravatarServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;
use Illuminate\Http\Middleware\TrustProxies as BaseTrustProxies;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Route;
use Laravel\Tinker\TinkerServiceProvider;
use Sentry\Laravel\Integration;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        TinkerServiceProvider::class,
        GravatarServiceProvider::class,
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: static function (): void {
            Route::middleware('api')
                ->prefix('rss')
                ->group(base_path('routes/rss.php'));
        },
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
            PreventRequestsDuringMaintenance::class,
            ForceJsonOnAPI::class,
            BlockAbusiveServices::class, // Block AIOStreams, Oracle Cloud, UsenetStreamer, Cloudflare WARP
        ]);

        $middleware->replace(
            BaseTrustProxies::class,
            AppTrustProxies::class
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
            'apiRateLimit' => ThrottleApiRequestsByToken::class,
            'isVerified' => EnsureAuthenticatedUsersAreVerified::class,
            'permission' => PermissionMiddleware::class,
            'role' => RoleMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        Integration::handles($exceptions);
    })->create();

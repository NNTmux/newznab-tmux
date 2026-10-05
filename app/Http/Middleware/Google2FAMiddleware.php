<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Google2FAAuthenticator;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Google2FAMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Google2FAAuthenticator $authenticator */
        $authenticator = app(Google2FAAuthenticator::class)->boot($request);

        if ($authenticator->isAuthenticated()) {
            return $next($request);
        }

        if ($request->routeIs('passkeys.*', 'password.confirm')) {
            $request->session()->put('url.intended', route('profileedit').'#security');
        } elseif ($request->isMethod('GET')) {
            $request->session()->put('url.intended', $request->fullUrl());
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Two-factor authentication required.',
                'redirect' => route('2fa.verify'),
            ], 403);
        }

        return $authenticator->makeRequestOneTimePasswordResponse();
    }
}

<?php

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
        // Direct check for trusted device cookie
        $trustedCookie = $request->cookie('2fa_trusted_device');

        if ($trustedCookie && auth()->check()) {
            try {
                $cookieData = json_decode($trustedCookie, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    // Check if all required fields exist
                    if (isset($cookieData['user_id'], $cookieData['token'], $cookieData['expires_at'])) {
                        // Check if cookie user ID matches authenticated user
                        if ((int) $cookieData['user_id'] === (int) auth()->id()) {
                            // Check if cookie is not expired
                            if (time() <= $cookieData['expires_at']) {
                                // Set the session variables for 2FA authentication
                                session([config('google2fa.session_var') => true]);
                                session([config('google2fa.session_var').'.auth.passed_at' => time()]);

                                // IMMEDIATELY allow the request to proceed - bypass all other checks
                                return $next($request);
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                // Silently handle any exceptions
            }
        }

        // Continue with normal 2FA flow if we reach this point
        /** @var Google2FAAuthenticator $authenticator */
        $authenticator = app(Google2FAAuthenticator::class)->boot($request);

        if ($authenticator->isAuthenticated()) {
            return $next($request);
        }

        return $authenticator->makeRequestOneTimePasswordResponse();
    }
}

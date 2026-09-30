<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\TrustedDevice;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TrustedDevice2FAMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $trustedCookie = $request->cookie('2fa_trusted_device');

        if ($trustedCookie && auth()->check()) {
            try {
                if (TrustedDevice::cookieIsValidForUser($trustedCookie, (int) auth()->id())) {
                    session([config('google2fa.session_var') => true]);
                    session([config('google2fa.session_var').'.auth.passed_at' => time()]);
                }
            } catch (\Exception $e) {
                Log::error('TrustedDevice2FAMiddleware - Error processing cookie', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $response = $next($request);

        if ($request->session()->has('2fa_trusted_device_pending')) {
            $cookieData = $request->session()->pull('2fa_trusted_device_pending');
            $cookieValue = json_encode($cookieData, JSON_UNESCAPED_SLASHES);

            try {
                if ($cookieValue !== false) {
                    $response->headers->setCookie(cookie(
                        '2fa_trusted_device',
                        $cookieValue,
                        60 * 24 * 30,
                        '/',
                        config('session.domain'),
                        config('session.secure'),
                        true,
                        false,
                        config('session.same_site', 'lax')
                    ));
                }
            } catch (\Exception $e) {
                Log::error('TrustedDevice2FAMiddleware - Error setting cookie', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $response;
    }
}

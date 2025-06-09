<?php

namespace App\Http\Middleware;

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
        // Check for trusted device cookie on incoming request
        $trustedCookie = $request->cookie('2fa_trusted_device');

        if ($trustedCookie && auth()->check()) {
            try {
                $cookieData = json_decode($trustedCookie, true);

                // If cookie data is valid and user matches
                if (json_last_error() === JSON_ERROR_NONE &&
                    isset($cookieData['user_id'], $cookieData['token'], $cookieData['expires_at']) &&
                    (int) $cookieData['user_id'] === (int) auth()->id() &&
                    time() <= $cookieData['expires_at']) {

                    // Mark this user's session as having passed 2FA
                    session([config('google2fa.session_var') => true]);
                    session([config('google2fa.session_var').'.auth.passed_at' => time()]);
                }
            } catch (\Exception $e) {
                Log::error('TrustedDevice2FAMiddleware - Error processing cookie', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Process the request
        $response = $next($request);

        // Check if we need to set a trusted device cookie
        if ($request->session()->has('2fa_trusted_device_pending')) {
            $cookieData = $request->session()->pull('2fa_trusted_device_pending');

            // Ensure cookie data is properly formatted
            $cookieValue = json_encode($cookieData, JSON_UNESCAPED_SLASHES);

            try {
                // Create a cookie instance with proper settings for persistence
                $cookie = cookie(
                    '2fa_trusted_device',      // name
                    $cookieValue,              // value
                    60 * 24 * 30,              // minutes (30 days)
                    '/',                       // path
                    null,                      // domain (null = current domain)
                    null,                      // secure (auto)
                    false,                     // httpOnly - allow JS access
                    false,                     // raw
                    'lax'                      // sameSite
                );

                // Add cookie to the response
                $response->headers->setCookie($cookie);

                // Backup approach - also set directly in PHP
                $expiry = time() + (60 * 60 * 24 * 30); // 30 days
                @setcookie('2fa_trusted_device', $cookieValue, [
                    'expires' => $expiry,
                    'path' => '/',
                    'domain' => '',
                    'secure' => $request->secure(),
                    'httponly' => false,
                    'samesite' => 'Lax',
                ]);

                // Keep in session for backup access
                $request->session()->put('2fa_trusted_device_value', $cookieValue);
            } catch (\Exception $e) {
                Log::error('TrustedDevice2FAMiddleware - Error setting cookie', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $response;
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to prevent caching of authenticated and auth-flow pages.
 *
 * This prevents issues where:
 * - CDNs (like Cloudflare) cache user-specific pages
 * - Flash messages on login/register flows are served from stale HTML
 * - Browsers reuse cached auth forms after redirects or CAPTCHA validation
 * - One user could see another user's data due to cache poisoning
 */
class NoCacheForAuthenticatedUsers
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldPreventCaching($request)) {
            $this->applyNoCacheHeaders($request, $response);
        }

        return $response;
    }

    /**
     * Keep session-backed auth flows out of CDN/browser caches.
     */
    private function shouldPreventCaching(Request $request): bool
    {
        return Auth::check() || $request->routeIs(
            'login',
            'login.post',
            'register',
            'register.post',
            'logout',
            'forgottenpassword',
            'password.request',
            '2fa.verify',
            '2fa.post',
            '2faVerify'
        );
    }

    private function applyNoCacheHeaders(Request $request, Response $response): void
    {
        // 'private' tells shared caches that this response must not be reused.
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, private, max-age=0, s-maxage=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT');

        // Cloudflare-specific headers to reinforce cache bypass at the edge.
        $response->headers->set('CDN-Cache-Control', 'no-store');
        $response->headers->set('Cloudflare-CDN-Cache-Control', 'no-store');

        $existingVary = $response->headers->get('Vary', '');
        $varyHeaders = array_filter(array_map('trim', explode(',', $existingVary)));

        if (! in_array('Cookie', $varyHeaders, true)) {
            $varyHeaders[] = 'Cookie';
        }

        if ($request->bearerToken() && ! in_array('Authorization', $varyHeaders, true)) {
            $varyHeaders[] = 'Authorization';
        }

        $response->headers->set('Vary', implode(', ', $varyHeaders));
    }
}

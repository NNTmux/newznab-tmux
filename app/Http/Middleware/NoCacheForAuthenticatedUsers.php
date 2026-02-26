<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to prevent caching of authenticated user pages.
 *
 * This prevents issues where:
 * - CDNs (like Cloudflare) cache user-specific pages
 * - Reverse proxies cache authenticated pages showing wrong user data
 * - Browsers cache authenticated pages
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

        // For authenticated users, add headers to prevent caching of personal data
        if (Auth::check()) {
            // Prevent any caching of authenticated user responses
            // 'private' tells CDNs like Cloudflare not to cache this response
            $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, private, max-age=0, s-maxage=0');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT');

            // Cloudflare-specific: Tell Cloudflare to bypass cache for this response
            // CDN-Cache-Control is respected by Cloudflare and other CDNs
            $response->headers->set('CDN-Cache-Control', 'no-store');

            // Cloudflare also respects this header
            $response->headers->set('Cloudflare-CDN-Cache-Control', 'no-store');

            // Add Vary header to ensure caches differentiate by user session
            $existingVary = $response->headers->get('Vary', '');
            $varyHeaders = array_filter(array_map('trim', explode(',', $existingVary)));

            // Add Cookie to Vary header if not already present
            if (! in_array('Cookie', $varyHeaders, true)) {
                $varyHeaders[] = 'Cookie';
            }
            // Add Authorization to Vary header for API requests
            if ($request->bearerToken() && ! in_array('Authorization', $varyHeaders, true)) {
                $varyHeaders[] = 'Authorization';
            }

            $response->headers->set('Vary', implode(', ', $varyHeaders));
        }

        return $response;
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * Outermost global middleware for HTTP QUERY (RFC 10008) response headers.
 *
 * Running outside every other layer means throttle 429s, 404/405s, maintenance
 * and Redis-degrade responses are covered too. QUERY responses carry per-user
 * quota data and token-bearing links, so they must never be stored by caches.
 * Responses produced before routing get the cache policy but no Accept-Query.
 */
final class DecorateHttpQueryResponses
{
    public const ACCEPT_QUERY = 'application/json';

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->isMethod('QUERY') && $request->is('api/*')) {
            $response->headers->set('Cache-Control', 'private, no-store');
        }

        $route = $request->route();
        if ($route instanceof Route && in_array('QUERY', $route->methods(), true)) {
            $response->headers->set('Accept-Query', self::ACCEPT_QUERY);
        }

        return $response;
    }
}

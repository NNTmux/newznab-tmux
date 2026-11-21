<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Security\IpBlockService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses every request from an address on the IP block list. Global and ahead of routing,
 * so a blocked address reaches neither the login page nor the session or database layers
 * behind it.
 */
class DenyBlockedIps
{
    public function __construct(private readonly IpBlockService $blocks) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();

        if ($ip === null || ! config('nntmux.ip_blocking.enabled') || ! $this->blocks->isBlocked($ip)) {
            return $next($request);
        }

        $response = $request->is('api/*', 'rss/*') || $request->expectsJson()
            ? response()->json(['error' => true, 'message' => 'Access denied: this IP address is blocked.'], 403)
            : response()->view('errors.ip-blocked', [], 403);

        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}

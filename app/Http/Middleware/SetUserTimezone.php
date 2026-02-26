<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetUserTimezone
{
    /**
     * Handle an incoming request.
     *
     * Note: We do NOT set the global timezone here because it would interfere
     * with database operations and helper functions. Instead, the timezone
     * conversion happens in the userDate() and userDateDiffForHumans() helpers.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Timezone conversion is handled by helper functions, not middleware
        // This middleware is kept for future enhancements if needed
        return $next($request);
    }
}

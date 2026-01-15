<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCacheControlHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldCacheResponse($request, $response)) {
            $response->headers->add([
                'Cache-Control' => 'max-age=86400, public',
            ]);
        }

        return $response;
    }

    public function shouldCacheResponse(Request $request, Response $response): bool
    {
        if (! $request->isMethod('GET')) {
            return false;
        }

        if (! $response->isSuccessful()) {
            return false;
        }

        if (app()->isLocal()) {
            return false;
        }

        return true;
    }
}

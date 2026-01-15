<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceCache
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldCacheResponse($request, $response)) {
            $response->headers->add([
                'Cache-Control' => 'max-age=86400, public',
                'ETag' => $this->generateEtag($request),
            ]);
            $response->headers->remove('Set-Cookie');
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

        if ($request->user()) {
            return false;
        }

        return true;
    }

    private function generateEtag(Request $request): string
    {
        $uri = $request->path();

        if ($request->getQueryString()) {
            $uri .= '?' . $request->getQueryString();
        }

        return md5($uri . ':' . time());
    }
}

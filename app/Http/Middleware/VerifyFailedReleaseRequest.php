<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyFailedReleaseRequest
{
    public function __construct(private readonly PreventRequestForgery $csrf) {}

    /**
     * Handle an incoming request.
     * API token requests are authenticated exclusively by the controller.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->has('api_token')) {
            return $next($request);
        }

        abort_unless($request->isMethod('post'), 405, 'Browser failure reports require POST.');

        return $this->csrf->handle($request, $next);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Auth\WebLoginSessionPolicy;
use Closure;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

final class RefreshRememberedLogin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard();
        $user = $request->user();

        if (! $guard instanceof SessionGuard || $user === null) {
            return $next($request);
        }

        if ($guard->viaRemember()) {
            $request->session()->put(WebLoginSessionPolicy::REMEMBERED_LOGIN_KEY, true);
        }

        $response = $next($request);

        $remembered = (bool) $request->session()->get(
            WebLoginSessionPolicy::REMEMBERED_LOGIN_KEY,
            false,
        );

        if (! $remembered || ! Auth::check()) {
            return $response;
        }

        $rememberToken = (string) $user->getRememberToken();
        if ($rememberToken === '') {
            return $response;
        }

        Cookie::queue(
            $guard->getRecallerName(),
            $user->getAuthIdentifier().'|'.$rememberToken.'|'.$guard->hashPasswordForCookie($user->getAuthPassword()),
            (int) config('auth.guards.web.remember', 10_080),
        );

        return $response;
    }
}

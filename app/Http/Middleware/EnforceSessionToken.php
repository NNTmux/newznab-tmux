<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Auth\WebLoginSessionPolicy;
use Closure;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class EnforceSessionToken
{
    public function __construct(private readonly WebLoginSessionPolicy $webLoginSessionPolicy) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession() || ! $request->user()) {
            return $next($request);
        }

        $user = $request->user();
        $userToken = (string) ($user->session_token ?? '');
        $sessionToken = (string) ($request->session()->get(WebLoginSessionPolicy::SESSION_TOKEN_KEY) ?? '');

        if ($userToken === '') {
            return $next($request);
        }

        if ($sessionToken === '') {
            $guard = Auth::guard();
            if ($this->webLoginSessionPolicy->isAdminExpiryToken($userToken)
                && (! $guard instanceof SessionGuard || ! $guard->viaRemember())) {
                return $this->logout($request, adminExpired: true);
            }

            $request->session()->put(WebLoginSessionPolicy::SESSION_TOKEN_KEY, $userToken);

            return $next($request);
        }

        if (! hash_equals($userToken, $sessionToken)) {
            $adminExpired = $this->webLoginSessionPolicy->isAdminExpiryToken($userToken);

            if (! $adminExpired && ! $this->webLoginSessionPolicy->singleActiveSessionEnabled()) {
                $request->session()->put(WebLoginSessionPolicy::SESSION_TOKEN_KEY, $userToken);

                return $next($request);
            }

            return $this->logout($request, $adminExpired);
        }

        return $next($request);
    }

    private function logout(Request $request, bool $adminExpired): Response
    {
        $guard = Auth::guard();
        if ($guard instanceof SessionGuard) {
            $guard->logoutCurrentDevice();
        } else {
            $guard->logout();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $message = $adminExpired
            ? 'You were signed out because an administrator has signed everyone out.'
            : 'You were signed out because this account signed in on another device.';

        if ($request->expectsJson()) {
            abort(401, $message);
        }

        return redirect()->route('login')->with('info', $message);
    }
}

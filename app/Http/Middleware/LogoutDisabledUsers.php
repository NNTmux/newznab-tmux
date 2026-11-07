<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends the web session of a user who was disabled after logging in.
 */
final class LogoutDisabledUsers
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->is_disabled) {
            return $next($request);
        }

        Auth::guard()->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        if ($request->expectsJson()) {
            abort(401, 'This account has been disabled.');
        }

        return redirect()->route('login')->with('error', 'This account has been disabled.');
    }
}

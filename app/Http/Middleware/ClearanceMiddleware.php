<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class ClearanceMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $user = Auth::user();

        if ($user->hasRole(['Admin']) || ($user->hasRole(['Moderator']) && (!$request->is('Admin') || !$request->is('Admin/*')))) {
            return $next($request);
        }

        if ($request->is('Movies') || $request->is('browse/Movies') || $request->is('browse/Movies/*')) {
            if (!$user->hasDirectPermission('view movies') || !$user->hasPermissionTo('view movies')) {
                abort(401);
            } else {
                return $next($request);
            }
        }

        if ($request->is('Console') || $request->is('browse/Console') || $request->is('browse/Console/*')) {
            if (!$user->hasDirectPermission('view console') || !$user->hasPermissionTo('view console')) {
                abort(401);
            } else {
                return $next($request);
            }
        }

        if ($request->is('Books') || $request->is('browse/Books') || $request->is('browse/Books/*')) {
            if (!$user->hasDirectPermission('view books') || !$user->hasPermissionTo('view books')) {
                abort(401);
            } else {
                return $next($request);
            }
        }

        if ($request->is('Audio') || $request->is('browse/Audio') || $request->is('browse/Audio/*')) {
            if (!$user->hasDirectPermission('view audio') || !$user->hasPermissionTo('view audio')) {
                abort(401);
            } else {
                return $next($request);
            }
        }

        if ($request->is('XXX') || $request->is('browse/XXX') || $request->is('browse/XXX/*')) {
            if (!$user->hasDirectPermission('view adult') || !$user->hasPermissionTo('view adult')) {
                abort(401);
            } else {
                return $next($request);
            }
        }

        if ($request->is('Games') || $request->is('browse/PC') || $request->is('browse/PC/*')) {
            if (!$user->hasDirectPermission('view pc') || !$user->hasPermissionTo('view pc')) {
                abort(401);
            } else {
                return $next($request);
            }
        }

        return $next($request);
    }
}

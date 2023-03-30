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
     * @return mixed
     *
     * @throws \Exception
     */
    public function handle($request, Closure $next)
    {
        $user = $request->user();

        if ($user->hasAnyRole(['Admin', 'Moderator']) && ! $request->is(['Admin', 'Admin/*'])) {
            return $next($request);
        }

        if ($request->is(['Movies', 'browse/Movies', 'browse/Movies/*'])) {
            if (! $user->hasDirectPermission('view movies') || ! $user->hasPermissionTo('view movies')) {
                abort(401);
            } else {
                return $next($request);
            }
        }

        if ($request->is(['Console', 'browse/Console', 'browse/Console/*'])) {
            if (! $user->hasDirectPermission('view console') || ! $user->hasPermissionTo('view console')) {
                abort(401);
            } else {
                return $next($request);
            }
        }

        if ($request->is(['Books', 'browse/Books', 'browse/Books/*'])) {
            if (! $user->hasDirectPermission('view books') || ! $user->hasPermissionTo('view books')) {
                abort(401);
            } else {
                return $next($request);
            }
        }

        if ($request->is(['Audio', 'browse/Audio', 'browse/Audio/*'])) {
            if (! $user->hasDirectPermission('view audio') || ! $user->hasPermissionTo('view audio')) {
                abort(401);
            } else {
                return $next($request);
            }
        }

        if ($request->is(['XXX', 'browse/XXX', 'browse/XXX/*'])) {
            if (! $user->hasDirectPermission('view adult') || ! $user->hasPermissionTo('view adult')) {
                abort(401);
            } else {
                return $next($request);
            }
        }

        if ($request->is(['Games', 'browse/PC', 'browse/PC/*'])) {
            if (! $user->hasDirectPermission('view pc') || ! $user->hasPermissionTo('view pc')) {
                abort(401);
            } else {
                return $next($request);
            }
        }

        if ($request->is(['TV', 'browse/TV', 'browse/TV/*'])) {
            if (! $user->hasDirectPermission('view tv') || ! $user->hasPermissionTo('view tv')) {
                abort(401);
            } else {
                return $next($request);
            }
        }

        return $next($request);
    }
}

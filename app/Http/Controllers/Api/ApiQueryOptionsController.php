<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Middleware\DecorateHttpQueryResponses;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;

/**
 * Answers OPTIONS for QUERY-enabled API endpoints with Allow and Accept-Query.
 *
 * Laravel's implicit OPTIONS response skips route middleware and sends no
 * Accept-Query, so these endpoints register this explicit handler instead.
 * CORS preflights never reach it: HandleCors answers them before routing.
 */
class ApiQueryOptionsController
{
    public function __invoke(Request $request, Router $router): Response
    {
        $uri = $request->route()?->uri();

        $methods = collect($router->getRoutes()->getRoutes())
            ->filter(static fn (Route $route): bool => $route->uri() === $uri)
            ->flatMap(static fn (Route $route): array => $route->methods())
            ->unique()
            ->values()
            ->all();

        return response()->noContent()->withHeaders([
            'Allow' => implode(', ', $methods),
            'Accept-Query' => DecorateHttpQueryResponses::ACCEPT_QUERY,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class MeasureApiPerformance
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('api/v1/*', 'api/v2/*') || ! $this->sampled()) {
            return $next($request);
        }

        $connection = DB::connection();
        $connection->flushQueryLog();
        $connection->enableQueryLog();
        $startedAt = hrtime(true);

        try {
            $response = $next($request);
        } finally {
            $queries = $connection->getQueryLog();
            $connection->disableQueryLog();
        }

        $durationMs = (hrtime(true) - $startedAt) / 1_000_000;
        $queryMs = array_sum(array_map(static fn (array $query): float => (float) ($query['time'] ?? 0), $queries));

        $response->headers->set('Server-Timing', sprintf(
            'app;dur=%.2f, db;dur=%.2f;desc="%d queries", cache;desc="%s"',
            $durationMs,
            $queryMs,
            count($queries),
            (string) $request->attributes->get('nntmux.api_release_cache', 'unused'),
        ));

        return $response;
    }

    private function sampled(): bool
    {
        $rate = min(1.0, max(0.0, (float) config('nntmux.api.metrics_sample_rate', 0.01)));

        return $rate > 0.0 && random_int(1, 1_000_000) <= (int) ($rate * 1_000_000);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

/**
 * Laravel's cache "failover" driver still tries Redis on every read/write, so when Redis is down
 * each Cache call pays the full connection timeout (very slow + spinners on admin/API-heavy pages).
 *
 * This middleware runs first (global stack): quick TCP reachability check to the configured Redis
 * host/port, then switches default cache + session to file for this request only. terminate()
 * restores config for Octane/long-lived workers.
 */
class DegradeWhenRedisUnreachable
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('nntmux.redis_fast_degrade', true)) {
            return $next($request);
        }

        if ($this->runningUnitTests()) {
            return $next($request);
        }

        if (! $this->shouldProbeRedis()) {
            return $next($request);
        }

        $target = $this->redisTcpTarget();
        if ($target === null) {
            return $next($request);
        }

        if ($this->tcpReachable($target['remote'], (float) config('nntmux.redis_tcp_check_seconds', 0.2))) {
            return $next($request);
        }

        $request->attributes->set('redis_degrade_original_config', [
            'cache.default' => config('cache.default'),
            'session.driver' => config('session.driver'),
        ]);

        $this->degradeAwayFromRedis();

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $snapshot = $request->attributes->get('redis_degrade_original_config');
        if (! is_array($snapshot)) {
            return;
        }

        foreach ($snapshot as $key => $value) {
            Config::set($key, $value);
        }
    }

    private function runningUnitTests(): bool
    {
        return app()->environment('testing');
    }

    private function shouldProbeRedis(): bool
    {
        $cacheDefault = (string) config('cache.default', '');
        if ($cacheDefault === 'redis' || $cacheDefault === 'failover_redis_file' || $this->failoverChainContainsRedis($cacheDefault)) {
            return true;
        }

        return config('session.driver') === 'redis';
    }

    private function failoverChainContainsRedis(string $storeName): bool
    {
        $store = config('cache.stores.'.$storeName);
        if (! is_array($store) || ($store['driver'] ?? '') !== 'failover') {
            return false;
        }

        foreach ($store['stores'] ?? [] as $sub) {
            if (strtolower((string) $sub) === 'redis') {
                return true;
            }
        }

        return false;
    }

    private function degradeAwayFromRedis(): void
    {
        $cacheDefault = (string) config('cache.default', '');
        if ($cacheDefault === 'redis'
            || $cacheDefault === 'failover_redis_file'
            || $this->failoverChainContainsRedis($cacheDefault)) {
            Config::set('cache.default', 'file');
        }

        if (config('session.driver') === 'redis') {
            Config::set('session.driver', 'file');
        }
    }

    /**
     * @return array{remote: string}|null PHP stream remote URL (tcp:// or ssl://)
     */
    private function redisTcpTarget(): ?array
    {
        $cfg = config('database.redis.default');
        if (! is_array($cfg)) {
            return null;
        }

        if (! empty($cfg['url'])) {
            $parts = parse_url((string) $cfg['url']);
            if ($parts === false) {
                return null;
            }
            $host = $parts['host'] ?? null;
            if ($host === null || $host === '') {
                return null;
            }
            $port = (int) ($parts['port'] ?? 6379);
            $scheme = strtolower((string) ($parts['scheme'] ?? 'tcp'));

            return ['remote' => $this->streamRemote($scheme, $host, $port)];
        }

        $host = (string) ($cfg['host'] ?? '127.0.0.1');
        if ($host === '' || str_starts_with($host, '/')) {
            return null;
        }

        $port = (int) ($cfg['port'] ?? 6379);

        return ['remote' => $this->streamRemote('tcp', $host, $port)];
    }

    private function streamRemote(string $scheme, string $host, int $port): string
    {
        if ($scheme === 'tls' || $scheme === 'ssl' || $scheme === 'rediss') {
            return 'ssl://'.$host.':'.$port;
        }

        $wrapped = str_contains($host, ':') && ! str_starts_with($host, '[')
            ? '['.$host.']'
            : $host;

        return 'tcp://'.$wrapped.':'.$port;
    }

    private function tcpReachable(string $remote, float $timeoutSeconds): bool
    {
        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client(
            $remote,
            $errno,
            $errstr,
            $timeoutSeconds,
            STREAM_CLIENT_CONNECT
        );

        if (is_resource($socket)) {
            fclose($socket);

            return true;
        }

        return false;
    }
}

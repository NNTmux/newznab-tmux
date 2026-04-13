<?php

declare(strict_types=1);

namespace App\Services\StatusProbes;

use App\Enums\IncidentImpactEnum;
use App\Services\StatusProbes\Contracts\ServiceProbeInterface;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class RedisProbe implements ServiceProbeInterface
{
    public function identifier(): string
    {
        return 'redis';
    }

    public function probe(): ProbeResult
    {
        if (! config('status-probes.redis.probe', true)) {
            return new ProbeResult(
                ok: true,
                responseTimeMs: 0,
                impact: null,
                reason: 'Redis probe disabled (STATUS_PROBE_REDIS=false)',
            );
        }

        if (config('status-probes.redis.only_when_used', true) && ! $this->applicationUsesRedis()) {
            return new ProbeResult(
                ok: true,
                responseTimeMs: 0,
                impact: null,
                reason: 'Skipping: cache/session/queue do not use Redis',
            );
        }

        try {
            $start = hrtime(true);
            $pong = Redis::connection()->ping();
            $elapsed = (int) ((hrtime(true) - $start) / 1_000_000);
            $isOk = $pong === true || $pong === '+PONG' || strtoupper((string) $pong) === 'PONG';

            if (! $isOk) {
                return new ProbeResult(
                    ok: false,
                    responseTimeMs: $elapsed,
                    impact: IncidentImpactEnum::Major,
                    reason: 'Redis ping returned unexpected response',
                    metadata: ['response' => $pong],
                );
            }

            return new ProbeResult(
                ok: true,
                responseTimeMs: $elapsed,
                impact: null,
                reason: 'Connected',
            );
        } catch (\Throwable $e) {
            return new ProbeResult(
                ok: false,
                responseTimeMs: 0,
                impact: IncidentImpactEnum::Critical,
                reason: 'Redis unreachable: '.Str::limit($e->getMessage(), 120),
            );
        }
    }

    private function applicationUsesRedis(): bool
    {
        $cacheDefault = strtolower((string) config('cache.default', ''));
        $sessionDriver = strtolower((string) config('session.driver', ''));
        $queueDefault = strtolower((string) config('queue.default', ''));

        if ($cacheDefault === 'redis' || $sessionDriver === 'redis' || $queueDefault === 'redis') {
            return true;
        }

        if ($this->cacheFailoverUsesRedis()) {
            return true;
        }

        $pulseIngest = strtolower((string) config('pulse.ingest.driver', ''));

        return $pulseIngest === 'redis';
    }

    /**
     * True when the default cache store is a failover chain that includes the redis store.
     */
    private function cacheFailoverUsesRedis(): bool
    {
        $name = (string) config('cache.default', '');
        $store = config('cache.stores.'.$name);

        if (! is_array($store) || ($store['driver'] ?? '') !== 'failover') {
            return false;
        }

        foreach ($store['stores'] ?? [] as $subStore) {
            if (strtolower((string) $subStore) === 'redis') {
                return true;
            }
        }

        return false;
    }
}

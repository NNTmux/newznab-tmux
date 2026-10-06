<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Hands the tmux monitor's latest statistics to the metrics exporter.
 *
 * The monitor loop is the only place these (expensive) counts are computed,
 * so it publishes a trimmed copy to the cache and the scheduled exporter
 * reads it back instead of re-running the queries.
 *
 * @phpstan-type Snapshot array{
 *     collected_at: int,
 *     is_running: int,
 *     counts: array<string, int|float>,
 *     connections: array<string, array{active: int, total: int}>,
 *     query_timers: array<string, float>
 * }
 */
class TmuxMetricsSnapshot
{
    public function __construct(private readonly CacheRepository $cache) {}

    /**
     * @param  array<string, mixed>  $runVar  The array returned by TmuxMonitorService::collectStatistics().
     */
    public function publish(array $runVar, ?int $collectedAt = null): void
    {
        $this->cache->put(
            (string) config('monitoring.tmux_snapshot.key'),
            [
                'collected_at' => $collectedAt ?? time(),
                'is_running' => (int) ($runVar['settings']['is_running'] ?? 0),
                'counts' => $this->numericValues($runVar['counts']['now'] ?? []),
                'connections' => $this->connections($runVar['conncounts'] ?? []),
                'query_timers' => array_map('floatval', $this->numericValues($runVar['timers']['query'] ?? [])),
            ],
            (int) config('monitoring.tmux_snapshot.ttl', 900),
        );
    }

    /**
     * @return Snapshot|null
     */
    public function read(): ?array
    {
        $snapshot = $this->cache->get((string) config('monitoring.tmux_snapshot.key'));

        if (! is_array($snapshot) || ! isset($snapshot['collected_at'], $snapshot['counts'])) {
            return null;
        }

        /** @var Snapshot $snapshot */
        return $snapshot;
    }

    /**
     * @return array<string, int|float>
     */
    private function numericValues(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $numeric = [];

        foreach ($values as $key => $value) {
            if (is_string($key) && (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value)))) {
                $numeric[$key] = $value + 0;
            }
        }

        return $numeric;
    }

    /**
     * @return array<string, array{active: int, total: int}>
     */
    private function connections(mixed $connections): array
    {
        if (! is_array($connections)) {
            return [];
        }

        $result = [];

        foreach ($connections as $server => $counts) {
            if (is_string($server) && is_array($counts)) {
                $result[$server] = [
                    'active' => (int) ($counts['active'] ?? 0),
                    'total' => (int) ($counts['total'] ?? 0),
                ];
            }
        }

        return $result;
    }
}

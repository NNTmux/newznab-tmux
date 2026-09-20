<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\StatusProbes\DatabaseProbe;
use App\Services\StatusProbes\RedisProbe;
use App\Services\StatusProbes\SearchProbe;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Sleep;
use RuntimeException;

class BackgroundWorkPressureGate
{
    /**
     * @param  array<string, bool|float|int|string>  $overrides
     */
    public function __construct(
        private readonly SystemMetricsService $systemMetrics,
        private readonly DatabaseProbe $databaseProbe,
        private readonly SearchProbe $searchProbe,
        private readonly RedisProbe $redisProbe,
        private readonly array $overrides = [],
    ) {}

    /**
     * Wait until foreground dependencies and host resources have recovered.
     *
     * @param  (callable(string): void)|null  $onPause
     */
    public function awaitPermission(?callable $onPause = null): void
    {
        if (! config('background-work.backpressure.enabled', true)) {
            return;
        }

        $lastReason = null;

        while (true) {
            $decision = $this->check();
            if ($decision['allowed']) {
                return;
            }

            if ($onPause !== null && $decision['reason'] !== $lastReason) {
                $onPause($decision['reason']);
                $lastReason = $decision['reason'];
            }

            Sleep::sleep(max(1, (int) config('background-work.backpressure.poll_seconds', 15)));
        }
    }

    /**
     * @return array{allowed: bool, reason: string, healthy_samples: int}
     */
    public function check(): array
    {
        if (! config('background-work.backpressure.enabled', true)) {
            return ['allowed' => true, 'reason' => '', 'healthy_samples' => 0];
        }

        return $this->transition($this->snapshot());
    }

    /**
     * Pure hysteresis transition used by the runtime gate and regression tests.
     *
     * @param  array{load_ratio: float, available_memory_percent: float, dependencies: array<string, array{ok: bool, latency_ms: int}>, outbox_age_seconds: int}  $snapshot
     * @param  array{paused?: bool, healthy_samples?: int, reason?: string}  $state
     * @return array{paused: bool, healthy_samples: int, reason: string}
     */
    public function nextState(array $snapshot, array $state): array
    {
        $pauseReason = $this->pauseReason($snapshot);
        $wasPaused = (bool) ($state['paused'] ?? false);

        if (! $wasPaused) {
            return $pauseReason === null
                ? ['paused' => false, 'healthy_samples' => 0, 'reason' => '']
                : ['paused' => true, 'healthy_samples' => 0, 'reason' => $pauseReason];
        }

        if (! $this->isHealthyForResume($snapshot)) {
            return [
                'paused' => true,
                'healthy_samples' => 0,
                'reason' => $pauseReason ?? (string) ($state['reason'] ?? 'Waiting for foreground capacity'),
            ];
        }

        $healthySamples = (int) ($state['healthy_samples'] ?? 0) + 1;
        if ($healthySamples < max(1, (int) $this->setting('healthy_samples', 2))) {
            return [
                'paused' => true,
                'healthy_samples' => $healthySamples,
                'reason' => 'Foreground capacity is recovering',
            ];
        }

        return ['paused' => false, 'healthy_samples' => 0, 'reason' => ''];
    }

    /**
     * @return array{load_ratio: float, available_memory_percent: float, dependencies: array<string, array{ok: bool, latency_ms: int}>, outbox_age_seconds: int}
     */
    private function snapshot(): array
    {
        $ram = $this->systemMetrics->getRamUsage();
        $memoryTotal = (float) ($ram['total'] ?? 0);
        $availableMemory = $memoryTotal > 0
            ? max(0.0, 100.0 - (float) ($ram['percentage'] ?? 100))
            : 0.0;

        $dependencies = [];
        foreach ([$this->databaseProbe, $this->searchProbe, $this->redisProbe] as $probe) {
            $result = $probe->probe();
            $dependencies[$probe->identifier()] = [
                'ok' => $result->ok,
                'latency_ms' => $result->responseTimeMs,
            ];
        }

        return [
            'load_ratio' => max(0.0, $this->systemMetrics->getCpuUsage() / 100),
            'available_memory_percent' => $availableMemory,
            'dependencies' => $dependencies,
            'outbox_age_seconds' => $this->oldestOutboxAgeSeconds(),
        ];
    }

    /**
     * @param  array{load_ratio: float, available_memory_percent: float, dependencies: array<string, array{ok: bool, latency_ms: int}>, outbox_age_seconds: int}  $snapshot
     */
    private function pauseReason(array $snapshot): ?string
    {
        if ($snapshot['load_ratio'] >= (float) $this->setting('pause_load_ratio', 0.75)) {
            return sprintf('Normalized load %.2f reached the pause threshold', $snapshot['load_ratio']);
        }

        if ($snapshot['available_memory_percent'] <= (float) $this->setting('pause_available_memory_percent', 15)) {
            return sprintf('Available memory %.1f%% reached the pause threshold', $snapshot['available_memory_percent']);
        }

        $latencyThreshold = (int) $this->setting('pause_dependency_latency_ms', 100);
        foreach ($snapshot['dependencies'] as $name => $dependency) {
            if (! $dependency['ok']) {
                return ucfirst($name).' dependency is unavailable';
            }

            if ($dependency['latency_ms'] >= $latencyThreshold) {
                return sprintf('%s dependency latency reached %d ms', ucfirst($name), $dependency['latency_ms']);
            }
        }

        if ($snapshot['outbox_age_seconds'] > (int) $this->setting('pause_outbox_age_seconds', 5)) {
            return sprintf('Search index outbox is %d seconds behind', $snapshot['outbox_age_seconds']);
        }

        return null;
    }

    /**
     * @param  array{load_ratio: float, available_memory_percent: float, dependencies: array<string, array{ok: bool, latency_ms: int}>, outbox_age_seconds: int}  $snapshot
     */
    private function isHealthyForResume(array $snapshot): bool
    {
        if ($snapshot['load_ratio'] > (float) $this->setting('resume_load_ratio', 0.60)
            || $snapshot['available_memory_percent'] < (float) $this->setting('resume_available_memory_percent', 20)
            || $snapshot['outbox_age_seconds'] >= (int) $this->setting('resume_outbox_age_seconds', 2)) {
            return false;
        }

        $latencyThreshold = (int) $this->setting('resume_dependency_latency_ms', 50);
        foreach ($snapshot['dependencies'] as $dependency) {
            if (! $dependency['ok'] || $dependency['latency_ms'] >= $latencyThreshold) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array{load_ratio: float, available_memory_percent: float, dependencies: array<string, array{ok: bool, latency_ms: int}>, outbox_age_seconds: int}  $snapshot
     * @return array{allowed: bool, reason: string, healthy_samples: int}
     */
    private function transition(array $snapshot): array
    {
        $path = (string) config('background-work.backpressure.state_path', storage_path('tmux/background-work-pressure.json'));
        File::ensureDirectoryExists(dirname($path));

        $handle = fopen($path, 'c+');
        if ($handle === false) {
            throw new RuntimeException('Unable to open background-work pressure state file.');
        }

        try {
            if (! flock($handle, LOCK_EX)) {
                throw new RuntimeException('Unable to lock background-work pressure state file.');
            }

            rewind($handle);
            $contents = stream_get_contents($handle);
            $decoded = is_string($contents) && $contents !== '' ? json_decode($contents, true) : [];
            $state = is_array($decoded) ? $decoded : [];
            $next = $this->nextState($snapshot, $state);
            $next['updated_at'] = now()->toIso8601String();

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, (string) json_encode($next, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            fflush($handle);
            flock($handle, LOCK_UN);

            return [
                'allowed' => ! $next['paused'],
                'reason' => $next['reason'],
                'healthy_samples' => $next['healthy_samples'],
            ];
        } finally {
            fclose($handle);
        }
    }

    private function oldestOutboxAgeSeconds(): int
    {
        if (! Schema::hasTable('search_index_outbox')) {
            return 0;
        }

        $createdAt = DB::table('search_index_outbox')->min('created_at');
        if ($createdAt === null) {
            return 0;
        }

        return (int) max(0, CarbonImmutable::parse((string) $createdAt)->diffInSeconds(now()));
    }

    private function setting(string $key, mixed $default): mixed
    {
        return $this->overrides[$key] ?? config('background-work.backpressure.'.$key, $default);
    }
}

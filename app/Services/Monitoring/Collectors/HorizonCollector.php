<?php

declare(strict_types=1);

namespace App\Services\Monitoring\Collectors;

use App\Services\Monitoring\Prometheus\MetricFamily;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\MetricsRepository;
use Laravel\Horizon\Contracts\WorkloadRepository;

/**
 * Queue depth, wait time and throughput from Horizon's Redis repositories.
 */
class HorizonCollector implements MetricsCollector
{
    public function __construct(
        private readonly WorkloadRepository $workload,
        private readonly MasterSupervisorRepository $masters,
        private readonly JobRepository $jobs,
        private readonly MetricsRepository $metrics,
    ) {}

    public function collect(): iterable
    {
        // Horizon only tracks Redis queues; with the sync/database drivers there is nothing to read.
        if (config('queue.default') !== 'redis') {
            return;
        }

        $length = MetricFamily::gauge('nntmux_queue_jobs', 'Jobs waiting in each Horizon queue.');
        $wait = MetricFamily::gauge('nntmux_queue_wait_seconds', 'Estimated seconds to clear each Horizon queue.');
        $processes = MetricFamily::gauge('nntmux_queue_processes', 'Horizon worker processes per queue.');

        /** @var array<int, array{name: string, length: int, wait: int|float, processes: int}> $queues */
        $queues = $this->workload->get();

        foreach ($queues as $queue) {
            $labels = ['queue' => $queue['name']];
            $length->add((int) $queue['length'], $labels);
            $wait->add((float) $queue['wait'], $labels);
            $processes->add((int) $queue['processes'], $labels);
        }

        yield $length;
        yield $wait;
        yield $processes;

        $running = collect($this->masters->all())
            ->contains(static fn (object $master): bool => ($master->status ?? null) === 'running');

        yield MetricFamily::gauge('nntmux_horizon_up', 'Whether a Horizon master supervisor is running.')->add($running ? 1 : 0);

        yield MetricFamily::gauge('nntmux_horizon_recently_failed_jobs', 'Jobs Horizon recorded as failed in its retention window.')
            ->add((int) $this->jobs->countRecentlyFailed());

        yield MetricFamily::gauge('nntmux_horizon_jobs_per_minute', 'Jobs processed per minute, as measured by Horizon.')
            ->add((float) $this->metrics->jobsProcessedPerMinute());
    }
}

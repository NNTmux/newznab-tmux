<?php

declare(strict_types=1);

namespace App\Services\Monitoring\Collectors;

use App\Services\Monitoring\Prometheus\MetricFamily;
use Illuminate\Queue\Failed\CountableFailedJobProvider;
use Illuminate\Queue\Failed\FailedJobProviderInterface;

class FailedJobsCollector implements MetricsCollector
{
    public function __construct(private readonly FailedJobProviderInterface $failer) {}

    public function collect(): iterable
    {
        if (! $this->failer instanceof CountableFailedJobProvider) {
            return;
        }

        yield MetricFamily::gauge('nntmux_failed_jobs', 'Jobs in the failed jobs store.')->add($this->failer->count());
    }
}

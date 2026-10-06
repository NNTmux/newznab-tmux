<?php

declare(strict_types=1);

namespace Tests\Support\Monitoring;

use App\Services\Monitoring\Collectors\MetricsCollector;
use App\Services\Monitoring\Prometheus\MetricFamily;
use RuntimeException;

/**
 * Yields one family and then throws, to prove partial output is discarded.
 */
class FailingMetricsCollector implements MetricsCollector
{
    public function collect(): iterable
    {
        yield MetricFamily::gauge('nntmux_partial', 'Emitted before the failure.')->add(1);

        throw new RuntimeException('collector exploded');
    }
}

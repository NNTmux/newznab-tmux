<?php

declare(strict_types=1);

namespace App\Services\Monitoring\Collectors;

use App\Services\Monitoring\Prometheus\MetricFamily;

interface MetricsCollector
{
    /**
     * Container tag the exporter resolves collectors from.
     */
    public const string TAG = 'monitoring.collectors';

    /**
     * @return iterable<MetricFamily>
     */
    public function collect(): iterable;
}

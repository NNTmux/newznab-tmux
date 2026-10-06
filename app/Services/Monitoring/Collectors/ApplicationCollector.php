<?php

declare(strict_types=1);

namespace App\Services\Monitoring\Collectors;

use App\Services\Monitoring\Prometheus\MetricFamily;

class ApplicationCollector implements MetricsCollector
{
    public function collect(): iterable
    {
        yield MetricFamily::gauge('nntmux_up', 'Whether the NNTmux metrics exporter ran.')->add(1);

        yield MetricFamily::gauge('nntmux_build_info', 'NNTmux runtime versions.')->add(1, [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
        ]);
    }
}

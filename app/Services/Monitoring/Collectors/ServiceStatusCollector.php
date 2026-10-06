<?php

declare(strict_types=1);

namespace App\Services\Monitoring\Collectors;

use App\Enums\ServiceStatusEnum;
use App\Models\ServiceStatus;
use App\Services\Monitoring\Prometheus\MetricFamily;

/**
 * Mirrors the /status page. Reads the stored results of nntmux:check-service-health
 * rather than probing live, so a slow NNTP probe can never stall the exporter.
 */
class ServiceStatusCollector implements MetricsCollector
{
    public function collect(): iterable
    {
        $up = MetricFamily::gauge('nntmux_service_up', 'Whether a monitored service is operational or degraded (1) or down (0).');
        $severity = MetricFamily::gauge('nntmux_service_severity', 'Service status severity: 0 operational, 1 degraded, 2 maintenance, 3 partial outage, 4 major outage.');
        $responseTime = MetricFamily::gauge('nntmux_service_response_time_seconds', 'Response time of the last service health check.');

        $services = ServiceStatus::query()->where('is_enabled', true)->orderBy('sort_order')->get();

        foreach ($services as $service) {
            $labels = ['service' => $service->slug];
            $up->add($service->status->severity() <= ServiceStatusEnum::Degraded->severity() ? 1 : 0, $labels);
            $severity->add($service->status->severity(), $labels);

            if ($service->response_time_ms !== null) {
                $responseTime->add($service->response_time_ms / 1000, $labels);
            }
        }

        yield $up;
        yield $severity;
        yield $responseTime;
    }
}

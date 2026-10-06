<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\Services\Monitoring\Collectors\MetricsCollector;
use App\Services\Monitoring\Prometheus\MetricFamily;
use App\Services\Monitoring\Prometheus\TextFormatter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Collects NNTmux application metrics and pushes them to the Prometheus Pushgateway.
 */
class PrometheusMetricsExporter
{
    /**
     * @param  iterable<MetricsCollector>  $collectors
     */
    public function __construct(
        private readonly iterable $collectors,
        private readonly TextFormatter $formatter,
    ) {}

    /**
     * Run every collector and render the result. A failing collector is
     * reported through nntmux_metrics_collector_errors instead of aborting.
     */
    public function render(): string
    {
        $startedAt = hrtime(true);
        $families = [];
        $errors = MetricFamily::gauge('nntmux_metrics_collector_errors', 'Whether a metrics collector failed during the last export.');

        foreach ($this->collectors as $collector) {
            $name = class_basename($collector);

            try {
                // Materialize first so a collector that throws midway contributes nothing partial.
                $collected = [...$collector->collect()];
                array_push($families, ...$collected);

                $errors->add(0, ['collector' => $name]);
            } catch (Throwable $exception) {
                $errors->add(1, ['collector' => $name]);
                Log::warning('Monitoring collector failed', ['collector' => $name, 'exception' => $exception->getMessage()]);
            }
        }

        $families[] = $errors;
        $families[] = MetricFamily::gauge('nntmux_metrics_export_duration_seconds', 'Time spent collecting NNTmux metrics.')
            ->add((hrtime(true) - $startedAt) / 1e9);

        return $this->formatter->format($families);
    }

    /**
     * Replace the NNTmux metric group on the Pushgateway. PUT (not POST) drops
     * series that are no longer reported, e.g. a queue that was removed.
     *
     * @return bool Whether the Pushgateway accepted the metrics.
     */
    public function push(): bool
    {
        $url = config('monitoring.pushgateway.url').'/metrics/job/'.rawurlencode((string) config('monitoring.pushgateway.job'));

        try {
            $response = Http::timeout((int) config('monitoring.pushgateway.timeout', 5))
                ->withBody($this->render(), TextFormatter::CONTENT_TYPE)
                ->put($url);
        } catch (ConnectionException $exception) {
            Log::warning('Monitoring metrics push failed', ['url' => $url, 'exception' => $exception->getMessage()]);

            return false;
        }

        if ($response->failed()) {
            Log::warning('Monitoring metrics push rejected', ['url' => $url, 'status' => $response->status(), 'body' => mb_substr($response->body(), 0, 500)]);

            return false;
        }

        return true;
    }
}

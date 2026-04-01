<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SiteStatusService;
use Illuminate\Console\Command;

class CheckServiceHealth extends Command
{
    protected $signature = 'nntmux:check-service-health
                            {--days=30 : Rolling window in days for uptime calculation}';

    protected $description = 'Ping service endpoints, auto-create/resolve incidents, and recalculate uptime';

    public function handle(SiteStatusService $statusService): int
    {
        $days = (int) $this->option('days');
        $appUrl = rtrim((string) config('app.url'), '/');

        if ($appUrl === '' || ! str_starts_with($appUrl, 'http')) {
            $this->components->warn('APP_URL is not set — health checks will be skipped. Set APP_URL in .env to enable endpoint monitoring.');
        }

        $services = $statusService->getEnabledServices();

        $rows = [];

        foreach ($services as $service) {
            if ($service->endpoint_url === null || $service->endpoint_url === '') {
                $rows[] = [$service->name, '—', '—', 'Skipped (no endpoint)'];

                continue;
            }

            $result = $statusService->checkServiceHealth($service);
            $statusService->handleHealthCheckResult($service, $result);

            $rows[] = [
                $service->name,
                $result['status_code'] ?: '—',
                $result['response_time_ms'] ? $result['response_time_ms'].'ms' : '—',
                $result['ok'] ? 'Healthy' : $result['reason'],
            ];
        }

        $statusService->refreshAllServiceUptime($days);

        $this->table(['Service', 'HTTP', 'Response', 'Result'], $rows);

        $services = $statusService->getEnabledServices();
        $summary = $services->map(fn ($s) => [
            $s->name,
            $s->status->label(),
            number_format((float) $s->uptime_percentage, 2).'%',
            $s->last_checked_at?->diffForHumans() ?? 'never',
        ])->all();

        $this->newLine();
        $this->table(['Service', 'Status', 'Uptime', 'Checked'], $summary);

        return self::SUCCESS;
    }
}

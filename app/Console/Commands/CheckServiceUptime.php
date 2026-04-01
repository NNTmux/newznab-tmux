<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SiteStatusService;
use Illuminate\Console\Command;

class CheckServiceUptime extends Command
{
    protected $signature = 'nntmux:check-service-uptime
                            {--days=30 : Rolling window in days for uptime calculation}';

    protected $description = 'Recalculate uptime percentages for all enabled services based on incident history';

    public function handle(SiteStatusService $statusService): int
    {
        $days = (int) $this->option('days');

        $this->components->info("Recalculating service uptime over a {$days}-day window…");

        $statusService->refreshAllServiceUptime($days);

        $services = $statusService->getEnabledServices();
        $rows = $services->map(fn ($s) => [
            $s->name,
            $s->status->label(),
            number_format((float) $s->uptime_percentage, 2).'%',
            $s->last_checked_at?->diffForHumans() ?? 'never',
        ])->all();

        $this->table(['Service', 'Status', 'Uptime', 'Checked'], $rows);

        return self::SUCCESS;
    }
}

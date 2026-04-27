<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\AdminDashboardSnapshotService;
use Illuminate\Console\Command;

class WarmAdminDashboard extends Command
{
    protected $signature = 'admin:warm-dashboard';

    protected $description = 'Rebuild and re-cache the admin dashboard snapshot (Cache::flexible payload)';

    public function handle(AdminDashboardSnapshotService $snapshot): int
    {
        $start = microtime(true);
        $snapshot->warm();
        $elapsed = (int) ((microtime(true) - $start) * 1000);

        $this->info("Admin dashboard snapshot warmed in {$elapsed} ms.");

        return self::SUCCESS;
    }
}

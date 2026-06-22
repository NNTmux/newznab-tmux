<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Gdpr\GdprRetentionService;
use Illuminate\Console\Command;

class GdprPurgeExpiredExports extends Command
{
    protected $signature = 'gdpr:purge-expired-exports';

    protected $description = 'Purge expired GDPR export files while retaining the request audit record.';

    public function handle(GdprRetentionService $retentionService): int
    {
        $count = $retentionService->purgeExpiredExports();

        $this->info("Purged {$count} expired GDPR export file(s).");

        return self::SUCCESS;
    }
}

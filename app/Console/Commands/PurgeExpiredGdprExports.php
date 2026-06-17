<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Gdpr\GdprRetentionService;
use Illuminate\Console\Command;

class PurgeExpiredGdprExports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gdpr:purge-expired-exports';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete expired GDPR data export files and clear their stored paths to honour the retention policy.';

    public function handle(GdprRetentionService $retentionService): int
    {
        try {
            $purged = $retentionService->purgeExpiredExports();

            $this->info("Purged {$purged} expired GDPR export file(s).");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Failed to purge expired GDPR exports: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}

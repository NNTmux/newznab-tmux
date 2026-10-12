<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ReleaseProcessingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'releases:process')]
class ProcessReleasesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'releases:process
                            {groupId? : Group ID to process (optional)}
                            {--maintenance-only : Run release cleanup and categorization without processing collections}
                            {--ready-only : Process one bounded ready window for a group without reconciliation or retention cleanup}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process releases for a specific group or all groups';

    public function __construct(
        private readonly ReleaseProcessingService $releaseProcessingService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $groupId = $this->argument('groupId');

        if ($groupId !== null && (! ctype_digit((string) $groupId) || (int) $groupId <= 0)) {
            $this->error('Group ID must be a positive integer.');

            return self::FAILURE;
        }

        if ($this->option('maintenance-only') && $groupId !== null) {
            $this->error('The --maintenance-only option cannot be combined with a group ID.');

            return self::FAILURE;
        }

        if ($this->option('ready-only') && ($groupId === null || $this->option('maintenance-only'))) {
            $this->error('The --ready-only option requires a group ID and cannot be combined with --maintenance-only.');

            return self::FAILURE;
        }

        try {
            if ($this->option('ready-only')) {
                $this->releaseProcessingService->processReadyReleaseWindow((int) $groupId);
            } elseif ($this->option('maintenance-only')) {
                $this->runPostProcessingTasks();
            } elseif ($groupId !== null) {
                $this->processReleasesForGroup((string) $groupId);
            } else {
                $this->processReleasesForGroup('');
                $this->runPostProcessingTasks();
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error($e->getTraceAsString());
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Create / process releases for a groupID.
     */
    private function processReleasesForGroup(string $groupID): void
    {
        $this->releaseProcessingService->processCollectionsAndReleases($groupID);

        $this->releaseProcessingService->deleteCollections($groupID);
    }

    /**
     * Run post-processing tasks after all group releases are processed.
     */
    private function runPostProcessingTasks(): void
    {
        $this->releaseProcessingService->deletedReleasesByGroup();
        $this->releaseProcessingService->deleteReleases();
        $this->releaseProcessingService->categorizeReleases(2);
    }
}

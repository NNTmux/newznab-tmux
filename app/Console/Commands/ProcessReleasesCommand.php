<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ReleaseProcessingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessReleasesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'releases:process {groupId? : Group ID to process (optional)}';

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

        try {
            if (is_numeric($groupId)) {
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
        $limit = $this->releaseProcessingService->getReleaseCreationLimit();

        $this->releaseProcessingService->processIncompleteCollections($groupID);
        $this->releaseProcessingService->processCollectionSizes($groupID);
        $this->releaseProcessingService->deleteUnwantedCollections($groupID);

        do {
            $result = $this->releaseProcessingService->createReleases($groupID);
            $nzbFilesAdded = $this->releaseProcessingService->createNZBs($groupID);

            $shouldContinue = $result->total() >= $limit || $nzbFilesAdded >= $limit;
        } while ($shouldContinue);

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

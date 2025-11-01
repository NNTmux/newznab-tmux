<?php

namespace App\Console\Commands;

use App\Models\Settings;
use Blacklight\processing\ProcessReleases;
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

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $groupId = $this->argument('groupId');

        try {
            $releases = new ProcessReleases;

            if (is_numeric($groupId)) {
                $this->processReleasesForGroup($releases, $groupId);
            } else {
                $this->processReleasesForGroup($releases, '');
                $this->runPostProcessingTasks($releases);
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
    private function processReleasesForGroup(ProcessReleases $releases, string $groupID): void
    {
        $releaseCreationLimit = Settings::settingValue('maxnzbsprocessed') !== ''
            ? (int) Settings::settingValue('maxnzbsprocessed')
            : 1000;

        $releases->processIncompleteCollections($groupID);
        $releases->processCollectionSizes($groupID);
        $releases->deleteUnwantedCollections($groupID);

        do {
            $releasesCount = $releases->createReleases($groupID);
            $nzbFilesAdded = $releases->createNZBs($groupID);
        } while ($releaseCreationLimit <= $releasesCount['added'] + $releasesCount['dupes']
                 || $nzbFilesAdded >= $releaseCreationLimit);

        $releases->deleteCollections($groupID);
    }

    /**
     * Run post-processing tasks after all group releases are processed.
     */
    private function runPostProcessingTasks(ProcessReleases $releases): void
    {
        $releases->deletedReleasesByGroup();
        $releases->deleteReleases();
        $releases->categorizeReleases(2);
    }
}

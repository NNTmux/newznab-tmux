<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Settings;
use App\Models\UsenetGroup;
use Blacklight\Backfill;
use Blacklight\Binaries;
use Blacklight\Nfo;
use Blacklight\NNTP;
use Blacklight\processing\post\ProcessAdditional;
use Blacklight\processing\ProcessReleases;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdatePerGroup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'group:update-all {groupId : Group ID to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Do a single group (update_binaries/backFill/update_releases/postprocess)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $groupId = $this->argument('groupId');

        if (!is_numeric($groupId)) {
            $this->error('Group ID must be numeric.');

            return self::FAILURE;
        }

        try {
            $group = UsenetGroup::find($groupId);

            if ($group === null) {
                $this->error("Group not found with id {$groupId}");

                return self::FAILURE;
            }

            $groupMySQL = $group->toArray();
            $nntp = $this->getNntp();
            $backFill = new Backfill();

            // Update the group for new binaries
            $this->info("Updating binaries for group: {$groupMySQL['name']}");
            (new Binaries())->updateGroup($groupMySQL);

            // BackFill the group with 20k articles
            $this->info("Backfilling group: {$groupMySQL['name']}");
            $backFill->backfillAllGroups($groupMySQL['name'], 20000, 'normal');

            // Create releases
            $this->info("Processing releases for group: {$groupMySQL['name']}");
            $this->processReleases(new ProcessReleases(), (string) $groupId);

            // Post process the releases
            $this->info("Post-processing additional for group: {$groupMySQL['name']}");
            (new ProcessAdditional(['Echo' => true, 'NNTP' => $nntp]))->start($groupId);

            $this->info("Processing NFO files for group: {$groupMySQL['name']}");
            (new Nfo())->processNfoFiles(
                $nntp,
                $groupId,
                '',
                (bool)Settings::settingValue('lookupimdb'),
                (bool)Settings::settingValue('lookuptv')
            );

            $this->info("Completed all processing for group: {$groupMySQL['name']}");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error($e->getTraceAsString());
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Create / process releases for a groupID.
     *
     * Uses the ProcessReleases DTO-based workflow for cleaner code.
     */
    private function processReleases(ProcessReleases $releases, string $groupID): void
    {
        $limit = $releases->getReleaseCreationLimit();

        $releases->processIncompleteCollections($groupID);
        $releases->processCollectionSizes($groupID);
        $releases->deleteUnwantedCollections($groupID);

        do {
            $result = $releases->createReleases($groupID);
            $nzbFilesAdded = $releases->createNZBs($groupID);

            // Continue if we processed up to the limit (more work may be available)
            $shouldContinue = $result->total() >= $limit || $nzbFilesAdded >= $limit;
        } while ($shouldContinue);

        $releases->deleteCollections($groupID);
    }

    /**
     * Get NNTP connection.
     *
     * @throws \Exception If unable to connect to usenet
     */
    private function getNntp(): NNTP
    {
        $nntp = new NNTP();

        $useAlternate = config('nntmux_nntp.use_alternate_nntp_server') === true;
        $connected = $useAlternate
            ? $nntp->doConnect(false, true)
            : $nntp->doConnect();

        if ($connected !== true) {
            throw new \RuntimeException('Unable to connect to usenet.');
        }

        return $nntp;
    }
}

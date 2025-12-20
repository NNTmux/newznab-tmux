<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Settings;
use App\Models\UsenetGroup;
use App\Services\AdditionalProcessing\AdditionalProcessingOrchestrator;
use App\Services\Backfill\BackfillService;
use App\Services\Binaries\BinariesService;
use App\Services\ReleaseProcessingService;
use Blacklight\Nfo;
use Blacklight\NNTP;
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
            $backfillService = new BackfillService(nntp: $nntp);

            // Update the group for new binaries
            $this->info("Updating binaries for group: {$groupMySQL['name']}");
            (new BinariesService())->updateGroup($groupMySQL);

            // BackFill the group with 20k articles
            $this->info("Backfilling group: {$groupMySQL['name']}");
            $backfillService->backfillAllGroups($groupMySQL['name'], 20000, 'normal');

            // Create releases
            $this->info("Processing releases for group: {$groupMySQL['name']}");
            $this->processReleases((string) $groupId);

            // Post process the releases
            $this->info("Post-processing additional for group: {$groupMySQL['name']}");
            app(AdditionalProcessingOrchestrator::class)->start($groupId);

            $this->info("Processing NFO files for group: {$groupMySQL['name']}");
            (new Nfo())->processNfoFiles(
                $nntp,
                $groupId,
                '',
                (bool) Settings::settingValue('lookupimdb'),
                (bool) Settings::settingValue('lookuptv')
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
     */
    private function processReleases(string $groupID): void
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

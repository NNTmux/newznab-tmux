<?php

declare(strict_types=1);

namespace App\Services\AdditionalProcessing;

use App\Models\Release;
use App\Services\AdditionalProcessing\Config\ProcessingConfiguration;
use App\Services\AdditionalProcessing\State\ReleaseProcessingContext;
use App\Services\TempWorkspaceService;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Main orchestrator for additional release post-processing.
 * Coordinates release selection and delegates actual per-release work to ReleaseProcessor.
 */
class AdditionalProcessingOrchestrator
{
    public const int MAX_COMPRESSED_FILES_TO_CHECK = 10;

    /**
     * @var Collection<int, mixed>
     */
    private Collection $releases;

    private int $totalReleases = 0;

    private string $mainTmpPath = '';

    public function __construct(
        private readonly ProcessingConfiguration $config,
        private readonly ReleaseProcessor $processor,
        private readonly TempWorkspaceService $tempWorkspace,
        private readonly ConsoleOutputService $output
    ) {}

    /**
     * Start the additional processing.
     *
     * @throws Exception
     */
    public function start(string $groupID = '', string $guidChar = ''): void
    {
        $this->finish();
        $this->setupTempPath($guidChar, $groupID);
        $this->fetchReleases($groupID, $guidChar);

        if ($this->totalReleases > 0) {
            $this->output->echoDescription($this->totalReleases);
            $this->processReleases();
        }
    }

    /**
     * Process a single release by GUID.
     */
    public function processSingleGuid(string $guid): bool
    {
        try {
            $this->finish();
            $release = Release::where('guid', $guid)->first();
            if ($release === null) {
                $this->output->warning('Release not found for GUID: '.$guid);

                return false;
            }

            $this->releases = collect([$release]);
            $this->totalReleases = 1;
            $guidChar = $release->leftguid ?? substr($release->guid, 0, 1);
            $groupID = '';
            $this->setupTempPath($guidChar, $groupID);
            $this->processReleases();

            return true;
        } catch (\Throwable $e) {
            if ($this->config->debugMode) {
                Log::error('processSingleGuid failed: '.$e->getMessage());
            }

            return false;
        }
    }

    /**
     * Set up the main temp path.
     */
    private function setupTempPath(string $guidChar, string $groupID): void
    {
        $this->mainTmpPath = $this->tempWorkspace->ensureMainTempPath(
            $this->config->tmpUnrarPath,
            $guidChar,
            $groupID
        );
        $this->tempWorkspace->clearDirectory($this->mainTmpPath, true);
    }

    /**
     * Fetch releases for processing.
     */
    private function fetchReleases(int|string $groupID, string $guidChar): void
    {
        $query = Release::query()
            ->from('releases as r')
            ->select([
                'r.id',
                'r.guid',
                'r.name',
                'r.size',
                'r.groups_id',
                'r.nfostatus',
                'r.fromname',
                'r.completion',
                'r.categories_id',
                'r.searchname',
                'r.predb_id',
                'r.pp_timeout_count',
            ])
            ->selectRaw('r.id as releases_id')
            ->leftJoin('categories as c', 'c.id', '=', 'r.categories_id')
            ->where('r.passwordstatus', -1)
            ->where('r.nzbstatus', 1)
            ->where('r.haspreview', -1)
            ->where('c.disablepreview', 0);

        if ($this->config->maxSizeGB > 0) {
            $query->where('r.size', '<', $this->config->maxSizeGB * 1073741824);
        }
        if ($this->config->minSizeMB > 0) {
            $query->where('r.size', '>', $this->config->minSizeMB * 1048576);
        }
        if ($groupID !== '') {
            $query->where('r.groups_id', $groupID);
        }
        if ($guidChar !== '') {
            $query->where('r.leftguid', $guidChar);
        }

        $this->releases = $query
            ->orderBy('r.passwordstatus')
            ->orderByDesc('r.postdate')
            ->limit($this->config->queryLimit > 0 ? $this->config->queryLimit : 25)
            ->get();
        $this->totalReleases = $this->releases->count();
    }

    /**
     * Process all fetched releases.
     *
     * @throws Exception
     */
    private function processReleases(): void
    {
        foreach ($this->releases as $release) {
            $this->processor->process(new ReleaseProcessingContext($release), $this->mainTmpPath);
        }

        $this->output->endOutput();
    }

    public function finish(): void
    {
        if ($this->mainTmpPath !== '') {
            $this->tempWorkspace->clearDirectory($this->mainTmpPath, true);
            $this->mainTmpPath = '';
        }

        $this->releases = collect();
        $this->totalReleases = 0;
    }
}

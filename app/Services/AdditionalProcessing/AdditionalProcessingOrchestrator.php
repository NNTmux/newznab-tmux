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
            $this->processReleases($guidChar);
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
            $this->processReleases($guidChar);

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
     *
     * The selection predicates (passwordstatus, haspreview, nzbstatus,
     * disablepreview, size bounds) are owned by AdditionalCandidateQuery so
     * they stay consistent with the bucket-fanout SQL in
     * PostProcessRunner::processAdditional(). Do NOT inline new predicates
     * here; add them to AdditionalCandidateQuery instead.
     */
    private function fetchReleases(int|string $groupID, string $guidChar): void
    {
        $query = AdditionalCandidateQuery::baseBuilder(
            $groupID,
            $guidChar,
            $this->config->minSizeMB,
            $this->config->maxSizeGB,
        )
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
            ->selectRaw('r.id as releases_id');

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
     * Each release is processed inside its own try/catch so that a single
     * poison release cannot stall an entire GUID-character bucket. Without
     * this, an exception from ReleaseProcessor::process() would abort the
     * foreach for the whole worker, the same release would be re-selected on
     * every subsequent cycle, and the "needs additional pp" backlog would
     * grow indefinitely without any visible failure.
     *
     * @throws Exception
     */
    private function processReleases(string $guidChar = ''): void
    {
        $processed = 0;
        $failed = 0;

        foreach ($this->releases as $release) {
            try {
                $this->processor->process(new ReleaseProcessingContext($release), $this->mainTmpPath);
                $processed++;
            } catch (\Throwable $e) {
                $failed++;
                Log::error('Additional postprocessing failed for release '.($release->id ?? '?').': '.$e->getMessage(), [
                    'release_id' => $release->id ?? null,
                    'guid' => $release->guid ?? null,
                    'guid_char' => $guidChar,
                    'exception' => $e,
                ]);
                // Don't rethrow: keep draining the bucket. The release will be
                // re-selected on the next cycle, and the pp_timeout_count /
                // maxpptimeoutcount machinery will eventually drop it.
            }
        }

        Log::info('Additional postprocessing run finished', [
            'guid_char' => $guidChar,
            'picked' => $this->totalReleases,
            'processed' => $processed,
            'failed' => $failed,
        ]);

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

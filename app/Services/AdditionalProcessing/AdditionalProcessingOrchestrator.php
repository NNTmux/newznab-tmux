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

    private string $claimToken = '';

    public function __construct(
        private readonly ProcessingConfiguration $config,
        private readonly ReleaseProcessor $processor,
        private readonly TempWorkspaceService $tempWorkspace,
        private readonly ConsoleOutputService $output
    ) {}

    /**
     * Start the additional processing.
     *
     * @param  list<int>  $excludedReleaseIds
     * @return array{claimed: int, processed: int, failed: int, claimed_ids: list<int>}
     *
     * @throws Exception
     */
    public function start(
        string $groupID = '',
        string $guidChar = '',
        string $workerToken = '',
        array $excludedReleaseIds = []
    ): array {
        $this->finish();
        if (! $this->setupTempPath($guidChar, $groupID, $workerToken)) {
            return ['claimed' => 0, 'processed' => 0, 'failed' => 0, 'claimed_ids' => []];
        }

        $this->fetchReleases($groupID, $guidChar, $excludedReleaseIds);

        if ($this->totalReleases > 0) {
            $this->output->echoDescription($this->totalReleases);

            return $this->processReleases($guidChar);
        }

        return ['claimed' => 0, 'processed' => 0, 'failed' => 0, 'claimed_ids' => []];
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
            if (! $this->setupTempPath($guidChar, $groupID)) {
                return false;
            }

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
    private function setupTempPath(string $guidChar, string $groupID, string $workerToken = ''): bool
    {
        try {
            $this->mainTmpPath = $this->tempWorkspace->ensureMainTempPath(
                $this->config->tmpUnrarPath,
                $guidChar,
                $groupID,
                $workerToken !== '' ? $workerToken : bin2hex(random_bytes(16)),
            );
            $this->tempWorkspace->clearDirectory($this->mainTmpPath, true);
        } catch (\Throwable $e) {
            $this->output->warning('Additional post-processing skipped: '.$e->getMessage());
            Log::error('Additional post-processing temp path is unavailable', [
                'tmp_unrar_path' => $this->config->tmpUnrarPath,
                'guid_char' => $guidChar,
                'group_id' => $groupID,
                'exception' => $e,
            ]);
            $this->mainTmpPath = '';

            return false;
        }

        return true;
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
    /**
     * @param  list<int>  $excludedReleaseIds
     */
    private function fetchReleases(int|string $groupID, string $guidChar, array $excludedReleaseIds = []): void
    {
        $this->claimToken = bin2hex(random_bytes(16));
        $this->releases = AdditionalCandidateQuery::claimBatch(
            $guidChar,
            $this->config->queryLimit > 0 ? $this->config->queryLimit : 25,
            $this->claimToken,
            $groupID,
            $this->config->minSizeMB,
            $this->config->maxSizeGB,
            [
                'id',
                'guid',
                'name',
                'size',
                'groups_id',
                'nfostatus',
                'fromname',
                'completion',
                'categories_id',
                'searchname',
                'predb_id',
                'pp_timeout_count',
                AdditionalCandidateQuery::CLAIM_TOKEN_COLUMN,
            ],
            $excludedReleaseIds,
        );
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
     * @return array{claimed: int, processed: int, failed: int, claimed_ids: list<int>}
     *
     * @throws Exception
     */
    private function processReleases(string $guidChar = ''): array
    {
        $startedAt = microtime(true);
        $claimedIds = $this->releases
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
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
            } finally {
                if ($this->claimToken !== '' && ! empty($release->id)) {
                    AdditionalCandidateQuery::clearClaim((int) $release->id, $this->claimToken);
                }
            }
        }

        Log::info('Additional postprocessing run finished', [
            'guid_char' => $guidChar,
            'picked' => $this->totalReleases,
            'processed' => $processed,
            'failed' => $failed,
            'elapsed_seconds' => round(microtime(true) - $startedAt, 3),
            'peak_memory_bytes' => memory_get_peak_usage(true),
        ]);

        $this->output->endOutput();

        return [
            'claimed' => $this->totalReleases,
            'processed' => $processed,
            'failed' => $failed,
            'claimed_ids' => $claimedIds,
        ];
    }

    public function finish(): void
    {
        if ($this->mainTmpPath !== '') {
            $this->tempWorkspace->clearDirectory($this->mainTmpPath, false);
            $this->mainTmpPath = '';
        }

        $this->releases = collect();
        $this->totalReleases = 0;
        $this->claimToken = '';
    }
}

<?php

declare(strict_types=1);

namespace App\Services\AdditionalProcessing;

use App\Models\Release;
use App\Services\AdditionalProcessing\Config\ProcessingConfiguration;
use App\Services\AdditionalProcessing\DTO\AdditionalBatchResult;
use App\Services\AdditionalProcessing\DTO\ReleaseProcessingResult;
use App\Services\AdditionalProcessing\Enums\ProcessingOutcome;
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

    private string $lastSetupError = '';

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
     *
     * @throws Exception
     */
    public function start(
        string $groupID = '',
        string $guidChar = '',
        string $workerToken = '',
        array $excludedReleaseIds = []
    ): AdditionalBatchResult {
        $this->finish();
        if (! $this->setupTempPath($guidChar, $groupID, $workerToken)) {
            return AdditionalBatchResult::setupFailed($this->lastSetupError);
        }

        $this->fetchReleases($groupID, $guidChar, $excludedReleaseIds);

        if ($this->totalReleases > 0) {
            $this->output->echoDescription($this->totalReleases);

            return $this->processReleases($guidChar);
        }

        return AdditionalBatchResult::empty();
    }

    /**
     * Process a single release by GUID.
     */
    public function processSingleGuid(string $guid): ReleaseProcessingResult
    {
        $releaseId = 0;

        try {
            $this->finish();
            $release = Release::where('guid', $guid)->first();
            if ($release === null) {
                $this->output->warning('Release not found for GUID: '.$guid);

                return new ReleaseProcessingResult(0, $guid, ProcessingOutcome::NotFound, reason: 'Release not found.');
            }

            $releaseId = (int) $release->id;

            $this->releases = collect([$release]);
            $this->totalReleases = 1;
            $guidChar = $release->leftguid ?? substr($release->guid, 0, 1);
            $groupID = '';
            if (! $this->setupTempPath($guidChar, $groupID)) {
                return new ReleaseProcessingResult(
                    $releaseId,
                    $guid,
                    ProcessingOutcome::TemporaryWorkspaceUnavailable,
                    reason: $this->lastSetupError,
                );
            }

            $result = $this->processReleases($guidChar)->firstResult();

            return $result ?? new ReleaseProcessingResult(
                $releaseId,
                $guid,
                ProcessingOutcome::Failed,
                reason: 'No processing result was produced.',
            );
        } catch (\Throwable $e) {
            if ($this->config->debugMode) {
                Log::error('processSingleGuid failed: '.$e->getMessage());
            }

            return new ReleaseProcessingResult(
                $releaseId,
                $guid,
                ProcessingOutcome::Failed,
                reason: $e->getMessage(),
            );
        }
    }

    /**
     * Set up the main temp path.
     */
    private function setupTempPath(string $guidChar, string $groupID, string $workerToken = ''): bool
    {
        $this->lastSetupError = '';

        try {
            $this->mainTmpPath = $this->tempWorkspace->ensureMainTempPath(
                $this->config->tmpUnrarPath,
                $guidChar,
                $groupID,
                $workerToken !== '' ? $workerToken : bin2hex(random_bytes(16)),
            );
            $this->tempWorkspace->clearDirectory($this->mainTmpPath, true);
        } catch (\Throwable $e) {
            $this->lastSetupError = $e->getMessage();
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
            $this->config->minSizeBytes,
            $this->config->maxSizeBytes,
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
     * @throws Exception
     */
    private function processReleases(string $guidChar = ''): AdditionalBatchResult
    {
        $startedAt = hrtime(true);
        $claimedIds = $this->releases
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
        $results = [];

        foreach ($this->releases as $release) {
            $releaseStartedAt = hrtime(true);

            try {
                $results[] = $this->processor->process(new ReleaseProcessingContext($release), $this->mainTmpPath);
            } catch (\Throwable $e) {
                $results[] = new ReleaseProcessingResult(
                    releaseId: (int) ($release->id ?? 0),
                    guid: (string) ($release->guid ?? ''),
                    outcome: ProcessingOutcome::Failed,
                    reason: $e->getMessage(),
                    elapsedSeconds: $this->elapsedSecondsSince($releaseStartedAt),
                );
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

        $batchResult = new AdditionalBatchResult(
            claimedIds: $claimedIds,
            results: $results,
            elapsedSeconds: $this->elapsedSecondsSince($startedAt),
            peakMemoryBytes: memory_get_peak_usage(true),
        );
        $downloadMetrics = $batchResult->downloadMetrics();
        $persistenceMetrics = $batchResult->persistenceMetrics();

        Log::info('Additional postprocessing run finished', [
            'pipeline' => 'v2',
            'guid_char' => $guidChar,
            'picked' => $this->totalReleases,
            'processed' => $batchResult->successfulCount(),
            'failed' => $batchResult->unsuccessfulCount(),
            'outcomes' => $batchResult->outcomeCounts(),
            'artifacts_created' => $batchResult->artifactsCreatedCount(),
            'artifact_yield_percent' => round($batchResult->artifactYieldPercent(), 2),
            'release_files_added' => $batchResult->releaseFilesAdded(),
            'download_requests' => $downloadMetrics->logicalRequests,
            'nntp_requests' => $downloadMetrics->networkRequests,
            'download_cache_hits' => $downloadMetrics->cacheHits,
            'nntp_bytes' => $downloadMetrics->bytesDownloaded,
            'reused_bytes' => $downloadMetrics->bytesReused,
            'database_statements' => $persistenceMetrics->databaseStatements,
            'database_milliseconds' => round($persistenceMetrics->databaseMilliseconds, 3),
            'search_sync_requests' => $persistenceMetrics->searchSyncRequests,
            'search_sync_executions' => $persistenceMetrics->searchSyncExecutions,
            'duplicate_message_ids' => $batchResult->duplicateMessageIdCount(),
            'unsupported_reasons' => $batchResult->unsupportedReasonCounts(),
            'elapsed_seconds' => round($batchResult->elapsedSeconds, 6),
            'releases_per_second' => round($batchResult->releasesPerSecond(), 4),
            'average_release_seconds' => round($batchResult->averageReleaseSeconds(), 6),
            'stage_seconds' => $this->roundedStageDurations($batchResult->stageDurationTotals()),
            'peak_memory_bytes' => $batchResult->peakMemoryBytes,
        ]);

        $this->output->endOutput();

        return $batchResult;
    }

    private function elapsedSecondsSince(int $startedAtNanoseconds): float
    {
        return (hrtime(true) - $startedAtNanoseconds) / 1_000_000_000;
    }

    /**
     * @param  array<string, float>  $durations
     * @return array<string, float>
     */
    private function roundedStageDurations(array $durations): array
    {
        return array_map(
            static fn (float $duration): float => round($duration, 6),
            $durations,
        );
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
        $this->lastSetupError = '';
    }
}

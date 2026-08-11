<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Settings;
use App\Services\AdditionalProcessing\AdditionalProcessingOrchestrator;
use App\Services\AdditionalProcessing\DTO\AdditionalBatchResult;
use App\Services\NfoService;
use App\Services\NNTP\NNTPService;
use App\Services\PostProcessService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PostProcessGuid extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'postprocess:guid
                            {type : Type: additional, nfo, movie, tv, anime, books, music, console, or games}
                            {guid : First character of release guid (a-f, 0-9)}
                            {renamed? : For movie/tv: process renamed only (optional)}
                            {--worker : Drain multiple additional-processing batches}
                            {--max-batches=4 : Maximum claim batches for worker mode}
                            {--profile : Emit one machine-readable performance record per additional batch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Post process releases by GUID character';

    public function __construct(
        private readonly PostProcessService $postProcessService,
        private readonly AdditionalProcessingOrchestrator $additionalProcessor
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $type = $this->argument('type');
        $guid = $this->argument('guid');
        $renamed = $this->argument('renamed') ?? '';

        if (! $this->isValidChar($guid)) {
            $this->error('GUID character must be a-f or 0-9.');

            return self::FAILURE;
        }

        try {
            match ($type) {
                'additional' => $this->processAdditional($guid, (bool) $this->option('worker')),
                'nfo' => $this->processNfo($guid),
                'movie' => $this->postProcessService->processMovies('', $guid, $renamed),
                'tv' => $this->postProcessService->processTv('', $guid, $renamed),
                'anime' => $this->postProcessService->processAnime('', $guid),
                'books' => $this->postProcessService->processBooks('', $guid),
                'music' => $this->postProcessService->processMusic('', $guid),
                'console' => $this->postProcessService->processConsoles('', $guid),
                'games' => $this->postProcessService->processGames('', $guid),
                default => throw new \InvalidArgumentException(
                    'Invalid type. Must be: additional, nfo, movie, tv, anime, books, music, console, or games.'
                ),
            };

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error($e->getTraceAsString());
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Process additional data for releases.
     */
    private function processAdditional(string $guid, bool $workerMode): void
    {
        $maxBatches = $workerMode ? max(1, (int) $this->option('max-batches')) : 1;
        $workerToken = bin2hex(random_bytes(16));
        $childTimeout = (int) config('nntmux.multiprocessing_max_child_time', 1800);
        $deadline = microtime(true) + max(1, $childTimeout - 30);
        $excludedReleaseIds = [];

        try {
            for ($batch = 0; $batch < $maxBatches && microtime(true) < $deadline; $batch++) {
                $result = $this->additionalProcessor->start('', $guid, $workerToken, $excludedReleaseIds);
                if ((bool) $this->option('profile')) {
                    $this->writeAdditionalProfile($guid, $batch + 1, $result);
                }
                if ($result->claimedCount() === 0) {
                    break;
                }
                $excludedReleaseIds = array_values(array_unique([
                    ...$excludedReleaseIds,
                    ...$result->claimedIds,
                ]));
            }
        } finally {
            $this->additionalProcessor->finish();
        }
    }

    private function writeAdditionalProfile(string $guid, int $batch, AdditionalBatchResult $result): void
    {
        $downloadMetrics = $result->downloadMetrics();
        $persistenceMetrics = $result->persistenceMetrics();

        $this->line(json_encode([
            'event' => 'additional-postprocessing-profile',
            'pipeline' => 'v2',
            'guid_char' => $guid,
            'batch' => $batch,
            'claimed' => $result->claimedCount(),
            'attempted' => $result->attemptedCount(),
            'successful' => $result->successfulCount(),
            'failed' => $result->unsuccessfulCount(),
            'outcomes' => $result->outcomeCounts(),
            'artifacts_created' => $result->artifactsCreatedCount(),
            'artifact_yield_percent' => round($result->artifactYieldPercent(), 2),
            'release_files_added' => $result->releaseFilesAdded(),
            'nntp_requests' => $downloadMetrics->networkRequests,
            'nntp_bytes' => $downloadMetrics->bytesDownloaded,
            'database_statements' => $persistenceMetrics->databaseStatements,
            'database_milliseconds' => round($persistenceMetrics->databaseMilliseconds, 3),
            'search_sync_requests' => $persistenceMetrics->searchSyncRequests,
            'search_sync_executions' => $persistenceMetrics->searchSyncExecutions,
            'duplicate_message_ids' => $result->duplicateMessageIdCount(),
            'elapsed_seconds' => round($result->elapsedSeconds, 6),
            'releases_per_hour' => round($result->releasesPerSecond() * 3600, 2),
            'average_release_seconds' => round($result->averageReleaseSeconds(), 6),
            'stage_seconds' => $result->stageDurationTotals(),
            'peak_memory_bytes' => $result->peakMemoryBytes,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * Process NFO files for releases.
     */
    private function processNfo(string $guid): void
    {
        $nntp = $this->getNntp();
        (new NfoService)->processNfoFiles(
            $nntp,
            '',
            $guid,
            (bool) Settings::settingValue('lookupimdb'),
            (bool) Settings::settingValue('lookuptv')
        );
    }

    /**
     * Check if the character contains a-f or 0-9.
     */
    private function isValidChar(string $char): bool
    {
        return \in_array(
            $char,
            ['a', 'b', 'c', 'd', 'e', 'f', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            true
        );
    }

    /**
     * Get NNTP connection.
     */
    private function getNntp(): NNTPService
    {
        $nntp = new NNTPService;

        $connectResult = config('nntmux_nntp.use_alternate_nntp_server') === true
            ? $nntp->doConnect(false, true)
            : $nntp->doConnect();

        if ($connectResult !== true) {
            $errorMessage = 'Unable to connect to usenet.';
            if (NNTPService::isError($connectResult)) {
                $errorMessage .= ' Error: '.$connectResult->getMessage();
            }
            throw new \RuntimeException($errorMessage);
        }

        return $nntp;
    }
}

<?php

declare(strict_types=1);

namespace App\Services\AdditionalProcessing\DTO;

use App\Services\AdditionalProcessing\Enums\ProcessingOutcome;

final readonly class AdditionalBatchResult
{
    /**
     * @param  list<int>  $claimedIds
     * @param  list<ReleaseProcessingResult>  $results
     */
    public function __construct(
        public array $claimedIds = [],
        public array $results = [],
        public string $setupFailure = '',
        public float $elapsedSeconds = 0.0,
        public int $peakMemoryBytes = 0,
    ) {}

    public static function empty(): self
    {
        return new self;
    }

    public static function setupFailed(string $reason): self
    {
        return new self(setupFailure: $reason);
    }

    public function claimedCount(): int
    {
        return count($this->claimedIds);
    }

    public function attemptedCount(): int
    {
        return count($this->results);
    }

    public function successfulCount(): int
    {
        return count(array_filter(
            $this->results,
            static fn (ReleaseProcessingResult $result): bool => $result->isSuccessful(),
        ));
    }

    public function unsuccessfulCount(): int
    {
        return $this->attemptedCount() - $this->successfulCount();
    }

    /**
     * @return array<string, int>
     */
    public function outcomeCounts(): array
    {
        $counts = [];

        foreach ($this->results as $result) {
            $outcome = $result->outcome->value;
            $counts[$outcome] = ($counts[$outcome] ?? 0) + 1;
        }

        return $counts;
    }

    public function firstResult(): ?ReleaseProcessingResult
    {
        return $this->results[0] ?? null;
    }

    public function hasOutcome(ProcessingOutcome $outcome): bool
    {
        foreach ($this->results as $result) {
            if ($result->outcome === $outcome) {
                return true;
            }
        }

        return false;
    }

    public function withPerformance(float $elapsedSeconds, int $peakMemoryBytes): self
    {
        return new self(
            claimedIds: $this->claimedIds,
            results: $this->results,
            setupFailure: $this->setupFailure,
            elapsedSeconds: $elapsedSeconds,
            peakMemoryBytes: $peakMemoryBytes,
        );
    }

    public function releasesPerSecond(): float
    {
        if ($this->elapsedSeconds <= 0.0) {
            return 0.0;
        }

        return $this->attemptedCount() / $this->elapsedSeconds;
    }

    public function averageReleaseSeconds(): float
    {
        if ($this->attemptedCount() === 0) {
            return 0.0;
        }

        return array_sum(array_map(
            static fn (ReleaseProcessingResult $result): float => $result->elapsedSeconds,
            $this->results,
        )) / $this->attemptedCount();
    }

    public function artifactsCreatedCount(): int
    {
        return count(array_filter(
            $this->results,
            static fn (ReleaseProcessingResult $result): bool => $result->artifactsCreated,
        ));
    }

    public function artifactYieldPercent(): float
    {
        if ($this->successfulCount() === 0) {
            return 0.0;
        }

        $successfulArtifacts = count(array_filter(
            $this->results,
            static fn (ReleaseProcessingResult $result): bool => $result->isSuccessful() && $result->artifactsCreated,
        ));

        return ($successfulArtifacts / $this->successfulCount()) * 100;
    }

    public function releaseFilesAdded(): int
    {
        return array_sum(array_map(
            static fn (ReleaseProcessingResult $result): int => $result->releaseFilesAdded,
            $this->results,
        ));
    }

    public function downloadMetrics(): DownloadMetrics
    {
        $logicalRequests = 0;
        $networkRequests = 0;
        $cacheHits = 0;
        $bytesDownloaded = 0;
        $bytesReused = 0;

        foreach ($this->results as $result) {
            if ($result->downloadMetrics === null) {
                continue;
            }

            $logicalRequests += $result->downloadMetrics->logicalRequests;
            $networkRequests += $result->downloadMetrics->networkRequests;
            $cacheHits += $result->downloadMetrics->cacheHits;
            $bytesDownloaded += $result->downloadMetrics->bytesDownloaded;
            $bytesReused += $result->downloadMetrics->bytesReused;
        }

        return new DownloadMetrics(
            logicalRequests: $logicalRequests,
            networkRequests: $networkRequests,
            cacheHits: $cacheHits,
            bytesDownloaded: $bytesDownloaded,
            bytesReused: $bytesReused,
        );
    }

    public function persistenceMetrics(): PersistenceMetrics
    {
        $databaseStatements = 0;
        $databaseMilliseconds = 0.0;
        $searchSyncRequests = 0;
        $searchSyncExecutions = 0;

        foreach ($this->results as $result) {
            if ($result->persistenceMetrics === null) {
                continue;
            }

            $databaseStatements += $result->persistenceMetrics->databaseStatements;
            $databaseMilliseconds += $result->persistenceMetrics->databaseMilliseconds;
            $searchSyncRequests += $result->persistenceMetrics->searchSyncRequests;
            $searchSyncExecutions += $result->persistenceMetrics->searchSyncExecutions;
        }

        return new PersistenceMetrics(
            databaseStatements: $databaseStatements,
            databaseMilliseconds: $databaseMilliseconds,
            searchSyncRequests: $searchSyncRequests,
            searchSyncExecutions: $searchSyncExecutions,
        );
    }

    public function duplicateMessageIdCount(): int
    {
        return array_sum(array_map(
            static fn (ReleaseProcessingResult $result): int => $result->duplicateMessageIdCount,
            $this->results,
        ));
    }

    /**
     * @return array<string, int>
     */
    public function unsupportedReasonCounts(): array
    {
        $counts = [];

        foreach ($this->results as $result) {
            foreach ($result->unsupportedReasons as $reason) {
                $counts[$reason] = ($counts[$reason] ?? 0) + 1;
            }
        }

        return $counts;
    }

    /**
     * @return array<string, float>
     */
    public function stageDurationTotals(): array
    {
        $totals = [];

        foreach ($this->results as $result) {
            foreach ($result->stageDurations as $stage => $duration) {
                $totals[$stage] = ($totals[$stage] ?? 0.0) + $duration;
            }
        }

        return $totals;
    }
}

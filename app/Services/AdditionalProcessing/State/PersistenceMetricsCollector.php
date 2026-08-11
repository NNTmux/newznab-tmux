<?php

declare(strict_types=1);

namespace App\Services\AdditionalProcessing\State;

use App\Services\AdditionalProcessing\DTO\PersistenceMetrics;

final class PersistenceMetricsCollector
{
    private ?int $releaseId = null;

    private int $databaseStatements = 0;

    private float $databaseMilliseconds = 0.0;

    private int $searchSyncRequests = 0;

    private int $searchSyncExecutions = 0;

    public function beginReleaseScope(int $releaseId): void
    {
        $this->releaseId = $releaseId;
        $this->databaseStatements = 0;
        $this->databaseMilliseconds = 0.0;
        $this->searchSyncRequests = 0;
        $this->searchSyncExecutions = 0;
    }

    public function recordDatabaseStatement(float $milliseconds): void
    {
        if ($this->releaseId === null) {
            return;
        }

        $this->databaseStatements++;
        $this->databaseMilliseconds += $milliseconds;
    }

    public function recordSearchSyncRequest(int $releaseId): void
    {
        if ($this->releaseId === $releaseId) {
            $this->searchSyncRequests++;
        }
    }

    public function recordSearchSyncExecution(int $releaseId): void
    {
        if ($this->releaseId === $releaseId) {
            $this->searchSyncExecutions++;
        }
    }

    public function finishReleaseScope(): PersistenceMetrics
    {
        $metrics = new PersistenceMetrics(
            databaseStatements: $this->databaseStatements,
            databaseMilliseconds: $this->databaseMilliseconds,
            searchSyncRequests: $this->searchSyncRequests,
            searchSyncExecutions: $this->searchSyncExecutions,
        );

        $this->releaseId = null;
        $this->databaseStatements = 0;
        $this->databaseMilliseconds = 0.0;
        $this->searchSyncRequests = 0;
        $this->searchSyncExecutions = 0;

        return $metrics;
    }
}

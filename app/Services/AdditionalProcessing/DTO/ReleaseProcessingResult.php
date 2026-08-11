<?php

declare(strict_types=1);

namespace App\Services\AdditionalProcessing\DTO;

use App\Services\AdditionalProcessing\Enums\ProcessingOutcome;

final readonly class ReleaseProcessingResult
{
    /**
     * @param  array<string, float>  $stageDurations
     * @param  list<string>  $unsupportedReasons
     */
    public function __construct(
        public int $releaseId,
        public string $guid,
        public ProcessingOutcome $outcome,
        public bool $artifactsCreated = false,
        public int $releaseFilesAdded = 0,
        public string $reason = '',
        public float $elapsedSeconds = 0.0,
        public array $stageDurations = [],
        public ?DownloadMetrics $downloadMetrics = null,
        public ?PersistenceMetrics $persistenceMetrics = null,
        public int $duplicateMessageIdCount = 0,
        public array $unsupportedReasons = [],
    ) {}

    public function isSuccessful(): bool
    {
        return $this->outcome->isSuccessful();
    }

    /**
     * @param  array<string, float>  $stageDurations
     */
    public function withPerformance(
        float $elapsedSeconds,
        array $stageDurations,
        ?DownloadMetrics $downloadMetrics = null,
        ?PersistenceMetrics $persistenceMetrics = null,
    ): self {
        return new self(
            releaseId: $this->releaseId,
            guid: $this->guid,
            outcome: $this->outcome,
            artifactsCreated: $this->artifactsCreated,
            releaseFilesAdded: $this->releaseFilesAdded,
            reason: $this->reason,
            elapsedSeconds: $elapsedSeconds,
            stageDurations: $stageDurations,
            downloadMetrics: $downloadMetrics ?? $this->downloadMetrics,
            persistenceMetrics: $persistenceMetrics ?? $this->persistenceMetrics,
            duplicateMessageIdCount: $this->duplicateMessageIdCount,
            unsupportedReasons: $this->unsupportedReasons,
        );
    }
}

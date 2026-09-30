<?php

declare(strict_types=1);

namespace App\Support\Data;

use App\Models\ProcessingRuntimeState;
use App\Support\Configuration\IngestionConfigurationData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Configuration settings for ProcessReleases operations.
 *
 * Hydrated from typed ingestion configuration and uncached runtime state.
 */
#[TypeScript]
final class ProcessReleasesSettings extends Data
{
    public function __construct(
        public int $collectionDelayTime = 2,
        public int $crossPostTime = 2,
        public int $releaseCreationLimit = 1000,
        public int $completion = 0,
        public int $collectionTimeout = 48,
        public int $maxSizeToFormRelease = 0,
        public int $minSizeToFormRelease = 0,
        public int $minFilesToFormRelease = 0,
        public int $releaseRetentionDays = 0,
        public bool $deletePasswordedRelease = false,
        public int $miscOtherRetentionHours = 0,
        public int $miscHashedRetentionHours = 0,
        public int $partRetentionHours = 24,
        public ?string $lastRunTime = null,
    ) {
        // Clamp completion to a sane upper bound (legacy `min(100, …)`).
        if ($this->completion > 100) {
            $this->completion = 100;
        }
    }

    public static function fromConfiguration(
        IngestionConfigurationData $configuration,
        ProcessingRuntimeState $runtimeState,
    ): self {
        return new self(
            collectionDelayTime: $configuration->collectionDelayHours,
            crossPostTime: $configuration->crossPostHours,
            releaseCreationLimit: $configuration->maxReleasesCreated,
            completion: $configuration->completionPercent,
            collectionTimeout: $configuration->collectionTimeoutHours,
            maxSizeToFormRelease: $configuration->maxSizeToFormRelease,
            minSizeToFormRelease: $configuration->minSizeToFormRelease,
            minFilesToFormRelease: $configuration->minFilesToFormRelease,
            releaseRetentionDays: $configuration->releaseRetentionDays,
            deletePasswordedRelease: $configuration->deletePasswordedReleases,
            miscOtherRetentionHours: $configuration->miscOtherRetentionHours,
            miscHashedRetentionHours: $configuration->miscHashedRetentionHours,
            partRetentionHours: $configuration->partRetentionHours,
            lastRunTime: $runtimeState->last_binary_run_at?->toDateTimeString(),
        );
    }

    public function hasValidCompletion(): bool
    {
        return $this->completion >= 0 && $this->completion <= 100;
    }

    public function hasRetentionCleanup(): bool
    {
        return $this->releaseRetentionDays > 0;
    }

    public function hasCrossPostDetection(): bool
    {
        return $this->crossPostTime > 0;
    }

    public function hasCompletionCleanup(): bool
    {
        return $this->completion > 0;
    }
}

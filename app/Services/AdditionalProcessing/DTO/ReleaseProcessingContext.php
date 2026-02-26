<?php

declare(strict_types=1);

namespace App\Services\AdditionalProcessing\DTO;

use App\Models\Release;

/**
 * Mutable context object that holds the processing state for a single release.
 * Passed between services during processing to share state.
 */
class ReleaseProcessingContext
{
    // The release being processed
    public Release $release;

    // Processing state flags
    public bool $foundVideo = false;

    public bool $foundMediaInfo = false;

    public bool $foundAudioInfo = false;

    public bool $foundAudioSample = false;

    public bool $foundJPGSample = false;

    public bool $foundSample = false;

    public bool $foundPAR2Info = false;

    // Password state
    public int $passwordStatus = 0;

    public bool $releaseHasPassword = false;

    // NFO state
    public bool $releaseHasNoNFO = false;

    // Group state
    public string $releaseGroupName = '';

    public bool $groupUnavailable = false;

    // NZB state
    public bool $nzbHasCompressedFile = false;

    /**
     * @var array<string, mixed>
     */
    public array $nzbContents = [];

    // Message IDs for downloading
    /**
     * @var array<string, mixed>
     */
    public array $sampleMessageIDs = [];

    /**
     * @var array<string, mixed>
     */
    public array $jpgMessageIDs = [];

    /**
     * @var array<string, mixed>
     */
    public string|array $mediaInfoMessageIDs = [];

    /**
     * @var array<string, mixed>
     */
    public string|array $audioInfoMessageIDs = [];

    /**
     * @var array<string, mixed>
     */
    public array $rarFileMessageIDs = [];

    public string $audioInfoExtension = '';

    // File info counters
    public int $addedFileInfo = 0;

    public int $totalFileInfo = 0;

    public int $compressedFilesChecked = 0;

    // Temp path for this release
    public string $tmpPath = '';

    // Timeout tracking
    public float $startTime = 0;

    public function __construct(Release $release)
    {
        $this->release = $release;
        $this->startTime = hrtime(true);
    }

    /**
     * Initialize processing state based on configuration flags.
     * Sets "found" flags to true if processing is disabled (to skip those steps).
     */
    public function initializeFromConfig(
        bool $processVideo,
        bool $processMediaInfo,
        bool $processAudioInfo,
        bool $processAudioSample,
        bool $processJPGSample,
        bool $processThumbnails
    ): void {
        $this->foundVideo = ! $processVideo;
        $this->foundMediaInfo = ! $processMediaInfo;
        $this->foundAudioInfo = ! $processAudioInfo;
        $this->foundAudioSample = ! $processAudioSample;
        $this->foundJPGSample = ! $processJPGSample;
        $this->foundSample = ! $processThumbnails;
        $this->foundPAR2Info = false;
    }

    /**
     * Reset message ID arrays for a fresh processing run.
     */
    public function resetMessageIDs(): void
    {
        $this->sampleMessageIDs = [];
        $this->jpgMessageIDs = [];
        $this->mediaInfoMessageIDs = [];
        $this->audioInfoMessageIDs = [];
        $this->rarFileMessageIDs = [];
        $this->audioInfoExtension = '';
    }

    /**
     * Reset file counters.
     */
    public function resetCounters(): void
    {
        $this->addedFileInfo = 0;
        $this->totalFileInfo = 0;
        $this->compressedFilesChecked = 0;
    }

    /**
     * Full reset for processing a new release.
     */
    public function reset(): void
    {
        $this->passwordStatus = 0;
        $this->releaseHasPassword = false;
        $this->nzbHasCompressedFile = false;
        $this->groupUnavailable = false;
        $this->resetMessageIDs();
        $this->resetCounters();
    }

    /**
     * Check if more media processing is needed.
     */
    public function needsMediaProcessing(): bool
    {
        return ! $this->foundVideo
            || ! $this->foundMediaInfo
            || ! $this->foundAudioInfo
            || ! $this->foundAudioSample;
    }

    /**
     * Check if any sample is still needed.
     */
    public function needsSample(): bool
    {
        return ! $this->foundSample || ! $this->foundJPGSample;
    }

    /**
     * Check if the release has exceeded the processing timeout.
     */
    public function isTimedOut(int $timeoutSeconds): bool
    {
        if ($timeoutSeconds <= 0) {
            return false;
        }

        return (hrtime(true) - $this->startTime) / 1e9 > $timeoutSeconds;
    }

    /**
     * Get elapsed processing time in seconds.
     */
    public function getElapsedSeconds(): int
    {
        return (int) ((hrtime(true) - $this->startTime) / 1e9);
    }
}

<?php

declare(strict_types=1);

namespace App\Services\AdditionalProcessing\State;

use App\Models\Release;
use App\Services\AdditionalProcessing\DTO\AdditionalWorkPlan;
use App\Services\AdditionalProcessing\DTO\ArchivedMediaEntry;
use App\Services\AdditionalProcessing\DTO\DownloadedArchive;
use App\Services\AdditionalProcessing\DTO\MediaSourcePlan;

/**
 * Mutable context object that holds the processing state for a single release.
 * Passed between services during processing to share state.
 *
 * NOTE: Intentionally NOT a `spatie/laravel-data` Data object. This class is
 * mutated heavily during the additional-processing pipeline (counters, found
 * flags, accumulating message IDs) and holds an Eloquent {@see Release} model,
 * neither of which fits the immutable, serialisable design of `Data` DTOs.
 */
class ReleaseProcessingContext
{
    private const int MAX_RETAINED_ARCHIVES = 2;

    private const int MAX_RETAINED_ARCHIVE_BYTES = 16_777_216;

    private const int MAX_ARCHIVE_FILE_NAMES = 500;

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
     * Volume sets of compressed candidates whose data turned out to be plain media,
     * which has no password.
     *
     * @var array<string, true>
     */
    public array $mediaVolumeSets = [];

    // Archive data was found outside the planned archive candidates (e.g. by the probe).
    public bool $unplannedArchiveSeen = false;

    /**
     * @var list<array<string, mixed>>
     */
    public array $nzbContents = [];

    public ?AdditionalWorkPlan $workPlan = null;

    /** The release is a whole season (by name, NZB file names or listed archive contents). */
    public bool $seasonPack = false;

    /** One episode was listed from a release with several archive sets; the rest of the season may follow. */
    public bool $seasonPackProbable = false;

    /** @var list<string> names listed from every archive read for this release */
    public array $archiveFileNames = [];

    /** @var array<int, array<string, mixed>>|null files of the first archive listed while names were deferred */
    public ?array $firstArchiveFiles = null;

    /** @var array<string, mixed>|null summary of the last archive read in reverse while names were deferred */
    public ?array $reverseArchiveSummary = null;

    /** Real names the PAR2 index revealed for obfuscated files. */
    public ?MediaSourcePlan $mediaPlan = null;

    /** Network bytes left for finding preview sources. */
    public ?MediaByteBudget $mediaBudget = null;

    /**
     * Starts of videos stored in downloaded archive volumes, samples first.
     *
     * @var list<ArchivedMediaEntry>
     */
    public array $archivedMedia = [];

    /**
     * @var list<DownloadedArchive>
     */
    public array $downloadedArchives = [];

    private int $retainedArchiveBytes = 0;

    // Message IDs for downloading
    /**
     * @var list<string>
     */
    public array $sampleMessageIDs = [];

    /**
     * @var list<string>
     */
    public array $jpgMessageIDs = [];

    /**
     * @var list<string>|string
     */
    public string|array $mediaInfoMessageIDs = [];

    /**
     * @var list<string>|string
     */
    public string|array $audioInfoMessageIDs = [];

    /**
     * @var list<string>
     */
    public array $rarFileMessageIDs = [];

    public string $audioInfoExtension = '';

    // File info counters
    public int $addedFileInfo = 0;

    public int $totalFileInfo = 0;

    public int $compressedFilesChecked = 0;

    /**
     * @var array<string, array<string, mixed>>
     */
    public array $pendingReleaseFiles = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    public array $pendingParHashes = [];

    /**
     * @var array<string, true>|null
     */
    public ?array $existingReleaseFileNames = null;

    public bool $releaseFilesChanged = false;

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
        $this->pendingReleaseFiles = [];
        $this->pendingParHashes = [];
        $this->existingReleaseFileNames = null;
        $this->releaseFilesChanged = false;
    }

    /**
     * Full reset for processing a new release.
     */
    public function reset(): void
    {
        $this->passwordStatus = 0;
        $this->releaseHasPassword = false;
        $this->nzbHasCompressedFile = false;
        $this->mediaVolumeSets = [];
        $this->unplannedArchiveSeen = false;
        $this->groupUnavailable = false;
        $this->workPlan = null;
        $this->seasonPack = false;
        $this->seasonPackProbable = false;
        $this->archiveFileNames = [];
        $this->firstArchiveFiles = null;
        $this->reverseArchiveSummary = null;
        $this->mediaPlan = null;
        $this->mediaBudget = null;
        $this->archivedMedia = [];
        $this->releaseDownloadedArchives();
        $this->resetMessageIDs();
        $this->resetCounters();
    }

    /**
     * Retain only small, immediately reusable archive payloads for this release.
     *
     * @param  array<int, array<string, mixed>>  $files
     */
    public function rememberDownloadedArchive(string $title, string $data, array $files): bool
    {
        $byteSize = strlen($data);
        if ($byteSize === 0
            || count($this->downloadedArchives) >= self::MAX_RETAINED_ARCHIVES
            || ($this->retainedArchiveBytes + $byteSize) > self::MAX_RETAINED_ARCHIVE_BYTES
        ) {
            return false;
        }

        $archive = new DownloadedArchive($title, $data, $files);
        $this->downloadedArchives[] = $archive;
        $this->retainedArchiveBytes += $archive->byteSize();

        return true;
    }

    /**
     * @param  array<int, array<string, mixed>>  $files
     */
    public function rememberArchiveFileNames(array $files): void
    {
        foreach ($files as $file) {
            $name = (string) ($file['name'] ?? '');
            if ($name === '' || count($this->archiveFileNames) >= self::MAX_ARCHIVE_FILE_NAMES || in_array($name, $this->archiveFileNames, true)) {
                continue;
            }
            $this->archiveFileNames[] = $name;
        }
    }

    /**
     * Season packs read several archive sets, and no inner file name (one episode)
     * may rename the release while they are read.
     */
    public function defersArchiveNameChecks(): bool
    {
        return $this->seasonPack || $this->seasonPackProbable;
    }

    /**
     * Keep at most two archived videos, so memory stays bounded; a sample displaces a main video.
     */
    public function rememberArchivedMedia(ArchivedMediaEntry $entry): bool
    {
        foreach ($this->archivedMedia as $known) {
            if ($known->name === $entry->name) {
                return false;
            }
        }

        if (count($this->archivedMedia) >= self::MAX_RETAINED_ARCHIVES) {
            $replace = $entry->isSample ? array_search(false, array_map(static fn (ArchivedMediaEntry $known): bool => $known->isSample, $this->archivedMedia), true) : false;
            if ($replace === false) {
                return false;
            }
            unset($this->archivedMedia[$replace]);
        }

        $this->archivedMedia[] = $entry;
        usort($this->archivedMedia, static fn (ArchivedMediaEntry $a, ArchivedMediaEntry $b): int => $b->isSample <=> $a->isSample);

        return true;
    }

    public function releaseDownloadedArchives(): void
    {
        $this->downloadedArchives = [];
        $this->retainedArchiveBytes = 0;
    }

    public function duplicateMessageIdCount(): int
    {
        return $this->workPlan === null ? 0 : $this->workPlan->duplicateMessageIdCount;
    }

    /**
     * @return list<string>
     */
    public function unsupportedReasons(): array
    {
        return $this->workPlan === null ? [] : $this->workPlan->unsupportedReasons;
    }

    /**
     * Mark a processing artifact as found.
     */
    public function markFound(string $type): void
    {
        match ($type) {
            'video' => $this->foundVideo = true,
            'mediaInfo' => $this->foundMediaInfo = true,
            'audioInfo' => $this->foundAudioInfo = true,
            'audioSample' => $this->foundAudioSample = true,
            'jpgSample' => $this->foundJPGSample = true,
            'sample' => $this->foundSample = true,
            'par2Info' => $this->foundPAR2Info = true,
            default => null,
        };
    }

    /**
     * True when every compressed candidate was proven to be plain media; an unread or
     * unreadable archive anywhere else in the release keeps its password status unknown.
     */
    public function compressedFilesAreMedia(): bool
    {
        if ($this->mediaVolumeSets === [] || $this->unplannedArchiveSeen) {
            return false;
        }

        foreach ($this->workPlan->archiveCandidates ?? [] as $candidate) {
            if (! isset($this->mediaVolumeSets[$candidate->volumeSet()])) {
                return false;
            }
        }

        return true;
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

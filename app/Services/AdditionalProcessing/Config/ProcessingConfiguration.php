<?php

declare(strict_types=1);

namespace App\Services\AdditionalProcessing\Config;

use App\Services\AdditionalProcessing\AdditionalCandidateQuery;
use App\Services\Configuration\ConfigurationProvider;

/**
 * Configuration DTO for additional post-processing.
 * Centralizes all settings loading from database and config files.
 */
final readonly class ProcessingConfiguration
{
    public bool $echoCLI;

    public bool|string $innerFileBlacklist;

    public int $maxNestedLevels;

    public bool $extractUsingRarInfo;

    public bool $fetchLastFiles;

    public string|false $unrarPath;

    public string|false $unzipPath;

    public string|false $sevenZipPath;

    public string|false $timeoutPath;

    public int $timeoutSeconds;

    public int $queryLimit;

    public int $segmentsToDownload;

    public int $maximumRarSegments;

    public int $maximumRarPasswordChecks;

    public int $archiveProbeFiles;

    public int $seasonPackArchiveSets;

    public int $seasonPackMaxReleaseFiles;

    public int $maxSizeBytes;

    public int $minSizeBytes;

    public bool $alternateNNTP;

    public int $ffmpegDuration;

    public bool $addPAR2Files;

    public bool $processVideo;

    public bool $processThumbnails;

    public bool $processAudioSample;

    public bool $processJPGSample;

    public bool $processMediaInfo;

    public bool $processAudioInfo;

    public bool $processPasswords;

    public string $audioSavePath;

    public string $tmpUnrarPath;

    public bool $debugMode;

    public bool $searchEnabled;

    public string $searchDriver;

    public bool $renameMusicMediaInfo;

    public bool $renamePar2;

    public string|false $ffmpegPath;

    public string|false $ffprobePath;

    public string|false $mediaInfoPath;

    // Preview source settings
    public bool $par2MediaMapping;

    public int $par2IndexMaxSegments;

    public int $par2VerifyFiles;

    public bool $mediaArchiveSlicing;

    public bool $mediaMainVideoFallback;

    public bool $mediaMidSources;

    public int $mediaMaxBytes;

    public int $mediaSampleMaxBytes;

    public int $mediaMidBytes;

    public int $mediaMainVideoMinBytes;

    public int $mediaImageMaxBytes;

    public int $mediaStageMaxSeconds;

    public int $mediaFfmpegTimeout;

    public int $thumbnailFrameAttempts;

    public float $thumbnailMinStddev;

    public float $thumbnailMinLuma;

    public float $thumbnailMaxLuma;

    // Timeout settings
    public int $releaseProcessingTimeout;

    public int $maxPpTimeoutCount;

    // Regex patterns
    public string $audioFileRegex;

    public string $ignoreBookRegex;

    public string $supportFileRegex;

    public string $videoFileRegex;

    public function __construct()
    {
        $this->echoCLI = (bool) config('nntmux.echocli');
        $innerFileBlacklistValue = app(ConfigurationProvider::class)->postProcessing()->innerFileBlacklist;
        $this->innerFileBlacklist = ($innerFileBlacklistValue === '' || $innerFileBlacklistValue === null)
            ? false
            : $innerFileBlacklistValue;
        $this->maxNestedLevels = (int) app(ConfigurationProvider::class)->postProcessing()->maxNestedLevels ?: 3;
        $this->extractUsingRarInfo = (int) app(ConfigurationProvider::class)->postProcessing()->extractUsingRarInfo !== 0;
        $this->fetchLastFiles = (bool) config('nntmux_settings.fetch_last_file');
        $this->unrarPath = config('nntmux_settings.unrar_path') ?: false;
        $this->unzipPath = config('nntmux_settings.unzip_path') ?: false;
        $this->sevenZipPath = config('nntmux_settings.sevenzip_path') ?: false;
        $this->timeoutPath = config('nntmux_settings.timeout_path') ?: false;
        $this->timeoutSeconds = (int) app(ConfigurationProvider::class)->postProcessing()->timeoutSeconds;
        $this->queryLimit = (int) (app(ConfigurationProvider::class)->postProcessing()->maxAdditionalProcessed ?: 25);
        $this->segmentsToDownload = (int) (app(ConfigurationProvider::class)->postProcessing()->segmentsToDownload ?: 2);
        $this->maximumRarSegments = (int) (app(ConfigurationProvider::class)->postProcessing()->maxPartsProcessed ?: 3);
        $this->maximumRarPasswordChecks = max((int) (app(ConfigurationProvider::class)->postProcessing()->passwordCheckAttempts ?: 1), 1);
        $this->archiveProbeFiles = max(0, (int) config('nntmux.archive_probe_files', 3));
        $this->seasonPackArchiveSets = max(1, min(50, (int) config('nntmux.season_pack_archive_sets', 3)));
        $this->seasonPackMaxReleaseFiles = max(11, min(200, (int) config('nntmux.season_pack_max_release_files', 30)));
        // Delegate to AdditionalCandidateQuery so size-filter semantics
        // (explicit '0' means disabled, empty/null means default) are owned
        // in one place and shared between the bucket query and the per-worker
        // fetch.
        $this->maxSizeBytes = AdditionalCandidateQuery::maxSizeBytes();
        $this->minSizeBytes = AdditionalCandidateQuery::minSizeBytes();
        $this->alternateNNTP = (bool) config('nntmux_nntp.use_alternate_nntp_server');
        $this->ffmpegDuration = (int) (app(ConfigurationProvider::class)->postProcessing()->ffmpegDuration ?: 5);
        $this->addPAR2Files = (bool) config('nntmux_settings.add_par2');
        $this->ffmpegPath = config('nntmux_settings.ffmpeg_path') ?: false;
        $this->ffprobePath = self::resolveFfprobePath(config('nntmux_settings.ffprobe_path') ?: false, $this->ffmpegPath);
        $this->mediaInfoPath = config('nntmux_settings.mediainfo_path') ?: false;
        $this->par2MediaMapping = (bool) config('nntmux.par2_media_mapping', true);
        $this->par2IndexMaxSegments = max(1, (int) config('nntmux.par2_index_max_segments', 2));
        $this->par2VerifyFiles = max(0, (int) config('nntmux.par2_verify_files', 2));
        $this->mediaArchiveSlicing = (bool) config('nntmux.media_archive_slicing', true);
        $this->mediaMainVideoFallback = (bool) config('nntmux.media_main_video_fallback', true);
        $this->mediaMidSources = (bool) config('nntmux.media_mid_sources', false);
        $this->mediaMaxBytes = max(0, (int) config('nntmux.media_max_bytes', 16777216));
        $this->mediaSampleMaxBytes = max(1048576, (int) config('nntmux.media_sample_max_bytes', 8388608));
        $this->mediaMidBytes = max(262144, (int) config('nntmux.media_mid_bytes', 3145728));
        $this->mediaMainVideoMinBytes = max(0, (int) config('nntmux.media_main_video_min_bytes', 104857600));
        $this->mediaImageMaxBytes = max(1048576, (int) config('nntmux.media_image_max_bytes', 8388608));
        $this->mediaStageMaxSeconds = max(0, (int) config('nntmux.media_stage_max_seconds', 30));
        $this->mediaFfmpegTimeout = max(1, (int) config('nntmux.media_ffmpeg_timeout', 30));
        $this->thumbnailFrameAttempts = min(10, max(1, (int) config('nntmux.thumbnail_frame_attempts', 4)));
        $this->thumbnailMinStddev = (float) config('nntmux.thumbnail_min_stddev', 12);
        $this->thumbnailMinLuma = (float) config('nntmux.thumbnail_min_luma', 24);
        $this->thumbnailMaxLuma = (float) config('nntmux.thumbnail_max_luma', 232);
        if (! $this->ffmpegPath) {
            $this->processAudioSample = false;
            $this->processThumbnails = false;
            $this->processVideo = false;
        } else {
            $this->processAudioSample = (int) app(ConfigurationProvider::class)->postProcessing()->saveAudioPreview !== 0;
            $this->processThumbnails = (int) app(ConfigurationProvider::class)->postProcessing()->processThumbnails !== 0;
            $this->processVideo = (int) app(ConfigurationProvider::class)->postProcessing()->processVideos !== 0;
        }
        $this->processJPGSample = (int) app(ConfigurationProvider::class)->postProcessing()->processJpg !== 0;
        $this->processMediaInfo = (bool) $this->mediaInfoPath;
        $this->processAudioInfo = $this->processMediaInfo;
        $this->processPasswords = config('nntmux_settings.check_passworded_rars') === true
            && ! empty(config('nntmux_settings.unrar_path'));
        $this->audioSavePath = config('nntmux_settings.covers_path').'/audiosample/';
        $this->tmpUnrarPath = config('nntmux.tmp_unrar_path');
        $this->debugMode = (bool) config('app.debug');
        $this->searchDriver = config('search.default', 'manticore');
        $this->searchEnabled = in_array($this->searchDriver, ['manticore', 'elasticsearch']);
        $this->renameMusicMediaInfo = (bool) config('nntmux.rename_music_mediainfo');
        $this->renamePar2 = (bool) config('nntmux.rename_par2');
        $this->releaseProcessingTimeout = (int) (app(ConfigurationProvider::class)->postProcessing()->releaseTimeoutSeconds ?: 120);
        $this->maxPpTimeoutCount = (int) (app(ConfigurationProvider::class)->postProcessing()->maxTimeoutCount ?: 3);
        // Regex patterns
        $this->audioFileRegex = '\\.(AAC|AIFF|APE|AC3|ASF|DTS|FLAC|MKA|MKS|MP2|MP3|RA|OGG|OGM|W64|WAV|WMA)';
        $this->ignoreBookRegex = '/\\b(epub|lit|mobi|pdf|sipdf|html)\\b.*\\.rar(?!.{20,})/i';
        $this->supportFileRegex = '\\.(?:vol\\d{1,3}\\+\\d{1,3}|par2|srs|sfv|nzb)';
        $this->videoFileRegex = '\\.(AVI|F4V|IFO|M1V|M2V|M4V|MKV|MOV|MP4|MPEG|MPG|MPGV|MPV|OGV|QT|RM|RMVB|TS|VOB|WMV)';
    }

    /**
     * Use the configured ffprobe, or the one installed next to ffmpeg.
     */
    public static function resolveFfprobePath(string|false $ffprobePath, string|false $ffmpegPath): string|false
    {
        if ($ffprobePath !== false && is_executable($ffprobePath)) {
            return $ffprobePath;
        }

        if ($ffmpegPath !== false) {
            $sibling = dirname($ffmpegPath).DIRECTORY_SEPARATOR.'ffprobe';
            if (is_executable($sibling)) {
                return $sibling;
            }
        }

        return $ffprobePath;
    }

    /**
     * Build the kill string for timeout command wrapper.
     */
    public function getKillString(): string
    {
        if ($this->timeoutPath && $this->timeoutSeconds > 0) {
            return '"'.$this->timeoutPath.'" --foreground --signal=KILL '.$this->timeoutSeconds.' "';
        }

        return '"';
    }
}

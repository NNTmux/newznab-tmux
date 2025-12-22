<?php
namespace App\Services\AdditionalProcessing\Config;
use App\Models\Settings;
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
    public int $maxSizeGB;
    public int $minSizeMB;
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
    public bool $elasticsearchEnabled;
    public bool $renameMusicMediaInfo;
    public bool $renamePar2;
    public string|false $ffmpegPath;
    public string|false $mediaInfoPath;
    // Regex patterns
    public string $audioFileRegex;
    public string $ignoreBookRegex;
    public string $supportFileRegex;
    public string $videoFileRegex;
    public function __construct()
    {
        $this->echoCLI = (bool) config('nntmux.echocli');
        $innerFileBlacklistValue = Settings::settingValue('innerfileblacklist');
        $this->innerFileBlacklist = ($innerFileBlacklistValue === '' || $innerFileBlacklistValue === null)
            ? false
            : $innerFileBlacklistValue;
        $this->maxNestedLevels = (int) Settings::settingValue('maxnestedlevels') ?: 3;
        $this->extractUsingRarInfo = (int) Settings::settingValue('extractusingrarinfo') !== 0;
        $this->fetchLastFiles = (bool) config('nntmux_settings.fetch_last_file');
        $this->unrarPath = config('nntmux_settings.unrar_path') ?: false;
        $this->unzipPath = config('nntmux_settings.unzip_path') ?: false;
        $this->sevenZipPath = config('nntmux_settings.7zip_path') ?: false;
        $this->timeoutPath = config('nntmux_settings.timeout_path') ?: false;
        $this->timeoutSeconds = (int) Settings::settingValue('timeoutseconds');
        $this->queryLimit = (int) (Settings::settingValue('maxaddprocessed') ?: 25);
        $this->segmentsToDownload = (int) (Settings::settingValue('segmentstodownload') ?: 2);
        $this->maximumRarSegments = (int) (Settings::settingValue('maxpartsprocessed') ?: 3);
        $this->maximumRarPasswordChecks = max((int) (Settings::settingValue('passchkattempts') ?: 1), 1);
        $this->maxSizeGB = (int) (Settings::settingValue('maxsizetopostprocess') ?: 100);
        $this->minSizeMB = (int) (Settings::settingValue('minsizetopostprocess') ?: 100);
        $this->alternateNNTP = (bool) config('nntmux_nntp.use_alternate_nntp_server');
        $this->ffmpegDuration = (int) (Settings::settingValue('ffmpeg_duration') ?: 5);
        $this->addPAR2Files = (bool) config('nntmux_settings.add_par2');
        $this->ffmpegPath = config('nntmux_settings.ffmpeg_path') ?: false;
        $this->mediaInfoPath = config('nntmux_settings.mediainfo_path') ?: false;
        if (! $this->ffmpegPath) {
            $this->processAudioSample = false;
            $this->processThumbnails = false;
            $this->processVideo = false;
        } else {
            $this->processAudioSample = (int) Settings::settingValue('saveaudiopreview') !== 0;
            $this->processThumbnails = (int) Settings::settingValue('processthumbnails') !== 0;
            $this->processVideo = (int) Settings::settingValue('processvideos') !== 0;
        }
        $this->processJPGSample = (int) Settings::settingValue('processjpg') !== 0;
        $this->processMediaInfo = (bool) $this->mediaInfoPath;
        $this->processAudioInfo = $this->processMediaInfo;
        $this->processPasswords = config('nntmux_settings.check_passworded_rars') === true
            && ! empty(config('nntmux_settings.unrar_path'));
        $this->audioSavePath = config('nntmux_settings.covers_path').'/audiosample/';
        $this->tmpUnrarPath = config('nntmux.tmp_unrar_path');
        $this->debugMode = (bool) config('app.debug');
        $this->elasticsearchEnabled = config('nntmux.elasticsearch_enabled') === true;
        $this->renameMusicMediaInfo = (bool) config('nntmux.rename_music_mediainfo');
        $this->renamePar2 = (bool) config('nntmux.rename_par2');
        // Regex patterns
        $this->audioFileRegex = '\\.(AAC|AIFF|APE|AC3|ASF|DTS|FLAC|MKA|MKS|MP2|MP3|RA|OGG|OGM|W64|WAV|WMA)';
        $this->ignoreBookRegex = '/\\b(epub|lit|mobi|pdf|sipdf|html)\\b.*\\.rar(?!.{20,})/i';
        $this->supportFileRegex = '\\.(?:vol\\d{1,3}\\+\\d{1,3}|par2|srs|sfv|nzb)';
        $this->videoFileRegex = '\\.(AVI|F4V|IFO|M1V|M2V|M4V|MKV|MOV|MP4|MPEG|MPG|MPGV|MPV|OGV|QT|RM|RMVB|TS|VOB|WMV)';
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

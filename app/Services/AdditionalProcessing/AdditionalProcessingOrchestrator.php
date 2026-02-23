<?php

namespace App\Services\AdditionalProcessing;

use App\Models\Release;
use App\Models\ReleaseFile;
use App\Models\UsenetGroup;
use App\Services\AdditionalProcessing\Config\ProcessingConfiguration;
use App\Services\AdditionalProcessing\DTO\ReleaseProcessingContext;
use App\Services\Releases\ReleaseBrowseService;
use App\Services\TempWorkspaceService;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Main orchestrator for additional release post-processing.
 * Coordinates all processing services to handle NZB parsing, archive extraction,
 * media processing, and release updates.
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

    /**
     * @var array<string, mixed>
     */
    private array $triedCompressedMids = [];

    public function __construct(
        private readonly ProcessingConfiguration $config,
        private readonly NzbContentParser $nzbParser,
        private readonly ArchiveExtractionService $archiveService,
        private readonly MediaExtractionService $mediaService,
        private readonly UsenetDownloadService $downloadService,
        private readonly ReleaseFileManager $releaseManager,
        private readonly TempWorkspaceService $tempWorkspace,
        private readonly ConsoleOutputService $output
    ) {}

    /**
     * Start the additional processing.
     *
     * @throws Exception
     */
    public function start(string $groupID = '', string $guidChar = ''): void
    {
        $this->setupTempPath($guidChar, $groupID);
        $this->fetchReleases($groupID, $guidChar);

        if ($this->totalReleases > 0) {
            $this->output->echoDescription($this->totalReleases);
            $this->processReleases();
        }
    }

    /**
     * Process a single release by GUID.
     */
    public function processSingleGuid(string $guid): bool
    {
        try {
            $release = Release::where('guid', $guid)->first();
            if ($release === null) {
                $this->output->warning('Release not found for GUID: '.$guid);

                return false;
            }

            $this->releases = collect([$release]);
            $this->totalReleases = 1;
            $guidChar = $release->leftguid ?? substr($release->guid, 0, 1);
            $groupID = '';
            $this->setupTempPath($guidChar, $groupID);
            $this->processReleases();

            return true;
        } catch (\Throwable $e) {
            if ($this->config->debugMode) {
                Log::error('processSingleGuid failed: '.$e->getMessage());
            }

            return false;
        }
    }

    /**
     * Set up the main temp path.
     */
    private function setupTempPath(string &$guidChar, string &$groupID): void
    {
        $this->mainTmpPath = $this->tempWorkspace->ensureMainTempPath(
            $this->config->tmpUnrarPath,
            $guidChar,
            $groupID
        );
        $this->tempWorkspace->clearDirectory($this->mainTmpPath, true);
    }

    /**
     * Fetch releases for processing.
     */
    private function fetchReleases(int|string $groupID, string $guidChar): void
    {
        $sqlParts = [];
        $bindings = [];

        $sqlParts[] = 'SELECT r.id AS id, r.id AS releases_id, r.guid, r.name, r.size, r.groups_id, r.nfostatus, r.fromname,';
        $sqlParts[] = 'r.completion, r.categories_id, r.searchname, r.predb_id, r.pp_timeout_count, c.disablepreview';
        $sqlParts[] = 'FROM releases r';
        $sqlParts[] = 'LEFT JOIN categories c ON c.id = r.categories_id';
        $sqlParts[] = 'WHERE r.passwordstatus = ?';
        $bindings[] = -1;
        $sqlParts[] = 'AND r.nzbstatus = ?';
        $bindings[] = 1;
        $sqlParts[] = 'AND r.haspreview = ?';
        $bindings[] = -1;
        $sqlParts[] = 'AND c.disablepreview = ?';
        $bindings[] = 0;

        if ($this->config->maxSizeGB > 0) {
            $sqlParts[] = 'AND r.size < ?';
            $bindings[] = $this->config->maxSizeGB * 1073741824;
        }
        if ($this->config->minSizeMB > 0) {
            $sqlParts[] = 'AND r.size > ?';
            $bindings[] = $this->config->minSizeMB * 1048576;
        }
        if (! empty($groupID)) {
            $sqlParts[] = 'AND r.groups_id = ?';
            $bindings[] = $groupID;
        }
        if (! empty($guidChar)) {
            $sqlParts[] = 'AND r.leftguid = ?';
            $bindings[] = $guidChar;
        }

        $sqlParts[] = 'ORDER BY r.passwordstatus ASC, r.postdate DESC';
        $limit = $this->config->queryLimit > 0 ? $this->config->queryLimit : 25;
        $sqlParts[] = 'LIMIT '.$limit;

        $rawResults = DB::select(implode(' ', $sqlParts), $bindings);
        $attributeArrays = array_map(static fn ($row) => (array) $row, $rawResults);
        $this->releases = Release::hydrate($attributeArrays);
        $this->totalReleases = $this->releases->count();
    }

    /**
     * Process all fetched releases.
     *
     * @throws Exception
     */
    private function processReleases(): void
    {
        foreach ($this->releases as $release) {
            $context = new ReleaseProcessingContext($release);

            $this->output->echoReleaseStart($release->id, $release->size);
            cli_set_process_title(PHP_BINARY.' '.__DIR__.'/AdditionalProcessingOrchestrator.php ReleaseID: '.$release->id);

            // Create temp folder for this release
            try {
                $context->tmpPath = $this->tempWorkspace->createReleaseTempFolder($this->mainTmpPath, $release->guid);
            } catch (\Throwable $e) {
                $this->output->warning('Unable to create directory: '.$e->getMessage());

                continue;
            }

            // Parse NZB contents
            $nzbResult = $this->nzbParser->parseNzb($release->guid);
            if ($nzbResult['error'] !== null) {
                $this->output->warning($nzbResult['error']);
                $this->releaseManager->deleteRelease($release);

                continue;
            }
            $context->nzbContents = $nzbResult['contents'];

            // Check timeout after NZB parsing
            if ($this->checkReleaseTimeout($context)) {
                continue;
            }

            // Initialize context
            $this->initializeContext($context);

            // Extract message IDs from NZB
            $messageIds = $this->nzbParser->extractMessageIDs(
                $context->nzbContents,
                $context->releaseGroupName,
                $this->config->segmentsToDownload,
                $this->config->processThumbnails,
                $this->config->processJPGSample,
                $this->config->processMediaInfo,
                $this->config->processAudioInfo,
                $this->config->audioFileRegex,
                $this->config->videoFileRegex,
                $this->config->supportFileRegex,
                $this->config->ignoreBookRegex
            );

            $context->nzbHasCompressedFile = $messageIds['hasCompressedFile'];
            $context->sampleMessageIDs = $messageIds['sampleMessageIDs'];
            $context->jpgMessageIDs = $messageIds['jpgMessageIDs'];
            $context->mediaInfoMessageIDs = $messageIds['mediaInfoMessageID'];
            $context->audioInfoMessageIDs = $messageIds['audioInfoMessageID'];
            $context->audioInfoExtension = $messageIds['audioInfoExtension'];

            // Check for book flood
            $bookFlood = $messageIds['bookFileCount'] > 80
                && ($messageIds['bookFileCount'] * 2) >= count($context->nzbContents);

            // Process message ID downloads
            if ($this->config->processPasswords || $this->config->processThumbnails
                || $this->config->processMediaInfo || $this->config->processAudioInfo
                || $this->config->processVideo || $this->config->processJPGSample
            ) {
                $this->processMessageIDDownloads($context);

                // Check timeout after message ID downloads
                if ($this->checkReleaseTimeout($context)) {
                    continue;
                }

                // Process compressed files
                if (! $bookFlood && $context->nzbHasCompressedFile) {
                    $this->processNZBCompressedFiles($context, false);

                    // Check timeout after compressed file processing
                    if ($this->checkReleaseTimeout($context)) {
                        continue;
                    }

                    if ($this->config->fetchLastFiles) {
                        $this->processNZBCompressedFiles($context, true);

                        // Check timeout after last files processing
                        if ($this->checkReleaseTimeout($context)) {
                            continue;
                        }
                    }

                    if (! $context->releaseHasPassword) {
                        $this->processExtractedFiles($context);

                        // Check timeout after extracted file processing
                        if ($this->checkReleaseTimeout($context)) {
                            continue;
                        }
                    }
                }

                // If still no JPG sample, try to fetch JPG from release_files entries
                if (! $context->foundJPGSample && $this->config->processJPGSample) {
                    $this->processJpgFromReleaseFiles($context);
                }
            }

            // Finalize
            $this->releaseManager->finalizeRelease($context, $this->config->processPasswords);

            // Cleanup
            $this->tempWorkspace->clearDirectory($context->tmpPath, false);
        }

        $this->output->endOutput();
    }

    /**
     * Check if the current release has exceeded the processing timeout.
     * If timed out, handles cleanup, logs the event, and marks the release accordingly.
     *
     * @return bool True if the release timed out and should be skipped (caller should `continue`)
     */
    private function checkReleaseTimeout(ReleaseProcessingContext $context): bool
    {
        if (! $context->isTimedOut($this->config->releaseProcessingTimeout)) {
            return false;
        }

        $elapsedSeconds = $context->getElapsedSeconds();

        // Handle the timeout: increment counter, skip or delete
        $deleted = $this->releaseManager->handleReleaseTimeout(
            $context->release,
            $this->config->maxPpTimeoutCount
        );

        if ($deleted) {
            $this->output->echoReleaseTimeoutDeleted(
                $context->release->id,
                (int) ($context->release->pp_timeout_count ?? 0) + 1
            );
        } else {
            $this->output->echoReleaseTimeout($context->release->id, $elapsedSeconds);
        }

        // Cleanup temp directory
        if (! empty($context->tmpPath)) {
            $this->tempWorkspace->clearDirectory($context->tmpPath, false);
        }

        return true;
    }

    /**
     * Initialize the processing context.
     */
    private function initializeContext(ReleaseProcessingContext $context): void
    {
        $context->initializeFromConfig(
            $this->config->processVideo,
            $this->config->processMediaInfo,
            $this->config->processAudioInfo,
            $this->config->processAudioSample,
            $this->config->processJPGSample,
            $this->config->processThumbnails
        );

        $context->passwordStatus = ReleaseBrowseService::PASSWD_NONE;
        $context->releaseHasPassword = false;
        $context->releaseGroupName = UsenetGroup::getNameByID($context->release->groups_id);
        $context->releaseHasNoNFO = (int) $context->release->nfostatus !== 1;
        $context->resetMessageIDs();
        $context->resetCounters();
    }

    /**
     * Process message ID downloads (sample, media info, audio, JPG).
     */
    private function processMessageIDDownloads(ReleaseProcessingContext $context): void
    {
        // Sample download
        if ((! $context->foundSample || ! $context->foundVideo) && ! empty($context->sampleMessageIDs)) {
            $result = $this->downloadService->downloadSample(
                $context->sampleMessageIDs,
                $context->releaseGroupName,
                $context->release->id
            );

            if ($result['success'] && $this->downloadService->meetsMinimumSize($result['data'])) {
                $this->output->echoSampleDownload();
                $fileLocation = $context->tmpPath.'sample_'.random_int(0, 99999).'.avi';
                File::put($fileLocation, $result['data']);

                if (! $context->foundSample) {
                    if ($this->mediaService->getSample($fileLocation, $context->tmpPath, $context->release->guid)) {
                        $context->foundSample = true;
                        $this->output->echoSampleCreated();
                    }
                }
                if (! $context->foundVideo) {
                    if ($this->mediaService->getVideo($fileLocation, $context->tmpPath, $context->release->guid)) {
                        $context->foundVideo = true;
                        $this->output->echoVideoCreated();
                    }
                }
            } elseif (! $result['success']) {
                $this->output->echoSampleFailure();
            }
        }

        // Media info download
        if ((! $context->foundMediaInfo || ! $context->foundSample || ! $context->foundVideo)
            && ! empty($context->mediaInfoMessageIDs)
        ) {
            $result = $this->downloadService->downloadMediaInfo(
                $context->mediaInfoMessageIDs,
                $context->releaseGroupName,
                $context->release->id
            );

            if ($result['success'] && $this->downloadService->meetsMinimumSize($result['data'])) {
                $this->output->echoMediaInfoDownload();
                $fileLocation = $context->tmpPath.'media.avi';
                File::put($fileLocation, $result['data']);

                if (! $context->foundMediaInfo) {
                    if ($this->mediaService->getMediaInfo($fileLocation, $context->release->id)) {
                        $context->foundMediaInfo = true;
                        $this->output->echoMediaInfoAdded();
                    }
                }
                if (! $context->foundSample) {
                    if ($this->mediaService->getSample($fileLocation, $context->tmpPath, $context->release->guid)) {
                        $context->foundSample = true;
                        $this->output->echoSampleCreated();
                    }
                }
                if (! $context->foundVideo) {
                    if ($this->mediaService->getVideo($fileLocation, $context->tmpPath, $context->release->guid)) {
                        $context->foundVideo = true;
                        $this->output->echoVideoCreated();
                    }
                }
            } elseif (! $result['success']) {
                $this->output->echoMediaInfoFailure();
            }
        }

        // Audio info download
        if ((! $context->foundAudioInfo || ! $context->foundAudioSample)
            && ! empty($context->audioInfoMessageIDs)
        ) {
            $result = $this->downloadService->downloadAudio(
                $context->audioInfoMessageIDs,
                $context->releaseGroupName,
                $context->release->id
            );

            if ($result['success']) {
                $this->output->echoAudioDownload();
                $fileLocation = $context->tmpPath.'audio.'.$context->audioInfoExtension;
                File::put($fileLocation, $result['data']);

                $audioResult = $this->mediaService->getAudioInfo(
                    $fileLocation,
                    $context->audioInfoExtension,
                    $context,
                    $context->tmpPath
                );

                if ($audioResult['audioInfo']) {
                    $this->output->echoAudioInfoAdded();
                }
                if ($audioResult['audioSample']) {
                    $this->output->echoAudioSampleCreated();
                }
            } elseif (! $result['success']) {
                $this->output->echoAudioFailure();
            }
        }

        // JPG download
        if (! $context->foundJPGSample && ! empty($context->jpgMessageIDs)) {
            $result = $this->downloadService->downloadJPG(
                $context->jpgMessageIDs,
                $context->releaseGroupName,
                $context->release->id
            );

            if ($result['success']) {
                $this->output->echoJpgDownload();
                $fileLocation = $context->tmpPath.'samplepicture.jpg';
                File::put($fileLocation, $result['data']);

                if ($this->mediaService->isJpegData($fileLocation)) {
                    if ($this->mediaService->getJPGSample($fileLocation, $context->release->guid)) {
                        $context->foundJPGSample = true;
                        $this->output->echoJpgSaved();
                    }
                }
                File::delete($fileLocation);
            } elseif (! $result['success']) {
                $this->output->echoJpgFailure();
            }
        }
    }

    /**
     * Process compressed files in the NZB.
     */
    private function processNZBCompressedFiles(ReleaseProcessingContext $context, bool $reverse): void
    {
        if ($context->groupUnavailable) {
            return;
        }

        $nzbContents = $context->nzbContents;
        if ($reverse) {
            krsort($nzbContents);
        } else {
            $this->triedCompressedMids = [];
        }

        $failed = $downloaded = 0;

        foreach ($nzbContents as $nzbFile) {
            if ($downloaded >= $this->config->maximumRarSegments) {
                break;
            }
            if ($failed >= $this->config->maximumRarPasswordChecks) {
                break;
            }
            if ($context->releaseHasPassword || $context->groupUnavailable) { // @phpstan-ignore booleanOr.rightAlwaysFalse
                break;
            }

            $title = $nzbFile['title'] ?? '';
            if (! preg_match(
                '/(\\.(part\\d+|[rz]\\d+|rar|0+|0*10?|zipr\\d{2,3}|zipx?)(\\s*\\.rar)*($|[ ")]|-])|"[a-f0-9]{32}\\.[1-9]\\d{1,2}".*\\(\\d+\\/\\d{2,}\\)$)/i',
                $title
            )) {
                continue;
            }

            // Get message IDs
            $segments = $nzbFile['segments'] ?? [];
            $segCount = count($segments) - 1;
            $messageIDs = [];

            foreach (range(0, $this->config->maximumRarSegments - 1) as $i) {
                if ($i > $segCount) {
                    break;
                }
                $segment = (string) $segments[$i];
                if (! $reverse) {
                    $this->triedCompressedMids[] = $segment;
                } elseif (in_array($segment, $this->triedCompressedMids, false)) {
                    continue 2;
                }
                $messageIDs[] = $segment;
            }

            if (empty($messageIDs)) {
                continue;
            }

            // Download
            $result = $this->downloadService->downloadCompressedFile(
                $messageIDs, // @phpstan-ignore argument.type
                $context->releaseGroupName,
                $context->release->id,
                $title
            );

            if ($result['groupUnavailable']) {
                $context->groupUnavailable = true;
                $this->output->echoGroupUnavailable();
                break;
            }

            if ($result['success']) {
                $this->output->echoCompressedDownload();
                $downloaded++;

                $processed = $this->processCompressedData($result['data'], $context, $reverse);
                if ($processed || $context->releaseHasPassword) { // @phpstan-ignore booleanOr.rightAlwaysFalse
                    break;
                }
            } else {
                $failed++;
                $this->output->echoCompressedFailure($failed);
            }
        }
    }

    /**
     * Process compressed data.
     */
    private function processCompressedData(
        string $compressedData,
        ReleaseProcessingContext $context,
        bool $reverse
    ): bool {
        $result = $this->archiveService->processCompressedData(
            $compressedData,
            $context,
            $context->tmpPath
        );

        if ($result['hasPassword']) {
            $context->releaseHasPassword = true;
            $context->passwordStatus = $result['passwordStatus'];

            return false;
        }

        // Handle standalone video
        if (isset($result['standaloneVideoType'])) {
            $this->output->echoInlineVideo();
            $ext = $result['standaloneVideoType'];
            $fileLocation = $context->tmpPath.'inline_video_'.uniqid('', true).'.'.$ext;
            File::put($fileLocation, $result['standaloneVideoData']);

            if (! $context->foundMediaInfo) {
                if ($this->mediaService->getMediaInfo($fileLocation, $context->release->id)) {
                    $context->foundMediaInfo = true;
                    $this->output->echoMediaInfoAdded();
                }
            }
            if (! $context->foundSample) {
                if ($this->mediaService->getSample($fileLocation, $context->tmpPath, $context->release->guid)) {
                    $context->foundSample = true;
                    $this->output->echoSampleCreated();
                }
            }
            if (! $context->foundVideo) {
                if ($this->mediaService->getVideo($fileLocation, $context->tmpPath, $context->release->guid)) {
                    $context->foundVideo = true;
                    $this->output->echoVideoCreated();
                }
            }

            return $context->foundMediaInfo || $context->foundSample || $context->foundVideo;
        }

        if (! $result['success']) {
            return false;
        }

        // Echo archive marker
        if (! empty($result['archiveMarker'])) {
            $this->output->echoArchiveMarker($result['archiveMarker']);
        }

        // Handle reverse processing for release name extraction
        if ($reverse && ! empty($result['dataSummary'])) {
            $this->releaseManager->processReleaseNameFromRar($result['dataSummary'], $context);
        }

        // Process file list
        foreach ($result['files'] as $file) {
            if ($context->releaseHasPassword) {
                break;
            }

            if ($this->releaseManager->addFileInfo($file, $context, $this->config->supportFileRegex)) {
                $this->output->echoFileInfoAdded();
            }
        }

        if ($context->addedFileInfo > 0) {
            $this->releaseManager->updateSearchIndex($context->release->id);
        }

        // Try to extract and process JPG files from the archive file list for sample/preview
        if (! $context->foundJPGSample && $this->config->processJPGSample) {
            $this->processJpgFromArchiveFileList($compressedData, $result['files'], $context);
        }

        return $context->totalFileInfo > 0;
    }

    /**
     * Find and process JPG/PNG files from archive file list to create sample/preview.
     *
     * @param  array<string, mixed>  $files
     */
    private function processJpgFromArchiveFileList(
        string $compressedData,
        array $files,
        ReleaseProcessingContext $context
    ): void {
        // Find image files (JPG/PNG) in the archive file list
        $imageFiles = [];
        foreach ($files as $file) {
            $name = $file['name'] ?? '';
            if (preg_match('/\.(jpe?g|png)$/i', $name)) {
                $imageFiles[] = $file;
            }
        }

        if (empty($imageFiles)) {
            return;
        }

        // Sort by size (prefer larger images, likely better quality) - limit to first 3
        usort($imageFiles, fn ($a, $b) => ($b['size'] ?? 0) <=> ($a['size'] ?? 0));
        $imageFiles = array_slice($imageFiles, 0, 3);

        foreach ($imageFiles as $imageFile) {
            $imageFilename = $imageFile['name'];

            // Try to extract this specific image from the archive
            $imageData = $this->archiveService->extractSpecificFile(
                $compressedData,
                $imageFilename,
                $context->tmpPath
            );

            if ($imageData === null || empty($imageData)) {
                continue;
            }

            // Save to temp file and validate it's actually a valid image
            $ext = strtolower(pathinfo($imageFilename, PATHINFO_EXTENSION));
            $tempImagePath = $context->tmpPath.'extracted_'.uniqid('', true).'.'.$ext;
            File::put($tempImagePath, $imageData);

            if ($this->mediaService->isValidImage($tempImagePath)) {
                if ($this->mediaService->getJPGSample($tempImagePath, $context->release->guid)) {
                    $context->foundJPGSample = true;
                    $this->output->echoJpgSaved();
                    File::delete($tempImagePath);
                    break;
                }
            }

            File::delete($tempImagePath);
        }
    }

    /**
     * Process JPG/PNG files from existing release_files entries.
     * This fetches the archive from NZB and extracts image files that were previously listed.
     */
    private function processJpgFromReleaseFiles(ReleaseProcessingContext $context): void
    {
        // Get JPG/PNG files from release_files table for this release
        $imageFiles = ReleaseFile::where('releases_id', $context->release->id)
            ->where(function ($query) {
                $query->where('name', 'like', '%.jpg')
                    ->orWhere('name', 'like', '%.jpeg')
                    ->orWhere('name', 'like', '%.png');
            })
            ->orderByDesc('size')
            ->limit(3)
            ->get();

        if ($imageFiles->isEmpty()) {
            return;
        }

        // We need to download a compressed file from the NZB to extract the JPG
        if (empty($context->nzbContents)) {
            return;
        }

        // Find compressed files in NZB
        foreach ($context->nzbContents as $nzbFile) {
            if ($context->foundJPGSample) {
                break;
            }

            $title = $nzbFile['title'] ?? '';
            if (! preg_match(
                '/(\\.(part0*1|rar|zip))(\\s*\\.rar)*($|[ ")]|-])|"[a-f0-9]{32}\\.[1-9]\\d{1,2}".*\\(\\d+\\/\\d{2,}\\)$/i',
                $title
            )) {
                continue;
            }

            // Get message IDs for first few segments
            $segments = $nzbFile['segments'] ?? [];
            if (empty($segments)) {
                continue;
            }

            $messageIDs = [];
            $segCount = min(count($segments), $this->config->maximumRarSegments);
            for ($i = 0; $i < $segCount; $i++) {
                $messageIDs[] = (string) $segments[$i];
            }

            if (empty($messageIDs)) {
                continue;
            }

            // Download the compressed file
            $result = $this->downloadService->downloadCompressedFile(
                $messageIDs, // @phpstan-ignore argument.type
                $context->releaseGroupName,
                $context->release->id,
                $title
            );

            if (! $result['success'] || empty($result['data'])) {
                continue;
            }

            // Try to extract each image file from the archive
            foreach ($imageFiles as $imageFile) {
                $imageData = $this->archiveService->extractSpecificFile(
                    $result['data'],
                    $imageFile->name,
                    $context->tmpPath
                );

                if ($imageData === null || empty($imageData)) {
                    continue;
                }

                // Save to temp file and validate
                $ext = strtolower(pathinfo($imageFile->name, PATHINFO_EXTENSION));
                $tempImagePath = $context->tmpPath.'release_file_'.uniqid('', true).'.'.$ext;
                File::put($tempImagePath, $imageData);

                if ($this->mediaService->isValidImage($tempImagePath)) {
                    if ($this->mediaService->getJPGSample($tempImagePath, $context->release->guid)) {
                        $context->foundJPGSample = true;
                        $this->output->echoJpgSaved();
                        File::delete($tempImagePath);
                        break 2; // Exit both loops
                    }
                }

                File::delete($tempImagePath);
            }
        }
    }

    /**
     * Process extracted files from archives.
     */
    private function processExtractedFiles(ReleaseProcessingContext $context): void
    {
        $nestedLevels = 0;

        // Handle nested archives
        while ($nestedLevels < $this->config->maxNestedLevels) {
            if ($context->compressedFilesChecked >= self::MAX_COMPRESSED_FILES_TO_CHECK) {
                break;
            }

            $foundCompressed = false;
            $pattern = '/.*\.([rz]\d{2,}|rar|zipx?|0{0,2}1)($|[^a-z0-9])/i';

            try {
                $files = $this->tempWorkspace->listFiles($context->tmpPath, $pattern);
            } catch (\Throwable) {
                break;
            }

            foreach ($files as $file) {
                $filePath = is_array($file) ? $file[0] : $file->getPathname();
                if (File::isFile($filePath)) {
                    $rarData = @File::get($filePath);
                    if (! empty($rarData)) {
                        $this->processCompressedData($rarData, $context, false);
                        $foundCompressed = true;
                    }
                    File::delete($filePath);
                }
            }

            if (! $foundCompressed) {
                break;
            }
            $nestedLevels++;
        }

        // Process remaining files
        try {
            $files = $this->tempWorkspace->listFiles($context->tmpPath);
        } catch (\Throwable) {
            return;
        }

        foreach ($files as $file) {
            $filePath = is_object($file) ? $file->getPathname() : $file;

            if (preg_match('/[\/\\\\]\.{1,2}$/', $filePath)) {
                continue;
            }

            if (! File::isFile($filePath)) {
                continue;
            }

            // PAR2 files
            if (! $context->foundPAR2Info && preg_match('/\.par2$/i', $filePath)) {
                $this->releaseManager->processPar2File(
                    $filePath,
                    $context,
                    $this->archiveService->getPar2Info()
                );

                continue;
            }

            // NFO files - enhanced detection with multiple patterns
            if ($context->releaseHasNoNFO) {
                // Standard NFO extensions
                if (preg_match('/(\.(nfo|inf|ofn|diz)|info\.txt)$/i', $filePath)) {
                    if ($this->releaseManager->processNfoFile($filePath, $context, $this->downloadService->getNNTP())) {
                        $this->output->echoNfoFound();
                    }

                    continue;
                }
                // Alternative NFO filenames (file_id.diz, readme.txt, etc.)
                elseif ($this->releaseManager->isNfoFilename($filePath)) {
                    if ($this->releaseManager->processNfoFile($filePath, $context, $this->downloadService->getNNTP())) {
                        $this->output->echoNfoFound();
                    }

                    continue;
                }
            }

            // Audio files
            if ((! $context->foundAudioInfo || ! $context->foundAudioSample)
                && preg_match('/(.*)'.$this->config->audioFileRegex.'$/i', $filePath, $fileType)
            ) {
                $audioPath = $context->tmpPath.'audiofile.'.$fileType[2];
                File::move($filePath, $audioPath);
                $audioResult = $this->mediaService->getAudioInfo(
                    $audioPath,
                    $fileType[2],
                    $context,
                    $context->tmpPath
                );
                if ($audioResult['audioInfo']) {
                    $this->output->echoAudioInfoAdded();
                }
                if ($audioResult['audioSample']) {
                    $this->output->echoAudioSampleCreated();
                }
                File::delete($audioPath);

                continue;
            }

            // JPG/PNG image files
            if (! $context->foundJPGSample && preg_match('/\.(jpe?g|png)$/i', $filePath)) {
                if ($this->mediaService->getJPGSample($filePath, $context->release->guid)) {
                    $context->foundJPGSample = true;
                    $this->output->echoJpgSaved();
                }
                File::delete($filePath);

                continue;
            }

            // Video files
            if ((! $context->foundSample || ! $context->foundVideo || ! $context->foundMediaInfo)
                && preg_match('/(.*)'.$this->config->videoFileRegex.'$/i', $filePath)
            ) {
                $this->mediaService->processVideoFile($filePath, $context, $context->tmpPath);

                continue;
            }

            // Check file magic
            $output = fileInfo($filePath);
            if (empty($output)) {
                continue;
            }

            if (! $context->foundJPGSample && preg_match('/^JPE?G|^PNG/i', $output)) {
                if ($this->mediaService->getJPGSample($filePath, $context->release->guid)) {
                    $context->foundJPGSample = true;
                    $this->output->echoJpgSaved();
                }
                File::delete($filePath);
            } elseif ((! $context->foundMediaInfo || ! $context->foundSample || ! $context->foundVideo)
                && preg_match('/Matroska data|MPEG v4|MPEG sequence, v2|\WAVI\W/i', $output)
            ) {
                $this->mediaService->processVideoFile($filePath, $context, $context->tmpPath);
            } elseif ((! $context->foundAudioSample || ! $context->foundAudioInfo)
                && preg_match('/^FLAC|layer III|Vorbis audio/i', $output, $audioType)
            ) {
                $ext = match ($audioType[0]) {
                    'FLAC' => 'FLAC',
                    'layer III' => 'MP3',
                    'Vorbis audio' => 'OGG',
                    default => 'audio',
                };
                $audioPath = $context->tmpPath.'audiofile.'.$ext;
                File::move($filePath, $audioPath);
                $audioResult = $this->mediaService->getAudioInfo($audioPath, $ext, $context, $context->tmpPath);
                if ($audioResult['audioInfo']) {
                    $this->output->echoAudioInfoAdded();
                }
                if ($audioResult['audioSample']) {
                    $this->output->echoAudioSampleCreated();
                }
                File::delete($audioPath);
            } elseif (! $context->foundPAR2Info && stripos($output, 'Parity') === 0) {
                $this->releaseManager->processPar2File(
                    $filePath,
                    $context,
                    $this->archiveService->getPar2Info()
                );
            }
        }
    }

    /**
     * Clear the main temp path.
     */
    public function __destruct()
    {
        if ($this->mainTmpPath !== '') {
            $this->tempWorkspace->clearDirectory($this->mainTmpPath, true);
        }
    }
}

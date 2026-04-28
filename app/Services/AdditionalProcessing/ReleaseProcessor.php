<?php

declare(strict_types=1);

namespace App\Services\AdditionalProcessing;

use App\Models\UsenetGroup;
use App\Services\AdditionalProcessing\Config\ProcessingConfiguration;
use App\Services\AdditionalProcessing\State\ReleaseProcessingContext;
use App\Services\AdditionalProcessing\Enums\DownloadKind;
use App\Services\Releases\ReleaseBrowseService;
use App\Services\TempWorkspaceService;
use Illuminate\Support\Facades\File;

/**
 * Stateless release processor for additional post-processing.
 */
class ReleaseProcessor
{
    public function __construct(
        private readonly ProcessingConfiguration $config,
        private readonly NzbContentParser $nzbParser,
        private readonly ArchiveExtractionService $archiveService,
        private readonly MediaExtractionService $mediaService,
        private readonly UsenetDownloadService $downloadService,
        private readonly ReleaseFileManager $releaseManager,
        private readonly ReleaseFilesArchiveFallback $archiveFallback,
        private readonly TempWorkspaceService $tempWorkspace,
        private readonly ConsoleOutputService $output
    ) {}

    public function process(ReleaseProcessingContext $context, string $mainTmpPath): void
    {
        $release = $context->release;

        $this->output->echoReleaseStart($release->id, $release->size);
        $this->output->setProcessTitle((int) $release->id);

        try {
            $context->tmpPath = $this->tempWorkspace->createReleaseTempFolder($mainTmpPath, $release->guid);
        } catch (\Throwable $e) {
            $this->output->warning('Unable to create directory: '.$e->getMessage());

            return;
        }

        try {
            $nzbResult = $this->nzbParser->parseNzb($release->guid);
            if ($nzbResult['error'] !== null) {
                $this->output->warning($nzbResult['error']);
                $this->releaseManager->deleteRelease($release);

                return;
            }

            $context->nzbContents = array_values($nzbResult['contents']);
            if ($this->checkReleaseTimeout($context)) {
                return;
            }

            $this->initializeContext($context);
            $this->releaseManager->processReleaseNameFromNzbContents($context->nzbContents, $context);
            $messageIds = $this->nzbParser->extractMessageIDs($context->nzbContents, $context->releaseGroupName, $this->config);

            $context->nzbHasCompressedFile = $messageIds['hasCompressedFile'];
            $context->sampleMessageIDs = $messageIds['sampleMessageIDs'];
            $context->jpgMessageIDs = $messageIds['jpgMessageIDs'];
            $context->mediaInfoMessageIDs = $messageIds['mediaInfoMessageID'];
            $context->audioInfoMessageIDs = $messageIds['audioInfoMessageID'];
            $context->audioInfoExtension = $messageIds['audioInfoExtension'];

            $bookFlood = $messageIds['bookFileCount'] > 80
                && ($messageIds['bookFileCount'] * 2) >= count($context->nzbContents);

            if ($this->shouldProcessDownloads()) {
                $this->processMessageIdDownloads($context);
                if ($this->checkReleaseTimeout($context)) {
                    return;
                }

                if (! $bookFlood && $context->nzbHasCompressedFile) {
                    $triedCompressedMids = [];
                    $this->processNzbCompressedFiles($context, false, $triedCompressedMids);
                    if ($this->checkReleaseTimeout($context)) {
                        return;
                    }

                    if ($this->config->fetchLastFiles) {
                        $this->processNzbCompressedFiles($context, true, $triedCompressedMids);
                        if ($this->checkReleaseTimeout($context)) {
                            return;
                        }
                    }

                    if (! $context->releaseHasPassword) {
                        $this->processExtractedFiles($context);
                        if ($this->checkReleaseTimeout($context)) {
                            return;
                        }
                    }
                }

                if (! $context->foundJPGSample && $this->config->processJPGSample) {
                    $this->archiveFallback->processJpgFromReleaseFiles($context);
                }

                if ($context->releaseHasNoNFO) {
                    $this->archiveFallback->processNfoFromReleaseFiles($context);
                }
            }

            $this->releaseManager->finalizeRelease($context, $this->config->processPasswords);
        } finally {
            if ($context->tmpPath !== '') {
                $this->tempWorkspace->clearDirectory($context->tmpPath, false);
            }
        }
    }

    private function shouldProcessDownloads(): bool
    {
        return $this->config->processPasswords
            || $this->config->processThumbnails
            || $this->config->processMediaInfo
            || $this->config->processAudioInfo
            || $this->config->processVideo
            || $this->config->processJPGSample;
    }

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
        try {
            $context->releaseGroupName = UsenetGroup::getNameByID($context->release->groups_id);
        } catch (\Throwable) {
            $context->releaseGroupName = '';
        }
        $context->releaseHasNoNFO = (int) $context->release->nfostatus !== 1;
        $context->resetMessageIDs();
        $context->resetCounters();
    }

    private function checkReleaseTimeout(ReleaseProcessingContext $context): bool
    {
        if (! $context->isTimedOut($this->config->releaseProcessingTimeout)) {
            return false;
        }

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
            $this->output->echoReleaseTimeout($context->release->id, $context->getElapsedSeconds());
        }

        if ($context->tmpPath !== '') {
            $this->tempWorkspace->clearDirectory($context->tmpPath, false);
        }

        return true;
    }

    private function processMessageIdDownloads(ReleaseProcessingContext $context): void
    {
        if ((! $context->foundSample || ! $context->foundVideo) && $context->sampleMessageIDs !== []) {
            $result = $this->downloadService->download(
                DownloadKind::Sample,
                $context->sampleMessageIDs,
                $context->releaseGroupName,
                $context->release->id
            );

            if ($result['success'] && is_string($result['data']) && $this->downloadService->meetsMinimumSize($result['data'])) {
                $this->output->echoSampleDownload();
                $fileLocation = $context->tmpPath.'sample_'.random_int(0, 99999).'.avi';
                File::put($fileLocation, $result['data']);

                if (! $context->foundSample && $this->mediaService->getSample($fileLocation, $context->tmpPath, $context->release->guid)) {
                    $context->markFound('sample');
                    $this->output->echoSampleCreated();
                }
                if (! $context->foundVideo && $this->mediaService->getVideo($fileLocation, $context->tmpPath, $context->release->guid)) {
                    $context->markFound('video');
                    $this->output->echoVideoCreated();
                }
            } elseif (! $result['success']) {
                $this->output->echoSampleFailure();
            }
        }

        if ((! $context->foundMediaInfo || ! $context->foundSample || ! $context->foundVideo)
            && ! empty($context->mediaInfoMessageIDs)
        ) {
            $result = $this->downloadService->download(
                DownloadKind::MediaInfo,
                $context->mediaInfoMessageIDs,
                $context->releaseGroupName,
                $context->release->id
            );

            if ($result['success'] && is_string($result['data']) && $this->downloadService->meetsMinimumSize($result['data'])) {
                $this->output->echoMediaInfoDownload();
                $fileLocation = $context->tmpPath.'media.avi';
                File::put($fileLocation, $result['data']);

                if (! $context->foundMediaInfo && $this->mediaService->getMediaInfo($fileLocation, $context->release->id)) {
                    $context->markFound('mediaInfo');
                    $this->output->echoMediaInfoAdded();
                }
                if (! $context->foundSample && $this->mediaService->getSample($fileLocation, $context->tmpPath, $context->release->guid)) {
                    $context->markFound('sample');
                    $this->output->echoSampleCreated();
                }
                if (! $context->foundVideo && $this->mediaService->getVideo($fileLocation, $context->tmpPath, $context->release->guid)) {
                    $context->markFound('video');
                    $this->output->echoVideoCreated();
                }
            } elseif (! $result['success']) {
                $this->output->echoMediaInfoFailure();
            }
        }

        if ((! $context->foundAudioInfo || ! $context->foundAudioSample)
            && ! empty($context->audioInfoMessageIDs)
        ) {
            $result = $this->downloadService->download(
                DownloadKind::Audio,
                $context->audioInfoMessageIDs,
                $context->releaseGroupName,
                $context->release->id
            );

            if ($result['success'] && is_string($result['data'])) {
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

        if (! $context->foundJPGSample && $context->jpgMessageIDs !== []) {
            $result = $this->downloadService->download(
                DownloadKind::Jpg,
                $context->jpgMessageIDs,
                $context->releaseGroupName,
                $context->release->id
            );

            if ($result['success'] && is_string($result['data'])) {
                $this->output->echoJpgDownload();
                $fileLocation = $context->tmpPath.'samplepicture.jpg';
                File::put($fileLocation, $result['data']);

                if ($this->mediaService->isJpegData($fileLocation)
                    && $this->mediaService->getJPGSample($fileLocation, $context->release->guid)
                ) {
                    $context->markFound('jpgSample');
                    $this->output->echoJpgSaved();
                }

                File::delete($fileLocation);
            } elseif (! $result['success']) {
                $this->output->echoJpgFailure();
            }
        }
    }

    /**
     * @param  list<string>  $triedCompressedMids
     */
    private function processNzbCompressedFiles(
        ReleaseProcessingContext $context,
        bool $reverse,
        array &$triedCompressedMids
    ): void {
        if ($context->groupUnavailable) {
            return;
        }

        $nzbContents = $context->nzbContents;
        if ($reverse) {
            krsort($nzbContents);
        } else {
            $triedCompressedMids = [];
        }

        $failed = $downloaded = 0;

        foreach ($nzbContents as $nzbFile) {
            if ($downloaded >= $this->config->maximumRarSegments
                || $failed >= $this->config->maximumRarPasswordChecks
                || $context->releaseHasPassword
                || $context->groupUnavailable
            ) {
                break;
            }

            $title = (string) ($nzbFile['title'] ?? '');
            if (preg_match(
                '/(\.(part\d+|[rz]\d+|rar|0+|0*10?|zipr\d{2,3}|zipx?)(\s*\.rar)*($|[ ")]|-])|"[a-f0-9]{32}\.[1-9]\d{1,2}".*\(\d+\/\d{2,}\)$)/i',
                $title
            ) !== 1) {
                continue;
            }

            $segments = $nzbFile['segments'] ?? [];
            $messageIDs = [];
            $segmentCount = count($segments) - 1;

            foreach (range(0, $this->config->maximumRarSegments - 1) as $index) {
                if ($index > $segmentCount) {
                    break;
                }

                $segment = (string) $segments[$index];
                if (! $reverse) {
                    $triedCompressedMids[] = $segment;
                } elseif (in_array($segment, $triedCompressedMids, false)) {
                    continue 2;
                }

                $messageIDs[] = $segment;
            }

            if ($messageIDs === []) {
                continue;
            }

            $result = $this->downloadService->download(
                DownloadKind::Compressed,
                $messageIDs,
                $context->releaseGroupName,
                $context->release->id,
                $title
            );

            if ($result['groupUnavailable']) {
                $context->groupUnavailable = true;
                $this->output->echoGroupUnavailable();
                break;
            }

            if ($result['success'] && is_string($result['data'])) {
                $this->output->echoCompressedDownload();
                $downloaded++;

                $processed = $this->processCompressedData($result['data'], $context, $reverse);
                if ($processed || $context->releaseHasPassword) {
                    break;
                }
            } else {
                $failed++;
                $this->output->echoCompressedFailure($failed);
            }
        }
    }

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

        if (isset($result['standaloneVideoType'])) {
            $this->output->echoInlineVideo();
            $fileLocation = $context->tmpPath.'inline_video_'.uniqid('', true).'.'.$result['standaloneVideoType'];
            File::put($fileLocation, $result['standaloneVideoData']);

            if (! $context->foundMediaInfo && $this->mediaService->getMediaInfo($fileLocation, $context->release->id)) {
                $context->markFound('mediaInfo');
                $this->output->echoMediaInfoAdded();
            }
            if (! $context->foundSample && $this->mediaService->getSample($fileLocation, $context->tmpPath, $context->release->guid)) {
                $context->markFound('sample');
                $this->output->echoSampleCreated();
            }
            if (! $context->foundVideo && $this->mediaService->getVideo($fileLocation, $context->tmpPath, $context->release->guid)) {
                $context->markFound('video');
                $this->output->echoVideoCreated();
            }

            return $context->foundMediaInfo || $context->foundSample || $context->foundVideo;
        }

        if (! $result['success']) {
            return false;
        }

        if (! empty($result['archiveMarker'])) {
            $this->output->echoArchiveMarker($result['archiveMarker']);
        }

        if ($reverse && ! empty($result['dataSummary'])) {
            $this->releaseManager->processReleaseNameFromRar($result['dataSummary'], $context);
        }

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

        if (! $context->foundJPGSample && $this->config->processJPGSample) {
            $this->archiveFallback->processJpgFromArchiveFileList($compressedData, $result['files'], $context);
        }

        return $context->totalFileInfo > 0;
    }

    private function processExtractedFiles(ReleaseProcessingContext $context): void
    {
        $nestedLevels = 0;

        while ($nestedLevels < $this->config->maxNestedLevels) {
            if ($context->compressedFilesChecked >= AdditionalProcessingOrchestrator::MAX_COMPRESSED_FILES_TO_CHECK) {
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
                if (! File::isFile($filePath)) {
                    continue;
                }

                $rarData = @File::get($filePath);
                if (! empty($rarData)) {
                    $this->processCompressedData($rarData, $context, false);
                    $foundCompressed = true;
                }
                File::delete($filePath);
            }

            if (! $foundCompressed) {
                break;
            }
            $nestedLevels++;
        }

        try {
            $files = $this->tempWorkspace->listFiles($context->tmpPath);
        } catch (\Throwable) {
            return;
        }

        foreach ($files as $file) {
            $filePath = is_object($file) ? $file->getPathname() : $file;

            if (preg_match('/[\/\\\\]\.{1,2}$/', $filePath) === 1 || ! File::isFile($filePath)) {
                continue;
            }

            if (! $context->foundPAR2Info && preg_match('/\.par2$/i', $filePath) === 1) {
                $this->releaseManager->processPar2File(
                    $filePath,
                    $context,
                    $this->archiveService->getPar2Info()
                );

                continue;
            }

            if ($context->releaseHasNoNFO) {
                if (preg_match('/(\.(nfo|inf|ofn|diz)|info\.txt)$/i', $filePath) === 1) {
                    if ($this->releaseManager->processNfoFile($filePath, $context, $this->downloadService->getNNTP())) {
                        $this->output->echoNfoFound();
                    }

                    continue;
                }

                if ($this->releaseManager->isNfoFilename($filePath)) {
                    if ($this->releaseManager->processNfoFile($filePath, $context, $this->downloadService->getNNTP())) {
                        $this->output->echoNfoFound();
                    }

                    continue;
                }
            }

            if ((! $context->foundAudioInfo || ! $context->foundAudioSample)
                && preg_match('/(.*)'.$this->config->audioFileRegex.'$/i', $filePath, $fileType) === 1
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

            if (! $context->foundJPGSample && preg_match('/\.(jpe?g|png)$/i', $filePath) === 1) {
                if ($this->mediaService->getJPGSample($filePath, $context->release->guid)) {
                    $context->markFound('jpgSample');
                    $this->output->echoJpgSaved();
                }
                File::delete($filePath);

                continue;
            }

            if ((! $context->foundSample || ! $context->foundVideo || ! $context->foundMediaInfo)
                && preg_match('/(.*)'.$this->config->videoFileRegex.'$/i', $filePath) === 1
            ) {
                $this->mediaService->processVideoFile($filePath, $context, $context->tmpPath);

                continue;
            }

            $output = fileInfo($filePath);
            if ($output === '' || $output === null) {
                continue;
            }

            if (! $context->foundJPGSample && preg_match('/^JPE?G|^PNG/i', $output) === 1) {
                if ($this->mediaService->getJPGSample($filePath, $context->release->guid)) {
                    $context->markFound('jpgSample');
                    $this->output->echoJpgSaved();
                }
                File::delete($filePath);
            } elseif ((! $context->foundMediaInfo || ! $context->foundSample || ! $context->foundVideo)
                && preg_match('/Matroska data|MPEG v4|MPEG sequence, v2|\WAVI\W/i', $output) === 1
            ) {
                $this->mediaService->processVideoFile($filePath, $context, $context->tmpPath);
            } elseif ((! $context->foundAudioSample || ! $context->foundAudioInfo)
                && preg_match('/^FLAC|layer III|Vorbis audio/i', $output, $audioType) === 1
            ) {
                $extension = match ($audioType[0]) {
                    'FLAC' => 'FLAC',
                    'layer III' => 'MP3',
                    'Vorbis audio' => 'OGG',
                    default => 'audio',
                };
                $audioPath = $context->tmpPath.'audiofile.'.$extension;
                File::move($filePath, $audioPath);
                $audioResult = $this->mediaService->getAudioInfo($audioPath, $extension, $context, $context->tmpPath);
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
}

<?php

declare(strict_types=1);

namespace App\Services\AdditionalProcessing;

use App\Models\UsenetGroup;
use App\Services\AdditionalProcessing\Config\ProcessingConfiguration;
use App\Services\AdditionalProcessing\DTO\ReleaseProcessingResult;
use App\Services\AdditionalProcessing\Enums\DownloadKind;
use App\Services\AdditionalProcessing\Enums\ProcessingOutcome;
use App\Services\AdditionalProcessing\Enums\ProcessingStage;
use App\Services\AdditionalProcessing\State\PersistenceMetricsCollector;
use App\Services\AdditionalProcessing\State\ProcessingMetrics;
use App\Services\AdditionalProcessing\State\ReleaseProcessingContext;
use App\Services\Releases\ReleaseBrowseService;
use App\Services\TempWorkspaceService;
use Illuminate\Support\Facades\File;

/**
 * Stateless release processor for additional post-processing.
 */
class ReleaseProcessor
{
    /**
     * @var array<int, string>
     */
    private array $groupNameCache = [];

    public function __construct(
        private readonly ProcessingConfiguration $config,
        private readonly NzbContentParser $nzbParser,
        private readonly AdditionalWorkPlanner $workPlanner,
        private readonly ArchiveExtractionService $archiveService,
        private readonly MediaExtractionService $mediaService,
        private readonly UsenetDownloadService $downloadService,
        private readonly ReleaseFileManager $releaseManager,
        private readonly ReleaseFilesArchiveFallback $archiveFallback,
        private readonly TempWorkspaceService $tempWorkspace,
        private readonly ConsoleOutputService $output,
        private readonly ?ReleaseSearchSyncCoordinator $searchSyncCoordinator = null,
        private readonly ?PersistenceMetricsCollector $persistenceMetricsCollector = null,
    ) {}

    public function process(ReleaseProcessingContext $context, string $mainTmpPath): ReleaseProcessingResult
    {
        $metrics = new ProcessingMetrics;
        $releaseId = (int) $context->release->id;
        $persistenceMetricsCollector = $this->persistenceMetricsCollector ?? new PersistenceMetricsCollector;
        $searchSyncCoordinator = $this->searchSyncCoordinator
            ?? new ReleaseSearchSyncCoordinator(
                $persistenceMetricsCollector,
            );

        $persistenceMetricsCollector->beginReleaseScope($releaseId);
        $searchSyncCoordinator->beginReleaseScope($releaseId);
        $this->downloadService->beginReleaseScope();

        try {
            $result = $this->processRelease($context, $mainTmpPath, $metrics);
        } finally {
            $context->releaseDownloadedArchives();
            try {
                $searchSyncCoordinator->finishReleaseScope();
            } finally {
                $persistenceMetrics = $persistenceMetricsCollector->finishReleaseScope();
                $downloadMetrics = $this->downloadService->finishReleaseScope();
            }
        }

        return $result->withPerformance(
            $metrics->elapsedSeconds(),
            $metrics->stageDurations(),
            $downloadMetrics,
            $persistenceMetrics,
        );
    }

    private function processRelease(
        ReleaseProcessingContext $context,
        string $mainTmpPath,
        ProcessingMetrics $metrics,
    ): ReleaseProcessingResult {
        $release = $context->release;

        $this->output->echoReleaseStart($release->id, $release->size);
        $this->output->setProcessTitle((int) $release->id);

        try {
            $context->tmpPath = $metrics->measure(
                ProcessingStage::WorkspacePreparation,
                fn (): string => $this->tempWorkspace->createReleaseTempFolder($mainTmpPath, $release->guid),
            );
        } catch (\Throwable $e) {
            $this->output->warning('Unable to prepare release temp directory: '.$e->getMessage());

            return $this->result(
                $context,
                ProcessingOutcome::TemporaryWorkspaceUnavailable,
                reason: $e->getMessage(),
            );
        }

        try {
            $releaseNameChanged = false;
            $releaseNeededNfo = false;
            $nzbResult = $metrics->measure(
                ProcessingStage::NzbParsing,
                fn (): array => $this->nzbParser->parseNzb($release->guid),
            );
            if ($nzbResult['error'] !== null) {
                $this->output->warning($nzbResult['error']);
                $this->releaseManager->deleteRelease($release);

                return $this->result(
                    $context,
                    ProcessingOutcome::DeletedBrokenNzb,
                    reason: $nzbResult['error'],
                );
            }

            $context->nzbContents = array_values($nzbResult['contents']);
            if (($timeoutResult = $this->processingTimeoutResult($context, $metrics)) !== null) {
                return $timeoutResult;
            }

            $releaseNameChanged = $metrics->measure(
                ProcessingStage::ReleaseInitialization,
                function () use ($context): bool {
                    $this->initializeContext($context);

                    return $this->releaseManager->processReleaseNameFromNzbContents($context->nzbContents, $context);
                },
            );
            $releaseNeededNfo = $context->releaseHasNoNFO;
            $bookFlood = $metrics->measure(
                ProcessingStage::MessageIdSelection,
                fn (): bool => $this->prepareMessageIds($context),
            );

            if ($this->shouldProcessDownloads()) {
                $metrics->measure(
                    ProcessingStage::DirectDownloads,
                    fn () => $this->processMessageIdDownloads($context),
                );
                if (($timeoutResult = $this->processingTimeoutResult($context, $metrics)) !== null) {
                    return $timeoutResult;
                }

                if (! $bookFlood && $context->nzbHasCompressedFile) {
                    $triedCompressedMids = [];
                    $metrics->measure(
                        ProcessingStage::ArchiveDownloads,
                        function () use ($context, &$triedCompressedMids): void {
                            $this->processNzbCompressedFiles($context, false, $triedCompressedMids);
                        },
                    );
                    if (($timeoutResult = $this->processingTimeoutResult($context, $metrics)) !== null) {
                        return $timeoutResult;
                    }

                    if ($this->config->fetchLastFiles) {
                        $metrics->measure(
                            ProcessingStage::ArchiveDownloads,
                            function () use ($context, &$triedCompressedMids): void {
                                $this->processNzbCompressedFiles($context, true, $triedCompressedMids);
                            },
                        );
                        if (($timeoutResult = $this->processingTimeoutResult($context, $metrics)) !== null) {
                            return $timeoutResult;
                        }
                    }

                    if (! $context->releaseHasPassword) {
                        $metrics->measure(
                            ProcessingStage::ExtractedFiles,
                            fn () => $this->processExtractedFiles($context),
                        );
                        if (($timeoutResult = $this->processingTimeoutResult($context, $metrics)) !== null) {
                            return $timeoutResult;
                        }
                    }
                }

                $metrics->measure(ProcessingStage::ArchiveFallbacks, function () use ($context): void {
                    if (! $context->foundJPGSample && $this->config->processJPGSample) {
                        $this->archiveFallback->processJpgFromReleaseFiles($context);
                    }

                    if ($context->releaseHasNoNFO) {
                        $this->archiveFallback->processNfoFromDownloadedArchives($context);
                    }

                    if ($context->releaseHasNoNFO) {
                        $this->archiveFallback->processNfoFromReleaseFiles($context);
                    }
                });
            }

            $metrics->measure(
                ProcessingStage::Finalization,
                fn () => $this->releaseManager->finalizeRelease($context, $this->config->processPasswords),
            );

            $artifactsCreated = $releaseNameChanged || $this->createdArtifacts($context, $releaseNeededNfo);
            $outcome = match (true) {
                $context->releaseHasPassword => ProcessingOutcome::Passworded,
                $context->groupUnavailable => ProcessingOutcome::GroupUnavailable,
                ! $artifactsCreated => ProcessingOutcome::NoUsefulArtifacts,
                default => ProcessingOutcome::Completed,
            };

            return $this->result(
                $context,
                $outcome,
                $artifactsCreated,
                reason: match ($outcome) {
                    ProcessingOutcome::Passworded => 'Password protection was detected.',
                    ProcessingOutcome::GroupUnavailable => 'The release group was unavailable during processing.',
                    ProcessingOutcome::NoUsefulArtifacts => 'Processing completed without creating useful artifacts.',
                    default => '',
                },
            );
        } finally {
            if ($context->tmpPath !== '') {
                $metrics->measure(
                    ProcessingStage::WorkspaceCleanup,
                    fn () => $this->tempWorkspace->clearDirectory($context->tmpPath, false),
                );
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

    private function prepareMessageIds(ReleaseProcessingContext $context): bool
    {
        $workPlan = $this->workPlanner->plan(
            $context->nzbContents,
            $context->releaseGroupName,
        );
        $context->workPlan = $workPlan;
        $context->nzbHasCompressedFile = $workPlan->hasCompressedFile();
        $context->sampleMessageIDs = $workPlan->sampleMessageIds;
        $context->jpgMessageIDs = $workPlan->jpgMessageIds;
        $context->mediaInfoMessageIDs = $workPlan->mediaInfoMessageId;
        $context->audioInfoMessageIDs = $workPlan->audioInfoMessageId;
        $context->audioInfoExtension = $workPlan->audioInfoExtension;

        return $workPlan->bookFlood;
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
            $groupId = (int) $context->release->groups_id;
            if (! array_key_exists($groupId, $this->groupNameCache)) {
                $this->groupNameCache[$groupId] = UsenetGroup::getNameByID($groupId);
            }
            $context->releaseGroupName = $this->groupNameCache[$groupId];
        } catch (\Throwable) {
            $context->releaseGroupName = '';
        }
        $context->releaseHasNoNFO = (int) $context->release->nfostatus !== 1;
        $context->resetMessageIDs();
        $context->resetCounters();
    }

    private function processingTimeoutResult(
        ReleaseProcessingContext $context,
        ProcessingMetrics $metrics,
    ): ?ReleaseProcessingResult {
        if (! $context->isTimedOut($this->config->releaseProcessingTimeout)) {
            return null;
        }

        $deleted = $metrics->measure(
            ProcessingStage::TimeoutHandling,
            fn (): bool => $this->releaseManager->handleReleaseTimeout(
                $context->release,
                $this->config->maxPpTimeoutCount,
            ),
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

        return $this->result(
            $context,
            $deleted ? ProcessingOutcome::DeletedAfterTimeout : ProcessingOutcome::TimedOut,
            reason: $deleted
                ? 'The release was deleted after reaching the post-processing timeout limit.'
                : 'The release exceeded the post-processing timeout.',
        );
    }

    private function createdArtifacts(ReleaseProcessingContext $context, bool $releaseNeededNfo): bool
    {
        return $context->releaseFilesChanged
            || $context->foundPAR2Info
            || ($releaseNeededNfo && ! $context->releaseHasNoNFO)
            || ($this->config->processVideo && $context->foundVideo)
            || ($this->config->processThumbnails && $context->foundSample)
            || ($this->config->processJPGSample && $context->foundJPGSample)
            || ($this->config->processMediaInfo && $context->foundMediaInfo)
            || ($this->config->processAudioInfo && $context->foundAudioInfo)
            || ($this->config->processAudioSample && $context->foundAudioSample);
    }

    private function result(
        ReleaseProcessingContext $context,
        ProcessingOutcome $outcome,
        bool $artifactsCreated = false,
        string $reason = '',
    ): ReleaseProcessingResult {
        return new ReleaseProcessingResult(
            releaseId: (int) $context->release->id,
            guid: (string) $context->release->guid,
            outcome: $outcome,
            artifactsCreated: $artifactsCreated,
            releaseFilesAdded: $context->addedFileInfo,
            reason: $reason,
            duplicateMessageIdCount: $context->duplicateMessageIdCount(),
            unsupportedReasons: $context->unsupportedReasons(),
        );
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

                if ($this->mediaService->isValidImage($fileLocation)
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

        $archiveCandidates = match (true) {
            $context->workPlan === null => [],
            $reverse => $context->workPlan->orderedArchiveCandidates(true),
            default => $context->workPlan->prioritizedArchiveCandidates(),
        };
        if (! $reverse) {
            $triedCompressedMids = [];
        }

        $failed = $downloaded = 0;

        foreach ($archiveCandidates as $archiveCandidate) {
            if ($downloaded >= $this->config->maximumRarSegments
                || $failed >= $this->config->maximumRarPasswordChecks
                || $context->releaseHasPassword
            ) {
                break;
            }

            if ($reverse && array_intersect($archiveCandidate->messageIds, $triedCompressedMids) !== []) {
                continue;
            }

            if (! $reverse) {
                $triedCompressedMids = [...$triedCompressedMids, ...$archiveCandidate->messageIds];
            }

            $result = $this->downloadService->download(
                DownloadKind::Compressed,
                $archiveCandidate->messageIds,
                $context->releaseGroupName,
                $context->release->id,
                $archiveCandidate->title,
            );

            if ($result['groupUnavailable']) {
                $context->groupUnavailable = true;
                $this->output->echoGroupUnavailable();
                break;
            }

            if ($result['success'] && is_string($result['data'])) {
                $this->output->echoCompressedDownload();
                $downloaded++;

                $processed = $this->processCompressedData(
                    $result['data'],
                    $context,
                    $reverse,
                    $archiveCandidate->title,
                );
                if ($processed) {
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
        bool $reverse,
        string $archiveTitle = '',
    ): bool {
        $result = $this->archiveService->processCompressedData(
            $compressedData,
            $context,
            $context->tmpPath
        );

        if ($result['hasPassword']) {
            $context->releaseHasPassword = true;
            $context->passwordStatus = $result['passwordStatus'];

            return true;
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

        if ($context->releaseHasNoNFO
            && is_array($result['files'])
            && $this->containsNfoCandidate($result['files'])
        ) {
            $context->rememberDownloadedArchive($archiveTitle, $compressedData, $result['files']);
        }

        if (! $context->foundJPGSample && $this->config->processJPGSample) {
            $this->archiveFallback->processJpgFromArchiveFileList($compressedData, $result['files'], $context);
        }

        return $context->totalFileInfo > 0;
    }

    /**
     * @param  array<int, array<string, mixed>>  $files
     */
    private function containsNfoCandidate(array $files): bool
    {
        foreach ($files as $file) {
            if ($this->archiveService->isNfoFile((string) ($file['name'] ?? ''))) {
                return true;
            }
        }

        return false;
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
                    $this->processCompressedData($rarData, $context, false, basename($filePath));
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

            if (! $context->foundJPGSample && preg_match('/\.(jpe?g|png|webp)$/i', $filePath) === 1) {
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

            if (! $context->foundJPGSample && preg_match('/^JPE?G|^PNG|^WebP/i', $output) === 1) {
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

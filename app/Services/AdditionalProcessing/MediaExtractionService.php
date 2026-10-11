<?php

declare(strict_types=1);

namespace App\Services\AdditionalProcessing;

use App\Enums\ImageAssetProfile;
use App\Models\Category;
use App\Models\Release;
use App\Services\AdditionalProcessing\Config\ProcessingConfiguration;
use App\Services\AdditionalProcessing\DTO\MediaSourceHints;
use App\Services\AdditionalProcessing\Enums\SourcePosition;
use App\Services\AdditionalProcessing\Media\FfmpegFrameGrabber;
use App\Services\AdditionalProcessing\Media\FrameQualityScorer;
use App\Services\AdditionalProcessing\Media\ImageInspector;
use App\Services\AdditionalProcessing\Media\MediaToolFactory;
use App\Services\AdditionalProcessing\Media\ThumbnailFrameSelector;
use App\Services\AdditionalProcessing\State\PersistenceMetricsCollector;
use App\Services\AdditionalProcessing\State\ReleaseProcessingContext;
use App\Services\Categorization\CategorizationService;
use App\Services\NameFixing\ReleaseUpdateService;
use App\Services\ReleaseExtraService;
use App\Services\ReleaseImageService;
use FFMpeg\Coordinate\Dimension;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\FFMpeg;
use FFMpeg\FFProbe;
use FFMpeg\Filters\Video\ResizeFilter;
use FFMpeg\Format\Video\Ogg;
use FFMpeg\Media\Video;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Mhor\MediaInfo\MediaInfo;

/**
 * Service for processing media files (video, audio, images).
 * Handles sample generation, thumbnails, media info extraction, and audio processing.
 */
class MediaExtractionService
{
    private readonly MediaToolFactory $tools;

    private readonly ThumbnailFrameSelector $frameSelector;

    private readonly ImageInspector $imageInspector;

    private ?MediaInfo $mediaInfo = null;

    private readonly ReleaseSearchSyncCoordinator $searchSyncCoordinator;

    public function __construct(
        private readonly ProcessingConfiguration $config,
        private readonly ReleaseImageService $releaseImage,
        private readonly ReleaseExtraService $releaseExtra,
        private readonly CategorizationService $categorize,
        ?ReleaseSearchSyncCoordinator $searchSyncCoordinator = null,
        ?MediaToolFactory $tools = null,
        ?ThumbnailFrameSelector $frameSelector = null,
    ) {
        $this->searchSyncCoordinator = $searchSyncCoordinator
            ?? new ReleaseSearchSyncCoordinator(
                new PersistenceMetricsCollector,
            );
        $this->tools = $tools ?? new MediaToolFactory($config);
        $scorer = new FrameQualityScorer($config->thumbnailMinStddev, $config->thumbnailMinLuma, $config->thumbnailMaxLuma);
        $this->frameSelector = $frameSelector ?? new ThumbnailFrameSelector(
            $config,
            new FfmpegFrameGrabber($this->tools, $config->debugMode),
            $scorer,
        );
        $this->imageInspector = new ImageInspector($scorer, $config->thumbnailMinStddev);
    }

    /**
     * Read a file's duration in seconds from its container, or from its first video stream.
     */
    public function getMediaDuration(string $videoLocation): ?float
    {
        try {
            if (! $this->ffprobe()->isValid($videoLocation)) {
                return null;
            }

            $duration = $this->ffprobe()->format($videoLocation)->get('duration');
            if (! is_numeric($duration) || (float) $duration <= 0) {
                $duration = $this->ffprobe()->streams($videoLocation)->videos()->first()?->get('duration');
            }
        } catch (\Throwable $e) {
            if ($this->config->debugMode) {
                Log::debug($e->getMessage());
            }

            return null;
        }

        return is_numeric($duration) && (float) $duration > 0 ? (float) $duration : null;
    }

    /**
     * Extract a preview image from a video file, skipping blank frames.
     */
    public function getSample(string $fileLocation, string $tmpPath, string $guid, ?MediaSourceHints $hints = null): bool
    {
        if (! $this->config->processThumbnails || ! File::isFile($fileLocation)) {
            return false;
        }

        $workDirectory = $tmpPath.'frames_'.bin2hex(random_bytes(6)).'/';

        try {
            File::ensureDirectoryExists($workDirectory);
            $frame = $this->frameSelector->select($fileLocation, $workDirectory, $hints);
            if ($frame === null) {
                return false;
            }

            return $this->releaseImage->saveLocalImage(
                $guid.'_thumb',
                $frame,
                $this->releaseImage->imgSavePath,
                ImageAssetProfile::Preview,
            )->success;
        } catch (\Throwable $e) {
            if ($this->config->debugMode) {
                Log::error($e->getTraceAsString());
            }

            return false;
        } finally {
            File::deleteDirectory($workDirectory);
        }
    }

    /**
     * Create a video sample clip.
     *
     * A complete file is clipped from 30% in; a partial download from its start,
     * because only the start is known to be there.
     */
    public function getVideo(string $fileLocation, string $tmpPath, string $guid, ?MediaSourceHints $hints = null): bool
    {
        if (! $this->config->processVideo || ! File::isFile($fileLocation) || $hints?->position === SourcePosition::Mid) {
            return false;
        }

        $fileName = $tmpPath.'zzzz'.$guid.'.ogv';

        try {
            if ($this->ffprobe()->isValid($fileLocation)) {
                $start = 0.0;
                if ($hints?->position === SourcePosition::Complete) {
                    $duration = $this->getMediaDuration($fileLocation) ?? 0.0;
                    $start = max(0.0, min($duration * 0.3, $duration - $this->config->ffmpegDuration));
                }

                /** @var Video $video */
                $video = $this->ffmpeg()->open($fileLocation);
                $clip = $video->clip(
                    TimeCode::fromSeconds($start),
                    TimeCode::fromSeconds($this->config->ffmpegDuration)
                );
                $format = new Ogg;
                $format->setAudioCodec('libvorbis');
                $clip->filters()->resize(new Dimension(320, -1), ResizeFilter::RESIZEMODE_SCALE_HEIGHT);
                $clip->save($format, $fileName);
            }
        } catch (\Throwable $e) {
            if ($this->config->debugMode) {
                Log::error($e->getTraceAsString());
            }
        }

        if (! File::isFile($fileName)) {
            return false;
        }

        $newFile = $this->releaseImage->vidSavePath.$guid.'.ogv';

        if (! @File::move($fileName, $newFile)) {
            $copied = @File::copy($fileName, $newFile);
            File::delete($fileName);
            if (! $copied) {
                return false;
            }
        }

        @chmod($newFile, 0764);
        Release::query()->where('guid', $guid)->update(['videostatus' => 1]);

        return true;
    }

    /**
     * Extract media info from a video file.
     */
    public function getMediaInfo(string $fileLocation, int $releaseId): bool
    {
        if (! $this->config->processMediaInfo || ! File::isFile($fileLocation)) {
            return false;
        }

        try {
            $xmlArray = $this->mediaInfo()->getInfo($fileLocation, true);
            \App\Models\MediaInfo::addData($releaseId, $xmlArray);
            $this->releaseExtra->addFromXml($releaseId, $xmlArray);

            return true;
        } catch (\Throwable $e) {
            Log::debug($e->getMessage());

            return false;
        }
    }

    /**
     * Process a JPG sample image.
     */
    public function getJPGSample(string $fileLocation, string $guid): bool
    {
        $saved = $this->releaseImage->saveLocalImage(
            $guid.'_thumb',
            $fileLocation,
            $this->releaseImage->jpgSavePath,
            ImageAssetProfile::Sample,
        );

        if ($saved->success) {
            Release::query()->where('guid', $guid)->update(['jpgstatus' => 1]);

            return true;
        }

        return false;
    }

    /**
     * Process audio file for media info and sample.
     *
     * @return array{audioInfo: bool, audioSample: bool}
     */
    public function getAudioInfo(
        string $fileLocation,
        string $fileExtension,
        ReleaseProcessingContext $context,
        string $tmpPath
    ): array {
        $result = ['audioInfo' => false, 'audioSample' => false];

        if (! $this->config->processAudioSample) {
            $result['audioSample'] = true;
        }
        if (! $this->config->processAudioInfo) {
            $result['audioInfo'] = true;
        }

        $rQuery = Release::query()
            ->where('proc_pp', '=', 0)
            ->where('id', $context->release->id)
            ->select(['searchname', 'fromname', 'categories_id', 'groups_id'])
            ->first();

        $musicParent = (string) Category::MUSIC_ROOT;
        if ($rQuery === null || ! preg_match(
            sprintf(
                '/%d\d{3}|%d|%d|%d/',
                $musicParent[0],
                Category::OTHER_MISC,
                Category::MOVIE_OTHER,
                Category::TV_OTHER
            ),
            (string) $rQuery->categories_id
        )) {
            return $result;
        }

        if (! File::isFile($fileLocation)) {
            return $result;
        }

        // Get media info
        if (! $result['audioInfo']) {
            try {
                $xmlArray = $this->mediaInfo()->getInfo($fileLocation, false);
                foreach ($xmlArray->getAudios() as $track) {
                    if ($track->get('album') !== null && $track->get('performer') !== null) {
                        if ((int) $context->release->predb_id === 0 && $this->config->renameMusicMediaInfo) {
                            $ext = strtoupper($fileExtension);

                            $newName = $track->get('performer')->getFullName().' - '.$track->get('album')->getFullName();
                            if (! empty($track->get('recorded_date'))
                                && preg_match('/(?:19|20)\d\d/', $track->get('recorded_date')->getFullname, $Year)
                            ) {
                                $newName .= ' ('.$Year[0].') '.$ext;
                            } else {
                                $newName .= ' '.$ext;
                            }

                            $newCat = match ($ext) {
                                'MP3' => Category::MUSIC_MP3,
                                'FLAC' => Category::MUSIC_LOSSLESS,
                                default => $this->categorize->determineCategory($rQuery->groups_id, $newName, $rQuery->fromname),
                            };

                            $newTitle = escapeString(substr($newName, 0, 255));
                            Release::whereId($context->release->id)->update([
                                'searchname' => $newTitle,
                                'categories_id' => is_array($newCat) ? $newCat['categories_id'] : $newCat,
                                'iscategorized' => 1,
                                'isrenamed' => 1,
                                'proc_pp' => 1,
                            ]);

                            $this->searchSyncCoordinator->request((int) $context->release->id);

                            if ($this->config->echoCLI) {
                                $releaseInfo = (object) [
                                    'groups_id' => $rQuery->groups_id, 'categories_id' => $rQuery->categories_id,
                                    'searchname' => $rQuery->searchname, 'name' => $rQuery->searchname,
                                    'releases_id' => $context->release->id, 'filename' => '',
                                ];
                                (new ReleaseUpdateService)->echoReleaseInfo($releaseInfo, $newTitle,
                                    is_array($newCat) ? $newCat : ['categories_id' => $newCat], '',
                                    'MediaExtractionService->getAudioInfo');
                            }
                        }

                        $this->releaseExtra->addFromXml($context->release->id, $xmlArray);
                        $result['audioInfo'] = true;
                        $context->foundAudioInfo = true;
                        break;
                    }
                }
            } catch (\Throwable $e) {
                Log::debug($e->getMessage());
            }
        }

        // Create audio sample
        if (! $result['audioSample']) {
            $audioFileName = $context->release->guid.'.ogg';

            try {
                if ($this->ffprobe()->isValid($fileLocation)) {
                    $this->saveAudioClip($fileLocation, $tmpPath.$audioFileName);
                }
            } catch (\Throwable $e) {
                if ($this->config->debugMode) {
                    Log::error($e->getTraceAsString());
                }
            }

            if (File::isFile($tmpPath.$audioFileName)) {
                $renamed = File::move($tmpPath.$audioFileName, $this->config->audioSavePath.$audioFileName);
                if (! $renamed) {
                    $copied = File::copy($tmpPath.$audioFileName, $this->config->audioSavePath.$audioFileName);
                    File::delete($tmpPath.$audioFileName);
                    if (! $copied) {
                        return $result;
                    }
                }

                @chmod($this->config->audioSavePath.$audioFileName, 0764);
                $result['audioSample'] = true;
                $context->foundAudioSample = true;
            }
        }

        return $result;
    }

    /**
     * Encode 30 seconds of a track as Ogg Vorbis, skipping the intro of anything over a minute.
     */
    public function saveAudioClip(string $fileLocation, string $outputFile): bool
    {
        $duration = $this->getMediaDuration($fileLocation) ?? 0.0;

        $this->ffmpeg()->getFFMpegDriver()->command([
            '-y',
            '-ss', $duration > 60 ? '30' : '0',
            '-t', '30',
            '-i', $fileLocation,
            '-vn',
            '-c:a', 'libvorbis',
            '-q:a', '4',
            $outputFile,
        ]);

        return File::isFile($outputFile) && File::size($outputFile) > 0;
    }

    /**
     * Process a video file for sample, video clip, and media info.
     *
     * @return array<string, mixed>
     */
    public function processVideoFile(
        string $fileLocation,
        ReleaseProcessingContext $context,
        string $tmpPath
    ): array {
        $result = [
            'sample' => false,
            'video' => false,
            'mediaInfo' => false,
        ];

        if (! $context->foundSample) {
            $result['sample'] = $this->getSample($fileLocation, $tmpPath, $context->release->guid);
            if ($result['sample']) {
                $context->foundSample = true;
            }
        }

        // Only get video if sampleMessageIDs count is less than 2
        if (! $context->foundVideo && count($context->sampleMessageIDs) < 2) {
            $result['video'] = $this->getVideo($fileLocation, $tmpPath, $context->release->guid);
            if ($result['video']) {
                $context->foundVideo = true;
            }
        }

        if (! $context->foundMediaInfo) {
            $result['mediaInfo'] = $this->getMediaInfo($fileLocation, $context->release->id);
            if ($result['mediaInfo']) {
                $context->foundMediaInfo = true;
            }
        }

        return $result;
    }

    /**
     * Check if data appears to be a JPEG image.
     */
    public function isJpegData(string $filePath): bool
    {
        if (! File::isFile($filePath)) {
            return false;
        }

        return exif_imagetype($filePath) === IMAGETYPE_JPEG;
    }

    /**
     * Whether an image is complete, big enough to show something, and not blank.
     */
    public function isUsableImage(string $filePath): bool
    {
        return File::isFile($filePath) && $this->imageInspector->isUsable($filePath);
    }

    /**
     * Check if file is a valid image (JPEG or PNG).
     */
    public function isValidImage(string $filePath): bool
    {
        if (! File::isFile($filePath)) {
            return false;
        }

        $type = @exif_imagetype($filePath);

        return in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true);
    }

    private function ffmpeg(): FFMpeg
    {
        return $this->tools->ffmpeg();
    }

    private function ffprobe(): FFProbe
    {
        return $this->tools->ffprobe();
    }

    private function mediaInfo(): MediaInfo
    {
        if ($this->mediaInfo === null) {
            $this->mediaInfo = new MediaInfo;
            $this->mediaInfo->setConfig('use_oldxml_mediainfo_output_format', true);
            if ($this->config->mediaInfoPath) {
                $this->mediaInfo->setConfig('command', $this->config->mediaInfoPath);
            }
        }

        return $this->mediaInfo;
    }
}

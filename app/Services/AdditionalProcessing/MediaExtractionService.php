<?php

namespace App\Services\AdditionalProcessing;

use App\Facades\Search;
use App\Models\Category;
use App\Models\Release;
use App\Services\AdditionalProcessing\Config\ProcessingConfiguration;
use App\Services\AdditionalProcessing\DTO\ReleaseProcessingContext;
use App\Services\Categorization\CategorizationService;
use App\Services\NameFixing\ReleaseUpdateService;
use App\Services\ReleaseExtraService;
use App\Services\ReleaseImageService;
use FFMpeg\Coordinate\Dimension;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\FFMpeg;
use FFMpeg\FFProbe;
use FFMpeg\Filters\Video\ResizeFilter;
use FFMpeg\Format\Audio\Vorbis;
use FFMpeg\Format\Video\Ogg;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Mhor\MediaInfo\MediaInfo;

/**
 * Service for processing media files (video, audio, images).
 * Handles sample generation, thumbnails, media info extraction, and audio processing.
 */
class MediaExtractionService
{
    private ?FFMpeg $ffmpeg = null;

    private ?FFProbe $ffprobe = null;

    private ?MediaInfo $mediaInfo = null;

    public function __construct(
        private readonly ProcessingConfiguration $config,
        private readonly ReleaseImageService $releaseImage,
        private readonly ReleaseExtraService $releaseExtra,
        private readonly CategorizationService $categorize
    ) {}

    /**
     * Get video time code for sample extraction.
     */
    public function getVideoTime(string $videoLocation): string
    {
        try {
            if (! $this->ffprobe()->isValid($videoLocation)) {
                return '';
            }
            $time = $this->ffprobe()->format($videoLocation)->get('duration');
        } catch (\Throwable $e) {
            if ($this->config->debugMode) {
                Log::debug($e->getMessage());
            }

            return '';
        }

        if (empty($time) || ! preg_match('/time=(\d{1,2}:\d{1,2}:)?(\d{1,2})\.(\d{1,2})\s*bitrate=/i', $time, $numbers)) {
            return '';
        }

        if ($numbers[3] > 0) {
            $numbers[3]--;
        } elseif ($numbers[1] > 0) {
            $numbers[2]--;
            $numbers[3] = '99';
        }

        return '00:00:'.str_pad($numbers[2], 2, '0', STR_PAD_LEFT).'.'.str_pad($numbers[3], 2, '0', STR_PAD_LEFT);
    }

    /**
     * Extract a sample image from a video file.
     */
    public function getSample(string $fileLocation, string $tmpPath, string $guid): bool
    {
        if (! $this->config->processThumbnails || ! File::isFile($fileLocation)) {
            return false;
        }

        $fileName = $tmpPath.'zzzz'.random_int(5, 12).random_int(5, 12).'.jpg';
        $time = $this->getVideoTime($fileLocation);

        try {
            if ($this->ffprobe()->isValid($fileLocation)) {
                $this->ffmpeg()->open($fileLocation)
                    ->frame(TimeCode::fromString($time === '' ? '00:00:03:00' : $time))
                    ->save($fileName);
            }
        } catch (\Throwable $e) {
            if ($this->config->debugMode) {
                Log::error($e->getTraceAsString());
            }

            return false;
        }

        if (! File::isFile($fileName)) {
            return false;
        }

        $saved = $this->releaseImage->saveImage(
            $guid.'_thumb',
            $fileName,
            $this->releaseImage->imgSavePath,
            800,
            600
        );

        File::delete($fileName);

        return $saved === 1;
    }

    /**
     * Create a video sample clip.
     */
    public function getVideo(string $fileLocation, string $tmpPath, string $guid): bool
    {
        if (! $this->config->processVideo || ! File::isFile($fileLocation)) {
            return false;
        }

        $fileName = $tmpPath.'zzzz'.$guid.'.ogv';
        $newMethod = false;

        // Try to get sample from end of video if duration is short
        if ($this->config->ffmpegDuration < 60) {
            $time = $this->getVideoTime($fileLocation);
            if ($time !== '' && preg_match('/(\d{2}).(\d{2})/', $time, $numbers)) {
                $newMethod = true;
                if ($numbers[1] <= $this->config->ffmpegDuration) {
                    $lowestLength = '00:00:00.00';
                } else {
                    $lowestLength = ($numbers[1] - $this->config->ffmpegDuration);
                    $end = '.'.$numbers[2];
                    $lowestLength = match (strlen($lowestLength)) {
                        1 => '00:00:0'.$lowestLength.$end,
                        2 => '00:00:'.$lowestLength.$end,
                        default => '00:00:60.00',
                    };
                }

                try {
                    if ($this->ffprobe()->isValid($fileLocation)) {
                        $video = $this->ffmpeg()->open($fileLocation);
                        $clip = $video->clip(
                            TimeCode::fromString($lowestLength),
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
            }
        }

        // Fallback: use start of video
        if (! $newMethod) {
            try {
                if ($this->ffprobe()->isValid($fileLocation)) {
                    $video = $this->ffmpeg()->open($fileLocation);
                    $clip = $video->clip(
                        TimeCode::fromSeconds(0),
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
        $saved = $this->releaseImage->saveImage(
            $guid.'_thumb',
            $fileLocation,
            $this->releaseImage->jpgSavePath,
            650,
            650
        );

        if ($saved === 1) {
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
            $rQuery->categories_id
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
                if ($xmlArray !== null) {
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

                                Search::updateRelease($context->release->id);

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
                    $audioSample = $this->ffmpeg()->open($fileLocation);
                    $format = new Vorbis;
                    $audioSample->clip(TimeCode::fromSeconds(30), TimeCode::fromSeconds(30));
                    $audioSample->save($format, $tmpPath.$audioFileName);
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
                Release::query()->where('id', $context->release->id)->update(['audiostatus' => 1]);
                $result['audioSample'] = true;
                $context->foundAudioSample = true;
            }
        }

        return $result;
    }

    /**
     * Process a video file for sample, video clip, and media info.
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
     * Check if file is a valid image (JPEG or PNG).
     */
    public function isValidImage(string $filePath): bool
    {
        if (! File::isFile($filePath)) {
            return false;
        }

        $type = @exif_imagetype($filePath);

        return $type === IMAGETYPE_JPEG || $type === IMAGETYPE_PNG;
    }

    private function ffmpeg(): FFMpeg
    {
        if ($this->ffmpeg === null) {
            $timeout = $this->config->timeoutSeconds > 0 ? $this->config->timeoutSeconds : 60;
            $this->ffmpeg = FFMpeg::create(['timeout' => $timeout]);
        }

        return $this->ffmpeg;
    }

    private function ffprobe(): FFProbe
    {
        if ($this->ffprobe === null) {
            $this->ffprobe = FFProbe::create();
        }

        return $this->ffprobe;
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

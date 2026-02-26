<?php

declare(strict_types=1);

namespace App\Services;

use App\Facades\Search;
use App\Models\Category;
use App\Models\Release;
use App\Services\Categorization\CategorizationService;
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

class MediaProcessingService
{
    public function __construct(
        private readonly FFMpeg $ffmpeg,
        private readonly FFProbe $ffprobe,
        private readonly MediaInfo $mediaInfo,
        private readonly ReleaseImageService $releaseImage,
        private readonly ReleaseExtraService $releaseExtra,
        private readonly CategorizationService $categorize,
    ) {}

    public function getVideoTime(string $videoLocation): string
    {
        $time = null;
        try {
            if ($this->ffprobe->isValid($videoLocation)) {
                $val = $this->ffprobe->format($videoLocation)->get('duration');
                if (is_string($val) || is_numeric($val)) {
                    $time = (string) $val;
                }
            }
        } catch (\Throwable $e) {
            if (config('app.debug') === true) {
                Log::debug($e->getMessage());
            }
        }

        if (empty($time)) {
            return '';
        }

        // Case 1: matches ffmpeg log style `time=.. bitrate=` (optionally with hours)
        if (preg_match('/time=(\d{1,2}:\d{1,2}:)?(\d{1,2})\.(\d{1,2})\s*bitrate=/i', $time, $numbers)) {
            if ($numbers[3] > 0) {
                $numbers[3]--;
            } elseif (! empty($numbers[1])) {
                $numbers[2]--;
                $numbers[3] = '99';
            }

            return '00:00:'.str_pad((string) $numbers[2], 2, '0', STR_PAD_LEFT).'.'.str_pad((string) $numbers[3], 2, '0', STR_PAD_LEFT);
        }

        // Case 1b: matches `time=MM:SS.xx` (without trailing bitrate)
        if (preg_match('/time=(\d{2}):(\d{2})\.(\d{2})/i', $time, $m)) {
            $sec = (int) $m[2];
            $hund = (int) $m[3];
            if ($hund > 0) {
                $hund--;
            } else {
                if ($sec > 0) {
                    $sec--;
                    $hund = 99;
                }
            }

            return '00:00:'.str_pad((string) $sec, 2, '0', STR_PAD_LEFT).'.'.str_pad((string) $hund, 2, '0', STR_PAD_LEFT);
        }

        // Case 2: numeric seconds
        if (is_numeric($time)) {
            $seconds = (float) $time;
            if ($seconds <= 0) {
                return '';
            }
            $seconds = max(0.0, $seconds - 0.01);
            $whole = (int) floor($seconds);
            $hund = (int) round(($seconds - $whole) * 100);
            $hund = min($hund, 99);

            return '00:00:'.str_pad((string) $whole, 2, '0', STR_PAD_LEFT).'.'.str_pad((string) $hund, 2, '0', STR_PAD_LEFT);
        }

        return '';
    }

    public function saveJPGSample(string $guid, string $fileLocation): bool
    {
        $saved = $this->releaseImage->saveImage(
            $guid.'_thumb',
            $fileLocation,
            $this->releaseImage->jpgSavePath,
            650,
            650
        ) === 1;
        if ($saved) {
            Release::query()->where('guid', $guid)->update(['jpgstatus' => 1]);
        }

        return $saved;
    }

    public function createSampleImage(string $guid, string $fileLocation, string $tmpPath, bool $enabled, int $width = 800, int $height = 600): bool
    {
        if (! $enabled) {
            return false;
        }
        if (! File::isFile($fileLocation)) {
            return false;
        }
        $fileName = ($tmpPath.'zzzz'.random_int(5, 12).random_int(5, 12).'.jpg');
        $time = $this->getVideoTime($fileLocation);
        if ($this->ffprobe->isValid($fileLocation)) {
            try {
                /** @var \FFMpeg\Media\Video $video */
                $video = $this->ffmpeg->open($fileLocation);
                $video->frame(TimeCode::fromString($time === '' ? '00:00:03:00' : $time))
                    ->save($fileName);
            } catch (\Throwable $e) {
                if (config('app.debug') === true) {
                    Log::error($e->getMessage());
                }
            }
        }
        if (! File::isFile($fileName)) {
            return false;
        }
        $saved = $this->releaseImage->saveImage(
            $guid.'_thumb',
            $fileName,
            $this->releaseImage->imgSavePath,
            $width,
            $height
        );
        File::delete($fileName);
        if ($saved === 1) {
            return true;
        }

        return false;
    }

    public function createVideoSample(string $guid, string $fileLocation, string $tmpPath, bool $enabled, int $durationSeconds): bool
    {
        if (! $enabled) {
            return false;
        }
        if (! File::isFile($fileLocation)) {
            return false;
        }
        $fileName = ($tmpPath.'zzzz'.$guid.'.ogv');
        $newMethod = false;
        if ($durationSeconds < 60) {
            $time = $this->getVideoTime($fileLocation);
            if ($time !== '' && preg_match('/(\d{2}).(\d{2})/', $time, $numbers)) {
                $newMethod = true;
                if ($numbers[1] <= $durationSeconds) {
                    $lowestLength = '00:00:00.00';
                } else {
                    $lowestLength = ($numbers[1] - $durationSeconds);
                    $end = '.'.$numbers[2];
                    $lowestLength = match (strlen((string) $lowestLength)) {
                        1 => ('00:00:0'.$lowestLength.$end),
                        2 => ('00:00:'.$lowestLength.$end),
                        default => '00:00:60.00',
                    };
                }
                if ($this->ffprobe->isValid($fileLocation)) {
                    try {
                        /** @var \FFMpeg\Media\Video $video */
                        $video = $this->ffmpeg->open($fileLocation);
                        $videoSample = $video->clip(TimeCode::fromString($lowestLength), TimeCode::fromSeconds($durationSeconds));
                        $format = new Ogg;
                        $format->setAudioCodec('libvorbis');
                        $videoSample->filters()->resize(new Dimension(320, -1), ResizeFilter::RESIZEMODE_SCALE_HEIGHT);
                        $videoSample->save($format, $fileName);
                    } catch (\Throwable $e) {
                        if (config('app.debug') === true) {
                            Log::error($e->getMessage());
                        }
                    }
                }
            }
        }
        if (! $newMethod && $this->ffprobe->isValid($fileLocation)) {
            try {
                /** @var \FFMpeg\Media\Video $video */
                $video = $this->ffmpeg->open($fileLocation);
                $videoSample = $video->clip(TimeCode::fromSeconds(0), TimeCode::fromSeconds($durationSeconds));
                $format = new Ogg;
                $format->setAudioCodec('libvorbis');
                $videoSample->filters()->resize(new Dimension(320, -1), ResizeFilter::RESIZEMODE_SCALE_HEIGHT);
                $videoSample->save($format, $fileName);
            } catch (\Throwable $e) {
                if (config('app.debug') === true) {
                    Log::error($e->getMessage());
                }
            }
        }
        if (! File::isFile($fileName)) {
            return false;
        }
        $newFile = ($this->releaseImage->vidSavePath.$guid.'.ogv');
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

    public function addVideoMediaInfo(int $releaseId, string $fileLocation): bool
    {
        if (! File::isFile($fileLocation)) {
            return false;
        }
        try {
            $xmlArray = $this->mediaInfo->getInfo($fileLocation, true);
            \App\Models\MediaInfo::addData($releaseId, $xmlArray);
            $this->releaseExtra->addFromXml($releaseId, $xmlArray);

            return true;
        } catch (\Throwable $e) {
            Log::debug($e->getMessage());

            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function addAudioInfoAndSample(
        Release $release,
        string $fileLocation,
        string $fileExtension,
        bool $processAudioInfo,
        bool $processAudioSample,
        string $audioSavePath
    ): array {
        // Mirror original behavior: defaults depend on flags, not file presence
        $retVal = ! $processAudioInfo ? true : false;
        $audVal = ! $processAudioSample ? true : false;

        // Only proceed with file-dependent operations if file exists
        if (File::isFile($fileLocation)) {
            if ($processAudioInfo) {
                try {
                    $xmlArray = $this->mediaInfo->getInfo($fileLocation, false);
                    foreach ($xmlArray->getAudios() as $track) {
                        if ($track->get('album') !== null && $track->get('performer') !== null) {
                            if ((int) $release->predb_id === 0 && config('nntmux.rename_music_mediainfo')) {
                                $ext = strtoupper($fileExtension);
                                if (! empty($track->get('recorded_date')) && preg_match('/(?:19|20)\d\d/', $track->get('recorded_date')->getFullname(), $Year)) {
                                    $newName = $track->get('performer')->getFullName().' - '.$track->get('album')->getFullName().' ('.$Year[0].') '.$ext;
                                } else {
                                    $newName = $track->get('performer')->getFullName().' - '.$track->get('album')->getFullName().' '.$ext;
                                }
                                if ($ext === 'MP3') {
                                    $newCat = Category::MUSIC_MP3;
                                } elseif ($ext === 'FLAC') {
                                    $newCat = Category::MUSIC_LOSSLESS;
                                } else {
                                    $newCat = $this->categorize->determineCategory($release->groups_id, $newName, $release->fromname);
                                }
                                $newTitle = escapeString(substr($newName, 0, 255));
                                Release::whereId($release->id)->update([
                                    'searchname' => $newTitle,
                                    'categories_id' => $newCat['categories_id'] ?? $release->categories_id,
                                    'iscategorized' => 1,
                                    'isrenamed' => 1,
                                    'proc_pp' => 1,
                                ]);
                                Search::updateRelease($release->id);
                            }
                            $this->releaseExtra->addFromXml($release->id, $xmlArray);
                            $retVal = true;
                            break;
                        }
                    }
                } catch (\Throwable $e) {
                    Log::debug($e->getMessage());
                }
            }

            if ($processAudioSample) {
                $audioFileName = ($release->guid.'.ogg');
                if ($this->ffprobe->isValid($fileLocation)) {
                    try {
                        $audioSample = $this->ffmpeg->open($fileLocation);
                        $format = new Vorbis;
                        $audioSample->clip(TimeCode::fromSeconds(30), TimeCode::fromSeconds(30)); // @phpstan-ignore method.notFound
                        $audioSample->save($format, $audioSavePath.$audioFileName);
                    } catch (\Throwable $e) {
                        if (config('app.debug') === true) {
                            Log::error($e->getMessage());
                        }
                    }
                }
                if (File::isFile($audioSavePath.$audioFileName)) {
                    @chmod($audioSavePath.$audioFileName, 0764);
                    Release::query()->where('id', $release->id)->update(['audiostatus' => 1]);
                    $audVal = true;
                }
            }
        }

        return ['info' => $retVal, 'sample' => $audVal];
    }
}

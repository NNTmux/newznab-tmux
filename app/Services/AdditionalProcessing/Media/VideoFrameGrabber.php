<?php

declare(strict_types=1);

namespace App\Services\AdditionalProcessing\Media;

use App\Services\AdditionalProcessing\Enums\VideoContainer;

interface VideoFrameGrabber
{
    /**
     * Save up to $count keyframes from $from seconds on, at least $spacing seconds apart,
     * as JPEG files in $outputDirectory.
     *
     * @return list<string> frame paths, in playback order
     */
    public function grab(string $videoPath, ?VideoContainer $container, float $from, float $spacing, int $count, string $outputDirectory): array;

    /**
     * What the container says about the whole video. The bit rate is the video stream's own,
     * never one derived from the size of a partial download.
     *
     * @return array{duration: float|null, bitRate: int|null}
     */
    public function probe(string $videoPath, ?VideoContainer $container): array;
}

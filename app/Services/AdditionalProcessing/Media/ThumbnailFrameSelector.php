<?php

declare(strict_types=1);

namespace App\Services\AdditionalProcessing\Media;

use App\Services\AdditionalProcessing\Config\ProcessingConfiguration;
use App\Services\AdditionalProcessing\DTO\MediaSourceHints;
use App\Services\AdditionalProcessing\Enums\SourcePosition;
use App\Services\AdditionalProcessing\Enums\VideoContainer;

/**
 * Chooses the preview frame of a video, which is often only the start of the file.
 *
 * Openings tend to be black or show logos, so frames are taken a quarter of the
 * way into the part that was downloaded, estimated from the container's
 * duration and the file's real size or the stream's bit rate.
 */
class ThumbnailFrameSelector
{
    /** Share of the downloaded playing time skipped before taking frames. */
    private const float START_FRACTION = 0.25;

    /** Margin for bytes that hold headers or decode to less than the average. */
    private const float PLAYABLE_MARGIN = 0.85;

    public function __construct(
        private readonly ProcessingConfiguration $config,
        private readonly VideoFrameGrabber $grabber,
        private readonly FrameQualityScorer $scorer,
    ) {}

    /**
     * Return the best frame's image path, inside $workDirectory, or null if every frame was blank.
     */
    public function select(string $videoPath, string $workDirectory, ?MediaSourceHints $hints = null): ?string
    {
        $head = (string) @file_get_contents($videoPath, false, null, 0, 4096);
        $container = VideoContainer::detect($head);
        if ($container === null && self::looksLikeText($head)) {
            // Never let ffmpeg read a playlist or concat script out of downloaded data.
            return null;
        }

        [$from, $spacing] = $this->window($videoPath, $container, $hints);
        $windows = $from > 0.0 ? [[$from, $spacing], [0.0, 0.0]] : [[$from, $spacing]];
        foreach ($windows as [$start, $gap]) {
            $best = $this->scorer->best(
                $this->grabber->grab($videoPath, $container, $start, $gap, $this->config->thumbnailFrameAttempts, $workDirectory),
            );
            if ($best !== null) {
                return $best;
            }
        }

        return null;
    }

    /**
     * When to take the first frame, and how far apart the candidates should be so they
     * cover the rest of the downloaded playing time.
     *
     * @return array{0: float, 1: float} seconds
     */
    public function window(string $videoPath, ?VideoContainer $container, ?MediaSourceHints $hints): array
    {
        if ($hints?->position === SourcePosition::Mid) {
            return [0.0, 0.0];
        }

        $playable = $this->playableSeconds($videoPath, $container, $hints);
        $from = round($playable * self::START_FRACTION, 3);

        return [$from, round(($playable - $from) / $this->config->thumbnailFrameAttempts, 3)];
    }

    private function playableSeconds(string $videoPath, ?VideoContainer $container, ?MediaSourceHints $hints): float
    {

        ['duration' => $duration, 'bitRate' => $bitRate] = $this->grabber->probe($videoPath, $container);
        $available = $hints !== null && $hints->availableBytes > 0 ? $hints->availableBytes : (int) @filesize($videoPath);
        $fraction = $hints?->availableFraction();

        return match (true) {
            $duration !== null && $fraction !== null => $duration * $fraction * ($fraction < 1.0 ? self::PLAYABLE_MARGIN : 1.0),
            $hints !== null && $bitRate !== null && $available > 0 => $available * 8 / $bitRate * self::PLAYABLE_MARGIN,
            // Unknown size: assume the whole file is here; the retry from 0 covers a partial one.
            default => $duration ?? 0.0,
        };
    }

    private static function looksLikeText(string $head): bool
    {
        $start = ltrim($head, "\xEF\xBB\xBF \t\r\n");

        return stripos($start, '#EXTM3U') === 0 || stripos($start, 'ffconcat') === 0;
    }
}

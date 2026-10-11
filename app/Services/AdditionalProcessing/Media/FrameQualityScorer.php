<?php

declare(strict_types=1);

namespace App\Services\AdditionalProcessing\Media;

/**
 * Rates candidate preview frames by brightness and detail.
 *
 * A blank frame (black, white or one flat colour) is never chosen. Among the
 * rest, frames of normal brightness come first, then the most detailed one.
 */
final readonly class FrameQualityScorer
{
    private const int SAMPLE_WIDTH = 64;

    public function __construct(
        private float $minStddev,
        private float $minLuma,
        private float $maxLuma,
    ) {}

    /**
     * @param  list<string>  $imagePaths
     */
    public function best(array $imagePaths): ?string
    {
        $best = null;
        $bestRank = null;
        foreach ($imagePaths as $path) {
            $measure = $this->measure($path);
            if ($measure === null || $measure['stddev'] < $this->minStddev) {
                continue;
            }

            $rank = [
                $measure['mean'] >= $this->minLuma && $measure['mean'] <= $this->maxLuma,
                $measure['stddev'],
            ];
            if ($bestRank === null || $rank > $bestRank) {
                $best = $path;
                $bestRank = $rank;
            }
        }

        return $best;
    }

    /**
     * Mean luma and its standard deviation (0–255), measured on a downscaled copy.
     *
     * @return array{mean: float, stddev: float}|null
     */
    public function measure(string $imagePath): ?array
    {
        $contents = @file_get_contents($imagePath);
        $image = $contents === false || $contents === '' ? false : @imagecreatefromstring($contents);
        if ($image === false) {
            return null;
        }

        $sample = imagesx($image) > self::SAMPLE_WIDTH ? imagescale($image, self::SAMPLE_WIDTH) : $image;
        if ($sample === false) {
            return null;
        }

        $width = imagesx($sample);
        $height = imagesy($sample);
        $sum = $sumSquares = 0.0;
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgb = imagecolorat($sample, $x, $y);
                $luma = 0.299 * (($rgb >> 16) & 0xFF) + 0.587 * (($rgb >> 8) & 0xFF) + 0.114 * ($rgb & 0xFF);
                $sum += $luma;
                $sumSquares += $luma * $luma;
            }
        }

        $pixels = max(1, $width * $height);
        $mean = $sum / $pixels;

        return ['mean' => $mean, 'stddev' => sqrt(max(0.0, $sumSquares / $pixels - $mean * $mean))];
    }
}

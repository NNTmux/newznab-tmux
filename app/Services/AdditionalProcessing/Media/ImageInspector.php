<?php

declare(strict_types=1);

namespace App\Services\AdditionalProcessing\Media;

/**
 * Decides whether a downloaded image is worth publishing as a release's sample picture:
 * a complete JPEG, PNG or WebP, large enough to show something, and not blank.
 */
final readonly class ImageInspector
{
    /** Smallest width and height accepted; smaller images are icons and banners. */
    private const int MIN_EDGE = 150;

    /** Larger images are not decoded for the blank check; GD would need too much memory. */
    private const int MAX_MEASURED_PIXELS = 12_000_000;

    public function __construct(private FrameQualityScorer $scorer, private float $minStddev) {}

    public function isUsable(string $path): bool
    {
        $info = @getimagesize($path);
        if ($info === false || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)
            || $info[0] < self::MIN_EDGE || $info[1] < self::MIN_EDGE
            || ! $this->isComplete($path, $info[2])
        ) {
            return false;
        }

        if ($info[0] * $info[1] > self::MAX_MEASURED_PIXELS) {
            return true;
        }

        $measure = $this->scorer->measure($path);

        return $measure !== null && $measure['stddev'] >= $this->minStddev;
    }

    /**
     * A download cut short decodes to an image whose lower part is grey; its end marker is missing.
     */
    private function isComplete(string $path, int $type): bool
    {
        $size = (int) @filesize($path);
        $handle = @fopen($path, 'rb');
        if ($handle === false || $size < 16) {
            return false;
        }

        try {
            if ($type === IMAGETYPE_WEBP) {
                $header = (string) fread($handle, 8);
                $declared = unpack('V', substr($header, 4, 4));

                return $declared !== false && $size >= $declared[1] + 8;
            }

            fseek($handle, max(0, $size - 4096));
            $tail = rtrim((string) fread($handle, 4096), "\0\r\n ");

            return $type === IMAGETYPE_PNG
                ? str_contains(substr($tail, -64), 'IEND')
                : str_contains(substr($tail, -1024), "\xFF\xD9");
        } finally {
            fclose($handle);
        }
    }
}

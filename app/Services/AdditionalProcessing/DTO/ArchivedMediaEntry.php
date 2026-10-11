<?php

declare(strict_types=1);

namespace App\Services\AdditionalProcessing\DTO;

use App\Services\AdditionalProcessing\Enums\VideoContainer;

/**
 * A video stored uncompressed in a downloaded archive volume, and the bytes of it we hold.
 */
final readonly class ArchivedMediaEntry
{
    /**
     * @param  int  $sourceIndex  NZB file of the volume
     * @param  string  $headBytes  start of the video, as far as it was downloaded
     * @param  int  $rangeStart  offset of the video's first byte in the volume
     * @param  int  $inVolumeEnd  offset of the video's last byte in the volume
     * @param  int  $volumeBytesDownloaded  leading bytes of the volume that were downloaded
     * @param  int  $volumeSegmentsDownloaded  leading NZB segments those bytes came from
     */
    public function __construct(
        public string $name,
        public int $realSize,
        public int $sourceIndex,
        public string $headBytes,
        public int $rangeStart,
        public int $inVolumeEnd,
        public int $volumeBytesDownloaded,
        public int $volumeSegmentsDownloaded,
        public bool $isSample,
        public ?VideoContainer $container,
    ) {}

    public function isComplete(): bool
    {
        return strlen($this->headBytes) >= $this->realSize;
    }

    /**
     * Bytes that can still be appended from later segments of the same volume, up to $target in all.
     */
    public function continuationBytes(int $target): int
    {
        // Only a head that runs to the end of the downloaded data can be continued.
        if ($this->rangeStart + strlen($this->headBytes) !== $this->volumeBytesDownloaded) {
            return 0;
        }

        $wanted = min($target, $this->realSize) - strlen($this->headBytes);
        $leftInVolume = $this->inVolumeEnd + 1 - $this->volumeBytesDownloaded;

        return max(0, min($wanted, $leftInVolume));
    }
}

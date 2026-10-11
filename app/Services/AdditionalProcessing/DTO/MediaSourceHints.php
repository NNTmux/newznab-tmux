<?php

declare(strict_types=1);

namespace App\Services\AdditionalProcessing\DTO;

use App\Services\AdditionalProcessing\Enums\SourcePosition;

/**
 * What is known about a partial media file handed to ffmpeg.
 */
final readonly class MediaSourceHints
{
    /**
     * @param  int|null  $realSize  size of the whole file, when the release told us
     * @param  int  $availableBytes  bytes of it that were downloaded
     */
    public function __construct(
        public SourcePosition $position,
        public ?int $realSize = null,
        public int $availableBytes = 0,
    ) {}

    public static function complete(): self
    {
        return new self(SourcePosition::Complete);
    }

    public static function head(?int $realSize, int $availableBytes): self
    {
        if ($realSize !== null && $realSize > 0 && $availableBytes >= $realSize) {
            return new self(SourcePosition::Complete, $realSize, $availableBytes);
        }

        return new self(SourcePosition::Head, $realSize !== null && $realSize > 0 ? $realSize : null, $availableBytes);
    }

    public static function mid(int $availableBytes = 0): self
    {
        return new self(SourcePosition::Mid, null, $availableBytes);
    }

    /**
     * Fraction of the file that was downloaded, when the real size is known.
     */
    public function availableFraction(): ?float
    {
        if ($this->position === SourcePosition::Complete) {
            return 1.0;
        }

        if ($this->realSize === null || $this->availableBytes <= 0) {
            return null;
        }

        return min(1.0, $this->availableBytes / $this->realSize);
    }
}

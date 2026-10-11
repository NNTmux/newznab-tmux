<?php

declare(strict_types=1);

namespace App\Services\Yenc;

/**
 * The file an article belongs to, as its yEnc header describes it.
 */
final readonly class YencPart
{
    /**
     * @param  int  $size  size of the whole file
     * @param  int|null  $begin  1-based offset of this part's first byte in the file
     * @param  int|null  $end  1-based offset of this part's last byte in the file
     */
    public function __construct(
        public string $name,
        public int $size,
        public ?int $part = null,
        public ?int $begin = null,
        public ?int $end = null,
    ) {}

    /**
     * 0-based offset of this article's data in the file.
     */
    public function offset(): int
    {
        return $this->begin === null ? 0 : $this->begin - 1;
    }

    /**
     * Bytes this article carries, or null when its header does not say.
     */
    public function length(): ?int
    {
        return $this->begin === null || $this->end === null ? null : $this->end - $this->begin + 1;
    }
}

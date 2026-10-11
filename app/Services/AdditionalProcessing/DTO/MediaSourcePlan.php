<?php

declare(strict_types=1);

namespace App\Services\AdditionalProcessing\DTO;

/**
 * What the release's PAR2 index told us about its obfuscated files.
 */
final readonly class MediaSourcePlan
{
    /**
     * @param  array<int, MappedNzbFile>  $mappedFiles  keyed by NZB source index
     * @param  int|null  $articlePartSize  decoded bytes per article, when an article header showed it
     */
    public function __construct(
        public array $mappedFiles = [],
        public ?int $articlePartSize = null,
    ) {}

    public function realSizeOf(int $sourceIndex): ?int
    {
        return $this->mappedFiles[$sourceIndex]->file->size ?? null;
    }
}

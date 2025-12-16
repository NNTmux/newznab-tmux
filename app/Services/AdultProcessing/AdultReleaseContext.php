<?php

namespace App\Services\AdultProcessing;

/**
 * Value object containing release information for adult movie processing.
 */
class AdultReleaseContext
{
    public function __construct(
        public readonly int $releaseId,
        public readonly string $searchName,
        public readonly string $cleanTitle,
        public readonly ?string $guid = null,
    ) {}

    /**
     * Create from a Release model array/object.
     */
    public static function fromRelease(array|object $release, string $cleanTitle): self
    {
        $data = is_array($release) ? $release : (array) $release;

        return new self(
            releaseId: (int) ($data['id'] ?? 0),
            searchName: $data['searchname'] ?? '',
            cleanTitle: $cleanTitle,
            guid: $data['guid'] ?? null,
        );
    }

    /**
     * Create from a movie title string.
     */
    public static function fromTitle(string $title): self
    {
        return new self(
            releaseId: 0,
            searchName: $title,
            cleanTitle: $title,
            guid: null,
        );
    }
}


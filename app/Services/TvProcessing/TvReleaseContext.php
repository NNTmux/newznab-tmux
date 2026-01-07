<?php

namespace App\Services\TvProcessing;

/**
 * Value object containing release information for TV processing.
 */
class TvReleaseContext
{
    public function __construct(
        public readonly int $releaseId,
        public readonly string $searchName,
        public readonly int $groupsId,
        public readonly int $categoriesId,
        public readonly int $videosId = 0,
        public readonly int $tvEpisodesId = 0,
        public readonly ?string $guid = null,
        public readonly ?string $leftGuid = null,
    ) {}

    /**
     * Create from a Release model array/object.
     */
    public static function fromRelease(array|object $release): self
    {
        // Handle Eloquent models properly - use toArray() instead of casting
        if ($release instanceof \Illuminate\Database\Eloquent\Model) {
            $data = $release->toArray();
        } else {
            $data = is_array($release) ? $release : (array) $release;
        }

        return new self(
            releaseId: (int) ($data['id'] ?? 0),
            searchName: $data['searchname'] ?? '',
            groupsId: (int) ($data['groups_id'] ?? 0),
            categoriesId: (int) ($data['categories_id'] ?? 0),
            videosId: (int) ($data['videos_id'] ?? 0),
            tvEpisodesId: (int) ($data['tv_episodes_id'] ?? 0),
            guid: $data['guid'] ?? null,
            leftGuid: $data['leftguid'] ?? null,
        );
    }

    /**
     * Check if the release name matches a pattern.
     */
    public function matchesPattern(string $pattern): bool
    {
        return (bool) preg_match($pattern, $this->searchName);
    }
}

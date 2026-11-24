<?php

declare(strict_types=1);

namespace App\Services\Nzb;

/**
 * What the NZB writer needs while streaming a release's binaries into the temporary file.
 */
final readonly class NzbStreamContext
{
    /**
     * @param  array<int, object>  $collections  Collection rows keyed by id
     * @param  array<int, list<string>>  $groupsByCollection
     * @param  list<int>  $collectionIds
     */
    public function __construct(
        public array $collections,
        public array $groupsByCollection,
        public array $collectionIds,
        public string $path,
        public string $tempPath,
    ) {}
}

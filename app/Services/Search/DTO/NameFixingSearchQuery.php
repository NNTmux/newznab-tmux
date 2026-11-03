<?php

declare(strict_types=1);

namespace App\Services\Search\DTO;

use InvalidArgumentException;

final readonly class NameFixingSearchQuery
{
    public const SOURCE_NFO = 'nfo';

    public const SOURCE_FILES = 'files';

    public const SOURCE_SRR = 'srr';

    public const SOURCE_XXX = 'xxx';

    public const SOURCE_UID = 'uid';

    public const SOURCE_MEDIA = 'media';

    public const SOURCE_MEDIA_MOVIE = 'media_movie';

    /** @var list<string> */
    private const SOURCES = [
        self::SOURCE_NFO,
        self::SOURCE_FILES,
        self::SOURCE_SRR,
        self::SOURCE_XXX,
        self::SOURCE_UID,
        self::SOURCE_MEDIA,
        self::SOURCE_MEDIA_MOVIE,
    ];

    /**
     * @param  list<int>  $categoryIds
     * @param  list<int>  $releaseIds
     * @param  list<string>  $matchValues
     */
    public function __construct(
        public string $source,
        public int $afterId = 0,
        public int $beforeId = 0,
        public int $limit = 1000,
        public array $categoryIds = [],
        public int $addedAfter = 0,
        public array $releaseIds = [],
        public array $matchValues = [],
        public bool $descending = false,
    ) {
        if (! in_array($this->source, self::SOURCES, true)) {
            throw new InvalidArgumentException("Unsupported name-fixing search source [{$this->source}].");
        }
    }

    /** @return list<int> */
    public function normalizedCategoryIds(): array
    {
        return $this->positiveUniqueIntegers($this->categoryIds);
    }

    /** @return list<int> */
    public function normalizedReleaseIds(): array
    {
        return $this->positiveUniqueIntegers($this->releaseIds);
    }

    /** @return list<string> */
    public function normalizedMatchValues(): array
    {
        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $value): string => trim((string) $value), $this->matchValues),
            static fn (string $value): bool => $value !== ''
        )));
    }

    public function normalizedLimit(int $maximum = 10000): int
    {
        return max(1, min($this->limit, max(1, $maximum)));
    }

    /**
     * @param  list<mixed>  $values
     * @return list<int>
     */
    private function positiveUniqueIntegers(array $values): array
    {
        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $value): int => (int) $value, $values),
            static fn (int $value): bool => $value > 0
        )));
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Search\DTO;

final readonly class NameFixingSearchResult
{
    /**
     * @param  list<int>  $ids
     * @param  array<int, array<string, mixed>>  $documents
     */
    public function __construct(
        public array $ids,
        public array $documents,
        public int $total,
        public bool $available,
        public bool $hasMore,
        public string $driver,
    ) {}

    public static function unavailable(string $driver): self
    {
        return new self([], [], 0, false, false, $driver);
    }
}

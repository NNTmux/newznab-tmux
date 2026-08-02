<?php

declare(strict_types=1);

namespace App\Services\Search\DTO;

final readonly class SearchPage
{
    /**
     * @param  list<int>  $ids
     * @param  list<int|float|string>  $lastSortValues
     * @param  list<array<string, mixed>>  $documents
     */
    public function __construct(
        public array $ids,
        public int $total,
        public bool $fuzzy,
        public string $driver,
        public bool $available = true,
        public float $durationMs = 0.0,
        public array $lastSortValues = [],
        public bool $hasMore = false,
        public array $documents = [],
    ) {}

    /** @return array{ids: list<int>, total: int, fuzzy: bool} */
    public function legacy(): array
    {
        return ['ids' => $this->ids, 'total' => $this->total, 'fuzzy' => $this->fuzzy];
    }
}

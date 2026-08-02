<?php

declare(strict_types=1);

namespace App\Services\Search\DTO;

final readonly class ReleaseSearchQuery
{
    /**
     * @param  array<string, mixed>|string|null  $phrases
     * @param  list<int>|null  $categoryIds
     * @param  list<int>  $excludedCategoryIds
     * @param  list<int>|null  $releaseIds
     */
    public function __construct(
        public array|string|null $phrases = null,
        public ?array $categoryIds = null,
        public array $excludedCategoryIds = [],
        public ?array $releaseIds = null,
        public int $minSize = 0,
        public int $maxSize = 0,
        public int $maxAgeDays = -1,
        public int $minDate = 0,
        public int $maxDate = 0,
        public ?int $groupId = null,
        public bool $passwordAllowRar = false,
        public ?int $passwordStatusMin = null,
        public string $sortField = 'postdate_ts',
        public string $sortDirection = 'desc',
        public bool $tryFuzzy = true,
        public int $limit = 100,
        public int $offset = 0,
        public ?SearchCursor $cursor = null,
        public bool $trackTotal = true,
        public bool $includeDocuments = false,
    ) {}

    /** @param array<string, mixed> $criteria */
    public static function fromCriteria(array $criteria, int $limit, int $offset = 0, ?SearchCursor $cursor = null): self
    {
        return new self(
            phrases: is_array($criteria['phrases'] ?? null) || is_string($criteria['phrases'] ?? null) ? $criteria['phrases'] : null,
            categoryIds: is_array($criteria['category_ids'] ?? null) ? array_map('intval', $criteria['category_ids']) : null,
            excludedCategoryIds: is_array($criteria['excluded_category_ids'] ?? null) ? array_map('intval', $criteria['excluded_category_ids']) : [],
            releaseIds: is_array($criteria['release_ids'] ?? null) ? array_map('intval', $criteria['release_ids']) : null,
            minSize: (int) ($criteria['min_size'] ?? 0),
            maxSize: (int) ($criteria['max_size'] ?? 0),
            maxAgeDays: (int) ($criteria['max_age_days'] ?? -1),
            minDate: (int) ($criteria['min_date'] ?? 0),
            maxDate: (int) ($criteria['max_date'] ?? 0),
            groupId: isset($criteria['groups_id']) ? (int) $criteria['groups_id'] : null,
            passwordAllowRar: (bool) ($criteria['password_allow_rar'] ?? false),
            passwordStatusMin: isset($criteria['password_status_min']) ? (int) $criteria['password_status_min'] : null,
            sortField: (string) ($criteria['sort_field'] ?? 'postdate_ts'),
            sortDirection: (string) ($criteria['sort_dir'] ?? 'desc'),
            tryFuzzy: (bool) ($criteria['try_fuzzy'] ?? true),
            limit: max(1, $limit),
            offset: max(0, $offset),
            cursor: $cursor,
            trackTotal: (bool) ($criteria['track_total'] ?? true),
            includeDocuments: (bool) ($criteria['include_documents'] ?? false),
        );
    }

    /** @return array<string, mixed> */
    public function criteria(): array
    {
        return [
            'phrases' => $this->phrases,
            'category_ids' => $this->categoryIds,
            'excluded_category_ids' => $this->excludedCategoryIds,
            'release_ids' => $this->releaseIds,
            'min_size' => max(0, $this->minSize),
            'max_size' => max(0, $this->maxSize),
            'max_age_days' => $this->maxAgeDays,
            'min_date' => max(0, $this->minDate),
            'max_date' => max(0, $this->maxDate),
            'groups_id' => $this->groupId,
            'password_allow_rar' => $this->passwordAllowRar,
            'password_status_min' => $this->passwordStatusMin,
            'sort_field' => $this->normalizedSortField(),
            'sort_dir' => $this->normalizedSortDirection(),
            'try_fuzzy' => $this->tryFuzzy,
            'cursor_sort' => $this->cursor?->sortValues,
            'track_total' => $this->trackTotal,
            'include_documents' => $this->includeDocuments,
        ];
    }

    public function normalizedSortField(): string
    {
        return in_array($this->sortField, ['postdate_ts', 'adddate_ts', 'size', 'totalpart', 'grabs', 'categories_id', 'id'], true)
            ? $this->sortField
            : 'postdate_ts';
    }

    public function normalizedSortDirection(): string
    {
        return strtolower($this->sortDirection) === 'asc' ? 'asc' : 'desc';
    }
}

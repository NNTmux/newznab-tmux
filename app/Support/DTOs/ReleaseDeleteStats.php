<?php

declare(strict_types=1);

namespace App\Support\DTOs;

/**
 * Data transfer object for release deletion statistics.
 *
 * Tracks the number of releases deleted by category during cleanup operations.
 */
final readonly class ReleaseDeleteStats
{
    public function __construct(
        public int $retention = 0,
        public int $password = 0,
        public int $duplicate = 0,
        public int $completion = 0,
        public int $disabledCategory = 0,
        public int $categoryMinSize = 0,
        public int $disabledGenre = 0,
        public int $miscOther = 0,
        public int $miscHashed = 0,
    ) {}

    /**
     * Create a new instance with an incremented counter.
     */
    public function increment(string $field): self
    {
        $values = [
            'retention' => $this->retention,
            'password' => $this->password,
            'duplicate' => $this->duplicate,
            'completion' => $this->completion,
            'disabledCategory' => $this->disabledCategory,
            'categoryMinSize' => $this->categoryMinSize,
            'disabledGenre' => $this->disabledGenre,
            'miscOther' => $this->miscOther,
            'miscHashed' => $this->miscHashed,
        ];

        if (isset($values[$field])) {
            $values[$field]++;
        }

        return new self(...$values);
    }

    /**
     * Get the total number of deleted releases.
     */
    public function total(): int
    {
        return $this->retention
            + $this->password
            + $this->duplicate
            + $this->completion
            + $this->disabledCategory
            + $this->categoryMinSize
            + $this->disabledGenre
            + $this->miscOther
            + $this->miscHashed;
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return [
            'retention' => $this->retention,
            'password' => $this->password,
            'duplicate' => $this->duplicate,
            'completion' => $this->completion,
            'disabledCategory' => $this->disabledCategory,
            'categoryMinSize' => $this->categoryMinSize,
            'disabledGenre' => $this->disabledGenre,
            'miscOther' => $this->miscOther,
            'miscHashed' => $this->miscHashed,
        ];
    }

    /**
     * Create from an array of values.
     *
     * @param  array<string, int>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            retention: $data['retention'] ?? 0,
            password: $data['password'] ?? 0,
            duplicate: $data['duplicate'] ?? 0,
            completion: $data['completion'] ?? 0,
            disabledCategory: $data['disabledCategory'] ?? 0,
            categoryMinSize: $data['categoryMinSize'] ?? 0,
            disabledGenre: $data['disabledGenre'] ?? 0,
            miscOther: $data['miscOther'] ?? 0,
            miscHashed: $data['miscHashed'] ?? 0,
        );
    }
}

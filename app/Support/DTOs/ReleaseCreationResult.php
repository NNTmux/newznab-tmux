<?php

declare(strict_types=1);

namespace App\Support\DTOs;

/**
 * Data transfer object for release creation results.
 */
final readonly class ReleaseCreationResult
{
    public function __construct(
        public int $added = 0,
        public int $dupes = 0,
    ) {}

    /**
     * Get total number of processed collections.
     */
    public function total(): int
    {
        return $this->added + $this->dupes;
    }

    /**
     * Check if any releases were added.
     */
    public function hasAddedReleases(): bool
    {
        return $this->added > 0;
    }

    /**
     * Create from array.
     *
     * @param array{added?: int, dupes?: int} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            added: $data['added'] ?? 0,
            dupes: $data['dupes'] ?? 0,
        );
    }

    /**
     * Convert to array.
     *
     * @return array{added: int, dupes: int}
     */
    public function toArray(): array
    {
        return [
            'added' => $this->added,
            'dupes' => $this->dupes,
        ];
    }
}


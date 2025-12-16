<?php

declare(strict_types=1);

namespace App\Services\NameFixing\DTO;

/**
 * Data Transfer Object for name fix results.
 *
 * Encapsulates the result of a name fixing operation, including the new name,
 * method used, and optional metadata.
 */
final class NameFixResult
{
    public function __construct(
        public readonly string $newName,
        public readonly string $method,
        public readonly string $checkerName,
        public readonly int $preDbId = 0,
        public readonly float $confidence = 1.0,
        public readonly array $metadata = [],
    ) {}

    /**
     * Create a result from a pattern match.
     */
    public static function fromMatch(
        string $newName,
        string $method,
        string $checkerName,
        int $preDbId = 0,
        float $confidence = 1.0
    ): self {
        return new self(
            newName: $newName,
            method: $method,
            checkerName: $checkerName,
            preDbId: $preDbId,
            confidence: $confidence,
        );
    }

    /**
     * Create a result from PreDB match.
     */
    public static function fromPreDb(
        string $newName,
        int $preDbId,
        string $source,
        string $checkerName = 'PreDB'
    ): self {
        return new self(
            newName: $newName,
            method: "PreDB: {$source}",
            checkerName: $checkerName,
            preDbId: $preDbId,
            confidence: 1.0,
            metadata: ['source' => $source],
        );
    }

    /**
     * Check if this result has a PreDB ID.
     */
    public function hasPreDbId(): bool
    {
        return $this->preDbId > 0;
    }

    /**
     * Get a formatted method string for logging.
     */
    public function getFormattedMethod(): string
    {
        return "{$this->checkerName}: {$this->method}";
    }
}


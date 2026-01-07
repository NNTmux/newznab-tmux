<?php

namespace App\Services\Categorization;

use App\Models\Category;

/**
 * Value object representing the result of a categorization attempt.
 */
class CategorizationResult
{
    /**
     * @param  int  $categoryId  The determined category ID
     * @param  float  $confidence  Confidence level (0.0 to 1.0)
     * @param  string  $matchedBy  Description of what matched
     * @param  array  $debug  Additional debug information
     */
    public function __construct(
        public readonly int $categoryId = Category::OTHER_MISC,
        public readonly float $confidence = 0.0,
        public readonly string $matchedBy = 'none',
        public readonly array $debug = []
    ) {}

    /**
     * Check if this result represents a successful categorization.
     */
    public function isSuccessful(): bool
    {
        return $this->categoryId !== Category::OTHER_MISC && $this->confidence > 0;
    }

    /**
     * Check if this result should take precedence over another.
     */
    public function shouldOverride(CategorizationResult $other): bool
    {
        // Higher confidence always wins
        if ($this->confidence > $other->confidence) {
            return true;
        }

        // If same confidence, prefer non-misc categories
        if ($this->confidence === $other->confidence) {
            return $this->categoryId !== Category::OTHER_MISC && $other->categoryId === Category::OTHER_MISC;
        }

        return false;
    }

    /**
     * Create a failed/empty result.
     */
    public static function noMatch(): self
    {
        return new self(Category::OTHER_MISC, 0.0, 'no_match');
    }

    /**
     * Create a result with debug info merged.
     */
    public function withDebug(array $additionalDebug): self
    {
        return new self(
            $this->categoryId,
            $this->confidence,
            $this->matchedBy,
            array_merge($this->debug, $additionalDebug)
        );
    }
}

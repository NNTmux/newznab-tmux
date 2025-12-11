<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Binary file completion status during collection processing.
 */
enum FileCompletionStatus: int
{
    /** File/binary is still incomplete (missing parts) */
    case Incomplete = 0;

    /** File/binary has all parts */
    case Complete = 1;

    /**
     * Get human-readable description.
     */
    public function description(): string
    {
        return match ($this) {
            self::Incomplete => 'Incomplete',
            self::Complete => 'Complete',
        };
    }

    /**
     * Check if file is complete.
     */
    public function isComplete(): bool
    {
        return $this === self::Complete;
    }
}


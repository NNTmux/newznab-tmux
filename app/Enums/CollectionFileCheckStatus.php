<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Collection filecheck status values used during release processing.
 *
 * These statuses track the progress of collections through the release
 * creation pipeline, from initial discovery to NZB file creation.
 */
enum CollectionFileCheckStatus: int
{
    /** Default status for newly discovered collections */
    case Default = 0;

    /** Collection has all expected binary files */
    case CompleteCollection = 1;

    /** All parts of binaries are complete */
    case CompleteParts = 2;

    /** Collection size has been calculated */
    case Sized = 3;

    /** Collection has been converted to a release */
    case Inserted = 4;

    /** Collection marked for deletion */
    case Delete = 5;

    /** Temporary complete status during processing */
    case TempComplete = 15;

    /** Collection has zero-numbered parts (special handling) */
    case ZeroPart = 16;

    /**
     * Get the human-readable description for this status.
     */
    public function description(): string
    {
        return match ($this) {
            self::Default => 'Newly discovered',
            self::CompleteCollection => 'Complete collection',
            self::CompleteParts => 'Complete parts',
            self::Sized => 'Size calculated',
            self::Inserted => 'Inserted as release',
            self::Delete => 'Marked for deletion',
            self::TempComplete => 'Temporarily complete',
            self::ZeroPart => 'Zero-part collection',
        };
    }

    /**
     * Check if this status indicates processing is complete.
     */
    public function isProcessingComplete(): bool
    {
        return match ($this) {
            self::Inserted, self::Delete => true,
            default => false,
        };
    }

    /**
     * Get statuses that indicate a collection is ready for release creation.
     *
     * @return array<self>
     */
    public static function readyForRelease(): array
    {
        return [self::Sized];
    }

    /**
     * Get statuses that indicate a collection is still being processed.
     *
     * @return array<self>
     */
    public static function inProgress(): array
    {
        return [
            self::Default,
            self::CompleteCollection,
            self::CompleteParts,
            self::TempComplete,
            self::ZeroPart,
        ];
    }
}

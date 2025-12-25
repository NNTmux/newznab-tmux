<?php

namespace App\Services\Search\Contracts;

/**
 * Interface for search drivers.
 *
 * Extends SearchServiceInterface with additional driver-specific methods.
 */
interface SearchDriverInterface extends SearchServiceInterface
{
    /**
     * Get the driver name.
     */
    public function getDriverName(): string;

    /**
     * Check if the search service is available.
     */
    public function isAvailable(): bool;

    /**
     * Escape a search string for the specific driver.
     */
    public static function escapeString(string $string): string;
}


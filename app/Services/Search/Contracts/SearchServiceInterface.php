<?php

namespace App\Services\Search\Contracts;

/**
 * Interface for full-text search services (ManticoreSearch, Elasticsearch).
 *
 * Provides a common API for release and predb search, indexing, and autocomplete.
 */
interface SearchServiceInterface
{
    /**
     * Check if autocomplete is enabled.
     */
    public function isAutocompleteEnabled(): bool;

    /**
     * Check if suggest is enabled.
     */
    public function isSuggestEnabled(): bool;

    /**
     * Get the releases index name.
     */
    public function getReleasesIndex(): string;

    /**
     * Get the predb index name.
     */
    public function getPredbIndex(): string;

    /**
     * Insert a release into the search index.
     *
     * @param  array  $parameters  Release data with 'id', 'name', 'searchname', 'fromname', 'categories_id', 'filename'
     */
    public function insertRelease(array $parameters): void;

    /**
     * Update a release in the search index.
     *
     * @param  int|string  $releaseID  Release ID
     */
    public function updateRelease(int|string $releaseID): void;

    /**
     * Delete a release from the search index.
     *
     * @param  int  $id  Release ID
     */
    public function deleteRelease(int $id): void;

    /**
     * Insert a predb record into the search index.
     *
     * @param  array  $parameters  Predb data with 'id', 'title', 'filename', 'source'
     */
    public function insertPredb(array $parameters): void;

    /**
     * Update a predb record in the search index.
     *
     * @param  array  $parameters  Predb data with 'id', 'title', 'filename', 'source'
     */
    public function updatePreDb(array $parameters): void;

    /**
     * Search the releases index.
     *
     * @param  array|string  $phrases  Search phrases
     * @param  int  $limit  Maximum number of results
     * @return array Array of release IDs
     */
    public function searchReleases(array|string $phrases, int $limit = 1000): array;

    /**
     * Search the predb index.
     *
     * @param  array|string  $searchTerm  Search term(s)
     * @return array Array of predb records
     */
    public function searchPredb(array|string $searchTerm): array;

    /**
     * Get autocomplete suggestions for a search query.
     *
     * @param  string  $query  The partial search query
     * @param  string|null  $index  Index to search (defaults to releases index)
     * @return array<array{suggest: string, distance: int, docs: int}>
     */
    public function autocomplete(string $query, ?string $index = null): array;

    /**
     * Get spell correction suggestions ("Did you mean?").
     *
     * @param  string  $query  The search query to check
     * @param  string|null  $index  Index to use for suggestions
     * @return array<array{suggest: string, distance: int, docs: int}>
     */
    public function suggest(string $query, ?string $index = null): array;
    /**
     * Truncate/clear an index (remove all documents).
     *
     * @param  array|string  $indexes  Index name(s) to truncate
     */
    public function truncateIndex(array|string $indexes): void;

    /**
     * Optimize index for better search performance.
     */
    public function optimizeIndex(): void;

    /**
     * Bulk insert multiple releases into the index.
     *
     * @param  array  $releases  Array of release data arrays
     * @return array Results with 'success' and 'errors' counts
     */
    public function bulkInsertReleases(array $releases): array;

    /**
     * Delete a predb record from the index.
     *
     * @param  int  $id  Predb ID
     */
    public function deletePreDb(int $id): void;
}


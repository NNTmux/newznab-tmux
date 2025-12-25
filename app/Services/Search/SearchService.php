<?php

namespace App\Services\Search;

use App\Services\Search\Contracts\SearchDriverInterface;
use App\Services\Search\Contracts\SearchServiceInterface;
use App\Services\Search\Drivers\ElasticSearchDriver;
use App\Services\Search\Drivers\ManticoreSearchDriver;
use Illuminate\Support\Manager;

/**
 * Search Service Manager - handles driver resolution for search functionality.
 *
 * This service manager provides a unified interface for full-text search operations,
 * supporting multiple backends (ManticoreSearch, Elasticsearch) that can be configured
 * via the SEARCH_DRIVER environment variable.
 */
class SearchService extends Manager implements SearchServiceInterface
{
    /**
     * Get the default driver name.
     */
    public function getDefaultDriver(): string
    {
        return $this->config->get('search.default', 'manticore');
    }

    /**
     * Create the ManticoreSearch driver instance.
     */
    protected function createManticoreDriver(): SearchDriverInterface
    {
        $config = $this->config->get('search.drivers.manticore', []);

        return new ManticoreSearchDriver($config);
    }

    /**
     * Create the Elasticsearch driver instance.
     */
    protected function createElasticsearchDriver(): SearchDriverInterface
    {
        $config = $this->config->get('search.drivers.elasticsearch', []);

        return new ElasticSearchDriver($config);
    }

    /**
     * Get a driver instance.
     *
     * @param  string|null  $driver
     * @return SearchDriverInterface
     *
     * @throws InvalidArgumentException
     */
    public function driver($driver = null): SearchDriverInterface
    {
        return parent::driver($driver);
    }

    /**
     * Check if the current driver is available.
     */
    public function isAvailable(): bool
    {
        return $this->driver()->isAvailable();
    }

    /**
     * Get the current driver name.
     */
    public function getCurrentDriver(): string
    {
        return $this->driver()->getDriverName();
    }

    /**
     * Escape a search string using the current driver's escape method.
     */
    public function escapeString(string $string): string
    {
        $driverClass = get_class($this->driver());

        return $driverClass::escapeString($string);
    }

    // Implement SearchServiceInterface methods by delegating to the current driver

    /**
     * Check if autocomplete is enabled.
     */
    public function isAutocompleteEnabled(): bool
    {
        return $this->driver()->isAutocompleteEnabled();
    }

    /**
     * Check if suggest is enabled.
     */
    public function isSuggestEnabled(): bool
    {
        return $this->driver()->isSuggestEnabled();
    }

    /**
     * Get the releases index name.
     */
    public function getReleasesIndex(): string
    {
        return $this->driver()->getReleasesIndex();
    }

    /**
     * Get the predb index name.
     */
    public function getPredbIndex(): string
    {
        return $this->driver()->getPredbIndex();
    }

    /**
     * Insert a release into the search index.
     */
    public function insertRelease(array $parameters): void
    {
        $this->driver()->insertRelease($parameters);
    }

    /**
     * Update a release in the search index.
     */
    public function updateRelease(int|string $releaseID): void
    {
        $this->driver()->updateRelease($releaseID);
    }

    /**
     * Delete a release from the search index.
     */
    public function deleteRelease(int $id): void
    {
        $this->driver()->deleteRelease($id);
    }

    /**
     * Insert a predb record into the search index.
     */
    public function insertPredb(array $parameters): void
    {
        $this->driver()->insertPredb($parameters);
    }

    /**
     * Update a predb record in the search index.
     */
    public function updatePreDb(array $parameters): void
    {
        $this->driver()->updatePreDb($parameters);
    }

    /**
     * Search the releases index.
     */
    public function searchReleases(array|string $phrases, int $limit = 1000): array
    {
        return $this->driver()->searchReleases($phrases, $limit);
    }

    /**
     * Search the predb index.
     */
    public function searchPredb(array|string $searchTerm): array
    {
        return $this->driver()->searchPredb($searchTerm);
    }

    /**
     * Get autocomplete suggestions for a search query.
     */
    public function autocomplete(string $query, ?string $index = null): array
    {
        return $this->driver()->autocomplete($query, $index);
    }

    /**
     * Get spell correction suggestions.
     */
    public function suggest(string $query, ?string $index = null): array
    {
        return $this->driver()->suggest($query, $index);
    }

    /**
     * Truncate/clear an index (remove all documents).
     *
     * @param  array|string  $indexes  Index name(s) to truncate
     */
    public function truncateIndex(array|string $indexes): void
    {
        $this->driver()->truncateIndex($indexes);
    }

    /**
     * Optimize index for better search performance.
     */
    public function optimizeIndex(): void
    {
        $this->driver()->optimizeIndex();
    }

    /**
     * Bulk insert multiple releases into the index.
     *
     * @param  array  $releases  Array of release data arrays
     * @return array Results with 'success' and 'errors' counts
     */
    public function bulkInsertReleases(array $releases): array
    {
        return $this->driver()->bulkInsertReleases($releases);
    }

    /**
     * Delete a predb record from the index.
     *
     * @param  int  $id  Predb ID
     */
    public function deletePreDb(int $id): void
    {
        $this->driver()->deletePreDb($id);
    }
}


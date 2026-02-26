<?php

declare(strict_types=1);

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
     *
     * @throws \InvalidArgumentException
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
     * Check if fuzzy search is enabled.
     */
    public function isFuzzyEnabled(): bool
    {
        return $this->driver()->isFuzzyEnabled();
    }

    /**
     * Get fuzzy search configuration.
     *
     * @return array<string, mixed>
     */
    public function getFuzzyConfig(): array
    {
        return $this->driver()->getFuzzyConfig();
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
     *
     * @param  array<string, mixed>  $parameters
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
     *
     * @param  array<string, mixed>  $parameters
     */
    public function insertPredb(array $parameters): void
    {
        $this->driver()->insertPredb($parameters);
    }

    /**
     * Update a predb record in the search index.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function updatePreDb(array $parameters): void
    {
        $this->driver()->updatePreDb($parameters);
    }

    /**
     * Search the releases index.
     *
     * @param  array<string, mixed>  $phrases
     * @return array<string, mixed>
     */
    public function searchReleases(array|string $phrases, int $limit = 1000): array
    {
        return $this->driver()->searchReleases($phrases, $limit);
    }

    /**
     * Search releases with fuzzy fallback.
     *
     * If exact search returns no results and fuzzy is enabled, this method
     * will automatically try a fuzzy search as a fallback.
     *
     * @param  array<string, mixed>  $phrases
     * @return array<string, mixed>
     */
    public function searchReleasesWithFuzzy(array|string $phrases, int $limit = 1000, bool $forceFuzzy = false): array
    {
        return $this->driver()->searchReleasesWithFuzzy($phrases, $limit, $forceFuzzy);
    }

    /**
     * Perform fuzzy search on releases index.
     *
     * @param  array<string, mixed>  $phrases
     * @return array<string, mixed>
     */
    public function fuzzySearchReleases(array|string $phrases, int $limit = 1000): array
    {
        return $this->driver()->fuzzySearchReleases($phrases, $limit);
    }

    /**
     * Search the predb index.
     *
     * @param  array<string, mixed>  $searchTerm
     * @return array<string, mixed>
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
     * @param  array<string, mixed>|string  $indexes  Index name(s) to truncate
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
     * @param  array<string, mixed>  $releases  Array of release data arrays
     * @return array<string, mixed> Results with 'success' and 'errors' counts
     */
    public function bulkInsertReleases(array $releases): array
    {
        return $this->driver()->bulkInsertReleases($releases);
    }

    /**
     * Bulk insert multiple predb records into the index.
     *
     * @param  array<string, mixed>  $predbRecords  Array of predb data arrays
     * @return array<string, mixed> Results with 'success' and 'errors' counts
     */
    public function bulkInsertPredb(array $predbRecords): array
    {
        return $this->driver()->bulkInsertPredb($predbRecords);
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

    /**
     * Get the movies index name.
     */
    public function getMoviesIndex(): string
    {
        return $this->driver()->getMoviesIndex();
    }

    /**
     * Get the TV shows index name.
     */
    public function getTvShowsIndex(): string
    {
        return $this->driver()->getTvShowsIndex();
    }

    /**
     * Insert a movie into the movies search index.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function insertMovie(array $parameters): void
    {
        $this->driver()->insertMovie($parameters);
    }

    /**
     * Update a movie in the search index.
     */
    public function updateMovie(int $movieId): void
    {
        $this->driver()->updateMovie($movieId);
    }

    /**
     * Delete a movie from the search index.
     */
    public function deleteMovie(int $id): void
    {
        $this->driver()->deleteMovie($id);
    }

    /**
     * Bulk insert multiple movies into the index.
     *
     * @param  array<string, mixed>  $movies  Array of movie data arrays
     * @return array<string, mixed> Results with 'success' and 'errors' counts
     */
    public function bulkInsertMovies(array $movies): array
    {
        return $this->driver()->bulkInsertMovies($movies);
    }

    /**
     * Search the movies index.
     *
     * @param  array<string, mixed>  $searchTerm
     * @return array<string, mixed>
     */
    public function searchMovies(array|string $searchTerm, int $limit = 1000): array
    {
        return $this->driver()->searchMovies($searchTerm, $limit);
    }

    /**
     * Search movies by external ID (IMDB, TMDB, Trakt).
     *
     * @return array<string, mixed>
     */
    public function searchMovieByExternalId(string $field, int|string $value): ?array
    {
        return $this->driver()->searchMovieByExternalId($field, $value);
    }

    /**
     * Insert a TV show into the tvshows search index.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function insertTvShow(array $parameters): void
    {
        $this->driver()->insertTvShow($parameters);
    }

    /**
     * Update a TV show in the search index.
     */
    public function updateTvShow(int $videoId): void
    {
        $this->driver()->updateTvShow($videoId);
    }

    /**
     * Delete a TV show from the search index.
     */
    public function deleteTvShow(int $id): void
    {
        $this->driver()->deleteTvShow($id);
    }

    /**
     * Bulk insert multiple TV shows into the index.
     *
     * @param  array<string, mixed>  $tvShows  Array of TV show data arrays
     * @return array<string, mixed> Results with 'success' and 'errors' counts
     */
    public function bulkInsertTvShows(array $tvShows): array
    {
        return $this->driver()->bulkInsertTvShows($tvShows);
    }

    /**
     * Search the TV shows index.
     *
     * @param  array<string, mixed>  $searchTerm
     * @return array<string, mixed>
     */
    public function searchTvShows(array|string $searchTerm, int $limit = 1000): array
    {
        return $this->driver()->searchTvShows($searchTerm, $limit);
    }

    /**
     * Search TV shows by external ID (TVDB, Trakt, TVMaze, TVRage, IMDB, TMDB).
     *
     * @return array<string, mixed>
     */
    public function searchTvShowByExternalId(string $field, int|string $value): ?array
    {
        return $this->driver()->searchTvShowByExternalId($field, $value);
    }

    /**
     * Search releases by external media IDs.
     * Used to find releases associated with a specific movie or TV show.
     *
     * @param  array<string, mixed>  $externalIds
     * @return array<string, mixed>
     */
    public function searchReleasesByExternalId(array $externalIds, int $limit = 1000): array
    {
        return $this->driver()->searchReleasesByExternalId($externalIds, $limit);
    }

    /**
     * Search releases by category ID using the search index.
     * This provides a fast way to get release IDs for a specific category without hitting the database.
     *
     * @param  array<string, mixed>  $categoryIds
     * @return array<string, mixed>
     */
    public function searchReleasesByCategory(array $categoryIds, int $limit = 1000): array
    {
        return $this->driver()->searchReleasesByCategory($categoryIds, $limit);
    }

    /**
     * Combined search: text search with category filtering.
     * First searches by text, then filters by category IDs using the search index.
     *
     * @param  array<string, mixed>  $categoryIds
     * @return array<string, mixed>
     */
    public function searchReleasesWithCategoryFilter(string $searchTerm, array $categoryIds = [], int $limit = 1000): array
    {
        return $this->driver()->searchReleasesWithCategoryFilter($searchTerm, $categoryIds, $limit);
    }
}

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
     * Check if fuzzy search is enabled.
     */
    public function isFuzzyEnabled(): bool;

    /**
     * Get fuzzy search configuration.
     *
     * @return array<string, mixed>
     */
    public function getFuzzyConfig(): array;

    /**
     * Get the releases index name.
     */
    public function getReleasesIndex(): string;

    /**
     * Get the predb index name.
     */
    public function getPredbIndex(): string;

    /**
     * Get the movies index name.
     */
    public function getMoviesIndex(): string;

    /**
     * Get the TV shows index name.
     */
    public function getTvShowsIndex(): string;

    /**
     * Insert a release into the search index.
     *
     * @param  array<string, mixed>  $parameters  Release data with 'id', 'name', 'searchname', 'fromname', 'categories_id', 'filename'
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
     * @param  array<string, mixed>  $parameters  Predb data with 'id', 'title', 'filename', 'source'
     */
    public function insertPredb(array $parameters): void;

    /**
     * Update a predb record in the search index.
     *
     * @param  array<string, mixed>  $parameters  Predb data with 'id', 'title', 'filename', 'source'
     */
    public function updatePreDb(array $parameters): void;

    /**
     * Search the releases index.
     *
     * @param  array<string, mixed>|string  $phrases  Search phrases
     * @param  int  $limit  Maximum number of results
     * @return array<string, mixed> Array of release IDs
     */
    public function searchReleases(array|string $phrases, int $limit = 1000): array;

    /**
     * Search releases with fuzzy fallback.
     *
     * If exact search returns no results and fuzzy is enabled, this method
     * will automatically try a fuzzy search as a fallback.
     *
     * @param  array<string, mixed>|string  $phrases  Search phrases
     * @param  int  $limit  Maximum number of results
     * @param  bool  $forceFuzzy  Force fuzzy search regardless of exact results
     * @return array<string, mixed> Array with 'ids' (release IDs) and 'fuzzy' (bool indicating if fuzzy was used)
     */
    public function searchReleasesWithFuzzy(array|string $phrases, int $limit = 1000, bool $forceFuzzy = false): array;

    /**
     * Perform fuzzy search on releases index.
     *
     * Uses search engine's fuzzy matching to find results with typo tolerance.
     *
     * @param  array<string, mixed>|string  $phrases  Search phrases
     * @param  int  $limit  Maximum number of results
     * @return array<string, mixed> Array of release IDs
     */
    public function fuzzySearchReleases(array|string $phrases, int $limit = 1000): array;

    /**
     * Search the predb index.
     *
     * @param  array<string, mixed>|string  $searchTerm  Search term(s)
     * @return array<string, mixed> Array of predb records
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
     * @param  array<string, mixed>|string  $indexes  Index name(s) to truncate
     */
    public function truncateIndex(array|string $indexes): void;

    /**
     * Optimize index for better search performance.
     */
    public function optimizeIndex(): void;

    /**
     * Bulk insert multiple releases into the index.
     *
     * @param  array<string, mixed>  $releases  Array of release data arrays
     * @return array<string, mixed> Results with 'success' and 'errors' counts
     */
    public function bulkInsertReleases(array $releases): array;

    /**
     * Bulk insert multiple predb records into the index.
     *
     * @param  array<string, mixed>  $predbRecords  Array of predb data arrays
     * @return array<string, mixed> Results with 'success' and 'errors' counts
     */
    public function bulkInsertPredb(array $predbRecords): array;

    /**
     * Delete a predb record from the index.
     *
     * @param  int  $id  Predb ID
     */
    public function deletePreDb(int $id): void;

    /**
     * Insert a movie into the movies search index.
     *
     * @param  array<string, mixed>  $parameters  Movie data with id, imdbid, tmdbid, traktid, title, year, genre, actors, director, rating
     */
    public function insertMovie(array $parameters): void;

    /**
     * Update a movie in the search index.
     *
     * @param  int  $movieId  Movie ID
     */
    public function updateMovie(int $movieId): void;

    /**
     * Delete a movie from the search index.
     *
     * @param  int  $id  Movie ID
     */
    public function deleteMovie(int $id): void;

    /**
     * Bulk insert multiple movies into the index.
     *
     * @param  array<string, mixed>  $movies  Array of movie data arrays
     * @return array<string, mixed> Results with 'success' and 'errors' counts
     */
    public function bulkInsertMovies(array $movies): array;

    /**
     * Search the movies index.
     *
     * @param  array<string, mixed>|string  $searchTerm  Search term(s)
     * @param  int  $limit  Maximum number of results
     * @return array<string, mixed> Array with 'id' (movie IDs) and 'data' (movie data)
     */
    public function searchMovies(array|string $searchTerm, int $limit = 1000): array;

    /**
     * Search movies by external ID (IMDB, TMDB, Trakt).
     *
     * @param  string  $field  Field name (imdbid, tmdbid, traktid)
     * @param  int|string  $value  The external ID value
     * @return array<string, mixed>|null Movie data or null if not found
     */
    public function searchMovieByExternalId(string $field, int|string $value): ?array;

    /**
     * Insert a TV show into the tvshows search index.
     *
     * @param  array<string, mixed>  $parameters  TV show data with id, title, tvdb, trakt, tvmaze, tvrage, imdb, tmdb, started, type
     */
    public function insertTvShow(array $parameters): void;

    /**
     * Update a TV show in the search index.
     *
     * @param  int  $videoId  Video/TV show ID
     */
    public function updateTvShow(int $videoId): void;

    /**
     * Delete a TV show from the search index.
     *
     * @param  int  $id  TV show ID
     */
    public function deleteTvShow(int $id): void;

    /**
     * Bulk insert multiple TV shows into the index.
     *
     * @param  array<string, mixed>  $tvShows  Array of TV show data arrays
     * @return array<string, mixed> Results with 'success' and 'errors' counts
     */
    public function bulkInsertTvShows(array $tvShows): array;

    /**
     * Search the TV shows index.
     *
     * @param  array<string, mixed>|string  $searchTerm  Search term(s)
     * @param  int  $limit  Maximum number of results
     * @return array<string, mixed> Array with 'id' (TV show IDs) and 'data' (TV show data)
     */
    public function searchTvShows(array|string $searchTerm, int $limit = 1000): array;

    /**
     * Search TV shows by external ID (TVDB, Trakt, TVMaze, TVRage, IMDB, TMDB).
     *
     * @param  string  $field  Field name (tvdb, trakt, tvmaze, tvrage, imdb, tmdb)
     * @param  int|string  $value  The external ID value
     * @return array<string, mixed>|null TV show data or null if not found
     */
    public function searchTvShowByExternalId(string $field, int|string $value): ?array;

    /**
     * Search releases by external media IDs.
     * Used to find releases associated with a specific movie or TV show.
     *
     * @param  array<string, mixed>  $externalIds  Associative array of external IDs, e.g., ['imdbid' => 123456, 'tmdbid' => 789]
     * @param  int  $limit  Maximum number of results
     * @return array<string, mixed> Array of release IDs
     */
    public function searchReleasesByExternalId(array $externalIds, int $limit = 1000): array;

    /**
     * Search releases by category ID using the search index.
     * This provides a fast way to get release IDs for a specific category without hitting the database.
     *
     * @param  array<string, mixed>  $categoryIds  Array of category IDs to filter by
     * @param  int  $limit  Maximum number of results
     * @return array<string, mixed> Array of release IDs
     */
    public function searchReleasesByCategory(array $categoryIds, int $limit = 1000): array;

    /**
     * Combined search: text search with category filtering.
     * First searches by text, then filters by category IDs using the search index.
     *
     * @param  string  $searchTerm  Search text
     * @param  array<string, mixed>  $categoryIds  Array of category IDs to filter by (empty for all categories)
     * @param  int  $limit  Maximum number of results
     * @return array<string, mixed> Array of release IDs
     */
    public function searchReleasesWithCategoryFilter(string $searchTerm, array $categoryIds = [], int $limit = 1000): array;
}

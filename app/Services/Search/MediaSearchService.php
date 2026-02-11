<?php

namespace App\Services\Search;

use App\Facades\Search;
use App\Models\MovieInfo;
use App\Models\Release;
use App\Models\Video;
use Illuminate\Support\Facades\Cache;

/**
 * Service for optimized media searches using search indexes.
 *
 * This service provides methods to search for movies and TV shows
 * using the dedicated search indexes, reducing database queries
 * by leveraging cached external IDs (IMDB, TMDB, TVDB, etc.).
 */
class MediaSearchService
{
    /**
     * Cache TTL for media search results (minutes).
     */
    private const CACHE_TTL = 5;

    /**
     * Find movie by any external ID and return cached movie data from index.
     *
     * @param  array<string, mixed>  $externalIds  Array with keys like 'imdbid', 'tmdbid', 'traktid'
     * @return array<string, mixed>|null Movie data from search index
     */
    public function findMovie(array $externalIds): ?array
    {
        foreach (['imdbid', 'tmdbid', 'traktid'] as $field) {
            if (! empty($externalIds[$field])) {
                $result = Search::searchMovieByExternalId($field, $externalIds[$field]);
                if ($result !== null) {
                    return $result;
                }
            }
        }

        return null;
    }

    /**
     * Find TV show by any external ID and return cached show data from index.
     *
     * @param  array<string, mixed>  $externalIds  Array with keys like 'tvdb', 'trakt', 'tvmaze', 'tvrage', 'imdb', 'tmdb'
     * @return array<string, mixed>|null TV show data from search index
     */
    public function findTvShow(array $externalIds): ?array
    {
        foreach (['tvdb', 'trakt', 'tvmaze', 'tvrage', 'imdb', 'tmdb'] as $field) {
            if (! empty($externalIds[$field])) {
                $result = Search::searchTvShowByExternalId($field, $externalIds[$field]);
                if ($result !== null) {
                    return $result;
                }
            }
        }

        return null;
    }

    /**
     * Search for movie releases by movie title.
     *
     * First searches the movies index for matching titles, then finds
     * releases with matching external IDs - reducing database JOINs.
     *
     * @param  string  $title  Movie title to search
     * @param  int  $limit  Maximum results
     * @return array<string, mixed> Array of release IDs
     */
    public function searchMovieReleases(string $title, int $limit = 1000): array
    {
        $cacheKey = 'media:movie_releases:'.md5($title.$limit);
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // First, search movies index for matching titles
        $movieResults = Search::searchMovies($title, 50);

        if (empty($movieResults['id'])) {
            return [];
        }

        // Collect all external IDs from matched movies
        $allReleaseIds = [];

        foreach ($movieResults['data'] ?? [] as $movie) {
            $externalIds = [];

            if (! empty($movie['imdbid'])) {
                $externalIds['imdbid'] = $movie['imdbid'];
            }
            if (! empty($movie['tmdbid'])) {
                $externalIds['tmdbid'] = $movie['tmdbid'];
            }
            if (! empty($movie['traktid'])) {
                $externalIds['traktid'] = $movie['traktid'];
            }

            if (! empty($externalIds)) {
                $releaseIds = Search::searchReleasesByExternalId($externalIds, $limit);
                $allReleaseIds = array_merge($allReleaseIds, $releaseIds);
            }
        }

        // Remove duplicates and limit results
        $allReleaseIds = array_unique($allReleaseIds);
        $allReleaseIds = array_slice($allReleaseIds, 0, $limit);

        if (! empty($allReleaseIds)) {
            Cache::put($cacheKey, $allReleaseIds, now()->addMinutes(self::CACHE_TTL));
        }

        return $allReleaseIds;
    }

    /**
     * Search for TV show releases by show title.
     *
     * First searches the tvshows index for matching titles, then finds
     * releases with matching external IDs - reducing database JOINs.
     *
     * @param  string  $title  TV show title to search
     * @param  int  $limit  Maximum results
     * @return array<string, mixed> Array of release IDs
     */
    public function searchTvShowReleases(string $title, int $limit = 1000): array
    {
        $cacheKey = 'media:tvshow_releases:'.md5($title.$limit);
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // First, search tvshows index for matching titles
        $tvResults = Search::searchTvShows($title, 50);

        if (empty($tvResults['id'])) {
            return [];
        }

        // Collect all external IDs from matched TV shows
        $allReleaseIds = [];

        foreach ($tvResults['data'] ?? [] as $tvShow) {
            $externalIds = [];

            if (! empty($tvShow['tvdb'])) {
                $externalIds['tvdb'] = $tvShow['tvdb'];
            }
            if (! empty($tvShow['trakt'])) {
                $externalIds['traktid'] = $tvShow['trakt'];
            }
            if (! empty($tvShow['tvmaze'])) {
                $externalIds['tvmaze'] = $tvShow['tvmaze'];
            }
            if (! empty($tvShow['tvrage'])) {
                $externalIds['tvrage'] = $tvShow['tvrage'];
            }

            if (! empty($externalIds)) {
                $releaseIds = Search::searchReleasesByExternalId($externalIds, $limit);
                $allReleaseIds = array_merge($allReleaseIds, $releaseIds);
            }
        }

        // Remove duplicates and limit results
        $allReleaseIds = array_unique($allReleaseIds);
        $allReleaseIds = array_slice($allReleaseIds, 0, $limit);

        if (! empty($allReleaseIds)) {
            Cache::put($cacheKey, $allReleaseIds, now()->addMinutes(self::CACHE_TTL));
        }

        return $allReleaseIds;
    }

    /**
     * Get releases for a specific IMDB ID directly from the index.
     *
     * @param  int|string  $imdbId  IMDB ID (without 'tt' prefix)
     * @param  int  $limit  Maximum results
     * @return array<string, mixed> Array of release IDs
     */
    public function getReleasesByImdbId(int|string $imdbId, int $limit = 1000): array
    {
        return Search::searchReleasesByExternalId(['imdbid' => (int) $imdbId], $limit);
    }

    /**
     * Get releases for a specific TMDB ID directly from the index.
     *
     * @param  int|string  $tmdbId  TMDB ID
     * @param  int  $limit  Maximum results
     * @return array<string, mixed> Array of release IDs
     */
    public function getReleasesByTmdbId(int|string $tmdbId, int $limit = 1000): array
    {
        return Search::searchReleasesByExternalId(['tmdbid' => (int) $tmdbId], $limit);
    }

    /**
     * Get releases for a specific TVDB ID directly from the index.
     *
     * @param  int|string  $tvdbId  TVDB ID
     * @param  int  $limit  Maximum results
     * @return array<string, mixed> Array of release IDs
     */
    public function getReleasesByTvdbId(int|string $tvdbId, int $limit = 1000): array
    {
        return Search::searchReleasesByExternalId(['tvdb' => (int) $tvdbId], $limit);
    }

    /**
     * Get releases for a specific Trakt ID directly from the index.
     *
     * @param  int|string  $traktId  Trakt ID
     * @param  int  $limit  Maximum results
     * @return array<string, mixed> Array of release IDs
     */
    public function getReleasesByTraktId(int|string $traktId, int $limit = 1000): array
    {
        return Search::searchReleasesByExternalId(['traktid' => (int) $traktId], $limit);
    }

    /**
     * Combined search: search by title and optionally by external IDs.
     *
     * @param  string  $title  Media title
     * @param  array<string, mixed>  $externalIds  Optional external IDs to narrow search
     * @param  string  $type  'movie' or 'tv'
     * @param  int  $limit  Maximum results
     * @return array<string, mixed> Array of release IDs
     */
    public function searchMedia(string $title, array $externalIds = [], string $type = 'movie', int $limit = 1000): array
    {
        // If we have external IDs, use them directly
        if (! empty($externalIds)) {
            return Search::searchReleasesByExternalId($externalIds, $limit);
        }

        // Otherwise search by title
        if ($type === 'movie') {
            return $this->searchMovieReleases($title, $limit);
        }

        return $this->searchTvShowReleases($title, $limit);
    }

    /**
     * Get movie info from the search index (faster than DB query).
     *
     * @param  int  $movieInfoId  MovieInfo ID
     * @return array<string, mixed>|null Movie data or null
     */
    public function getMovieFromIndex(int $movieInfoId): ?array
    {
        $cacheKey = 'media:movie:'.$movieInfoId;
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // Search movies index by ID
        $result = Search::searchMovieByExternalId('id', $movieInfoId);

        if ($result) {
            Cache::put($cacheKey, $result, now()->addMinutes(self::CACHE_TTL));
        }

        return $result;
    }

    /**
     * Get TV show info from the search index (faster than DB query).
     *
     * @param  int  $videoId  Video/TV show ID
     * @return array<string, mixed>|null TV show data or null
     */
    public function getTvShowFromIndex(int $videoId): ?array
    {
        $cacheKey = 'media:tvshow:'.$videoId;
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // Search tvshows index by ID
        $result = Search::searchTvShowByExternalId('id', $videoId);

        if ($result) {
            Cache::put($cacheKey, $result, now()->addMinutes(self::CACHE_TTL));
        }

        return $result;
    }
}

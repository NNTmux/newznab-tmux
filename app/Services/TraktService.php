<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Trakt.tv Service
 *
 * A modern service wrapper for the Trakt.tv API.
 * Provides methods to fetch movie and TV show information.
 *
 * API Documentation: https://trakt.docs.apiary.io/
 */
class TraktService
{
    protected const BASE_URL = 'https://api.trakt.tv';

    protected const CACHE_TTL_HOURS = 24;

    protected const API_VERSION = 2;

    protected const ID_TYPES = ['imdb', 'tmdb', 'trakt', 'tvdb'];

    protected string $clientId;

    protected int $timeout;

    protected int $retryTimes;

    protected int $retryDelay;

    public function __construct(?string $clientId = null)
    {
        $this->clientId = $clientId ?? (string) config('nntmux_api.trakttv_api_key', '');
        $this->timeout = (int) config('nntmux_api.trakttv_timeout', 30);
        $this->retryTimes = (int) config('nntmux_api.trakttv_retry_times', 3);
        $this->retryDelay = (int) config('nntmux_api.trakttv_retry_delay', 100);
    }

    /**
     * Check if the API key is configured.
     */
    public function isConfigured(): bool
    {
        return ! empty($this->clientId);
    }

    /**
     * Get the request headers for Trakt API.
     */
    protected function getHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'trakt-api-version' => self::API_VERSION,
            'trakt-api-key' => $this->clientId,
        ];
    }

    /**
     * Make a GET request to the Trakt API.
     *
     * @param  string  $endpoint  The API endpoint
     * @param  array  $params  Query parameters
     * @return array|null Response data or null on failure
     */
    protected function get(string $endpoint, array $params = []): ?array
    {
        if (! $this->isConfigured()) {
            Log::debug('Trakt API key is not configured');

            return null;
        }

        $url = self::BASE_URL.'/'.ltrim($endpoint, '/');

        try {
            $response = Http::timeout($this->timeout)
                ->retry($this->retryTimes, $this->retryDelay)
                ->withHeaders($this->getHeaders())
                ->get($url, $params);

            if ($response->successful()) {
                $data = $response->json();

                // Check for API error responses
                if (isset($data['status']) && $data['status'] === 'failure') {
                    Log::debug('Trakt API returned failure status', [
                        'endpoint' => $endpoint,
                    ]);

                    return null;
                }

                return $data;
            }

            // Handle specific error codes
            if ($response->status() === 404) {
                Log::debug('Trakt: Resource not found', ['endpoint' => $endpoint]);

                return null;
            }

            Log::warning('Trakt API request failed', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Throwable $e) {
            Log::warning('Trakt API request exception', [
                'endpoint' => $endpoint,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get episode summary.
     *
     * @param  int|string  $showId  The show ID (Trakt, IMDB, TMDB, or TVDB)
     * @param  int|string  $season  The season number
     * @param  int|string  $episode  The episode number
     * @param  string  $extended  Extended info level: 'min', 'full', 'aliases', 'full,aliases'
     * @return array|null Episode data or null on failure
     */
    public function getEpisodeSummary(
        int|string $showId,
        int|string $season,
        int|string $episode,
        string $extended = 'min'
    ): ?array {
        // Validate parameters to avoid unnecessary API calls with invalid IDs
        $showIdInt = is_numeric($showId) ? (int) $showId : 0;
        $seasonInt = is_numeric($season) ? (int) $season : -1;
        $episodeInt = is_numeric($episode) ? (int) $episode : -1;

        // For numeric show IDs, validate they are positive
        // Season and episode must be non-negative (season 0 can be specials)
        if (($showIdInt <= 0 && is_numeric($showId)) || $seasonInt < 0 || $episodeInt < 0) {
            return null;
        }

        $extended = match ($extended) {
            'aliases', 'full', 'full,aliases' => $extended,
            default => 'min',
        };

        $cacheKey = "trakt_episode_{$showId}_{$season}_{$episode}_{$extended}";

        return Cache::remember($cacheKey, now()->addHours(self::CACHE_TTL_HOURS), function () use ($showId, $season, $episode, $extended) {
            return $this->get("shows/{$showId}/seasons/{$season}/episodes/{$episode}", [
                'extended' => $extended,
            ]);
        });
    }

    /**
     * Get current box office movies.
     *
     * @return array|null Box office data or null on failure
     */
    public function getBoxOffice(): ?array
    {
        $cacheKey = 'trakt_boxoffice_'.date('Y-m-d');

        return Cache::remember($cacheKey, now()->addHours(6), function () {
            return $this->get('movies/boxoffice');
        });
    }

    /**
     * Get TV show calendar.
     *
     * @param  string  $startDate  Start date (YYYY-MM-DD format, defaults to today)
     * @param  int  $days  Number of days to retrieve (default: 7)
     * @return array|null Calendar data or null on failure
     */
    public function getCalendar(string $startDate = '', int $days = 7): ?array
    {
        if (empty($startDate)) {
            $startDate = date('Y-m-d');
        }

        $cacheKey = "trakt_calendar_{$startDate}_{$days}";

        return Cache::remember($cacheKey, now()->addHours(6), function () use ($startDate, $days) {
            return $this->get("calendars/all/shows/{$startDate}/{$days}");
        });
    }

    /**
     * Get movie summary.
     *
     * @param  string  $movie  Movie slug or ID
     * @param  string  $extended  Extended info level: 'min' or 'full'
     * @return array|null Movie data or null on failure
     */
    public function getMovieSummary(string $movie, string $extended = 'min'): ?array
    {
        if (empty($movie)) {
            return null;
        }

        $extended = $extended === 'full' ? 'full' : 'min';
        $slug = Str::slug($movie);
        $cacheKey = "trakt_movie_{$slug}_{$extended}";

        return Cache::remember($cacheKey, now()->addHours(self::CACHE_TTL_HOURS), function () use ($slug, $extended) {
            return $this->get("movies/{$slug}", ['extended' => $extended]);
        });
    }

    /**
     * Get IMDB ID for a movie.
     *
     * @param  string  $movie  Movie slug or ID
     * @return string|null IMDB ID or null on failure
     */
    public function getMovieImdbId(string $movie): ?string
    {
        $data = $this->getMovieSummary($movie, 'min');

        return $data['ids']['imdb'] ?? null;
    }

    /**
     * Search by external ID.
     *
     * @param  int|string  $id  The ID to search for
     * @param  string  $idType  The ID type: 'imdb', 'tmdb', 'trakt', 'tvdb'
     * @param  string  $mediaType  Media type: 'movie', 'show', 'episode', or empty for all
     * @return array|null Search results or null on failure
     */
    public function searchById(int|string $id, string $idType = 'trakt', string $mediaType = ''): ?array
    {
        if (! in_array($idType, self::ID_TYPES, true)) {
            Log::warning('Trakt: Invalid ID type', ['idType' => $idType]);

            return null;
        }

        // Format IMDB ID with 'tt' prefix if needed
        if ($idType === 'imdb' && is_numeric($id)) {
            $id = 'tt'.$id;
        }

        $params = [
            'id_type' => $idType,
            'id' => $id,
        ];

        if (! empty($mediaType)) {
            $params['type'] = $mediaType;
        }

        // Don't cache ID searches as they're typically one-off lookups
        return $this->get('search/'.$idType.'/'.$id, ['type' => $mediaType ?: null]);
    }

    /**
     * Search for a show by name.
     *
     * @param  string  $query  Search query
     * @param  string  $type  Search type: 'show', 'movie', 'episode', 'person', 'list'
     * @return array|null Search results or null on failure
     */
    public function searchShows(string $query, string $type = 'show'): ?array
    {
        if (empty($query)) {
            return null;
        }

        $slug = Str::slug($query);
        $cacheKey = "trakt_search_{$type}_{$slug}";

        return Cache::remember($cacheKey, now()->addHours(self::CACHE_TTL_HOURS), function () use ($query, $type) {
            return $this->get('search/'.$type, ['query' => $query]);
        });
    }

    /**
     * Get show summary.
     *
     * @param  string  $show  Show slug or ID
     * @param  string  $extended  Extended info level: 'min' or 'full'
     * @return array|null Show data or null on failure
     */
    public function getShowSummary(string $show, string $extended = 'full'): ?array
    {
        if (empty($show)) {
            return null;
        }

        $extended = $extended === 'full' ? 'full' : 'min';
        $slug = Str::slug($show);
        $cacheKey = "trakt_show_{$slug}_{$extended}";

        return Cache::remember($cacheKey, now()->addHours(self::CACHE_TTL_HOURS), function () use ($slug, $extended) {
            return $this->get("shows/{$slug}", ['extended' => $extended]);
        });
    }

    /**
     * Get show seasons.
     *
     * @param  string  $show  Show slug or ID
     * @param  string  $extended  Extended info level
     * @return array|null Seasons data or null on failure
     */
    public function getShowSeasons(string $show, string $extended = 'full'): ?array
    {
        if (empty($show)) {
            return null;
        }

        $slug = Str::slug($show);
        $cacheKey = "trakt_seasons_{$slug}_{$extended}";

        return Cache::remember($cacheKey, now()->addHours(self::CACHE_TTL_HOURS), function () use ($slug, $extended) {
            return $this->get("shows/{$slug}/seasons", ['extended' => $extended]);
        });
    }

    /**
     * Get season episodes.
     *
     * @param  string  $show  Show slug or ID
     * @param  int  $season  Season number
     * @param  string  $extended  Extended info level
     * @return array|null Episodes data or null on failure
     */
    public function getSeasonEpisodes(string $show, int $season, string $extended = 'full'): ?array
    {
        if (empty($show)) {
            return null;
        }

        $slug = Str::slug($show);
        $cacheKey = "trakt_season_episodes_{$slug}_{$season}_{$extended}";

        return Cache::remember($cacheKey, now()->addHours(self::CACHE_TTL_HOURS), function () use ($slug, $season, $extended) {
            return $this->get("shows/{$slug}/seasons/{$season}", ['extended' => $extended]);
        });
    }

    /**
     * Get trending shows.
     *
     * @param  int  $limit  Number of results to return
     * @param  string  $extended  Extended info level
     * @return array|null Trending shows or null on failure
     */
    public function getTrendingShows(int $limit = 10, string $extended = 'full'): ?array
    {
        $cacheKey = "trakt_trending_shows_{$limit}_{$extended}";

        return Cache::remember($cacheKey, now()->addHours(1), function () use ($limit, $extended) {
            return $this->get('shows/trending', [
                'limit' => $limit,
                'extended' => $extended,
            ]);
        });
    }

    /**
     * Get trending movies.
     *
     * @param  int  $limit  Number of results to return
     * @param  string  $extended  Extended info level
     * @return array|null Trending movies or null on failure
     */
    public function getTrendingMovies(int $limit = 10, string $extended = 'full'): ?array
    {
        $cacheKey = "trakt_trending_movies_{$limit}_{$extended}";

        return Cache::remember($cacheKey, now()->addHours(1), function () use ($limit, $extended) {
            return $this->get('movies/trending', [
                'limit' => $limit,
                'extended' => $extended,
            ]);
        });
    }

    /**
     * Get popular shows.
     *
     * @param  int  $limit  Number of results to return
     * @param  string  $extended  Extended info level
     * @return array|null Popular shows or null on failure
     */
    public function getPopularShows(int $limit = 10, string $extended = 'full'): ?array
    {
        $cacheKey = "trakt_popular_shows_{$limit}_{$extended}";

        return Cache::remember($cacheKey, now()->addHours(6), function () use ($limit, $extended) {
            return $this->get('shows/popular', [
                'limit' => $limit,
                'extended' => $extended,
            ]);
        });
    }

    /**
     * Get popular movies.
     *
     * @param  int  $limit  Number of results to return
     * @param  string  $extended  Extended info level
     * @return array|null Popular movies or null on failure
     */
    public function getPopularMovies(int $limit = 10, string $extended = 'full'): ?array
    {
        $cacheKey = "trakt_popular_movies_{$limit}_{$extended}";

        return Cache::remember($cacheKey, now()->addHours(6), function () use ($limit, $extended) {
            return $this->get('movies/popular', [
                'limit' => $limit,
                'extended' => $extended,
            ]);
        });
    }

    /**
     * Clear cached data for a specific show.
     */
    public function clearShowCache(string $show): void
    {
        $slug = Str::slug($show);
        Cache::forget("trakt_show_{$slug}_min");
        Cache::forget("trakt_show_{$slug}_full");
        Cache::forget("trakt_seasons_{$slug}_full");
        Cache::forget("trakt_seasons_{$slug}_min");
    }

    /**
     * Clear cached data for a specific movie.
     */
    public function clearMovieCache(string $movie): void
    {
        $slug = Str::slug($movie);
        Cache::forget("trakt_movie_{$slug}_min");
        Cache::forget("trakt_movie_{$slug}_full");
    }
}


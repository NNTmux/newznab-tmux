<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Custom TMDB (The Movie Database) API Client
 *
 * This service provides methods to interact with The Movie Database API
 * for fetching movie and TV show information.
 */
class TmdbClient
{
    protected const BASE_URL = 'https://api.themoviedb.org/3';

    protected const IMAGE_BASE_URL = 'https://image.tmdb.org/t/p';

    protected string $apiKey;

    protected int $timeout;

    protected int $retryTimes;

    protected int $retryDelay;

    public function __construct()
    {
        $this->apiKey = (string) config('tmdb.api_key', '');
        $this->timeout = (int) config('tmdb.timeout', 30);
        $this->retryTimes = (int) config('tmdb.retry_times', 3);
        $this->retryDelay = (int) config('tmdb.retry_delay', 100);
    }

    /**
     * Check if the API key is configured
     */
    public function isConfigured(): bool
    {
        return ! empty($this->apiKey);
    }

    /**
     * Make a GET request to the TMDB API
     *
     * @param  string  $endpoint  The API endpoint
     * @param  array<string, mixed>  $params  Additional query parameters
     * @return array<string, mixed>|null Response data or null on failure
     */
    protected function get(string $endpoint, array $params = []): ?array
    {
        if (! $this->isConfigured()) {
            Log::warning('TMDB API key is not configured');

            return null;
        }

        $params['api_key'] = $this->apiKey;

        try {
            $response = Http::timeout($this->timeout)
                ->retry($this->retryTimes, $this->retryDelay, function (\Throwable $exception, \Illuminate\Http\Client\PendingRequest $request, ?string $key = null) {
                    // Don't retry on 404 errors - resource simply doesn't exist
                    if ($exception instanceof \Illuminate\Http\Client\RequestException) {
                        return $exception->response->status() !== 404;
                    }

                    return true;
                }, throw: false)
                ->get(self::BASE_URL.$endpoint, $params);

            if ($response->successful()) {
                return $response->json();
            }

            // Handle specific error codes - 404 is normal (resource not found)
            if ($response->status() === 404) {
                Log::debug('TMDB: Resource not found', ['endpoint' => $endpoint]);

                return null;
            }

            Log::warning('TMDB API request failed', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Throwable $e) {
            // Check if this is a 404 wrapped in an exception
            if ($e instanceof \Illuminate\Http\Client\RequestException && $e->response->status() === 404) {
                Log::debug('TMDB: Resource not found', ['endpoint' => $endpoint]);

                return null;
            }

            Log::warning('TMDB API request exception', [
                'endpoint' => $endpoint,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get the full image URL
     *
     * @param  string|null  $path  The image path from TMDB
     * @param  string  $size  The image size (w92, w154, w185, w342, w500, w780, original)
     */
    public function getImageUrl(?string $path, string $size = 'w500'): string
    {
        if (empty($path)) {
            return '';
        }

        return self::IMAGE_BASE_URL.'/'.$size.$path;
    }

    // =========================================================================
    // MOVIE METHODS
    // =========================================================================

    /**
     * Search for movies by title
     *
     * @param  string  $query  The search query
     * @param  int  $page  Page number for pagination
     * @param  string|null  $year  Filter by release year
     * @return array<string, mixed>|null Search results or null on failure
     */
    public function searchMovies(string $query, int $page = 1, ?string $year = null): ?array
    {
        $params = [
            'query' => $query,
            'page' => $page,
            'include_adult' => false,
        ];

        if ($year !== null) {
            $params['year'] = $year;
        }

        return $this->get('/search/movie', $params);
    }

    /**
     * Get movie details by TMDB ID or IMDB ID
     *
     * @param  int|string  $id  The TMDB ID or IMDB ID (with 'tt' prefix)
     * @param  array<string, mixed>  $appendToResponse  Additional data to append (e.g., ['credits', 'external_ids'])
     * @return array<string, mixed>|null Movie data or null on failure
     */
    public function getMovie(int|string $id, array $appendToResponse = []): ?array
    {
        $params = [];

        if (! empty($appendToResponse)) {
            $params['append_to_response'] = implode(',', $appendToResponse);
        }

        return $this->get('/movie/'.$id, $params);
    }

    /**
     * Get movie credits (cast and crew)
     *
     * @param  int  $movieId  The TMDB movie ID
     * @return array<string, mixed>|null Credits data or null on failure
     */
    public function getMovieCredits(int $movieId): ?array
    {
        return $this->get('/movie/'.$movieId.'/credits');
    }

    /**
     * Get movie external IDs (IMDB, etc.)
     *
     * @param  int  $movieId  The TMDB movie ID
     * @return array<string, mixed>|null External IDs or null on failure
     */
    public function getMovieExternalIds(int $movieId): ?array
    {
        return $this->get('/movie/'.$movieId.'/external_ids');
    }

    // =========================================================================
    // TV SHOW METHODS
    // =========================================================================

    /**
     * Search for TV shows by title
     *
     * @param  string  $query  The search query
     * @param  int  $page  Page number for pagination
     * @param  int|null  $firstAirDateYear  Filter by first air date year
     * @return array<string, mixed>|null Search results or null on failure
     */
    public function searchTv(string $query, int $page = 1, ?int $firstAirDateYear = null): ?array
    {
        $params = [
            'query' => $query,
            'page' => $page,
            'include_adult' => false,
        ];

        if ($firstAirDateYear !== null) {
            $params['first_air_date_year'] = $firstAirDateYear;
        }

        return $this->get('/search/tv', $params);
    }

    /**
     * Get TV show details by ID
     *
     * @param  int|string  $id  The TMDB TV show ID
     * @param  array<string, mixed>  $appendToResponse  Additional data to append
     * @return array<string, mixed>|null TV show data or null on failure
     */
    public function getTvShow(int|string $id, array $appendToResponse = []): ?array
    {
        $params = [];

        if (! empty($appendToResponse)) {
            $params['append_to_response'] = implode(',', $appendToResponse);
        }

        return $this->get('/tv/'.$id, $params);
    }

    /**
     * Get TV show external IDs (IMDB, TVDB, etc.)
     *
     * @param  int  $tvId  The TMDB TV show ID
     * @return array<string, mixed>|null External IDs or null on failure
     */
    public function getTvExternalIds(int $tvId): ?array
    {
        return $this->get('/tv/'.$tvId.'/external_ids');
    }

    /**
     * Get TV show alternative titles
     *
     * @param  int  $tvId  The TMDB TV show ID
     * @return array<string, mixed>|null Alternative titles or null on failure
     */
    public function getTvAlternativeTitles(int $tvId): ?array
    {
        return $this->get('/tv/'.$tvId.'/alternative_titles');
    }

    /**
     * Get TV season details
     *
     * @param  int  $tvId  The TMDB TV show ID
     * @param  int  $seasonNumber  The season number
     * @return array<string, mixed>|null Season data or null on failure
     */
    public function getTvSeason(int $tvId, int $seasonNumber): ?array
    {
        // Validate parameters to avoid unnecessary API calls with invalid IDs
        if ($tvId <= 0 || $seasonNumber < 0) {
            return null;
        }

        return $this->get('/tv/'.$tvId.'/season/'.$seasonNumber);
    }

    /**
     * Get TV episode details
     *
     * @param  int  $tvId  The TMDB TV show ID
     * @param  int  $seasonNumber  The season number
     * @param  int  $episodeNumber  The episode number
     * @return array<string, mixed>|null Episode data or null on failure
     */
    public function getTvEpisode(int $tvId, int $seasonNumber, int $episodeNumber): ?array
    {
        // Validate parameters to avoid unnecessary API calls with invalid IDs
        if ($tvId <= 0 || $seasonNumber < 0 || $episodeNumber < 0) {
            return null;
        }

        return $this->get('/tv/'.$tvId.'/season/'.$seasonNumber.'/episode/'.$episodeNumber);
    }

    /**
     * Find a TV show by external ID (IMDB, TVDB, etc.)
     *
     * @param  string  $externalId  The external ID value
     * @param  string  $source  The source: 'imdb_id', 'tvdb_id', 'tvrage_id'
     * @return array<string, mixed>|null The TMDB TV show data or null if not found
     */
    public function findTvByExternalId(string $externalId, string $source = 'tvdb_id'): ?array
    {
        if (empty($externalId)) {
            return null;
        }

        $validSources = ['imdb_id', 'tvdb_id', 'tvrage_id'];
        if (! in_array($source, $validSources, true)) {
            return null;
        }

        $result = $this->get('/find/'.$externalId, [
            'external_source' => $source,
        ]);

        if ($result === null || empty($result['tv_results'])) {
            return null;
        }

        // Return the first TV result
        return $result['tv_results'][0] ?? null;
    }

    /**
     * Get TV episode with fallback using multiple external IDs.
     * Tries TMDB ID first, then looks up by TVDB or IMDB if needed.
     *
     * @param  array<string, mixed>  $ids  Array of IDs: ['tmdb' => X, 'tvdb' => Y, 'imdb' => Z]
     * @param  int  $seasonNumber  The season number
     * @param  int  $episodeNumber  The episode number
     * @return array<string, mixed>|null Episode data or null on failure
     */
    public function getTvEpisodeWithFallback(array $ids, int $seasonNumber, int $episodeNumber): ?array
    {
        // First try with TMDB ID if available
        $tmdbId = (int) ($ids['tmdb'] ?? 0);
        if ($tmdbId > 0) {
            $result = $this->getTvEpisode($tmdbId, $seasonNumber, $episodeNumber);
            if ($result !== null) {
                return $result;
            }
        }

        // Try to find the show by TVDB ID
        $tvdbId = (int) ($ids['tvdb'] ?? 0);
        if ($tvdbId > 0) {
            $show = $this->findTvByExternalId((string) $tvdbId, 'tvdb_id');
            if ($show !== null && isset($show['id'])) {
                $result = $this->getTvEpisode((int) $show['id'], $seasonNumber, $episodeNumber);
                if ($result !== null) {
                    return $result;
                }
            }
        }

        // Try to find the show by IMDB ID
        $imdbId = $ids['imdb'] ?? 0;
        if (! empty($imdbId)) {
            // Format IMDB ID with tt prefix if it's numeric
            $imdbFormatted = is_numeric($imdbId)
                ? 'tt'.str_pad((string) $imdbId, 8, '0', STR_PAD_LEFT)
                : (string) $imdbId;

            $show = $this->findTvByExternalId($imdbFormatted, 'imdb_id');
            if ($show !== null && isset($show['id'])) {
                $result = $this->getTvEpisode((int) $show['id'], $seasonNumber, $episodeNumber);
                if ($result !== null) {
                    return $result;
                }
            }
        }

        return null;
    }

    /**
     * Look up a TV show and get all its external IDs.
     * Can be used to cross-reference between providers.
     *
     * @param  int|string  $id  The external ID
     * @param  string  $source  The ID source: 'tmdb', 'tvdb', 'imdb'
     * @return array<string, mixed>|null Array with all IDs: ['tmdb' => X, 'imdb' => Y, 'tvdb' => Z] or null
     */
    public function lookupTvShowIds(int|string $id, string $source = 'tmdb'): ?array
    {
        $show = null;

        if ($source === 'tmdb' && is_numeric($id) && (int) $id > 0) {
            // Direct TMDB lookup
            $show = $this->getTvShow((int) $id);
            if ($show !== null) {
                $externalIds = $this->getTvExternalIds((int) $id);
                if ($externalIds !== null) {
                    $show['external_ids'] = $externalIds;
                }
            }
        } elseif ($source === 'tvdb' && is_numeric($id) && (int) $id > 0) {
            $show = $this->findTvByExternalId((string) $id, 'tvdb_id');
        } elseif ($source === 'imdb') {
            $imdbFormatted = is_numeric($id)
                ? 'tt'.str_pad((string) $id, 8, '0', STR_PAD_LEFT)
                : (string) $id;
            $show = $this->findTvByExternalId($imdbFormatted, 'imdb_id');
        }

        if ($show === null) {
            return null;
        }

        // If we found by external ID, we need to fetch external IDs for full data
        $tmdbId = self::getInt($show, 'id');
        if ($tmdbId > 0 && ! isset($show['external_ids'])) {
            $externalIds = $this->getTvExternalIds($tmdbId);
            $show['external_ids'] = $externalIds ?? [];
        }

        $externalIds = self::getArray($show, 'external_ids');

        // Parse IMDB ID to numeric
        $imdbId = 0;
        if (! empty($externalIds['imdb_id'])) {
            preg_match('/tt(?P<imdbid>\d{6,8})$/i', $externalIds['imdb_id'], $imdb);
            $imdbId = (int) ($imdb['imdbid'] ?? 0);
        }

        return [
            'tmdb' => $tmdbId,
            'imdb' => $imdbId,
            'tvdb' => self::getInt($externalIds, 'tvdb_id'),
            'tvrage' => self::getInt($externalIds, 'tvrage_id'),
        ];
    }

    // =========================================================================
    // HELPER METHODS FOR NULL-SAFE DATA EXTRACTION
    // =========================================================================

    /**
     * Safely get a string value from an array
     *
     * @param  array<string, mixed>  $data
     */
    public static function getString(array $data, string $key, string $default = ''): string
    {
        return isset($data[$key]) && is_string($data[$key]) ? $data[$key] : $default;
    }

    /**
     * Safely get an integer value from an array
     *
     * @param  array<string, mixed>  $data
     */
    public static function getInt(array $data, string $key, int $default = 0): int
    {
        return isset($data[$key]) && is_numeric($data[$key]) ? (int) $data[$key] : $default;
    }

    /**
     * Safely get a float value from an array
     *
     * @param  array<string, mixed>  $data
     */
    public static function getFloat(array $data, string $key, float $default = 0.0): float
    {
        return isset($data[$key]) && is_numeric($data[$key]) ? (float) $data[$key] : $default;
    }

    /**
     * Safely get an array value from an array
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $default
     * @return array<string, mixed>
     */
    public static function getArray(array $data, string $key, array $default = []): array
    {
        return isset($data[$key]) && is_array($data[$key]) ? $data[$key] : $default;
    }

    /**
     * Safely get a nested value from an array using dot notation
     *
     * @param  array<string, mixed>  $data
     */
    public static function getNested(array $data, string $path, mixed $default = null): mixed
    {
        $keys = explode('.', $path);
        $value = $data;

        foreach ($keys as $key) {
            if (! is_array($value) || ! array_key_exists($key, $value)) {
                return $default;
            }
            $value = $value[$key];
        }

        return $value ?? $default;
    }
}

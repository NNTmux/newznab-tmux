<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * FanartTV Service
 *
 * A modern service wrapper for the Fanart.TV API.
 * Provides methods to fetch fan art for movies and TV shows.
 *
 * API Documentation: https://fanarttv.docs.apiary.io/
 */
class FanartTvService
{
    protected const BASE_URL = 'https://webservice.fanart.tv/v3';

    protected const CACHE_TTL_HOURS = 24;

    protected string $apiKey;

    protected int $timeout;

    protected int $retryTimes;

    protected int $retryDelay;

    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey ?? (string) config('nntmux_api.fanarttv_api_key', '');
        $this->timeout = (int) config('nntmux_api.fanarttv_timeout', 30);
        $this->retryTimes = (int) config('nntmux_api.fanarttv_retry_times', 3);
        $this->retryDelay = (int) config('nntmux_api.fanarttv_retry_delay', 100);
    }

    /**
     * Check if the API key is configured.
     */
    public function isConfigured(): bool
    {
        return ! empty($this->apiKey);
    }

    /**
     * Make a GET request to the Fanart.TV API.
     *
     * @param  string  $endpoint  The API endpoint
     * @return array|null Response data or null on failure
     */
    protected function get(string $endpoint): ?array
    {
        if (! $this->isConfigured()) {
            Log::debug('FanartTV API key is not configured');

            return null;
        }

        $url = self::BASE_URL.'/'.$endpoint;

        try {
            $response = Http::timeout($this->timeout)
                ->retry($this->retryTimes, $this->retryDelay)
                ->withQueryParameters(['api_key' => $this->apiKey])
                ->get($url);

            if ($response->successful()) {
                $data = $response->json();

                // Check for API error responses
                if (isset($data['status']) && $data['status'] === 'error') {
                    Log::debug('FanartTV API returned error status', [
                        'endpoint' => $endpoint,
                        'message' => $data['error message'] ?? 'Unknown error',
                    ]);

                    return null;
                }

                return $data;
            }

            // Handle specific error codes
            if ($response->status() === 404) {
                Log::debug('FanartTV: Resource not found', ['endpoint' => $endpoint]);

                return null;
            }

            Log::warning('FanartTV API request failed', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Throwable $e) {
            Log::warning('FanartTV API request exception', [
                'endpoint' => $endpoint,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get fan art for a movie by IMDB ID.
     *
     * @param  string  $imdbId  IMDB ID (with or without 'tt' prefix)
     * @return array|null Movie art data or null on failure
     */
    public function getMovieFanArt(string $imdbId): ?array
    {
        // Ensure the ID has the 'tt' prefix
        $id = $this->normalizeImdbId($imdbId);

        $cacheKey = 'fanarttv_movie_'.$id;

        return Cache::remember($cacheKey, now()->addHours(self::CACHE_TTL_HOURS), function () use ($id) {
            return $this->get('movies/'.$id);
        });
    }

    /**
     * Get fan art for a TV show by TVDB ID.
     *
     * @param  int|string  $tvdbId  TVDB ID
     * @return array|null TV show art data or null on failure
     */
    public function getTvFanArt(int|string $tvdbId): ?array
    {
        $cacheKey = 'fanarttv_tv_'.$tvdbId;

        return Cache::remember($cacheKey, now()->addHours(self::CACHE_TTL_HOURS), function () use ($tvdbId) {
            return $this->get('tv/'.$tvdbId);
        });
    }

    /**
     * Get fan art for music by MusicBrainz ID.
     *
     * @param  string  $mbId  MusicBrainz ID
     * @return array|null Music art data or null on failure
     */
    public function getMusicFanArt(string $mbId): ?array
    {
        $cacheKey = 'fanarttv_music_'.$mbId;

        return Cache::remember($cacheKey, now()->addHours(self::CACHE_TTL_HOURS), function () use ($mbId) {
            return $this->get('music/'.$mbId);
        });
    }

    /**
     * Extract movie properties from Fanart.TV response.
     *
     * @param  string  $imdbId  IMDB ID (with or without 'tt' prefix)
     * @return array|null Array with 'cover', 'backdrop', 'banner', 'title' keys or null on failure
     */
    public function getMovieProperties(string $imdbId): ?array
    {
        $art = $this->getMovieFanArt($imdbId);

        if (empty($art)) {
            return null;
        }

        $result = [];

        // Get backdrop (preferring moviebackground, falling back to moviethumb)
        if (! empty($art['moviebackground'][0]['url'])) {
            $result['backdrop'] = $art['moviebackground'][0]['url'];
        } elseif (! empty($art['moviethumb'][0]['url'])) {
            $result['backdrop'] = $art['moviethumb'][0]['url'];
        }

        // Get cover/poster
        if (! empty($art['movieposter'][0]['url'])) {
            $result['cover'] = $art['movieposter'][0]['url'];
        }

        // Get banner
        if (! empty($art['moviebanner'][0]['url'])) {
            $result['banner'] = $art['moviebanner'][0]['url'];
        }

        // Only return if we have both backdrop and cover
        if (isset($result['backdrop'], $result['cover'])) {
            $result['title'] = $art['name'] ?? $imdbId;

            return $result;
        }

        return null;
    }

    /**
     * Extract TV show properties from Fanart.TV response.
     *
     * @param  int|string  $tvdbId  TVDB ID
     * @return array|null Array with 'poster', 'banner', 'background', 'title' keys or null on failure
     */
    public function getTvProperties(int|string $tvdbId): ?array
    {
        $art = $this->getTvFanArt($tvdbId);

        if (empty($art)) {
            return null;
        }

        $result = [];

        // Get poster (sort by likes)
        if (! empty($art['tvposter'])) {
            $best = collect($art['tvposter'])->sortByDesc('likes')->first();
            if (! empty($best['url'])) {
                $result['poster'] = $best['url'];
            }
        }

        // Get banner (sort by likes)
        if (! empty($art['tvbanner'])) {
            $best = collect($art['tvbanner'])->sortByDesc('likes')->first();
            if (! empty($best['url'])) {
                $result['banner'] = $best['url'];
            }
        }

        // Get background (sort by likes)
        if (! empty($art['showbackground'])) {
            $best = collect($art['showbackground'])->sortByDesc('likes')->first();
            if (! empty($best['url'])) {
                $result['background'] = $best['url'];
            }
        }

        // Get HD clearlogo (sort by likes)
        if (! empty($art['hdtvlogo'])) {
            $best = collect($art['hdtvlogo'])->sortByDesc('likes')->first();
            if (! empty($best['url'])) {
                $result['logo'] = $best['url'];
            }
        }

        if (! empty($result)) {
            $result['title'] = $art['name'] ?? (string) $tvdbId;

            return $result;
        }

        return null;
    }

    /**
     * Get the best poster URL for a TV show.
     *
     * @param  int|string  $tvdbId  TVDB ID
     * @return string|null Poster URL or null if not found
     */
    public function getBestTvPoster(int|string $tvdbId): ?string
    {
        $art = $this->getTvFanArt($tvdbId);

        if (empty($art['tvposter'])) {
            return null;
        }

        $best = collect($art['tvposter'])->sortByDesc('likes')->first();

        return $best['url'] ?? null;
    }

    /**
     * Get the best poster URL for a movie.
     *
     * @param  string  $imdbId  IMDB ID (with or without 'tt' prefix)
     * @return string|null Poster URL or null if not found
     */
    public function getBestMoviePoster(string $imdbId): ?string
    {
        $art = $this->getMovieFanArt($imdbId);

        if (empty($art['movieposter'])) {
            return null;
        }

        $best = collect($art['movieposter'])->sortByDesc('likes')->first();

        return $best['url'] ?? null;
    }

    /**
     * Normalize IMDB ID to include 'tt' prefix.
     */
    protected function normalizeImdbId(string $imdbId): string
    {
        if (str_starts_with(strtolower($imdbId), 'tt')) {
            return $imdbId;
        }

        return 'tt'.$imdbId;
    }

    /**
     * Clear cached data for a specific movie.
     */
    public function clearMovieCache(string $imdbId): bool
    {
        $id = $this->normalizeImdbId($imdbId);

        return Cache::forget('fanarttv_movie_'.$id);
    }

    /**
     * Clear cached data for a specific TV show.
     */
    public function clearTvCache(int|string $tvdbId): bool
    {
        return Cache::forget('fanarttv_tv_'.$tvdbId);
    }
}

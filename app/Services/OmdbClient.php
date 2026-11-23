<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\TvProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * OMDb API client built on Laravel's HTTP client.
 *
 * Only looks a title up by its IMDb id and keeps the fields the movie pipeline uses.
 * The free key allows 1,000 requests a day; a spent quota answers 401.
 */
class OmdbClient
{
    protected const BASE_URL = 'https://www.omdbapi.com';

    protected string $apiKey;

    protected int $timeout;

    protected int $retryTimes;

    public function __construct()
    {
        $this->apiKey = trim((string) config('nntmux_api.omdb_api_key', ''));
        $this->timeout = (int) config('nntmux_api.omdb_timeout', 30);
        $this->retryTimes = (int) config('nntmux_api.omdb_retry_times', 2);
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * The OMDb record of an IMDb id; null when OMDb doesn't know the id or no key is configured.
     *
     * @param  string  $imdbId  The numeric IMDb id, without the tt prefix
     * @return array{title: string, year: string, cover: string, genre: string, plot: string, rating: string, rtRating: string, director: string, actors: string, language: string}|null
     *
     * @throws TvProviderException on every failure but an unknown id (spent quota, rejected key, server errors, connection failures)
     */
    public function movieByImdbId(string $imdbId): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            $response = Http::baseUrl(self::BASE_URL)
                ->acceptJson()
                ->timeout($this->timeout)
                ->connectTimeout(10)
                ->retry($this->retryTimes, 500, static fn (\Throwable $exception): bool => $exception instanceof ConnectionException, throw: false)
                ->get('/', ['i' => 'tt'.$imdbId, 'apikey' => $this->apiKey]);
        } catch (ConnectionException $e) {
            throw new TvProviderException('omdb', 'OMDb connection failed: '.$e->getMessage(), null, null, $e);
        }

        $data = $response->json();

        // A spent quota and a rejected key both answer 401 and say nothing about the movie.
        if (! $response->successful()) {
            throw new TvProviderException(
                'omdb',
                sprintf('OMDb returned %d for tt%s: %s', $response->status(), $imdbId, \is_array($data) ? (string) ($data['Error'] ?? '') : ''),
                $response->status(),
                TvProviderException::parseRetryAfter($response->header('Retry-After')),
            );
        }

        if (! \is_array($data)) {
            throw new TvProviderException('omdb', sprintf('OMDb returned an unreadable body for tt%s', $imdbId), $response->status());
        }

        if (($data['Response'] ?? '') !== 'True') {
            return null;
        }

        return [
            'title' => self::field($data, 'Title'),
            'year' => self::field($data, 'Year'),
            'cover' => self::field($data, 'Poster'),
            'genre' => self::field($data, 'Genre'),
            'plot' => self::field($data, 'Plot'),
            'rating' => self::field($data, 'imdbRating'),
            'rtRating' => self::rottenTomatoesScore($data),
            'director' => self::field($data, 'Director'),
            'actors' => self::field($data, 'Actors'),
            'language' => self::field($data, 'Language'),
        ];
    }

    /**
     * A text field of an OMDb record; OMDb writes "N/A" for values it doesn't have.
     *
     * @param  array<string, mixed>  $data
     */
    private static function field(array $data, string $key): string
    {
        $value = \is_scalar($data[$key] ?? null) ? trim((string) $data[$key]) : '';

        return strcasecmp($value, 'N/A') === 0 ? '' : $value;
    }

    /**
     * The Tomatometer score ("83%") of an OMDb record, or '' when OMDb lists none.
     *
     * @param  array<string, mixed>  $data
     */
    private static function rottenTomatoesScore(array $data): string
    {
        foreach (\is_array($data['Ratings'] ?? null) ? $data['Ratings'] : [] as $rating) {
            if (\is_array($rating) && ($rating['Source'] ?? '') === 'Rotten Tomatoes') {
                return self::field($rating, 'Value');
            }
        }

        return '';
    }
}

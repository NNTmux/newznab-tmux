<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\TvProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * MDBList API client built on Laravel's HTTP client.
 *
 * Only asks for Rotten Tomatoes (Tomatometer) scores, several movies per request.
 * The free tier allows 1,000 requests a day; one request of up to 10 ids counts once.
 */
class MdbListClient
{
    protected const BASE_URL = 'https://api.mdblist.com';

    private const string PAUSE_CACHE_KEY = 'mdblist:paused_until';

    /** Seconds to pause when MDBList gives no Retry-After: after a spent limit, and after a rejected key. */
    private const int DEFAULT_LIMIT_PAUSE = 300;

    private const int REJECTED_KEY_PAUSE = 600;

    protected string $apiKey;

    protected int $timeout;

    protected int $retryTimes;

    protected int $batchSize;

    public function __construct()
    {
        $this->apiKey = trim((string) config('nntmux_api.mdblist_api_key', ''));
        $this->timeout = (int) config('nntmux_api.mdblist_timeout', 30);
        $this->retryTimes = (int) config('nntmux_api.mdblist_retry_times', 2);
        $this->batchSize = max(1, (int) config('nntmux_api.mdblist_batch_size', 10));
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    /** How many ids one request carries. */
    public function batchSize(): int
    {
        return $this->batchSize;
    }

    /**
     * Tomatometer scores (0-100) by IMDb id. Movies without a score, and ids MDBList
     * doesn't know, are left out; so is everything when no key is configured.
     *
     * @param  list<string>  $imdbIds  Numeric IMDb ids, without the tt prefix
     * @return array<int|string, int> Keyed by the ids as given; PHP turns ids without a leading zero into integer keys
     *
     * @throws TvProviderException on every failure (spent limit, rejected key, server errors, connection failures)
     */
    public function tomatometerScores(array $imdbIds): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        $scores = [];
        foreach (array_chunk(array_values(array_unique($imdbIds)), $this->batchSize) as $batch) {
            $scores += $this->fetchScores($batch);
        }

        return $scores;
    }

    /**
     * @param  list<string>  $imdbIds
     * @return array<int|string, int>
     */
    private function fetchScores(array $imdbIds): array
    {
        $pauseLeft = (int) Cache::get(self::PAUSE_CACHE_KEY, 0) - time();
        if ($pauseLeft > 0) {
            throw new TvProviderException('mdblist', 'MDBList lookups are paused', 429, $pauseLeft);
        }

        $requested = [];
        foreach ($imdbIds as $imdbId) {
            $requested['tt'.$imdbId] = $imdbId;
        }

        try {
            $response = Http::baseUrl(self::BASE_URL)
                ->acceptJson()
                ->asJson()
                ->timeout($this->timeout)
                ->connectTimeout(10)
                ->retry($this->retryTimes, 500, static fn (\Throwable $exception): bool => $exception instanceof ConnectionException, throw: false)
                ->withQueryParameters(['apikey' => $this->apiKey])
                ->post('/rating/movie/tomatoes', ['provider' => 'imdb', 'ids' => array_keys($requested)]);
        } catch (ConnectionException $e) {
            throw new TvProviderException('mdblist', 'MDBList connection failed: '.$e->getMessage(), null, null, $e);
        }

        $status = $response->status();

        if (! $response->successful()) {
            $retryAfter = TvProviderException::parseRetryAfter($response->header('Retry-After'));

            // A spent limit or a rejected key fails every request the same way: stop asking for a while, in every worker.
            if (\in_array($status, [401, 403, 429], true)) {
                $retryAfter = max(1, $retryAfter ?? ($status === 429 ? self::DEFAULT_LIMIT_PAUSE : self::REJECTED_KEY_PAUSE));
                Cache::put(self::PAUSE_CACHE_KEY, time() + $retryAfter, $retryAfter);
                Log::warning('MDBList refused the request; Rotten Tomatoes lookups pause.', [
                    'status' => $status,
                    'error' => (string) $response->json('error', ''),
                    'seconds' => $retryAfter,
                ]);
            }

            throw new TvProviderException('mdblist', sprintf('MDBList returned %d', $status), $status, $retryAfter);
        }

        $ratings = $response->json('ratings');
        if (! \is_array($ratings)) {
            throw new TvProviderException('mdblist', 'MDBList returned an unreadable body', $status);
        }

        $scores = [];
        foreach ($ratings as $rating) {
            $imdbId = \is_array($rating) ? ($requested[(string) ($rating['id'] ?? '')] ?? null) : null;
            $score = \is_array($rating) ? ($rating['rating'] ?? null) : null;
            if ($imdbId !== null && is_numeric($score) && $score >= 0 && $score <= 100) {
                $scores[$imdbId] = (int) round((float) $score);
            }
        }

        return $scores;
    }
}

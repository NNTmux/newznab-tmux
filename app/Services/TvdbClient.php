<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\TvProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * TheTVDB API v4 client built on Laravel's HTTP client.
 *
 * Covers the four requests the TV pipeline makes and returns the decoded JSON as is.
 * The bearer token is cached and shared by every worker; TVDB tokens last a month.
 */
class TvdbClient
{
    protected const BASE_URL = 'https://api4.thetvdb.com/v4';

    /** Seconds to stop logging in after TVDB rejected the credentials. */
    public const int REJECTED_LOGIN_PAUSE = 600;

    private const int TOKEN_TTL_DAYS = 25;

    /** Episode pages hold 500 rows; no series comes near this many pages. */
    private const int MAX_EPISODE_PAGES = 40;

    /** TVDB's artwork type for a series poster. */
    private const int ARTWORK_TYPE_POSTER = 2;

    protected string $apiKey;

    protected string $pin;

    protected int $timeout;

    protected int $retryTimes;

    public function __construct()
    {
        $this->apiKey = trim((string) config('tvdb.api_key', ''));
        $this->pin = trim((string) config('tvdb.user_pin', ''));
        $this->timeout = (int) config('tvdb.timeout', 30);
        $this->retryTimes = (int) config('tvdb.retry_times', 2);
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * Series matching a name, best match first.
     *
     * @return list<array<string, mixed>>
     *
     * @throws TvProviderException
     */
    public function searchSeries(string $name, ?int $year = null): array
    {
        $query = ['query' => $name, 'type' => 'series'];
        if ($year !== null) {
            $query['year'] = $year;
        }

        return self::rows($this->get('/search', $query)['data'] ?? null);
    }

    /**
     * The extended record of a series with its translations, or null when TVDB has none.
     *
     * @return array<string, mixed>|null
     *
     * @throws TvProviderException
     */
    public function seriesExtended(int $id): ?array
    {
        $data = $this->get('/series/'.$id.'/extended', ['meta' => 'translations', 'short' => 'true'])['data'] ?? null;

        return \is_array($data) && $data !== [] ? $data : null;
    }

    /**
     * Every episode of a series in aired order.
     *
     * @param  array{season?: int, episodeNumber?: int, airDate?: string}  $filters  A season filter of 0 means the specials, so only pass one to narrow the list
     * @param  string  $language  Three-letter language of the episode titles; '' for the series' original language
     * @return list<array<string, mixed>>
     *
     * @throws TvProviderException
     */
    public function seriesEpisodes(int $id, array $filters = [], string $language = ''): array
    {
        $path = '/series/'.$id.'/episodes/default'.($language !== '' ? '/'.$language : '');

        $episodes = [];
        for ($page = 0; $page < self::MAX_EPISODE_PAGES; $page++) {
            $body = $this->get($path, ['page' => $page] + $filters);
            $rows = self::rows($body['data']['episodes'] ?? null);
            if ($rows === []) {
                break;
            }

            array_push($episodes, ...$rows);

            if (empty($body['links']['next'])) {
                break;
            }
        }

        return $episodes;
    }

    /**
     * The posters of a series.
     *
     * @return list<array<string, mixed>>
     *
     * @throws TvProviderException
     */
    public function seriesPosters(int $id): array
    {
        return self::rows($this->get('/series/'.$id.'/artworks', ['type' => self::ARTWORK_TYPE_POSTER])['data']['artworks'] ?? null);
    }

    /**
     * GET a JSON document; null when TVDB has nothing for it or no key is configured.
     *
     * Logs in again once when TVDB no longer accepts the cached token.
     *
     * @param  array<string, scalar>  $query
     * @return array<string, mixed>|null
     *
     * @throws TvProviderException on every failure but a 404 (rate limits, server errors, rejected credentials, connection failures)
     */
    protected function get(string $path, array $query = []): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $token = $this->token($attempt > 0);

            try {
                $response = Http::baseUrl(self::BASE_URL)
                    ->acceptJson()
                    ->withToken($token)
                    ->timeout($this->timeout)
                    ->connectTimeout(10)
                    ->retry($this->retryTimes, 500, static fn (\Throwable $exception): bool => $exception instanceof ConnectionException, throw: false)
                    ->get($path, $query);
            } catch (ConnectionException $e) {
                throw new TvProviderException('tvdb', 'TVDB connection failed: '.$e->getMessage(), null, null, $e);
            }

            if ($response->status() === 401) {
                Cache::forget($this->tokenCacheKey());

                continue;
            }

            return $this->decode($response, $path);
        }

        throw new TvProviderException('tvdb', 'TVDB keeps answering 401 after a fresh login', 401, self::REJECTED_LOGIN_PAUSE);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decode(Response $response, string $path): ?array
    {
        $status = $response->status();

        if (TvProviderException::isTransientStatus($status)) {
            throw new TvProviderException(
                'tvdb',
                sprintf('TVDB returned %d for %s', $status, $path),
                $status,
                TvProviderException::parseRetryAfter($response->header('Retry-After')),
            );
        }

        if ($status === 404) {
            return null;
        }

        // A 403, another 4xx or an unreadable body says nothing about the show: retry
        // later instead of recording "not found".
        if (! $response->successful()) {
            throw new TvProviderException('tvdb', sprintf('TVDB returned %d for %s', $status, $path), $status);
        }

        $body = $response->json();
        if (! \is_array($body) || ! \array_key_exists('data', $body)) {
            throw new TvProviderException('tvdb', sprintf('TVDB returned an unreadable body for %s', $path), $status);
        }

        return $body;
    }

    /**
     * The bearer token, from the cache or a fresh login.
     *
     * @throws TvProviderException when the login fails; rejected credentials pause logins for every worker
     */
    private function token(bool $fresh = false): string
    {
        $cached = $fresh ? null : Cache::get($this->tokenCacheKey());
        if (\is_string($cached) && $cached !== '') {
            return $cached;
        }

        $pauseLeft = (int) Cache::get($this->rejectedCacheKey(), 0) - time();
        if ($pauseLeft > 0) {
            throw new TvProviderException('tvdb', 'TVDB rejected the configured credentials', 401, $pauseLeft);
        }

        try {
            $response = Http::baseUrl(self::BASE_URL)
                ->acceptJson()
                ->timeout($this->timeout)
                ->connectTimeout(10)
                ->retry($this->retryTimes, 500, static fn (\Throwable $exception): bool => $exception instanceof ConnectionException, throw: false)
                ->post('/login', array_filter(['apikey' => $this->apiKey, 'pin' => $this->pin]));
        } catch (ConnectionException $e) {
            throw new TvProviderException('tvdb', 'TVDB login failed: '.$e->getMessage(), null, null, $e);
        }

        $status = $response->status();

        if ($status === 401 || $status === 404) {
            // Not a reason to call every show "not found": the releases wait and retry.
            Log::warning('TVDB rejected the configured credentials; TVDB lookups pause and are retried later.', ['status' => $status]);
            Cache::put($this->rejectedCacheKey(), time() + self::REJECTED_LOGIN_PAUSE, self::REJECTED_LOGIN_PAUSE);

            throw new TvProviderException('tvdb', 'TVDB rejected the configured credentials', 401, self::REJECTED_LOGIN_PAUSE);
        }

        $token = $response->successful() ? $response->json('data.token') : null;
        if (! \is_string($token) || $token === '') {
            throw new TvProviderException(
                'tvdb',
                sprintf('TVDB login failed with status %d', $status),
                $status,
                TvProviderException::parseRetryAfter($response->header('Retry-After')),
            );
        }

        Cache::put($this->tokenCacheKey(), $token, now()->addDays(self::TOKEN_TTL_DAYS));

        return $token;
    }

    /** A token belongs to one key and PIN, so changed credentials never reuse an old one. */
    private function tokenCacheKey(): string
    {
        return 'tvdb:token:'.md5($this->apiKey.'|'.$this->pin);
    }

    private function rejectedCacheKey(): string
    {
        return 'tvdb:login_rejected:'.md5($this->apiKey.'|'.$this->pin);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function rows(mixed $value): array
    {
        return \is_array($value) ? array_values(array_filter($value, 'is_array')) : [];
    }
}

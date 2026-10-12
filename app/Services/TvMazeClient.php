<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\TvProviderException;
use DariusIII\TVMaze\Episode;
use DariusIII\TVMaze\TVShow;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * TVMaze API client built on Laravel's HTTP client.
 *
 * Replaces the vendor curl transport (no timeout, rate-limit bodies returned as data)
 * while keeping the vendor TVShow/Episode value objects the TV providers already use.
 * TVMaze needs no API key; it allows about 20 calls per 10 seconds per IP.
 */
class TvMazeClient
{
    protected const BASE_URL = 'https://api.tvmaze.com';

    /** Minimum seconds between requests from one process. */
    protected const MIN_INTERVAL_SECONDS = 1.0;

    private static float $lastRequestAt = 0.0;

    protected int $timeout;

    protected int $retryTimes;

    public function __construct()
    {
        $this->timeout = (int) config('nntmux_api.tvmaze_timeout', 30);
        $this->retryTimes = (int) config('nntmux_api.tvmaze_retry_times', 2);
    }

    /**
     * @return list<TVShow>
     */
    public function searchShows(string $name): array
    {
        $shows = [];
        foreach ($this->getList('/search/shows', ['q' => $name]) as $result) {
            if (\is_array($result['show'] ?? null)) {
                $shows[] = new TVShow($result['show']);
            }
        }

        return $shows;
    }

    /** The single best match for a name, with its AKAs embedded. */
    public function singleSearchAkas(string $name): ?TVShow
    {
        $show = $this->get('/singlesearch/shows', ['q' => $name, 'embed' => 'akas']);

        return $show !== null ? new TVShow($show) : null;
    }

    public function showById(int $id): ?TVShow
    {
        $show = $this->get('/shows/'.$id, ['embed' => 'akas']);

        return $show !== null ? new TVShow($show) : null;
    }

    /**
     * Look up a show by another site's ID.
     *
     * @param  string  $site  'thetvdb', 'tvrage' or 'imdb' (with the tt prefix)
     */
    public function lookup(string $site, int|string $id): ?TVShow
    {
        $show = $this->get('/lookup/shows', [strtolower($site) => (string) $id]);

        return $show !== null ? new TVShow($show) : null;
    }

    /**
     * @return list<string>
     */
    public function akas(int $showId): array
    {
        $names = [];
        foreach ($this->getList('/shows/'.$showId.'/akas') as $aka) {
            if (\is_string($aka['name'] ?? null) && $aka['name'] !== '') {
                $names[] = $aka['name'];
            }
        }

        return $names;
    }

    /**
     * @return list<Episode>
     */
    public function episodesByShow(int $showId, bool $specials = true): array
    {
        return array_map(
            static fn (array $episode): Episode => new Episode($episode),
            $this->getList('/shows/'.$showId.'/episodes', $specials ? ['specials' => 1] : []),
        );
    }

    public function episodeByNumber(int $showId, int $season, int $episode): ?Episode
    {
        $data = $this->get('/shows/'.$showId.'/episodebynumber', ['season' => $season, 'number' => $episode]);

        return $data !== null ? new Episode($data) : null;
    }

    /**
     * @return list<Episode>
     */
    public function episodesByDate(int $showId, string $airdate): array
    {
        $timestamp = strtotime($airdate);
        if ($timestamp === false) {
            return [];
        }

        return array_map(
            static fn (array $episode): Episode => new Episode($episode),
            $this->getList('/shows/'.$showId.'/episodesbydate', ['date' => date('Y-m-d', $timestamp)]),
        );
    }

    /**
     * @param  array<string, scalar>  $query
     * @return list<array<string, mixed>>
     */
    private function getList(string $path, array $query = []): array
    {
        $data = $this->get($path, $query);
        if ($data === null || ! array_is_list($data)) {
            return [];
        }

        return array_values(array_filter($data, 'is_array'));
    }

    /**
     * GET a JSON document; null when TVMaze has nothing for it.
     *
     * @param  array<string, scalar>  $query
     * @return array<int|string, mixed>|null
     *
     * @throws TvProviderException on rate limits, server errors and connection failures
     */
    protected function get(string $path, array $query = []): ?array
    {
        $this->throttle();

        try {
            $response = Http::baseUrl(self::BASE_URL)
                ->acceptJson()
                ->timeout($this->timeout)
                ->connectTimeout(10)
                ->retry($this->retryTimes, 500, static fn (\Throwable $exception): bool => $exception instanceof ConnectionException, throw: false)
                ->get($path, $query);
        } catch (ConnectionException $e) {
            throw new TvProviderException('tvmaze', 'TVMaze connection failed: '.$e->getMessage(), null, null, $e);
        }

        return $this->decode($response, $path);
    }

    /**
     * @return array<int|string, mixed>|null
     */
    private function decode(Response $response, string $path): ?array
    {
        $status = $response->status();

        if (TvProviderException::isTransientStatus($status)) {
            throw new TvProviderException(
                'tvmaze',
                sprintf('TVMaze returned %d for %s', $status, $path),
                $status,
                TvProviderException::parseRetryAfter($response->header('Retry-After')),
            );
        }

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        return \is_array($data) && $data !== [] ? $data : null;
    }

    private function throttle(): void
    {
        $elapsed = microtime(true) - self::$lastRequestAt;
        if (self::$lastRequestAt > 0.0 && $elapsed < self::MIN_INTERVAL_SECONDS && ! app()->runningUnitTests()) {
            usleep((int) ((self::MIN_INTERVAL_SECONDS - $elapsed) * 1_000_000));
        }
        self::$lastRequestAt = microtime(true);
    }
}

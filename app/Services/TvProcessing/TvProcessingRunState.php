<?php

declare(strict_types=1);

namespace App\Services\TvProcessing;

use Illuminate\Support\Facades\Cache;

/**
 * Memo shared by every release processed in one pipeline run.
 *
 * Keeps providers from searching the same unknown title or downloading the same
 * show's episode list twice, and trips a per-provider circuit breaker after
 * repeated transient failures.
 */
final class TvProcessingRunState
{
    /** How long a show's episode list counts as fresh across worker processes. */
    public const int EPISODE_REFRESH_TTL = 3600;

    /** Consecutive transient failures before a provider is skipped for the rest of the run. */
    public const int CIRCUIT_BREAKER_THRESHOLD = 3;

    /** @var array<string, array<string, true>> */
    private array $notFoundTitles = [];

    /** @var array<string, true> */
    private array $refreshedShows = [];

    /** @var array<string, true> */
    private array $episodeLookups = [];

    /** @var array<string, int> */
    private array $consecutiveTransient = [];

    public function isTitleNotFound(string $provider, string $title): bool
    {
        return isset($this->notFoundTitles[$provider][mb_strtolower($title)]);
    }

    public function rememberTitleNotFound(string $provider, string $title): void
    {
        $this->notFoundTitles[$provider][mb_strtolower($title)] = true;
    }

    public function wasEpisodeListRefreshed(string $provider, int $videoId): bool
    {
        $key = $this->refreshKey($provider, $videoId);

        return isset($this->refreshedShows[$key]) || Cache::has($key);
    }

    public function markEpisodeListRefreshed(string $provider, int $videoId): void
    {
        $key = $this->refreshKey($provider, $videoId);
        $this->refreshedShows[$key] = true;
        Cache::put($key, 1, self::EPISODE_REFRESH_TTL);
    }

    /**
     * True the first time a provider asks for this episode of a show in this run, so
     * several releases of one episode don't repeat the same API lookup.
     */
    public function firstEpisodeLookup(string $provider, int $videoId, string $episodeKey): bool
    {
        $key = $provider.':'.$videoId.':'.$episodeKey;
        if (isset($this->episodeLookups[$key])) {
            return false;
        }
        $this->episodeLookups[$key] = true;

        return true;
    }

    public function recordTransient(string $provider): void
    {
        $this->consecutiveTransient[$provider] = ($this->consecutiveTransient[$provider] ?? 0) + 1;
    }

    public function recordSuccess(string $provider): void
    {
        $this->consecutiveTransient[$provider] = 0;
    }

    public function isCircuitOpen(string $provider): bool
    {
        return ($this->consecutiveTransient[$provider] ?? 0) >= self::CIRCUIT_BREAKER_THRESHOLD;
    }

    private function refreshKey(string $provider, int $videoId): string
    {
        return 'tv:eps-refreshed:'.mb_strtolower($provider).':'.$videoId;
    }
}

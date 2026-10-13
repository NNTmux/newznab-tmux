<?php

declare(strict_types=1);

namespace Tests\Support\Tv;

use App\Exceptions\TvProviderException;
use App\Services\TvProcessing\Providers\AbstractTvProvider;

/**
 * In-memory TV provider: shows keyed by lowercase title, episodes keyed by site id.
 */
final class FakeTvProvider extends AbstractTvProvider
{
    /** @var array{search: int, all: int, episode: int, airdate: int} */
    public array $calls = ['search' => 0, 'all' => 0, 'episode' => 0, 'airdate' => 0];

    /** Thrown by every API call while set. */
    public ?TvProviderException $failWith = null;

    /** @var list<string> Lowercase titles whose search throws, to simulate a bug rather than an outage. */
    public array $crashTitles = [];

    /**
     * @param  array<string, array<string, mixed>>  $shows  formatShowInfo() fields, keyed by lowercase title
     * @param  array<int, list<array{series: int, episode: int, firstaired?: string|null, title?: string}>>  $episodes
     */
    public function __construct(
        public array $shows = [],
        public array $episodes = [],
        public bool $airdateLookup = false,
    ) {
        parent::__construct();
    }

    /**
     * @return array<string, mixed>|false
     */
    public function getShowInfo(string $name, ?int $year = null, string $country = ''): array|false
    {
        $this->calls['search']++;
        $this->fail();
        if (\in_array(mb_strtolower($name), $this->crashTitles, true)) {
            throw new \RuntimeException('Unexpected response for '.$name);
        }

        $show = $this->shows[mb_strtolower($name)] ?? null;

        return $show === null ? false : $this->formatShowInfo($show);
    }

    public function fetchAllEpisodes(int $siteId, int $videoId): int
    {
        $this->calls['all']++;
        $this->fail();

        return $this->addEpisodes($videoId, array_map($this->formatEpisodeInfo(...), $this->episodes[$siteId] ?? []));
    }

    /**
     * @return array<string, mixed>|false
     */
    public function fetchEpisode(int $siteId, int $season, int $episode, int $videoId = 0): array|false
    {
        $this->calls['episode']++;
        $this->fail();

        foreach ($this->episodes[$siteId] ?? [] as $candidate) {
            if ($candidate['series'] === $season && $candidate['episode'] === $episode) {
                return $this->formatEpisodeInfo($candidate);
            }
        }

        return false;
    }

    public function supportsAirdateLookup(): bool
    {
        return $this->airdateLookup;
    }

    public function fetchEpisodesByAirdate(int $siteId, int $videoId, string $airdate): int
    {
        $this->calls['airdate']++;
        $this->fail();

        $matching = array_filter($this->episodes[$siteId] ?? [], static fn (array $episode): bool => ($episode['firstaired'] ?? null) === $airdate);

        return $this->addEpisodes($videoId, array_map($this->formatEpisodeInfo(...), $matching));
    }

    /**
     * @return array<string, mixed>
     */
    public function formatShowInfo(mixed $show): array
    {
        return [
            'type' => self::TYPE_TV,
            'title' => '',
            'summary' => '',
            'started' => '2020-01-01',
            'publisher' => '',
            'country' => '',
            'source' => self::SOURCE_TVDB,
            'imdb' => '',
            'tvdb' => 0,
            'trakt' => 0,
            'tvrage' => 0,
            'tvmaze' => 0,
            'tmdb' => 0,
            'aliases' => '',
            'localzone' => '',
            'poster' => '',
            ...$show,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formatEpisodeInfo(mixed $episode): array
    {
        return [
            'title' => (string) ($episode['title'] ?? 'Episode'),
            'series' => (int) $episode['series'],
            'episode' => (int) $episode['episode'],
            'se_complete' => sprintf('S%02dE%02d', $episode['series'], $episode['episode']),
            'firstaired' => (string) ($episode['firstaired'] ?? ''),
            'summary' => '',
        ];
    }

    private function fail(): void
    {
        if ($this->failWith !== null) {
            throw $this->failWith;
        }
    }
}

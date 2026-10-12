<?php

declare(strict_types=1);

namespace App\Services\TvProcessing\Providers;

/**
 * Class LocalDbProvider -- the provider-independent parts of TV processing.
 *
 * Parses release names and matches them against already scraped shows and
 * episodes, without calling any external API.
 */
class LocalDbProvider extends AbstractTvProvider
{
    /**
     * @return array<string, mixed>|false
     */
    public function getShowInfo(string $name, ?int $year = null, string $country = ''): array|false
    {
        return false;
    }

    public function fetchAllEpisodes(int $siteId, int $videoId): int
    {
        return 0;
    }

    /**
     * @return array<string, mixed>|false
     */
    public function fetchEpisode(int $siteId, int $season, int $episode, int $videoId = 0): array|false
    {
        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function formatShowInfo(mixed $show): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function formatEpisodeInfo(mixed $episode): array
    {
        return [];
    }
}

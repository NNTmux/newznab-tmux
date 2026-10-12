<?php

declare(strict_types=1);

namespace App\Services\TvProcessing\Providers;

use App\Services\TraktService;
use App\Services\TvMazeClient;
use Illuminate\Support\Carbon;

/**
 * Class TraktProvider.
 *
 * Process information retrieved from the Trakt API.
 */
class TraktProvider extends AbstractTvProvider
{
    public TraktService $client;

    /**
     * The URL to grab the TV fanart.
     */
    public string $fanartUrl = '';

    /**
     * The localized (network airing) timezone of the show being processed.
     */
    private string $localizedTZ = '';

    public function __construct(?TraktService $client = null)
    {
        parent::__construct();
        $this->client = $client ?? new TraktService;
    }

    /**
     * Get show information from Trakt by name.
     *
     * @return array<string, mixed>|false
     */
    public function getShowInfo(string $name, ?int $year = null, string $country = ''): array|false
    {
        $client = $this->tvClient();
        if (! $client->isConfigured()) {
            return false;
        }

        // Trakt does NOT like shows with the year in them even without the parentheses
        $name = (string) preg_replace('# \((19|20)\d{2}\)$#', '', $name);

        $highestScore = 0.0;
        $highest = null;
        foreach ((array) $client->searchShows($name) as $result) {
            $show = \is_array($result) ? ($result['show'] ?? null) : null;
            if (! \is_array($show) || empty($show['title'])) {
                continue;
            }

            $score = $this->scoreCandidate($name, $year, (string) $show['title'], isset($show['year']) ? (int) $show['year'] : null);
            if ($score > $highestScore) {
                $highestScore = $score;
                $highest = $show;
            }
        }

        if ($highest === null || empty($highest['ids']['trakt'])) {
            return false;
        }

        $fullShow = $client->getShowSummary($highest['ids']['trakt']);

        return \is_array($fullShow) && $this->checkRequiredAttr($fullShow, 'traktS')
            ? $this->formatShowInfo($fullShow)
            : false;
    }

    public function fetchAllEpisodes(int $siteId, int $videoId): int
    {
        $client = $this->tvClient();
        if ($siteId <= 0 || $videoId <= 0 || ! $client->isConfigured()) {
            return 0;
        }

        $this->localizedTZ = $this->getLocalZoneFromVideoID($videoId);

        $seasons = $client->getShowSeasons((string) $siteId, 'full,episodes');
        if (! \is_array($seasons)) {
            return 0;
        }

        $formatted = [];
        foreach ($seasons as $seasonInfo) {
            if (! \is_array($seasonInfo) || (int) ($seasonInfo['number'] ?? -1) < 0) {
                continue;
            }

            foreach (\is_array($seasonInfo['episodes'] ?? null) ? $seasonInfo['episodes'] : [] as $episode) {
                if (\is_array($episode) && $this->checkRequiredAttr($episode, 'traktE')) {
                    $formatted[] = $this->formatEpisodeInfo($episode);
                }
            }
        }

        return $this->addEpisodes($videoId, $formatted);
    }

    /**
     * Single episode lookup, falling back to the show's TMDB, TVDB and IMDB ids.
     *
     * @return array<string, mixed>|false
     */
    public function fetchEpisode(int $siteId, int $season, int $episode, int $videoId = 0): array|false
    {
        $client = $this->tvClient();
        if ($season < 0 || $episode <= 0 || ! $client->isConfigured()) {
            return false;
        }

        if ($videoId > 0) {
            $this->localizedTZ = $this->getLocalZoneFromVideoID($videoId);
            $ids = $this->getAllSiteIdsFromVideoID($videoId);
            if ($siteId > 0) {
                $ids['trakt'] = $siteId;
            }
            $response = $client->getEpisodeSummaryWithFallback($ids, $season, $episode, 'full');
        } elseif ($siteId > 0) {
            $response = $client->getEpisodeSummary($siteId, $season, $episode, 'full');
        } else {
            return false;
        }

        return \is_array($response) && $this->checkRequiredAttr($response, 'traktE')
            ? $this->formatEpisodeInfo($response)
            : false;
    }

    /**
     * Save the poster, falling back to the fan art.
     */
    public function getPoster(int $videoId): int
    {
        if ($this->savePoster($videoId, $this->posterUrl) || $this->savePoster($videoId, $this->fanartUrl)) {
            return 1;
        }

        return 0;
    }

    /**
     * Assigns API show response values to a formatted array for insertion
     * Returns the formatted array.
     *
     * @return array<string, mixed>
     */
    public function formatShowInfo(mixed $show): array
    {
        $this->posterUrl = $this->fanartUrl = $this->localizedTZ = '';
        if (! \is_array($show)) {
            return [];
        }

        $ids = \is_array($show['ids'] ?? null) ? $show['ids'] : [];
        $airs = \is_array($show['airs'] ?? null) ? $show['airs'] : [];
        $images = \is_array($show['images'] ?? null) ? $show['images'] : [];

        preg_match('/tt(?P<imdbid>\d{6,})$/i', (string) ($ids['imdb'] ?? ''), $imdb);
        $this->posterUrl = $this->imageUrl($images['poster'] ?? null);
        $this->fanartUrl = $this->imageUrl($images['fanart'] ?? null);
        $this->localizedTZ = (string) ($airs['timezone'] ?? '');

        $imdbId = (string) ($imdb['imdbid'] ?? '');
        $tvdbId = (int) ($ids['tvdb'] ?? 0);

        return [
            'type' => parent::TYPE_TV,
            'title' => (string) ($show['title'] ?? ''),
            'summary' => (string) ($show['overview'] ?? ''),
            'started' => $this->formatTraktDate($show['first_aired'] ?? null),
            'publisher' => (string) ($show['network'] ?? ''),
            'country' => (string) ($show['country'] ?? ''),
            'source' => parent::SOURCE_TRAKT,
            'imdb' => $imdbId,
            'tvdb' => $tvdbId,
            'trakt' => (int) ($ids['trakt'] ?? 0),
            'tvrage' => (int) ($ids['tvrage'] ?? 0),
            'tvmaze' => $this->lookupTvMazeId($tvdbId, $imdbId),
            'tmdb' => (int) ($ids['tmdb'] ?? 0),
            'aliases' => ! empty($show['aliases']) ? $show['aliases'] : '',
            'localzone' => $this->localizedTZ,
            'poster' => $this->posterUrl,
        ];
    }

    /**
     * Trakt image fields are either the legacy {thumb, full, medium} object or a list of
     * host paths without a scheme.
     */
    private function imageUrl(mixed $image): string
    {
        if (! \is_array($image)) {
            return '';
        }

        $url = (string) ($image['full'] ?? $image['medium'] ?? $image['thumb'] ?? (\is_string($image[0] ?? null) ? $image[0] : ''));

        if ($url !== '' && ! str_starts_with($url, 'http')) {
            $url = 'https://'.ltrim($url, '/');
        }

        return $url;
    }

    /**
     * Look up TVMaze ID using TVDB ID or IMDB ID.
     *
     * @param  int|string  $imdbId  IMDB ID (numeric, without 'tt' prefix)
     * @return int TVMaze ID or 0 if not found
     */
    protected function lookupTvMazeId(int $tvdbId, int|string $imdbId): int
    {
        try {
            $tvmaze = app(TvMazeClient::class);

            $show = $tvdbId > 0 ? $tvmaze->lookup('thetvdb', $tvdbId) : null;
            if ($show === null && ! empty($imdbId) && imdb_id_is_valid($imdbId)) {
                $show = $tvmaze->lookup('imdb', 'tt'.$imdbId);
            }

            return (int) ($show->id ?? 0);
        } catch (\Throwable) {
            // TVMaze ID lookup is optional enrichment.
            return 0;
        }
    }

    /**
     * Assigns API episode response values to a formatted array for insertion
     * Returns the formatted array.
     *
     * @return array<string, mixed>
     */
    public function formatEpisodeInfo(mixed $episode): array
    {
        $season = (int) ($episode['season'] ?? 0);
        $episodeNumber = (int) ($episode['number'] ?? ($episode['episode'] ?? 0));

        return [
            'title' => (string) ($episode['title'] ?? ''),
            'series' => $season,
            'episode' => $episodeNumber,
            'se_complete' => 'S'.sprintf('%02d', $season).'E'.sprintf('%02d', $episodeNumber),
            'firstaired' => $this->formatTraktDate($episode['first_aired'] ?? null),
            'summary' => (string) ($episode['overview'] ?? ''),
        ];
    }

    /**
     * Trakt dates are UTC instants; the airdate is the local date in the show's timezone.
     */
    private function formatTraktDate(mixed $date): string
    {
        if (empty($date)) {
            return '';
        }

        try {
            return Carbon::parse((string) $date)
                ->setTimezone($this->localizedTZ !== '' ? $this->localizedTZ : 'UTC')
                ->format('Y-m-d');
        } catch (\Throwable) {
            try {
                return Carbon::parse((string) $date)->format('Y-m-d');
            } catch (\Throwable) {
                return '';
            }
        }
    }

    /**
     * Trakt client for TV processing: transient failures are thrown so releases are retried later.
     */
    private function tvClient(): TraktService
    {
        return $this->client->throwingOnTransientFailure();
    }
}

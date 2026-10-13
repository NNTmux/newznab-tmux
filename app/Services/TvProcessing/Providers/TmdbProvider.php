<?php

declare(strict_types=1);

namespace App\Services\TvProcessing\Providers;

use App\Services\TmdbClient;
use App\Services\TraktService;

class TmdbProvider extends AbstractTvProvider
{
    /**
     * TMDB client that throws TvProviderException on transient failures.
     */
    protected TmdbClient $tmdbClient;

    public function __construct(?TmdbClient $client = null)
    {
        parent::__construct();
        $this->tmdbClient = ($client ?? app(TmdbClient::class))->throwingOnTransientFailure();
    }

    /**
     * Calls the API to perform initial show name match to TMDB title
     * Returns a formatted array of show data or false if no match.
     *
     * @return array<string, mixed>|false
     */
    public function getShowInfo(string $name, ?int $year = null, string $country = ''): array|false
    {
        if (! $this->tmdbClient->isConfigured()) {
            return false;
        }

        $name = (string) preg_replace('# \((19|20)\d{2}\)$#', '', $name);

        $response = $year !== null ? $this->tmdbClient->searchTv($name, 1, $year) : null;
        if (empty($response['results'])) {
            $response = $this->tmdbClient->searchTv($name);
        }

        if (empty($response['results']) || ! \is_array($response['results'])) {
            return false;
        }

        return $this->matchShowInfo($response['results'], $name, $year, $country);
    }

    /**
     * @param  array<int|string, mixed>  $shows
     * @return array<string, mixed>|false
     */
    private function matchShowInfo(array $shows, string $cleanName, ?int $year, string $country): array|false
    {
        $highestScore = 0.0;
        $highest = null;

        foreach ($shows as $show) {
            if (! \is_array($show) || ! $this->checkRequiredAttr($show, 'tmdbS')) {
                continue;
            }

            $originCountry = TmdbClient::getArray($show, 'origin_country');
            foreach (array_unique([TmdbClient::getString($show, 'name'), TmdbClient::getString($show, 'original_name')]) as $candidateName) {
                $score = $this->scoreCandidate(
                    $cleanName,
                    $year,
                    $candidateName,
                    TmdbClient::getString($show, 'first_air_date'),
                    $country,
                    (string) ($originCountry[0] ?? ''),
                );
                if ($score > $highestScore) {
                    $highestScore = $score;
                    $highest = $show;
                }
            }
        }

        if ($highest === null) {
            return false;
        }

        $showId = TmdbClient::getInt($highest, 'id');
        if ($showId === 0) {
            return false;
        }

        // One request for the details the search result lacks (network, external ids, aliases).
        $details = $this->tmdbClient->getTvShow($showId, ['external_ids', 'alternative_titles']) ?? [];

        $alternativeTitles = [];
        foreach (TmdbClient::getArray(TmdbClient::getArray($details, 'alternative_titles'), 'results') as $aka) {
            if (\is_array($aka) && isset($aka['title'])) {
                $alternativeTitles[] = $aka['title'];
            }
        }
        $highest['alternative_titles'] = $alternativeTitles;

        $networks = array_values(TmdbClient::getArray($details, 'networks'));
        $highest['network'] = \is_array($networks[0] ?? null) ? (string) ($networks[0]['name'] ?? '') : '';
        $highest['external_ids'] = TmdbClient::getArray($details, 'external_ids');

        return $this->formatShowInfo($highest);
    }

    public function fetchAllEpisodes(int $siteId, int $videoId): int
    {
        if ($siteId <= 0 || $videoId <= 0 || ! $this->tmdbClient->isConfigured()) {
            return 0;
        }

        $tvDetails = $this->tmdbClient->getTvShow($siteId);
        if ($tvDetails === null) {
            return 0;
        }

        $formatted = [];
        foreach (TmdbClient::getArray($tvDetails, 'seasons') as $seasonInfo) {
            if (! \is_array($seasonInfo)) {
                continue;
            }

            // Season 0 holds the specials.
            $seasonNumber = TmdbClient::getInt($seasonInfo, 'season_number', -1);
            if ($seasonNumber < 0) {
                continue;
            }

            $seasonData = $this->tmdbClient->getTvSeason($siteId, $seasonNumber);
            foreach (TmdbClient::getArray($seasonData ?? [], 'episodes') as $episode) {
                if (\is_array($episode) && $this->checkRequiredAttr($episode, 'tmdbE')) {
                    $formatted[] = $this->formatEpisodeInfo($episode);
                }
            }
        }

        return $this->addEpisodes($videoId, $formatted);
    }

    /**
     * Single episode lookup, falling back to the show's TVDB and IMDB ids.
     *
     * @return array<string, mixed>|false
     */
    public function fetchEpisode(int $siteId, int $season, int $episode, int $videoId = 0): array|false
    {
        if ($season < 0 || $episode <= 0 || ! $this->tmdbClient->isConfigured()) {
            return false;
        }

        if ($videoId > 0) {
            $ids = $this->getAllSiteIdsFromVideoID($videoId);
            if ($siteId > 0) {
                $ids['tmdb'] = $siteId;
            }
            $response = $this->tmdbClient->getTvEpisodeWithFallback($ids, $season, $episode);
        } elseif ($siteId > 0) {
            $response = $this->tmdbClient->getTvEpisode($siteId, $season, $episode);
        } else {
            return false;
        }

        return \is_array($response) && $this->checkRequiredAttr($response, 'tmdbE')
            ? $this->formatEpisodeInfo($response)
            : false;
    }

    /**
     * Assigns API show response values to a formatted array for insertion
     * Returns the formatted array.
     *
     * @return array<string, mixed>
     */
    public function formatShowInfo(mixed $show): array
    {
        $this->posterUrl = '';
        if (! \is_array($show)) {
            return [];
        }

        $posterPath = TmdbClient::getString($show, 'poster_path');
        $this->posterUrl = $posterPath !== '' ? 'https://image.tmdb.org/t/p/w500'.$posterPath : '';

        $imdbId = '';
        $externalIds = TmdbClient::getArray($show, 'external_ids');
        if (! empty($externalIds['imdb_id'])) {
            preg_match('/tt(?P<imdbid>\d{6,})$/i', (string) $externalIds['imdb_id'], $imdb);
            $imdbId = (string) ($imdb['imdbid'] ?? '');
        }

        $originCountry = TmdbClient::getArray($show, 'origin_country');
        $alternativeTitles = TmdbClient::getArray($show, 'alternative_titles');

        $traktId = 0;
        $tmdbId = TmdbClient::getInt($show, 'id');
        if ($tmdbId > 0) {
            $traktId = $this->lookupTraktId($tmdbId, $imdbId, TmdbClient::getInt($externalIds, 'tvdb_id'));
        }

        return [
            'type' => parent::TYPE_TV,
            'title' => TmdbClient::getString($show, 'name'),
            'summary' => TmdbClient::getString($show, 'overview'),
            'started' => TmdbClient::getString($show, 'first_air_date'),
            'publisher' => TmdbClient::getString($show, 'network'),
            'country' => (string) ($originCountry[0] ?? ''),
            'source' => parent::SOURCE_TMDB,
            'imdb' => $imdbId,
            'tvdb' => TmdbClient::getInt($externalIds, 'tvdb_id'),
            'trakt' => $traktId,
            'tvrage' => TmdbClient::getInt($externalIds, 'tvrage_id'),
            'tvmaze' => 0,
            'tmdb' => $tmdbId,
            'aliases' => ! empty($alternativeTitles) ? $alternativeTitles : '',
            'localzone' => '',
            'poster' => $this->posterUrl,
        ];
    }

    /**
     * Look up Trakt ID using available external IDs.
     * Tries TMDB ID first, then IMDB, then TVDB.
     *
     * @param  int|string  $imdbId  IMDB ID (numeric, without 'tt' prefix)
     * @return int Trakt ID or 0 if not found
     */
    protected function lookupTraktId(int $tmdbId, int|string $imdbId, int $tvdbId): int
    {
        try {
            $traktService = app(TraktService::class);

            if (! $traktService->isConfigured()) {
                return 0;
            }

            $lookups = [['tmdb', $tmdbId], ['imdb', $imdbId], ['tvdb', $tvdbId]];
            foreach ($lookups as [$type, $id]) {
                if (empty($id) || ($type === 'imdb' && ! imdb_id_is_valid($id))) {
                    continue;
                }
                $ids = $traktService->lookupShowIds($id, $type);
                if (! empty($ids['trakt'])) {
                    return (int) $ids['trakt'];
                }
            }
        } catch (\Throwable) {
            // Trakt ID lookup is optional enrichment.
        }

        return 0;
    }

    /**
     * Assigns API episode response values to a formatted array for insertion
     * Returns the formatted array.
     *
     * @return array<string, mixed>
     */
    public function formatEpisodeInfo(mixed $episode): array
    {
        if (! \is_array($episode)) {
            return [];
        }

        $seasonNumber = TmdbClient::getInt($episode, 'season_number');
        $episodeNumber = TmdbClient::getInt($episode, 'episode_number');

        return [
            'title' => TmdbClient::getString($episode, 'name'),
            'series' => $seasonNumber,
            'episode' => $episodeNumber,
            'se_complete' => 'S'.sprintf('%02d', $seasonNumber).'E'.sprintf('%02d', $episodeNumber),
            'firstaired' => TmdbClient::getString($episode, 'air_date'),
            'summary' => TmdbClient::getString($episode, 'overview'),
        ];
    }
}

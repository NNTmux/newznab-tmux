<?php

declare(strict_types=1);

namespace App\Services\TvProcessing\Providers;

use App\Services\TvMazeClient;
use DariusIII\TVMaze\Episode;
use DariusIII\TVMaze\TVShow;

/**
 * Class TvMazeProvider.
 *
 * Process information retrieved from the TVMaze API.
 */
class TvMazeProvider extends AbstractTvProvider
{
    /** An exact title whose year doesn't contradict the release. */
    private const float CONVINCING_SCORE = 100.0;

    public TvMazeClient $client;

    public function __construct(?TvMazeClient $client = null)
    {
        parent::__construct();
        $this->client = $client ?? app(TvMazeClient::class);
    }

    /**
     * Calls the API to find the best matching show for a title.
     *
     * @return array<string, mixed>|false
     */
    public function getShowInfo(string $name, ?int $year = null, string $country = ''): array|false
    {
        // TVMaze does NOT like shows with the year in them even without the parentheses
        $name = (string) preg_replace('# \((19|20)\d{2}\)$#', '', $name);

        // The single search embeds AKAs; the full search ranks better but carries none.
        // Only a convincing single result (exact title, year not contradicting) skips the
        // full search; otherwise both compete, so a same-titled remake can't win by default.
        $candidates = [];
        $single = $this->client->singleSearchAkas($name);
        if ($single !== null) {
            $candidates[] = $single;
        }
        [$best, $score] = $this->bestShow($candidates, $name, $year, $country);

        if ($score < self::CONVINCING_SCORE) {
            [$fullBest, $fullScore] = $this->bestShow($this->client->searchShows($name), $name, $year, $country);
            if ($fullScore > $score) {
                $best = $fullBest;
            }
        }

        $return = $best !== null ? $this->formatShowInfo($best) : false;

        if (\is_array($return) && empty($return['aliases']) && (int) $return['tvmaze'] > 0) {
            $return['aliases'] = $this->client->akas((int) $return['tvmaze']);
        }

        return $return;
    }

    /**
     * The best matching show by title or aliases, with its score; [null, 0.0] when none scores.
     *
     * @param  list<TVShow>  $shows
     * @return array{0: TVShow|null, 1: float}
     */
    private function bestShow(array $shows, string $cleanName, ?int $year, string $country): array
    {
        $highestScore = 0.0;
        $highest = null;

        foreach ($shows as $show) {
            if (! $this->checkRequiredAttr($show, 'tvmazeS')) {
                continue;
            }

            $names = [(string) $show->name];
            foreach (\is_array($show->akas) ? $show->akas : [] as $aka) {
                if (\is_array($aka) && ! empty($aka['name'])) {
                    $names[] = (string) $aka['name'];
                }
            }

            foreach ($names as $candidateName) {
                $score = $this->scoreCandidate($cleanName, $year, $candidateName, $show->premiered, $country, (string) $show->country);
                if ($score > $highestScore) {
                    $highestScore = $score;
                    $highest = $show;
                }
            }
        }

        return [$highest, $highestScore];
    }

    public function resolveSiteId(array $knownIds): int
    {
        $show = $knownIds['tvdb'] > 0 ? $this->client->lookup('thetvdb', $knownIds['tvdb']) : null;
        $imdb = $this->imdbWithPrefix($knownIds['imdb']);
        if ($show === null && $imdb !== '') {
            $show = $this->client->lookup('imdb', $imdb);
        }

        return $show !== null ? (int) $show->id : 0;
    }

    public function fetchAllEpisodes(int $siteId, int $videoId): int
    {
        if ($siteId <= 0 || $videoId <= 0) {
            return 0;
        }

        return $this->addEpisodes($videoId, $this->formatEpisodes($this->client->episodesByShow($siteId)));
    }

    public function supportsAirdateLookup(): bool
    {
        return true;
    }

    public function fetchEpisodesByAirdate(int $siteId, int $videoId, string $airdate): int
    {
        if ($siteId <= 0 || $videoId <= 0) {
            return 0;
        }

        return $this->addEpisodes($videoId, $this->formatEpisodes($this->client->episodesByDate($siteId, $airdate)));
    }

    /**
     * @return array<string, mixed>|false
     */
    public function fetchEpisode(int $siteId, int $season, int $episode, int $videoId = 0): array|false
    {
        if ($siteId <= 0 || $season < 0 || $episode <= 0) {
            return false;
        }

        $response = $this->client->episodeByNumber($siteId, $season, $episode);

        return $response !== null && $this->checkRequiredAttr($response, 'tvmazeE')
            ? $this->formatEpisodeInfo($response)
            : false;
    }

    /**
     * @param  list<Episode>  $episodes
     * @return list<array<string, mixed>>
     */
    private function formatEpisodes(array $episodes): array
    {
        $formatted = [];
        foreach ($episodes as $episode) {
            // Specials carry season but no number; addEpisodes() keys those by airdate.
            if ($episode->season !== null) {
                $formatted[] = $this->formatEpisodeInfo($episode);
            }
        }

        return $formatted;
    }

    /**
     * Assigns API show response values to a formatted array for insertion
     * Returns the formatted array.
     *
     * @return array<string, mixed>
     */
    public function formatShowInfo(mixed $show): array
    {
        $this->posterUrl = (string) ($show->originalImage ?? $show->mediumImage ?? '');

        $tvdbId = (int) ($show->externalIDs['thetvdb'] ?? 0);
        $imdbId = '';

        if (! empty($show->externalIDs['imdb'])) {
            preg_match('/tt(?P<imdbid>\d{6,})$/i', (string) $show->externalIDs['imdb'], $imdb);
            $imdbId = (string) ($imdb['imdbid'] ?? '');
        }

        $externalIds = $this->lookupExternalIds($tvdbId, $imdbId);

        return [
            'type' => parent::TYPE_TV,
            'title' => (string) $show->name,
            'summary' => (string) $show->summary,
            'started' => (string) $show->premiered,
            'publisher' => (string) ($show->network ?? ($show->webChannel['name'] ?? '')),
            'country' => (string) $show->country,
            'source' => parent::SOURCE_TVMAZE,
            'imdb' => $imdbId,
            'tvdb' => $tvdbId,
            'tvmaze' => (int) $show->id,
            'trakt' => $externalIds['trakt'],
            'tvrage' => (int) ($show->externalIDs['tvrage'] ?? 0),
            'tmdb' => $externalIds['tmdb'],
            'aliases' => ! empty($show->akas) ? (array) $show->akas : '',
            'localzone' => (string) ($show->network_array['country']['timezone'] ?? ($show->webChannel['country']['timezone'] ?? '')),
            'poster' => $this->posterUrl,
        ];
    }

    /**
     * Assigns API episode response values to a formatted array for insertion
     * Returns the formatted array.
     *
     * @return array<string, mixed>
     */
    public function formatEpisodeInfo(mixed $episode): array
    {
        return [
            'title' => (string) $episode->name,
            'series' => (int) $episode->season,
            'episode' => (int) $episode->number,
            'se_complete' => 'S'.sprintf('%02d', $episode->season).'E'.sprintf('%02d', $episode->number),
            'firstaired' => (string) $episode->airdate,
            'summary' => (string) $episode->summary,
        ];
    }
}

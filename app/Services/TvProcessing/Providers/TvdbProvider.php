<?php

declare(strict_types=1);

namespace App\Services\TvProcessing\Providers;

use App\Exceptions\TvProviderException;
use App\Services\TmdbClient;
use App\Services\TvdbClient;
use Illuminate\Support\Facades\Log;

/**
 * Class TvdbProvider -- functions used to post process releases against TVDB (API v4).
 */
class TvdbProvider extends AbstractTvProvider
{
    public TvdbClient $client;

    public function __construct(?TvdbClient $client = null)
    {
        parent::__construct();
        $this->client = $client ?? app(TvdbClient::class);
    }

    /**
     * @return array<string, mixed>|false
     */
    public function getShowInfo(string $name, ?int $year = null, string $country = ''): array|false
    {
        $name = (string) preg_replace('# \((19|20)\d{2}\)$#', '', $name);

        // A wrong year (an episode title, a revival's year) filters the right show out,
        // so an unconvincing year-filtered search is retried without the year.
        $highest = null;
        if ($year !== null) {
            $highest = $this->bestSearchResult($this->client->searchSeries($name, $year), $name, $year, $country);
        }
        if ($highest === null) {
            $highest = $this->bestSearchResult($this->client->searchSeries($name), $name, $year, $country);
        }

        return $highest !== null ? $this->formatShowInfo($highest) : false;
    }

    /**
     * The highest scoring series of a search response, comparing its name, English
     * translation and aliases; null when nothing scores.
     *
     * @param  list<array<string, mixed>>  $response
     */
    private function bestSearchResult(array $response, string $name, ?int $year, string $country): ?object
    {
        $highestScore = 0.0;
        $highest = null;
        foreach ($response as $row) {
            $show = (object) $row;
            if (! $this->checkRequiredAttr($show, 'tvdbS')) {
                continue;
            }

            $names = [(string) $show->name, $this->englishName($show)];
            foreach (\is_array($show->aliases ?? null) ? $show->aliases : [] as $alias) {
                if (\is_string($alias)) {
                    $names[] = $alias;
                }
            }

            foreach (array_unique(array_filter($names)) as $candidateName) {
                $score = $this->scoreCandidate($name, $year, $candidateName, $show->first_air_time ?? ($show->year ?? null), $country, (string) ($show->country ?? ''));
                if ($score > $highestScore) {
                    $highestScore = $score;
                    $highest = $show;
                }
            }
        }

        return $highest;
    }

    /** The English title of a search result, or '' when TVDB has none. */
    private function englishName(object $show): string
    {
        $translations = \is_array($show->translations ?? null) ? $show->translations : [];

        return trim((string) ($translations['eng'] ?? ''));
    }

    public function resolveSiteId(array $knownIds): int
    {
        $tmdb = app(TmdbClient::class)->throwingOnTransientFailure();
        if ($knownIds['tmdb'] <= 0 || ! $tmdb->isConfigured()) {
            return 0;
        }

        return (int) ($tmdb->getTvExternalIds($knownIds['tmdb'])['tvdb_id'] ?? 0);
    }

    public function fetchAllEpisodes(int $siteId, int $videoId): int
    {
        if ($siteId <= 0 || $videoId <= 0) {
            return 0;
        }

        $episodes = $this->client->seriesEpisodes($siteId, [], 'eng');

        // Shows without an English translation come back empty from the /eng listing.
        if ($episodes === []) {
            $language = (string) ($this->client->seriesExtended($siteId)['originalLanguage'] ?? 'eng');
            if ($language !== '' && $language !== 'eng') {
                $episodes = $this->client->seriesEpisodes($siteId, [], $language);
            }
        }

        return $this->addEpisodes($videoId, $this->formatEpisodes($episodes));
    }

    /**
     * @param  list<array<string, mixed>>  $episodes
     * @return list<array<string, mixed>>
     */
    private function formatEpisodes(array $episodes): array
    {
        $formatted = [];
        foreach ($episodes as $row) {
            $episode = (object) $row;
            if ($this->checkRequiredAttr($episode, 'tvdbE')) {
                $formatted[] = $this->formatEpisodeInfo($episode);
            }
        }

        return $formatted;
    }

    public function supportsAirdateLookup(): bool
    {
        return true;
    }

    public function fetchEpisodesByAirdate(int $siteId, int $videoId, string $airdate): int
    {
        $date = \DateTime::createFromFormat('!Y-m-d', $airdate);
        if ($siteId <= 0 || $videoId <= 0 || $date === false) {
            return 0;
        }

        return $this->addEpisodes($videoId, $this->formatEpisodes(
            $this->client->seriesEpisodes($siteId, ['airDate' => $date->format('Y-m-d')]),
        ));
    }

    /**
     * @return array<string, mixed>|false
     */
    public function fetchEpisode(int $siteId, int $season, int $episode, int $videoId = 0): array|false
    {
        if ($siteId <= 0 || $season < 0 || $episode <= 0) {
            return false;
        }

        foreach ($this->client->seriesEpisodes($siteId, ['season' => $season, 'episodeNumber' => $episode]) as $row) {
            $record = (object) $row;
            if ($this->checkRequiredAttr($record, 'tvdbE') && (int) $record->seasonNumber === $season && (int) $record->number === $episode) {
                return $this->formatEpisodeInfo($record);
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function formatShowInfo(mixed $show): array
    {
        $this->posterUrl = '';
        $tvdbId = (int) $show->tvdb_id;

        // The search result already carries the series poster; only look it up without one.
        if (! empty($show->image_url)) {
            $this->posterUrl = (string) $show->image_url;
        } else {
            $poster = collect($this->postersQuietly($tvdbId))->sortByDesc('score')->first();
            $this->posterUrl = (string) ($poster['image'] ?? '');
        }

        // Store English titles; the original-language name stays findable as an alias.
        $originalName = (string) $show->name;
        $englishName = \is_object($show) ? $this->englishName($show) : '';
        $title = $englishName !== '' ? $englishName : $originalName;
        $aliases = \is_array($show->aliases ?? null) ? $show->aliases : [];
        if ($title !== $originalName) {
            $aliases[] = $originalName;
        }
        $overviews = \is_array($show->overviews ?? null) ? $show->overviews : [];
        $summary = (string) ($overviews['eng'] ?? ($show->overview ?? ''));

        $imdbId = $this->imdbId($show);

        $externalIds = $this->lookupExternalIds($tvdbId, $imdbId);

        return [
            'type' => parent::TYPE_TV,
            'title' => $title,
            'summary' => $summary,
            'started' => (string) ($show->first_air_time ?? ''),
            'publisher' => (string) ($show->network ?? ''),
            'country' => (string) ($show->country ?? ''),
            'poster' => $this->posterUrl,
            'source' => parent::SOURCE_TVDB,
            'imdb' => $imdbId,
            'tvdb' => $tvdbId,
            'trakt' => $externalIds['trakt'],
            'tvrage' => 0,
            'tvmaze' => 0,
            'tmdb' => $externalIds['tmdb'],
            'aliases' => $aliases !== [] ? $aliases : '',
            'localzone' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formatEpisodeInfo(mixed $episode): array
    {
        return [
            'title' => (string) ($episode->name ?? ''),
            'series' => (int) $episode->seasonNumber,
            'episode' => (int) $episode->number,
            'se_complete' => 'S'.sprintf('%02d', $episode->seasonNumber).'E'.sprintf('%02d', $episode->number),
            'firstaired' => (string) ($episode->aired ?? ''),
            'summary' => (string) ($episode->overview ?? ''),
        ];
    }

    /**
     * A series by its TVDB id, shaped like a search result so formatShowInfo() can read it;
     * null when TVDB has none.
     *
     * @throws TvProviderException
     */
    public function showById(int $tvdbId): ?object
    {
        $series = $this->client->seriesExtended($tvdbId);
        if ($series === null) {
            return null;
        }

        $aliases = [];
        foreach (\is_array($series['aliases'] ?? null) ? $series['aliases'] : [] as $alias) {
            $aliasName = \is_array($alias) ? ($alias['name'] ?? null) : $alias;
            if (\is_string($aliasName) && $aliasName !== '') {
                $aliases[] = $aliasName;
            }
        }

        $translations = \is_array($series['translations'] ?? null) ? $series['translations'] : [];

        return (object) [
            'tvdb_id' => (string) ($series['id'] ?? $tvdbId),
            'name' => (string) ($series['name'] ?? ''),
            'overview' => (string) ($series['overview'] ?? ''),
            'first_air_time' => (string) ($series['firstAired'] ?? ''),
            'aliases' => $aliases,
            'image_url' => (string) ($series['image'] ?? ''),
            'country' => (string) ($series['originalCountry'] ?? ''),
            'network' => (string) ($series['originalNetwork']['name'] ?? ''),
            'remote_ids' => \is_array($series['remoteIds'] ?? null) ? $series['remoteIds'] : [],
            'translations' => array_filter(['eng' => self::englishTranslation($translations['nameTranslations'] ?? null, 'name')]),
            'overviews' => array_filter(['eng' => self::englishTranslation($translations['overviewTranslations'] ?? null, 'overview')]),
        ];
    }

    /**
     * The English entry of a TVDB translation list, skipping alternative titles; '' when there is none.
     */
    private static function englishTranslation(mixed $translations, string $field): string
    {
        foreach (\is_array($translations) ? $translations : [] as $translation) {
            if (\is_array($translation) && ($translation['language'] ?? '') === 'eng' && empty($translation['isAlias'])) {
                return trim((string) ($translation[$field] ?? ''));
            }
        }

        return '';
    }

    /** The numeric IMDb id among a series' remote ids, or '' when TVDB lists none. */
    private function imdbId(object $show): string
    {
        foreach (\is_array($show->remote_ids ?? null) ? $show->remote_ids : [] as $remote) {
            if (\is_array($remote) && preg_match('/^tt(?P<imdbid>\d{6,})$/i', (string) ($remote['id'] ?? ''), $imdb) === 1) {
                return $imdb['imdbid'];
            }
        }

        return '';
    }

    /**
     * The posters of a series. Optional enrichment: a failure is logged instead of thrown.
     *
     * @return list<array<string, mixed>>
     */
    private function postersQuietly(int $tvdbId): array
    {
        try {
            return $this->client->seriesPosters($tvdbId);
        } catch (TvProviderException $e) {
            Log::info('TVDB enrichment request failed', ['error' => $e->getMessage()]);

            return [];
        }
    }
}

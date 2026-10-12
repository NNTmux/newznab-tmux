<?php

declare(strict_types=1);

namespace App\Services\TvProcessing\Providers;

use App\Enums\ImageAssetProfile;
use App\Exceptions\TvProviderException;
use App\Models\TvEpisode;
use App\Models\TvInfo;
use App\Models\Video;
use App\Services\ReleaseImageService;
use App\Services\TmdbClient;
use App\Services\TraktService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Class AbstractTvProvider -- abstract extension of BaseVideoProvider
 * Contains functions suitable for re-use in all TV scrapers.
 */
abstract class AbstractTvProvider extends BaseVideoProvider
{
    // Television Sources
    public const SOURCE_NONE = 0;   // No Scrape source

    public const SOURCE_TVDB = 1;   // Scrape source was TVDB

    public const SOURCE_TVMAZE = 2;   // Scrape source was TVMAZE

    public const SOURCE_TMDB = 3;   // Scrape source was TMDB

    public const SOURCE_TRAKT = 4;   // Scrape source was Trakt

    public const SOURCE_IMDB = 5;   // Scrape source was IMDB

    // Anime Sources
    public const SOURCE_ANIDB = 10;   // Scrape source was AniDB

    /** Minimum title similarity (percent) for a search result to be considered. */
    protected const MATCH_PROBABILITY = 75;

    /** Seconds to wait for the per-show episode insert lock. */
    private const EPISODE_LOCK_SECONDS = 120;

    /**
     * @string Path to Save Images
     */
    public string $imgSavePath;

    /**
     * @var list<string> Site ID columns for TV, in the order add() checks them for an existing video
     */
    public array $siteColumns;

    /**
     * URL of the poster for the show last formatted by formatShowInfo().
     */
    public string $posterUrl = '';

    public function __construct()
    {
        parent::__construct();
        $this->imgSavePath = storage_path('covers/tvshows/');
        $this->siteColumns = ['tvdb', 'trakt', 'tvrage', 'tvmaze', 'imdb', 'tmdb'];
    }

    /**
     * Search the provider for a show and return the best match, formatted by formatShowInfo().
     *
     * @param  string  $name  Show title without any year suffix.
     * @param  int|null  $year  Year the show started, when the release name carries one.
     * @param  string  $country  Two-letter country parsed from the release name, or ''.
     * @return array<string, mixed>|false
     *
     * @throws TvProviderException on transient provider failures
     */
    abstract public function getShowInfo(string $name, ?int $year = null, string $country = ''): array|false;

    /**
     * Download every episode of a show and store the new ones. Returns the number stored.
     *
     * @throws TvProviderException on transient provider failures
     */
    abstract public function fetchAllEpisodes(int $siteId, int $videoId): int;

    /**
     * Look up a single episode. Returns formatEpisodeInfo() output, or false when the provider has none.
     *
     * @return array<string, mixed>|false
     *
     * @throws TvProviderException on transient provider failures
     */
    abstract public function fetchEpisode(int $siteId, int $season, int $episode, int $videoId = 0): array|false;

    /**
     * Assigns API show response values to a formatted array for insertion
     * Returns the formatted array.
     *
     * @return array<string, mixed>
     */
    abstract public function formatShowInfo(mixed $show): array;

    /**
     * Assigns API episode response values to a formatted array for insertion
     * Returns the formatted array.
     *
     * @return array<string, mixed>
     */
    abstract public function formatEpisodeInfo(mixed $episode): array;

    /**
     * Whether fetchEpisodesByAirdate() asks the provider for a single date.
     */
    public function supportsAirdateLookup(): bool
    {
        return false;
    }

    /**
     * Download and store the episodes that aired on a date. Returns the number stored.
     *
     * @throws TvProviderException on transient provider failures
     */
    public function fetchEpisodesByAirdate(int $siteId, int $videoId, string $airdate): int
    {
        return 0;
    }

    /**
     * Save the poster at $this->posterUrl for a video.
     */
    public function getPoster(int $videoId): int
    {
        return $this->savePoster($videoId, $this->posterUrl) ? 1 : 0;
    }

    public function savePoster(int $videoId, string $url): bool
    {
        if ($videoId <= 0 || $url === '') {
            return false;
        }

        $saved = (new ReleaseImageService)->saveRemoteImage(
            (string) $videoId,
            $url,
            $this->imgSavePath,
            ImageAssetProfile::Original,
        )->success;

        if ($saved) {
            $this->setCoverFound($videoId);
        }

        return (bool) $saved;
    }

    public function hasCover(int $videoId): bool
    {
        return (int) TvInfo::query()->where('videos_id', $videoId)->value('image') === 1;
    }

    /**
     * Inserts a new video for a TV show, or updates the existing one found by site ID or title.
     *
     * Serialised per title across workers; a lost race falls back to the row the other worker created.
     *
     * @param  array<string, mixed>  $show
     */
    public function add(array $show = []): int
    {
        $lock = Cache::lock('tv:add:'.md5(mb_strtolower((string) ($show['title'] ?? ''))), 30);

        try {
            return $lock->block(10, fn (): int => $this->addOrUpdate($show));
        } catch (LockTimeoutException) {
            return $this->addOrUpdate($show);
        }
    }

    /**
     * @param  array<string, mixed>  $show
     */
    private function addOrUpdate(array $show): int
    {
        $show['country'] = $this->normalizeCountry((string) ($show['country'] ?? ''));
        $show['type'] = (int) ($show['type'] ?? parent::TYPE_TV);

        $videoId = $this->findExistingVideo($show);
        if ($videoId > 0) {
            $this->update($videoId, $show);

            return $videoId;
        }

        try {
            // Eloquent create() so VideoObserver indexes the show.
            $video = Video::create([
                'type' => $show['type'],
                'title' => mb_substr((string) $show['title'], 0, 180),
                'countries_id' => $show['country'],
                'started' => $show['started'] ?? '',
                'source' => (int) ($show['source'] ?? self::SOURCE_NONE),
                'tvdb' => (int) ($show['tvdb'] ?? 0),
                'trakt' => (int) ($show['trakt'] ?? 0),
                'tvrage' => (int) ($show['tvrage'] ?? 0),
                'tvmaze' => (int) ($show['tvmaze'] ?? 0),
                'imdb' => $this->normalizeImdbValue($show['imdb'] ?? '') ?: '0',
                'tmdb' => (int) ($show['tmdb'] ?? 0),
            ]);
        } catch (UniqueConstraintViolationException) {
            // Another worker inserted the same title/started/country between our lookup and insert.
            $videoId = $this->findExistingVideo($show);
            if ($videoId > 0) {
                $this->update($videoId, $show);
            }

            return $videoId;
        }

        $videoId = (int) $video->id;
        TvInfo::query()->insertOrIgnore([
            'videos_id' => $videoId,
            'summary' => $this->normalizeOptionalString($show['summary'] ?? ''),
            'publisher' => mb_substr($this->normalizeOptionalString($show['publisher'] ?? ''), 0, 50),
            'localzone' => $this->normalizeOptionalString($show['localzone'] ?? ''),
        ]);

        if (! empty($show['aliases']) && \is_array($show['aliases'])) {
            $this->addAliases($videoId, $show['aliases']);
        }

        return $videoId;
    }

    /**
     * @param  array<string, mixed>  $show
     */
    private function findExistingVideo(array $show): int
    {
        foreach ($this->siteColumns as $column) {
            $siteId = $column === 'imdb' ? (int) $this->normalizeImdbValue($show['imdb'] ?? '') : (int) ($show[$column] ?? 0);
            if ($siteId > 0) {
                $videoId = $this->getVideoIDFromSiteID($column, $siteId);
                if ($videoId !== false) {
                    return (int) $videoId;
                }
            }
        }

        return (int) Video::query()
            ->where('title', (string) ($show['title'] ?? ''))
            ->where('type', (int) ($show['type'] ?? parent::TYPE_TV))
            ->orderBy('id')
            ->value('id');
    }

    /**
     * Store one episode and return its id, or false when it can't be stored.
     *
     * @param  array<string, mixed>  $episode
     */
    public function addEpisode(int $videoId, array $episode = []): int|false
    {
        if ($videoId <= 0) {
            return false;
        }

        $row = $this->episodeRow($videoId, $episode);
        if ($row === null) {
            return false;
        }

        $existing = $this->getBySeasonEp($videoId, $row['series'], $row['episode'], (string) $row['firstaired']);
        if ($existing !== false) {
            return $existing;
        }

        TvEpisode::query()->insertOrIgnore($row);

        return $this->getBySeasonEp($videoId, $row['series'], $row['episode'], (string) $row['firstaired']);
    }

    /**
     * Store the episodes of a show that aren't stored yet. Returns the number inserted.
     *
     * @param  iterable<array<string, mixed>>  $episodes  formatEpisodeInfo() output
     */
    public function addEpisodes(int $videoId, iterable $episodes): int
    {
        if ($videoId <= 0) {
            return 0;
        }

        $lock = Cache::lock('tv:episodes:'.$videoId, self::EPISODE_LOCK_SECONDS);

        try {
            return $lock->block(self::EPISODE_LOCK_SECONDS, fn (): int => $this->insertNewEpisodes($videoId, $episodes));
        } catch (LockTimeoutException) {
            return $this->insertNewEpisodes($videoId, $episodes);
        }
    }

    /**
     * @param  iterable<array<string, mixed>>  $episodes
     */
    private function insertNewEpisodes(int $videoId, iterable $episodes): int
    {
        $known = [];
        foreach (TvEpisode::query()->where('videos_id', $videoId)->get(['series', 'episode', 'firstaired']) as $existing) {
            $known[$this->episodeKey((int) $existing->series, (int) $existing->episode, (string) $existing->firstaired)] = true;
        }

        $rows = [];
        foreach ($episodes as $episode) {
            $row = $this->episodeRow($videoId, $episode);
            if ($row === null) {
                continue;
            }
            $key = $this->episodeKey($row['series'], $row['episode'], (string) $row['firstaired']);
            if (isset($known[$key])) {
                continue;
            }
            $known[$key] = true;
            $rows[] = $row;
        }

        $inserted = 0;
        foreach (array_chunk($rows, 500) as $chunk) {
            $inserted += TvEpisode::query()->insertOrIgnore($chunk);
        }

        return $inserted;
    }

    /**
     * Numbered episodes are keyed by season/episode; unnumbered specials by their airdate.
     */
    private function episodeKey(int $series, int $episode, string $firstAired): string
    {
        return $episode > 0 ? $series.'x'.$episode : '0x0@'.substr($firstAired, 0, 10);
    }

    /**
     * @param  array<string, mixed>  $episode
     * @return array{videos_id: int, series: int, episode: int, se_complete: string, title: string, firstaired: string|null, summary: string}|null
     */
    private function episodeRow(int $videoId, array $episode): ?array
    {
        $series = max(0, (int) ($episode['series'] ?? 0));
        $number = max(0, (int) ($episode['episode'] ?? 0));
        $firstAired = $this->normalizeAirdate($episode['firstaired'] ?? null);

        // An unnumbered episode without an airdate can't be told apart from any other one.
        if ($number === 0 && $firstAired === null) {
            return null;
        }

        return [
            'videos_id' => $videoId,
            'series' => $series,
            'episode' => $number,
            'se_complete' => mb_substr((string) ($episode['se_complete'] ?? sprintf('S%02dE%02d', $series, $number)), 0, 10),
            'title' => mb_substr($this->normalizeOptionalString($episode['title'] ?? ''), 0, 180),
            'firstaired' => $firstAired,
            'summary' => $this->normalizeOptionalString($episode['summary'] ?? ''),
        ];
    }

    private function normalizeAirdate(mixed $value): ?string
    {
        $value = $this->normalizeOptionalString($value);
        if ($value === '' || str_starts_with($value, '0000')) {
            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? null : date('Y-m-d', $timestamp);
    }

    /**
     * Fill in the video and tv_info fields that are still empty.
     *
     * Goes through Eloquent so VideoObserver keeps the tvshows index in sync.
     *
     * @param  array<string, mixed>  $show
     */
    public function update(int $videoId, array $show = []): void
    {
        /** @var Video|null $video */
        $video = Video::query()->find($videoId);
        if ($video === null) {
            return;
        }

        $country = $this->normalizeCountry((string) ($show['country'] ?? ''));
        if ($country !== '' && trim((string) $video->countries_id) === '') {
            $video->countries_id = $country;
        }

        foreach (['tvdb', 'trakt', 'tvrage', 'tvmaze', 'tmdb'] as $column) {
            $siteId = (int) ($show[$column] ?? 0);
            if ($siteId > 0 && (int) $video->{$column} === 0) {
                $video->{$column} = $siteId;
            }
        }

        $imdb = $this->normalizeImdbValue($show['imdb'] ?? '');
        if ($imdb !== '' && \in_array(trim((string) $video->imdb), ['', '0'], true)) {
            $video->imdb = $imdb;
        }

        if ($video->isDirty()) {
            $video->save();
        }

        /** @var TvInfo|null $info */
        $info = TvInfo::query()->find($videoId);
        $info ??= new TvInfo(['videos_id' => $videoId, 'summary' => '', 'publisher' => '', 'localzone' => '']);

        $fields = [
            'summary' => $this->normalizeOptionalString($show['summary'] ?? ''),
            'publisher' => mb_substr($this->normalizeOptionalString($show['publisher'] ?? ''), 0, 50),
            'localzone' => $this->normalizeOptionalString($show['localzone'] ?? ''),
        ];
        foreach ($fields as $column => $value) {
            if ($value !== '' && trim((string) $info->{$column}) === '') {
                $info->{$column} = $value;
            }
        }

        if (! $info->exists || $info->isDirty()) {
            $info->save();
        }

        if (! empty($show['aliases']) && \is_array($show['aliases'])) {
            $this->addAliases($videoId, $show['aliases']);
        }
    }

    /**
     * Two-letter ISO 3166 code for a code or country name; '' when unknown.
     */
    public function normalizeCountry(string $country): string
    {
        $country = trim($country);
        if ($country === '') {
            return '';
        }

        if (\strlen($country) === 2) {
            $code = strtoupper($country);

            return $code === 'UK' ? 'GB' : $code;
        }

        return countryCode($country);
    }

    protected function normalizeImdbValue(mixed $value): string
    {
        $normalizedValue = $this->normalizeOptionalString($value);

        if ($normalizedValue === '' || $normalizedValue === '0') {
            return '';
        }

        if (preg_match('/^(?:tt)?(?P<imdbid>\d{6,})$/i', $normalizedValue, $matches) === 1) {
            return $matches['imdbid'];
        }

        return $normalizedValue;
    }

    protected function normalizeOptionalString(mixed $value): string
    {
        if (is_string($value) || is_int($value) || is_float($value) || $value instanceof \Stringable) {
            $normalizedValue = trim((string) $value);

            return $normalizedValue === "''" ? '' : $normalizedValue;
        }

        return '';
    }

    /**
     * @throws \Throwable
     */
    public function delete(int $id): void
    {
        DB::transaction(function () use ($id) {
            DB::delete(
                sprintf(
                    '
				DELETE v, tvi, tve, va
				FROM videos v
				LEFT JOIN tv_info tvi ON v.id = tvi.videos_id
				LEFT JOIN tv_episodes tve ON v.id = tve.videos_id
				LEFT JOIN videos_aliases va ON v.id = va.videos_id
				WHERE v.id = %d',
                    $id
                )
            );
        }, 3);
    }

    public function setCoverFound(int $videoId): void
    {
        TvInfo::query()->where('videos_id', $videoId)->update(['image' => 1]);
    }

    /**
     * @return Video|false|Builder|Model
     */
    public function getSiteByID(string $column, int $id): Model|bool|Builder|Video|int|string // @phpstan-ignore missingType.generics
    {
        $return = false;
        $video = Video::query()->where('id', $id)->first([$column]);
        if ($column === '*') {
            $return = $video;
        } elseif ($video !== null) {
            $value = $video[$column];
            $return = ($value !== null && $value !== '') ? $value : false;
        }

        return $return;
    }

    /**
     * Retrieves the Episode ID using the Video ID and either the season/episode
     * numbers (season 0 holds specials) or the airdate.
     *
     * Returns the Episode ID or false if not found
     */
    public function getBySeasonEp(int|string $id, int|string $series, int|string $episode, string $airdate = ''): int|false
    {
        $videoId = (int) $id;
        $season = is_numeric($series) ? (int) $series : -1;
        $number = is_numeric($episode) ? (int) $episode : 0;

        if ($videoId <= 0) {
            return false;
        }

        $query = TvEpisode::query()->where('videos_id', $videoId);

        if ($season >= 0 && $number > 0) {
            $query->where('series', $season)->where('episode', $number);
        } elseif ($airdate !== '') {
            $timestamp = strtotime($airdate);
            if ($timestamp === false) {
                return false;
            }
            $query->whereDate('firstaired', date('Y-m-d', $timestamp));
        } else {
            return false;
        }

        $episodeId = $query->orderBy('id')->value('id');

        return $episodeId !== null ? (int) $episodeId : false;
    }

    /**
     * Returns (true) if episodes for a given Video ID exist or don't (false).
     */
    public function countEpsByVideoID(int $videoId): bool
    {
        return TvEpisode::query()->where('videos_id', $videoId)->exists();
    }

    /**
     * @return array<string, mixed>|false
     */
    public function parseInfo(string $relname): bool|array
    {
        $showInfo['name'] = $this->parseName($relname);

        if (! empty($showInfo['name'])) {
            // Retrieve the country from the cleaned name
            $showInfo['country'] = $this->parseCountry($showInfo['name']);

            // Clean show name.
            $showInfo['cleanname'] = preg_replace('/ - \d+$/i', '', $this->cleanName($showInfo['name']));
            $showInfo['cleanname'] = $this->normalizeShowTitle($showInfo['cleanname']);

            // Get the Season/Episode/Airdate
            $showInfo += $this->parseSeasonEp($relname);

            if (isset($showInfo['season'], $showInfo['episode'])) {
                if (! isset($showInfo['airdate'])) {
                    if (preg_match('/[^a-z0-9](?P<year>(19|20)(\d{2}))[^a-z0-9]/i', $relname, $yearMatch)) {
                        $showInfo['cleanname'] .= ' ('.$yearMatch['year'].')';
                        $showInfo['year'] = (int) $yearMatch['year'];
                    }
                    if (\is_array($showInfo['episode'])) {
                        $showInfo['episode'] = $showInfo['episode'][0];
                    }
                    $showInfo['airdate'] = '';
                }

                return $showInfo;
            }
        }

        return false;
    }

    /**
     * Parses the release searchname and returns a show title.
     */
    private function parseName(string $relname): string
    {
        $showName = '';

        $following = '[^a-z0-9]([(|\[]\w+[)|\]]\s)*?(\d\d-\d\d|\d{1,3}x\d{2,3}|\(?(19|20)\d{2}\)?|(480|720|1080|2160)[ip]|AAC2?|BD-?Rip|Blu-?Ray|D0?\d|DD5|DiVX|DLMux|DTS|DVD(-?Rip)?|E\d{2,3}|[HX][\-_. ]?26[45]|ITA(-ENG)?|HEVC|[HPS]DTV|PROPER|REPACK|Season|Episode|S\d+[^a-z0-9]?((E\d+)[abr]?)*|WEB[\-_. ]?(DL|Rip)|XViD)[^a-z0-9]?';

        // Handle fansub/release group prefixes like [SubsPlease], [Erai-raws], [ASW], etc.
        $cleanRelname = preg_replace('/^\[[^]]+][_\s]*/i', '', $relname);

        if (preg_match('/^([^a-z0-9]{2,}|(sample|proof|repost)-)(?P<name>[\w .\-!&]+?)'.$following.'/i', $cleanRelname, $hits)) {
            $showName = $hits['name'];
        } elseif (preg_match('/^(?P<name>[\w!&][\s\w\'._\-!&]*?)'.$following.'/i', $cleanRelname, $hits)) {
            $showName = $hits['name'];
        }

        // If still no match, try extracting name before common delimiters
        if (empty($showName)) {
            // Try to extract name before - SxxExx pattern
            if (preg_match('/^(?P<name>.+?)[_\s]*[-_\s]+S\d{1,2}E\d{1,3}/i', $cleanRelname, $hits)) {
                $showName = $hits['name'];
            }
            // Try to extract name before standalone episode number like "- 06 [" or "_09_["
            elseif (preg_match('/^(?P<name>.+?)[_\s]+[-_]+[_\s]*\d{1,3}[_\s]*[\[(]/i', $cleanRelname, $hits)) {
                $showName = $hits['name'];
            }
            // Handle anime-style "Show_Name_-_Episode_Title_-_XX_[quality]" format (extract before first _-_)
            elseif (preg_match('/^(?P<name>.+?)_-_.+?_-_\d{1,3}_[\[(]/i', $cleanRelname, $hits)) {
                $showName = $hits['name'];
            }
            // Simpler pattern: extract everything before "_-_" for anime releases
            elseif (preg_match('/^(?P<name>[^_]+(?:_[^_-]+)*)_-_/i', $cleanRelname, $hits)) {
                $showName = $hits['name'];
            }
        }

        $showName = preg_replace('/'.$following.'/i', ' ', $showName);
        $showName = preg_replace('/^\d{6}/', '', $showName);
        $showName = $this->convertAcronyms($showName);
        $showName = preg_replace('/\(.*?\)|[._]/i', ' ', $showName);
        $showName = trim(preg_replace('/\s{2,}/', ' ', $showName));

        return $showName;
    }

    /**
     * Convert acronyms with dots to condensed form.
     */
    private function convertAcronyms(string $str): string
    {
        return preg_replace_callback(
            '/\b((?:[A-Za-z]\.){2,}[A-Za-z]?\.?)\b/',
            function ($matches) {
                return str_replace('.', '', $matches[1]);
            },
            $str
        );
    }

    /**
     * Normalize well-known daily/talk show titles to their canonical names.
     * Also includes German-to-English mappings for popular shows.
     */
    protected function normalizeShowTitle(string $cleanName): string
    {
        $normalized = strtolower(trim($cleanName));
        $aliases = [
            // English show aliases
            'grits' => 'Girls Raised in the South',
            'shield' => 'Agents of S.H.I.E.L.D.',
            'stephen colbert' => 'The Late Show with Stephen Colbert',
            'late show with stephen colbert' => 'The Late Show with Stephen Colbert',
            'late show stephen colbert' => 'The Late Show with Stephen Colbert',
            'the late show stephen colbert' => 'The Late Show with Stephen Colbert',
            'colbert' => 'The Late Show with Stephen Colbert',
            'daily show' => 'The Daily Show',
            'the daily show' => 'The Daily Show',
            'daily show with jon stewart' => 'The Daily Show with Jon Stewart',
            'the daily show with jon stewart' => 'The Daily Show with Jon Stewart',
            'daily show with trevor noah' => 'The Daily Show with Trevor Noah',
            'the daily show with trevor noah' => 'The Daily Show with Trevor Noah',
            'seth meyers' => 'Late Night with Seth Meyers',
            'late night with seth meyers' => 'Late Night with Seth Meyers',
            'late night seth meyers' => 'Late Night with Seth Meyers',
            'jimmy kimmel' => 'Jimmy Kimmel Live!',
            'jimmy kimmel live' => 'Jimmy Kimmel Live!',
            'the late late show with james corden' => 'The Late Late Show with James Corden',
            'late late show with james corden' => 'The Late Late Show with James Corden',
            'late show with james corden' => 'The Late Late Show with James Corden',
            'james corden' => 'The Late Late Show with James Corden',
            'late late show james corden' => 'The Late Late Show with James Corden',
            'jimmy fallon' => 'The Tonight Show Starring Jimmy Fallon',
            'the tonight show starring jimmy fallon' => 'The Tonight Show Starring Jimmy Fallon',
            'tonight show with jimmy fallon' => 'The Tonight Show Starring Jimmy Fallon',
            'tonight show jimmy fallon' => 'The Tonight Show Starring Jimmy Fallon',

            // German title to English title mappings
            'oak island fluch und legende' => 'The Curse of Oak Island',
            'oak island - fluch und legende' => 'The Curse of Oak Island',
            'tierische freundschaften' => 'Animal Odd Couples',
            'zwischen meer und maloche' => 'Zwischen Meer und Maloche',
            'die schatzsucher von oak island' => 'The Curse of Oak Island',
            'game of thrones das lied von eis und feuer' => 'Game of Thrones',
            'die simpsons' => 'The Simpsons',
            'die wilden siebziger' => 'That 70s Show',
            'how i met your mother' => 'How I Met Your Mother',
            'scrubs die anfanger' => 'Scrubs',
            'scrubs die anfaenger' => 'Scrubs',
            'der prinz von bel-air' => 'The Fresh Prince of Bel-Air',
            'prinz von bel-air' => 'The Fresh Prince of Bel-Air',
            'der prinz von bel air' => 'The Fresh Prince of Bel-Air',
            'alarm fur cobra 11' => 'Alarm for Cobra 11',
            'alarm fuer cobra 11' => 'Alarm for Cobra 11',
            'soko' => 'SOKO',
            'der bergdoktor' => 'Der Bergdoktor',
            'das boot' => 'Das Boot',
            'babylon berlin' => 'Babylon Berlin',
            'dark' => 'Dark',
            'tatort' => 'Tatort',
            'polizeiruf 110' => 'Polizeiruf 110',
        ];

        if (isset($aliases[$normalized])) {
            return $aliases[$normalized];
        }

        return $cleanName;
    }

    /**
     * Parses the release searchname for the season/episode/airdate information.
     *
     * @return array<string, mixed>
     */
    private function parseSeasonEp(string $relname): array
    {
        $episodeArr = [];

        // S01E01-E02 and S01E01-02
        if (preg_match('/^(.*?)[^a-z0-9]s(\d{1,2})[^a-z0-9]?e(\d{1,3})[e-](\d{1,3})[^a-z0-9]?/i', $relname, $hits)) {
            $episodeArr['season'] = (int) $hits[2];
            $episodeArr['episode'] = [(int) $hits[3], (int) $hits[4]];
        }
        // S01E0102 and S01E01E02
        elseif (preg_match('/^(.*?)[^a-z0-9]s(\d{2})[^a-z0-9]?e(\d{2})e?(\d{2})[^a-z0-9]?/i', $relname, $hits)) {
            $episodeArr['season'] = (int) $hits[2];
            $episodeArr['episode'] = (int) $hits[3];
        }
        // S01E01 and S01.E01
        elseif (preg_match('/^(.*?)[^a-z0-9]s(\d{1,2})[^a-z0-9]?e(\d{1,3})[abr]?[^a-z0-9]?/i', $relname, $hits)) {
            $episodeArr['season'] = (int) $hits[2];
            $episodeArr['episode'] = (int) $hits[3];
        }
        // S01D1 and S1D1 (checked before plain S01, which would otherwise swallow it)
        elseif (preg_match('/^(.*?)[^a-z0-9]s(\d{1,2})[^a-z0-9]?d\d[^a-z0-9]?/i', $relname, $hits)) {
            $episodeArr['season'] = (int) $hits[2];
            $episodeArr['episode'] = 'all';
        }
        // S01
        elseif (preg_match('/^(.*?)[^a-z0-9]s(\d{1,2})[^a-z0-9]?/i', $relname, $hits)) {
            $episodeArr['season'] = (int) $hits[2];
            $episodeArr['episode'] = 'all';
        }
        // 1x01
        elseif (preg_match('/^(.*?)[^a-z0-9](\d{1,2})x(\d{1,3})[^a-z0-9]?/i', $relname, $hits)) {
            $episodeArr['season'] = (int) $hits[2];
            $episodeArr['episode'] = (int) $hits[3];
        }
        // 2009.01.01 and 2009-01-01
        elseif (preg_match('/^(.*?)[^a-z0-9](?P<year>(19|20)\d{2})[.\/-](?P<month>\d{2})[.\/-](?P<day>\d{2})[^a-z0-9]?/i', $relname, $hits)) {
            $episodeArr['season'] = 0;
            $episodeArr['episode'] = 0;
            $episodeArr['airdate'] = $this->formatAirdate((int) $hits['year'], (int) $hits['month'], (int) $hits['day']);
        }
        // 01.01.2009 (dotted is day-first, slashed/dashed is month-first unless a value rules it out)
        elseif (preg_match('/^(.*?)[^a-z0-9](?P<first>\d{2})(?P<sep>[.\/-])(?P<second>\d{2})(?P=sep)(?P<year>(19|20)\d{2})[^a-z0-9]?/i', $relname, $hits)) {
            $episodeArr['season'] = 0;
            $episodeArr['episode'] = 0;
            $episodeArr['airdate'] = $this->dayMonthAirdate((int) $hits['first'], (int) $hits['second'], (int) $hits['year'], $hits['sep']);
        }
        // 01.01.09
        elseif (preg_match('/^(.*?)[^a-z0-9](?P<first>\d{2})(?P<sep>[.\/-])(?P<second>\d{2})(?P=sep)(?P<year>\d{2})[^a-z0-9]?/i', $relname, $hits)) {
            $twoDigitYear = (int) $hits['year'];
            $year = $twoDigitYear <= ((int) date('y') + 1) ? 2000 + $twoDigitYear : 1900 + $twoDigitYear;
            $episodeArr['season'] = 0;
            $episodeArr['episode'] = 0;
            $episodeArr['airdate'] = $this->dayMonthAirdate((int) $hits['first'], (int) $hits['second'], $year, $hits['sep']);
        }
        // 2009.E01
        elseif (preg_match('/^(.*?)[^a-z0-9](20\d{2})[^a-z0-9]E(\d{1,3})(?!\d)/i', $relname, $hits)) {
            $episodeArr['season'] = (int) $hits[2];
            $episodeArr['episode'] = (int) $hits[3];
        }
        // 2009.Part1
        elseif (preg_match('/^(.*?)[^a-z0-9](19|20)(\d{2})[^a-z0-9]Part(\d{1,2})[^a-z0-9]?/i', $relname, $hits)) {
            $episodeArr['season'] = (int) ($hits[2].$hits[3]);
            $episodeArr['episode'] = (int) $hits[4];
        }
        // Part1/Pt1
        elseif (preg_match('/^(.*?)[^a-z0-9](?:Part|Pt)[^a-z0-9](\d{1,2})[^a-z0-9]?/i', $relname, $hits)) {
            $episodeArr['season'] = 1;
            $episodeArr['episode'] = (int) $hits[2];
        }
        // Band.Of.Brothers.EP06.Bastogne.DVDRiP.XviD-DEiTY
        elseif (preg_match('/^(.*?)[^a-z0-9]EP?[^a-z0-9]?(\d{1,3})/i', $relname, $hits)) {
            $episodeArr['season'] = 1;
            $episodeArr['episode'] = (int) $hits[2];
        }
        // Anime style: [Fansub]_Show_Name_-_06_[quality] or Show_-_Episode_Title_-_06_[
        elseif (preg_match('/[_\s][-_][_\s](\d{1,3})[_\s]*[\[(]/i', $relname, $hits)) {
            $episodeArr['season'] = 1;
            $episodeArr['episode'] = (int) $hits[1];
        }
        // Season.1
        elseif (preg_match('/^(.*?)[^a-z0-9]Seasons?[^a-z0-9]?(\d{1,2})/i', $relname, $hits)) {
            $episodeArr['season'] = (int) $hits[2];
            $episodeArr['episode'] = 'all';
        }

        return $episodeArr;
    }

    /**
     * Resolve a two-part date whose day/month order is ambiguous.
     *
     * A value over 12 decides the order; otherwise dotted dates are read day-first
     * (European style) and slashed or dashed dates month-first (US style).
     */
    private function dayMonthAirdate(int $first, int $second, int $year, string $separator): string
    {
        if ($first > 12) {
            [$day, $month] = [$first, $second];
        } elseif ($second > 12) {
            [$month, $day] = [$first, $second];
        } elseif ($separator === '.') {
            [$day, $month] = [$first, $second];
        } else {
            [$month, $day] = [$first, $second];
        }

        return $this->formatAirdate($year, $month, $day);
    }

    private function formatAirdate(int $year, int $month, int $day): string
    {
        return checkdate($month, $day, $year) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : '';
    }

    /**
     * Parses the cleaned release name to determine if it has a country appended.
     */
    private function parseCountry(string $showName): string
    {
        if (preg_match('/[^a-z0-9](US|UK|AU|NZ|CA|NL|Canada|Australia|America|United[^a-z0-9]States|United[^a-z0-9]Kingdom)/i', $showName, $countryMatch)) {
            $currentCountry = strtolower($countryMatch[1]);
            if ($currentCountry === 'canada') {
                $country = 'CA';
            } elseif ($currentCountry === 'australia') {
                $country = 'AU';
            } elseif ($currentCountry === 'america' || $currentCountry === 'united states') {
                $country = 'US';
            } elseif ($currentCountry === 'united kingdom') {
                $country = 'UK';
            } else {
                $country = strtoupper($countryMatch[1]);
            }
        } else {
            $country = '';
        }

        return $country;
    }

    /**
     * Supplementary to parseInfo
     * Cleans a derived local 'showname' for better matching probability
     */
    public function cleanName(string $str): string
    {
        $str = str_replace(['.', '_'], ' ', $str);

        $str = str_replace(['à', 'á', 'â', 'ã', 'ä', 'æ', 'À', 'Á', 'Â', 'Ã', 'Ä'], 'a', $str);
        $str = str_replace(['ç', 'Ç'], 'c', $str);
        $str = str_replace(['Σ', 'è', 'é', 'ê', 'ë', 'È', 'É', 'Ê', 'Ë'], 'e', $str);
        $str = str_replace(['ì', 'í', 'î', 'ï', 'Ì', 'Í', 'Î', 'Ï'], 'i', $str);
        $str = str_replace(['ò', 'ó', 'ô', 'õ', 'ö', 'Ò', 'Ó', 'Ô', 'Õ', 'Ö'], 'o', $str);
        $str = str_replace(['ù', 'ú', 'û', 'ü', 'ū', 'Ú', 'Û', 'Ü', 'Ū'], 'u', $str);
        $str = str_replace('ß', 'ss', $str);

        $str = str_replace('&', 'and', $str);
        $str = preg_replace('/^(history|discovery) channel/i', '', $str);
        $str = str_replace(["'", ':', '!', '"', '#', '*', "'", ',', '(', ')', '?'], '', $str);
        $str = str_replace('$', 's', $str);

        // Remove language tags that are commonly found in release names
        $str = preg_replace('/\b(German|French|Spanish|Italian|Dutch|Russian|Japanese|Korean|Chinese|Portuguese|Swedish|Norwegian|Danish|Finnish|Polish|Czech|Hungarian|Romanian|Greek|Turkish|Arabic|Hebrew|Hindi|Thai|Vietnamese|Indonesian|Malay|Tagalog|DL|Dubbed|Subbed|MULTI|MULTi)\b/i', '', $str);

        // Remove common subtitle/episode indicators that aren't part of the show title
        $str = preg_replace('/\s+-\s+.*(wo andere|where others|Arbeiten|Working).*$/i', '', $str);

        // Clean up trailing hyphens, episode numbers, and other artifacts
        $str = preg_replace('/\s*-\s*$/', '', $str);  // Trailing hyphen
        $str = preg_replace('/\s+\d{1,3}\s*$/', '', $str);  // Trailing episode number
        $str = preg_replace('/\s+-$/', '', $str);  // Another trailing hyphen cleanup

        $str = preg_replace('/\s{2,}/', ' ', $str);

        $str = trim($str, '"-');  // Trim quotes and hyphens from both ends

        return trim($str);
    }

    /**
     * Simple function that compares two strings of text
     */
    public function checkMatch(mixed $ourName, mixed $scrapeName, mixed $probability): float
    {
        $normalizedOurName = $this->normalizeComparableValue($ourName);
        $normalizedScrapeName = $this->normalizeComparableValue($scrapeName);

        if ($normalizedOurName === null || $normalizedScrapeName === null) {
            return 0.0;
        }

        $requiredProbability = is_numeric($probability) ? (float) $probability : 0.0;
        $matchpct = 0.0;

        similar_text($normalizedOurName, $normalizedScrapeName, $matchpct);

        if ($matchpct >= $requiredProbability) {
            return $matchpct;
        }

        return 0.0;
    }

    /**
     * Score a search result against the parsed release; 0 means "not a candidate".
     *
     * Title similarity (exact match = 100) plus a bonus when the start year agrees. A year
     * more than one off rejects a fuzzy match and demotes an exact one, so "Doctor Who 2005"
     * prefers the 2005 series over the 1963 one. A matching country breaks remaining ties.
     */
    public function scoreCandidate(
        string $ourName,
        ?int $ourYear,
        string $candidateTitle,
        mixed $candidateStarted = null,
        string $ourCountry = '',
        string $candidateCountry = '',
    ): float {
        $candidateYear = $this->yearOf($candidateStarted);
        if (preg_match('/^(?P<title>.+?)\s*\((?P<year>(19|20)\d{2})\)$/', trim($candidateTitle), $titleYear) === 1) {
            $candidateTitle = $titleYear['title'];
            $candidateYear ??= (int) $titleYear['year'];
        }

        $ours = mb_strtolower(trim($ourName));
        $theirs = mb_strtolower(trim($candidateTitle));
        if ($ours === '' || $theirs === '') {
            return 0.0;
        }

        $exact = $ours === $theirs;
        $score = $exact ? 100.0 : $this->checkMatch($theirs, $ours, self::MATCH_PROBABILITY);
        if ($score <= 0.0) {
            return 0.0;
        }

        if ($ourYear !== null && $candidateYear !== null) {
            if (abs($ourYear - $candidateYear) <= 1) {
                $score += 10.0;
            } elseif (! $exact) {
                return 0.0;
            } else {
                $score -= 20.0;
            }
        }

        if ($ourCountry !== '' && $candidateCountry !== ''
            && $this->normalizeCountryCode($ourCountry) === $this->normalizeCountryCode($candidateCountry)) {
            $score += 2.0;
        }

        return $score;
    }

    private function yearOf(mixed $date): ?int
    {
        if (is_int($date)) {
            return $date >= 1900 ? $date : null;
        }

        if (! is_string($date) || preg_match('/^(?P<year>(19|20)\d{2})/', trim($date), $match) !== 1) {
            return null;
        }

        return (int) $match['year'];
    }

    private function normalizeCountryCode(string $country): string
    {
        $code = strtoupper(trim($country));

        return $code === 'UK' ? 'GB' : $code;
    }

    protected function normalizeComparableValue(mixed $value): ?string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if ($value instanceof \Stringable) {
            return (string) $value;
        }

        return null;
    }

    /**
     * Checks that an API response carries the fields that identify a show or episode.
     *
     * Descriptive fields (overview, summary, airdate, country) are optional: new
     * episodes and web-only shows often don't have them yet.
     */
    public function checkRequiredAttr(mixed $array, string $type): bool
    {
        $required = match ($type) {
            'tvdbS' => ['tvdb_id', 'name'],
            'tvdbE' => ['seasonNumber', 'number'],
            'tvmazeS' => ['id', 'name'],
            'tvmazeE' => ['season', 'number'],
            'tmdbS' => ['id', 'name'],
            'tmdbE' => ['season_number', 'episode_number'],
            'traktS' => ['title', 'ids'],
            'traktE' => ['season', 'number'],
            default => null,
        };

        if ($required === null) {
            return false;
        }

        foreach ($required as $req) {
            $value = \is_array($array) ? ($array[$req] ?? null) : (\is_object($array) ? ($array->$req ?? null) : null);
            if ($value === null || $value === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Look up TMDB and Trakt IDs for a show from its TVDB and IMDB IDs.
     *
     * Optional enrichment: failures are swallowed so they never block a match.
     *
     * @param  int|string  $imdbId  IMDB ID (numeric, without 'tt' prefix)
     * @return array{tmdb: int, trakt: int}
     */
    protected function lookupExternalIds(int $tvdbId, int|string $imdbId): array
    {
        $result = ['tmdb' => 0, 'trakt' => 0];

        try {
            $tmdbClient = app(TmdbClient::class);
            if ($tmdbClient->isConfigured()) {
                if ($tvdbId > 0) {
                    $result['tmdb'] = (int) ($tmdbClient->lookupTvShowIds($tvdbId, 'tvdb')['tmdb'] ?? 0);
                }
                if ($result['tmdb'] === 0 && ! empty($imdbId) && imdb_id_is_valid($imdbId)) {
                    $result['tmdb'] = (int) ($tmdbClient->lookupTvShowIds($imdbId, 'imdb')['tmdb'] ?? 0);
                }
            }

            $traktService = app(TraktService::class);
            if ($traktService->isConfigured()) {
                $traktIds = null;
                if ($tvdbId > 0) {
                    $traktIds = $traktService->lookupShowIds($tvdbId, 'tvdb');
                }
                if (empty($traktIds['trakt']) && ! empty($imdbId) && imdb_id_is_valid($imdbId)) {
                    $traktIds = $traktService->lookupShowIds($imdbId, 'imdb');
                }
                if (! empty($traktIds['trakt'])) {
                    $result['trakt'] = (int) $traktIds['trakt'];
                    if ($result['tmdb'] === 0 && ! empty($traktIds['tmdb'])) {
                        $result['tmdb'] = (int) $traktIds['tmdb'];
                    }
                }
            }
        } catch (\Throwable) {
            // External ID lookup is optional enrichment.
        }

        return $result;
    }

    /**
     * Get all external IDs for a video from the database.
     *
     * @return array{trakt: int, tmdb: int, tvdb: int, imdb: string}
     */
    protected function getAllSiteIdsFromVideoID(int $videoId): array
    {
        $result = Video::query()
            ->where('id', $videoId)
            ->first(['trakt', 'tmdb', 'tvdb', 'imdb']);

        if ($result === null) {
            return ['trakt' => 0, 'tmdb' => 0, 'tvdb' => 0, 'imdb' => ''];
        }

        return [
            'trakt' => (int) ($result->trakt ?? 0),
            'tmdb' => (int) ($result->tmdb ?? 0),
            'tvdb' => (int) ($result->tvdb ?? 0),
            'imdb' => (string) ($result->imdb ?? ''),
        ];
    }
}

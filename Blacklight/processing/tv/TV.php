<?php

namespace Blacklight\processing\tv;

use App\Models\Category;
use App\Models\Release;
use App\Models\Settings;
use App\Models\TvEpisode;
use App\Models\TvInfo;
use App\Models\Video;
use Blacklight\ColorCLI;
use Blacklight\processing\Videos;
use Blacklight\utility\Country;
use Illuminate\Support\Facades\DB;

/**
 * Class TV -- abstract extension of Videos
 * Contains functions suitable for re-use in all TV scrapers.
 */
abstract class TV extends Videos
{
    // Television Sources
    protected const SOURCE_NONE = 0;   // No Scrape source

    protected const SOURCE_TVDB = 1;   // Scrape source was TVDB

    protected const SOURCE_TVMAZE = 2;   // Scrape source was TVMAZE

    protected const SOURCE_TMDB = 3;   // Scrape source was TMDB

    protected const SOURCE_TRAKT = 4;   // Scrape source was Trakt

    protected const SOURCE_IMDB = 5;   // Scrape source was IMDB

    // Anime Sources
    protected const SOURCE_ANIDB = 10;   // Scrape source was AniDB

    // Processing signifiers
    protected const PROCESS_TVDB = 0;   // Process TVDB First

    protected const PROCESS_TVMAZE = -1;   // Process TVMaze Second

    protected const PROCESS_TMDB = -2;   // Process TMDB Third

    protected const PROCESS_TRAKT = -3;   // Process Trakt Fourth

    protected const PROCESS_IMDB = -4;   // Process IMDB Fifth

    protected const NO_MATCH_FOUND = -6;   // Failed All Methods

    protected const FAILED_PARSE = -100; // Failed Parsing

    public int $tvqty;

    /**
     * @string Path to Save Images
     */
    public string $imgSavePath;

    /**
     * @var array Site ID columns for TV
     */
    public array $siteColumns;

    /**
     * @var string The TV categories_id lookup SQL language
     */
    public string $catWhere;

    protected ColorCLI $colorCli;

    /**
     * TV constructor.
     *
     *
     * @throws \Exception
     */
    public function __construct()
    {
        parent::__construct();
        $this->colorCli = new ColorCLI();
        $this->catWhere = 'categories_id BETWEEN '.Category::TV_ROOT.' AND '.Category::TV_OTHER.' AND categories_id != '.Category::TV_ANIME;
        $this->tvqty = Settings::settingValue('..maxrageprocessed') !== '' ? (int) Settings::settingValue('..maxrageprocessed') : 75;
        $this->imgSavePath = storage_path('covers/tvshows/');
        $this->siteColumns = ['tvdb', 'trakt', 'tvrage', 'tvmaze', 'imdb', 'tmdb'];
    }

    /**
     * Retrieve banner image from site using its API.
     */
    abstract protected function getBanner(int $videoID, int $siteId): mixed;

    /**
     * Retrieve info of TV episode from site using its API.
     *
     * @return array|false False on failure, an array of information fields otherwise.
     */
    abstract protected function getEpisodeInfo(int $siteId, int $series, int $episode): array|bool;

    /**
     * Retrieve poster image for TV episode from site using its API.
     *
     * @param  int  $videoId  ID from videos table.
     */
    abstract protected function getPoster(int $videoId): int;

    /**
     * Retrieve info of TV programme from site using it's API.
     *
     * @param  string  $name  Title of programme to look up. Usually a cleaned up version from releases table.
     * @return array|false False on failure, an array of information fields otherwise.
     */
    abstract protected function getShowInfo(string $name): bool|array;

    /**
     * Assigns API show response values to a formatted array for insertion
     * Returns the formatted array.
     */
    abstract protected function formatShowInfo($show): array;

    /**
     * Assigns API episode response values to a formatted array for insertion
     * Returns the formatted array.
     */
    abstract protected function formatEpisodeInfo($episode): array;

    /**
     * @return \App\Models\Release[]|\Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection|\Illuminate\Database\Query\Builder[]|\Illuminate\Support\Collection|int
     */
    public function getTvReleases(string $groupID = '', string $guidChar = '', int $lookupSetting = 1, int $status = 0): array|\Illuminate\Database\Eloquent\Collection|int|\Illuminate\Support\Collection
    {
        $ret = 0;
        if ($lookupSetting === 0) {
            return $ret;
        }

        $qry = Release::query()
            ->where(['nzbstatus' => 1, 'videos_id' => 0, 'tv_episodes_id' => $status])
            ->where('size', '>', 1048576)
            ->whereBetween('categories_id', [Category::TV_ROOT, Category::TV_OTHER])
            ->where('categories_id', '<>', Category::TV_ANIME)
            ->orderByDesc('postdate')
            ->limit($this->tvqty);
        if ($groupID !== '') {
            $qry->where('groups_id', $groupID);
        }
        if ($guidChar !== '') {
            $qry->where('leftguid', $guidChar);
        }
        if ($lookupSetting === 2) {
            $qry->where('isrenamed', '=', 1);
        }

        return $qry->get();
    }

    public function setVideoIdFound(int $videoId, int $releaseId, int $episodeId): void
    {
        Release::query()
            ->where('id', $releaseId)
            ->update(['videos_id' => $videoId, 'tv_episodes_id' => $episodeId]);
    }

    /**
     * Updates the release tv_episodes_id status when scraper match is not found.
     */
    public function setVideoNotFound($status, $Id): void
    {
        Release::query()
            ->where('id', $Id)
            ->update(['tv_episodes_id' => $status]);
    }

    /**
     * Inserts a new video ID into the database for TV shows
     * If a duplicate is found it is handle by calling update instead.
     */
    public function add(array $show = []): int
    {
        $videoId = false;

        // Check if the country is not a proper code and retrieve if not
        if ($show['country'] !== '' && \strlen($show['country']) > 2) {
            $show['country'] = Country::countryCode($show['country']);
        }

        // Check if video already exists based on site ID info
        // if that fails be sure we're not inserting duplicates by checking the title
        foreach ($this->siteColumns as $column) {
            if ((int) $show[$column] > 0) {
                $videoId = $this->getVideoIDFromSiteID($column, $show[$column]);
            }
            if ($videoId !== false) {
                break;
            }
        }

        if ($videoId === false) {
            $title = Video::query()->where('title', $show['title'])->first(['title']);
            if ($title === null) {
                // Insert the Show
                $videoId = Video::query()->insertGetId([
                    'type' => $show['type'],
                    'title' => $show['title'],
                    'countries_id' => $show['country'] ?? '',
                    'started' => $show['started'],
                    'source' => $show['source'],
                    'tvdb' => $show['tvdb'],
                    'trakt' => $show['trakt'],
                    'tvrage' => $show['tvrage'],
                    'tvmaze' => $show['tvmaze'],
                    'imdb' => $show['imdb'],
                    'tmdb' => $show['tmdb'],
                ]);
                // Insert the supplementary show info
                TvInfo::query()->insert([
                    'videos_id' => $videoId,
                    'summary' => $show['summary'],
                    'publisher' => $show['publisher'],
                    'localzone' => $show['localzone'],
                ]);
                // If we have AKAs\aliases, insert those as well
                if (! empty($show['aliases'])) {
                    $this->addAliases($videoId, $show['aliases']);
                }
            }
        } else {
            // If a local match was found, just update missing video info
            $this->update($videoId, $show);
        }

        return $videoId;
    }

    public function addEpisode(int $videoId, array $episode = []): bool|int
    {
        $episodeId = $this->getBySeasonEp($videoId, $episode['series'], $episode['episode'], $episode['firstaired']);

        if ($episodeId === false) {
            $episodeId = TvEpisode::query()->insert(
                [
                    'videos_id' => $videoId,
                    'series' => $episode['series'],
                    'episode' => $episode['episode'],
                    'se_complete' => $episode['se_complete'],
                    'title' => $episode['title'],
                    'firstaired' => $episode['firstaired'] !== '' ? $episode['firstaired'] : null,
                    'summary' => $episode['summary'],
                ]
            );
        }

        return $episodeId;
    }

    public function update(int $videoId, array $show = []): void
    {
        if ($show['country'] !== '') {
            $show['country'] = Country::countryCode($show['country']);
        }

        $ifStringID = 'IF(%s = 0, %s, %s)';
        $ifStringInfo = "IF(%s = '', %s, %s)";

        DB::update(
            sprintf(
                '
				UPDATE videos v
				LEFT JOIN tv_info tvi ON v.id = tvi.videos_id
				SET v.countries_id = %s, v.tvdb = %s, v.trakt = %s, v.tvrage = %s,
					v.tvmaze = %s, v.imdb = %s, v.tmdb = %s,
					tvi.summary = %s, tvi.publisher = %s, tvi.localzone = %s
				WHERE v.id = %d',
                sprintf($ifStringInfo, 'v.countries_id', escapeString($show['country']), 'v.countries_id'),
                sprintf($ifStringID, 'v.tvdb', $show['tvdb'], 'v.tvdb'),
                sprintf($ifStringID, 'v.trakt', $show['trakt'], 'v.trakt'),
                sprintf($ifStringID, 'v.tvrage', $show['tvrage'], 'v.tvrage'),
                sprintf($ifStringID, 'v.tvmaze', $show['tvmaze'], 'v.tvmaze'),
                sprintf($ifStringID, 'v.imdb', $show['imdb'], 'v.imdb'),
                sprintf($ifStringID, 'v.tmdb', $show['tmdb'], 'v.tmdb'),
                sprintf($ifStringInfo, 'tvi.summary', escapeString($show['summary']), 'tvi.summary'),
                sprintf($ifStringInfo, 'tvi.publisher', escapeString($show['publisher']), 'tvi.publisher'),
                sprintf($ifStringInfo, 'tvi.localzone', escapeString($show['localzone']), 'tvi.localzone'),
                $videoId
            )
        );
        if (! empty($show['aliases'])) {
            $this->addAliases($videoId, $show['aliases']);
        }
    }

    /**
     * @throws \Throwable
     */
    public function delete(int $id): mixed
    {
        return DB::transaction(function () use ($id) {
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
     * @return \App\Models\Video|false|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model
     */
    public function getSiteByID(string $column, int $id): \Illuminate\Database\Eloquent\Model|bool|\Illuminate\Database\Eloquent\Builder|Video
    {
        $return = false;
        $video = Video::query()->where('id', $id)->first([$column]);
        if ($column === '*') {
            $return = $video;
        } elseif ($column !== '*' && $video !== null) {
            $return = $video[$column];
        }

        return $return;
    }

    /**
     * Retrieves the Episode ID using the Video ID and either:
     * season/episode numbers OR the airdate.
     *
     * Returns the Episode ID or false if not found
     *
     * @return int|false
     */
    public function getBySeasonEp(int $id, int $series, int $episode, string $airdate = ''): bool|int
    {
        if ($series > 0 && $episode > 0) {
            $queryString = sprintf('tve.series = %d AND tve.episode = %d', $series, $episode);
        } elseif (! empty($airdate)) {
            $queryString = sprintf('DATE(tve.firstaired) = %s', escapeString(date('Y-m-d', strtotime($airdate))));
        } else {
            return false;
        }

        $episodeArr = DB::selectOne(
            sprintf(
                '
				SELECT tve.id
				FROM tv_episodes tve
				WHERE tve.videos_id = %d
				AND %s',
                $id,
                $queryString
            )
        );

        return $episodeArr->id ?? false;
    }

    /**
     * Returns (true) if episodes for a given Video ID exist or don't (false).
     */
    public function countEpsByVideoID(int $videoId): bool
    {
        $count = TvEpisode::query()
            ->where('videos_id', $videoId)->count(['id']);

        return $count !== null && $count > 0;
    }

    /**
     * @return array|false
     */
    public function parseInfo(string $relname): bool|array
    {
        $showInfo['name'] = $this->parseName($relname);

        if (! empty($showInfo['name'])) {
            // Retrieve the country from the cleaned name
            $showInfo['country'] = $this->parseCountry($showInfo['name']);

            // Clean show name.
            $showInfo['cleanname'] = preg_replace('/ - \d+$/i', '', $this->cleanName($showInfo['name']));

            // Get the Season/Episode/Airdate
            $showInfo += $this->parseSeasonEp($relname);

            if (isset($showInfo['season'], $showInfo['episode'])) {
                if (! isset($showInfo['airdate'])) {
                    // If year is present in the release name, add it to the cleaned name for title search
                    if (preg_match('/[^a-z0-9](?P<year>(19|20)(\d{2}))[^a-z0-9]/i', $relname, $yearMatch)) {
                        $showInfo['cleanname'] .= ' ('.$yearMatch['year'].')';
                    }
                    // Check for multi episode release.
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

        // For names that don't start with the title.
        if (preg_match('/^([^a-z0-9]{2,}|(sample|proof|repost)-)(?P<name>[\w .-]*?)'.$following.'/i', $relname, $hits)) {
            $showName = $hits['name'];
        } elseif (preg_match('/^(?P<name>[\w+][\s\w\'.-]*?)'.$following. '/i', $relname, $hits)) {
            // For names that start with the title.
            $showName = $hits['name'];
        }
        // If we still have any of the words in $following, remove them.
        $showName = preg_replace('/'.$following.'/i', ' ', $showName);
        // Remove leading date if present
        $showName = preg_replace('/^\d{6}/', '', $showName);
        // Remove periods, underscored, anything between parenthesis.
        $showName = preg_replace('/\(.*?\)|[._]/i', ' ', $showName);
        // Finally remove multiple spaces and trim leading spaces.
        $showName = trim(preg_replace('/\s{2,}/', ' ', $showName));

        return $showName;
    }

    /**
     * Parses the release searchname for the season/episode/airdate information.
     */
    private function parseSeasonEp(string $relname): array
    {
        $episodeArr = [];

        // S01E01-E02 and S01E01-02
        if (preg_match('/^(.*?)[^a-z0-9]s(\d{1,2})[^a-z0-9]?e(\d{1,3})(?:[e-])(\d{1,3})[^a-z0-9]?/i', $relname, $hits)) {
            $episodeArr['season'] = (int) $hits[2];
            $episodeArr['episode'] = [(int) $hits[3], (int) $hits[4]];
        }
        //S01E0102 and S01E01E02 - lame no delimit numbering, regex would collide if there was ever 1000 ep season.
        elseif (preg_match('/^(.*?)[^a-z0-9]s(\d{2})[^a-z0-9]?e(\d{2})e?(\d{2})[^a-z0-9]?/i', $relname, $hits)) {
            $episodeArr['season'] = (int) $hits[2];
            $episodeArr['episode'] = (int) $hits[3];
        }
        // S01E01 and S01.E01
        elseif (preg_match('/^(.*?)[^a-z0-9]s(\d{1,2})[^a-z0-9]?e(\d{1,3})[abr]?[^a-z0-9]?/i', $relname, $hits)) {
            $episodeArr['season'] = (int) $hits[2];
            $episodeArr['episode'] = (int) $hits[3];
        }
        // S01
        elseif (preg_match('/^(.*?)[^a-z0-9]s(\d{1,2})[^a-z0-9]?/i', $relname, $hits)) {
            $episodeArr['season'] = (int) $hits[2];
            $episodeArr['episode'] = 'all';
        }
        // S01D1 and S1D1
        elseif (preg_match('/^(.*?)[^a-z0-9]s(\d{1,2})[^a-z0-9]?d\d{1}[^a-z0-9]?/i', $relname, $hits)) {
            $episodeArr['season'] = (int) $hits[2];
            $episodeArr['episode'] = 'all';
        }
        // 1x01 and 101
        elseif (preg_match('/^(.*?)[^a-z0-9](\d{1,2})x(\d{1,3})[^a-z0-9]?/i', $relname, $hits)) {
            $episodeArr['season'] = (int) $hits[2];
            $episodeArr['episode'] = (int) $hits[3];
        }
        // 2009.01.01 and 2009-01-01
        elseif (preg_match('/^(.*?)[^a-z0-9](?P<airdate>(19|20)(\d{2})[.\/-](\d{2})[.\/-](\d{2}))[^a-z0-9]?/i', $relname, $hits)) {
            $episodeArr['season'] = $hits[4].$hits[5];
            $episodeArr['episode'] = $hits[5].'/'.$hits[6];
            $episodeArr['airdate'] = date('Y-m-d', strtotime(preg_replace('/[^0-9]/i', '/', $hits['airdate']))); //yyyy-mm-dd
        }
        // 01.01.2009
        elseif (preg_match('/^(.*?)[^a-z0-9](?P<airdate>(\d{2})[^a-z0-9](\d{2})[^a-z0-9](19|20)(\d{2}))[^a-z0-9]?/i', $relname, $hits)) {
            $episodeArr['season'] = $hits[5].$hits[6];
            $episodeArr['episode'] = $hits[3].'/'.$hits[4];
            $episodeArr['airdate'] = date('Y-m-d', strtotime(preg_replace('/[^0-9]/i', '/', $hits['airdate']))); //yyyy-mm-dd
        }
        // 01.01.09
        elseif (preg_match('/^(.*?)[^a-z0-9](\d{2})[^a-z0-9](\d{2})[^a-z0-9](\d{2})[^a-z0-9]?/i', $relname, $hits)) {
            // Add extra logic to capture the proper YYYY year
            $episodeArr['season'] = $hits[4] = ($hits[4] <= 99 && $hits[4] > 15) ? '19'.$hits[4] : '20'.$hits[4];
            $episodeArr['episode'] = $hits[2].'/'.$hits[3];
            $tmpAirdate = $episodeArr['season'].'/'.$episodeArr['episode'];
            $episodeArr['airdate'] = date('Y-m-d', strtotime(preg_replace('/[^0-9]/i', '/', $tmpAirdate))); //yyyy-mm-dd
        }
        // 2009.E01
        elseif (preg_match('/^(.*?)[^a-z0-9]20(\d{2})[^a-z0-9](\d{1,3})[^a-z0-9]?/i', $relname, $hits)) {
            $episodeArr['season'] = '20'.$hits[2];
            $episodeArr['episode'] = (int) $hits[3];
        }
        // 2009.Part1
        elseif (preg_match('/^(.*?)[^a-z0-9](19|20)(\d{2})[^a-z0-9]Part(\d{1,2})[^a-z0-9]?/i', $relname, $hits)) {
            $episodeArr['season'] = $hits[2].$hits[3];
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
        // Season.1
        elseif (preg_match('/^(.*?)[^a-z0-9]Seasons?[^a-z0-9]?(\d{1,2})/i', $relname, $hits)) {
            $episodeArr['season'] = (int) $hits[2];
            $episodeArr['episode'] = 'all';
        }

        return $episodeArr;
    }

    /**
     * Parses the cleaned release name to determine if it has a country appended.
     */
    private function parseCountry(string $showName): string
    {
        // Country or origin matching.
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
     * Returns the cleaned string.
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
        $str = str_replace(['\'', ':', '!', '"', '#', '*', '’', ',', '(', ')', '?'], '', $str);
        $str = str_replace('$', 's', $str);
        $str = preg_replace('/\s{2,}/', ' ', $str);

        $str = trim($str, '\"');

        return trim($str);
    }

    /**
     * Simple function that compares two strings of text
     * Returns percentage of similarity.
     */
    public function checkMatch($ourName, $scrapeName, $probability): float|int
    {
        similar_text($ourName, $scrapeName, $matchpct);

        if ($matchpct >= $probability) {
            return $matchpct;
        }

        return 0;
    }

    //

    /**
     * Convert 2012-24-07 to 2012-07-24, there is probably a better way.
     *
     * This shouldn't ever happen as I've never heard of a date starting with year being followed by day value.
     * Could this be a mistake? i.e. trying to solve the mm-dd-yyyy/dd-mm-yyyy confusion into a yyyy-mm-dd?
     */
    public function checkDate(bool|string|null $date): string
    {
        if (! empty($date)) {
            $chk = explode(' ', $date);
            $chkd = explode('-', $chk[0]);
            if ($chkd[1] > 12) {
                $date = date('Y-m-d H:i:s', strtotime($chkd[1].' '.$chkd[2].' '.$chkd[0]));
            }
        } else {
            $date = null;
        }

        return $date;
    }

    /**
     * Checks API response returns have all REQUIRED attributes set
     * Returns true or false.
     */
    public function checkRequiredAttr($array, string $type): bool
    {
        $required = ['failedToMatchType'];

        switch ($type) {
            case 'tvdbS':
                $required = ['tvdb_id', 'name', 'overview', 'first_air_time'];
                break;
            case 'tvdbE':
                $required = ['name', 'seasonNumber', 'number', 'aired', 'overview'];
                break;
            case 'tvmazeS':
                $required = ['id', 'name', 'summary', 'premiered', 'country'];
                break;
            case 'tvmazeE':
                $required = ['name', 'season', 'number', 'airdate', 'summary'];
                break;
            case 'tmdbS':
                $required = ['id', 'original_name', 'overview', 'first_air_date', 'origin_country'];
                break;
            case 'tmdbE':
                $required = ['name', 'season_number', 'episode_number', 'air_date', 'overview'];
                break;
            case 'traktS':
                $required = ['title', 'ids', 'overview', 'first_aired', 'airs', 'country'];
                break;
            case 'traktE':
                $required = ['title', 'season', 'episode', 'overview', 'first_aired'];
                break;
        }

        foreach ($required as $req) {
            if (! \in_array($type, ['tmdbS', 'tmdbE', 'traktS', 'traktE'], false)) {
                if (! isset($array->$req)) {
                    return false;
                }
            } elseif (! isset($array[$req])) {
                return false;
            }
        }

        return true;
    }
}

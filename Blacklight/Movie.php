<?php

namespace Blacklight;

use aharen\OMDbAPI;
use App\Models\Category;
use App\Models\MovieInfo;
use App\Models\Release;
use App\Models\Settings;
use Blacklight\libraries\FanartTV;
use Blacklight\processing\tv\TraktTv;
use Blacklight\utility\Utility;
use DariusIII\ItunesApi\Exceptions\InvalidProviderException;
use DariusIII\ItunesApi\Exceptions\MovieNotFoundException;
use DariusIII\ItunesApi\Exceptions\SearchNoResultsException;
use DariusIII\ItunesApi\iTunes;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Imdb\Config;
use Imdb\Title;
use Imdb\TitleSearch;
use Tmdb\Exception\TmdbApiException;
use Tmdb\Laravel\Facades\Tmdb;

/**
 * Class Movie.
 */
class Movie
{
    /**
     * @var int
     */
    protected const MATCH_PERCENT = 75;

    /**
     * @var int
     */
    protected const YEAR_MATCH_PERCENT = 80;

    /**
     * Current title being passed through various sites/api's.
     */
    protected string $currentTitle = '';

    /**
     * Current year of parsed search name.
     */
    protected string $currentYear = '';

    /**
     * Current release id of parsed search name.
     */
    protected string $currentRelID = '';

    protected string $showPasswords;

    protected ReleaseImage $releaseImage;

    protected Client $client;

    /**
     * Language to fetch from IMDB.
     */
    protected string $lookuplanguage;

    public FanartTV $fanart;

    /**
     * @var null|string
     */
    public mixed $fanartapikey;

    /**
     * @var null|string
     */
    public mixed $omdbapikey;

    /**
     * @var bool
     */
    public $imdburl;

    public int $movieqty;

    public bool $echooutput;

    public string $imgSavePath;

    public string $service;

    public TraktTv $traktTv;

    public ?OMDbAPI $omdbApi;

    private Config $config;

    /**
     * @var null|string
     */
    protected mixed $traktcheck;

    protected ColorCLI $colorCli;

    /**
     * @throws \Exception
     */
    public function __construct()
    {

        $this->releaseImage = new ReleaseImage;
        $this->colorCli = new ColorCLI;
        $this->traktcheck = config('nntmux_api.trakttv_api_key');
        if ($this->traktcheck !== null) {
            $this->traktTv = new TraktTv(['Settings' => null]);
        }
        $this->client = new Client;
        $this->fanartapikey = config('nntmux_api.fanarttv_api_key');
        if ($this->fanartapikey !== null) {
            $this->fanart = new FanartTV($this->fanartapikey);
        }
        $this->omdbapikey = config('nntmux_api.omdb_api_key');
        if ($this->omdbapikey !== null) {
            $this->omdbApi = new OMDbAPI($this->omdbapikey);
        }

        $this->lookuplanguage = Settings::settingValue('imdblanguage') !== '' ? (string) Settings::settingValue('imdblanguage') : 'en';
        $this->config = new Config;
        $this->config->language = $this->lookuplanguage;
        $this->config->throwHttpExceptions = false;
        $cacheDir = storage_path('framework/cache/imdb_cache');
        if (! File::isDirectory($cacheDir)) {
            File::makeDirectory($cacheDir, 0777, false, true);
        }
        $this->config->cachedir = $cacheDir;

        $this->imdburl = (int) Settings::settingValue('imdburl') !== 0;
        $this->movieqty = Settings::settingValue('maximdbprocessed') !== '' ? (int) Settings::settingValue('maximdbprocessed') : 100;
        $this->showPasswords = (new Releases)->showPasswords();

        $this->echooutput = config('nntmux.echocli');
        $this->imgSavePath = storage_path('covers/movies/');
        $this->service = '';
    }

    /**
     * @return Builder|Model|null|object
     */
    public function getMovieInfo($imdbId)
    {
        return MovieInfo::query()->where('imdbid', $imdbId)->first();
    }

    /**
     * Get movie releases with covers for movie browse page.
     *
     *
     * @return array|mixed
     */
    public function getMovieRange($page, $cat, $start, $num, $orderBy, int $maxAge = -1, array $excludedCats = [])
    {
        $page = max(1, $page);
        $start = max(0, $start);

        $catsrch = '';
        if (\count($cat) > 0 && $cat[0] !== -1) {
            $catsrch = Category::getCategorySearch($cat);
        }
        $order = $this->getMovieOrder($orderBy);
        $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_medium'));
        $moviesSql =
            sprintf(
                "
					SELECT SQL_CALC_FOUND_ROWS
						m.imdbid,
						GROUP_CONCAT(r.id ORDER BY r.postdate DESC SEPARATOR ',') AS grp_release_id
					FROM movieinfo m
					LEFT JOIN releases r USING (imdbid)
					WHERE r.nzbstatus = 1
					AND m.title != ''
					AND m.imdbid != '0000000'
					AND r.passwordstatus %s
					%s %s %s %s
					GROUP BY m.imdbid
					ORDER BY %s %s %s",
                $this->showPasswords,
                $this->getBrowseBy(),
                (! empty($catsrch) ? $catsrch : ''),
                (
                    $maxAge > 0
                        ? 'AND r.postdate > NOW() - INTERVAL '.$maxAge.'DAY '
                        : ''
                ),
                \count($excludedCats) > 0 ? ' AND r.categories_id NOT IN ('.implode(',', $excludedCats).')' : '',
                $order[0],
                $order[1],
                $start === false ? '' : ' LIMIT '.$num.' OFFSET '.$start
            );
        $movieCache = Cache::get(md5($moviesSql.$page));
        if ($movieCache !== null) {
            $movies = $movieCache;
        } else {
            $data = MovieInfo::fromQuery($moviesSql);
            $movies = ['total' => DB::select('SELECT FOUND_ROWS() AS total'), 'result' => $data];
            Cache::put(md5($moviesSql.$page), $movies, $expiresAt);
        }
        $movieIDs = $releaseIDs = [];
        if (! empty($movies['result'])) {
            foreach ($movies['result'] as $movie => $id) {
                $movieIDs[] = $id->imdbid;
                $releaseIDs[] = $id->grp_release_id;
            }
        }

        $sql = sprintf(
            "
			SELECT
				GROUP_CONCAT(r.id ORDER BY r.postdate DESC SEPARATOR ',') AS grp_release_id,
				GROUP_CONCAT(r.rarinnerfilecount ORDER BY r.postdate DESC SEPARATOR ',') AS grp_rarinnerfilecount,
				GROUP_CONCAT(r.haspreview ORDER BY r.postdate DESC SEPARATOR ',') AS grp_haspreview,
				GROUP_CONCAT(r.passwordstatus ORDER BY r.postdate DESC SEPARATOR ',') AS grp_release_password,
				GROUP_CONCAT(r.guid ORDER BY r.postdate DESC SEPARATOR ',') AS grp_release_guid,
				GROUP_CONCAT(rn.releases_id ORDER BY r.postdate DESC SEPARATOR ',') AS grp_release_nfoid,
				GROUP_CONCAT(g.name ORDER BY r.postdate DESC SEPARATOR ',') AS grp_release_grpname,
				GROUP_CONCAT(r.searchname ORDER BY r.postdate DESC SEPARATOR '#') AS grp_release_name,
				GROUP_CONCAT(r.postdate ORDER BY r.postdate DESC SEPARATOR ',') AS grp_release_postdate,
				GROUP_CONCAT(r.size ORDER BY r.postdate DESC SEPARATOR ',') AS grp_release_size,
				GROUP_CONCAT(r.totalpart ORDER BY r.postdate DESC SEPARATOR ',') AS grp_release_totalparts,
				GROUP_CONCAT(r.comments ORDER BY r.postdate DESC SEPARATOR ',') AS grp_release_comments,
				GROUP_CONCAT(r.grabs ORDER BY r.postdate DESC SEPARATOR ',') AS grp_release_grabs,
				GROUP_CONCAT(df.failed ORDER BY r.postdate DESC SEPARATOR ',') AS grp_release_failed,
				GROUP_CONCAT(cp.title, ' > ', c.title ORDER BY r.postdate DESC SEPARATOR ',') AS grp_release_catname,
			m.*,
			g.name AS group_name,
			rn.releases_id AS nfoid
			FROM releases r
			LEFT OUTER JOIN usenet_groups g ON g.id = r.groups_id
			LEFT OUTER JOIN release_nfos rn ON rn.releases_id = r.id
			LEFT OUTER JOIN dnzb_failures df ON df.release_id = r.id
			LEFT OUTER JOIN categories c ON c.id = r.categories_id
			LEFT OUTER JOIN root_categories cp ON cp.id = c.root_categories_id
			INNER JOIN movieinfo m ON m.imdbid = r.imdbid
			WHERE m.imdbid IN (%s)
			AND r.id IN (%s) %s
			GROUP BY m.imdbid
			ORDER BY %s %s",
            (\is_array($movieIDs) && ! empty($movieIDs) ? implode(',', $movieIDs) : -1),
            (\is_array($releaseIDs) && ! empty($releaseIDs) ? implode(',', $releaseIDs) : -1),
            (! empty($catsrch) ? $catsrch : ''),
            $order[0],
            $order[1]
        );
        $return = Cache::get(md5($sql.$page));
        if ($return !== null) {
            return $return;
        }
        $return = Release::fromQuery($sql);
        if (\count($return) > 0) {
            $return[0]->_totalcount = $movies['total'][0]->total ?? 0;
        }
        Cache::put(md5($sql.$page), $return, $expiresAt);

        return $return;
    }

    /**
     * Get the order type the user requested on the movies page.
     */
    protected function getMovieOrder($orderBy): array
    {
        $orderArr = explode('_', (($orderBy === '') ? 'MAX(r.postdate)' : $orderBy));
        $orderField = match ($orderArr[0]) {
            'title' => 'm.title',
            'year' => 'm.year',
            'rating' => 'm.rating',
            default => 'MAX(r.postdate)',
        };

        return [$orderField, isset($orderArr[1]) && preg_match('/^asc|desc$/i', $orderArr[1]) ? $orderArr[1] : 'desc'];
    }

    /**
     * Order types for movie page.
     */
    public function getMovieOrdering(): array
    {
        return ['title_asc', 'title_desc', 'year_asc', 'year_desc', 'rating_asc', 'rating_desc'];
    }

    protected function getBrowseBy(): string
    {
        $browseBy = ' ';
        $browseByArr = ['title', 'director', 'actors', 'genre', 'rating', 'year', 'imdb'];
        foreach ($browseByArr as $bb) {
            if (request()->has($bb) && ! empty(request()->input($bb))) {
                $bbv = stripslashes(request()->input($bb));
                if ($bb === 'rating') {
                    $bbv .= '.';
                }
                if ($bb === 'imdb') {
                    $browseBy .= sprintf(' AND m.imdbid = %d', $bbv);
                } else {
                    $browseBy .= ' AND m.'.$bb.' '.'LIKE '.escapeString('%'.$bbv.'%');
                }
            }
        }

        return $browseBy;
    }

    /**
     * Get trailer using IMDB Id.
     *
     * @return bool|string
     *
     * @throws \Exception
     * @throws GuzzleException
     */
    public function getTrailer(int $imdbId)
    {
        $trailer = MovieInfo::query()->where('imdbid', $imdbId)->where('trailer', '<>', '')->first(['trailer']);
        if ($trailer !== null) {
            return $trailer['trailer'];
        }

        if ($this->traktcheck !== null) {
            $data = $this->traktTv->client->movieSummary('tt'.$imdbId, 'full');
            if (($data !== false) && ! empty($data['trailer'])) {
                return $data['trailer'];
            }
        }

        $trailer = Utility::imdb_trailers($imdbId);
        if ($trailer) {
            MovieInfo::query()->where('imdbid', $imdbId)->update(['trailer' => $trailer]);

            return $trailer;
        }

        return false;
    }

    /**
     * Parse trakt info, insert into DB.
     *
     * @return mixed
     */
    public function parseTraktTv(array &$data)
    {
        if (empty($data['ids']['imdb'])) {
            return false;
        }

        if (! empty($data['trailer'])) {
            $data['trailer'] = str_ireplace(
                ['watch?v=', 'http://'],
                ['embed/', 'https://'],
                $data['trailer']
            );
        }
        $imdbId = (str_starts_with($data['ids']['imdb'], 'tt')) ? substr($data['ids']['imdb'], 2) : $data['ids']['imdb'];
        $cover = 0;
        if (File::isFile($this->imgSavePath.$imdbId).'-cover.jpg') {
            $cover = 1;
        }

        return $this->update([
            'genre' => implode(', ', $data['genres']),
            'imdbid' => $this->checkTraktValue($imdbId),
            'language' => $this->checkTraktValue($data['language']),
            'plot' => $this->checkTraktValue($data['overview']),
            'rating' => $this->checkTraktValue($data['rating']),
            'tagline' => $this->checkTraktValue($data['tagline']),
            'title' => $this->checkTraktValue($data['title']),
            'tmdbid' => $this->checkTraktValue($data['ids']['tmdb']),
            'traktid' => $this->checkTraktValue($data['ids']['trakt']),
            'trailer' => $this->checkTraktValue($data['trailer']),
            'cover' => $cover,
            'year' => $this->checkTraktValue($data['year']),
        ]);
    }

    /**
     * @return mixed|string
     */
    private function checkTraktValue($value)
    {
        if (\is_array($value) && ! empty($value)) {
            $temp = '';
            foreach ($value as $val) {
                if (! is_array($val) && ! is_object($val)) {
                    $temp .= $val;
                }
            }
            $value = $temp;
        }

        return ! empty($value) ? $value : '';
    }

    /**
     * Get array of column keys, for inserting / updating.
     */
    public function getColumnKeys(): array
    {
        return [
            'actors', 'backdrop', 'cover', 'director', 'genre', 'imdbid', 'language',
            'plot', 'rating', 'rtrating', 'tagline', 'title', 'tmdbid', 'traktid', 'trailer', 'type', 'year',
        ];
    }

    /**
     * Update movie on movie-edit page.
     *
     * @param  array  $values  Array of keys/values to update. See $validKeys
     */
    public function update(array $values): bool
    {
        if (! count($values)) {
            return false;
        }

        $query = [];
        $onDuplicateKey = ['created_at' => now()];
        $found = 0;
        foreach ($values as $key => $value) {
            if (! empty($value)) {
                $found++;
                if (\in_array($key, ['genre', 'language'], false)) {
                    $value = substr($value, 0, 64);
                }
                $query += [$key => $value];
                $onDuplicateKey += [$key => $value];
            }
        }
        if (! $found) {
            return false;
        }
        foreach ($query as $key => $value) {
            $query[$key] = rtrim($value, ', ');
        }

        MovieInfo::upsert($query, ['imdbid'], $onDuplicateKey);

        return true;
    }

    /**
     * @return array|string
     */
    protected function setVariables(string|array $variable1, string|array $variable2, string|array $variable3, string|array $variable4, string|array $variable5)
    {
        if (! empty($variable1)) {
            return $variable1;
        }
        if (! empty($variable2)) {
            return $variable2;
        }
        if (! empty($variable3)) {
            return $variable3;
        }
        if (! empty($variable4)) {
            return $variable4;
        }
        if (! empty($variable5)) {
            return $variable5;
        }

        return '';
    }

    /**
     * Fetch IMDB/TMDB/TRAKT/OMDB/iTunes info for the movie.
     *
     *
     * @throws \Exception
     */
    public function updateMovieInfo($imdbId): bool
    {
        if ($this->echooutput && $this->service !== '') {
            $this->colorCli->primary('Fetching IMDB info from TMDB/IMDB/Trakt/OMDB/iTunes using IMDB id: '.$imdbId);
        }

        // Check TMDB for IMDB info.
        $tmdb = $this->fetchTMDBProperties($imdbId);

        // Check IMDB for movie info.
        $imdb = $this->fetchIMDBProperties($imdbId);

        // Check TRAKT for movie info
        $trakt = $this->fetchTraktTVProperties($imdbId);

        // Check OMDb for movie info
        $omdb = $this->fetchOmdbAPIProperties($imdbId);

        // Check iTunes for movie info as last resort (iTunes do not provide all the info we need)

        $iTunes = $this->fetchItunesMovieProperties($this->currentTitle);

        if (! $imdb && ! $tmdb && ! $trakt && ! $omdb && empty($iTunes)) {
            return false;
        }

        // Check FanArt.tv for cover and background images.
        $fanart = $this->fetchFanartTVProperties($imdbId);

        $mov = [];

        $mov['cover'] = $mov['backdrop'] = $mov['banner'] = 0;
        $mov['type'] = $mov['director'] = $mov['actors'] = $mov['language'] = '';

        $mov['imdbid'] = $imdbId;
        $mov['tmdbid'] = (! isset($tmdb['tmdbid']) || $tmdb['tmdbid'] === '') ? 0 : $tmdb['tmdbid'];
        $mov['traktid'] = (! isset($trakt['id']) || $trakt['id'] === '') ? 0 : $trakt['id'];

        // Prefer Fanart.tv cover over TMDB,TMDB over IMDB,IMDB over OMDB and OMDB over iTunes.
        if (! empty($fanart['cover'])) {
            $mov['cover'] = $this->releaseImage->saveImage($imdbId.'-cover', $fanart['cover'], $this->imgSavePath);
        } elseif (! empty($tmdb['cover'])) {
            $mov['cover'] = $this->releaseImage->saveImage($imdbId.'-cover', $tmdb['cover'], $this->imgSavePath);
        } elseif (! empty($imdb['cover'])) {
            $mov['cover'] = $this->releaseImage->saveImage($imdbId.'-cover', $imdb['cover'], $this->imgSavePath);
        } elseif (! empty($omdb['cover'])) {
            $mov['cover'] = $this->releaseImage->saveImage($imdbId.'-cover', $omdb['cover'], $this->imgSavePath);
        } elseif (! empty($iTunes['cover'])) {
            $mov['cover'] = $this->releaseImage->saveImage($imdbId.'-cover', $iTunes['cover'], $this->imgSavePath);
        }

        // Backdrops.
        if (! empty($fanart['backdrop'])) {
            $mov['backdrop'] = $this->releaseImage->saveImage($imdbId.'-backdrop', $fanart['backdrop'], $this->imgSavePath, 1920, 1024);
        } elseif (! empty($tmdb['backdrop'])) {
            $mov['backdrop'] = $this->releaseImage->saveImage($imdbId.'-backdrop', $tmdb['backdrop'], $this->imgSavePath, 1920, 1024);
        }

        // Banner
        if (! empty($fanart['banner'])) {
            $mov['banner'] = $this->releaseImage->saveImage($imdbId.'-banner', $fanart['banner'], $this->imgSavePath);
        }

        // RottenTomatoes rating from OmdbAPI
        if ($omdb !== false && ! empty($omdb['rtRating'])) {
            $mov['rtrating'] = $omdb['rtRating'];
        }

        $mov['title'] = $this->setVariables($imdb['title'] ?? '', $tmdb['title'] ?? '', $trakt['title'] ?? '', $omdb['title'] ?? '', $iTunes['title'] ?? '');
        $mov['rating'] = $this->setVariables($imdb['rating'] ?? '', $tmdb['rating'] ?? '', $trakt['rating'] ?? '', $omdb['rating'] ?? '', $iTunes['rating'] ?? '');
        $mov['plot'] = $this->setVariables($imdb['plot'] ?? '', $tmdb['plot'] ?? '', $trakt['overview'] ?? '', $omdb['plot'] ?? '', $iTunes['plot'] ?? '');
        $mov['tagline'] = $this->setVariables($imdb['tagline'] ?? '', $tmdb['tagline'] ?? '', $trakt['tagline'] ?? '', $omdb['tagline'] ?? '', $iTunes['tagline'] ?? '');
        $mov['year'] = $this->setVariables($imdb['year'] ?? '', $tmdb['year'] ?? '', $trakt['year'] ?? '', $omdb['year'] ?? '', $iTunes['year'] ?? '');
        $mov['genre'] = $this->setVariables($imdb['genre'] ?? '', $tmdb['genre'] ?? '', $trakt['genres'] ?? '', $omdb['genre'] ?? '', $iTunes['genre'] ?? '');

        if (! empty($imdb['type'])) {
            $mov['type'] = $imdb['type'];
        }

        if (! empty($imdb['director'])) {
            $mov['director'] = \is_array($imdb['director']) ? implode(', ', array_unique($imdb['director'])) : $imdb['director'];
        } elseif (! empty($omdb['director'])) {
            $mov['director'] = \is_array($omdb['director']) ? implode(', ', array_unique($omdb['director'])) : $omdb['director'];
        } elseif (! empty($tmdb['director'])) {
            $mov['director'] = \is_array($tmdb['director']) ? implode(', ', array_unique($tmdb['director'])) : $tmdb['director'];
        }

        if (! empty($imdb['actors'])) {
            $mov['actors'] = \is_array($imdb['actors']) ? implode(', ', array_unique($imdb['actors'])) : $imdb['actors'];
        } elseif (! empty($omdb['actors'])) {
            $mov['actors'] = \is_array($omdb['actors']) ? implode(', ', array_unique($omdb['actors'])) : $omdb['actors'];
        } elseif (! empty($tmdb['actors'])) {
            $mov['actors'] = \is_array($tmdb['actors']) ? implode(', ', array_unique($tmdb['actors'])) : $tmdb['actors'];
        }

        if (! empty($imdb['language'])) {
            $mov['language'] = \is_array($imdb['language']) ? implode(', ', array_unique($imdb['language'])) : $imdb['language'];
        } elseif (! empty($omdb['language']) && ! is_bool($omdb['language'])) {
            $mov['language'] = \is_array($omdb['language']) ? implode(', ', array_unique($omdb['language'])) : $omdb['language'];
        }

        if (\is_array($mov['genre'])) {
            $mov['genre'] = implode(', ', array_unique($mov['genre']));
        }

        if (\is_array($mov['type'])) {
            $mov['type'] = implode(', ', array_unique($mov['type']));
        }

        $mov['title'] = html_entity_decode($mov['title'], ENT_QUOTES, 'UTF-8');

        $mov['title'] = str_replace(['/', '\\'], '', $mov['title']);
        $movieID = $this->update([
            'actors' => html_entity_decode($mov['actors'], ENT_QUOTES, 'UTF-8'),
            'backdrop' => $mov['backdrop'],
            'cover' => $mov['cover'],
            'director' => html_entity_decode($mov['director'], ENT_QUOTES, 'UTF-8'),
            'genre' => html_entity_decode($mov['genre'], ENT_QUOTES, 'UTF-8'),
            'imdbid' => $mov['imdbid'],
            'language' => html_entity_decode($mov['language'], ENT_QUOTES, 'UTF-8'),
            'plot' => html_entity_decode(preg_replace('/\s+See full summary »/u', ' ', $mov['plot']), ENT_QUOTES, 'UTF-8'),
            'rating' => round((int) $mov['rating'], 1),
            'rtrating' => $mov['rtrating'] ?? 'N/A',
            'tagline' => html_entity_decode($mov['tagline'], ENT_QUOTES, 'UTF-8'),
            'title' => $mov['title'],
            'tmdbid' => $mov['tmdbid'],
            'traktid' => $mov['traktid'],
            'type' => html_entity_decode(ucwords(preg_replace('/[\.\_]/', ' ', $mov['type'])), ENT_QUOTES, 'UTF-8'),
            'year' => $mov['year'],
        ]);

        if ($this->echooutput && $this->service !== '') {
            PHP_EOL.$this->colorCli->headerOver('Added/updated movie: ').
            $this->colorCli->primary(
                $mov['title'].
                ' ('.
                $mov['year'].
                ') - '.
                $mov['imdbid']
            );
        }

        return $movieID;
    }

    /**
     * Fetch FanArt.tv backdrop / cover / title.
     *
     * @return array|false
     */
    protected function fetchFanartTVProperties($imdbId)
    {
        if ($this->fanartapikey !== null) {
            $art = $this->fanart->getMovieFanArt('tt'.$imdbId);

            if (! empty($art)) {
                if (isset($art['status']) && $art['status'] === 'error') {
                    return false;
                }
                $ret = [];
                if (! empty($art['moviebackground'][0]['url'])) {
                    $ret['backdrop'] = $art['moviebackground'][0]['url'];
                } elseif (! empty($art['moviethumb'][0]['url'])) {
                    $ret['backdrop'] = $art['moviethumb'][0]['url'];
                }
                if (! empty($art['movieposter'][0]['url'])) {
                    $ret['cover'] = $art['movieposter'][0]['url'];
                }
                if (! empty($art['moviebanner'][0]['url'])) {
                    $ret['banner'] = $art['moviebanner'][0]['url'];
                }

                if (isset($ret['backdrop'], $ret['cover'])) {
                    $ret['title'] = $imdbId;
                    if (isset($art['name'])) {
                        $ret['title'] = $art['name'];
                    }
                    if ($this->echooutput) {
                        $this->colorCli->climate()->info('Fanart found '.$ret['title']);
                    }

                    return $ret;
                }
            }
        }

        return false;
    }

    /**
     * Fetch movie information from TMDB using an IMDB ID.
     *
     * @param  string  $imdbId  The IMDB ID to look up
     * @param  bool  $text  Whether the ID is already in text format
     * @return array|false Movie data array or false if not found/matched
     */
    public function fetchTMDBProperties(string $imdbId, bool $text = false): array|false
    {
        // Format the lookup ID correctly with 'tt' prefix if needed
        $lookupId = $text === false && (strlen($imdbId) === 7 || strlen($imdbId) === 8) ? 'tt'.$imdbId : $imdbId;

        // Create a cache key for this request
        $cacheKey = 'tmdb_movie_'.md5($lookupId);
        $expiresAt = now()->addDays(7); // Cache for 7 days since movie data rarely changes

        // Check if we have this cached
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            // Fetch movie data from TMDB
            $tmdbLookup = Tmdb::getMoviesApi()->getMovie($lookupId, ['append_to_response' => 'credits']);

            if (empty($tmdbLookup)) {
                Cache::put($cacheKey, false, $expiresAt);

                return false;
            }

            // Title similarity check
            if ($this->currentTitle !== '') {
                similar_text($this->currentTitle, $tmdbLookup['title'], $percent);
                if ($percent < self::MATCH_PERCENT) {
                    Cache::put($cacheKey, false, $expiresAt);

                    return false;
                }
            }

            // Year similarity check
            if ($this->currentYear !== '') {
                $tmdbYear = ! empty($tmdbLookup['release_date'])
                    ? Carbon::parse($tmdbLookup['release_date'])->year
                    : '';

                if ($tmdbYear !== '') {
                    similar_text($this->currentYear, $tmdbYear, $percent);
                    if ($percent < self::YEAR_MATCH_PERCENT) {
                        Cache::put($cacheKey, false, $expiresAt);

                        return false;
                    }
                }
            }

            // Build the return array with proper null handling
            $ret = [
                'title' => $tmdbLookup['title'] ?? '',
                'tmdbid' => $tmdbLookup['id'] ?? 0,
                'imdbid' => str_replace('tt', '', $tmdbLookup['imdb_id'] ?? ''),
                'rating' => '',
                'actors' => '',
                'director' => '',
                'plot' => $tmdbLookup['overview'] ?? '',
                'tagline' => $tmdbLookup['tagline'] ?? '',
                'year' => '',
                'genre' => '',
                'cover' => '',
                'backdrop' => '',
            ];

            // Rating
            $vote = $tmdbLookup['vote_average'] ?? null;
            if ($vote !== null && (int) $vote !== 0) {
                $ret['rating'] = $vote;
            }

            // Actors
            $actors = Arr::pluck($tmdbLookup['credits']['cast'] ?? [], 'name');
            if (! empty($actors)) {
                $ret['actors'] = $actors;
            }

            // Director - get first director only
            foreach ($tmdbLookup['credits']['crew'] ?? [] as $crew) {
                if ($crew['department'] === 'Directing' && $crew['job'] === 'Director') {
                    $ret['director'] = $crew['name'];
                    break;
                }
            }

            // Year
            $released = $tmdbLookup['release_date'] ?? '';
            if (! empty($released)) {
                $ret['year'] = Carbon::parse($released)->year;
            }

            // Genres
            $genresa = $tmdbLookup['genres'] ?? [];
            if (! empty($genresa)) {
                $genres = [];
                foreach ($genresa as $genre) {
                    $genres[] = $genre['name'];
                }
                $ret['genre'] = $genres;
            }

            // Cover and backdrop
            $posterPath = $tmdbLookup['poster_path'] ?? '';
            if (! empty($posterPath)) {
                $ret['cover'] = 'https://image.tmdb.org/t/p/original'.$posterPath;
            }

            $backdropPath = $tmdbLookup['backdrop_path'] ?? '';
            if (! empty($backdropPath)) {
                $ret['backdrop'] = 'https://image.tmdb.org/t/p/original'.$backdropPath;
            }

            // Log success
            if ($this->echooutput) {
                $this->colorCli->climate()->info('TMDb found '.$ret['title']);
            }

            // Cache the result
            Cache::put($cacheKey, $ret, $expiresAt);

            return $ret;

        } catch (TmdbApiException|\ErrorException $error) {
            // Log the error
            Log::error('TMDB API error for '.$lookupId.': '.$error->getMessage());

            // Cache the failure but for shorter time
            Cache::put($cacheKey, false, now()->addHours(6));

            return false;
        }
    }

    /**
     * Fetch movie information from IMDB.
     *
     * @param  string  $imdbId  The IMDB ID to look up
     * @return array|false Movie data array or false if not found/matched
     */
    public function fetchIMDBProperties(string $imdbId): array|false
    {
        // Create a cache key for this request
        $cacheKey = 'imdb_movie_'.md5($imdbId);
        $expiresAt = now()->addDays(7); // Cache for 7 days

        // Check if we have this cached
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            // Get real ID and title information
            $realId = (new Title($imdbId, $this->config))->real_id();
            $result = new Title($realId, $this->config);
            $title = ! empty($result->orig_title()) ? $result->orig_title() : $result->title();

            // If no title was found, cache the failure and return false
            if (empty($title)) {
                Cache::put($cacheKey, false, now()->addHours(6));

                return false;
            }

            // Title similarity check when we have a title to compare against
            if (! empty($this->currentTitle)) {
                similar_text($this->currentTitle, $title, $percent);
                if ($percent < self::MATCH_PERCENT) {
                    Cache::put($cacheKey, false, now()->addHours(6));

                    return false;
                }

                // Year similarity check
                similar_text($this->currentYear, $result->year(), $percent);
                if ($percent < self::YEAR_MATCH_PERCENT) {
                    Cache::put($cacheKey, false, now()->addHours(6));

                    return false;
                }
            }

            // Build the movie data array
            $movieData = [
                'title' => $title,
                'tagline' => $result->tagline() ?? '',
                'plot' => Arr::get($result->plot_split(), '0.plot', ''),
                'rating' => $result->rating() ?? '',
                'year' => $result->year() ?? '',
                'cover' => $result->photo() ?? '',
                'genre' => $result->genre() ?? '',
                'language' => $result->language() ?? '',
                'type' => $result->movietype() ?? '',
                'actors' => $result->cast() ?? [],
                'director' => $result->director() ?? [],
            ];

            // Log success
            if ($this->echooutput) {
                $this->colorCli->climate()->info('IMDb found '.$title);
            }

            // Cache the successful result
            Cache::put($cacheKey, $movieData, $expiresAt);

            return $movieData;

        } catch (\Exception $e) {
            // Log the error
            Log::error('IMDB API error for '.$imdbId.': '.$e->getMessage());

            // Cache the failure but for shorter time
            Cache::put($cacheKey, false, now()->addHours(6));

            return false;
        }
    }

    /**
     * Fetch movie information from Trakt.tv using IMDB ID.
     *
     * @param  string  $imdbId  The IMDB ID without the 'tt' prefix
     * @return array|false Movie data array or false if not found/matched
     *
     * @throws GuzzleException Only if unhandled HTTP errors occur
     */
    public function fetchTraktTVProperties(string $imdbId): array|false
    {
        // Skip if Trakt API key isn't configured
        if ($this->traktcheck === null) {
            return false;
        }

        // Create a cache key for this request
        $cacheKey = 'trakt_movie_'.md5($imdbId);
        $expiresAt = now()->addDays(7); // Cache for 7 days since movie data rarely changes

        // Check if we have this cached
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            // Fetch movie data from Trakt.tv
            $resp = $this->traktTv->client->movieSummary('tt'.$imdbId, 'full');

            // If no result or no title, cache the failure and return
            if ($resp === false || empty($resp['title'])) {
                Cache::put($cacheKey, false, now()->addHours(6));

                return false;
            }

            // Title similarity check
            if (! empty($this->currentTitle)) {
                similar_text($this->currentTitle, $resp['title'], $percent);
                if ($percent < self::MATCH_PERCENT) {
                    Cache::put($cacheKey, false, now()->addHours(6));

                    return false;
                }
            }

            // Year similarity check
            if (! empty($this->currentYear) && ! empty($resp['year'])) {
                similar_text($this->currentYear, $resp['year'], $percent);
                if ($percent < self::YEAR_MATCH_PERCENT) {
                    Cache::put($cacheKey, false, now()->addHours(6));

                    return false;
                }
            }

            // Build the return data
            $movieData = [
                'id' => $resp['ids']['trakt'] ?? null,
                'title' => $resp['title'],
                'overview' => $resp['overview'] ?? '',
                'tagline' => $resp['tagline'] ?? '',
                'year' => $resp['year'] ?? '',
                'genres' => $resp['genres'] ?? '',
                'rating' => $resp['rating'] ?? '',
                'votes' => $resp['votes'] ?? 0,
                'language' => $resp['language'] ?? '',
                'runtime' => $resp['runtime'] ?? 0,
                'trailer' => $resp['trailer'] ?? '',
            ];

            // Log success
            if ($this->echooutput) {
                $this->colorCli->climate()->info('Trakt found '.$movieData['title']);
            }

            // Cache the successful result
            Cache::put($cacheKey, $movieData, $expiresAt);

            return $movieData;

        } catch (\Exception $e) {
            // Log the error
            Log::error('Trakt API error for '.$imdbId.': '.$e->getMessage());

            // Cache the failure but for shorter time
            Cache::put($cacheKey, false, now()->addHours(6));

            return false;
        }
    }

    /**
     * Fetch movie information from OMDB API using IMDB ID.
     *
     * @param  string  $imdbId  The IMDB ID without the 'tt' prefix
     * @return array|false Movie data array or false if not found/matched
     */
    public function fetchOmdbAPIProperties(string $imdbId): array|false
    {
        // Skip if OMDB API key isn't configured
        if ($this->omdbapikey === null) {
            return false;
        }

        // Create a cache key for this request
        $cacheKey = 'omdb_movie_'.md5($imdbId);
        $expiresAt = now()->addDays(7); // Cache for 7 days since movie data rarely changes

        // Check if we have this cached
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            // Fetch movie data from OMDB
            $resp = $this->omdbApi->fetch('i', 'tt'.$imdbId);

            // Validate the response
            if (! is_object($resp) ||
                $resp->message !== 'OK' ||
                Str::contains($resp->data->Response, 'Error:') ||
                $resp->data->Response === 'False') {

                Cache::put($cacheKey, false, now()->addHours(6));

                return false;
            }

            // Title similarity check when we have a title to compare against
            if (! empty($this->currentTitle)) {
                similar_text($this->currentTitle, $resp->data->Title, $percent);
                if ($percent < self::MATCH_PERCENT) {
                    Cache::put($cacheKey, false, now()->addHours(6));

                    return false;
                }

                // Year similarity check
                if (! empty($this->currentYear)) {
                    similar_text($this->currentYear, $resp->data->Year, $percent);
                    if ($percent < self::YEAR_MATCH_PERCENT) {
                        Cache::put($cacheKey, false, now()->addHours(6));

                        return false;
                    }
                }
            }

            // Safely extract the Rotten Tomatoes rating
            $rtRating = '';
            if (isset($resp->data->Ratings) && is_array($resp->data->Ratings) && count($resp->data->Ratings) > 1) {
                $rtRating = $resp->data->Ratings[1]->Value ?? '';
            }

            // Build the movie data array
            $movieData = [
                'title' => $resp->data->Title ?? '',
                'cover' => $resp->data->Poster ?? '',
                'genre' => $resp->data->Genre ?? '',
                'year' => $resp->data->Year ?? '',
                'plot' => $resp->data->Plot ?? '',
                'rating' => $resp->data->imdbRating ?? '',
                'rtRating' => $rtRating,
                'tagline' => $resp->data->Tagline ?? '',
                'director' => $resp->data->Director ?? '',
                'actors' => $resp->data->Actors ?? '',
                'language' => $resp->data->Language ?? '',
                'boxOffice' => $resp->data->BoxOffice ?? '',
            ];

            // Log success
            if ($this->echooutput) {
                $this->colorCli->climate()->info('OMDbAPI Found '.$movieData['title']);
            }

            // Cache the successful result
            Cache::put($cacheKey, $movieData, $expiresAt);

            return $movieData;

        } catch (\Exception $e) {
            // Log the error
            Log::error('OMDB API error for '.$imdbId.': '.$e->getMessage());

            // Cache the failure but for shorter time
            Cache::put($cacheKey, false, now()->addHours(6));

            return false;
        }
    }

    /**
     * Fetch movie information from iTunes API using movie title.
     *
     * @param  string  $title  The movie title to search for
     * @return array|false Movie data array or false if not found/matched
     *
     * @throws InvalidProviderException If there's an issue with the iTunes provider
     */
    public function fetchItunesMovieProperties(string $title): array|false
    {
        // Skip empty titles
        if (empty(trim($title))) {
            return false;
        }

        // Create a cache key for this request
        $cacheKey = 'itunes_movie_'.md5($title);
        $expiresAt = now()->addDays(7); // Cache for 7 days since movie data rarely changes

        // Check if we have this cached
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            // Fetch movie data from iTunes
            $iTunesMovie = iTunes::load('movie')->fetchOneByName($title);

            // Title similarity check
            similar_text($this->currentTitle, $iTunesMovie->getName(), $percent);
            if ($percent < self::MATCH_PERCENT) {
                // Movie found but title doesn't match closely enough
                Cache::put($cacheKey, false, now()->addHours(6));

                return false;
            }

            // Build the movie data array
            $movieData = [
                'title' => $iTunesMovie->getName(),
                'director' => $iTunesMovie->getDirector() ?? '',
                'tagline' => $iTunesMovie->getTagLine() ?? '',
                'cover' => str_replace('100x100', '800x800', $iTunesMovie->getCover()),
                'genre' => $iTunesMovie->getGenre() ?? '',
                'plot' => $iTunesMovie->getDescription() ?? '',
                'year' => $iTunesMovie->getReleaseDate() ? $iTunesMovie->getReleaseDate()->format('Y') : '',
                'rating' => '', // iTunes doesn't provide ratings
            ];

            // Log success
            if ($this->echooutput) {
                $this->colorCli->climate()->info('iTunes found '.$movieData['title']);
            }

            // Cache the successful result
            Cache::put($cacheKey, $movieData, $expiresAt);

            return $movieData;

        } catch (MovieNotFoundException|SearchNoResultsException $e) {
            // Not found or no results
            Log::info('iTunes movie not found for "'.$title.'": '.$e->getMessage());
            Cache::put($cacheKey, false, now()->addHours(6));

            return false;
        } catch (\Exception $e) {
            // General error
            Log::error('iTunes API error for "'.$title.'": '.$e->getMessage());
            Cache::put($cacheKey, false, now()->addHours(6));

            return false;
        }
    }

    /**
     * Update a release with an IMDB ID and related movie information.
     *
     * @param  string  $buffer  Data to parse an IMDB ID from
     * @param  string  $service  Method that called this method
     * @param  int  $id  ID of the release
     * @param  int  $processImdb  Whether to fetch movie info (1) or not (0)
     * @return string|false IMDB ID or false if not found
     *
     * @throws \Exception
     */
    public function doMovieUpdate(string $buffer, string $service, int $id, int $processImdb = 1): string|false
    {
        // Create a cache key for this request
        $cacheKey = 'release_imdb_'.md5($buffer.$id);

        // Check if we have this cached
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        // Extract IMDB ID using regex
        $imdbId = false;
        if (preg_match('/(?:imdb.*?)?(?:tt|Title\?)(?P<imdbid>\d{5,8})/i', $buffer, $hits)) {
            $imdbId = $hits['imdbid'];
        }

        if ($imdbId !== false) {
            try {
                $this->service = $service;
                if ($this->echooutput && $this->service !== '') {
                    $this->colorCli->climate()->info($this->service.' found IMDBid: tt'.$imdbId);
                }

                // Get movie info ID
                $movieInfoId = MovieInfo::query()->where('imdbid', $imdbId)->first(['id']);

                // Update release with IMDB ID
                Release::query()->where('id', $id)->update([
                    'imdbid' => $imdbId,
                    'movieinfo_id' => $movieInfoId !== null ? $movieInfoId['id'] : null,
                ]);

                // If set, scan for IMDB info
                if ($processImdb === 1) {
                    $movCheck = $this->getMovieInfo($imdbId);
                    $thirtyDaysInSeconds = 30 * 24 * 60 * 60; // 30 days in seconds

                    // Check if movie info is missing or outdated
                    if ($movCheck === null ||
                        (isset($movCheck['updated_at']) &&
                        (time() - strtotime($movCheck['updated_at'])) > $thirtyDaysInSeconds)) {

                        $info = $this->updateMovieInfo($imdbId);

                        if ($info === false) {
                            // Update failed, mark with invalid IMDB ID
                            Release::query()->where('id', $id)->update(['imdbid' => '0000000']);
                        } elseif ($info === true) {
                            // Get fresh movie info ID after update
                            $freshMovieInfo = MovieInfo::query()->where('imdbid', $imdbId)->first(['id']);

                            // Update release with movie info ID
                            Release::query()->where('id', $id)->update([
                                'movieinfo_id' => $freshMovieInfo !== null ? $freshMovieInfo['id'] : null,
                            ]);
                        }
                    }
                }

                // Cache the IMDB ID for future use
                Cache::put($cacheKey, $imdbId, now()->addDays(1));

                return $imdbId;
            } catch (\Exception $e) {
                // Log the error
                Log::error('Error updating movie information: '.$e->getMessage());

                // Cache the failure but for shorter time
                Cache::put($cacheKey, false, now()->addHours(1));

                return false;
            }
        }

        // Cache negative result to avoid repeated processing
        Cache::put($cacheKey, false, now()->addHours(3));

        return $imdbId;
    }

    /**
     * Process releases with no IMDB IDs by looking up movie information from various sources.
     *
     * Searches for IMDB IDs using multiple services in this order:
     * 1. Local database
     * 2. IMDb API
     * 3. OMDb API
     * 4. Trakt.tv
     * 5. The Movie Database (TMDB)
     *
     * @param  string  $groupID  Optional group ID to filter by
     * @param  string  $guidChar  Optional first character of GUID to filter by
     * @param  int  $lookupIMDB  0: Skip lookup, 1: Process all, 2: Only renamed releases
     *
     * @throws \Exception
     * @throws GuzzleException
     */
    public function processMovieReleases(string $groupID = '', string $guidChar = '', int $lookupIMDB = 1): void
    {
        // Skip processing if lookup is disabled
        if ($lookupIMDB === 0) {
            return;
        }

        // Create a cache key for this query
        $cacheKey = 'movie_releases_'.md5($groupID.$guidChar.$lookupIMDB);
        $shortCacheTime = now()->addHours(1);

        // Check if we have cached results
        if (Cache::has($cacheKey)) {
            $res = Cache::get($cacheKey);
        } else {
            // Build query to get releases without IMDB IDs
            $query = Release::query()
                ->select(['searchname', 'id'])
                ->whereBetween('categories_id', [Category::MOVIE_ROOT, Category::MOVIE_OTHER])
                ->whereNull('imdbid')
                ->where('nzbstatus', '=', 1);

            // Apply filters if provided
            if ($groupID !== '') {
                $query->where('groups_id', $groupID);
            }

            if ($guidChar !== '') {
                $query->where('leftguid', $guidChar);
            }

            if ((int) $lookupIMDB === 2) {
                $query->where('isrenamed', '=', 1);
            }

            // Execute the query with limit
            $res = $query->limit($this->movieqty)->get();

            // Cache the results
            Cache::put($cacheKey, $res, $shortCacheTime);
        }

        $movieCount = count($res);
        $failedIDs = []; // Track IDs that need to be marked as unidentifiable

        if ($movieCount > 0) {
            // Log the start of processing if multiple movies
            if ($this->echooutput && $movieCount > 1) {
                $this->colorCli->header('Processing '.$movieCount.' movie releases.');
            }

            // Loop over releases
            foreach ($res as $arr) {
                // Try to extract movie name and year
                if (! $this->parseMovieSearchName($arr['searchname'])) {
                    $failedIDs[] = $arr['id'];

                    continue;
                }

                $this->currentRelID = $arr['id'];
                $movieName = $this->formatMovieName();

                // Log current lookup if output is enabled
                if ($this->echooutput) {
                    $this->colorCli->climate()->info('Looking up: '.$movieName);
                }

                // Try all available sources to find IMDB ID
                $foundIMDB = $this->searchLocalDatabase($arr['id']) ||
                             $this->searchIMDb($arr['id']) ||
                             $this->searchOMDbAPI($arr['id']) ||
                             $this->searchTraktTV($arr['id'], $movieName) ||
                             $this->searchTMDB($arr['id']);

                // Double-check if we actually got an IMDB ID
                if ($foundIMDB) {
                    // Movie was successfully updated by one of the services
                    if ($this->echooutput) {
                        $this->colorCli->climate()->success('Successfully updated release with IMDB ID');
                    }

                    continue;
                } else {
                    // Verify the release wasn't actually updated
                    $releaseCheck = Release::query()->where('id', $arr['id'])->whereNotNull('imdbid')->exists();
                    if ($releaseCheck) {
                        if ($this->echooutput) {
                            $this->colorCli->climate()->info('Release already has IMDB ID, skipping');
                        }

                        continue;
                    }
                }

                // If we get here, all searches failed
                $failedIDs[] = $arr['id'];
            }

            // Batch update all failed releases at once
            if (! empty($failedIDs)) {
                // Get searchnames for failed releases to show in output
                if ($this->echooutput) {
                    $failedReleases = Release::query()
                        ->select(['id', 'searchname'])
                        ->whereIn('id', $failedIDs)
                        ->get();

                    $this->colorCli->header('Failed to find IMDB IDs for '.count($failedIDs).' releases:');
                    foreach ($failedReleases as $release) {
                        $this->colorCli->climate()->error("ID: {$release->id} - {$release->searchname}");
                    }
                }

                // Use chunk to avoid huge queries for many IDs
                foreach (array_chunk($failedIDs, 100) as $chunk) {
                    Release::query()->whereIn('id', $chunk)->update(['imdbid' => 0000000]);
                }
            }
        }
    }

    /**
     * Format current movie name with year if available.
     */
    private function formatMovieName(): string
    {
        $movieName = $this->currentTitle;
        if ($this->currentYear !== '') {
            $movieName .= ' ('.$this->currentYear.')';
        }

        return $movieName;
    }

    /**
     * Search local database for movie.
     */
    private function searchLocalDatabase(int $releaseId): bool
    {
        $getIMDBid = $this->localIMDBSearch();
        if ($getIMDBid === false) {
            return false;
        }

        $imdbId = $this->doMovieUpdate('tt'.$getIMDBid, 'Local DB', $releaseId);

        return $imdbId !== false;
    }

    /**
     * Search IMDb for movie.
     */
    private function searchIMDb(int $releaseId): bool
    {
        try {
            $imdbSearch = new TitleSearch($this->config);
            foreach ($imdbSearch->search($this->currentTitle, [TitleSearch::MOVIE]) as $imdbTitle) {
                if (empty($imdbTitle->title())) {
                    continue;
                }

                // Compare title similarity
                similar_text($imdbTitle->title(), $this->currentTitle, $percent);
                if ($percent < self::MATCH_PERCENT) {
                    continue;
                }

                // Compare year similarity if year is available
                similar_text($this->currentYear, $imdbTitle->year(), $yearPercent);
                if ($yearPercent < self::YEAR_MATCH_PERCENT) {
                    continue;
                }

                // Found a good match, update the release
                $getIMDBid = $imdbTitle->imdbid();
                $imdbId = $this->doMovieUpdate('tt'.$getIMDBid, 'IMDb', $releaseId);
                if ($imdbId !== false) {
                    return true;
                }
            }
        } catch (\ErrorException $e) {
            $this->colorCli->error('Error fetching data from IMDb: '.$e->getMessage(), true);
            Log::debug($e->getMessage());
        }

        return false;
    }

    /**
     * Search OMDb API for movie.
     */
    private function searchOMDbAPI(int $releaseId): bool
    {
        if ($this->omdbapikey === null) {
            return false;
        }

        $omdbTitle = strtolower(str_replace(' ', '_', $this->currentTitle));

        try {
            // Search with year if available, otherwise search without year
            $buffer = $this->currentYear !== ''
                ? $this->omdbApi->search($omdbTitle, 'movie', $this->currentYear)
                : $this->omdbApi->search($omdbTitle, 'movie');

            if (! is_object($buffer) ||
                $buffer->message !== 'OK' ||
                Str::contains($buffer->data->Response, 'Error:') ||
                $buffer->data->Response !== 'True' ||
                empty($buffer->data->Search[0]->imdbID)) {
                return false;
            }

            $getIMDBid = $buffer->data->Search[0]->imdbID;
            $imdbId = $this->doMovieUpdate($getIMDBid, 'OMDbAPI', $releaseId);

            return $imdbId !== false;

        } catch (\Exception $e) {
            // Log error but continue processing
            Log::error('OMDb API error: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Search Trakt.tv for movie.
     */
    private function searchTraktTV(int $releaseId, string $movieName): bool
    {
        if ($this->traktcheck === null) {
            return false;
        }

        try {
            $data = $this->traktTv->client->movieSummary($movieName, 'full');
            if ($data === false || empty($data['ids']['imdb'])) {
                return false;
            }

            $this->parseTraktTv($data);
            $imdbId = $this->doMovieUpdate($data['ids']['imdb'], 'Trakt', $releaseId);

            return $imdbId !== false;

        } catch (\Exception $e) {
            Log::error('Trakt.tv error: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Search TMDB for movie.
     */
    private function searchTMDB(int $releaseId): bool
    {
        try {
            $data = Tmdb::getSearchApi()->searchMovies($this->currentTitle);
            if (empty($data['total_results']) || empty($data['results'])) {
                return false;
            }

            foreach ($data['results'] as $result) {
                // Skip results without ID or release date
                if (empty($result['id']) || empty($result['release_date'])) {
                    continue;
                }

                // Compare release years
                similar_text(
                    $this->currentYear,
                    Carbon::parse($result['release_date'])->year,
                    $percent
                );

                if ($percent < self::YEAR_MATCH_PERCENT) {
                    continue;
                }

                // Try to get IMDB ID from TMDB
                $ret = $this->fetchTMDBProperties($result['id'], true);
                if ($ret === false || empty($ret['imdbid'])) {
                    continue;
                }

                $imdbId = $this->doMovieUpdate('tt'.$ret['imdbid'], 'TMDB', $releaseId);
                if ($imdbId !== false) {
                    return true;
                }
            }

        } catch (TmdbApiException|\ErrorException $error) {
            Log::error('TMDB API error: '.$error->getMessage());
        }

        return false;
    }

    /**
     * Search for a movie in the local database by title and year.
     *
     * @return string|false IMDB ID without 'tt' prefix if found, false otherwise
     */
    protected function localIMDBSearch(): string|false
    {
        // Skip processing if title is empty
        if (empty($this->currentTitle)) {
            return false;
        }

        // Create a cache key for this search
        $cacheKey = 'local_imdb_'.md5($this->currentTitle.$this->currentYear);

        // Check cache first
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        // Build the base query
        $query = MovieInfo::query()
            ->select(['imdbid', 'title'])
            ->where('title', 'like', '%'.$this->currentTitle.'%');

        // Add year range filter if we have a year
        if (! empty($this->currentYear)) {
            $start = Carbon::createFromFormat('Y', $this->currentYear)->subYears(2)->year;
            $end = Carbon::createFromFormat('Y', $this->currentYear)->addYears(2)->year;
            $query->whereBetween('year', [$start, $end]);
        }

        // Get potential matches in a single query
        $potentialMatches = $query->get();

        // If no matches found, cache the failure and return false
        if ($potentialMatches->isEmpty()) {
            Cache::put($cacheKey, false, now()->addHours(6));

            return false;
        }

        // Check each potential match for title similarity
        foreach ($potentialMatches as $match) {
            similar_text($this->currentTitle, $match['title'], $percent);

            if ($percent >= self::MATCH_PERCENT) {
                // Found a good match, cache it and return
                Cache::put($cacheKey, $match['imdbid'], now()->addDays(7));

                if ($this->echooutput) {
                    $this->colorCli->climate()->info("Found local match: {$match['title']} ({$match['imdbid']})");
                }

                return $match['imdbid'];
            }
        }

        // No good matches found, cache the failure
        Cache::put($cacheKey, false, now()->addHours(6));

        return false;
    }

    /**
     * Parse a movie title and year from a release search name.
     *
     * This method attempts to extract a clean movie title and year from scene release names
     * which often contain quality indicators, source information, and other metadata.
     *
     * @param  string  $releaseName  The raw release name to parse
     * @return bool True if parsing was successful, false otherwise
     */
    protected function parseMovieSearchName(string $releaseName): bool
    {
        // Skip empty release names
        if (empty(trim($releaseName))) {
            return false;
        }

        // Create a cache key for this parsing operation
        $cacheKey = 'parse_movie_'.md5($releaseName);

        // Check if we have a cached result
        if (Cache::has($cacheKey)) {
            $result = Cache::get($cacheKey);
            if (is_array($result)) {
                $this->currentTitle = $result['title'];
                $this->currentYear = $result['year'];

                return true;
            }

            return false;
        }

        $name = $year = '';

        // Common movie quality, format, and release group patterns to identify boundaries
        $followingList = '[^\w]((1080|480|720|2160)p|AC3D|Directors([^\w]CUT)?|DD5\.1|(DVD|BD|BR|UHD)(Rip)?|'
            .'BluRay|divx|HDTV|iNTERNAL|LiMiTED|(Real\.)?PROPER|RE(pack|Rip)|Sub\.?(fix|pack)|'
            .'Unrated|WEB-?DL|WEBRip|(x|H|HEVC)[ ._-]?26[45]|xvid|AAC|REMUX)[^\w]';

        // First attempt: Find pattern with year - most reliable method
        if (preg_match('/(?P<name>[\w. -]+)[^\w](?P<year>(19|20)\d\d)/i', $releaseName, $hits)) {
            $name = $hits['name'];
            $year = $hits['year'];
        }
        // Second attempt: Look for title followed by common release identifiers
        elseif (preg_match('/([^\w]{2,})?(?P<name>[\w .-]+?)'.$followingList.'/i', $releaseName, $hits)) {
            $name = $hits['name'];
        }
        // Third attempt: Try to match the start of the string up to a pattern
        elseif (preg_match('/^(?P<name>[\w .-]+?)'.$followingList.'/i', $releaseName, $hits)) {
            $name = $hits['name'];
        }
        // Fourth attempt: Check if the whole string might be a title (for simple releases)
        elseif (strlen($releaseName) <= 100 && ! preg_match('/\.(rar|zip|avi|mkv|mp4)$/i', $releaseName)) {
            $name = $releaseName;
        }

        // Process the name if we have one
        if (! empty($name)) {
            // Clean up the name by removing common patterns
            // 1. Remove any common movie flags like 1080p, BluRay, etc.
            $name = preg_replace('/'.$followingList.'/i', ' ', $name);

            // 2. Remove content in parentheses, brackets, and periods/underscores
            $name = preg_replace('/\(.*?\)|\[.*?\]|[._]/i', ' ', $name);

            // 3. Remove scene group names (typically after a hyphen)
            $name = preg_replace('/-[A-Z0-9].*$/i', '', $name);

            // 4. Clean up multiple spaces
            $name = trim(preg_replace('/\s{2,}/', ' ', $name));

            // Validate the extracted name (at least 2 characters, not just numbers)
            if (strlen($name) > 2 && ! preg_match('/^\d+$/', $name)) {
                $this->currentTitle = $name;
                $this->currentYear = $year;

                // Cache the successful result
                Cache::put($cacheKey, [
                    'title' => $name,
                    'year' => $year,
                ], now()->addDays(7));

                return true;
            }
        }

        // Cache the failed result but with shorter expiration
        Cache::put($cacheKey, false, now()->addHours(24));

        return false;
    }

    /**
     * Process movie data for frontend display - moves heavy processing from template to backend
     */
    public function processMovieDataForDisplay(array $movies): array
    {
        $processedMovies = [];

        foreach ($movies as $movie) {
            $processedMovie = (array) $movie;

            // Process all the comma/delimiter-separated fields that were being processed in the template
            $processedMovie['releases'] = $this->parseMovieReleaseData($movie);

            // Process genre tags for display (genre field links are already processed in controller)
            $processedMovie['genre_tags'] = [];
            if (! empty($processedMovie['genre'])) {
                // Extract original genre text from the field links for tag display
                $genreText = strip_tags($processedMovie['genre']);
                $processedMovie['genre_tags'] = explode(', ', $genreText);
            }

            // Languages are already processed in the controller, no need to duplicate

            $processedMovies[] = $processedMovie;
        }

        return $processedMovies;
    }

    /**
     * Parse individual movie release data for frontend display
     */
    private function parseMovieReleaseData($movie): array
    {
        $releases = [];

        // Convert to array if it's an object to ensure consistent access
        $movieData = (array) $movie;

        // Split all the grouped release data - use array access instead of object notation
        $releaseIds = ! empty($movieData['grp_release_id']) ? explode(',', $movieData['grp_release_id']) : [];
        $releaseGuids = ! empty($movieData['grp_release_guid']) ? explode(',', $movieData['grp_release_guid']) : [];
        $releaseNfos = ! empty($movieData['grp_release_nfoid']) ? explode(',', $movieData['grp_release_nfoid']) : [];
        $releaseGroups = ! empty($movieData['grp_release_grpname']) ? explode(',', $movieData['grp_release_grpname']) : [];
        $releaseNames = ! empty($movieData['grp_release_name']) ? explode('#', $movieData['grp_release_name']) : [];
        $releasePostdates = ! empty($movieData['grp_release_postdate']) ? explode(',', $movieData['grp_release_postdate']) : [];
        $releaseSizes = ! empty($movieData['grp_release_size']) ? explode(',', $movieData['grp_release_size']) : [];
        $releaseTotalparts = ! empty($movieData['grp_release_totalparts']) ? explode(',', $movieData['grp_release_totalparts']) : [];
        $releaseComments = ! empty($movieData['grp_release_comments']) ? explode(',', $movieData['grp_release_comments']) : [];
        $releaseGrabs = ! empty($movieData['grp_release_grabs']) ? explode(',', $movieData['grp_release_grabs']) : [];
        $releaseFailed = ! empty($movieData['grp_release_failed']) ? explode(',', $movieData['grp_release_failed']) : [];
        $releasePasswords = ! empty($movieData['grp_release_password']) ? explode(',', $movieData['grp_release_password']) : [];
        $releaseInnerfiles = ! empty($movieData['grp_rarinnerfilecount']) ? explode(',', $movieData['grp_rarinnerfilecount']) : [];
        $releaseHaspreview = ! empty($movieData['grp_haspreview']) ? explode(',', $movieData['grp_haspreview']) : [];

        // Build structured release data
        $maxCount = max(
            count($releaseIds),
            count($releaseGuids),
            count($releaseNames)
        );

        for ($i = 0; $i < $maxCount; $i++) {
            $releases[] = [
                'id' => $releaseIds[$i] ?? '',
                'guid' => $releaseGuids[$i] ?? '',
                'nfo_id' => $releaseNfos[$i] ?? '',
                'group_name' => $releaseGroups[$i] ?? '',
                'name' => $releaseNames[$i] ?? '',
                'postdate' => $releasePostdates[$i] ?? '',
                'size' => $releaseSizes[$i] ?? '',
                'size_formatted' => ! empty($releaseSizes[$i]) ? $this->formatFileSize($releaseSizes[$i]) : '',
                'totalparts' => $releaseTotalparts[$i] ?? '',
                'comments' => $releaseComments[$i] ?? '',
                'grabs' => $releaseGrabs[$i] ?? '',
                'failed' => $releaseFailed[$i] ?? '',
                'password_status' => $releasePasswords[$i] ?? '',
                'inner_files' => $releaseInnerfiles[$i] ?? '',
                'has_preview' => $releaseHaspreview[$i] ?? '',
                'postdate_formatted' => ! empty($releasePostdates[$i]) ? $this->formatTimeAgo($releasePostdates[$i]) : '',
            ];
        }

        return $releases;
    }

    /**
     * Format file size for display
     */
    private function formatFileSize($bytes): string
    {
        if (! is_numeric($bytes)) {
            return $bytes;
        }

        $bytes = (int) $bytes;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2).' '.$units[$i];
    }

    /**
     * Format timestamp to time ago
     */
    private function formatTimeAgo($timestamp): string
    {
        try {
            $date = Carbon::parse($timestamp);

            return $date->diffForHumans();
        } catch (\Exception $e) {
            return $timestamp;
        }
    }

    /**
     * Get all unique genres from the movieinfo table
     */
    public function getGenres(): array
    {
        $cacheKey = 'movie_genres_list';
        $expiresAt = now()->addHours(24); // Cache for 24 hours

        return Cache::remember($cacheKey, $expiresAt, function () {
            $genres = MovieInfo::whereNotNull('genre')
                ->where('genre', '!=', '')
                ->pluck('genre')
                ->flatMap(function ($genre) {
                    return explode(', ', $genre);
                })
                ->map(function ($genre) {
                    return trim($genre);
                })
                ->filter(function ($genre) {
                    return ! empty($genre);
                })
                ->unique()
                ->sort()
                ->values()
                ->toArray();

            return $genres;
        });
    }
}

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

        $this->lookuplanguage = Settings::settingValue('indexer.categorise.imdblanguage') !== '' ? (string) Settings::settingValue('indexer.categorise.imdblanguage') : 'en';
        $this->config = new Config;
        $this->config->language = $this->lookuplanguage;
        $this->config->throwHttpExceptions = false;
        $cacheDir = storage_path('framework/cache/imdb_cache');
        if (! File::isDirectory($cacheDir)) {
            File::makeDirectory($cacheDir, 0777, false, true);
        }
        $this->config->cachedir = $cacheDir;

        $this->imdburl = (int) Settings::settingValue('indexer.categorise.imdburl') !== 0;
        $this->movieqty = Settings::settingValue('..maximdbprocessed') !== '' ? (int) Settings::settingValue('..maximdbprocessed') : 100;
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
     * Order types for movies page.
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
                if (! \is_array($val) && ! \is_object($val)) {
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
        if (! \count($values)) {
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
     * Fetch info for IMDB id from TMDB.
     *
     *
     * @return array|false
     */
    public function fetchTMDBProperties($imdbId, bool $text = false)
    {
        $lookupId = $text === false && (\strlen($imdbId) === 7 || strlen($imdbId) === 8) ? 'tt'.$imdbId : $imdbId;

        try {
            $tmdbLookup = Tmdb::getMoviesApi()->getMovie($lookupId, ['append_to_response' => 'credits']);
        } catch (TmdbApiException|\ErrorException $error) {
            return false;
        }

        if (! empty($tmdbLookup)) {
            if ($this->currentTitle !== '') {
                // Check the similarity.
                similar_text($this->currentTitle, $tmdbLookup['title'], $percent);
                if ($percent < self::MATCH_PERCENT) {
                    return false;
                }
            }

            if ($this->currentYear !== '') {
                // Check the similarity.
                similar_text($this->currentYear, Carbon::parse($tmdbLookup['release_date'])->year, $percent);
                if ($percent < self::YEAR_MATCH_PERCENT) {
                    return false;
                }
            }

            $ret = [];
            $ret['title'] = $tmdbLookup['title'];

            $ret['tmdbid'] = $tmdbLookup['id'];
            $ret['imdbid'] = str_replace('tt', '', $tmdbLookup['imdb_id']);
            $vote = $tmdbLookup['vote_average'];
            if ($vote !== null) {
                $ret['rating'] = (int) $vote === 0 ? '' : $vote;
            } else {
                $ret['rating'] = '';
            }
            $actors = Arr::pluck($tmdbLookup['credits']['cast'], 'name');
            if (! empty($actors)) {
                $ret['actors'] = $actors;
            } else {
                $ret['actors'] = '';
            }
            foreach ($tmdbLookup['credits']['crew'] as $crew) {
                if ($crew['department'] === 'Directing' && $crew['job'] === 'Director') {
                    $ret['director'] = $crew['name'];
                }
            }
            $overview = $tmdbLookup['overview'];
            if (! empty($overview)) {
                $ret['plot'] = $overview;
            } else {
                $ret['plot'] = '';
            }
            $tagline = $tmdbLookup['tagline'];

            $ret['tagline'] = $tagline ?? '';

            $released = $tmdbLookup['release_date'];
            if (! empty($released)) {
                $ret['year'] = Carbon::parse($released)->year;
            } else {
                $ret['year'] = '';
            }
            $genresa = $tmdbLookup['genres'];
            if (! empty($genresa) && \count($genresa) > 0) {
                $genres = [];
                foreach ($genresa as $genre) {
                    $genres[] = $genre['name'];
                }
                $ret['genre'] = $genres;
            } else {
                $ret['genre'] = '';
            }
            $posterp = $tmdbLookup['poster_path'];
            if (! empty($posterp)) {
                $ret['cover'] = 'https://image.tmdb.org/t/p/original'.$posterp;
            } else {
                $ret['cover'] = '';
            }
            $backdrop = $tmdbLookup['backdrop_path'];
            if (! empty($backdrop)) {
                $ret['backdrop'] = 'https://image.tmdb.org/t/p/original'.$backdrop;
            } else {
                $ret['backdrop'] = '';
            }
            if ($this->echooutput) {
                $this->colorCli->climate()->info('TMDb found '.$ret['title']);
            }

            return $ret;
        }

        return false;
    }

    /**
     * @return array|false
     */
    public function fetchIMDBProperties($imdbId)
    {
        $realId = (new Title($imdbId, $this->config))->real_id();
        $result = new Title($realId, $this->config);
        $title = ! empty($result->orig_title()) ? $result->orig_title() : $result->title();
        if (! empty($title)) {
            if (! empty($this->currentTitle)) {
                similar_text($this->currentTitle, $title, $percent);
                if ($percent >= self::MATCH_PERCENT) {
                    similar_text($this->currentYear, $result->year(), $percent);
                    if ($percent >= self::YEAR_MATCH_PERCENT) {
                        $ret = [
                            'title' => $title,
                            'tagline' => $result->tagline() ?? '',
                            'plot' => Arr::get($result->plot_split(), '0.plot'),
                            'rating' => ! empty($result->rating()) ? $result->rating() : '',
                            'year' => $result->year() ?? '',
                            'cover' => $result->photo() ?? '',
                            'genre' => $result->genre() ?? '',
                            'language' => $result->language() ?? '',
                            'type' => $result->movietype() ?? '',
                        ];

                        if ($this->echooutput) {
                            $this->colorCli->climate()->info('IMDb found '.$title);
                        }

                        return $ret;
                    }

                    return false;
                }

                return false;
            }

            return [
                'title' => $title,
                'tagline' => $result->tagline() ?? '',
                'plot' => Arr::get($result->plot_split(), '0.plot'),
                'rating' => ! empty($result->rating()) ? $result->rating() : '',
                'year' => $result->year() ?? '',
                'cover' => $result->photo() ?? '',
                'genre' => $result->genre() ?? '',
                'language' => $result->language() ?? '',
                'type' => $result->movietype() ?? '',
            ];
        }

        return false;
    }

    /**
     * Fetch TraktTV backdrop / cover / title.
     *
     * @return array|false
     *
     * @throws \Exception
     * @throws GuzzleException
     */
    public function fetchTraktTVProperties($imdbId)
    {
        if ($this->traktcheck !== null) {
            $resp = $this->traktTv->client->movieSummary('tt'.$imdbId, 'full');
            if ($resp !== false) {
                similar_text($this->currentTitle, $resp['title'], $percent);
                if ($percent >= self::MATCH_PERCENT) {
                    similar_text($this->currentYear, $resp['year'], $percent);
                    if ($percent >= self::YEAR_MATCH_PERCENT) {
                        $ret = [];
                        if (isset($resp['ids']['trakt'])) {
                            $ret['id'] = $resp['ids']['trakt'];
                        }

                        $ret['overview'] = $resp['overview'] ?? '';
                        $ret['tagline'] = $resp['tagline'] ?? '';
                        $ret['year'] = $resp['year'] ?? '';
                        $ret['genres'] = $resp['genres'] ?? '';

                        if (isset($resp['title'])) {
                            $ret['title'] = $resp['title'];
                        } else {
                            return false;
                        }

                        if ($this->echooutput) {
                            $this->colorCli->climate()->info('Trakt found '.$ret['title']);
                        }

                        return $ret;
                    }

                    return false;
                }

                return false;
            }

            return false;
        }

        return false;
    }

    /**
     * Fetch OMDb backdrop / cover / title.
     *
     * @return array|false
     */
    public function fetchOmdbAPIProperties($imdbId)
    {
        if ($this->omdbapikey !== null) {
            $resp = $this->omdbApi->fetch('i', 'tt'.$imdbId);

            if (\is_object($resp) && $resp->message === 'OK' && ! Str::contains($resp->data->Response, 'Error:') && $resp->data->Response !== 'False') {
                similar_text($this->currentTitle, $resp->data->Title, $percent);
                if ($percent >= self::MATCH_PERCENT) {
                    similar_text($this->currentYear, $resp->data->Year, $percent);
                    if ($percent >= self::YEAR_MATCH_PERCENT) {
                        $ret = [
                            'title' => $resp->data->Title ?? '',
                            'cover' => $resp->data->Poster ?? '',
                            'genre' => $resp->data->Genre ?? '',
                            'year' => $resp->data->Year ?? '',
                            'plot' => $resp->data->Plot ?? '',
                            'rating' => $resp->data->imdbRating ?? '',
                            'rtRating' => $resp->data->Ratings[1]->Value ?? '',
                            'tagline' => $resp->data->Tagline ?? '',
                            'director' => $resp->data->Director ?? '',
                            'actors' => $resp->data->Actors ?? '',
                            'language' => $resp->data->Language ?? '',
                            'boxOffice' => $resp->data->BoxOffice ?? '',
                        ];

                        if ($this->echooutput) {
                            $this->colorCli->climate()->info('OMDbAPI Found '.$ret['title']);
                        }

                        return $ret;
                    }

                    return false;
                }

                return false;
            }

            return false;
        }

        return false;
    }

    /**
     * @return array|bool
     *
     * @throws InvalidProviderException
     * @throws \Exception
     */
    public function fetchItunesMovieProperties(string $title)
    {
        $movie = true;
        try {
            $iTunesMovie = iTunes::load('movie')->fetchOneByName($title);
        } catch (MovieNotFoundException $e) {
            $movie = false;
        } catch (SearchNoResultsException $e) {
            $movie = false;
        }

        if ($movie !== false) {
            similar_text($this->currentTitle, $iTunesMovie->getName(), $percent);
            if ($percent >= self::MATCH_PERCENT) {
                $movie = [
                    'title' => $iTunesMovie->getName(),
                    'director' => $iTunesMovie->getDirector() ?? '',
                    'tagline' => $iTunesMovie->getTagLine() ?? '',
                    'cover' => str_replace('100x100', '800x800', $iTunesMovie->getCover()),
                    'genre' => $iTunesMovie->getGenre() ?? '',
                    'plot' => $iTunesMovie->getDescription() ?? '',
                    'year' => $iTunesMovie->getReleaseDate() ? $iTunesMovie->getReleaseDate()->format('Y') : '',
                ];
            } else {
                $movie = false;
            }
        }

        return $movie;
    }

    /**
     * Update a release with a IMDB id.
     *
     * @param  string  $buffer  Data to parse a IMDB id/Trakt Id from.
     * @param  string  $service  Method that called this method.
     * @param  int  $id  id of the release.
     * @param  int  $processImdb  To get IMDB info on this IMDB id or not.
     *
     * @throws \Exception
     */
    public function doMovieUpdate(string $buffer, string $service, int $id, int $processImdb = 1): string
    {
        $imdbId = false;
        if (preg_match('/(?:imdb.*?)?(?:tt|Title\?)(?P<imdbid>\d{5,8})/i', $buffer, $hits)) {
            $imdbId = $hits['imdbid'];
        }

        if ($imdbId !== false) {
            $this->service = $service;
            if ($this->echooutput && $this->service !== '') {
                $this->colorCli->climate()->info($this->service.' found IMDBid: tt'.$imdbId);
            }

            $movieInfoId = MovieInfo::query()->where('imdbid', $imdbId)->first(['id']);

            Release::query()->where('id', $id)->update(['imdbid' => $imdbId, 'movieinfo_id' => $movieInfoId !== null ? $movieInfoId['id'] : null]);

            // If set, scan for imdb info.
            if ($processImdb === 1) {
                $movCheck = $this->getMovieInfo($imdbId);
                if ($movCheck === null || (isset($movCheck['updated_at']) && (time() - strtotime($movCheck['updated_at'])) > 2592000)) {
                    $info = $this->updateMovieInfo($imdbId);
                    if ($info === false) {
                        Release::query()->where('id', $id)->update(['imdbid' => 0000000]);
                    } elseif ($info === true) {
                        $movieInfoId = MovieInfo::query()->where('imdbid', $imdbId)->first(['id']);

                        Release::query()->where('id', $id)->update(['imdbid' => $imdbId, 'movieinfo_id' => $movieInfoId !== null ? $movieInfoId['id'] : null]);
                    }
                }
            }
        }

        return $imdbId;
    }

    /**
     * Process releases with no IMDB id's.
     *
     *
     *
     * @throws \Exception
     * @throws GuzzleException
     */
    public function processMovieReleases(string $groupID = '', string $guidChar = '', int $lookupIMDB = 1): void
    {
        if ($lookupIMDB === 0) {
            return;
        }

        // Get all releases without an IMDB id.
        $sql = Release::query()
            ->select(['searchname', 'id'])
            ->whereBetween('categories_id', [Category::MOVIE_ROOT, Category::MOVIE_OTHER])
            ->whereNull('imdbid')
            ->where('nzbstatus', '=', 1);
        if ($groupID !== '') {
            $sql->where('groups_id', $groupID);
        }

        if ($guidChar !== '') {
            $sql->where('leftguid', $guidChar);
        }

        if ((int) $lookupIMDB === 2) {
            $sql->where('isrenamed', '=', 1);
        }

        $res = $sql->limit($this->movieqty)->get();

        $movieCount = \count($res);

        if ($movieCount > 0) {
            if ($this->echooutput && $movieCount > 1) {
                $this->colorCli->header('Processing '.$movieCount.' movie releases.');
            }

            // Loop over releases.
            foreach ($res as $arr) {
                // Try to get a name/year.
                if (! $this->parseMovieSearchName($arr['searchname'])) {
                    //We didn't find a name, so set to all 0's so we don't parse again.
                    Release::query()->where('id', $arr['id'])->update(['imdbid' => 0000000]);

                    continue;
                }
                $this->currentRelID = $arr['id'];

                $movieName = $this->currentTitle;
                if ($this->currentYear !== '') {
                    $movieName .= ' ('.$this->currentYear.')';
                }

                if ($this->echooutput) {
                    $this->colorCli->climate()->info('Looking up: '.$movieName);
                }

                $movieUpdated = false;

                // Check local DB.
                $getIMDBid = $this->localIMDBSearch();

                if ($getIMDBid !== false) {
                    $imdbId = $this->doMovieUpdate('tt'.$getIMDBid, 'Local DB', $arr['id']);
                    if ($imdbId !== false) {
                        $movieUpdated = true;
                    }
                }

                // Check on IMDb first
                if ($movieUpdated === false) {
                    try {
                        $imdbSearch = new TitleSearch($this->config);
                        foreach ($imdbSearch->search($this->currentTitle, [TitleSearch::MOVIE]) as $imdbTitle) {
                            if (! empty($imdbTitle->title())) {
                                similar_text($imdbTitle->title(), $this->currentTitle, $percent);
                                if ($percent >= self::MATCH_PERCENT) {
                                    similar_text($this->currentYear, $imdbTitle->year(), $percent);
                                    if ($percent >= self::YEAR_MATCH_PERCENT) {
                                        $getIMDBid = $imdbTitle->imdbid();
                                        $imdbId = $this->doMovieUpdate('tt'.$getIMDBid, 'IMDb', $arr['id']);
                                        if ($imdbId !== false) {
                                            $movieUpdated = true;
                                        }
                                    }
                                }
                            }
                        }
                    } catch (\ErrorException $e) {
                        $this->colorCli->error('Error fetching data from imdb occurred', true);
                        Log::debug($e->getMessage());
                    }
                }

                // Check on OMDbAPI
                if ($movieUpdated === false) {
                    $omdbTitle = strtolower(str_replace(' ', '_', $this->currentTitle));
                    if ($this->omdbapikey !== null) {
                        if ($this->currentYear !== '') {
                            $buffer = $this->omdbApi->search($omdbTitle, 'movie', $this->currentYear);
                        } else {
                            $buffer = $this->omdbApi->search($omdbTitle, 'movie');
                        }

                        if (\is_object($buffer) && $buffer->message === 'OK' && ! Str::contains($buffer->data->Response, 'Error:') && $buffer->data->Response === 'True') {
                            $getIMDBid = $buffer->data->Search[0]->imdbID;

                            if (! empty($getIMDBid)) {
                                $imdbId = $this->doMovieUpdate($getIMDBid, 'OMDbAPI', $arr['id']);
                                if ($imdbId !== false) {
                                    $movieUpdated = true;
                                }
                            }
                        }
                    }
                }

                // Check on Trakt.
                if ($movieUpdated === false && $this->traktcheck !== null) {
                    $data = $this->traktTv->client->movieSummary($movieName, 'full');
                    if ($data !== false) {
                        $this->parseTraktTv($data);
                        if (! empty($data['ids']['imdb'])) {
                            $imdbId = $this->doMovieUpdate($data['ids']['imdb'], 'Trakt', $arr['id']);
                            if ($imdbId !== false) {
                                $movieUpdated = true;
                            }
                        }
                    }
                }

                // Check on The Movie Database.
                if ($movieUpdated === false) {
                    try {
                        $data = Tmdb::getSearchApi()->searchMovies($this->currentTitle);
                        if (($data['total_results'] > 0) && ! empty($data['results'])) {
                            foreach ($data['results'] as $result) {
                                if (! empty($result['id']) && ! empty($result['release_date'])) {
                                    similar_text($this->currentYear, Carbon::parse($result['release_date'])->year, $percent);
                                    if ($percent >= self::YEAR_MATCH_PERCENT) {
                                        $ret = $this->fetchTMDBProperties($result['id'], true);
                                        if ($ret !== false && ! empty($ret['imdbid'])) {
                                            $imdbId = $this->doMovieUpdate('tt'.$ret['imdbid'], 'TMDB', $arr['id']);
                                            if ($imdbId !== false) {
                                                $movieUpdated = true;
                                            }
                                        }
                                    }
                                } else {
                                    $movieUpdated = false;
                                }
                            }
                        } else {
                            $movieUpdated = false;
                        }
                    } catch (TmdbApiException|\ErrorException $error) {
                        $movieUpdated = false;
                    }
                }

                // We failed to get an IMDB id from all sources.
                if ($movieUpdated === false) {
                    Release::query()->where('id', $arr['id'])->update(['imdbid' => 0000000]);
                }
            }
        }
    }

    /**
     * @return false|mixed
     */
    protected function localIMDBSearch()
    {
        //If we found a year, try looking in a 4 year range.
        $check = MovieInfo::query()
            ->where('title', 'like', '%'.$this->currentTitle.'%');

        if ($this->currentYear !== '') {
            $start = Carbon::createFromFormat('Y', $this->currentYear)->subYears(2)->year;
            $end = Carbon::createFromFormat('Y', $this->currentYear)->addYears(2)->year;
            $check->whereBetween('year', [$start, $end]);
        }
        $IMDBCheck = $check->get(['imdbid']);
        foreach ($IMDBCheck as $check) {
            // match the title and year of the movie as close as possible.
            if ($this->currentYear !== '') {
                $IMDBCheck = MovieInfo::query()
                    ->where('imdbid', $check['imdbid'])
                    ->where('title', 'like', '%'.$this->currentTitle.'%')
                    ->whereBetween('year', [$start, $end])
                    ->first(['imdbid', 'title']);
            } else {
                $IMDBCheck = MovieInfo::query()
                    ->where('imdbid', $check['imdbid'])
                    ->where('title', 'like', '%'.$this->currentTitle.'%')
                    ->first(['imdbid', 'title']);
            }
            // If we found a match, check if percentage is high enough. If so, return the IMDB id.
            if ($IMDBCheck !== null) {
                similar_text($this->currentTitle, $IMDBCheck['title'], $percent);
                if ($percent >= self::MATCH_PERCENT) {
                    return $IMDBCheck['imdbid'];
                }
            }
        }

        return false;
    }

    /**
     * Parse a movie name from a release search name.
     */
    protected function parseMovieSearchName(string $releaseName): bool
    {
        $name = $year = '';
        $followingList = '[^\w]((1080|480|720)p|AC3D|Directors([^\w]CUT)?|DD5\.1|(DVD|BD|BR)(Rip)?|BluRay|divx|HDTV|iNTERNAL|LiMiTED|(Real\.)?Proper|RE(pack|Rip)|Sub\.?(fix|pack)|Unrated|WEB-DL|(x|H)[ ._-]?264|xvid)[^\w]';

        /* Initial scan of getting a year/name.
         * [\w. -]+ Gets 0-9a-z. - characters, most scene movie titles contain these chars.
         * ie: [61420]-[FULL]-[a.b.foreignEFNet]-[ Coraline.2009.DUTCH.INTERNAL.1080p.BluRay.x264-VeDeTT ]-[21/85] - "vedett-coralien-1080p.r04" yEnc
         * Then we look up the year, (19|20)\d\d, so $hits[1] would be Coraline $hits[2] 2009
         */
        if (preg_match('/(?P<name>[\w. -]+)[^\w](?P<year>(19|20)\d\d)/i', $releaseName, $hits)) {
            $name = $hits['name'];
            $year = $hits['year'];

            /* If we didn't find a year, try to get a name anyways.
             * Try to look for a title before the $followingList and after anything but a-z0-9 two times or more (-[ for example)
             */
        } elseif (preg_match('/([^\w]{2,})?(?P<name>[\w .-]+?)'.$followingList.'/i', $releaseName, $hits)) {
            $name = $hits['name'];
        }

        // Check if we got something.
        if ($name !== '') {
            // If we still have any of the words in $followingList, remove them.
            $name = preg_replace('/'.$followingList.'/i', ' ', $name);
            // Remove periods, underscored, anything between parenthesis.
            $name = preg_replace('/\(.*?\)|[._]/i', ' ', $name);
            // Finally remove multiple spaces and trim leading spaces.
            $name = trim(preg_replace('/\s{2,}/', ' ', $name));
            // Check if the name is long enough and not just numbers.
            if (\strlen($name) > 4 && ! preg_match('/^\d+$/', $name)) {
                $this->currentTitle = $name;
                $this->currentYear = $year;

                return true;
            }
        }

        return false;
    }

    /**
     * Get IMDB genres.
     */
    public function getGenres(): array
    {
        return [
            'Action',
            'Adventure',
            'Animation',
            'Biography',
            'Comedy',
            'Crime',
            'Documentary',
            'Drama',
            'Family',
            'Fantasy',
            'Film-Noir',
            'Game-Show',
            'History',
            'Horror',
            'Music',
            'Musical',
            'Mystery',
            'News',
            'Reality-TV',
            'Romance',
            'Sci-Fi',
            'Sport',
            'Talk-Show',
            'Thriller',
            'War',
            'Western',
        ];
    }
}

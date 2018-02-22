<?php

namespace Blacklight;

use Imdb\Title;
use Imdb\Config;
use Tmdb\ApiToken;
use aharen\OMDbAPI;
use Blacklight\db\DB;
use GuzzleHttp\Client;
use App\Models\Release;
use App\Models\Category;
use App\Models\Settings;
use Imdb\Exception\Http;
use App\Models\MovieInfo;
use Tmdb\Helper\ImageHelper;
use Illuminate\Support\Carbon;
use Tmdb\Client as TmdbClient;
use Blacklight\utility\Utility;
use Blacklight\libraries\FanartTV;
use Tmdb\Exception\TmdbApiException;
use Blacklight\processing\tv\TraktTv;
use Illuminate\Support\Facades\Cache;
use GuzzleHttp\Exception\RequestException;
use Tmdb\Repository\ConfigurationRepository;

/**
 * Class Movie.
 */
class Movie
{
    protected const MATCH_PERCENT = 75;

    protected const YEAR_MATCH_PERCENT = 80;

    /**
     * @var \Blacklight\db\DB
     */
    public $pdo;

    /**
     * Current title being passed through various sites/api's.
     * @var string
     */
    protected $currentTitle = '';

    /**
     * Current year of parsed search name.
     * @var string
     */
    protected $currentYear = '';

    /**
     * Current release id of parsed search name.
     *
     * @var string
     */
    protected $currentRelID = '';

    /**
     * Use search engines to find IMDB id's.
     * @var bool
     */
    protected $searchEngines;

    /**
     * How many times have we hit google this session.
     * @var int
     */
    protected $googleLimit = 0;

    /**
     * If we are temp banned from google, set time we were banned here, try again after 10 minutes.
     * @var int
     */
    protected $googleBan = 0;

    /**
     * How many times have we hit bing this session.
     *
     * @var int
     */
    protected $bingLimit = 0;

    /**
     * How many times have we hit yahoo this session.
     *
     * @var int
     */
    protected $yahooLimit = 0;

    /**
     * How many times have we hit duckduckgo this session.
     *
     * @var int
     */
    protected $duckduckgoLimit = 0;

    /**
     * @var string
     */
    protected $showPasswords;

    /**
     * @var \Blacklight\ReleaseImage
     */
    protected $releaseImage;

    /**
     * @var \Tmdb\Client
     */
    protected $tmdbclient;

    /**
     * @var \GuzzleHttp\Client
     */
    protected $client;

    /**
     * Language to fetch from IMDB.
     * @var string
     */
    protected $lookuplanguage;

    /**
     * @var \Blacklight\libraries\FanartTV
     */
    public $fanart;

    /**
     * @var null|string
     */
    public $fanartapikey;

    /**
     * @var null|string
     */
    public $omdbapikey;

    /**
     * @var bool
     */
    public $imdburl;

    /**
     * @var array|bool|int|string
     */
    public $movieqty;

    /**
     * @var bool
     */
    public $echooutput;

    /**
     * @var string
     */
    public $imgSavePath;

    /**
     * @var string
     */
    public $service;

    /**
     * @var \Tmdb\ApiToken
     */
    public $tmdbtoken;

    /**
     * @var null|TraktTv
     */
    public $traktTv;

    /**
     * @var OMDbAPI|null
     */
    public $omdbApi;

    /**
     * @var \Imdb\Config
     */
    private $config;

    /**
     * @var \Tmdb\Repository\ConfigurationRepository
     */
    protected $configRepository;

    /**
     * @var \Tmdb\Helper\ImageHelper
     */
    protected $helper;

    /**
     * @var \Tmdb\Model\Configuration
     */
    protected $tmdbconfig;

    /**
     * @param array $options Class instances / Echo to CLI.
     * @throws \Exception
     */
    public function __construct(array $options = [])
    {
        $defaults = [
            'Echo'         => false,
            'Logger'    => null,
            'ReleaseImage' => null,
            'Settings'     => null,
            'TMDb'         => null,
        ];
        $options += $defaults;

        $this->pdo = ($options['Settings'] instanceof DB ? $options['Settings'] : new DB());
        $this->releaseImage = ($options['ReleaseImage'] instanceof ReleaseImage ? $options['ReleaseImage'] : new ReleaseImage());
        $this->client = new Client();
        $this->tmdbtoken = new ApiToken(Settings::settingValue('APIs..tmdbkey'));
        $this->tmdbclient = new TmdbClient(
            $this->tmdbtoken,
            [
            'cache' => [
                'enabled' => false,
            ],
        ]
        );
        $this->configRepository = new ConfigurationRepository($this->tmdbclient);
        $this->tmdbconfig = $this->configRepository->load();
        $this->helper = new ImageHelper($this->tmdbconfig);
        $this->fanartapikey = Settings::settingValue('APIs..fanarttvkey');
        $this->fanart = new FanartTV($this->fanartapikey);
        $this->omdbapikey = Settings::settingValue('APIs..omdbkey');
        if ($this->omdbapikey !== null) {
            $this->omdbApi = new OMDbAPI($this->omdbapikey);
        }

        $this->lookuplanguage = Settings::settingValue('indexer.categorise.imdblanguage') !== '' ? (string) Settings::settingValue('indexer.categorise.imdblanguage') : 'en';
        $this->config = new Config();
        $this->config->language = $this->lookuplanguage;

        $this->imdburl = (int) Settings::settingValue('indexer.categorise.imdburl') !== 0;
        $this->movieqty = Settings::settingValue('..maximdbprocessed') !== '' ? (int) Settings::settingValue('..maximdbprocessed') : 100;
        $this->searchEngines = true;
        $this->showPasswords = Releases::showPasswords();

        $this->echooutput = ($options['Echo'] && env('echocli', true) && $this->pdo->cli);
        $this->imgSavePath = NN_COVERS.'movies'.DS;
        $this->service = '';
    }

    /**
     * @param $imdbId
     * @return array|bool|\Illuminate\Database\Eloquent\Model|null|static
     */
    public function getMovieInfo($imdbId)
    {
        return MovieInfo::query()->where('imdbid', $imdbId)->first();
    }

    /**
     * Get movie releases with covers for movie browse page.
     *
     * @param       $cat
     * @param       $start
     * @param       $num
     * @param       $orderBy
     * @param       $maxAge
     * @param array $excludedCats
     *
     * @return array|bool|\PDOStatement
     * @throws \Exception
     */
    public function getMovieRange($cat, $start, $num, $orderBy, $maxAge = -1, array $excludedCats = [])
    {
        $catsrch = '';
        if (\count($cat) > 0 && $cat[0] !== -1) {
            $catsrch = Category::getCategorySearch($cat);
        }

        $order = $this->getMovieOrder($orderBy);
        $expiresAt = Carbon::now()->addSeconds(NN_CACHE_EXPIRY_MEDIUM);

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
                        (\count($excludedCats) > 0 ? ' AND r.categories_id NOT IN ('.implode(',', $excludedCats).')' : ''),
                        $order[0],
                        $order[1],
                        ($start === false ? '' : ' LIMIT '.$num.' OFFSET '.$start)
                );
        $movieCache = Cache::get(md5($moviesSql));
        if ($movieCache !== null) {
            $movies = $movieCache;
        } else {
            $movies = $this->pdo->queryCalc($moviesSql);
            Cache::put(md5($moviesSql), $movies, $expiresAt);
        }

        $movieIDs = $releaseIDs = false;

        if (\is_array($movies['result'])) {
            foreach ($movies['result'] as $movie => $id) {
                $movieIDs[] = $id['imdbid'];
                $releaseIDs[] = $id['grp_release_id'];
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
			LEFT OUTER JOIN groups g ON g.id = r.groups_id
			LEFT OUTER JOIN release_nfos rn ON rn.releases_id = r.id
			LEFT OUTER JOIN dnzb_failures df ON df.release_id = r.id
			LEFT OUTER JOIN categories c ON c.id = r.categories_id
			LEFT OUTER JOIN categories cp ON cp.id = c.parentid
			INNER JOIN movieinfo m ON m.imdbid = r.imdbid
			WHERE m.imdbid IN (%s)
			AND r.id IN (%s) %s
			GROUP BY m.imdbid
			ORDER BY %s %s",
                (\is_array($movieIDs) ? implode(',', $movieIDs) : -1),
                (\is_array($releaseIDs) ? implode(',', $releaseIDs) : -1),
                (! empty($catsrch) ? $catsrch : ''),
                $order[0],
                $order[1]
        );
        $return = Cache::get(md5($sql));
        if ($return !== null) {
            return $return;
        }
        $return = $this->pdo->query($sql);
        if (! empty($return)) {
            $return[0]['_totalcount'] = $movies['total'] ?? 0;
        }

        Cache::put(md5($sql), $return, $expiresAt);

        return $return;
    }

    /**
     * Get the order type the user requested on the movies page.
     *
     * @param $orderBy
     *
     * @return array
     */
    protected function getMovieOrder($orderBy): array
    {
        $orderArr = explode('_', (($orderBy === '') ? 'MAX(r.postdate)' : $orderBy));
        switch ($orderArr[0]) {
            case 'title':
                $orderField = 'm.title';
                break;
            case 'year':
                $orderField = 'm.year';
                break;
            case 'rating':
                $orderField = 'm.rating';
                break;
            case 'posted':
            default:
                $orderField = 'MAX(r.postdate)';
                break;
        }

        return [$orderField, isset($orderArr[1]) && preg_match('/^asc|desc$/i', $orderArr[1]) ? $orderArr[1] : 'desc'];
    }

    /**
     * Order types for movies page.
     *
     * @return array
     */
    public function getMovieOrdering(): array
    {
        return ['title_asc', 'title_desc', 'year_asc', 'year_desc', 'rating_asc', 'rating_desc'];
    }

    /**
     * @return string
     */
    protected function getBrowseBy(): string
    {
        $browseBy = ' ';
        $browseByArr = ['title', 'director', 'actors', 'genre', 'rating', 'year', 'imdb'];
        foreach ($browseByArr as $bb) {
            if (isset($_REQUEST[$bb]) && ! empty($_REQUEST[$bb])) {
                $bbv = stripslashes($_REQUEST[$bb]);
                if ($bb === 'rating') {
                    $bbv .= '.';
                }
                if ($bb === 'imdb') {
                    $browseBy .= sprintf('AND m.%sid = %d', $bb, $bbv);
                } else {
                    $browseBy .= 'AND m.'.$bb.' '.$this->pdo->likeString($bbv, true, true);
                }
            }
        }

        return $browseBy;
    }

    /**
     * Get trailer using IMDB Id.
     *
     * @param int $imdbID
     *
     * @return bool|string
     * @throws \Exception
     */
    public function getTrailer($imdbID)
    {
        if (! is_numeric($imdbID)) {
            return false;
        }

        $trailer = MovieInfo::query()->where('imdbid', $imdbID)->where('trailer', '!=', '')->first(['trailer']);
        if ($trailer !== null) {
            return $trailer['trailer'];
        }

        if ($this->traktTv === null) {
            $this->traktTv = new TraktTv(['Settings' => $this->pdo]);
        }

        $data = $this->traktTv->client->movieSummary('tt'.$imdbID, 'full');
        if ($data) {
            $this->parseTraktTv($data);
            if (! empty($data['trailer'])) {
                return $data['trailer'];
            }
        }

        $trailer = Utility::imdb_trailers($imdbID);
        if ($trailer) {
            MovieInfo::query()->where('imdbid', $imdbID)->update(['trailer' => $trailer]);

            return $trailer;
        }

        return false;
    }

    /**
     * Parse trakt info, insert into DB.
     *
     * @param array $data
     *
     * @return mixed
     */
    public function parseTraktTv(&$data)
    {
        if (empty($data['ids']['imdb'])) {
            return false;
        }

        if (! empty($data['trailer'])) {
            $data['trailer'] = str_ireplace(
                'http://',
                'https://',
                str_ireplace('watch?v=', 'embed/', $data['trailer'])
            );

            return $data['trailer'];
        }
        $imdbid = (strpos($data['ids']['imdb'], 'tt') === 0) ? substr($data['ids']['imdb'], 2) : $data['ids']['imdb'];
        $cover = 0;
        if (is_file($this->imgSavePath.$imdbid).'-cover.jpg') {
            $cover = 1;
        } else {
            $link = $this->checkTraktValue($data['images']['poster']['thumb']);
            if ($link) {
                $cover = $this->releaseImage->saveImage($imdbid.'-cover', $link, $this->imgSavePath);
            }
        }

        return $this->update([
            'genres'   => $this->checkTraktValue($data['genres']),
            'imdbid'   => $this->checkTraktValue($imdbid),
            'language' => $this->checkTraktValue($data['language']),
            'plot'     => $this->checkTraktValue($data['overview']),
            'rating'   => round($this->checkTraktValue($data['rating']), 1),
            'tagline'  => $this->checkTraktValue($data['tagline']),
            'title'    => $this->checkTraktValue($data['title']),
            'tmdbid'   => $this->checkTraktValue($data['ids']['tmdb']),
            'trailer'  => $this->checkTraktValue($data['trailer']),
            'cover'    => $cover,
            'year'     => $this->checkTraktValue($data['year']),
        ]);
    }

    /**
     * Checks if the value is set and not empty, returns it, else empty string.
     *
     * @param mixed $value
     *
     * @return string
     */
    private function checkTraktValue($value): string
    {
        if (\is_array($value) && ! empty($value)) {
            $temp = '';
            foreach ($value as $val) {
                if (! \is_array($val) && ! \is_object($val)) {
                    $temp .= (string) $val;
                }
            }
            $value = $temp;
        }

        return ! empty($value) ? $value : '';
    }

    /**
     * Get array of column keys, for inserting / updating.
     *
     * @return array
     */
    public function getColumnKeys(): array
    {
        return [
            'actors', 'backdrop', 'cover', 'director', 'genre', 'imdbid', 'language',
            'plot', 'rating', 'rtrating', 'tagline', 'title', 'tmdbid', 'trailer', 'type', 'year',
        ];
    }

    /**
     * Update movie on movie-edit page.
     *
     * @param array $values Array of keys/values to update. See $validKeys
     *
     * @return int|bool
     */
    public function update(array $values)
    {
        if (! \count($values)) {
            return false;
        }

        $validKeys = $this->getColumnKeys();

        $query = [
            '0' => 'INSERT INTO movieinfo (updated_at, created_at, ',
            '1' => ' VALUES (NOW(), NOW(), ',
            '2' => 'ON DUPLICATE KEY UPDATE updated_at = NOW(), ',
        ];
        $found = 0;
        foreach ($values as $key => $value) {
            if (! empty($value) && \in_array($key, $validKeys, false)) {
                $found++;
                $query[0] .= "$key, ";
                if (\in_array($key, ['genre', 'language'], false)) {
                    $value = substr($value, 0, 64);
                }
                $value = $this->pdo->escapeString($value);
                $query[1] .= "$value, ";
                $query[2] .= "$key = $value, ";
            }
        }
        if (! $found) {
            return false;
        }
        foreach ($query as $key => $value) {
            $query[$key] = rtrim($value, ', ');
        }

        return $this->pdo->queryInsert($query[0].') '.$query[1].') '.$query[2]);
    }

    /**
     * Check if a variable is set and not a empty string.
     *
     * @param $variable
     *
     * @return bool
     */
    protected function checkVariable(&$variable): bool
    {
        return ! empty($variable) ? true : false;
    }

    /**
     * Returns a tmdb, imdb or trakt variable, the one that is set. Empty string if both not set.
     *
     * @param string $variable1
     * @param string $variable2
     * @param string $variable3
     * @param $variable4
     *
     * @return array|string
     */
    protected function setVariables(&$variable1, &$variable2, &$variable3, &$variable4)
    {
        if ($this->checkVariable($variable1)) {
            return $variable1;
        }
        if ($this->checkVariable($variable2)) {
            return $variable2;
        }
        if ($this->checkVariable($variable3)) {
            return $variable3;
        }
        if ($this->checkVariable($variable4)) {
            return $variable4;
        }

        return '';
    }

    /**
     * Fetch IMDB/TMDB/TRAKT info for the movie.
     *
     * @param $imdbId
     *
     * @return bool
     * @throws \Exception
     */
    public function updateMovieInfo($imdbId): bool
    {
        if ($this->echooutput && $this->service !== '') {
            ColorCLI::doEcho(ColorCLI::primary('Fetching IMDB info from TMDB/IMDB/Trakt/OMDB using IMDB id: '.$imdbId), true);
        }

        // Check TMDB for IMDB info.
        $tmdb = $this->fetchTMDBProperties($imdbId);

        // Check IMDB for movie info.
        $imdb = $this->fetchIMDBProperties($imdbId);

        // Check TRAKT for movie info
        $trakt = $this->fetchTraktTVProperties($imdbId);

        // Check OMDb for movie info
        $omdb = $this->fetchOmdbAPIProperties($imdbId);
        if (! $imdb && ! $tmdb && ! $trakt && ! $omdb) {
            return false;
        }

        // Check FanArt.tv for cover and background images.
        $fanart = $this->fetchFanartTVProperties($imdbId);

        $mov = [];

        $mov['cover'] = $mov['backdrop'] = $mov['banner'] = $movieID = 0;
        $mov['type'] = $mov['director'] = $mov['actors'] = $mov['language'] = '';

        $mov['imdbid'] = $imdbId;
        $mov['tmdbid'] = (! isset($tmdb['tmdbid']) || $tmdb['tmdbid'] === '') ? 0 : $tmdb['tmdbid'];

        // Prefer Fanart.tv cover over TMDB,TMDB over IMDB and IMDB over OMDB.
        if ($this->checkVariable($fanart['cover'])) {
            $mov['cover'] = $this->releaseImage->saveImage($imdbId.'-cover', $fanart['cover'], $this->imgSavePath);
        } elseif ($this->checkVariable($tmdb['cover'])) {
            $mov['cover'] = $this->releaseImage->saveImage($imdbId.'-cover', $tmdb['cover'], $this->imgSavePath);
        } elseif ($this->checkVariable($imdb['cover'])) {
            $mov['cover'] = $this->releaseImage->saveImage($imdbId.'-cover', $imdb['cover'], $this->imgSavePath);
        } elseif ($this->checkVariable($omdb['cover'])) {
            $mov['cover'] = $this->releaseImage->saveImage($imdbId.'-cover', $omdb['cover'], $this->imgSavePath);
        }

        // Backdrops.
        if ($this->checkVariable($fanart['backdrop'])) {
            $mov['backdrop'] = $this->releaseImage->saveImage($imdbId.'-backdrop', $fanart['backdrop'], $this->imgSavePath, 1920, 1024);
        } elseif ($this->checkVariable($tmdb['backdrop'])) {
            $mov['backdrop'] = $this->releaseImage->saveImage($imdbId.'-backdrop', $tmdb['backdrop'], $this->imgSavePath, 1920, 1024);
        }

        // Banner
        if ($this->checkVariable($fanart['banner'])) {
            $mov['banner'] = $this->releaseImage->saveImage($imdbId.'-banner', $fanart['banner'], $this->imgSavePath);
        }

        //RottenTomatoes rating from OmdbAPI
        if ($this->checkVariable($omdb['rtRating'])) {
            $mov['rtrating'] = $omdb['rtRating'];
        }

        $mov['title'] = $this->setVariables($imdb['title'], $tmdb['title'], $trakt['title'], $omdb['title']);
        $mov['rating'] = $this->setVariables($imdb['rating'], $tmdb['rating'], $trakt['rating'], $omdb['rating']);
        $mov['plot'] = $this->setVariables($imdb['plot'], $tmdb['plot'], $trakt['overview'], $omdb['plot']);
        $mov['tagline'] = $this->setVariables($imdb['tagline'], $tmdb['tagline'], $trakt['tagline'], $omdb['tagline']);
        $mov['year'] = $this->setVariables($imdb['year'], $tmdb['year'], $trakt['year'], $omdb['year']);
        $mov['genre'] = $this->setVariables($imdb['genre'], $tmdb['genre'], $trakt['genres'], $omdb['genre']);

        if ($this->checkVariable($imdb['type'])) {
            $mov['type'] = $imdb['type'];
        }

        if ($this->checkVariable($imdb['director'])) {
            $mov['director'] = \is_array($imdb['director']) ? implode(', ', array_unique($imdb['director'])) : $imdb['director'];
        } elseif ($this->checkVariable($omdb['director'])) {
            $mov['director'] = \is_array($omdb['director']) ? implode(', ', array_unique($omdb['director'])) : $omdb['director'];
        }

        if ($this->checkVariable($imdb['actors'])) {
            $mov['actors'] = \is_array($imdb['actors']) ? implode(', ', array_unique($imdb['actors'])) : $imdb['actors'];
        } elseif ($this->checkVariable($omdb['actors'])) {
            $mov['actors'] = \is_array($omdb['actors']) ? implode(', ', array_unique($omdb['actors'])) : $omdb['actors'];
        }

        if ($this->checkVariable($imdb['language'])) {
            $mov['language'] = \is_array($imdb['language']) ? implode(', ', array_unique($imdb['language'])) : $imdb['language'];
        } elseif ($this->checkVariable($omdb['language'])) {
            $mov['language'] = \is_array($imdb['language']) ? implode(', ', array_unique($omdb['language'])) : $omdb['language'];
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
            'actors'    => html_entity_decode($mov['actors'], ENT_QUOTES, 'UTF-8'),
            'backdrop'  => $mov['backdrop'],
            'cover'     => $mov['cover'],
            'director'  => html_entity_decode($mov['director'], ENT_QUOTES, 'UTF-8'),
            'genre'     => html_entity_decode($mov['genre'], ENT_QUOTES, 'UTF-8'),
            'imdbid'    => $mov['imdbid'],
            'language'  => html_entity_decode($mov['language'], ENT_QUOTES, 'UTF-8'),
            'plot'      => html_entity_decode(preg_replace('/\s+See full summary »/u', ' ', $mov['plot']), ENT_QUOTES, 'UTF-8'),
            'rating'    => round($mov['rating'], 1),
            'rtrating' => $mov['rtrating'] ?? 'N/A',
            'tagline'   => html_entity_decode($mov['tagline'], ENT_QUOTES, 'UTF-8'),
            'title'     => $mov['title'],
            'tmdbid'    => $mov['tmdbid'],
            'type'      => html_entity_decode(ucwords(preg_replace('/[\.\_]/', ' ', $mov['type'])), ENT_QUOTES, 'UTF-8'),
            'year'      => $mov['year'],
        ]);

        if ($this->echooutput && $this->service !== '') {
            ColorCLI::doEcho(
                ColorCLI::headerOver(($movieID !== 0 ? 'Added/updated movie: ' : 'Nothing to update for movie: ')).
                ColorCLI::primary(
                    $mov['title'].
                    ' ('.
                    $mov['year'].
                    ') - '.
                    $mov['imdbid']
                ), true
            );
        }

        return $movieID !== 0;
    }

    /**
     * Fetch FanArt.tv backdrop / cover / title.
     *
     * @param $imdbId
     *
     * @return bool|array
     */
    protected function fetchFanartTVProperties($imdbId)
    {
        if ($this->fanartapikey !== '') {
            $art = $this->fanart->getMovieFanart('tt'.$imdbId);

            if (isset($art) && $art !== false) {
                if (isset($art['status']) && $art['status'] === 'error') {
                    return false;
                }
                $ret = [];
                if ($this->checkVariable($art['moviebackground'][0]['url'])) {
                    $ret['backdrop'] = $art['moviebackground'][0]['url'];
                } elseif ($this->checkVariable($art['moviethumb'][0]['url'])) {
                    $ret['backdrop'] = $art['moviethumb'][0]['url'];
                }
                if ($this->checkVariable($art['movieposter'][0]['url'])) {
                    $ret['cover'] = $art['movieposter'][0]['url'];
                }
                if ($this->checkVariable($art['moviebanner'][0]['url'])) {
                    $ret['banner'] = $art['moviebanner'][0]['url'];
                }

                if (isset($ret['backdrop'], $ret['cover'])) {
                    $ret['title'] = $imdbId;
                    if (isset($art['name'])) {
                        $ret['title'] = $art['name'];
                    }
                    if ($this->echooutput) {
                        ColorCLI::doEcho(ColorCLI::alternateOver('Fanart Found ').ColorCLI::headerOver($ret['title']), true);
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
     * @param      $imdbId
     * @param bool $text
     *
     * @return array|bool
     */
    public function fetchTMDBProperties($imdbId, $text = false)
    {
        $lookupId = $text === false && \strlen($imdbId) === 7 ? 'tt'.$imdbId : $imdbId;

        try {
            $tmdbLookup = $this->tmdbclient->getMoviesApi()->getMovie($lookupId);
        } catch (TmdbApiException $error) {
            echo $error->getMessage().PHP_EOL;
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
            $ImdbID = str_replace('tt', '', $tmdbLookup['imdb_id']);
            $ret['imdbid'] = $ImdbID;
            $vote = $tmdbLookup['vote_average'];
            if (isset($vote)) {
                $ret['rating'] = ($vote === 0) ? '' : $vote;
            }
            $overview = $tmdbLookup['overview'];
            if (! empty($overview)) {
                $ret['plot'] = $overview;
            }
            $tagline = $tmdbLookup['tagline'];
            if (! empty($tagline)) {
                $ret['tagline'] = $tagline;
            }
            $released = $tmdbLookup['release_date'];
            if (! empty($released)) {
                $ret['year'] = Carbon::parse($released)->year;
            }
            $genresa = $tmdbLookup['genres'];
            if (! empty($genresa) && \count($genresa) > 0) {
                $genres = [];
                foreach ($genresa as $genre) {
                    $genres[] = $genre['name'];
                }
                $ret['genre'] = $genres;
            }
            $posterp = $tmdbLookup['poster_path'];
            if (! empty($posterp)) {
                $ret['cover'] = 'https:'.$this->helper->getUrl($posterp);
            }
            $backdrop = $tmdbLookup['backdrop_path'];
            if (! empty($backdrop)) {
                $ret['backdrop'] = 'https:'.$this->helper->getUrl($backdrop);
            }
            if ($this->echooutput) {
                ColorCLI::doEcho(ColorCLI::primaryOver('TMDb Found ').ColorCLI::headerOver($ret['title']), true);
            }

            return $ret;
        }

        return false;
    }

    /**
     * @param $imdbId
     *
     * @return array|bool
     */
    public function fetchIMDBProperties($imdbId)
    {
        $result = null;

        try {
            $result = new Title($imdbId, $this->config);
        } catch (Http $e) {
            echo $e->getMessage().PHP_EOL;
        }
        if ($result !== null) {
            similar_text($this->currentTitle, $result->title(), $percent);
            if ($percent > self::MATCH_PERCENT) {
                similar_text($this->currentYear, $result->year(), $percent);
                if ($percent >= self::YEAR_MATCH_PERCENT) {
                    $ret = [
                        'title' => $result->title(),
                        'tagline' => $result->tagline(),
                        'plot' => array_get($result->plot_split(), '0.plot'),
                        'rating' => $result->rating(),
                        'year' => $result->year(),
                        'cover' => $result->photo(),
                        'genre' => $result->genre(),
                        'language' => $result->language(),
                        'type' => $result->movietype(),
                    ];

                    if ($this->echooutput && $result->title() !== null) {
                        ColorCLI::doEcho(ColorCLI::headerOver('IMDb Found ').ColorCLI::primaryOver($result->title()), true);
                    }

                    return $ret;
                }

                return false;
            }

            return false;
        }

        return false;
    }

    /**
     * Fetch TraktTV backdrop / cover / title.
     *
     * @param $imdbId
     *
     * @return bool|array
     * @throws \Exception
     */
    protected function fetchTraktTVProperties($imdbId)
    {
        if ($this->traktTv === null) {
            $this->traktTv = new TraktTv(['Settings' => $this->pdo]);
        }
        $resp = $this->traktTv->client->movieSummary('tt'.$imdbId, 'full');
        if ($resp !== false) {
            similar_text($this->currentTitle, $resp['title'], $percent);
            if ($percent > self::MATCH_PERCENT) {
                similar_text($this->currentYear, $resp['year'], $percent);
                if ($percent >= self::YEAR_MATCH_PERCENT) {
                    $ret = [];
                    if (isset($resp['ids']['trakt'])) {
                        $ret['id'] = $resp['ids']['trakt'];
                    }

                    if (isset($resp['title'])) {
                        $ret['title'] = $resp['title'];
                    } else {
                        return false;
                    }
                    if ($this->echooutput) {
                        ColorCLI::doEcho(ColorCLI::alternateOver('Trakt Found ').ColorCLI::headerOver($ret['title']), true);
                    }

                    return $ret;
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
     * @param $imdbId
     *
     * @return bool|array
     */
    protected function fetchOmdbAPIProperties($imdbId)
    {
        if ($this->omdbapikey !== null) {
            $resp = $this->omdbApi->fetch('i', 'tt'.$imdbId);

            if (\is_object($resp) && $resp->message === 'OK' && $resp->data->Response !== 'False') {
                similar_text($this->currentTitle, $resp->data->Title, $percent);
                if ($percent > self::MATCH_PERCENT) {
                    similar_text($this->currentYear, $resp->data->Year, $percent);
                    if ($percent >= self::YEAR_MATCH_PERCENT) {
                        $ret = [
                            'title' => ! empty($resp->data->Title) ? $resp->data->Title : '',
                            'cover' => ! empty($resp->data->Poster) ? $resp->data->Poster : '',
                            'genre' => ! empty($resp->data->Genre) ? $resp->data->Genre : '',
                            'year' => ! empty($resp->data->Year) ? $resp->data->Year : '',
                            'plot' => ! empty($resp->data->Plot) ? $resp->data->Plot : '',
                            'rating' => ! empty($resp->data->imdbRating) ? $resp->data->imdbRating : '',
                            'rtRating' => ! empty($resp->data->Ratings[1]->Value) ? $resp->data->Ratings[1]->Value : '',
                            'tagline' => ! empty($resp->data->Tagline) ? $resp->data->Tagline : '',
                            'director' => ! empty($resp->data->Director) ? $resp->data->Director : '',
                            'actors' => ! empty($resp->data->Actors) ? $resp->data->Actors : '',
                            'language' => ! empty($resp->data->Language) ? $resp->data->Language : '',
                            'boxOffice' => ! empty($resp->data->BoxOffice) ? $resp->data->BoxOffice : '',
                        ];

                        if ($this->echooutput) {
                            ColorCLI::doEcho(ColorCLI::alternateOver('OMDbAPI Found ').ColorCLI::headerOver($ret['title']), true);
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
     * Update a release with a IMDB id.
     *
     * @param string $buffer Data to parse a IMDB id/Trakt Id from.
     * @param string $service Method that called this method.
     * @param int $id id of the release.
     * @param int $processImdb To get IMDB info on this IMDB id or not.
     *
     * @return string
     * @throws \Exception
     */
    public function doMovieUpdate($buffer, $service, $id, $processImdb = 1): string
    {
        $imdbID = false;
        if (\is_string($buffer) && preg_match('/(?:imdb.*?)?(?:tt|Title\?)(?P<imdbid>\d{5,7})/i', $buffer, $matches)) {
            $imdbID = $matches['imdbid'];
        }

        if ($imdbID !== false) {
            $this->service = $service;
            if ($this->echooutput && $this->service !== '') {
                ColorCLI::doEcho(ColorCLI::headerOver($service.' found IMDBid: ').ColorCLI::primary('tt'.$imdbID), true);
            }

            Release::query()->where('id', $id)->update(['imdbid' => $imdbID]);

            // If set, scan for imdb info.
            if ($processImdb === 1) {
                $movCheck = $this->getMovieInfo($imdbID);
                if ($movCheck === false || (isset($movCheck['updated_at']) && (time() - strtotime($movCheck['updated_at'])) > 2592000)) {
                    if ($this->updateMovieInfo($imdbID) === false) {
                        Release::query()->where('id', $id)->update(['imdbid' => 0000000]);
                    }
                }
            }
        }

        return $imdbID;
    }

    /**
     * Process releases with no IMDB id's.
     *
     *
     * @param string $groupID
     * @param string $guidChar
     * @param int $lookupIMDB
     * @throws \Exception
     */
    public function processMovieReleases($groupID = '', $guidChar = '', $lookupIMDB = 1): void
    {
        if ($lookupIMDB === 0) {
            return;
        }

        // Get all releases without an IMDB id.
        $sql = Release::query()
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

        $res = $sql->limit($this->movieqty)->get(['searchname', 'id'])->toArray();

        $movieCount = \count($res);

        if ($movieCount > 0) {
            if ($this->traktTv === null) {
                $this->traktTv = new TraktTv(['Settings' => $this->pdo]);
            }
            if ($this->echooutput && $movieCount > 1) {
                ColorCLI::doEcho(ColorCLI::header('Processing '.$movieCount.' movie releases.'), true);
            }

            // Loop over releases.
            foreach ($res as $arr) {
                // Try to get a name/year.
                if ($this->parseMovieSearchName($arr['searchname']) === false) {
                    //We didn't find a name, so set to all 0's so we don't parse again.
                    Release::query()->where('id', $arr['id'])->update(['imdbid' => 0000000]);
                    continue;
                }
                $this->currentRelID = $arr['id'];

                $movieName = $this->currentTitle;
                if ($this->currentYear !== false) {
                    $movieName .= ' ('.$this->currentYear.')';
                }

                if ($this->echooutput) {
                    ColorCLI::doEcho(ColorCLI::primaryOver('Looking up: ').ColorCLI::headerOver($movieName), true);
                }

                $movieUpdated = false;

                // Check local DB.
                $getIMDBid = $this->localIMDBSearch();

                if ($getIMDBid !== false) {
                    $imdbID = $this->doMovieUpdate('tt'.$getIMDBid, 'Local DB', $arr['id']);
                    if ($imdbID !== false) {
                        $movieUpdated = true;
                    }
                }

                // Check on OMDbAPI
                if ($movieUpdated === false) {
                    $omdbTitle = strtolower(str_replace(' ', '_', $this->currentTitle));
                    if ($this->omdbapikey !== null) {
                        $buffer = $this->omdbApi->search($omdbTitle, 'movie');

                        if (\is_object($buffer) && $buffer->message === 'OK' && $buffer->data->Response === 'True') {
                            $getIMDBid = $buffer->data->Search[0]->imdbID;

                            if (! empty($getIMDBid)) {
                                $imdbID = $this->doMovieUpdate($getIMDBid, 'OMDbAPI', $arr['id']);
                                if ($imdbID !== false) {
                                    $movieUpdated = true;
                                }
                            }
                        }
                    }
                }

                // Check on Trakt.
                if ($movieUpdated === false) {
                    $data = $this->traktTv->client->movieSummary($movieName, 'full');
                    if ($data !== false) {
                        $this->parseTraktTv($data);
                        if (! empty($data['ids']['imdb'])) {
                            $imdbID = $this->doMovieUpdate($data['ids']['imdb'], 'Trakt', $arr['id']);
                            if ($imdbID !== false) {
                                $movieUpdated = true;
                            }
                        }
                    }
                }

                // Check on The Movie Database.
                if ($movieUpdated === false) {
                    $data = $this->tmdbclient->getSearchApi()->searchMovies($this->currentTitle);
                    if ($data['total_results'] > 0) {
                        if (! empty($data['results'])) {
                            foreach ($data['results'] as $result) {
                                if (! empty($result['id'])) {
                                    similar_text($this->currentYear, Carbon::parse($result['release_date'])->year, $percent);
                                    if ($percent > 80) {
                                        $ret = $this->fetchTMDBProperties($result['id'], true);
                                        if ($ret !== false) {
                                            $imdbID = $this->doMovieUpdate('tt'.$ret['imdbid'], 'TMDB', $arr['id']);
                                            if ($imdbID !== false) {
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
                    } else {
                        $movieUpdated = false;
                    }
                }

                // Try on search engines.
                if ($movieUpdated === false) {
                    if ($this->searchEngines && $this->currentYear !== false) {
                        if ($this->imdbIDFromEngines() === true) {
                            $movieUpdated = true;
                        }
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
     * @return bool|mixed
     */
    protected function localIMDBSearch()
    {
        //If we found a year, try looking in a 4 year range.
        $check = MovieInfo::query()
            ->where('title', 'LIKE', '%'.$this->currentTitle.'%');

        if ($this->currentYear !== false) {
            $start = Carbon::parse($this->currentYear)->subYears(2)->year;
            $end = Carbon::parse($this->currentYear)->addYears(2)->year;
            $check->whereBetween('year', [$start, $end]);
        }
        $IMDBCheck = $check->first(['imdbid']);

        return $IMDBCheck === null ? false : $IMDBCheck->imdbid;
    }

    /**
     * Try to get an IMDB id from search engines.
     *
     * @return bool
     * @throws \Exception
     */
    protected function imdbIDFromEngines(): bool
    {
        if ($this->googleLimit < 41 && (time() - $this->googleBan) > 600) {
            if ($this->googleSearch() === true) {
                return true;
            }
        } elseif ($this->duckduckgoLimit < 41) {
            if ($this->duckduckgoSearch() === true) {
                return true;
            }
        } elseif ($this->bingLimit < 41) {
            if ($this->bingSearch() === true) {
                return true;
            }
        }

        return $this->yahooLimit < 41 && $this->yahooSearch() === true;
    }

    /**
     * Try to find a IMDB id on google.com.
     *
     * @return bool
     * @throws \Exception
     */
    protected function googleSearch(): bool
    {
        try {
            $buffer = $this->client->get(
                'https://www.google.com/search?hl=en&as_q=&as_epq='.
                urlencode(
                    $this->currentTitle.
                    ' '.
                    $this->currentYear
                ).
                '&as_oq=&as_eq=&as_nlo=&as_nhi=&lr=&cr=&as_qdr=all&as_sitesearch='.
                urlencode('www.imdb.com/title/').
                '&as_occt=title&safe=images&tbs=&as_filetype=&as_rights='
            )->getBody()->getContents();
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                if ($e->getCode() === 404) {
                    ColorCLI::doEcho(ColorCLI::notice('Data not available on Google search'), true);
                } elseif ($e->getCode() === 503) {
                    ColorCLI::doEcho(ColorCLI::notice('Google service unavailable'), true);
                } else {
                    ColorCLI::doEcho(ColorCLI::notice('Unable to fetch data from Google, http error reported: '.$e->getCode()), true);
                }
            }
        } catch (\RuntimeException $e) {
            ColorCLI::doEcho(ColorCLI::notice('Runtime error: '.$e->getCode()), true);
        }

        // Make sure we got some data.
        if (! empty($buffer)) {
            $this->googleLimit++;

            if (preg_match('/(To continue, please type the characters below)|(- did not match any documents\.)/i', $buffer, $matches)) {
                if (! empty($matches[1])) {
                    $this->googleBan = time();
                }
            } elseif ($this->doMovieUpdate($buffer, 'Google.com', $this->currentRelID) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Try to find a IMDB id on bing.com.
     *
     * @return bool
     * @throws \Exception
     */
    protected function bingSearch(): bool
    {
        try {
            $buffer = $this->client->get(
                'http://www.bing.com/search?q='.
                urlencode(
                    '("'.
                    $this->currentTitle.
                    '" and "'.
                    $this->currentYear.
                    '") site:www.imdb.com/title/'
                ).
                '&qs=n&form=QBLH&filt=all'
            )->getBody()->getContents();
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                if ($e->getCode() === 404) {
                    ColorCLI::doEcho(ColorCLI::notice('Data not available on Bing search'), true);
                } elseif ($e->getCode() === 503) {
                    ColorCLI::doEcho(ColorCLI::notice('Bing search service unavailable'), true);
                } else {
                    ColorCLI::doEcho(ColorCLI::notice('Unable to fetch data from Bing search , http error reported: '.$e->getCode()), true);
                }
            }
        } catch (\RuntimeException $e) {
            ColorCLI::doEcho(ColorCLI::notice('Runtime error: '.$e->getCode()), true);
        }

        if (! empty($buffer)) {
            $this->bingLimit++;

            if ($this->doMovieUpdate($buffer, 'Bing.com', $this->currentRelID) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Try to find a IMDB id on yahoo.com.
     *
     * @return bool
     * @throws \Exception
     */
    protected function yahooSearch(): bool
    {
        try {
            $buffer = $this->client->get(
                'http://search.yahoo.com/search?n=10&ei=UTF-8&va_vt=title&vo_vt=any&ve_vt=any&vp_vt=any&vf=all&vm=p&fl=0&fr=fp-top&p='.
                urlencode(
                    ''.
                    implode(
                        '+',
                        explode(
                            ' ',
                            preg_replace(
                                '/\s+/',
                                ' ',
                                preg_replace(
                                    '/\W/',
                                    ' ',
                                    $this->currentTitle
                                )
                            )
                        )
                    ).
                    '+'.
                    $this->currentYear
                ).
                '&vs='.
                urlencode('www.imdb.com/title/')
            )->getBody()->getContents();
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                if ($e->getCode() === 999) {
                    ColorCLI::doEcho(ColorCLI::notice('Banned from Yahoo search'), true);
                } elseif ($e->getCode() === 404) {
                    ColorCLI::doEcho(ColorCLI::notice('Data not available on Yahoo search'), true);
                } elseif ($e->getCode() === 503) {
                    ColorCLI::doEcho(ColorCLI::notice('Yahoo search service unavailable'), true);
                } else {
                    ColorCLI::doEcho(ColorCLI::notice('Unable to fetch data from Yahoo search, http error reported: '.$e->getCode()), true);
                }
            }
        } catch (\RuntimeException $e) {
            ColorCLI::doEcho(ColorCLI::notice('Runtime error: '.$e->getCode()), true);
        }

        if (! empty($buffer)) {
            $this->yahooLimit++;

            if ($this->doMovieUpdate($buffer, 'Yahoo.com', $this->currentRelID) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Try to find a IMDB id on bing.com.
     *
     * @return bool
     * @throws \Exception
     */
    protected function duckduckgoSearch(): bool
    {
        try {
            $buffer = $this->client->get(
                'https://duckduckgo.com/html?q='.
                urlencode(
                    $this->currentTitle.
                    ' imdb'
                )
            )->getBody()->getContents();
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                if ($e->getCode() === 404) {
                    ColorCLI::doEcho(ColorCLI::notice('Data not available on DuckDuckGo search'), true);
                } elseif ($e->getCode() === 503) {
                    ColorCLI::doEcho(ColorCLI::notice('DuckDuckGo search service unavailable'), true);
                } else {
                    ColorCLI::doEcho(ColorCLI::notice('Unable to fetch data from DuckDuckGo search , http error reported: '.$e->getCode()), true);
                }
            }
        } catch (\RuntimeException $e) {
            ColorCLI::doEcho(ColorCLI::notice('Runtime error: '.$e->getCode()), true);
        }

        if (! empty($buffer)) {
            $this->duckduckgoLimit++;

            if ($this->doMovieUpdate($buffer, 'duckduckgo.com', $this->currentRelID) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Parse a movie name from a release search name.
     *
     * @param string $releaseName
     *
     * @return bool
     */
    protected function parseMovieSearchName($releaseName): bool
    {
        $name = $year = '';
        $followingList = '[^\w]((1080|480|720)p|AC3D|Directors([^\w]CUT)?|DD5\.1|(DVD|BD|BR)(Rip)?|BluRay|divx|HDTV|iNTERNAL|LiMiTED|(Real\.)?Proper|RE(pack|Rip)|Sub\.?(fix|pack)|Unrated|WEB-DL|(x|H)[-._ ]?264|xvid)[^\w]';

        /* Initial scan of getting a year/name.
         * [\w. -]+ Gets 0-9a-z. - characters, most scene movie titles contain these chars.
         * ie: [61420]-[FULL]-[a.b.foreignEFNet]-[ Coraline.2009.DUTCH.INTERNAL.1080p.BluRay.x264-VeDeTT ]-[21/85] - "vedett-coralien-1080p.r04" yEnc
         * Then we look up the year, (19|20)\d\d, so $matches[1] would be Coraline $matches[2] 2009
         */
        if (preg_match('/(?P<name>[\w. -]+)[^\w](?P<year>(19|20)\d\d)/i', $releaseName, $matches)) {
            $name = $matches['name'];
            $year = $matches['year'];

        /* If we didn't find a year, try to get a name anyways.
         * Try to look for a title before the $followingList and after anything but a-z0-9 two times or more (-[ for example)
         */
        } elseif (preg_match('/([^\w]{2,})?(?P<name>[\w .-]+?)'.$followingList.'/i', $releaseName, $matches)) {
            $name = $matches['name'];
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
                $this->currentYear = ($year === '' ? false : $year);

                return true;
            }
        }

        return false;
    }

    /**
     * Get IMDB genres.
     *
     * @return array
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

<?php

namespace Blacklight;

use App\Models\Genre;
use App\Models\Release;
use App\Models\XxxInfo;
use App\Models\Category;
use App\Models\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Blacklight\processing\adult\ADE;
use Blacklight\processing\adult\ADM;
use Blacklight\processing\adult\AEBN;
use Illuminate\Support\Facades\Cache;
use Blacklight\processing\adult\Popporn;
use Blacklight\processing\adult\Hotmovies;

/**
 * Class XXX.
 */
class XXX
{
    /**
     * What scraper class did we use -- used for template and trailer information.
     *
     * @var string
     */
    protected $whichclass = '';

    /**
     * Current title being passed through various sites/api's.
     *
     * @var string
     */
    protected $currentTitle = '';

    /**
     * @var bool
     */
    protected $echooutput;

    /**
     * @var string
     */
    protected $imgSavePath;

    /**
     * @var \Blacklight\ReleaseImage
     */
    protected $releaseImage;

    /**
     * @var
     */
    protected $currentRelID;

    /**
     * @var int|null|string
     */
    protected $movieqty;

    /**
     * @var string
     */
    protected $showPasswords;

    protected $cookie;

    /**
     * @var array|bool|int|string
     */
    public $catWhere;

    protected $pdo;

    /**
     * @param array $options Echo to cli / Class instances.
     *
     * @throws \Exception
     */
    public function __construct(array $options = [])
    {
        $defaults = [
            'Echo'         => false,
            'ReleaseImage' => null,
            'Settings'     => null,
        ];
        $options += $defaults;
        $this->releaseImage = ($options['ReleaseImage'] instanceof ReleaseImage ? $options['ReleaseImage'] : new ReleaseImage());
        $this->pdo = DB::connection()->getPdo();

        $this->movieqty = Settings::settingValue('..maxxxxprocessed') !== '' ? (int) Settings::settingValue('..maxxxxprocessed') : 100;
        $this->showPasswords = Releases::showPasswords();
        $this->echooutput = ($options['Echo'] && config('nntmux.echocli'));
        $this->imgSavePath = NN_COVERS.'xxx'.DS;
        $this->cookie = NN_TMP.'xxx.cookie';
    }

    /**
     * Get info for a xxx id.
     *
     * @param $xxxid
     * @return \Illuminate\Database\Query\Builder|static
     */
    public function getXXXInfo($xxxid)
    {
        return XxxInfo::query()->where('id', $xxxid)->selectRaw(' *, UNCOMPRESS(plot) as plot')->first();
    }

    /**
     * Get XXX releases with covers for xxx browse page.
     *
     * @param $page
     * @param       $cat
     * @param       $start
     * @param       $num
     * @param       $orderBy
     * @param int $maxAge
     * @param array $excludedCats
     *
     * @return array
     */
    public function getXXXRange($page, $cat, $start, $num, $orderBy, $maxAge = -1, array $excludedCats = []): array
    {
        $catsrch = '';
        if (\count($cat) > 0 && $cat[0] !== -1) {
            $catsrch = Category::getCategorySearch($cat);
        }
        $order = $this->getXXXOrder($orderBy);
        $expiresAt = Carbon::now()->addMinutes(config('nntmux.cache_expiry_medium'));
        $xxxmoviesSql =
            sprintf(
                "
				SELECT SQL_CALC_FOUND_ROWS
					xxx.id,
					GROUP_CONCAT(r.id ORDER BY r.postdate DESC SEPARATOR ',') AS grp_release_id
				FROM xxxinfo xxx
				LEFT JOIN releases r ON xxx.id = r.xxxinfo_id
				WHERE r.nzbstatus = 1
				AND xxx.title != ''
				AND r.passwordstatus %s
				%s %s %s %s
				GROUP BY xxx.id
				ORDER BY %s %s %s",
                $this->showPasswords,
                $this->getBrowseBy(),
                $catsrch,
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
        $xxxmoviesCache = Cache::get(md5($xxxmoviesSql.$page));
        if ($xxxmoviesCache !== null) {
            $xxxmovies = $xxxmoviesCache;
        } else {
            $data = DB::select($xxxmoviesSql);
            $xxxmovies = ['total' => DB::select('SELECT FOUND_ROWS() AS total'), 'result' => $data];
            Cache::put(md5($xxxmoviesSql.$page), $xxxmovies, $expiresAt);
        }
        $xxxIDs = $releaseIDs = false;
        if (\is_array($xxxmovies['result'])) {
            foreach ($xxxmovies['result'] as $xxx => $id) {
                $xxxIDs[] = $id->id;
                $releaseIDs[] = $id->grp_release_id;
            }
        }
        $sql = sprintf(
            "
			SELECT
				r.id, r.rarinnerfilecount, r.grabs, r.comments, r.totalpart, r.size, r.postdate, r.searchname, r.haspreview, r.passwordstatus, r.guid, df.failed AS failed,
				CONCAT(cp.title, ' > ', c.title) AS catname,
			xxx.*, UNCOMPRESS(xxx.plot) AS plot,
			g.name AS group_name,
			rn.releases_id AS nfoid
			FROM releases r
			LEFT OUTER JOIN groups g ON g.id = r.groups_id
			LEFT OUTER JOIN release_nfos rn ON rn.releases_id = r.id
			LEFT OUTER JOIN dnzb_failures df ON df.release_id = r.id
			LEFT OUTER JOIN categories c ON c.id = r.categories_id
			LEFT OUTER JOIN categories cp ON cp.id = c.parentid
			INNER JOIN xxxinfo xxx ON xxx.id = r.xxxinfo_id
			WHERE r.nzbstatus = 1
			AND xxx.id IN (%s)
			AND xxx.title != ''
			AND r.passwordstatus %s
			%s %s %s %s
			GROUP BY xxx.id
			ORDER BY %s %s",
            (\is_array($xxxIDs) ? implode(',', $xxxIDs) : -1),
            $this->showPasswords,
            $this->getBrowseBy(),
            $catsrch,
            (
            $maxAge > 0
                ? 'AND r.postdate > NOW() - INTERVAL '.$maxAge.'DAY '
                : ''
            ),
            (\count($excludedCats) > 0 ? ' AND r.categories_id NOT IN ('.implode(',', $excludedCats).')' : ''),
            $order[0],
            $order[1]
        );
        $return = Cache::get(md5($sql.$page));
        if ($return !== null) {
            return $return;
        }
        $return = DB::select($sql);
        if (\count($return) > 0) {
            $return['_totalcount'] = $xxxmovies['total'][0]->total ?? 0;
        }
        Cache::put(md5($sql.$page), $return, $expiresAt);
        return $return;
    }

    /**
     * Get the order type the user requested on the xxx page.
     *
     * @param $orderBy
     *
     * @return array
     */
    protected function getXXXOrder($orderBy): array
    {
        $orderArr = explode('_', (($orderBy === '') ? 'r.postdate' : $orderBy));
        switch ($orderArr[0]) {
            case 'title':
                $orderField = 'xxx.title';
                break;
            case 'posted':
            default:
                $orderField = 'r.postdate';
                break;
        }

        return [$orderField, isset($orderArr[1]) && preg_match('/^asc|desc$/i', $orderArr[1]) ? $orderArr[1] : 'desc'];
    }

    /**
     * Order types for xxx page.
     *
     * @return array
     */
    public function getXXXOrdering(): array
    {
        return ['title_asc', 'title_desc', 'name_asc', 'name_desc', 'size_asc', 'size_desc', 'posted_asc', 'posted_desc', 'cat_asc', 'cat_desc'];
    }

    /**
     * @return string
     */
    protected function getBrowseBy(): string
    {
        $browseBy = ' ';
        foreach (['title', 'director', 'actors', 'genre', 'id'] as $bb) {
            if (isset($_REQUEST[$bb]) && ! empty($_REQUEST[$bb])) {
                $bbv = stripslashes($_REQUEST[$bb]);
                if ($bb === 'genre') {
                    $bbv = $this->getGenreID($bbv);
                }
                if ($bb === 'id') {
                    $browseBy .= 'AND xxx.'.$bb.'='.$bbv;
                } else {
                    $browseBy .= 'AND xxx.'.$bb.' '.$this->pdo->quote('%'.$bbv.'%');
                }
            }
        }
        return $browseBy;
    }

    /**
     * Update XXX Information from getXXXCovers.php in misc/testing/PostProc.
     *
     * @param string $id
     * @param string $title
     * @param string $tagLine
     * @param string $plot
     * @param string $genre
     * @param string $director
     * @param string $actors
     * @param string $extras
     * @param string $productInfo
     * @param string $trailers
     * @param string $directUrl
     * @param string $classUsed
     * @param string $cover
     * @param string $backdrop
     */
    public function update(
        $id = '',
        $title = '',
        $tagLine = '',
        $plot = '',
        $genre = '',
        $director = '',
        $actors = '',
        $extras = '',
        $productInfo = '',
        $trailers = '',
        $directUrl = '',
        $classUsed = '',
        $cover = '',
        $backdrop = ''
    ): void {
        if (! empty($id)) {
            XxxInfo::query()->where('id', $id)->update(
                [
                    'title' => $title,
                    'tagline' => $tagLine,
                    'plot' => "\x1f\x8b\x08\x00".gzcompress($plot),
                    'genre' => substr($genre, 0, 64),
                    'director' => $director,
                    'actors' => $actors,
                    'extras' => $extras,
                    'productinfo' => $productInfo,
                    'trailers'=> $trailers,
                    'directurl' => $directUrl,
                    'classused' => $classUsed,
                    'cover' => empty($cover) ? 0 : $cover,
                    'backdrop' => empty($backdrop) ? 0 : $backdrop,
                ]
            );
        }
    }

    /**
     * Get all genres for search-filter.tpl.
     *
     *
     * @param bool $activeOnly
     * @return array
     */
    public function getAllGenres($activeOnly = false): array
    {
        $ret = [];
        if ($activeOnly) {
            $res = Genre::query()->where(['disabled' => 0, 'type' => Category::XXX_ROOT])->orderBy('title')->get(['title']);
        } else {
            $res = Genre::query()->where(['type' => Category::XXX_ROOT])->orderBy('title')->get(['title']);
        }

        foreach ($res as $arr => $value) {
            $ret[] = $value['title'];
        }

        return $ret;
    }

    /**
     * @param bool $activeOnly
     * @param null $gid
     * @return mixed
     */
    public function getGenres($activeOnly = false, $gid = null)
    {
        if ($activeOnly) {
            return Genre::query()->where(['disabled' => 0, 'type' => Category::XXX_ROOT])->when($gid !== null, function ($query) use ($gid) {
                return $query->where('id', $gid);
            })->orderBy('title')->first(['title']);
        }

        return Genre::query()->where(['type' => Category::XXX_ROOT])->when($gid !== null, function ($query) use ($gid) {
            return $query->where('id', $gid);
        })->orderBy('title')->first(['title']);
    }

    /**
     * Get Genre id's Of the title.
     *
     * @param $arr - Array or String
     *
     * @return string - If array .. 1,2,3,4 if string .. 1
     */
    protected function getGenreID($arr): string
    {
        $ret = null;

        if (! \is_array($arr)) {
            $res = Genre::query()->where('title', $arr)->first(['id']);
            if ($res !== null) {
                return $res['id'];
            }
        }

        foreach ($arr as $key => $value) {
            $res = Genre::query()->where('title', $value)->first(['id']);
            if ($res !== null) {
                $ret .= ','.$res['id'];
            } else {
                $ret .= ','.$this->insertGenre($value);
            }
        }

        $ret = ltrim($ret, ',');

        return $ret;
    }

    /**
     * Inserts Genre and returns last affected row (Genre ID).
     *
     *
     * @param $genre
     * @return int|string
     */
    private function insertGenre($genre)
    {
        $res = '';
        if ($genre !== null) {
            $res = Genre::query()->insert(['title' => $genre, 'type' => Category::XXX_ROOT, 'disabled' => 0]);
        }

        return $res;
    }

    /**
     * Inserts Trailer Code by Class.
     *
     * @param $whichclass
     * @param $res
     *
     * @return string
     */
    public function insertSwf($whichclass, $res): string
    {
        $ret = '';
        if ($whichclass === 'ade') {
            if (! empty($res)) {
                $trailers = unserialize($res, 'ade');
                $ret .= "<object width='360' height='240' type='application/x-shockwave-flash' id='EmpireFlashPlayer' name='EmpireFlashPlayer' data='".$trailers['url']."'>";
                $ret .= "<param name='flashvars' value= 'streamID=".$trailers['streamid'].'&amp;autoPlay=false&amp;BaseStreamingUrl='.$trailers['baseurl']."'>";
                $ret .= '</object>';

                return $ret;
            }
        }
        if ($whichclass === 'pop') {
            if (! empty($res)) {
                $trailers = unserialize($res, 'pop');
                $ret .= "<embed id='trailer' width='480' height='360'";
                $ret .= "flashvars='".$trailers['flashvars']."' allowfullscreen='true' allowscriptaccess='always' quality='high' name='trailer' style='undefined'";
                $ret .= "src='".$trailers['baseurl']."' type='application/x-shockwave-flash'>";

                return $ret;
            }
        }

        return $ret;
    }

    /**
     * @param $movie
     *
     * @return false|int|string
     * @throws \Exception
     */
    public function updateXXXInfo($movie)
    {
        $cover = $backdrop = 0;
        $xxxID = -2;
        $this->whichclass = 'aebn';
        $mov = new AEBN();
        $mov->cookie = $this->cookie;
        ColorCLI::doEcho(ColorCLI::info('Checking AEBN for movie info'), true);
        $res = $mov->processSite($movie);

        if ($res === false) {
            $this->whichclass = 'pop';
            $mov = new Popporn();
            $mov->cookie = $this->cookie;
            ColorCLI::doEcho(ColorCLI::info('Checking PopPorn for movie info'), true);
            $res = $mov->processSite($movie);
        }

        if ($res === false) {
            $this->whichclass = 'adm';
            $mov = new ADM();
            $mov->cookie = $this->cookie;
            ColorCLI::doEcho(ColorCLI::info('Checking ADM for movie info'), true);
            $res = $mov->processSite($movie);
        }

        if ($res === false) {
            $this->whichclass = 'ade';
            $mov = new ADE();
            ColorCLI::doEcho(ColorCLI::info('Checking ADE for movie info'), true);
            $res = $mov->processSite($movie);
        }

        if ($res === false) {
            $this->whichclass = 'hotm';
            $mov = new Hotmovies();
            $mov->cookie = $this->cookie;
            ColorCLI::doEcho(ColorCLI::info('Checking HotMovies for movie info'), true);
            $res = $mov->processSite($movie);
        }

        // If a result is true getAll information.
        if ($res) {
            if ($this->echooutput) {
                switch ($this->whichclass) {
                    case 'aebn':
                        $fromstr = 'Adult Entertainment Broadcast Network';
                        break;
                    case 'ade':
                        $fromstr = 'Adult DVD Empire';
                        break;
                    case 'pop':
                        $fromstr = 'PopPorn';
                        break;
                    case 'adm':
                        $fromstr = 'Adult DVD Marketplace';
                        break;
                    case 'hotm':
                        $fromstr = 'HotMovies';
                        break;
                    default:
                        $fromstr = '';
                }
                ColorCLI::doEcho(ColorCLI::primary('Fetching XXX info from: '.$fromstr), true);
            }
            $res = $mov->getAll();
        } else {
            // Nothing was found, go ahead and set to -2
            return -2;
        }

        $res['cast'] = ! empty($res['cast']) ? implode(',', $res['cast']) : '';
        $res['genres'] = ! empty($res['genres']) ? $this->getGenreID($res['genres']) : '';

        $mov = [
            'trailers'    => ! empty($res['trailers']) ? serialize($res['trailers']) : '',
            'extras'      => ! empty($res['extras']) ? serialize($res['extras']) : '',
            'productinfo' => ! empty($res['productinfo']) ? serialize($res['productinfo']) : '',
            'backdrop'    => ! empty($res['backcover']) ? $res['backcover'] : 0,
            'cover'       => ! empty($res['boxcover']) ? $res['boxcover'] : 0,
            'title'       => ! empty($res['title']) ? html_entity_decode($res['title'], ENT_QUOTES, 'UTF-8') : '',
            'plot'        => ! empty($res['synopsis']) ? html_entity_decode($res['synopsis'], ENT_QUOTES, 'UTF-8') : '',
            'tagline'     => ! empty($res['tagline']) ? html_entity_decode($res['tagline'], ENT_QUOTES, 'UTF-8') : '',
            'genre'       => ! empty($res['genres']) ? html_entity_decode($res['genres'], ENT_QUOTES, 'UTF-8') : '',
            'director'    => ! empty($res['director']) ? html_entity_decode($res['director'], ENT_QUOTES, 'UTF-8') : '',
            'actors'      => ! empty($res['cast']) ? html_entity_decode($res['cast'], ENT_QUOTES, 'UTF-8') : '',
            'directurl'   => ! empty($res['directurl']) ? html_entity_decode($res['directurl'], ENT_QUOTES, 'UTF-8') : '',
            'classused'   => $this->whichclass,
        ];

        $check = XxxInfo::query()->where('title', $mov['title'])->first(['id']);

        if ($check !== null && $check['id'] > 0) {
            $xxxID = $check['id'];

            // Update BoxCover.
            if (! empty($mov['cover'])) {
                $cover = $this->releaseImage->saveImage($xxxID.'-cover', $mov['cover'], $this->imgSavePath);
            }

            // BackCover.
            if (! empty($mov['backdrop'])) {
                $backdrop = $this->releaseImage->saveImage($xxxID.'-backdrop', $mov['backdrop'], $this->imgSavePath, 1920, 1024);
            }

            // Update Current XXX Information
            $this->update($check['id'], $mov['title'], $mov['tagline'], $mov['plot'], $mov['genre'], $mov['director'], $mov['actors'], $mov['extras'], $mov['productinfo'], $mov['trailers'], $mov['directurl'], $mov['classused'], $cover, $backdrop);
        }

        // Insert New XXX Information
        if ($check === null) {
            $xxxID = XxxInfo::query()->insertGetId(
                [
                    'title' => $mov['title'],
                    'tagline' => $mov['tagline'],
                    'plot' => "\x1f\x8b\x08\x00".gzcompress($mov['plot']),
                    'genre' => substr($mov['genre'], 0, 64),
                    'director' => $mov['director'],
                    'actors' => $mov['actors'],
                    'extras' => $mov['extras'],
                    'productinfo' => $mov['productinfo'],
                    'trailers' => $mov['trailers'],
                    'directurl' => $mov['directurl'],
                    'classused' => $mov['classused'],
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]
            );
            // Update BoxCover.
            if (! empty($mov['cover'])) {
                $cover = $this->releaseImage->saveImage($xxxID.'-cover', $mov['cover'], $this->imgSavePath);
            }

            // BackCover.
            if (! empty($mov['backdrop'])) {
                $backdrop = $this->releaseImage->saveImage($xxxID.'-backdrop', $mov['backdrop'], $this->imgSavePath, 1920, 1024);
            }

            XxxInfo::query()->where('id', $xxxID)->update(['cover' => $cover, 'backdrop' => $backdrop]);
        }

        if ($this->echooutput) {
            ColorCLI::doEcho(
                ColorCLI::headerOver(($xxxID !== false ? 'Added/updated XXX movie: '.ColorCLI::primary($mov['title']) : 'Nothing to update for XXX movie: '.ColorCLI::primary($mov['title']))),
                true
            );
        }

        return $xxxID;
    }

    /**
     * Process XXX releases where xxxinfo is 0.
     *
     * @throws \Exception
     */
    public function processXXXReleases(): void
    {
        $res = Release::query()
            ->where(['nzbstatus' => 1, 'xxxinfo_id' => 0])
            ->whereIn(
                'categories_id',
            [
                Category::XXX_DVD,
                Category::XXX_WMV,
                Category::XXX_XVID,
                Category::XXX_X264,
                Category::XXX_SD,
                Category::XXX_CLIPHD,
                Category::XXX_CLIPSD,
                Category::XXX_WEBDL,
            ]
            )
            ->limit($this->movieqty)
            ->get(['searchname', 'id']);

        $movieCount = \count($res);

        if ($movieCount > 0) {
            if ($this->echooutput) {
                ColorCLI::doEcho(ColorCLI::header('Processing '.$movieCount.' XXX releases.'), true);
            }

            // Loop over releases.
            foreach ($res as $arr) {
                $idcheck = -2;

                // Try to get a name.
                if ($this->parseXXXSearchName($arr['searchname']) !== false) {
                    $check = $this->checkXXXInfoExists($this->currentTitle);
                    if ($check === null) {
                        $this->currentRelID = $arr['id'];
                        if ($this->echooutput) {
                            ColorCLI::doEcho(ColorCLI::primaryOver('Looking up: ').ColorCLI::headerOver($this->currentTitle), true);
                        }

                        ColorCLI::doEcho(ColorCLI::info('Local match not found, checking web!'), true);
                        $idcheck = $this->updateXXXInfo($this->currentTitle);
                    } else {
                        ColorCLI::doEcho(ColorCLI::info('Local match found for XXX Movie: '.ColorCLI::headerOver($this->currentTitle)), true);
                        $idcheck = (int) $check['id'];
                    }
                } else {
                    ColorCLI::doEcho('.', true);
                }
                Release::query()
                    ->where('id', $arr['id'])
                    ->whereIn('categories_id', [Category::XXX_DVD, Category::XXX_WMV, Category::XXX_XVID, Category::XXX_X264, Category::XXX_SD, Category::XXX_CLIPHD, Category::XXX_CLIPSD, Category::XXX_WEBDL])
                    ->update(['xxxinfo_id' => $idcheck]);
            }
        } elseif ($this->echooutput) {
            ColorCLI::doEcho(ColorCLI::header('No xxx releases to process.'), true);
        }
    }

    /**
     * Checks xxxinfo to make sure releases exist.
     *
     * @param $releaseName
     * @return \Illuminate\Database\Eloquent\Model|null|static
     */
    protected function checkXXXInfoExists($releaseName)
    {
        return XxxInfo::query()->where('title', 'like', '%'.$releaseName.'%')->first(['id', 'title']);
    }

    /**
     * Cleans up a searchname to make it easier to scrape.
     *
     * @param string $releaseName
     *
     * @return bool
     */
    protected function parseXXXSearchName($releaseName): bool
    {
        $name = '';
        $followingList = '[^\w]((2160|1080|480|720)(p|i)|AC3D|Directors([^\w]CUT)?|DD5\.1|(DVD|BD|BR)(Rip)?|BluRay|divx|HDTV|iNTERNAL|LiMiTED|(Real\.)?Proper|RE(pack|Rip)|Sub\.?(fix|pack)|Unrated|WEB-DL|(x|H)[-._ ]?264|xvid|[Dd][Ii][Ss][Cc](\d+|\s*\d+|\.\d+)|XXX|BTS|DirFix|Trailer|WEBRiP|NFO|(19|20)\d\d)[^\w]';

        if (preg_match('/([^\w]{2,})?(?P<name>[\w .-]+?)'.$followingList.'/i', $releaseName, $matches)) {
            $name = $matches['name'];
        }

        // Check if we got something.
        if ($name !== '') {

            // If we still have any of the words in $followingList, remove them.
            $name = preg_replace('/'.$followingList.'/i', ' ', $name);
            // Remove periods, underscored, anything between parenthesis.
            $name = preg_replace('/\(.*?\)|[-._]/i', ' ', $name);
            // Finally remove multiple spaces and trim leading spaces.
            $name = trim(preg_replace('/\s{2,}/', ' ', $name));
            // Remove Private Movies {d} from name better matching.
            $name = trim(preg_replace('/^Private\s(Specials|Blockbusters|Blockbuster|Sports|Gold|Lesbian|Movies|Classics|Castings|Fetish|Stars|Pictures|XXX|Private|Black\sLabel|Black)\s\d+/i', '', $name));
            // Remove Foreign Words at the end of the name.
            $name = trim(preg_replace('/(brazilian|chinese|croatian|danish|deutsch|dutch|estonian|flemish|finnish|french|german|greek|hebrew|icelandic|italian|latin|nordic|norwegian|polish|portuguese|japenese|japanese|russian|serbian|slovenian|spanish|spanisch|swedish|thai|turkish)$/i', '', $name));

            // Check if the name is long enough and not just numbers and not file (d) of (d) and does not contain Episodes and any dated 00.00.00 which are site rips..
            if (\strlen($name) > 5 && ! preg_match('/^\d+$/', $name) && ! preg_match('/( File \d+ of \d+|\d+.\d+.\d+)/', $name) && ! preg_match('/(E\d+)/', $name) && ! preg_match('/\d\d\.\d\d.\d\d/', $name)) {
                $this->currentTitle = $name;

                return true;
            }
        }

        return false;
    }
}

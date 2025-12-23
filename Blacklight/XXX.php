<?php

namespace Blacklight;

use App\Models\Category;
use App\Models\Genre;
use App\Models\Release;
use App\Models\Settings;
use App\Models\XxxInfo;
use App\Services\ReleaseImageService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Class XXX.
 *
 * This class handles display and browsing of adult content information.
 * For processing adult releases, use App\Services\AdultProcessing\AdultProcessingPipeline.
 */
class XXX
{
    /**
     * What scraper class did we use -- used for template and trailer information.
     */
    protected string $whichClass = '';

    /**
     * Current title being passed through various sites/api's.
     */
    protected string $currentTitle = '';

    protected bool $echoOutput;

    protected string $imgSavePath;

    protected ReleaseImageService $releaseImage;

    protected string|int|null $movieQty;

    protected string $showPasswords;

    protected string $cookie;

    protected ColorCLI $colorCli;

    public function __construct()
    {
        $this->releaseImage = new ReleaseImageService;
        $this->colorCli = new ColorCLI;

        $this->movieQty = Settings::settingValue('maxxxxprocessed') ?? 100;
        $this->showPasswords = app(\App\Services\Releases\ReleaseBrowseService::class)->showPasswords();
        $this->echoOutput = config('nntmux.echocli');
        $this->imgSavePath = storage_path('covers/xxx/');
        $this->cookie = resource_path('tmp/xxx.cookie');
    }

    /**
     * Get info for a xxx id.
     *
     * @return \Illuminate\Database\Query\Builder|static
     */
    public function getXXXInfo($xxxid)
    {
        return XxxInfo::query()->where('id', $xxxid)->selectRaw(' *, UNCOMPRESS(plot) as plot')->first();
    }

    /**
     * Get XXX releases with covers for xxx browse page.
     */
    public function getXXXRange($page, $cat, $start, $num, $orderBy, int $maxAge = -1, array $excludedCats = []): array
    {
        $page = max(1, $page);
        $start = max(0, $start);

        $catSrch = '';
        if (\count($cat) > 0 && $cat[0] !== -1) {
            $catSrch = Category::getCategorySearch($cat);
        }
        $order = $this->getXXXOrder($orderBy);
        $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_medium'));
        $xxxmoviesSql =
            sprintf(
                "
				SELECT SQL_CALC_FOUND_ROWS
					xxx.id,
					GROUP_CONCAT(r.id ORDER BY r.postdate DESC SEPARATOR ',') AS grp_release_id
				FROM xxxinfo xxx
				LEFT JOIN releases r ON xxx.id = r.xxxinfo_id
				WHERE xxx.title != ''
				AND r.passwordstatus %s
				%s %s %s %s
				GROUP BY xxx.id
				ORDER BY %s %s %s",
                $this->showPasswords,
                $this->getBrowseBy(),
                $catSrch,
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
        $xxxIDs = [];
        if (\is_array($xxxmovies['result'])) {
            foreach ($xxxmovies['result'] as $xxx => $id) {
                $xxxIDs[] = $id->id;
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
			xxx.*, UNCOMPRESS(xxx.plot) AS plot,
			g.name AS group_name,
			rn.releases_id AS nfoid
			FROM releases r
			LEFT OUTER JOIN usenet_groups g ON g.id = r.groups_id
			LEFT OUTER JOIN release_nfos rn ON rn.releases_id = r.id
			LEFT OUTER JOIN dnzb_failures df ON df.release_id = r.id
			LEFT OUTER JOIN categories c ON c.id = r.categories_id
			LEFT OUTER JOIN root_categories cp ON cp.id = c.root_categories_id
			INNER JOIN xxxinfo xxx ON xxx.id = r.xxxinfo_id
			WHERE xxx.id IN (%s)
			AND xxx.title != ''
			AND r.passwordstatus %s
			%s %s %s %s
			GROUP BY xxx.id
			ORDER BY %s %s",
            (! empty($xxxIDs) ? implode(',', $xxxIDs) : -1),
            $this->showPasswords,
            $this->getBrowseBy(),
            $catSrch,
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
            $return[0]->_totalcount = $xxxmovies['total'][0]->total ?? 0;
        }
        Cache::put(md5($sql.$page), $return, $expiresAt);

        return $return;
    }

    /**
     * Get the order type the user requested on the xxx page.
     */
    protected function getXXXOrder($orderBy): array
    {
        $orderArr = explode('_', (($orderBy === '') ? 'r.postdate' : $orderBy));
        $orderField = match ($orderArr[0]) {
            'title' => 'xxx.title',
            default => 'r.postdate',
        };

        return [$orderField, isset($orderArr[1]) && preg_match('/^asc|desc$/i', $orderArr[1]) ? $orderArr[1] : 'desc'];
    }

    /**
     * Order types for xxx page.
     */
    public function getXXXOrdering(): array
    {
        return ['title_asc', 'title_desc', 'name_asc', 'name_desc', 'size_asc', 'size_desc', 'posted_asc', 'posted_desc', 'cat_asc', 'cat_desc'];
    }

    protected function getBrowseBy(): string
    {
        $browseBy = ' ';
        foreach (['title', 'director', 'actors', 'genre', 'id'] as $bb) {
            if (! empty($_REQUEST[$bb])) {
                $bbv = stripslashes($_REQUEST[$bb]);
                if ($bb === 'genre') {
                    $bbv = $this->getGenreID($bbv);
                }
                if ($bb === 'id') {
                    $browseBy .= ' AND xxx.'.$bb.'='.$bbv;
                } else {
                    $browseBy .= ' AND xxx.'.$bb.' '.'LIKE '.escapeString('%'.$bbv.'%');
                }
            }
        }

        return $browseBy;
    }

    /**
     * Update XXX Information from getXXXCovers.php in misc/testing/PostProc.
     */
    public function update(
        string $id = '',
        string $title = '',
        string $tagLine = '',
        string $plot = '',
        string $genre = '',
        string $director = '',
        string $actors = '',
        string $extras = '',
        string $productInfo = '',
        string $trailers = '',
        string $directUrl = '',
        string $classUsed = '',
        string $cover = '',
        string $backdrop = ''
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
                    'trailers' => $trailers,
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
     */
    public function getAllGenres(bool $activeOnly = false): array
    {
        $ret = [];
        if ($activeOnly) {
            $res = Genre::query()->where(['disabled' => 0, 'type' => Category::XXX_ROOT])->orderBy('title')->get(['title'])->toArray();
        } else {
            $res = Genre::query()->where(['type' => Category::XXX_ROOT])->orderBy('title')->get(['title'])->toArray();
        }

        return array_column($res, 'title');
    }

    public function getGenres(bool $activeOnly = false, $gid = null): mixed
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
     * @param  $arr  - Array or String
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
     */
    private function insertGenre($genre): int|string
    {
        $res = '';
        if ($genre !== null) {
            $res = Genre::query()->insert(['title' => $genre, 'type' => Category::XXX_ROOT, 'disabled' => 0]);
        }

        return $res;
    }

    /**
     * Inserts Trailer Code by Class.
     */
    public function insertSwf($whichClass, $res): string
    {
        $ret = '';
        if (($whichClass === 'ade') && ! empty($res)) {
            $trailers = unserialize($res, 'ade');
            $ret .= "<object width='360' height='240' type='application/x-shockwave-flash' id='EmpireFlashPlayer' name='EmpireFlashPlayer' data='".$trailers['url']."'>";
            $ret .= "<param name='flashvars' value= 'streamID=".$trailers['streamid'].'&amp;autoPlay=false&amp;BaseStreamingUrl='.$trailers['baseurl']."'>";
            $ret .= '</object>';

            return $ret;
        }
        if (($whichClass === 'pop') && ! empty($res)) {
            $trailers = unserialize($res, 'pop');
            $ret .= "<embed id='trailer' width='480' height='360'";
            $ret .= "flashvars='".$trailers['flashvars']."' allowfullscreen='true' allowscriptaccess='always' quality='high' name='trailer' style='undefined'";
            $ret .= "src='".$trailers['baseurl']."' type='application/x-shockwave-flash'>";

            return $ret;
        }

        return $ret;
    }


    /**
     * Checks xxxinfo to make sure releases exist.
     *
     * @return \Illuminate\Database\Eloquent\Model|null|static
     */
    protected function checkXXXInfoExists($releaseName)
    {
        return XxxInfo::query()->where('title', 'like', '%'.$releaseName.'%')->first(['id', 'title']);
    }

    /**
     * Cleans up a searchname to make it easier to scrape.
     */
    protected function parseXXXSearchName(string $releaseName): bool
    {
        $name = '';
        $followingList = '[^\w]((2160|1080|480|720)(p|i)|AC3D|Directors([^\w]CUT)?|DD5\.1|(DVD|BD|BR)(Rip)?|BluRay|divx|HDTV|iNTERNAL|LiMiTED|(Real\.)?Proper|RE(pack|Rip)|Sub\.?(fix|pack)|Unrated|WEB-DL|(x|H)[ ._-]?264|xvid|[Dd][Ii][Ss][Cc](\d+|\s*\d+|\.\d+)|XXX|BTS|DirFix|Trailer|WEBRiP|NFO|(19|20)\d\d)[^\w]';

        if (preg_match('/([^\w]{2,})?(?P<name>[\w .-]+?)'.$followingList.'/i', $releaseName, $hits)) {
            $name = $hits['name'];
        }

        // Check if we got something.
        if ($name !== '') {
            // If we still have any of the words in $followingList, remove them.
            $name = preg_replace('/'.$followingList.'/i', ' ', $name);
            // Remove periods, underscored, anything between parenthesis.
            $name = preg_replace('/\(.*?\)|[._-]/i', ' ', $name);
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

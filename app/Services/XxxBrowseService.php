<?php

namespace App\Services;

use App\Models\Category;
use App\Models\XxxInfo;
use App\Services\Releases\ReleaseBrowseService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Service class for browsing XXX content.
 */
class XxxBrowseService
{
    protected string $showPasswords;

    public function __construct()
    {
        $this->showPasswords = app(ReleaseBrowseService::class)->showPasswords();
    }

    /**
     * Get XXX releases with covers for xxx browse page.
     */
    public function getXXXRange(int $page, array $cat, int $start, int $num, string $orderBy, int $maxAge = -1, array $excludedCats = []): array
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
    protected function getXXXOrder(string $orderBy): array
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
                    $bbv = XxxInfo::getGenreID($bbv);
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
     * Inserts Trailer Code by Class.
     */
    public function insertSwf(string $whichClass, ?string $res): string
    {
        $ret = '';
        if (($whichClass === 'ade') && ! empty($res)) {
            $trailers = unserialize($res, ['allowed_classes' => false]);
            $ret .= "<object width='360' height='240' type='application/x-shockwave-flash' id='EmpireFlashPlayer' name='EmpireFlashPlayer' data='".$trailers['url']."'>";
            $ret .= "<param name='flashvars' value= 'streamID=".$trailers['streamid'].'&amp;autoPlay=false&amp;BaseStreamingUrl='.$trailers['baseurl']."'>";
            $ret .= '</object>';

            return $ret;
        }
        if (($whichClass === 'pop') && ! empty($res)) {
            $trailers = unserialize($res, ['allowed_classes' => false]);
            $ret .= "<embed id='trailer' width='480' height='360'";
            $ret .= "flashvars='".$trailers['flashvars']."' allowfullscreen='true' allowscriptaccess='always' quality='high' name='trailer' style='undefined'";
            $ret .= "src='".$trailers['baseurl']."' type='application/x-shockwave-flash'>";

            return $ret;
        }

        return $ret;
    }
}

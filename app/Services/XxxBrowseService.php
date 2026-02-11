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
     *
     * Uses three separate queries instead of GROUP_CONCAT:
     * 1. COUNT query for total results (replaces SQL_CALC_FOUND_ROWS)
     * 2. Paginated XXX entity list with only needed columns
     * 3. Top 2 releases per XXX entity using ROW_NUMBER() window function
     *
     * @param  array<string, mixed>  $cat
     * @param  array<string, mixed>  $excludedCats
     */
    public function getXXXRange(int $page, array $cat, int $start, int $num, string $orderBy, int $maxAge = -1, array $excludedCats = []): mixed
    {
        $page = max(1, $page);
        $start = max(0, $start);

        $catSrch = '';
        if (\count($cat) > 0 && $cat[0] !== -1) { // @phpstan-ignore offsetAccess.notFound
            $catSrch = Category::getCategorySearch($cat);
        }
        $order = $this->getXXXOrder($orderBy);
        $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_medium'));

        $browseBy = $this->getBrowseBy();
        $whereAge = $maxAge > 0 ? 'AND r.postdate > NOW() - INTERVAL '.$maxAge.' DAY ' : '';
        $whereExcluded = \count($excludedCats) > 0 ? ' AND r.categories_id NOT IN ('.implode(',', $excludedCats).')' : '';

        $baseWhere = "xxx.title != '' "
            ."AND r.passwordstatus {$this->showPasswords} "
            .$browseBy.' '
            .$catSrch.' '
            .$whereAge
            .$whereExcluded;

        $cacheKey = md5('xxx_range_'.$baseWhere.$order[0].$order[1].$start.$num.$page); // @phpstan-ignore offsetAccess.notFound

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // Step 1: Count total distinct XXX entities matching filters
        $countSql = 'SELECT COUNT(DISTINCT xxx.id) AS total '
            .'FROM xxxinfo xxx '
            .'INNER JOIN releases r ON xxx.id = r.xxxinfo_id '
            .'WHERE '.$baseWhere;

        $totalResult = DB::select($countSql);
        $totalCount = $totalResult[0]->total ?? 0;

        if ($totalCount === 0) {
            return collect();
        }

        // Step 2: Get paginated XXX entity list with only needed columns
        $xxxSql = 'SELECT xxx.id, xxx.title, xxx.tagline, xxx.cover, xxx.genre, xxx.director, xxx.actors, '
            .'UNCOMPRESS(xxx.plot) AS plot, '
            .'MAX(r.postdate) AS latest_postdate, '
            .'COUNT(r.id) AS total_releases '
            .'FROM xxxinfo xxx '
            .'INNER JOIN releases r ON xxx.id = r.xxxinfo_id '
            .'WHERE '.$baseWhere.' '
            .'GROUP BY xxx.id, xxx.title, xxx.tagline, xxx.cover, xxx.genre, xxx.director, xxx.actors, xxx.plot '
            ."ORDER BY {$order[0]} {$order[1]} " // @phpstan-ignore offsetAccess.notFound
            ."LIMIT {$num} OFFSET {$start}";

        $xxxEntities = XxxInfo::fromQuery($xxxSql);

        if ($xxxEntities->isEmpty()) {
            return collect();
        }

        // Build list of XXX IDs for release query
        $xxxIds = $xxxEntities->pluck('id')->toArray();
        $inXxxIds = implode(',', array_map('intval', $xxxIds));

        // Step 3: Get top 2 releases per XXX entity using ROW_NUMBER()
        $releasesSql = 'SELECT ranked.id, ranked.xxxinfo_id, ranked.guid, ranked.searchname, '
            .'ranked.size, ranked.postdate, ranked.adddate, ranked.haspreview, ranked.grabs, '
            .'ranked.comments, ranked.totalpart, ranked.group_name, ranked.nfoid, ranked.failed_count '
            .'FROM ( '
            .'SELECT r.id, r.xxxinfo_id, r.guid, r.searchname, r.size, r.postdate, r.adddate, '
            .'r.haspreview, r.grabs, r.comments, r.totalpart, g.name AS group_name, '
            .'rn.releases_id AS nfoid, df.failed AS failed_count, '
            .'ROW_NUMBER() OVER (PARTITION BY r.xxxinfo_id ORDER BY r.postdate DESC) AS rn '
            .'FROM releases r '
            .'LEFT JOIN usenet_groups g ON g.id = r.groups_id '
            .'LEFT JOIN release_nfos rn ON rn.releases_id = r.id '
            .'LEFT JOIN dnzb_failures df ON df.release_id = r.id '
            ."WHERE r.xxxinfo_id IN ({$inXxxIds}) "
            ."AND r.passwordstatus {$this->showPasswords} "
            .$catSrch.' '
            .$whereAge
            .$whereExcluded
            .') ranked '
            .'WHERE ranked.rn <= 2 '
            .'ORDER BY ranked.xxxinfo_id, ranked.postdate DESC';

        $releases = DB::select($releasesSql);

        // Group releases by xxxinfo_id for fast lookup
        $releasesByXxx = [];
        foreach ($releases as $release) {
            $releasesByXxx[$release->xxxinfo_id][] = $release;
        }

        // Attach releases to each XXX entity
        foreach ($xxxEntities as $xxx) {
            $xxx->releases = $releasesByXxx[$xxx->id] ?? []; // @phpstan-ignore assign.propertyReadOnly
        }

        // Set total count on first item (matches existing pattern used by controllers)
        if ($xxxEntities->isNotEmpty()) {
            $xxxEntities[0]->_totalcount = $totalCount; // @phpstan-ignore property.notFound
        }

        Cache::put($cacheKey, $xxxEntities, $expiresAt);

        return $xxxEntities;
    }

    /**
     * Get the order type the user requested on the xxx page.
     *
     * @return array<string, mixed>
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
     *
     * @return array<int, string>
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

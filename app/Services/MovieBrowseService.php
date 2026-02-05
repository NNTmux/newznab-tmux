<?php

namespace App\Services;

use App\Models\Category;
use App\Models\MovieInfo;
use App\Models\Release;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Service class for movie browsing operations (frontend).
 */
class MovieBrowseService
{
    protected string $showPasswords;

    public function __construct()
    {
        $this->showPasswords = app(\App\Services\Releases\ReleaseBrowseService::class)->showPasswords();
    }

    /**
     * Get movie releases with covers for movie browse page.
     */
    public function getMovieRange(int $page, array $cat, int $start, int $num, string $orderBy, int $maxAge = -1, array $excludedCats = []): mixed
    {
        $page = max(1, $page);
        $start = max(0, $start);
        $catsrch = '';
        if (count($cat) > 0 && $cat[0] !== -1) {
            $catsrch = Category::getCategorySearch($cat);
        }
        $order = $this->getMovieOrder($orderBy);
        $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_medium'));
        $whereAge = $maxAge > 0 ? 'AND r.postdate > NOW() - INTERVAL '.$maxAge.' DAY ' : '';
        $whereExcluded = count($excludedCats) > 0 ? ' AND r.categories_id NOT IN ('.implode(',', $excludedCats).')' : '';
        $limitClause = $start === false ? '' : ' LIMIT '.$num.' OFFSET '.$start;
        $moviesSql = "SELECT SQL_CALC_FOUND_ROWS m.imdbid, GROUP_CONCAT(r.id ORDER BY r.postdate DESC SEPARATOR ',' ) AS grp_release_id "
            ."FROM movieinfo m LEFT JOIN releases r USING (imdbid) WHERE m.title != '' AND m.imdbid != '0000000' "
            ."AND r.passwordstatus {$this->showPasswords} "
            .$this->getBrowseBy().' '
            .(! empty($catsrch) ? $catsrch.' ' : '')
            .$whereAge
            .$whereExcluded.' '
            ."GROUP BY m.imdbid ORDER BY {$order[0]} {$order[1]} {$limitClause}";
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
            foreach ($movies['result'] as $id) {
                $movieIDs[] = $id->imdbid;
                $releaseIDs[] = $id->grp_release_id;
            }
        }
        $inMovieIds = (is_array($movieIDs) && ! empty($movieIDs)) ? implode(',', $movieIDs) : -1;
        $inReleaseIds = (is_array($releaseIDs) && ! empty($releaseIDs)) ? implode(',', $releaseIDs) : -1;
        $sql = 'SELECT '
            ."GROUP_CONCAT(r.id ORDER BY r.postdate DESC SEPARATOR ',' ) AS grp_release_id, "
            ."GROUP_CONCAT(r.rarinnerfilecount ORDER BY r.postdate DESC SEPARATOR ',' ) AS grp_rarinnerfilecount, "
            ."GROUP_CONCAT(r.haspreview ORDER BY r.postdate DESC SEPARATOR ',' ) AS grp_haspreview, "
            ."GROUP_CONCAT(r.passwordstatus ORDER BY r.postdate DESC SEPARATOR ',' ) AS grp_release_password, "
            ."GROUP_CONCAT(r.guid ORDER BY r.postdate DESC SEPARATOR ',' ) AS grp_release_guid, "
            ."GROUP_CONCAT(rn.releases_id ORDER BY r.postdate DESC SEPARATOR ',' ) AS grp_release_nfoid, "
            ."GROUP_CONCAT(g.name ORDER BY r.postdate DESC SEPARATOR ',' ) AS grp_release_grpname, "
            ."GROUP_CONCAT(r.searchname ORDER BY r.postdate DESC SEPARATOR '#') AS grp_release_name, "
            ."GROUP_CONCAT(r.postdate ORDER BY r.postdate DESC SEPARATOR ',' ) AS grp_release_postdate, "
            ."GROUP_CONCAT(r.adddate ORDER BY r.postdate DESC SEPARATOR ',' ) AS grp_release_adddate, "
            ."GROUP_CONCAT(r.size ORDER BY r.postdate DESC SEPARATOR ',' ) AS grp_release_size, "
            ."GROUP_CONCAT(r.totalpart ORDER BY r.postdate DESC SEPARATOR ',' ) AS grp_release_totalparts, "
            ."GROUP_CONCAT(r.comments ORDER BY r.postdate DESC SEPARATOR ',' ) AS grp_release_comments, "
            ."GROUP_CONCAT(r.grabs ORDER BY r.postdate DESC SEPARATOR ',' ) AS grp_release_grabs, "
            ."GROUP_CONCAT(df.failed ORDER BY r.postdate DESC SEPARATOR ',' ) AS grp_release_failed, "
            ."GROUP_CONCAT(rr.report_count ORDER BY r.postdate DESC SEPARATOR ',' ) AS grp_release_reports, "
            ."GROUP_CONCAT(rr.report_reasons ORDER BY r.postdate DESC SEPARATOR '|' ) AS grp_release_report_reasons, "
            ."GROUP_CONCAT(cp.title, ' > ', c.title ORDER BY r.postdate DESC SEPARATOR ',' ) AS grp_release_catname, "
            .'m.*, g.name AS group_name, rn.releases_id AS nfoid FROM releases r '
            .'LEFT OUTER JOIN usenet_groups g ON g.id = r.groups_id '
            .'LEFT OUTER JOIN release_nfos rn ON rn.releases_id = r.id '
            .'LEFT OUTER JOIN dnzb_failures df ON df.release_id = r.id '
            .'LEFT OUTER JOIN (SELECT releases_id, COUNT(*) AS report_count, GROUP_CONCAT(DISTINCT reason SEPARATOR \', \') AS report_reasons FROM release_reports WHERE status IN (\'pending\', \'reviewed\', \'resolved\') GROUP BY releases_id) rr ON rr.releases_id = r.id '
            .'LEFT OUTER JOIN categories c ON c.id = r.categories_id '
            .'LEFT OUTER JOIN root_categories cp ON cp.id = c.root_categories_id '
            .'INNER JOIN movieinfo m ON m.imdbid = r.imdbid '
            ."WHERE m.imdbid IN ($inMovieIds) AND r.id IN ($inReleaseIds) "
            .(! empty($catsrch) ? $catsrch.' ' : '')
            ."GROUP BY m.imdbid ORDER BY {$order[0]} {$order[1]}";
        $return = Cache::get(md5($sql.$page));
        if ($return !== null) {
            return $return;
        }
        $return = Release::fromQuery($sql);
        if (count($return) > 0) {
            $return[0]->_totalcount = $movies['total'][0]->total ?? 0;
        }
        Cache::put(md5($sql.$page), $return, $expiresAt);

        return $return;
    }

    /**
     * Get the order type the user requested on the movies page.
     */
    protected function getMovieOrder(string $orderBy): array
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

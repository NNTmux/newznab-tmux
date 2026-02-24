<?php

namespace App\Services;

use App\Models\Category;
use App\Models\MovieInfo;
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
     *
     * Uses three separate queries instead of GROUP_CONCAT:
     * 1. COUNT query for total results (replaces SQL_CALC_FOUND_ROWS)
     * 2. Paginated movie list with only needed columns
     * 3. Top 2 releases per movie using UNION ALL with LIMIT 2 per imdbid
     *
     * @param  array<string, mixed>  $cat
     * @param  array<string, mixed>  $excludedCats
     */
    public function getMovieRange(int $page, array $cat, int $start, int $num, string $orderBy, int $maxAge = -1, array $excludedCats = []): mixed
    {
        $page = max(1, $page);
        $start = max(0, $start);

        // Build effective category filter: merge inclusion and exclusion into a single IN clause
        // to avoid redundant IN + NOT IN predicates and help the optimizer
        $catArray = [];
        if (count($cat) > 0 && $cat[0] !== -1) { // @phpstan-ignore offsetAccess.notFound
            $catArray = (array) (Category::getCategorySearch($cat, null, true) ?? []);
        }

        if (! empty($catArray) && ! empty($excludedCats)) {
            $catArray = array_values(array_diff($catArray, array_map('intval', $excludedCats)));
        }

        if (! empty($catArray)) {
            $catFilter = ' AND r.categories_id IN ('.implode(',', $catArray).') ';
            $whereExcluded = '';
        } else {
            $catFilter = '';
            $whereExcluded = count($excludedCats) > 0 ? ' AND r.categories_id NOT IN ('.implode(',', $excludedCats).')' : '';
        }

        $order = $this->getMovieOrder($orderBy);
        $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_medium'));

        $whereAge = $maxAge > 0 ? 'AND r.postdate > NOW() - INTERVAL '.$maxAge.' DAY ' : '';
        $browseBy = $this->getBrowseBy();

        $baseWhere = "m.title != '' AND m.imdbid NOT IN ('0000000', '00000000') "
            ."AND r.passwordstatus {$this->showPasswords} "
            .$browseBy.' '
            .$catFilter
            .$whereAge
            .$whereExcluded;

        // Build a cache key from all the query parameters
        $cacheKey = md5('movie_range_'.$baseWhere.$order[0].$order[1].$start.$num.$page);

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // Step 1: Count total distinct movies matching filters.
        // Cached separately with a longer TTL (30 min) since the total changes slowly
        // and this query scans all 84K+ movieinfo rows joined to releases (~0.5s).
        $countCacheKey = md5('movie_count_'.$baseWhere);
        $totalCount = Cache::get($countCacheKey);

        if ($totalCount === null) {
            // releases.imdbid is stored 8-char normalized (imdb_id_pad); only pad movieinfo
            // so the index on r.imdbid can be used.
            $countSql = 'SELECT COUNT(DISTINCT m.imdbid) AS total '
                .'FROM movieinfo m '
                .'INNER JOIN releases r ON r.imdbid = LPAD(TRIM(m.imdbid), 8, \'0\') '
                .'WHERE '.$baseWhere;

            $totalResult = DB::select($countSql);
            $totalCount = $totalResult[0]->total ?? 0;

            Cache::put($countCacheKey, $totalCount, now()->addMinutes(30));
        }

        if ($totalCount === 0) {
            return collect();
        }

        // Step 2: Get paginated movie list using two-phase subquery.
        // Inner query aggregates by just imdbid (small temp table, fast filesort on ~53K
        // narrow rows instead of 53K wide rows with TEXT columns like plot/genre/actors).
        // Outer query joins back to movieinfo for full details on only the top N movies.
        $isAggregateOrder = ($order[0] === 'MAX(r.postdate)');

        if ($isAggregateOrder) {
            $innerOrderBy = 'latest_postdate';
            $innerExtraGroupBy = '';
            $outerOrderBy = 'stats.latest_postdate';
        } else {
            // orderField is like 'm.title', 'm.year', 'm.rating'
            $innerOrderBy = $order[0];
            $innerExtraGroupBy = ', '.$order[0];
            $outerOrderBy = $order[0];
        }

        $moviesSql = 'SELECT m.imdbid, m.tmdbid, m.traktid, m.title, m.year, m.rating, '
            .'m.plot, m.genre, m.director, m.actors, m.cover, '
            .'stats.latest_postdate, stats.total_releases '
            .'FROM ('
            .'SELECT m.imdbid, MAX(r.postdate) AS latest_postdate, COUNT(r.id) AS total_releases '
            .'FROM movieinfo m '
            .'INNER JOIN releases r ON r.imdbid = LPAD(TRIM(m.imdbid), 8, \'0\') '
            .'WHERE '.$baseWhere.' '
            .'GROUP BY m.imdbid'.$innerExtraGroupBy.' '
            ."ORDER BY {$innerOrderBy} {$order[1]} "
            ."LIMIT {$num} OFFSET {$start}"
            .') stats '
            .'INNER JOIN movieinfo m ON m.imdbid = stats.imdbid '
            ."ORDER BY {$outerOrderBy} {$order[1]}";

        $movies = MovieInfo::fromQuery($moviesSql);

        if ($movies->isEmpty()) {
            return collect();
        }

        // Build list of movie IMDB IDs for release query
        $movieImdbIds = $movies->pluck('imdbid')->toArray();

        // Step 3: Get top 2 releases per movie using UNION ALL with LIMIT 2 per imdbid.
        // r.imdbid is stored 8-char normalized so index can be used.
        $unionParts = [];
        foreach ($movieImdbIds as $id) {
            $paddedId = str_pad((string) (int) $id, 8, '0', STR_PAD_LEFT);
            $quotedId = "'".$paddedId."'";
            $unionParts[] = '(SELECT r.id, r.imdbid, r.guid, r.searchname, '
                .'r.size, r.postdate, r.adddate, r.haspreview '
                .'FROM releases r '
                .'WHERE r.imdbid = '.$quotedId.' '
                ."AND r.passwordstatus {$this->showPasswords} "
                .$catFilter
                .$whereExcluded
                .$whereAge
                .'ORDER BY r.postdate DESC LIMIT 2)';
        }

        $releasesSql = implode(' UNION ALL ', $unionParts);
        $releases = DB::select($releasesSql);

        // Group by imdbid (already 8-char in DB)
        $releasesByMovie = [];
        foreach ($releases as $release) {
            $releasesByMovie[$release->imdbid][] = $release;
        }

        // Attach releases to each movie (movie imdbid may be 7/8 digit; normalize for lookup)
        foreach ($movies as $movie) {
            $normMovieId = str_pad((string) (int) $movie->imdbid, 8, '0', STR_PAD_LEFT);
            $movie->releases = $releasesByMovie[$normMovieId] ?? []; // @phpstan-ignore assign.propertyReadOnly
        }

        // Set total count on first item (matches existing pattern used by controllers)
        if ($movies->isNotEmpty()) {
            $movies[0]->_totalcount = $totalCount; // @phpstan-ignore property.notFound
        }

        Cache::put($cacheKey, $movies, $expiresAt);

        return $movies;
    }

    /**
     * Get all releases for a single movie by IMDB ID.
     *
     * @param  array<string, mixed>  $excludedCats
     * @return array<int, object>
     */
    public function getMovieReleases(string $imdbid, array $excludedCats = []): array
    {
        $whereExcluded = count($excludedCats) > 0 ? ' AND r.categories_id NOT IN ('.implode(',', $excludedCats).')' : '';
        $paddedId = "'".str_pad((string) (int) $imdbid, 8, '0', STR_PAD_LEFT)."'";

        $sql = 'SELECT r.id, r.guid, r.searchname, r.size, r.postdate, r.adddate, r.haspreview '
            .'FROM releases r '
            .'WHERE r.imdbid = '.$paddedId.' '
            ."AND r.passwordstatus {$this->showPasswords} "
            .$whereExcluded.' '
            .'ORDER BY r.postdate DESC';

        return DB::select($sql);
    }

    /**
     * Get the order type the user requested on the movies page.
     *
     * @return array{0: string, 1: string}
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
     *
     * @return array<int, string>
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
                $bbv = request()->input($bb);
                if (is_array($bbv)) {
                    continue;
                }
                $bbv = stripslashes((string) $bbv);
                if ($bb === 'year') {
                    if (preg_match('/^(19|20)\d{2}$/', $bbv)) {
                        $browseBy .= ' AND m.year = '.escapeString($bbv);
                    }

                    continue;
                }
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
     *
     * @return array<int, string>
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

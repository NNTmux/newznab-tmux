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

        $baseWhere = "m.title != '' AND m.imdbid != '0000000' "
            ."AND r.passwordstatus {$this->showPasswords} "
            .$browseBy.' '
            .$catFilter
            .$whereAge
            .$whereExcluded;

        // Build a cache key from all the query parameters
        $cacheKey = md5('movie_range_'.$baseWhere.$order[0].$order[1].$start.$num.$page); // @phpstan-ignore offsetAccess.notFound

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
            // Use LPAD to convert movieinfo.imdbid (int) to the zero-padded varchar format
            // stored in releases.imdbid, so the index on r.imdbid can be used for the join.
            $countSql = 'SELECT COUNT(DISTINCT m.imdbid) AS total '
                .'FROM movieinfo m '
                .'INNER JOIN releases r ON r.imdbid = LPAD(m.imdbid, 7, \'0\') '
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
        $isAggregateOrder = ($order[0] === 'MAX(r.postdate)'); // @phpstan-ignore offsetAccess.notFound

        if ($isAggregateOrder) {
            $innerOrderBy = 'latest_postdate';
            $innerExtraGroupBy = '';
            $outerOrderBy = 'stats.latest_postdate';
        } else {
            // orderField is like 'm.title', 'm.year', 'm.rating'
            $innerOrderBy = $order[0]; // @phpstan-ignore offsetAccess.notFound
            $innerExtraGroupBy = ', '.$order[0]; // @phpstan-ignore offsetAccess.notFound
            $outerOrderBy = $order[0]; // @phpstan-ignore offsetAccess.notFound
        }

        $moviesSql = 'SELECT m.imdbid, m.tmdbid, m.traktid, m.title, m.year, m.rating, '
            .'m.plot, m.genre, m.director, m.actors, m.cover, '
            .'stats.latest_postdate, stats.total_releases '
            .'FROM ('
            .'SELECT m.imdbid, MAX(r.postdate) AS latest_postdate, COUNT(r.id) AS total_releases '
            .'FROM movieinfo m '
            .'INNER JOIN releases r ON r.imdbid = LPAD(m.imdbid, 7, \'0\') '
            .'WHERE '.$baseWhere.' '
            .'GROUP BY m.imdbid'.$innerExtraGroupBy.' '
            ."ORDER BY {$innerOrderBy} {$order[1]} " // @phpstan-ignore offsetAccess.notFound
            ."LIMIT {$num} OFFSET {$start}"
            .') stats '
            .'INNER JOIN movieinfo m ON m.imdbid = stats.imdbid '
            ."ORDER BY {$outerOrderBy} {$order[1]}"; // @phpstan-ignore offsetAccess.notFound

        $movies = MovieInfo::fromQuery($moviesSql);

        if ($movies->isEmpty()) {
            return collect();
        }

        // Build list of movie IMDB IDs for release query
        $movieImdbIds = $movies->pluck('imdbid')->toArray();

        // Step 3: Get top 2 releases per movie using UNION ALL with LIMIT 2 per imdbid.
        // Each subquery does an index lookup on imdbid and stops after 2 rows,
        // avoiding the full-table-scan + ROW_NUMBER() materialization of millions of rows.
        // IMDB IDs are quoted as strings with leading-zero padding to match the
        // varchar format stored in releases.imdbid (e.g. '0099348'), preventing
        // implicit type casts that would bypass index usage.
        $unionParts = [];
        foreach ($movieImdbIds as $id) {
            $quotedId = "'".str_pad((string) intval($id), 7, '0', STR_PAD_LEFT)."'";
            $unionParts[] = '(SELECT r.id, r.imdbid, r.guid, r.searchname, '
                .'r.size, r.postdate, r.adddate, r.haspreview '
                .'FROM releases r '
                ."WHERE r.imdbid = {$quotedId} "
                ."AND r.passwordstatus {$this->showPasswords} "
                .$catFilter
                .$whereExcluded
                .$whereAge
                .'ORDER BY r.postdate DESC LIMIT 2)';
        }

        $releasesSql = implode(' UNION ALL ', $unionParts);
        $releases = DB::select($releasesSql);

        // Group releases by imdbid for fast lookup
        $releasesByMovie = [];
        foreach ($releases as $release) {
            $releasesByMovie[$release->imdbid][] = $release;
        }

        // Attach releases to each movie object
        foreach ($movies as $movie) {
            $movie->releases = $releasesByMovie[$movie->imdbid] ?? []; // @phpstan-ignore assign.propertyReadOnly
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

        $sql = 'SELECT r.id, r.guid, r.searchname, r.size, r.postdate, r.adddate, r.haspreview '
            .'FROM releases r '
            ."WHERE r.imdbid = '".str_pad((string) intval($imdbid), 7, '0', STR_PAD_LEFT)."' "
            ."AND r.passwordstatus {$this->showPasswords} "
            .$whereExcluded.' '
            .'ORDER BY r.postdate DESC';

        return DB::select($sql);
    }

    /**
     * Get the order type the user requested on the movies page.
     *
     * @return array<string, mixed>
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

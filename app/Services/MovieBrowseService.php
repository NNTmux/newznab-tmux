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
     * 3. Top 2 releases per movie using ROW_NUMBER() window function
     *
     * @param  array<string, mixed>  $cat
     * @param  array<string, mixed>  $excludedCats
     */
    public function getMovieRange(int $page, array $cat, int $start, int $num, string $orderBy, int $maxAge = -1, array $excludedCats = []): mixed
    {
        $page = max(1, $page);
        $start = max(0, $start);

        $catsrch = '';
        if (count($cat) > 0 && $cat[0] !== -1) { // @phpstan-ignore offsetAccess.notFound
            $catsrch = Category::getCategorySearch($cat);
        }

        $order = $this->getMovieOrder($orderBy);
        $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_medium'));

        $whereAge = $maxAge > 0 ? 'AND r.postdate > NOW() - INTERVAL '.$maxAge.' DAY ' : '';
        $whereExcluded = count($excludedCats) > 0 ? ' AND r.categories_id NOT IN ('.implode(',', $excludedCats).')' : '';
        $browseBy = $this->getBrowseBy();
        $catFilter = ! empty($catsrch) ? $catsrch.' ' : '';

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

        // Step 1: Count total distinct movies matching filters
        $countSql = 'SELECT COUNT(DISTINCT m.imdbid) AS total '
            .'FROM movieinfo m '
            .'INNER JOIN releases r ON r.imdbid = m.imdbid '
            .'WHERE '.$baseWhere;

        $totalResult = DB::select($countSql);
        $totalCount = $totalResult[0]->total ?? 0;

        if ($totalCount === 0) {
            return collect();
        }

        // Step 2: Get paginated movie list with only needed columns
        $moviesSql = 'SELECT m.imdbid, m.tmdbid, m.traktid, m.title, m.year, m.rating, '
            .'m.plot, m.genre, m.director, m.actors, m.cover, '
            .'MAX(r.postdate) AS latest_postdate, '
            .'COUNT(r.id) AS total_releases '
            .'FROM movieinfo m '
            .'INNER JOIN releases r ON r.imdbid = m.imdbid '
            .'WHERE '.$baseWhere.' '
            .'GROUP BY m.imdbid, m.tmdbid, m.traktid, m.title, m.year, m.rating, '
            .'m.plot, m.genre, m.director, m.actors, m.cover '
            ."ORDER BY {$order[0]} {$order[1]} " // @phpstan-ignore offsetAccess.notFound
            ."LIMIT {$num} OFFSET {$start}";

        $movies = MovieInfo::fromQuery($moviesSql);

        if ($movies->isEmpty()) {
            return collect();
        }

        // Build list of movie IMDB IDs for release query
        $movieImdbIds = $movies->pluck('imdbid')->toArray();
        $inMovieIds = implode(',', array_map('intval', $movieImdbIds));

        // Step 3: Get top 2 releases per movie using ROW_NUMBER() window function
        $releasesSql = 'SELECT ranked.id, ranked.imdbid, ranked.guid, ranked.searchname, '
            .'ranked.size, ranked.postdate, ranked.adddate, ranked.haspreview '
            .'FROM ( '
            .'SELECT r.id, r.imdbid, r.guid, r.searchname, r.size, r.postdate, r.adddate, r.haspreview, '
            .'ROW_NUMBER() OVER (PARTITION BY r.imdbid ORDER BY r.postdate DESC) AS rn '
            .'FROM releases r '
            ."WHERE r.imdbid IN ({$inMovieIds}) "
            ."AND r.passwordstatus {$this->showPasswords} "
            .$catFilter
            .$whereAge
            .$whereExcluded
            .') ranked '
            .'WHERE ranked.rn <= 2 '
            .'ORDER BY ranked.imdbid, ranked.postdate DESC';

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
            .'WHERE r.imdbid = '.intval($imdbid).' '
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

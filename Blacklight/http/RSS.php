<?php

namespace Blacklight\http;

use App\Models\Release;
use App\Models\UserSerie;
use Blacklight\NZB;
use App\Models\Category;
use Blacklight\Releases;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Class RSS -- contains specific functions for RSS.
 */
class RSS extends Capabilities
{
    /** Releases class
     * @var Releases
     */
    public $releases;

    /**
     * @param array $options
     *
     * @throws \Exception
     */
    public function __construct(array $options = [])
    {
        parent::__construct($options);
        $defaults = [
            'Settings' => null,
            'Releases' => null,
        ];
        $options += $defaults;

        $this->releases = ($options['Releases'] instanceof Releases ? $options['Releases'] : new Releases(['Settings' => $this->pdo]));
    }

    /**
     * Get releases for RSS.
     *
     * @param     $cat
     * @param int $offset
     * @param int $userID
     * @param int $videosId
     * @param int $aniDbID
     * @param int $airDate
     *
     * @return array
     */
    public function getRss($cat, $offset, $videosId, $aniDbID, $userID = 0, $airDate = -1): array
    {
        $catSearch = $cartSearch = '';

        $catLimit = 'AND r.categories_id BETWEEN '.Category::TV_ROOT.' AND '.Category::TV_OTHER;

        if (\count($cat)) {
            if ((int) $cat[0] === -2) {
                $cartSearch = sprintf(
                    'INNER JOIN users_releases ON users_releases.users_id = %d AND users_releases.releases_id = r.id',
                    $userID
                );
            } elseif ((int) $cat[0] !== -1) {
                $catSearch = Category::getCategorySearch($cat);
            }
        }

        $sql = $this->pdo->query(
            sprintf(
                "SELECT r.*,
					m.cover, m.imdbid, m.rating, m.plot, m.year, m.genre, m.director, m.actors,
					g.name AS group_name,
					CONCAT(cp.title, ' > ', c.title) AS category_name,
					COALESCE(cp.id,0) AS parentid,
					mu.title AS mu_title, mu.url AS mu_url, mu.artist AS mu_artist,
					mu.publisher AS mu_publisher, mu.releasedate AS mu_releasedate,
					mu.review AS mu_review, mu.tracks AS mu_tracks, mu.cover AS mu_cover,
					mug.title AS mu_genre, co.title AS co_title, co.url AS co_url,
					co.publisher AS co_publisher, co.releasedate AS co_releasedate,
					co.review AS co_review, co.cover AS co_cover, cog.title AS co_genre,
					bo.cover AS bo_cover,
					%s AS category_ids
				FROM releases r
				LEFT JOIN categories c ON c.id = r.categories_id
				INNER JOIN categories cp ON cp.id = c.parentid
				LEFT JOIN groups g ON g.id = r.groups_id
				LEFT OUTER JOIN movieinfo m ON m.imdbid = r.imdbid AND m.title != ''
				LEFT OUTER JOIN musicinfo mu ON mu.id = r.musicinfo_id
				LEFT OUTER JOIN genres mug ON mug.id = mu.genres_id
				LEFT OUTER JOIN consoleinfo co ON co.id = r.consoleinfo_id
				LEFT OUTER JOIN genres cog ON cog.id = co.genres_id %s
				LEFT OUTER JOIN tv_episodes tve ON tve.id = r.tv_episodes_id
				LEFT OUTER JOIN bookinfo bo ON bo.id = r.bookinfo_id
				WHERE r.passwordstatus %s
				AND r.nzbstatus = %d
				%s %s %s %s
				ORDER BY postdate DESC %s",
                $this->releases->getConcatenatedCategoryIDs(),
                $cartSearch,
                $this->releases->showPasswords,
                NZB::NZB_ADDED,
                $catSearch,
                ($videosId > 0 ? sprintf('AND r.videos_id = %d %s', $videosId, ($catSearch === '' ? $catLimit : '')) : ''),
                ($aniDbID > 0 ? sprintf('AND r.anidbid = %d %s', $aniDbID, ($catSearch === '' ? $catLimit : '')) : ''),
                ($airDate > -1 ? sprintf('AND tve.firstaired >= DATE_SUB(CURDATE(), INTERVAL %d DAY)', $airDate) : ''),
                ' LIMIT 0,'.($offset > 100 ? 100 : $offset)
            ),
            true,
            config('nntmux.cache_expiry_medium')
        );

        return $sql;
    }

    /**
     * @param       $limit
     * @param int   $userID
     * @param array $excludedCats
     * @param int   $airDate
     *
     * @return \Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection
     * @throws \Exception
     */
    public function getShowsRss($limit, $userID = 0, array $excludedCats = [], $airDate = -1)
    {
        $query = Release::query()
            ->where('r.nzbstatus', NZB::NZB_ADDED)
            ->whereBetween('r.categories_id', [Category::TV_ROOT, Category::TV_OTHER]);
        Releases::showPasswords($query, true);
        $this->releases->uSQL(UserSerie::query()->where('users_id', $userID)->select(['videos_id', 'categories'])->get(), 'videos_id');
        if (\count($excludedCats) > 0) {
            $query->whereNotIn('r.categories_id', $excludedCats);
        }

        if ($airDate > -1) {
            $query->where('tve.firstaired', '>=', Carbon::now()->subDays($airDate));
        }

        $query->select(
            [
                'r.*',
                'v.id',
                'v.title',
                'g.name as group_name',
                DB::raw("CONCAT(cp.title, '-', c.title) as category_name"),
                DB::raw('COALESCE(cp.id,0) as parentid')])
            ->from('releases as r')
            ->leftJoin('categories as c', 'c.id', '=', 'r.categories_id')
            ->leftJoin('categories as cp', 'cp.id', '=', 'c.parentid')
            ->leftJoin('groups as g', 'g.id', '=', 'r.groups_id')
            ->leftJoin('videos as v', 'v.id', '=', 'r.videos_id')
            ->leftJoin('tv_episodes as tve', 'tve.id', '=', 'r.tv_episodes_id')
            ->orderByDesc('r.postdate')
            ->limit($limit > 100 ? 100 : $limit)
            ->offset(0);

        $expiresAt = Carbon::now()->addSeconds(config('nntmux.cache_expiry_medium'));
        $result =  Cache::get(md5($limit.$userID.implode('.', $excludedCats).$airDate));
        if ($result !== null) {
            return $result;
        }

        $result = $query->get();
        Cache::put(md5($limit.$userID.implode('.', $excludedCats).$airDate), $result, $expiresAt);

        return $result;


    }

    /**
     * Get movies for RSS.
     *
     * @param int   $limit
     * @param int   $userID
     * @param array $excludedCats
     *
     * @return array
     */
    public function getMyMoviesRss($limit, $userID = 0, array $excludedCats = []): array
    {
        return $this->pdo->query(
            sprintf(
                "
				SELECT r.*, mi.title AS releasetitle, g.name AS group_name,
					CONCAT(cp.title, '-', c.title) AS category_name,
					%s AS category_ids,
					COALESCE(cp.id,0) AS parentid
				FROM releases r
				LEFT JOIN categories c ON c.id = r.categories_id
				INNER JOIN categories cp ON cp.id = c.parentid
				LEFT JOIN groups g ON g.id = r.groups_id
				LEFT OUTER JOIN movieinfo mi ON mi.imdbid = r.imdbid
				WHERE %s %s
				AND r.nzbstatus = %d
				AND r.categories_id BETWEEN %d AND %d
				AND r.passwordstatus %s
				ORDER BY postdate DESC %s",
                $this->releases->getConcatenatedCategoryIDs(),
                $this->releases->uSQL(
                    $this->pdo->query(
                        sprintf(
                            '
							SELECT imdbid, categories
							FROM user_movies
							WHERE users_id = %d',
                            $userID
                        ),
                        true
                    ),
                    'imdbid'
                ),
                (\count($excludedCats) ? ' AND r.categories_id NOT IN ('.implode(',', $excludedCats).')' : ''),
                NZB::NZB_ADDED,
                Category::MOVIE_ROOT,
                Category::MOVIE_OTHER,
                $this->releases->showPasswords,
                ' LIMIT '.($limit > 100 ? 100 : $limit).' OFFSET 0'
            ),
            true,
            config('nntmux.cache_expiry_medium')
        );
    }

    /**
     * @param $column
     * @param $table
     *
     * @param $order
     *
     * @return array|bool
     */
    public function getFirstInstance($column, $table, $order)
    {
        return $this->pdo->queryOneRow(
            sprintf(
                '
				SELECT %1$s
				FROM %2$s
				WHERE %1$s > 0
				ORDER BY %3$s ASC',
                $column,
                $table,
                $order
            )
        );
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Models\Category;
use App\Models\Release;
use App\Models\UserMovie;
use App\Models\UserSerie;
use Blacklight\NZB;
use Blacklight\Releases;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Class RSS -- contains specific functions for RSS.
 */
class RSS extends ApiController
{
    /**
     * @var \Blacklight\Releases
     */
    public Releases $releases;

    /**
     * @throws \Exception
     */
    public function __construct()
    {
        parent::__construct();
        $this->releases = new Releases();
    }

    /**
     * @return Release[]|Collection|mixed
     */
    public function getRss($cat, $videosId, $aniDbID, int $userID = 0, int $airDate = -1, int $limit = 100, int $offset = 0)
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
        $sql =
            sprintf(
                "SELECT r.searchname, r.guid, r.postdate, r.categories_id, r.size, r.totalpart, r.fromname, r.passwordstatus, r.grabs, r.comments, r.adddate, r.videos_id, r.tv_episodes_id,
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
					bo.cover AS bo_cover
				FROM releases r
				LEFT JOIN categories c ON c.id = r.categories_id
				INNER JOIN root_categories cp ON cp.id = c.root_categories_id
				LEFT JOIN usenet_groups g ON g.id = r.groups_id
				LEFT OUTER JOIN musicinfo mu ON mu.id = r.musicinfo_id
				LEFT OUTER JOIN genres mug ON mug.id = mu.genres_id
				LEFT OUTER JOIN consoleinfo co ON co.id = r.consoleinfo_id
				LEFT JOIN movieinfo m ON m.id = r.movieinfo_id
				LEFT OUTER JOIN genres cog ON cog.id = co.genres_id %s
				LEFT OUTER JOIN tv_episodes tve ON tve.id = r.tv_episodes_id
				LEFT OUTER JOIN bookinfo bo ON bo.id = r.bookinfo_id
				WHERE r.passwordstatus %s
				AND r.nzbstatus = %d
				%s %s %s %s
				ORDER BY postdate DESC %s",
                $cartSearch,
                $this->releases->showPasswords(),
                NZB::NZB_ADDED,
                $catSearch,
                ($videosId > 0 ? sprintf('AND r.videos_id = %d %s', $videosId, ($catSearch === '' ? $catLimit : '')) : ''),
                ($aniDbID > 0 ? sprintf('AND r.anidbid = %d %s', $aniDbID, ($catSearch === '' ? $catLimit : '')) : ''),
                ($airDate > -1 ? sprintf('AND tve.firstaired >= DATE_SUB(CURDATE(), INTERVAL %d DAY)', $airDate) : ''),
                $limit === -1 ? '' : ' LIMIT '.$limit.' OFFSET '.$offset
            );

        $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_medium'));
        $result = Cache::get(md5($sql));
        if ($result !== null) {
            return $result;
        }

        $result = Release::fromQuery($sql);
        Cache::put(md5($sql), $result, $expiresAt);

        return $result;
    }

    /**
     * @return Builder|Collection
     */
    public function getShowsRss(int $limit, int $userID = 0, array $excludedCats = [], int $airDate = -1)
    {
        $sql = sprintf(
            "
				SELECT r.searchname, r.guid, r.postdate, r.categories_id, r.size, r.totalpart, r.fromname, r.passwordstatus, r.grabs, r.comments, r.adddate, r.videos_id, r.tv_episodes_id, v.id, v.title, g.name AS group_name,
					CONCAT(cp.title, '-', c.title) AS category_name,
					COALESCE(cp.id,0) AS parentid
				FROM releases r
				LEFT JOIN categories c ON c.id = r.categories_id
				INNER JOIN root_categories cp ON cp.id = c.root_categories_id
				LEFT JOIN usenet_groups g ON g.id = r.groups_id
				LEFT OUTER JOIN videos v ON v.id = r.videos_id
				LEFT OUTER JOIN tv_episodes tve ON tve.id = r.tv_episodes_id
				WHERE %s %s %s
				AND r.nzbstatus = %d
				AND r.categories_id BETWEEN %d AND %d
				AND r.passwordstatus %s
				ORDER BY postdate DESC %s",
            $this->releases->uSQL(
                UserSerie::fromQuery(
                    sprintf(
                        '
							SELECT videos_id, categories
							FROM user_series
							WHERE users_id = %d',
                        $userID
                    )
                ),
                'videos_id'
            ),
            (\count($excludedCats) ? 'AND r.categories_id NOT IN ('.implode(',', $excludedCats).')' : ''),
            ($airDate > -1 ? sprintf('AND tve.firstaired >= DATE_SUB(CURDATE(), INTERVAL %d DAY) ', $airDate) : ''),
            NZB::NZB_ADDED,
            Category::TV_ROOT,
            Category::TV_OTHER,
            $this->releases->showPasswords(),
            ! empty($limit) ? sprintf(' LIMIT %d OFFSET 0', $limit > 100 ? 100 : $limit) : ''
        );

        $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_medium'));
        $result = Cache::get(md5($sql));
        if ($result !== null) {
            return $result;
        }

        $result = Release::fromQuery($sql);
        Cache::put(md5($sql), $result, $expiresAt);

        return $result;
    }

    /**
     * @return Release[]|Collection|mixed
     */
    public function getMyMoviesRss(int $limit, int $userID = 0, array $excludedCats = [])
    {
        $sql = sprintf(
            "
				SELECT r.searchname, r.guid, r.postdate, r.categories_id, r.size, r.totalpart, r.fromname, r.passwordstatus, r.grabs, r.comments, r.adddate, r.videos_id, r.tv_episodes_id, mi.title AS releasetitle, g.name AS group_name,
					CONCAT(cp.title, '-', c.title) AS category_name,
					COALESCE(cp.id,0) AS parentid
				FROM releases r
				LEFT JOIN categories c ON c.id = r.categories_id
				INNER JOIN root_categories cp ON cp.id = c.root_categories_id
				LEFT JOIN usenet_groups g ON g.id = r.groups_id
				LEFT JOIN movieinfo mi ON mi.id = r.movieinfo_id
				WHERE %s %s
				AND r.nzbstatus = %d
				AND r.categories_id BETWEEN %d AND %d
				AND r.passwordstatus %s
				ORDER BY postdate DESC %s",
            $this->releases->uSQL(
                UserMovie::fromQuery(
                    sprintf(
                        '
							SELECT imdbid, categories
							FROM user_movies
							WHERE users_id = %d',
                        $userID
                    )
                ),
                'imdbid'
            ),
            (\count($excludedCats) > 0 ? ' AND r.categories_id NOT IN ('.implode(',', $excludedCats).')' : ''),
            NZB::NZB_ADDED,
            Category::MOVIE_ROOT,
            Category::MOVIE_OTHER,
            $this->releases->showPasswords(),
            ! empty($limit) ? sprintf(' LIMIT %d OFFSET 0', $limit > 100 ? 100 : $limit) : ''
        );

        $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_medium'));
        $result = Cache::get(md5($sql));
        if ($result !== null) {
            return $result;
        }

        $result = Release::fromQuery($sql);

        Cache::put(md5($sql), $result, $expiresAt);

        return $result;
    }

    /**
     * @return Model|\Illuminate\Database\Query\Builder|null
     */
    public function getFirstInstance($column, $table, $order)
    {
        return DB::table($table)
            ->select([$column])
            ->where($column, '>', 0)
            ->orderBy($order, 'asc')
            ->first();
    }
}

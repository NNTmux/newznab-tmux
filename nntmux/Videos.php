<?php

namespace nntmux;

use nntmux\db\DB;
use App\Models\Video;

/**
 * Class Videos -- functions for site interaction.
 */
class Videos
{
    /**
     * @param array $options
     * @throws \Exception
     */
    public function __construct(array $options = [])
    {
        $defaults = [
            'Echo'         => false,
            'Logger'       => null,
            'Settings'     => null,
        ];
        $options += $defaults;
        $this->pdo = $options['Settings'] instanceof DB ? $options['Settings'] : new DB();
        $this->catWhere = 'r.categories_id BETWEEN '.Category::TV_ROOT.' AND '.Category::TV_OTHER;
    }

    /**
     * Get info from tables for the provided ID.
     *
     *
     * @param $id
     * @return \Illuminate\Database\Eloquent\Model|null|static
     */
    public function getByVideoID($id)
    {
        return Video::query()
            ->where('videos.id', $id)
            ->join('tv_info', 'videos.id', '=', 'tv_info.videos_id')
            ->first(['videos.*', 'tv_info.summary', 'tv_info.publisher', 'tv_info.image']);
    }

    /**
     * Retrieves a range of all shows for the show-edit admin list.
     *
     *
     * @param $start
     * @param $num
     * @param string $showname
     * @return array
     */
    public function getRange($start, $num, $showname = '')
    {
        $sql = Video::query()
            ->select(['videos.*', 'tv_info.summary', 'tv_info.publisher', 'tv_info.image'])
            ->join('tv_info', 'videos.id', '=', 'tv_info.videos_id');

        if ($showname !== '') {
            $sql->where('videos.title', 'like', '%'.$showname.'%');
        }

        if ($start !== false) {
            $sql->limit($num)->offset($start);
        }

        return $sql->get()->toArray();
    }

    /**
     * Returns a count of all shows -- usually used by pager.
     *
     *
     * @param string $showname
     * @return int
     */
    public function getCount($showname = ''): int
    {
        $res = Video::query()->join('tv_info', 'videos.id', '=', 'tv_info.videos_id');

        if ($showname !== '') {
            $res->where('videos.title', 'like', '%'.$showname.'%');
        }

        return $res->count('videos.id');
    }

    /**
     * Retrieves and returns a list of shows with eligible releases.
     *
     * @param        $uid
     * @param string $letter
     * @param string $showname
     *
     * @return array
     */
    public function getSeriesList($uid, $letter = '', $showname = '')
    {
        $rsql = '';
        if ($letter !== '') {
            if ($letter === '0-9') {
                $letter = '[0-9]';
            }

            $rsql .= sprintf('AND v.title REGEXP %s', $this->pdo->escapeString('^'.$letter));
        }
        $tsql = '';
        if ($showname !== '') {
            $tsql .= sprintf('AND v.title %s', $this->pdo->likeString($showname));
        }

        $qry = sprintf(
            '
			SELECT v.* FROM
				(SELECT v.*,
					tve.firstaired AS prevdate, tve.title AS previnfo,
					tvi.publisher,
					us.id AS userseriesid
				FROM videos v
				INNER JOIN tv_info tvi ON v.id = tvi.videos_id
				INNER JOIN tv_episodes tve ON v.id = tve.videos_id
				LEFT OUTER JOIN user_series us ON v.id = us.videos_id AND us.users_id = %d
				WHERE 1=1
				AND tve.firstaired <= NOW()
				%s %s
				ORDER BY tve.firstaired DESC) v
			STRAIGHT_JOIN releases r ON r.videos_id = v.id
			WHERE %s
			GROUP BY v.id
			ORDER BY v.title ASC',
            $uid,
            $rsql,
            $tsql,
            $this->catWhere
        );

        return $this->pdo->query($qry);
    }
}

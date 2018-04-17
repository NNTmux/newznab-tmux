<?php

namespace Blacklight;

use Blacklight\db\DB;
use App\Models\Category;
use App\Models\AnidbTitle;

class AniDB
{
    /**
     * @var \Blacklight\db\DB
     */
    public $pdo;

    /**
     * @param array $options Class instances / Echo to cli.
     * @throws \Exception
     */
    public function __construct(array $options = [])
    {
        $defaults = [
            'Echo'     => false,
            'Settings' => null,
        ];
        $options += $defaults;

        $this->pdo = ($options['Settings'] instanceof DB ? $options['Settings'] : new DB());
    }

    /**
     * Updates stored AniDB entries in the database.
     *
     * @param int    $anidbID
     * @param string       $title
     * @param string $type
     * @param string $startdate
     * @param string $enddate
     * @param string $related
     * @param string $similar
     * @param string $creators
     * @param string $description
     * @param string $rating
     * @param string $categories
     * @param string $characters
     * @param        $epnos
     * @param        $airdates
     * @param        $episodetitles
     */
    public function updateTitle($anidbID, $title, $type, $startdate, $enddate, $related, $similar, $creators, $description, $rating, $categories, $characters, $epnos, $airdates, $episodetitles): void
    {
        $this->pdo->queryExec(
            sprintf(
                '
				UPDATE anidb_titles at
				INNER JOIN anidb_info ai ON ai.anidbid = at.anidbid
				INNER JOIN anidb_episodes ae ON ae.anidbid = at.anidbid
				SET title = %s, type = %s, startdate = %s, enddate = %s,
					related = %s, similar = %s, creators = %s, description = %s, rating = %s,
					categories = %s, characters = %s, epnos = %s, airdates = %s,
					episodetitles = %s WHERE anidbid = %d',
                $this->pdo->escapeString($title),
                $this->pdo->escapeString($type),
                $this->pdo->escapeString($startdate),
                $this->pdo->escapeString($enddate),
                $this->pdo->escapeString($related),
                $this->pdo->escapeString($similar),
                $this->pdo->escapeString($creators),
                $this->pdo->escapeString($description),
                $this->pdo->escapeString($rating),
                $this->pdo->escapeString($categories),
                $this->pdo->escapeString($characters),
                $this->pdo->escapeString($epnos),
                $this->pdo->escapeString($airdates),
                $this->pdo->escapeString($episodetitles),
                $anidbID
            )
        );
    }

    /**
     * Deletes stored AniDB entries in the database.
     *
     * @param int $anidbID
     */
    public function deleteTitle($anidbID): void
    {
        $this->pdo->queryExec(
            sprintf(
                '
				DELETE at, ai, ae
				FROM anidb_titles AS at
				LEFT OUTER JOIN anidb_info ai USING (anidbid)
				LEFT OUTER JOIN anidb_episodes ae USING (anidbid)
				WHERE anidbid = %d',
                $anidbID
            )
        );
    }

    /**
     * Retrieves a list of Anime titles, optionally filtered by starting character and title.
     *
     * @param string $letter
     * @param string $animetitle
     * @return array|bool
     * @throws \RuntimeException
     */
    public function getAnimeList($letter = '', $animetitle = '')
    {
        $rsql = $tsql = '';

        if ($letter !== '') {
            if ($letter === '0-9') {
                $letter = '[0-9]';
            }
            $rsql .= sprintf('AND at.title REGEXP %s', $this->pdo->escapeString('^'.$letter));
        }

        if ($animetitle !== '') {
            $tsql .= sprintf('AND at.title %s', $this->pdo->likeString($animetitle, true, true));
        }

        return $this->pdo->queryDirect(
            sprintf(
                '
				SELECT at.anidbid, at.title,
					ai.type, ai.categories, ai.rating, ai.startdate, ai.enddate
				FROM anidb_titles at
				LEFT JOIN anidb_info ai USING (anidbid)
				STRAIGHT_JOIN releases r ON at.anidbid = r.anidbid
				WHERE at.anidbid > 0 %s %s
				AND r.categories_id = %d
				GROUP BY at.anidbid
				ORDER BY at.title ASC',
                $rsql,
                $tsql,
                Category::TV_ANIME
            )
        );
    }

    /**
     * Retrieves a range of Anime titles for site display.
     *
     *
     * @param        $page
     * @param string $animetitle
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getAnimeRange($page, $animetitle = '')
    {
        $query = AnidbTitle::query()
            ->select(['at.anidbid', DB::raw("GROUP_CONCAT(at.title SEPARATOR ', ') AS title"), 'ai.description'])
            ->from('anidb_titles as at')
            ->leftJoin('anidb_info as ai', 'ai.anidbid', '=', 'at.anidbid')
            ->where('at.lang', '=', 'en');
        if ($animetitle !== '') {
            $query->where('at.title', 'LIKE', '%'.$animetitle.'%');
        }

        $query->groupBy('at.anidbid')
            ->orderByDesc('at.anidbid');

        return $query->paginate(config('nntmux.items_per_page'));
    }

    /**
     * Retrieves all info for a specific AniDB ID.
     *
     * @param int $anidbID
     * @return array|bool
     */
    public function getAnimeInfo($anidbID)
    {
        $animeInfo = $this->pdo->query(
            sprintf(
                '
				SELECT at.anidbid, at.lang, at.title,
					ai.startdate, ai.enddate, ai.updated, ai.related, ai.creators, ai.description,
					ai.rating, ai.picture, ai.categories, ai.characters, ai.type, ai.similar, ae.episodeid, ae
					.episode_title, ae.episode_no, ae.airdate
				FROM anidb_titles AS at
				LEFT JOIN anidb_info AS ai USING (anidbid)
				LEFT JOIN anidb_episodes ae USING (anidbid)
				WHERE at.anidbid = %d',
                $anidbID
            )
        );

        return $animeInfo[0] ?? false;
    }
}

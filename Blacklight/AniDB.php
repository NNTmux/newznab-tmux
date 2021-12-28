<?php

namespace Blacklight;

use App\Models\AnidbTitle;
use App\Models\Category;
use Illuminate\Support\Facades\DB;

/**
 * Class AniDB.
 */
class AniDB
{
    /**
     * AniDB constructor.
     */
    public function __construct()
    {
    }

    /**
     * Updates stored AniDB entries in the database.
     *
     * @param  int  $anidbID
     * @param  string  $title
     * @param  string  $type
     * @param  string  $startdate
     * @param  string  $enddate
     * @param  string  $related
     * @param  string  $similar
     * @param  string  $creators
     * @param  string  $description
     * @param  string  $rating
     * @param  string  $categories
     * @param  string  $characters
     * @param  $epnos
     * @param  $airdates
     * @param  $episodetitles
     */
    public function updateTitle($anidbID, $title, $type, $startdate, $enddate, $related, $similar, $creators, $description, $rating, $categories, $characters, $epnos, $airdates, $episodetitles): void
    {
        DB::update(
            sprintf(
                '
				UPDATE anidb_titles at
				INNER JOIN anidb_info ai ON ai.anidbid = at.anidbid
				INNER JOIN anidb_episodes ae ON ae.anidbid = at.anidbid
				SET title = %s, type = %s, startdate = %s, enddate = %s,
					related = %s, similar = %s, creators = %s, description = %s, rating = %s,
					categories = %s, characters = %s, epnos = %s, airdates = %s,
					episodetitles = %s WHERE anidbid = %d',
                escapeString($title),
                escapeString($type),
                escapeString($startdate),
                escapeString($enddate),
                escapeString($related),
                escapeString($similar),
                escapeString($creators),
                escapeString($description),
                escapeString($rating),
                escapeString($categories),
                escapeString($characters),
                escapeString($epnos),
                escapeString($airdates),
                escapeString($episodetitles),
                $anidbID
            )
        );
    }

    /**
     * @param $anidbID
     *
     * @throws \Throwable
     */
    public function deleteTitle($anidbID): void
    {
        DB::transaction(function () use ($anidbID) {
            DB::delete(
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
        }, 3);
    }

    /**
     * Retrieves a list of Anime titles, optionally filtered by starting character and title.
     *
     *
     * @param  string  $letter
     * @param  string  $animetitle
     * @return array
     */
    public function getAnimeList($letter = '', $animetitle = ''): array
    {
        $rsql = $tsql = '';

        if ($letter !== '') {
            if ($letter === '0-9') {
                $letter = '[0-9]';
            }
            $rsql .= sprintf('AND at.title REGEXP %s', escapeString('^'.$letter));
        }

        if ($animetitle !== '') {
            $tsql .= sprintf('AND at.title LIKE %s', escapeString('%'.$animetitle.'%'));
        }

        return DB::select(
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
     * @param  string  $animetitle
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getAnimeRange($animetitle = ''): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = AnidbTitle::query()
            ->where('at.lang', '=', 'en');
        if ($animetitle !== '') {
            $query->where('at.title', 'like', '%'.$animetitle.'%');
        }
        $query->select(['at.anidbid', DB::raw("GROUP_CONCAT(at.title SEPARATOR ', ') AS title"), 'ai.description'])
                ->from('anidb_titles as at')
                ->leftJoin('anidb_info as ai', 'ai.anidbid', '=', 'at.anidbid')
                ->groupBy('at.anidbid')
                ->orderByDesc('at.anidbid');

        return $query->paginate(config('nntmux.items_per_page'));
    }

    /**
     * Retrieves all info for a specific AniDB ID.
     *
     *
     * @param $anidbID
     * @return mixed
     */
    public function getAnimeInfo($anidbID)
    {
        $animeInfo = DB::select(
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

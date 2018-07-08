<?php

namespace Blacklight;

use App\Models\Genre;
use App\Models\Category;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class Genres
{
    public const CONSOLE_TYPE = Category::GAME_ROOT;
    public const MUSIC_TYPE = Category::MUSIC_ROOT;
    public const GAME_TYPE = Category::PC_ROOT;

    public const STATUS_ENABLED = 0;
    public const STATUS_DISABLED = 1;

    /**
     * @var \Blacklight\db\DB;
     */
    public $pdo;

    /**
     * @param array $options Class instances.
     * @throws \Exception
     */
    public function __construct(array $options = [])
    {
        $defaults = [
            'Settings' => null,
        ];
        $options += $defaults;
    }

    /**
     * @param string $type
     * @param bool $activeonly
     * @return array|bool
     */
    public function getGenres($type = '', $activeonly = false)
    {
        $sql = $this->getListQuery($type, $activeonly);
        $genres = Cache::get(md5($sql));
        if ($genres !== null) {
            return $genres;
        }
        $genres = (array) array_first(DB::select($sql));
        $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_long'));
        Cache::put(md5($sql), $genres, $expiresAt);

        return $genres;
    }

    /**
     * @param string $type
     * @param bool $activeonly
     * @return string
     */
    private function getListQuery($type = '', $activeonly = false): string
    {
        if (! empty($type)) {
            $typesql = sprintf(' AND g.type = %d', $type);
        } else {
            $typesql = '';
        }

        if ($activeonly) {
            $sql = sprintf(
                '
						SELECT g.*
						FROM genres g
						INNER JOIN
							(SELECT DISTINCT genres_id FROM musicinfo) x
							ON x.genres_id = g.id %1$s
						UNION
						SELECT g.*
						FROM genres g
						INNER JOIN
							(SELECT DISTINCT genres_id FROM consoleinfo) x
							ON x.genres_id = g.id %1$s
						UNION
						SELECT g.*
						FROM genres g
						INNER JOIN
							(SELECT DISTINCT genres_id FROM gamesinfo) x
							ON x.genres_id = g.id %1$s
							ORDER BY title',
                $typesql
            );
        } else {
            $sql = sprintf('SELECT g.* FROM genres g WHERE 1 %s ORDER BY g.title', $typesql);
        }

        return $sql;
    }

    /**
     * @param string $type
     * @param bool $activeonly
     * @param $start
     * @param $num
     * @return array|bool
     */
    public function getRange($type = '', $activeonly = false, $start, $num)
    {
        $sql = $this->getListQuery($type, $activeonly);
        $sql .= ' LIMIT '.$num.' OFFSET '.$start;

        return (array) array_first(DB::select($sql));
    }

    /**
     * @param string $type
     * @param bool $activeonly
     * @return mixed
     */
    public function getCount($type = '', $activeonly = false)
    {
        if (! empty($type)) {
            $typesql = sprintf(' AND g.type = %d', $type);
        } else {
            $typesql = '';
        }

        if ($activeonly) {
            $sql = sprintf(
                '
						SELECT COUNT(id) AS num
						FROM genres g
						INNER JOIN
							(SELECT DISTINCT genres_id FROM musicinfo) x
							ON x.genres_id = g.id %1$s
						+
						SELECT COUNT(id) AS num
						FROM genres g
						INNER JOIN
							(SELECT DISTINCT genres_id FROM consoleinfo) y
							ON y.genres_id = g.id %1$s
						+
						SELECT COUNT(id) AS num
						FROM genres g
						INNER JOIN
							(SELECT DISTINCT genres_id FROM gamesinfo) x
							ON x.genres_id = g.id %1$s',
                $typesql
            );
        } else {
            $sql = sprintf('SELECT COUNT(g.id) AS num FROM genres g WHERE 1 %s ORDER BY g.title', $typesql);
        }

        $res = DB::select($sql);

        return $res[0]->num;
    }

    /**
     * @param $id
     * @return \Illuminate\Database\Eloquent\Model|null|static
     */
    public function getById($id)
    {
        return Genre::query()->where('id', $id)->first();
    }

    /**
     * @param $id
     * @param $disabled
     * @return int
     */
    public function update($id, $disabled)
    {
        return Genre::query()->where('id', $id)->update(['disabled' => $disabled]);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function getDisabledIDs()
    {
        $cats = Cache::get('disabledcats');
        if ($cats !== null) {
            $disabled = $cats;
        } else {
            $disabled = Genre::query()->where('disabled', '=', 1)->get(['id']);
            $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_long'));
            Cache::put('disabledcats', $disabled, $expiresAt);
        }

        return $disabled;
    }
}

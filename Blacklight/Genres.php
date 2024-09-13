<?php

namespace Blacklight;

use App\Models\Category;
use App\Models\Genre;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Class Genres.
 */
class Genres
{
    public const CONSOLE_TYPE = Category::GAME_ROOT;

    public const MUSIC_TYPE = Category::MUSIC_ROOT;

    public const GAME_TYPE = Category::PC_ROOT;

    public const STATUS_ENABLED = 0;

    public const STATUS_DISABLED = 1;

    public function __construct() {}

    public function getGenres(string $type = '', bool $activeOnly = false): mixed
    {
        $sql = $this->getListQuery($type, $activeOnly);

        return Cache::flexible($sql, [config('nntmux.cache_expiry_medium'), config('nntmux.cache_expiry_long')], function () use ($sql) {
            return DB::select($sql);
        });
    }

    public function loadGenres($type): array
    {
        $genres = $this->getGenres($type);
        $genresArray = [];
        foreach ($genres as $genre) {
            $genresArray[$genre->id] = strtolower($genre->title);
        }

        return $genresArray;
    }

    private function getListQuery(string $type = '', bool $activeOnly = false): string
    {
        if (! empty($type)) {
            $typesql = sprintf(' AND g.type = %d', $type);
        } else {
            $typesql = '';
        }

        if ($activeOnly) {
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

    public function getRange(int $start, int $num, string $type = '', bool $activeOnly = false): array
    {
        $sql = $this->getListQuery($type, $activeOnly);
        $sql .= ' LIMIT '.$num.' OFFSET '.$start;

        return (array) Arr::first(DB::select($sql));
    }

    public function getCount(string $type = '', bool $activeOnly = false): mixed
    {
        if (! empty($type)) {
            $typesql = sprintf(' AND g.type = %d', $type);
        } else {
            $typesql = '';
        }

        if ($activeOnly) {
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
     * @return Genre|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|object|null
     */
    public function getById($id)
    {
        return Genre::query()->where('id', $id)->first();
    }

    public function update($id, $disabled): int
    {
        return Genre::query()->where('id', $id)->update(['disabled' => $disabled]);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function getDisabledIDs()
    {
        return Cache::flexible('disabledCats', [config('nntmux.cache_expiry_medium'), config('nntmux.cache_expiry_long')], function () {
            return Genre::query()->where('disabled', '=', 1)->get(['id']);
        });
    }
}

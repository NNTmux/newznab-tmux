<?php

namespace nntmux;

use App\Models\Genre;
use nntmux\db\DB;

class Genres
{
    const CONSOLE_TYPE = Category::GAME_ROOT;
    const MUSIC_TYPE = Category::MUSIC_ROOT;
    const GAME_TYPE = Category::PC_ROOT;

    const STATUS_ENABLED = 0;
    const STATUS_DISABLED = 1;

    /**
     * @var \nntmux\db\DB;
     */
    public $pdo;

    /**
     * @param array $options Class instances.
     */
    public function __construct(array $options = [])
    {
        $defaults = [
            'Settings' => null,
        ];
        $options += $defaults;

        $this->pdo = ($options['Settings'] instanceof DB ? $options['Settings'] : new DB());
    }

    /**
     * @param string $type
     * @param bool $activeonly
     * @return array
     */
    public function getGenres($type = '', $activeonly = false): array
    {
        return $this->pdo->query($this->getListQuery($type, $activeonly), true, NN_CACHE_EXPIRY_LONG);
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
     * @return array
     */
    public function getRange($type = '', $activeonly = false, $start, $num): array
    {
        $sql = $this->getListQuery($type, $activeonly);
        $sql .= ' LIMIT '.$num.' OFFSET '.$start;

        return $this->pdo->query($sql);
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

        $res = $this->pdo->queryOneRow($sql);

        return $res['num'];
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
     * @return bool|\PDOStatement
     */
    public function update($id, $disabled)
    {
        return Genre::query()->where('id', $id)->update(['disabled' => $disabled]);
    }

    /**
     * @return array
     */
    public function getDisabledIDs(): array
    {
        return $this->pdo->query('SELECT id FROM genres WHERE disabled = 1', true, NN_CACHE_EXPIRY_LONG);
    }
}

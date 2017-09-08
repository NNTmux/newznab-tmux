<?php

namespace nntmux;

use nntmux\db\DB;
use App\Models\DnzbFailure;

/**
 * Class DnzbFailures.
 */
class DnzbFailures
{
    const FAILED = 1;
    /**
     * @var DB
     */
    public $pdo;

    /**
     * @var ReleaseComments
     */
    public $rc;

    /**
     * @var array Class instances.
     * @throws \Exception
     */
    public function __construct(array $options = [])
    {
        $defaults = [
                'Settings' => null,
        ];
        $options += $defaults;

        $this->pdo = ($options['Settings'] instanceof DB ? $options['Settings'] : new DB());
        $this->rc = new ReleaseComments(['Settings' => $this->pdo]);
    }

    /**
     * Read failed downloads count for requested release_id.
     *
     *
     * @param $relId
     *
     * @return bool|mixed
     */
    public function getFailedCount($relId)
    {
        $result = DnzbFailure::query()->where('release_id', $relId)->value('failed');
        if (! empty($result)) {
            return $result;
        }

        return false;
    }

    /**
     * @return int
     */
    public function getCount(): int
    {
        return DnzbFailure::query()->count('release_id');
    }

    /**
     * Get a range of releases. used in admin manage list.
     *
     * @param $start
     * @param $num
     *
     * @return array
     */
    public function getFailedRange($start, $num): array
    {
        if ($start === false) {
            $limit = '';
        } else {
            $limit = ' LIMIT '.$start.','.$num;
        }

        return $this->pdo->query(
            "
			SELECT r.*, CONCAT(cp.title, ' > ', c.title) AS category_name
			FROM releases r
			RIGHT JOIN dnzb_failures df ON df.release_id = r.id
			LEFT OUTER JOIN categories c ON c.id = r.categories_id
			LEFT OUTER JOIN categories cp ON cp.id = c.parentid
			ORDER BY postdate DESC".$limit
        );
    }

    /**
     * Retrieve alternate release with same or similar searchname.
     *
     * @param string $guid
     * @param string $userid
     *
     * @return string|array
     * @throws \Exception
     */
    public function getAlternate($guid, $userid)
    {
        $rel = $this->pdo->queryOneRow(
            sprintf(
                '
				SELECT id, searchname, categories_id
				FROM releases
				WHERE guid = %s',
                $this->pdo->escapeString($guid)
            )
        );

        if ($rel === false) {
            return false;
        }

        DnzbFailure::query()->updateOrCreate(['release_id' => $rel['id'], 'users_id' => $userid], ['release_id' => $rel['id'], 'users_id' => $userid, 'failed' => 'failed + 1']);

        $alternate = $this->pdo->queryOneRow(
            sprintf(
                '
				SELECT r.guid
				FROM releases r
				LEFT JOIN dnzb_failures df ON r.id = df.release_id
				WHERE r.searchname %s
				AND df.release_id IS NULL
				AND r.categories_id = %d
				AND r.id != %d
				ORDER BY r.postdate DESC',
                $this->pdo->likeString($rel['searchname']),
                $rel['categories_id'],
                $rel['id']
            )
        );

        return $alternate;
    }
}

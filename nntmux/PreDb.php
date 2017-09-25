<?php

namespace nntmux;

use App\Models\Predb as PredbModel;
use nntmux\db\DB;

/**
 * Class for inserting names/categories etc from PreDB sources into the DB,
 * also for matching names on files / subjects.
 *
 * Class PreDb
 */
class PreDb
{
    // Nuke status.
    const PRE_NONUKE = 0; // Pre is not nuked.
    const PRE_UNNUKED = 1; // Pre was un nuked.
    const PRE_NUKED = 2; // Pre is nuked.
    const PRE_MODNUKE = 3; // Nuke reason was modified.
    const PRE_RENUKED = 4; // Pre was re nuked.
    const PRE_OLDNUKE = 5; // Pre is nuked for being old.

    /**
     * @var bool stdClass
     */
    protected $site;

    /**
     * @var bool
     */
    protected $echooutput;

    /**
     * @var \nntmux\db\DB
     */
    protected $pdo;

    private $dateLimit;

    /**
     * @param array $options
     * @throws \Exception
     */
    public function __construct(array $options = [])
    {
        $defaults = [
            'Echo'     => false,
            'Settings' => null,
        ];
        $options += $defaults;

        $this->echooutput = ($options['Echo'] && NN_ECHOCLI);
        $this->pdo = ($options['Settings'] instanceof DB ? $options['Settings'] : new DB());
    }

    /**
     * Attempts to match PreDB titles to releases.
     *
     * @param $dateLimit
     */
    public function checkPre($dateLimit = false): void
    {
        $this->dateLimit = $dateLimit;

        $consoleTools = new ConsoleTools(['ColorCLI' => $this->pdo->log]);
        $updated = 0;
        $datesql = '';

        if ($this->echooutput) {
            echo ColorCLI::header('Querying DB for release search names not matched with PreDB titles.');
        }

        if ($this->dateLimit !== false && is_numeric($this->dateLimit)) {
            $datesql = sprintf('AND adddate > (NOW() - INTERVAL %d DAY)', $this->dateLimit);
        }

        $res = $this->pdo->queryDirect(
            sprintf(
                '
				SELECT p.id AS predb_id, r.id AS releases_id
				FROM predb p
				INNER JOIN releases r ON p.title = r.searchname
				WHERE r.predb_id < 1 %s',
                $datesql
            )
        );

        if ($res !== false) {
            $total = $res->rowCount();
            echo ColorCLI::primary(number_format($total).' releases to match.');

            if ($res instanceof \Traversable) {
                foreach ($res as $row) {
                    $this->pdo->queryExec(
                        sprintf('UPDATE releases SET predb_id = %d WHERE id = %d', $row['predb_id'], $row['releases_id'])
                    );

                    if ($this->echooutput) {
                        $consoleTools->overWritePrimary(
                            'Matching up preDB titles with release searchnames: '.$consoleTools->percentString(++$updated, $total)
                        );
                    }
                }
                if ($this->echooutput) {
                    echo PHP_EOL;
                }
            }

            if ($this->echooutput) {
                echo ColorCLI::header(
                    'Matched '.number_format(($updated > 0) ? $updated : 0).' PreDB titles to release search names.'
                );
            }
        }
    }

    /**
     * Try to match a single release to a PreDB title when the release is created.
     *
     * @param string $cleanerName
     *
     * @return array|bool Array with title/id from PreDB if found, bool False if not found.
     */
    public function matchPre($cleanerName)
    {
        if (empty($cleanerName)) {
            return false;
        }

        $titleCheck = PredbModel::query()->where('title', $cleanerName)->first(['id']);

        if ($titleCheck !== null) {
            return [
                'title' => $cleanerName,
                'predb_id' => $titleCheck['id'],
            ];
        }

        // Check if clean name matches a PreDB filename.
        $fileCheck = PredbModel::query()->where('filename', $cleanerName)->first(['id', 'title']);

        if ($fileCheck !== null) {
            return [
                'title' => $fileCheck['title'],
                'predb_id' => $fileCheck['id'],
            ];
        }

        return false;
    }

    /**
     * Get all PRE's in the DB.
     *
     * @param int    $offset  OFFSET
     * @param int    $offset2 LIMIT
     * @param string|array $search  Optional title search.
     *
     * @return array The row count and the query results.
     */
    public function getAll($offset, $offset2, $search = ''): array
    {
        if ($search !== '') {
            $search = explode(' ', trim($search));
            if (count($search) > 1) {
                $search = "LIKE '%".implode("%' AND title LIKE '%", $search)."%'";
            } else {
                $search = "LIKE '%".$search[0]."%'";
            }
            $search = 'WHERE title '.$search;
        }

        $count = $this->getCount($search);

        $sql = sprintf(
            '
			SELECT p.*, r.guid
			FROM predb p
			LEFT OUTER JOIN releases r ON p.id = r.predb_id %s
			ORDER BY p.predate DESC
			LIMIT %d
			OFFSET %d',
                $search,
                $offset2,
                $offset
        );
        $parr = $this->pdo->query($sql, true, NN_CACHE_EXPIRY_MEDIUM);

        return ['arr' => $parr, 'count' => $count];
    }

    /**
     * Get count of all PRE's.
     *
     * @param string $search
     *
     * @return int
     */
    public function getCount($search = ''): int
    {
        $count = $this->pdo->query(
            "
			SELECT COUNT(id) AS cnt
			FROM predb {$search}",
                true,
                NN_CACHE_EXPIRY_MEDIUM
        );

        return $count === false ? 0 : $count[0]['cnt'];
    }

    /**
     * Get all PRE's for a release.
     *
     *
     * @param $preID
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function getForRelease($preID)
    {
        return PredbModel::query()->where('id', $preID)->get();
    }

    /**
     * Return a single PRE for a release.
     *
     *
     * @param $preID
     * @return \Illuminate\Database\Eloquent\Model|null|static
     */
    public function getOne($preID)
    {
        return PredbModel::query()->where('id', $preID)->first();
    }
}

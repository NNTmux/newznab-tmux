<?php

require_once dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use App\Models\Predb;
use App\Models\Release;
use Illuminate\Support\Facades\DB;

if (! isset($argv[1])) {
    exit(
        "Argument 1 is the index name, releases and predb are the only supported ones currently.\n".
        "Argument 2 is optional, max number of rows to send to ES at a time, 10,000 is the default if not set.\n"
    );
}

populate_indexes($argv[1], (isset($argv[2]) && is_numeric($argv[2]) && $argv[2] > 0 ? $argv[2] : 10000));

// Bulk insert releases into sphinx RT index.
function populate_indexes($table, $max)
{
    $allowedIndexes = ['releases', 'predb'];
    if (\in_array($table, $allowedIndexes, true)) {
        if ($table === 'releases') {
            DB::statement('SET SESSION group_concat_max_len=16384;');
            $query = 'SELECT r.id, r.name, r.searchname, r.fromname, r.categories_id, r.adddate, r.postdate, IFNULL(GROUP_CONCAT(rf.name SEPARATOR " "),"") filename
				FROM releases r
				LEFT JOIN release_files rf ON r.id = rf.releases_id
				WHERE r.id > %d
				GROUP BY r.id
				ORDER BY r.id ASC
				LIMIT %d';

            $totals = Release::fromQuery('SELECT COUNT(id) AS c, MIN(id) AS min FROM releases')->first();
            if (! $totals) {
                exit("Could not get database information for releases table.\n");
            }
            $total = $totals->c;
            $minId = $totals->min;
        }

        if ($table === 'predb') {
            DB::statement('SET SESSION group_concat_max_len=16384;');
            $query = 'SELECT id, title, filename, source
				FROM predb
				WHERE id > %d
				GROUP BY id
				ORDER BY id ASC
				LIMIT %d';

            $totals = Predb::fromQuery('SELECT COUNT(id) AS c, MIN(id) AS min FROM predb')->first();
            if (! $totals) {
                exit("Could not get database information for predb table.\n");
            }
            $total = $totals->c;
            $minId = $totals->min;
        }

        $lastId = $minId - 1;
        echo "[Starting to populate ElasticSearch index $table with $total releases.]".PHP_EOL;
        $data = ['body' => []];
        for ($i = $minId; $i <= ($total + $max + $minId); $i += $max) {
            $rows = DB::select(sprintf($query, $lastId, $max));
            if ($rows === 0) {
                continue;
            }

            foreach ($rows as $row) {
                if ($row->id > $lastId) {
                    $lastId = $row->id;
                }
                switch ($table) {
                    case 'releases':
                        $searchName = str_replace(['.', '-'], ' ', $row->searchname);
                        $data['body'][] = [
                            'index' => [
                                '_index' => 'releases',
                                '_id' => $row->id,
                            ],
                        ];

                        $data['body'][] = [
                            'id' => $row->id,
                            'name' => $row->name,
                            'searchname' => $row->searchname,
                            'plainsearchname' => $searchName,
                            'fromname' => $row->fromname,
                            'categories_id' => $row->categories_id,
                            'filename' => $row->filename,
                            'add_date' => $row->adddate,
                            'post_date' => $row->postdate,
                        ];
                        break;

                    case 'predb':
                        $data['body'][] = [
                            'index' => [
                                '_index' => 'predb',
                                '_id' => $row->id,
                            ],
                        ];
                        $data['body'][] = [
                            'id' => $row->id,
                            'title' => $row->title,
                            'filename' => $row->filename,
                            'source' => $row->source,
                        ];
                        break;
                }
            }
            if (! empty($data['body'])) {
                // Stop and send the bulk request
                $responses = \Elasticsearch::bulk($data);

                // erase the old bulk request
                $data = ['body' => []];

                // unset the bulk response when you are done to save memory
                unset($responses);

                echo '.';
            }
        }

        // Send the last batch if it exists
        if (! empty($data['body'])) {
            $responses = \Elasticsearch::bulk($data);
        }
        echo "\n[Done]\n";
    } else {
        exit(
            "Argument 1 is the index name, releases and predb are the only supported ones currently.\n".
            "Argument 2 is optional, max number of rows to send to ES at a time, 10,000 is the default if not set.\n"
        );
    }
}

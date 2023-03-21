<?php

require_once dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use App\Models\Predb;
use App\Models\Release;
use Blacklight\ManticoreSearch;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

if (! isset($argv[1])) {
    exit(
        "Argument 1 is the index name, releases_rt and predb_rt are the only supported ones currently.\n".
        "Argument 2 is optional, max number of rows to send to sphinx at a time, 10,000 is the default if not set.\n".
        "              The value of 10,000 is good for the default sphinx.conf max_packet_size of 8M, if you want\n".
        "              to raise this higher than 10,000, raise the sphinx.conf max_packet_size higher than  8M.\n".
        "              If you have many releases, raise the sphinx.conf max_packet_size to 128M (the maximum), restart sphinx and\n".
        "              and set Argument 2 to 250,000. This will speed up the script tremendously.\n"
    );
}

populate_rt($argv[1], (isset($argv[2]) && is_numeric($argv[2]) && $argv[2] > 0 ? $argv[2] : 10000));

// Bulk insert releases into Manticore RT index.
function populate_rt($table, $max)
{
    $allowedIndexes = ['releases_rt', 'predb_rt'];
    if (\in_array($table, $allowedIndexes, true)) {
        $manticore = new ManticoreSearch();
        $manticore->truncateRTIndex(Arr::wrap($table));
        if ($table === 'releases_rt') {
            DB::statement('SET SESSION group_concat_max_len=16384;');
            $query = 'SELECT r.id, r.name, r.searchname, r.fromname, r.categories_id, IFNULL(GROUP_CONCAT(rf.name SEPARATOR " "),"") filename
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

        if ($table === 'predb_rt') {
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
        echo "[Starting to populate sphinx RT index $table with $total releases.]".PHP_EOL;
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
                    case 'releases_rt':
                        $manticore->insertRelease([
                            'id' => $row->id,
                            'name' => $row->name,
                            'searchname' => $row->searchname,
                            'fromname' => $row->fromname,
                            'categories_id' => (string) $row->categories_id,
                            'filename' => $row->filename,
                        ]);
                        break;

                    case 'predb_rt':
                        $manticore->insertPredb([
                            'id' => $row->id,
                            'title' => $row->title,
                            'filename' => $row->filename,
                            'source' => $row->source,
                        ]);
                        break;
                }
            }

            echo '.';
        }
        echo "\n[Done]\n";
    } else {
        exit(
            "Argument 1 is the index name, releases_rt and predb_rt are the only supported ones currently.\n".
            "Argument 2 is optional, max number of rows to send to sphinx at a time, 10,000 is the default if not set.\n".
            "              The value of 10,000 is good for the default sphinx.conf max_packet_size of 8M, if you want\n".
            "              to raise this higher than 10,000, raise the sphinx.conf max_packet_size higher than  8M.\n".
            "              If you have many releases, raise the sphinx.conf max_packet_size to 128M (the maximum), restart sphinx and\n".
            "              and set Argument 2 to 250,000. This will speed up the script tremendously.\n"
        );
    }
}

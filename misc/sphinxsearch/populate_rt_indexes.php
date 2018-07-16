<?php

require_once dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use App\Models\Release;
use Blacklight\SphinxSearch;
use Illuminate\Support\Facades\DB;

if (! isset($argv[1]) || $argv[1] !== 'releases_rt') {
    exit(
            "Argument 1 is the index name, releases_rt are the only supported ones currently.\n".
            "Argument 2 is optional, max number of rows to send to sphinx at a time, 10,000 is the default if not set.\n".
            "              The value of 10,000 is good for the default sphinx.conf max_packet_size of 8M, if you want\n".
            "              to raise this higher than 10,000, raise the sphinx.conf max_packet_size higher than  8M.\n".
            "              If you have many releases, raise the sphinx.conf max_packet_size to 128M (the maximum), restart sphinx and\n".
            "              and set Argument 2 to 250,000. This will speed up the script tremendously.\n"
    );
} else {
    populate_rt($argv[1], (isset($argv[2]) && is_numeric($argv[2]) && $argv[2] > 0 ? $argv[2] : 10000));
}

// Bulk insert releases into sphinx RT index.
function populate_rt($table, $max)
{
    if ($table === 'releases_rt') {
        DB::unprepared('SET SESSION group_concat_max_len=16384;');
        DB::commit();
        $query = 'SELECT r.id, r.name, r.searchname, r.fromname, IFNULL(GROUP_CONCAT(rf.name SEPARATOR " "),"") filename
				FROM releases r
				LEFT JOIN release_files rf ON(r.id=rf.releases_id)
				WHERE r.id > %d
				GROUP BY r.id
				ORDER BY r.id ASC
				LIMIT %d';

        $totals = Release::query()->selectRaw('COUNT(id) AS c, MIN(id) AS min')->first();
        if (! $totals) {
            exit("Could not get database information for releases table.\n");
        }
        $total = $totals['c'];
        $minId = $totals['min'];
    }

    try {
        $sphinx = new SphinxSearch();
        $lastId = $minId - 1;
        echo "[Starting to populate sphinx RT index $table with $total releases.]".PHP_EOL;
        for ($i = $minId; $i <= ($total + $max + $minId); $i += $max) {
            $rows = DB::select(sprintf($query, $lastId, $max));
            DB::commit();
            if (! $rows) {
                continue;
            }

            $tempString = '';
            foreach ($rows as $row) {
                if ($row->id > $lastId) {
                    $lastId = $row->id;
                }
                switch ($table) {
                    case 'releases_rt':
                        $sphinx->insertRelease(
                            [
                                'id' => $row->id,
                                'name' => $row->name,
                                'searchname' => $row->searchname,
                                'fromname' => $row->fromname,
                                'filename' => $row->filename,
                            ]
                        );
                        break;
                }
            }
            if (! $tempString) {
                continue;
            }
            echo '.';
        }
        echo "\n[Done]\n";
    } catch (Exception $e) {
        echo $e->getMessage();
    }
}

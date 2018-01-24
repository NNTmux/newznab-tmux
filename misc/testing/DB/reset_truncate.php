<?php

require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use nntmux\db\DB;

$pdo = new DB();

if (isset($argv[1]) && ($argv[1] === 'true' || $argv[1] === 'drop')) {
    $pdo->queryExec('UPDATE groups SET first_record = 0, first_record_postdate = NULL, last_record = 0, last_record_postdate = NULL, last_updated = NULL');
    echo $pdo->log->primary('Reseting all groups completed.');
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0;');

    $arr = ['parts', 'missed_parts', 'binaries', 'collections', 'multigroup_parts', 'multigroup_missed_parts', 'multigroup_binaries', 'multigroup_collections'];

    foreach ($arr as &$value) {
        $rel = $pdo->queryExec("TRUNCATE TABLE $value");
        if ($rel !== false) {
            echo $pdo->log->primary("Truncating ${value} completed.");
        }
    }
    unset($value);

    $sql = 'SHOW table status';

    $tables = $pdo->query($sql);
    foreach ($tables as $row) {
        $tbl = $row['name'];
        if (preg_match('/collections_\d+/', $tbl) || preg_match('/binaries_\d+/', $tbl) || preg_match('/parts_\d+/', $tbl) || preg_match('/missed_parts_\d+/', $tbl) || preg_match('/\d+_collections/', $tbl) || preg_match('/\d+_binaries/', $tbl) || preg_match('/\d+_parts/', $tbl) || preg_match('/\d+_missed_parts_\d+/', $tbl)) {
            if ($argv[1] === 'drop') {
                $rel = $pdo->queryDirect(sprintf('DROP TABLE %s', $tbl));
                if ($rel !== false) {
                    echo $pdo->log->primary("Dropping ${tbl} completed.");
                }
            } else {
                $rel = $pdo->queryDirect(sprintf('TRUNCATE TABLE %s', $tbl));
                if ($rel !== false) {
                    echo $pdo->log->primary("Truncating ${tbl} completed.");
                }
            }
        }
    }

    $delcount = $pdo->queryDirect('DELETE FROM releases WHERE nzbstatus = 0');
    echo $pdo->log->primary($delcount->rowCount().' releases had no nzb, deleted.');
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1;');
} else {
    exit($pdo->log->error(
        "\nThis script removes releases with no NZBs, resets all groups, truncates or drops(tpg) \n"
        ."article tables. All other releases are left alone.\n"
        ."php $argv[0] [true, drop]   ...: To reset all groups and truncate/drop the tables.\n"
    )
    );
}

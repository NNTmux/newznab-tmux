<?php
// This script removes releases with no NZBs, resets all groups, truncates article tables. All other releases are left alone.
require_once(dirname(__FILE__) . "/../../../bin/config.php");
require_once(WWW_DIR . "/lib/framework/db.php");
require_once(WWW_DIR . "/lib/site.php");

$pdo = new DB();
$s = new Sites();
$site = $s->get();

if (isset($argv[1]) && ($argv[1] == "true" || $argv[1] == "drop")) {
	$pdo->queryExec("UPDATE groups SET first_record = 0, first_record_postdate = NULL, last_record = 0, last_record_postdate = NULL, last_updated = NULL");
	echo $pdo->log->primary("Reseting all groups completed.");

	$arr = array("parts", "partrepair", "binaries", "collections");
	foreach ($arr as &$value) {
		$rel = $pdo->queryExec("TRUNCATE TABLE $value");
		if ($rel !== false) {
			echo $pdo->log->primary("Truncating ${value} completed.");
		}
	}
	unset($value);

	$tpg = $site->tablepergroup;
	$tablepergroup = (!empty($tpg)) ? $tpg : 0;

	if ($tablepergroup == 1) {
		$sql = 'SHOW table status';

		$tables = $pdo->query($sql);
		foreach ($tables as $row) {
			$tbl = $row['Name'];
			if (preg_match('/binaries_\d+/', $tbl) || preg_match('/parts_\d+/', $tbl) || preg_match('/partrepair_\d+/', $tbl) || preg_match('/\d+_collections/', $tbl) || preg_match('/\d+_binaries/', $tbl) || preg_match('/\d+_parts/', $tbl) || preg_match('/\d+_partrepair_\d+/', $tbl)) {
				if ($argv[1] == "drop") {
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
	}

	$delcount = $pdo->queryDirect("DELETE FROM releases WHERE nzbstatus = 0");
	echo $pdo->log->primary($delcount->rowCount() . " releases had no nzb, deleted.");
} else {
	exit($pdo->log->error("\nThis script removes releases with no NZBs, resets all groups, truncates or drops(tpg) \n"
		. "article tables. All other releases are left alone.\n"
		. "php $argv[0] [true, drop]   ...: To reset all groups and truncate/drop the tables.\n"
	));
}
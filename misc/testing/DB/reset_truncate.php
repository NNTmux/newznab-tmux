<?php
require_once realpath(dirname(dirname(dirname(__DIR__))) . DIRECTORY_SEPARATOR . 'bootstrap.php');

use app\models\Settings;
use nntmux\ColorCLI;
use nntmux\db\DB;

$pdo = new DB();

if (isset($argv[1]) && ($argv[1] === 'true' || $argv[1] === 'drop')) {
	$pdo->queryExec('UPDATE groups SET first_record = 0, first_record_postdate = NULL, last_record = 0, last_record_postdate = NULL, last_updated = NULL');
	echo ColorCLI::primary('Reseting all groups completed.');

	$sql = "SELECT CALL loop_cbpm('truncate)";
	echo ColorCLI::primary('Truncating binaries, collections, missed_parts and parts tables...');
	$result = $pdo->query($sql);
	echo ColorCLI::primary('Truncating completed.');

	$tpg = Settings::value('..tablepergroup');
	$tablepergroup = (!empty($tpg)) ? $tpg : 0;

	if ($tablepergroup === 1) {
		$sql = 'SHOW table status';

		$tables = $pdo->query($sql);
		foreach ($tables as $row) {
			$tbl = $row['name'];
			if (preg_match('/collections_\d+/', $tbl) ||  preg_match('/binaries_\d+/', $tbl) || preg_match('/parts_\d+/', $tbl) || preg_match('/partrepair_\d+/', $tbl) || preg_match('/\d+_collections/', $tbl) || preg_match('/\d+_binaries/', $tbl) || preg_match('/\d+_parts/', $tbl) || preg_match('/\d+_partrepair_\d+/', $tbl)) {
				if ($argv[1] === 'drop') {
					$rel = $pdo->queryDirect(sprintf('DROP TABLE %s', $tbl));
					if ($rel !== false) {
						echo ColorCLI::primary("Dropping ${tbl} completed.");
					}
				} else {
					$rel = $pdo->queryDirect(sprintf('TRUNCATE TABLE %s', $tbl));
					if ($rel !== false) {
						echo ColorCLI::primary("Truncating ${tbl} completed.");
					}
				}
			}
		}
	}

	$delcount = $pdo->queryDirect('DELETE FROM releases WHERE nzbstatus = 0');
	echo ColorCLI::primary($delcount->rowCount() . ' releases had no nzb, deleted.');
} else {
	exit(ColorCLI::error("\nThis script removes releases with no NZBs, resets all groups, truncates or drops(tpg) \n"
		. "article tables. All other releases are left alone.\n"
		. "php $argv[0] [true, drop]   ...: To reset all groups and truncate/drop the tables.\n"
	));
}

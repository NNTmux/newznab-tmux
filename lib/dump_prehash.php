<?php
require_once(dirname(__FILE__)."/../bin/config.php");
require_once(WWW_DIR."/lib/framework/db.php");
require_once("ColorCLI.php");

$db = new DB();
$c = new ColorCLI();

if (isset($argv[2])) {
	if (!preg_match('/^\//', $argv[2])) {
		$path = getcwd() . '/' . $argv[2];
	} else {
		$path = $argv[2];
	}
}

if (isset($argv[1]) && $argv[1] == 'export' && isset($argv[2])) {
	if (!preg_match('/\.csv$/', $path)) {
		$path = dirname($path) . '/' . basename($path) . '/prebd_dump.csv';
	} else {
		$path = $path;
	}
	if (!preg_match('/^\//', $path)) {
		$path = getcwd() . '/' . $path;
	}

	if (file_exists($path) && is_file($path)) {
		unlink($path);
	}
	if (isset($argv[3])) {
		$table = $argv[3];
	} else {
		$table = 'prehash';
	}
	$db->queryDirect("SELECT title, nfo, size, files, nuked, nukereason, category, predate, source, md5, requestID, g.name FROM " . $table . " p LEFT OUTER JOIN groups g ON p.groupID = g.ID INTO OUTFILE '" . $path . "' FIELDS TERMINATED BY '\t\t' ENCLOSED BY \"'\" LINES TERMINATED BY '\r\n'");
} else if (isset($argv[1]) && ($argv[1] == 'local' || $argv[1] == 'remote') && isset($argv[2]) && is_file($argv[2])) {
	if (!preg_match('/^\//', $path)) {
		$path = require_once getcwd() . '/' . $argv[2];
	}
	if (isset($argv[3])) {
		$table = $argv[3];
	} else {
		$table = 'prehash';
	}

	// Create temp table to allow updating
	echo $c->info("Creating temporary table.");
	$db->query('DROP TABLE IF EXISTS tmp_pre');
	$db->query('CREATE TABLE tmp_pre LIKE prehash');

	// Drop indexes on tmp_pre
    echo $c->info("Dropping indexes from temporary table.");
	$db->query('ALTER TABLE tmp_pre DROP INDEX `ix_prehash_md5`, DROP INDEX `ix_prehash_nfo`, DROP INDEX `ix_prehash_predate`, DROP INDEX `ix_prehash_source`, DROP INDEX `ix_prehash_title`, DROP INDEX `ix_prehash_requestid`');
	$db->query('ALTER TABLE tmp_pre ADD COLUMN groupname VARCHAR (255)');

	// Import file into tmp_pre
    echo $c->info("Importing data into temporary table.");
	if ($argv[1] == 'remote') {
		$db->queryDirect("LOAD DATA LOCAL INFILE '" . $path . "' IGNORE INTO TABLE tmp_pre FIELDS TERMINATED BY '\t\t' ENCLOSED BY \"'\" LINES TERMINATED BY '\r\n' (title, nfo, size, files, nuked, nukereason, category, predate, source, md5, requestID, groupname)");
	} else {
		$db->queryDirect("LOAD DATA INFILE '" . $path . "' IGNORE INTO TABLE tmp_pre FIELDS TERMINATED BY '\t\t' ENCLOSED BY \"'\" LINES TERMINATED BY '\r\n' (title, nfo, size, category, predate, source, md5, requestID, groupname)");
	}

	// Insert and update table
    echo $c->info("Inserting data into" . $table.  "table.");
	$db->queryDirect('INSERT INTO ' . $table . ' (title, nfo, size, files, nuked, nukereason, category, predate, source, md5, requestID, groupID) SELECT t.title, t.nfo, t.size, t.category, t.predate, t.source, t.md5, t.requestID, IF(g.ID IS NOT NULL, g.ID, 0) FROM tmp_pre t LEFT OUTER JOIN groups g ON t.groupname = g.name ON DUPLICATE KEY UPDATE prehash.nfo = IF(prehash.nfo is null, t.nfo, prehash.nfo), prehash.size = IF(prehash.size is null, t.size, prehash.size), prehash.files = IF(prehash.files is null, t.files, prehash.files), prehash.nuked = IF(prehash.nuked is null, t.nuked, prehash.nuked), prehash.nukereason = IF(prehash.nukereason is null, t.nukereason, prehash.nukereason), prehash.category = IF(prehash.category is null, t.category, prehash.category), prehash.requestID = IF(prehash.requestID = 0, t.requestID, prehash.requestID), prehash.groupID = IF(g.ID IS NOT NULL, g.ID, 0)');

	// Drop tmp_pre table
    echo $c->info("Dropping temporary table");
	$db->query('DROP TABLE IF EXISTS tmp_pre');
    echo $c->info("Import complete");
} else {
	exit($c->error("\nThis script can export or import a prehash dump file. You may use the full path, or a relative path.\n"
					. "For importing, the script insert new rows and update existing matched rows. For databases not on the local system, use remote, else use local.\n"
					. "For exporting, the path must be writeable by mysql, any existing file[prebd_dump.csv] will be overwritten.\n\n"
					. "php $argv[0] export /path/to/write/to                     ...: To export.\n"
					. "php $argv[0] [remote | local] /path/to/filename           ...: To import.\n"));
}
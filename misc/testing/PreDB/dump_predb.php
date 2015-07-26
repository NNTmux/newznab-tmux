<?php
require_once dirname(__FILE__) . '/../../../www/config.php';

use newznab\db\Settings;

$pdo = new Settings();

$path = '';
if (isset($argv[2])) {
	if (!preg_match('/^\//', $argv[2])) {
		$path = getcwd() . '/' . $argv[2];
	} else {
		$path = $argv[2];
	}
}

if (isset($argv[1]) && $argv[1] == 'export' && isset($argv[2])) {
	if (!preg_match('/\.csv$/', $path)) {
		$path = dirname($path) . '/' . basename($path) . '/predb_dump.csv';
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
	echo  $pdo->log->header("SELECT title, nfo, size, files, filename, nuked, nukereason, category, predate, source, requestid, g.name FROM " . $table . " p LEFT OUTER JOIN groups g ON p.groupid = g.id INTO OUTFILE '" . $path . "' FIELDS TERMINATED BY '\\t\\t' ENCLOSED BY \"'\" LINES TERMINATED BY '\\r\\n';n");
	$pdo->queryDirect("SELECT title, nfo, size, files, filename, nuked, nukereason, category, predate, source, requestid, g.name FROM " . $table . " p LEFT OUTER JOIN groups g ON p.groupid = g.id INTO OUTFILE '" . $path . "' FIELDS TERMINATED BY '\t\t' ENCLOSED BY \"'\" LINES TERMINATED BY '\r\n'");
} else if (isset($argv[1]) && ($argv[1] == 'local' || $argv[1] == 'remote') && isset($argv[2]) && is_file($argv[2])) {
	if (!preg_match('/^\//', $path)) {
		$path = require_once getcwd() . '/' . $argv[2];
	}
	if (isset($argv[3])) {
		$table = $argv[3];
	} else {
		$table = 'prehash';
	}

	// Truncate prehash_imports to clear any old data
	$pdo->queryDirect("TRUNCATE TABLE prehash_imports");

	// Import file into prehash_imports
	if ($argv[1] == 'remote') {
		echo  $pdo->log->header("LOAD DATA LOCAL INFILE '" . $path . "' IGNORE into table prehash_imports FIELDS TERMINATED BY '\\t\\t' ENCLOSED BY \"'\" LINES TERMINATED BY '\\r\\n' (title, nfo, size, files, filename, nuked, nukereason, category, predate, source, requestid, groupname);");
		$pdo->queryDirect("LOAD DATA LOCAL INFILE '" . $path . "' IGNORE into table prehash_imports FIELDS TERMINATED BY '\t\t' ENCLOSED BY \"'\" LINES TERMINATED BY '\r\n' (title, nfo, size, files, filename, nuked, nukereason, category, predate, source, requestid, groupname)");
	} else {
		echo  $pdo->log->header("LOAD DATA INFILE '" . $path . "' IGNORE into table prehash_imports FIELDS TERMINATED BY '\\t\\t' ENCLOSED BY \"'\" LINES TERMINATED BY '\\r\\n' (title, nfo, size, files, filename, nuked, nukereason, category, predate, source, requestid, groupname);");
		$pdo->queryDirect("LOAD DATA INFILE '" . $path . "' IGNORE into table prehash_imports FIELDS TERMINATED BY '\t\t' ENCLOSED BY \"'\" LINES TERMINATED BY '\r\n' (title, nfo, size, files, filename, nuked, nukereason, category, predate, source, requestid, groupname)");
	}

	// Remove any titles where length <=8
	echo $pdo->log->info("Deleting any records where title <=8 from Temporary Table");
	$pdo->queryDirect("DELETE FROM prehash_imports WHERE LENGTH(title) <= 8");

	// Add any groups that do not currently exist
	$sqlAddGroups = <<<SQL_ADD_GROUPS
INSERT IGNORE INTO groups (`name`, description)
	SELECT groupname, 'Added by prehash import script'
	FROM prehash_imports AS t LEFT JOIN groups AS g ON t.`groupname` = g.`name`
	WHERE t.`groupname` IS NOT NULL AND g.`name` IS NULL
	GROUP BY groupname;
SQL_ADD_GROUPS;
	$pdo->queryDirect($sqlAddGroups);

	// Drop triggers on prehash
	echo $pdo->log->info("Dropping predb_hashes triggers");
	$pdo->queryDirect("DROP TRIGGER IF EXISTS insert_hashes");
	$pdo->queryDirect("DROP TRIGGER IF EXISTS update_hashes");

	// Insert and update table
	$sqlInsert = <<<SQL_INSERT
INSERT INTO $table (title, nfo, size, files, filename, nuked, nukereason, category, predate, SOURCE, requestid, groupid)
  SELECT t.title, t.nfo, t.size, t.files, t.filename, t.nuked, t.nukereason, t.category, t.predate, t.source, t.requestid, IF(g.id IS NOT NULL, g.id, 0)
    FROM prehash_imports AS t
	LEFT OUTER JOIN groups g ON t.groupname = g.name ON DUPLICATE KEY UPDATE prehash.nfo = IF(prehash.nfo IS NULL, t.nfo, prehash.nfo),
	  prehash.size = IF(prehash.size IS NULL, t.size, prehash.size),
	  prehash.files = IF(prehash.files IS NULL, t.files, prehash.files),
	  prehash.filename = IF(prehash.filename = '', t.filename, prehash.filename),
	  prehash.nuked = IF(t.nuked > 0, t.nuked, prehash.nuked),
	  prehash.nukereason = IF(t.nuked > 0, t.nukereason, prehash.nukereason),
	  prehash.category = IF(prehash.category IS NULL, t.category, prehash.category),
	  prehash.requestid = IF(prehash.requestid = 0, t.requestid, prehash.requestid),
	  prehash.groupid = IF(g.id IS NOT NULL, g.id, 0);
SQL_INSERT;
	echo $pdo->log->primary($sqlInsert);
	$pdo->queryDirect($sqlInsert);

	// Add hashes
	echo $pdo->log->info("Adding predb_hashes entries");
	$pdo->queryDirect("INSERT IGNORE INTO predb_hashes (pre_id, hashes) (SELECT id, CONCAT_WS(',', MD5(title), MD5(MD5(title)), SHA1(title)) FROM prehash)");

	// Re-add triggers on prehash
	echo $pdo->log->info("Adding predb_hashes triggers");
	$pdo->exec("CREATE TRIGGER insert_hashes AFTER INSERT ON prehash FOR EACH ROW BEGIN INSERT INTO predb_hashes (pre_id, hashes) VALUES (NEW.id, CONCAT_WS(',', MD5(NEW.title), MD5(MD5(NEW.title)), SHA1(NEW.title))); END;");
	$pdo->exec("CREATE TRIGGER update_hashes AFTER UPDATE ON prehash FOR EACH ROW BEGIN IF NEW.title != OLD.title THEN UPDATE predb_hashes SET hashes = CONCAT_WS(',', MD5(NEW.title), MD5(MD5(NEW.title)), SHA1(NEW.title)) WHERE pre_id = OLD.id; END IF; END;");

	$pdo->queryDirect("TRUNCATE TABLE prehash_imports");
} else {
	exit($pdo->log->error("\nThis script can export or import a prehash dump file. You may use the full path, or a relative path.\n"
					. "For importing, the script insert new rows and update existing matched rows. For databases not on the local system, use remote, else use local.\n"
					. "For exporting, the path must be writeable by mysql, any existing file[predb_dump.csv] will be
					overwritten.\n\n"
					. "php dump_predb.php export /path/to/write/to                     ...: To export.\n"
					. "php dump_predb.php [remote | local] /path/to/filename           ...: To import.\n"));
}

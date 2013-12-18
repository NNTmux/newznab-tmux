<?php
require_once(dirname(__FILE__)."/../bin/config.php");
require_once(WWW_DIR."/lib/framework/db.php");
require_once("ColorCLI.php");

$c = new ColorCLI;

if (isset($argv[2]))
{
	if (!preg_match('/^\//', $argv[2]))
		$path = getcwd() . '/' . $argv[2];
	else
		$path = $argv[2];
}

if (isset($argv[1]) && $argv[1] == 'export' && isset($argv[2]))
{
	if (!preg_match('/\.csv$/', $path))
		$path = dirname($path).'/'.basename($path).'/predb_dump.csv';
	else
		$path = $path;
	if (!preg_match('/^\//', $path))
		$path = getcwd() . '/' . $path;

	if (file_exists($path) && is_file($path))
		unlink($path);
	if (isset($argv[3]))
		$table = $argv[3];
	else
		$table = 'prehash';
	$db = new DB();
	$db->queryDirect("SELECT title, nfo, size, category, predate, adddate, source, hash, requestID, groupID INTO OUTFILE '".$path."' FROM ".$table);
}
else if (isset($argv[1]) && ($argv[1] == 'local' || $argv[1] == 'remote') && isset($argv[2]) && is_file($argv[2]))
{
	if (!preg_match('/^\//', $path))
		$path = require_once getcwd() . '/' . $argv[2];
	if (isset($argv[3]))
		$table = $argv[3];
	else
		$table = 'prehash';

	$db = new DB();

	// Create temp table to allow updating
	$db->exec('DROP TABLE IF EXISTS tmp_pre');
	$db->exec('CREATE TABLE tmp_pre LIKE prehash');

	// Drop indexes on tmp_pre
	$db->exec('ALTER TABLE tmp_pre DROP INDEX `ix_prehash_md5`, DROP INDEX `ix_prehash_nfo`, DROP INDEX `ix_prehash_predate`, DROP INDEX `ix_prehash_adddate`, DROP INDEX `ix_prehash_source`, DROP INDEX `ix_prehash_title`, DROP INDEX `ix_prehash_requestid`');

	// Import file into tmp_pre
	if ($argv[1] == 'remote')
	{
		$db->queryDirect("LOAD DATA LOCAL INFILE '".$path."' IGNORE into table tmp_pre (title, nfo, size, category, predate, adddate, source, hash, requestID, groupID)");
	}
	else
	{
		$db->queryDirect("LOAD DATA INFILE '".$path."' IGNORE into table tmp_pre (title, nfo, size, category, predate, adddate, source, hash, requestID, groupID)");
	}

	// Insert and update table
	$db->queryDirect('INSERT INTO '.$table.' (title, nfo, size, category, predate, adddate, source, hash, requestID, groupID) SELECT t.title, t.nfo, t.size, t.category, t.predate, t.adddate, t.source, t.hash, t.requestID, t.groupID FROM tmp_pre t ON DUPLICATE KEY UPDATE prehash.nfo = IF(prehash.nfo is null, t.nfo, prehash.nfo), prehash.size = IF(prehash.size is null, t.size, prehash.size), prehash.category = IF(prehash.category is null, t.category, prehash.category), prehash.requestID = IF(prehash.requestID = 0, t.requestID, prehash.requestID), prehash.groupID = IF(prehash.groupID = 0, t.groupID, prehash.groupID)');

	// Drop tmp_pre table
	$db->exec('DROP TABLE IF EXISTS tmp_pre');
}
else
	exit($c->error("\nThis script can export or import a predb dump file. You may use the full path, or a relative path.".
		"\nFor importing, the script insert new rows and update existing matched rows. For databases not on the local system, use remote, else use local.".
		"\nFor exporting, the path must be writeable by mysql, any existing file[prebd_dump.csv] will be overwritten.\nTo export:\nphp dump_predb.php export /path/to/write/to\n\nTo import:\nphp dump_predb.php [remote | local] /path/to/filename"));

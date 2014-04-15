<?php
require_once(dirname(__FILE__)."/../bin/config.php");
require_once(WWW_DIR."/lib/framework/db.php");
require_once(WWW_DIR."/lib/releases.php");
require_once(WWW_DIR."/lib/releaseimage.php");
require_once(WWW_DIR."/lib/nzb.php");
require_once(WWW_DIR."/lib/site.php");;
require_once("consoletools.php");
require_once("ColorCLI.php");
require_once("functions.php");

//This script is ported from nZEDb and adapted for newznab.

$c = new ColorCLI();
if (!isset($agrv[1]) && !is_numeric($argv[1])) {
	exit($c->error("\nIncorrect argument suppplied. This script will delete all duplicate releases matching on name, fromname, groupid and size.\n\n"
		. "php $argv[0] 10         ...: To delete all duplicates added within the last 10 hours.\n"
		. "php $argv[0] 0          ...: To delete all duplicates.\n"
		. "php $argv[0] 10 dupes/  ...: To delete all duplicates added within the last 10 hours and save a copy of the nzb to dupes folder.\n"));
}

$crosspostt = $argv[1];
$db = new DB();
$c = new ColorCLI();
$functions = new Functions();
$releases = new Releases();
$count = $total = 0;
$nzb = new NZB();
$ri = new ReleaseImage();
$s = new Sites();
$site = $s->get();
$consoleTools = new ConsoleTools();

if ($crosspostt != 0) {
		$query = sprintf('SELECT ID, guid FROM releases WHERE adddate > (NOW() - INTERVAL %d HOUR) GROUP BY name, fromname, groupID, size HAVING COUNT(*) > 1 ORDER BY ID DESC', $crosspostt);
	}
else {
	$query = sprintf('SELECT ID, guid FROM releases GROUP BY name, fromname, groupID, size HAVING COUNT(*) > 1 ORDER BY ID DESC');
}

do {
	$resrel = $db->queryDirect($query);
	$total = $resrel->rowCount();
	echo $c->header(number_format($total) . " Releases have Duplicates");
	if (count($resrel) > 0) {
		foreach ($resrel as $rowrel) {
			$nzbpath = $nzb->getNZBPath($rowrel['guid'], $site->nzbpath, false);
			if (isset($argv[2]) && is_dir($argv[2])) {
				$path = $argv[2];
				if (substr($path, strlen($path) - 1) != '/') {
					$path = $path . "/";
				}
				if (!file_exists($path . $rowrel['guid'] . ".nzb.gz") && file_exists($nzbpath)) {
					if (@copy($nzbpath, $path . $rowrel['guid'] . ".nzb.gz") !== true) {
						exit("\n" . $c->error("\nUnable to write " . $path . $rowrel['guid'] . ".nzb.gz"));
					}
				}
			}
			if ($functions->fastDelete($rowrel['ID'], $rowrel['guid'], $site) !== false) {
				$consoleTools->overWritePrimary('Deleted: ' . number_format(++$count) . " Duplicate Releases");
			}
		}
	}
	echo "\n\n";
	$consoleTools = new ConsoleTools();
} while ($total > 0);
echo $c->header("\nDeleted ". number_format($count) . " Duplicate Releases");
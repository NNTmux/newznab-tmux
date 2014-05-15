<?php
require_once(dirname(__FILE__) . "/../bin/config.php");
require_once(WWW_DIR . "/lib/framework/db.php");
require_once("namefixer.php");
require_once("ColorCLI.php");
require_once("consoletools.php");


$c = new ColorCLI();
if (!isset($argv[1]) && (($argv[1] !== "console") || ($argv[1] !== "expanded") OR ($argv[1] !== "misc") OR ($argv[1] !== "xxx"))) {
	exit($c->error(
		"\nThis script tries to match release filenames to PreDB filenames.\n"
		. "To display the changes, use 'show' as the second argument.\n\n"
		. "php match_prefiles.php full		...: to run on full database.\n"
		. "php match_prefiles.php expanded		...: to run on console, xxx, and misc releases.\n"
		. "php match_prefiles.php console		...: to run on all console releases.\n"
		. "php match_prefiles.php misc		...: to run on all misc releases.\n"
		. "php match_prefiles.php xxx		...: to run on all xxx releases.\n"
		. "php match_prefiles.php all		...: to run on all releases with filenames (including previously renamed).\n"
		. "\nCurrently the only available methods are: console, misc, and xxx.  You can also use the expanded keyword to run against all of these choices.\n"
	));
}

echo $c->header("\nMatch PreFiles (${argv[1]}) Started at " . date('g:i:s'));
echo $c->primary("Matching prehash filename to SUBSTRING_INDEX releasefiles.name.\n");

preFileName($argv);

function preFileName($argv)
{
	$db = new DB();
	$timestart = TIME();
	$namefixer = new Namefixer();
	$c = new ColorCLI();

	$qrycat = $renamed = $orderby = $limit = '';
	$regfilter = "AND rf.name NOT REGEXP '\\\\\\\\' AND rf.name REGEXP BINARY '^[a-z0-9]{1,20}-[a-z0-9]{1,20}\..{3}$'";
	$rfname = "SUBSTRING_INDEX(rf.name, '.', 1)";


	if (isset($argv[1]) && $argv[1] === "full") {
		$rfname = "rf.name";
	} else if (isset($argv[1]) && $argv[1] === "expanded") {
		$qrycat = "AND (r.categoryID BETWEEN 1000 AND 1999 OR r.categoryID BETWEEN 6000 AND 6999 OR r.categoryID BETWEEN 8000 AND 8999) ";

	} else if (isset($argv[1]) && $argv[1] === "console") {
		$qrycat = "AND r.categoryID BETWEEN 1000 AND 1999 ";
	} else if (isset($argv[1]) && $argv[1] === "xxx") {
		$qrycat = "AND r.categoryID BETWEEN 6000 AND 6999 ";
	} else if (isset($argv[1]) && $argv[1] === "misc") {
		$qrycat = "AND r.categoryID BETWEEN 8000 AND 8999 ";

	} else if (isset($argv[1]) && is_numeric($argv[1])) {
		$limit = "LIMIT " . $argv[1];
		$orderby = "ORDER BY postdate DESC";
	}

	echo $c->headerOver(sprintf("SELECT DISTINCT r.ID AS releaseID, r.name, r.searchname, r.groupID, r.categoryID, %s AS filename " .
				"FROM releases r INNER JOIN releasefiles rf ON r.ID = rf.releaseID " .
				"WHERE r.prehashID = 0 %s %s %s %s" .
				"GROUP BY r.ID %s", $rfname, $qrycat, $regfilter, $renamed, $orderby, $limit)) . "\n\n";
	$query = $db->queryDirect(sprintf("SELECT DISTINCT r.ID AS releaseID, r.name, r.searchname, r.groupID, r.categoryID, %s AS filename
						FROM releases r INNER JOIN releasefiles rf ON r.ID = rf.releaseID
						WHERE r.prehashID = 0 %s %s %s %s
						GROUP BY r.ID %s", $rfname, $qrycat, $regfilter, $renamed, $orderby, $limit));


	$total = $query->rowCount();
	$counter = $counted = 0;
	$show = (!isset($argv[2]) || $argv[2] !== 'show') ? 0 : 1;
	if ($total > 0) {
		echo $c->header("\n" . number_format($total) . ' releases to process.');
		sleep(2);
		$consoletools = new ConsoleTools();

		foreach ($query as $row) {
			$success = 0;
			$success = $namefixer->matchPredbFiles($row, 1, 1, true, $show);

			if ($success === 1) {
				$counted++;
			}
			if ($show === 0) {
				$consoletools->overWritePrimary("Renamed Releases: [" . number_format($counted) . "] " . $consoletools->percentString(++$counter, $total));
			}
		}
	}
	if ($total > 0) {
		echo $c->header("\nRenamed " . $counted . " releases in " . $consoletools->convertTime(TIME() - $timestart) . ".");
	} else {
		echo $c->info("\nNothing to do.");
	}
}
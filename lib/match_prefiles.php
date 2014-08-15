<?php
require_once(dirname(__FILE__) . "/../bin/config.php");
require_once(WWW_DIR . "/lib/framework/db.php");
require_once("namefixer.php");
require_once("ColorCLI.php");
require_once("consoletools.php");
require_once("functions.php");


$c = new ColorCLI();
$array = array("full", "expanded", "console", "misc", "xxx");

if (!in_array($argv[1], $array) || (isset($argv[2]) && $argv[2] !== 'show') || (isset($argv[3]) && !is_numeric($argv[3]))) {

	exit($c->error(
		"\nThis script tries to match release filenames to PreDB filenames.\n"
		. "To display the changes, use 'show' as the second argument. The optional third argument will limit the amount of filenames to attempt to match.\n\n"
		. "php match_prefiles.php full show	...: to run on full database and show renames.\n"
		. "php match_prefiles.php expanded		...: to run on console, xxx, and misc releases.\n"
		. "php match_prefiles.php console		...: to run on all console releases.\n"
		. "php match_prefiles.php misc		...: to run on all misc releases.\n"
		. "php match_prefiles.php xxx show 2000	...: to run on all xxx releases show renaming and limit to 2000 release filenames.\n"
		. "php match_prefiles.php all		...: to run on all releases with filenames (including previously renamed).\n"
		. "\nCurrently the only available methods are: console, misc, and xxx.  You can also use the expanded keyword to run against all of these choices or select full to run against everything.\n"
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

	$qrycat = $renamed = $regfilter = $orderby = $limit = '';
	$noslash = "AND rf.name NOT REGEXP '\\\\\\\\' ";
	$regfilter = "AND rf.name REGEXP BINARY '^[a-z0-9]{1,20}-[a-z0-9]{1,20}\..{3}$' ";
	$rfname = "SUBSTRING_INDEX(rf.name, '.', 1)";
	$orderby = "ORDER BY postdate ASC";

	if (isset($argv[1]) && $argv[1] === "full") {
		$rfname = "rf.name";
		$regfilter = "";
		$orderby = '';
	} else if (isset($argv[1]) && $argv[1] === "expanded") {
		$qrycat = "AND (r.categoryID BETWEEN 1000 AND 1999 OR r.categoryID BETWEEN 6000 AND 6999 OR r.categoryID BETWEEN 8000 AND 8999) ";

	} else if (isset($argv[1]) && $argv[1] === "console") {
		$qrycat = "AND r.categoryID BETWEEN 1000 AND 1999 ";
	} else if (isset($argv[1]) && $argv[1] === "xxx") {
		$qrycat = "AND r.categoryID BETWEEN 6000 AND 6999 ";
	} else if (isset($argv[1]) && $argv[1] === "misc") {
		$qrycat = "AND r.categoryID BETWEEN 8000 AND 8999 ";

	}

	if (isset($argv[3]) && is_numeric($argv[3])) {
		$limit = "LIMIT " . $argv[3];
	}

	echo $c->headerOver(sprintf("SELECT DISTINCT r.ID AS releaseID, r.name, r.searchname, r.groupID, r.categoryID, %s AS filename " .
				"FROM releases r INNER JOIN releasefiles rf ON r.ID = rf.releaseID " .
				"WHERE r.prehashID = 0 %s %s %s %s" .
				"GROUP BY r.ID %s", $rfname, $qrycat, $regfilter, $renamed, $orderby, $limit)) . "\n\n";
	$query = $db->queryDirect(sprintf("SELECT DISTINCT r.ID AS releaseID, r.name, r.searchname, r.groupID, r.categoryID, %s AS filename
						FROM releases r INNER JOIN releasefiles rf ON r.ID = rf.releaseID
						WHERE r.prehashID = 0 %s %s %s %s
						GROUP BY r.id %s %s", $rfname, $qrycat, $noslash, $regfilter, $renamed, $orderby, $limit
		)
	);

	$total = $query->rowCount();
	$counter = $counted = 0;
	$show = (!isset($argv[2]) || $argv[2] !== 'show') ? 0 : 1;

	if ($total > 0) {

		echo $c->header("\n" . number_format($total) . ' releases to process.');
		sleep(2);
		$consoletools = new ConsoleTools();
		$functions = new Functions();

		foreach ($query as $row) {
			$success = 0;
			if ($argv[1] === 'full') {
				//this function cuts the file extension off
				$row['filename'] = $functions->cutStringUsingLast('.', $row['filename'], "left", false);
				//if filename has a .part001, send it back to the function to cut the next period
				if (preg_match('/\.part\d+$/', $row['filename'])) {
					$row['filename'] = $functions->cutStringUsingLast('.', $row['filename'], "left", false);
				}
				//if filename has a .vol001, send it back to the function to cut the next period
				if (preg_match('/\.vol\d+$/', $row['filename'])) {
					$row['filename'] = $functions->cutStringUsingLast('.', $row['filename'], "left", false);
				}
			}
			if (isset($row['filename']) && $row['filename'] !== '') {
				$success = $namefixer->matchPredbFiles($row, 1, 1, true, $show, $argv[1]);
			}


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
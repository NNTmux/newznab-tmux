<?php
require_once(dirname(__FILE__) . "/../bin/config.php");
require_once(WWW_DIR . "/lib/framework/db.php");
require_once(WWW_DIR . "/lib/category.php");
require_once("consoletools.php");
require_once("ColorCLI.php");
require_once("namefixer.php");

$c = new ColorCLI();
if (!isset($argv[1]) || ( $argv[1] != "all" && $argv[1] != "full" && !is_numeric($argv[1]))) {
	exit($c->error(" This script tries to match a release name or searchname to a Prehash title by using Full Text Search Matching.\n"
		. "It will first parse PreDB titles to match, order by oldest to newest pre.\n\n"
		. "php predbftmatch.php 1000 show 1000	...: to limit to 1000 presently unsearched PreDB titles ordered by oldest to newest predate and show renaming offset title return by 1000.\n"
		. "php predbftmatch.php full show		...: to run on all unmatched PreDB titles and show renaming.\n"
		. "php predbftmatch.php all show		...: to run on all PreDB titles (Around 2-3 seconds per pre runtime).\n\n"
		. "Doing a limited search (first example) is recommended for testing.  As you match more PreDB IDs to your releases and search existing pres, the loops will get smaller and smaller.\n\n"
	));
}

$db = new DB();
$category = new Category();
$consoletools = new ConsoleTools();
$namefixer = new Namefixer();
$offset = '';

$timestart = TIME();
$counter = $counted = 0;

if (isset($argv[3]) && is_numeric($argv[3])) {
	$offset = " OFFSET " . $argv[3];
}

//Selects all Prehash Titles to Match Against
if (isset($argv[1]) && $argv[1] === "all") {
	$titles = $db->queryDirect("SELECT ID AS preid, title, source, searched FROM prehash
					WHERE LENGTH(title) >= 15 AND title NOT REGEXP '[\"\<\> ]'
					ORDER BY predate ASC");
//Selects all Prehash Titles that don't have a current match in releases (slower intial query but less loop time)
} else if (isset($argv[1]) && $argv[1] === "full") {
	$titles = $db->queryDirect("SELECT ID AS preid, title, source, searched FROM prehash
					WHERE LENGTH(title) >= 15 AND searched BETWEEN -5 AND 0
					AND title NOT REGEXP '[\"\<\> ]' ORDER BY predate ASC");
//Selects Prehash Titles where predate is greater than the past user selected number of hours
} else if (isset($argv[1]) && is_numeric($argv[1])) {
	$titles = $db->queryDirect(sprintf("SELECT ID AS preid, title, source, searched FROM prehash
						 WHERE LENGTH(title) >= 15 AND searched = 0
						 AND title NOT REGEXP '[\"\<\> ]' ORDER BY predate ASC LIMIT %d %s",
			$argv[1], $offset));
}

if (isset($argv[2]) && $argv[2] === "show") {
	$show = 1;
} else {
	$show = 0;
}

$total = $titles->rowCount();

if ($total > 1) {

	echo $c->header("\nMatching " . number_format($total) . " Prehash titles against release name or searchname.\n"
		. "'.' = No Match Found, '*' = Bad Match Parameters (Flood)\n\n");
	sleep(2);

	foreach ($titles as $row) {
		$matched = 0;
		$searched = 0;
		$matched = $namefixer->matchPredbFT($row, 1, 1, true, $show);
		//echo "Pre Title " . $row['title'] . " is translated to search string: ";
		//echo $c->header($matched);
		if ($matched > 0) {
			$searched = 1;
			$counted++;
		} elseif ($matched < 0) {
			$searched = -6;
			echo "*";
		} else {
			$searched = $row['searched'] - 1;
			echo ".";
		}
		$db->exec(sprintf("UPDATE prehash SET searched = %d WHERE ID = %d", $searched, $row['preid']));
		if (!isset($argv[2]) || $argv[2] !== 'show') {
			$consoletools->overWritePrimary("Renamed Releases: [" . number_format($counted) . "] " . $consoletools->percentString(++$counter, $total));
		}
	}
	if ($total > 0) {
		echo $c->header("\nRenamed " . number_format($counted) . " releases in " . $consoletools->convertTime(TIME() - $timestart) . ".");
	} else {
		echo $c->info("\nNothing to do.");
	}
} else {
	echo $c->info("No work to process.\n");
}

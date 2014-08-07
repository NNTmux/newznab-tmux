<?php
require_once(dirname(__FILE__) . "/../bin/config.php");
require_once(WWW_DIR . "/lib/framework/db.php");
require_once(WWW_DIR . "/lib/category.php");
require_once("ColorCLI.php");
require_once("ConsoleTools.php");


$c = new ColorCLI();
if (!(isset($argv[1]) && ($argv[1] == "all" || $argv[1] == "misc" || preg_match('/\([\d, ]+\)/', $argv[1]) || is_numeric($argv[1])))) {
	exit($c->error(
		"\nThis script will attempt to re-categorize releases and is useful if changes have been made to Category.php.\n"
		. "No updates will be done unless the category changes\n"
		. "An optional last argument, test, will display the number of category changes that would be made\n"
		. "but will not update the database.\n\n"
		. "php $argv[0] all                     ...: To process all releases.\n"
		. "php $argv[0] misc                    ...: To process all releases in misc categories.\n"
		. "php $argv[0] 155                     ...: To process all releases in groupID 155.\n"
		. "php $argv[0] '(155, 140)'            ...: To process all releases in group_ids 155 and 140.\n"
	));
}

reCategorize($argv);

function reCategorize($argv)
{
	$c = new ColorCLI();
	$where = '';
	$update = true;
	if (isset($argv[1]) && is_numeric($argv[1])) {
		$where = ' AND groupID = ' . $argv[1];
	} else if (isset($argv[1]) && preg_match('/\([\d, ]+\)/', $argv[1])) {
		$where = ' AND groupID IN ' . $argv[1];
	} else if (isset($argv[1]) && $argv[1] === 'misc') {
		$where = ' AND categoryID IN (1090, 2020, 3050, 4040, 5050, 6050, 7050, 8010)';
	}
	if (isset($argv[2]) && $argv[2] === 'test') {
		$update = false;
	}

	if (isset($argv[1]) && (is_numeric($argv[1]) || preg_match('/\([\d, ]+\)/', $argv[1]))) {
		echo $c->header("Categorizing all releases in ${argv[1]} using searchname. This can take a while, be patient.");
	} else if (isset($argv[1]) && $argv[1] == "misc") {
		echo $c->header("Categorizing all releases in misc categories using searchname. This can take a while, be patient.");
	} else {
		echo $c->header("Categorizing all releases using searchname. This can take a while, be patient.");
	}
	$timestart = TIME();
	if (isset($argv[1]) && (is_numeric($argv[1]) || preg_match('/\([\d, ]+\)/', $argv[1])) || $argv[1] === 'misc') {
		$chgcount = categorizeRelease($update, str_replace(" AND", "WHERE", $where), true);
	} else {
		$chgcount = categorizeRelease($update, "", true);
	}
	$consoletools = new ConsoleTools();
	$time = $consoletools->convertTime(TIME() - $timestart);
	if ($update === true) {
		echo $c->header("Finished re-categorizing " . number_format($chgcount) . " releases in " . $time . " , 	using the searchname.\n");
	} else {
		echo $c->header("Finished re-categorizing in " . $time . " , using the searchname.\n"
			. "This would have changed " . number_format($chgcount) . " releases but no updates were done.\n"
		);
	}
}

// Categorizes releases.
// Returns the quantity of categorized releases.
function categorizeRelease($update = true, $where, $echooutput = false)
{
	$pdo = new DB();
	$cat = new Categorize();
	$consoletools = new consoleTools();
	$relcount = $chgcount = 0;
	$c = new ColorCLI();
	echo $c->primary("SELECT ID, searchname, groupID, categoryID FROM releases " . $where);
	$resrel = $pdo->queryDirect("SELECT ID, searchname, groupID, categoryID FROM releases " . $where);
	$total = $resrel->rowCount();
	if ($total > 0) {
		foreach ($resrel as $rowrel) {
			$catId = $cat->determineCategory($rowrel['groupID'], $rowrel['searchname']);
			if ($rowrel['categoryID'] != $catId) {
				if ($update === true) {
					$pdo->exec(
						sprintf("
							UPDATE releases
							SET iscategorized = 1,
								rageID = -1,
								seriesfull = NULL,
								season = NULL,
								episode = NULL,
								tvtitle = NULL,
								tvairdate = NULL,
								imdbID = NULL,
								musicinfoID = NULL,
								consoleinfoID = NULL,
								gamesinfo_id = NULL,
								bookinfoID = NULL,
								anidbID = NULL,
								categoryID = %d
							WHERE ID = %d",
							$catId,
							$rowrel['ID']
						)
					);
				}
				$chgcount++;
			}
			$relcount++;
			if ($echooutput) {
				$consoletools->overWritePrimary("Re-Categorized: [" . number_format($chgcount) . "] " . $consoletools->percentString($relcount, $total));
			}
		}
	}
	if ($echooutput !== false && $relcount > 0) {
		echo "\n";
	}

	return $chgcount;
}
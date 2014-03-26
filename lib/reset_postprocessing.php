<?php

require_once(dirname(__FILE__)."/../bin/config.php");
require_once("ColorCLI.php");
require_once("consoletools.php");

$db = new DB();
$c = new ColorCLI();
$consoletools = new ConsoleTools();
$ran = false;

if (isset($argv[1]) && $argv[1] === "all") {
	if (isset($argv[2]) && $argv[2] === "true") {
		$ran = true;
		$where = '';
		if (isset($argv[3]) && $argv[3] === "truncate") {
			echo "Truncating tables\n";
			$db->exec("TRUNCATE TABLE consoleinfo");
			$db->exec("TRUNCATE TABLE movieinfo");
			$db->exec("TRUNCATE TABLE musicinfo");
			$db->exec("TRUNCATE TABLE bookinfo");
			$db->exec("TRUNCATE TABLE releasenfo");
			$db->exec("TRUNCATE TABLE releaseextrafull");
		}
		echo $c->header("Resetting all postprocessing");
		$qry = $db->queryDirect("SELECT ID FROM releases");
		$total = $qry->rowCount();
		$affected = 0;
		foreach ($qry as $releases) {
			$db->exec("UPDATE releases SET consoleinfoID = NULL, imdbID = NULL, musicinfoID = NULL, bookinfoID = NULL, rageID = -1, passwordstatus = -1, haspreview = -1, jpgstatus = 0, nfostatus = -1 WHERE ID = " . $releases['ID']);
			$consoletools->overWritePrimary("Resetting Releases:  " . $consoletools->percentString( ++$affected, $total));
		}
	}
}
if (isset($argv[1]) && ($argv[1] === "consoles" || $argv[1] === "all")) {
	$ran = true;
	if (isset($argv[3]) && $argv[3] === "truncate") {
		$db->exec("TRUNCATE TABLE consoleinfo");
	}
	if (isset($argv[2]) && $argv[2] === "true") {
		echo $c->header("Resetting all Console postprocessing");
		$where = ' WHERE consoleinfoID IS NOT NULL';
	} else {
		echo $c->header("Resetting all failed Console postprocessing");
		$where = " WHERE consoleinfoID IN (-2, 0) AND categoryID BETWEEN 1000 AND 1999";
	}

	$qry = $db->queryDirect("SELECT ID FROM releases" . $where);
	$total = $qry->rowCount();
	$concount = 0;
	foreach ($qry as $releases) {
		$db->exec("UPDATE releases SET consoleinfoID = NULL WHERE ID = " . $releases['ID']);
		$consoletools->overWritePrimary("Resetting Console Releases:  " . $consoletools->percentString( ++$concount, $total));
	}
	echo $c->header("\n" . number_format($concount) . " consoleinfoID's reset.");
}
if (isset($argv[1]) && ($argv[1] === "movies" || $argv[1] === "all")) {
	$ran = true;
	if (isset($argv[3]) && $argv[3] === "truncate") {
		$db->exec("TRUNCATE TABLE movieinfo");
	}
	if (isset($argv[2]) && $argv[2] === "true") {
		echo $c->header("Resetting all Movie postprocessing");
		$where = ' WHERE imdbID IS NOT NULL';
	} else {
		echo $c->header("Resetting all failed Movie postprocessing");
		$where = " WHERE imdbID IN (-2, 0) AND categoryID BETWEEN 2000 AND 2999";
	}

	$qry = $db->queryDirect("SELECT ID FROM releases" . $where);
	$total = $qry->rowCount();
	$concount = 0;
	foreach ($qry as $releases) {
		$db->exec("UPDATE releases SET imdbID = NULL WHERE ID = " . $releases['ID']);
		$consoletools->overWritePrimary("Resetting Movie Releases:  " . $consoletools->percentString( ++$concount, $total));
	}
	echo $c->header("\n" . number_format($concount) . " imdbID's reset.");
}
if (isset($argv[1]) && ($argv[1] === "music" || $argv[1] === "all")) {
	$ran = true;
	if (isset($argv[3]) && $argv[3] === "truncate") {
		$db->exec("TRUNCATE TABLE musicinfo");
	}
	if (isset($argv[2]) && $argv[2] === "true") {
		echo $c->header("Resetting all Music postprocessing");
		$where = ' WHERE musicinfoID IS NOT NULL';
	} else {
		echo $c->header("Resetting all failed Music postprocessing");
		$where = " WHERE musicinfoID IN (-2, 0) AND categoryID BETWEEN 3000 AND 3999";
	}

	$qry = $db->queryDirect("SELECT ID FROM releases" . $where);
	$total = $qry->rowCount();
	$concount = 0;
	foreach ($qry as $releases) {
		$db->exec("UPDATE releases SET musicinfoID = NULL WHERE ID = " . $releases['ID']);
		$consoletools->overWritePrimary("Resetting Music Releases:  " . $consoletools->percentString( ++$concount, $total));
	}
	echo $c->header("\n" . number_format($concount) . " musicinfoID's reset.");
}
if (isset($argv[1]) && ($argv[1] === "misc" || $argv[1] === "all")) {
	$ran = true;
	if (isset($argv[2]) && $argv[2] === "true") {
		echo $c->header("Resetting all Additional postprocessing");
		$where = ' WHERE (haspreview != -1 AND haspreview != 0) OR (passwordstatus != -1 AND passwordstatus != 0) OR jpgstatus != 0';
	} else {
		echo $c->header("Resetting all failed Additional postprocessing");
		$where = " WHERE haspreview < -1 OR haspreview = 0 OR passwordstatus < -1 OR passwordstatus = 0 OR jpgstatus < 0";
	}

	echo $c->primary("SELECT ID FROM releases" . $where);
	$qry = $db->queryDirect("SELECT ID FROM releases" . $where);
	$total = $qry->rowCount();
	$concount = 0;
	foreach ($qry as $releases) {
		$db->exec("UPDATE releases SET passwordstatus = -1, haspreview = -1, jpgstatus = 0, WHERE ID = " . $releases['ID']);
		$consoletools->overWritePrimary("Resetting Releases:  " . $consoletools->percentString( ++$concount, $total));
	}
	echo $c->header("\n" . number_format($concount) . " Release's reset.");
}
if (isset($argv[1]) && ($argv[1] === "tv" || $argv[1] === "all")) {
	$ran = true;
	if (isset($argv[3]) && $argv[3] === "truncate") {
		$db->exec("TRUNCATE TABLE tvrage");
	}
	if (isset($argv[2]) && $argv[2] === "true") {
		echo $c->header("Resetting all TV postprocessing");
		$where = '  WHERE rageID != -1';
	} else {
		echo $c->header("Resetting all failed TV postprocessing");
		$where = " WHERE rageID IN (-2, 0) OR rageID IS NULL AND categoryID BETWEEN 5000 AND 5999";
	}

	$qry = $db->queryDirect("SELECT ID FROM releases" . $where);
	$total = $qry->rowCount();
	$concount = 0;
	foreach ($qry as $releases) {
		$db->exec("UPDATE releases SET rageID = -1 WHERE ID = " . $releases['ID']);
		$consoletools->overWritePrimary("Resetting TV Releases:  " . $consoletools->percentString( ++$concount, $total));
	}
	echo $c->header("\n" . number_format($concount) . " rageID's reset.");
}
if (isset($argv[1]) && ($argv[1] === "books" || $argv[1] === "all")) {
	$ran = true;
	if (isset($argv[3]) && $argv[3] === "truncate") {
		$db->exec("TRUNCATE TABLE bookinfo");
	}
	if (isset($argv[2]) && $argv[2] === "true") {
		echo $c->header("Resetting all Book postprocessing");
		$where = ' WHERE bookinfoID IS NOT NULL';
	} else {
		echo $c->header("Resetting all failed Book postprocessing");
		$where = " WHERE bookinfoID IN (-2, 0) AND categoryID BETWEEN 8000 AND 8999";
	}

	$qry = $db->queryDirect("SELECT ID FROM releases" . $where);
	$total = $qry->rowCount();
	$concount = 0;
	foreach ($qry as $releases) {
		$db->exec("UPDATE releases SET bookinfoID = NULL WHERE ID = " . $releases['ID']);
		$consoletools->overWritePrimary("Resetting Book Releases:  " . $consoletools->percentString( ++$concount, $total));
	}
	echo $c->header("\n" . number_format($concount) . " bookinfoID's reset.");
}
if (isset($argv[1]) && ($argv[1] === "nfos" || $argv[1] === "all")) {
	$ran = true;
	if (isset($argv[3]) && $argv[3] === "truncate") {
		$db->exec("TRUNCATE TABLE releasenfo");
	}
	if (isset($argv[2]) && $argv[2] === "true") {
		echo $c->header("Resetting all NFO postprocessing");
		$where = ' WHERE nfostatus != -1';
	} else {
		echo $c->header("Resetting all failed NFO postprocessing");
		$where = " WHERE nfostatus < -1";
	}

	$qry = $db->queryDirect("SELECT ID FROM releases" . $where);
	$total = $qry->rowCount();
	$concount = 0;
	foreach ($qry as $releases) {
		$db->exec("UPDATE releases SET nfostatus = -1, releasenfoID = -1 WHERE ID = " . $releases['ID']);
		$consoletools->overWritePrimary("Resetting NFO Releases:  " . $consoletools->percentString( ++$concount, $total));
	}
	echo $c->header("\n" . number_format($concount) . " NFO's reset.");
}

if ($ran === false) {
	exit($c->error("\nThis script will reset postprocessing per category. It can also truncate the associated tables."
					. "\nTo reset only those that have previously failed, those without covers, samples, previews, etc. use the "
					. "second argument false.\n"
					. "To reset even those previoulsy postprocessed, use the second argument true.\n"
					. "To truncate the associated table, use the third argument truncate.\n\n"
					. "php $argv[0] consoles true    ...: To reset all consoles.\n"
					. "php $argv[0] movies true      ...: To reset all movies.\n"
					. "php $argv[0] music true       ...: To reset all music.\n"
					. "php $argv[0] misc true        ...: To reset all misc.\n"
					. "php $argv[0] tv true          ...: To reset all tv.\n"
					. "php $argv[0] books true       ...: To reset all books.\n"
					. "php $argv[0] nfos true        ...: To reset all nfos.\n"
					. "php $argv[0] all true         ...: To reset everything.\n"));
} else {
	echo "\n";
}

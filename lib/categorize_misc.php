<?php

require_once(dirname(__FILE__)."/../bin/config.php");
require_once(WWW_DIR."/lib/groups.php");
require_once(WWW_DIR."/lib/nntp.php");
require_once("ColorCLI.php");
require_once("functions.php");
require_once("consoletools.php");

$c = new ColorCLI();

$groupName = isset($argv[3]) ? $argv[3] : '';
if (isset($argv[1]) && isset($argv[2])) {
	$functions = new Functions();
    if ($argv[1] == 1 && ($argv[2] == 'true' || $argv[2] == 'false')) {
		echo $c->header("Categorizing all non-categorized releases in other->misc using the searchname. This can take a while, be patient.");
		$timestart = TIME();
		$relcount = $functions->categorizeRelease('searchname', 'WHERE iscategorized = 0 AND categoryID = 8010', true);
		$consoletools = new ConsoleTools();
		$time = $consoletools->convertTime(TIME() - $timestart);
		echo $c->primary("\n" . 'Finished categorizing ' . $relcount . ' releases in ' . $time . " seconds, using the usenet subject.");
	} else if ($argv[1] == 2 && $argv[2] == 'true') {
		echo $c->header("Categorizing releases in all sections using the searchname. This can take a while, be patient.");
		$timestart = TIME();
		$relcount = $functions->categorizeRelease('searchname', '', true);
		$consoletools = new ConsoleTools();
		$time = $consoletools->convertTime(TIME() - $timestart);
		echo $c->primary("\n" . 'Finished categorizing ' . $relcount . ' releases in ' . $time . " seconds, using the search name.");
	} else if ($argv[1] == 2 && $argv[2] == 'false') {
		echo $c->header("Categorizing releases in misc sections using the searchname. This can take a while, be patient.");
		$timestart = TIME();
		$relcount = $functions->categorizeRelease('searchname', 'WHERE categoryID IN (2020, 5050, 6070, 8010)', true);
		$consoletools = new ConsoleTools();
		$time = $consoletools->convertTime(TIME() - $timestart);
		echo $c->primary("\n" . 'Finished categorizing ' . $relcount . ' releases in ' . $time . " seconds, using the search name.");
	} else {
		exit($c->error("Wrong argument, type php categorize_misc.php to see a list of valid arguments."));
	}
} else {
	exit($c->error("\nWrong set of arguments.\n"
			. "php update_releases.php 1 true			...: Categorizes all releases in other->misc (which have not been categorized already)\n"
			. "php update_releases.php 2 false			...: Categorizes releases in misc sections using the search name\n"
			. "php update_releases.php 2 true			...: Categorizes releases in all sections using the search name\n"
            . "\nYou must pass a second argument whether to post process or not, true or false\n"));
}


<?php
/* This script is designed to gather all show data from anidb and add it to the anidb table for nntmux, as part of this process we need the number of PI queries that can be executed max and whether or not we want debuging the first argument if unset will try to do the entire list (a good way to get banned), the second option can be blank or true for debugging.
* IF you are using this script then then you also want to edit anidb.php in www/lib and locate "604800" and replace it with 1204400, this will make sure it never tries to connect to anidb as this will fail
*/
require_once realpath(dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'bootstrap.php');

use nntmux\db\DB;
use nntmux\db\populate\AniDB;

$pdo = new DB();

if ($argc > 1 && $argv[1] == true) {
	(new AniDB(['Settings' => $pdo, 'Echo' => true]))->populateTable('full');
} else {
	$pdo->log->doEcho(PHP_EOL . $pdo->log->error(
			"To execute this script you must provide a boolean argument." . PHP_EOL .
			"Argument1: true|false to run this script or not"), true
	);
}

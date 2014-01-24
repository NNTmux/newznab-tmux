<?php

/*
 * This script resets the relnamestatus to 1 on every release that has relnamestatus 2, so you can rerun fixReleaseNames.php
 */
//This script is adapted from nZEDb

require_once(dirname(__FILE__)."/../bin/config.php");
require_once(WWW_DIR ."/lib/framework/db.php");
require_once ("ColorCLI.php");

$c = new ColorCLI();

if (!isset($argv[1])) {
	exit($c->error("\nThis script will set bitwise = 0 or all rename bits to unchecked or just specific bits.\n\n"
					. "php $argv[0] true           ...: To reset bitwise on all releases to 0.\n"
					. "php $argv[0] rename         ...: To reset bitwise on all releases for just rename bits (4, 8, 16, 32, 64, 128).\n"
					. "php $argv[0] 512            ...: To reset a specific bit.\n"));
}

$db = new DB();
if ($argv[1] === 'true') {
    $res = $db->exec('UPDATE releases SET bitwise = 0');
} else if ($argv[1] === 'rename') {
    $res = $db->exec('UPDATE releases SET bitwise = ((bitwise & ~252)|0)');
} else if (is_numeric($argv[1])) {
    $res = $db->exec('UPDATE releases SET bitwise = ((bitwise & ~' . $argv[1] . ')|0)');
}

if ($res->rowCount() > 0 && is_numeric($argv[1])) {
    echo $c->header('Succesfully reset the bitwise of ' . $res->rowCount() . ' releases to 0 for bit(s) ' . $argv[1] . '.');
} else if ($res->rowCount() > 0) {
    echo $c->header('Succesfully reset the bitwise of ' . $res->rowCount() . ' releases to unprocessed.');
} else {
    echo $c->header('No releases to be reset.');
}

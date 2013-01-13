<?php

require("lib/innodb/config.php");
require_once("lib/backfill.php");

if (isset($argv[1]))
	$groupName = $argv[1];
else
	$groupName = '';

$backfill = new Backfill();
$backfill->backfillAllGroups($groupName);

?>

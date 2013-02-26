<?php
/*
This scripts backfills 100k parts, on ONE group from your active groups, reverse alphabetically, to a maximum of 2012-06-24. When that group is at 2012-06-24 it will move on to the next group.
*/
require(dirname(__FILE__)."/config.php");
require_once(WWW_DIR."/lib/backfill.php");
require_once(WWW_DIR."/lib/framework/db.php");

//get variables from config.sh and defaults.sh
$path = dirname(__FILE__);
$varnames = shell_exec("cat ".$path."/../config.sh | grep ^export | cut -d \= -f1 | awk '{print $2;}'");
$varnames .= shell_exec("cat ".$path."/../defaults.sh | grep ^export | cut -d \= -f1 | awk '{print $2;}'");
$vardata = shell_exec("cat ".$path."/../config.sh | grep ^export | cut -d \\\" -f2 | awk '{print $1;}'");
$vardata .= shell_exec("cat ".$path."/../defaults.sh | grep ^export | cut -d \\\" -f2 | awk '{print $1;}'");
$varnames = explode("\n", $varnames);
$vardata = explode("\n", $vardata);
$array = array_combine($varnames, $vardata);
unset($array['']);

$_date = $array['KEVIN_DATE'];
$_parts = $array['KEVIN_PARTS'];

$db = new DB;

$query = $db->queryOneRow(sprintf("select name from groups WHERE (first_record_postdate BETWEEN '${_date}' and now()) and (active = 1) order by name desc"));

$groupPost = $_parts;
$groupName = $query['name'];

$backfill = new Backfill();
$backfill->backfillPostAllGroups($groupName, $groupPost);
?>

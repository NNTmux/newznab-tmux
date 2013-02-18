<?php
/*
This scripts backfills 100k parts, on ONE group from your active groups, reverse alphabetically, to a maximum of 2012-06-24. When that group is at 2012-06-24 it will move on to the next group.
*/
require(dirname(__FILE__)."/config.php");
require_once(WWW_DIR."/lib/backfill.php");
require_once(WWW_DIR."/lib/framework/db.php");

	$db = new DB;
	$parts = "100000";
	$query = $db->queryOneRow(sprintf("select name from groups WHERE (first_record_postdate BETWEEN '2012-06-24' and now()) and (active = 1) order by name desc"));

	$groupPost = $parts;
	$groupName = $query['name'];

	$backfill = new Backfill();
	$backfill->backfillPostAllGroups($groupName, $groupPost);
?>

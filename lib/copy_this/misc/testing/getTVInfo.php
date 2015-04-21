<?php
define('FS_ROOT', realpath(dirname(__FILE__)));

use newznab\db\DB;

$t = new TvRage;

$db = new DB();

$shows = $db->query("select name from releases where categoryID IN (select ID from category where parentID = 5000) limit 0, 50");

foreach ($shows as $show) {
	$res = $t->parseNameEpSeason($show['name']);
	$res['release'] = $show['name'];

	echo "<pre>";
	print_r($res);
	echo "</pre>";
}
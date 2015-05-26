<?php
require_once dirname(__FILE__) . '/../../www/config.php';

use newznab\db\Settings;

$t = new TvRage;

$db = new Settings();

$shows = $db->query("select name from releases where categoryID IN (select ID from category where parentID = 5000) limit 0, 50");

foreach ($shows as $show) {
	$res = $t->parseNameEpSeason($show['name']);
	$res['release'] = $show['name'];

	echo "<pre>";
	print_r($res);
	echo "</pre>";
}
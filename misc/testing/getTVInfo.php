<?php
require_once realpath(dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'indexer.php');

use newznab\db\Settings;
use newznab\controllers\TvRage;

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
<?php

//This script allows you to delete properly all preview images for a range

require_once realpath(dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'indexer.php');

use newznab\db\Settings;
use newznab\controllers\Releases;

$releases = new Releases();
$db = new Settings;

//
// delete all previews over a year old
//
$sql = "select guid from releases where adddate < date_sub(now(), interval 365 day) and haspreview = 1;";

$rel = $db->query($sql);
echo "about to delete ".count($rel)." release previews";

foreach ($rel as $r)
{
	$releases->deletePreview($r['guid']);
}
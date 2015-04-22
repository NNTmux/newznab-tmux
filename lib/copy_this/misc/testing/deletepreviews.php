<?php

//This script allows you to delete properly all preview images for a range

define('FS_ROOT', realpath(dirname(__FILE__)));

use newznab\db\DB;

$releases = new Releases();
$db = new DB;

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
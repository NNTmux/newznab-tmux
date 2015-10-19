<?php
require_once realpath(dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'indexer.php');

use newznab\db\Settings;
use newznab\TvRage;

$db = new Settings();
$t = new TvRage();

//
// all rage entries with a blank description
$rows = $db->query("select ID, releasetitle from tvrage
                            where description is null
                            and rageID in (select distinct rageID from releases)
                            order by ID desc");

echo "Updating ".count($rows)." entries \n";

foreach ($rows as $row) {
    $t->refreshRageInfo($row["id"]);
	echo "Refreshing ".$row["releasetitle"]."\n";
}

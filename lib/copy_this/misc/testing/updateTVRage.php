<?php
define('FS_ROOT', realpath(dirname(__FILE__)));

use newznab\db\DB;

$db = new DB();
$t = new TvRage();

//
// all rage entries with a blank description
$rows = $db->query("select ID, releasetitle from tvrage
                            where description is null
                            and rageID in (select distinct rageID from releases)
                            order by ID desc");

echo "Updating ".count($rows)." entries \n";

foreach ($rows as $row) {
    $t->refreshRageInfo($row["ID"]);
	echo "Refreshing ".$row["releasetitle"]."\n";
}
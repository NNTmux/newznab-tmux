<?php

require_once realpath(dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'indexer.php');

use newznab\db\Settings;


$db = new Settings();
$sql = "select distinct rageid from tvrage where rageid in (select rageid from tvrage where rageid != -2 group by rageid having count(*) > 1)";
$rows = $db->query($sql);

foreach ($rows as $row)
{
    $sql = "select id, rageid, tvdbid from tvrage where rageid = " . $row["rageid"]."\n";
    $tvrows = $db->query($sql);
    $count = 0;
    $keeprow="0";
    $firstrow = "0";

    foreach ($tvrows as $tvrow) {
        $count++;
        if ($tvrow["tvdbid"] != 0) {
            $keeprow = $tvrow["id"];
        }
        if ($count == 1) {
            $firstrow = $tvrow["id"];
        }
    }
    if ($keeprow != "0")
        $firstrow = "0";

    $sql = "delete from tvrage where rageid = ".$row["rageid"]." and (id != ".$keeprow." and id != ".$firstrow.")";
    $db->exec($sql);
    echo "Cleaned - ".$row["rageid"]."\n";
}

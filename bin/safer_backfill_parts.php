<?php
/*
This scripts backfills 100k parts, on ONE group from your active groups, reverse alphabetically, to a maximum of 2012-06-24. When that group is at 2012-06-24 it will move on to the next group.
*/
require(dirname(__FILE__)."/config.php");
require_once(WWW_DIR."/lib/backfill.php");
require_once(WWW_DIR."/lib/framework/db.php");
$time = TIME();

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

function relativeTime($_time) {
    $d[0] = array(1,"sec");
    $d[1] = array(60,"min");
    $d[2] = array(3600,"hr");
    $d[3] = array(86400,"day");
    $d[4] = array(31104000,"yr");

    $w = array();

    $return = "";
    $now = TIME();
    $diff = ($now-$_time);
    $secondsLeft = $diff;

    for($i=4;$i>-1;$i--)
    {
        $w[$i] = intval($secondsLeft/$d[$i][0]);
        $secondsLeft -= ($w[$i]*$d[$i][0]);
        if($w[$i]!=0)
        {
            //$return.= abs($w[$i]). " " . $d[$i][1] . (($w[$i]>1)?'s':'') ." ";
            $return.= $w[$i]. " " . $d[$i][1] . (($w[$i]>1)?'s':'') ." ";
        }
    }

    //$return .= ($diff>0)?"ago":"left";
    return $return;
}

$_date = $array['KEVIN_DATE'];
$_parts = $array['KEVIN_PARTS'];

$db = new DB;

echo "Starting kevin123's safer backfill process\n\n";
$query = $db->queryOneRow(sprintf("select name from groups WHERE (first_record_postdate BETWEEN '${_date}' and now()) and (active = 1) order by name ASC"));

$groupPost = $_parts;
$groupName = $query['name'];

$backfill = new Backfill();
$backfill->backfillPostAllGroups($groupName, $groupPost);

echo "\n\033[1;33mKevin123's safer backfill process completed in: " .relativeTime($time). "\n";

?>

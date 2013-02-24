<?php
$time = TIME();
require(dirname(__FILE__)."/config.php");
require_once(WWW_DIR."/lib/releases.php");
//require_once(WWW_DIR."/lib/sphinx.php");

$releases = new Releases;
//$sphinx = new Sphinx();
$releases->processReleases();
//$sphinx->update();

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
            $return.= "\033[1;31m".abs($w[$i])."\033[0m" . " " . $d[$i][1] . (($w[$i]>1)?'s':'') ." ";
        }
    }

    //$return .= ($diff>0)?"ago":"left";
    return $return;
}

echo "\033[1;33mThis loop completed in: " .relativeTime($time). "\033[0m\n";
?>

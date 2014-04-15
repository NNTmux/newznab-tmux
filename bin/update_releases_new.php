<?php
$time = TIME();
require(dirname(__FILE__) . "/config.php");
require_once(WWW_DIR . "/lib/releases.php");
//require_once(WWW_DIR."/lib/sphinx.php");
require_once(dirname(__FILE__) . '/../lib/ColorCLI.php');

$c = new ColorCLI();
if (!isset($argv[1])) {
	exit($c->error("This script is not intended to be run manually, it is called from releases_threaded.py."));
}
$pieces = explode('  ', $argv[1]);
$groupID = $pieces[0];

$releases = new Releases;
//$sphinx = new Sphinx();
$releases->processReleases();
//$sphinx->update();

function relativeTime($_time)
{
	$d[0] = array(1, "\033[1;33msec");
	$d[1] = array(60, "\033[1;33mmin");
	$d[2] = array(3600, "\033[1;33mhr");
	$d[3] = array(86400, "\033[1;33mday");
	$d[4] = array(31104000, "\033[1;33myr");

	$w = array();

	$return = "";
	$now = TIME();
	$diff = ($now - $_time);
	$secondsLeft = $diff;

	for ($i = 4; $i > -1; $i--) {
		$w[$i] = intval($secondsLeft / $d[$i][0]);
		$secondsLeft -= ($w[$i] * $d[$i][0]);
		if ($w[$i] != 0) {
			//$return.= abs($w[$i]). " " . $d[$i][1] . (($w[$i]>1)?'s':'') ." ";
			$return .= $w[$i] . " " . $d[$i][1] . (($w[$i] > 1) ? 's' : '') . " ";
		}
	}

	//$return .= ($diff>0)?"ago":"left";
	return $return;
}

echo "\033[1;33mThis loop completed in: " . relativeTime($time) . "\n";
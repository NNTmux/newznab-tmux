<?php

require("lib/innodb/config.php");
require_once("lib/framework/db.php");

$force = false;

if (isset($argv[1]))
	$force = ($argv[1] == "true");

$db = new DB;
$ret = $db->optimise($force);

if (count($ret) > 0)
{
	echo "Optmze  : Optimised ".count($ret)." tables\n";
}
else
{
	echo "Optmze  : Nothing required optimisation.".(!$force?" Try using force (optimise_db.php true)":"")."\n";
}
?>

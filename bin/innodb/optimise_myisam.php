<?php

require("lib/innodb/config.php");
require_once("lib/framework/db.php");

$force = false;

echo "Optmze  : Going to repair, optimize and analyze the myisam table(s).\n";
echo "PLEASE DO NOT INTERRUPT THE SCRIPT!\n";

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
	echo "Optmze  : Nothing required optimisation.".(!$force?" Try using force (optimise_myisam.php true)":"")."\n";
}
?>

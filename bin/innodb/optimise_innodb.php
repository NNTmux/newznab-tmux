<?php

require("lib/innodb/config.php");
require_once("lib/framework/db.php");

$iforce = false;

echo "Inno Optmze  : Going to recreate and analyze the innodb table(s).\n";
echo "\033[1;33;41mPLEASE DO NOT INTERRUPT THE SCRIPT!\n\033[0m";

if (isset($argv[1]))
	$iforce = ($argv[1] == "true");

$db = new DB;
$iret = $db->optimiseinnodb($iforce);

if (count($iret) > 0)
{
	echo "Inno Optmze  : Finished recreating and analyzing ".count($iret)." tables\n";
}
else
{
	echo "Inno Optmze  : Nothing required optimisation.".(!$iforce?" Try using force (optimise_innodb.php true)":"")."\n";
}
?>

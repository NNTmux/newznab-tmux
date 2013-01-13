<?php

require("lib/innodb/config.php");
require_once("lib/framework/db.php");

$iforce = false;

if (isset($argv[1]))
	$iforce = ($argv[1] == "true");

$db = new DB;
$iret = $db->optimiseinnodb($iforce);

if (count($iret) > 0)
{
	echo "Inno Optmze  : Optimised ".count($iret)." tables\n";
}
else
{
	echo "Inno Optmze  : Nothing required optimisation.".(!$iforce?" Try using force (innoptimise_db.php true)":"")."\n";
}
?>

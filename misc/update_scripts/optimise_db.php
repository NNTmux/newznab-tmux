<?php

require_once("config.php");

use newznab\db\Settings;

$force = ((isset($argv[1]) && ($argv[1] == "true")));

$db = new Settings;
$ret = $db->optimise($force);

if (count($ret) > 0)
{
	echo "Optmze  : Optimised ".count($ret)." tables\n";
}
else
{
	echo "Optmze  : Nothing required optimisation.".(!$force ? " Try using force (optimise_db.php true)" : "")."\n";
}
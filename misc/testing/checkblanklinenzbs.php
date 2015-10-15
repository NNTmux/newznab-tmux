<?php
require_once realpath(dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'indexer.php');

use newznab\db\Settings;
use newznab\controllers\NZB;
use newznab\controllers\Sites;


$db = new Settings();
$n = new NZB();
$s = new Sites;
$site = $s->get();

$items = $db->query("SELECT guid FROM releases");

while ($item = array_pop($items))
{
	$guid = $item['guid'];
	$nzbpath = $n->getNZBPath($guid, $site->nzbpath);
	$zd = _gzopen($nzbpath, "r");
	$s = gzread($zd, 10 );

	if ($s != "<?xml vers")
	{
		echo "fucked - ". $guid . "\n";
	}
}
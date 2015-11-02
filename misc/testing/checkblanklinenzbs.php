<?php
require_once realpath(dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'indexer.php');

use newznab\db\Settings;
use newznab\NZB;
use newznab\Sites;


$pdo = new Settings();
$n = new NZB();
$s = New Sites;

$items = $pdo->query("SELECT guid FROM releases");

while ($item = array_pop($items))
{
	$guid = $item['guid'];
	$nzbpath = $n->getNZBPath($guid, $pdo->getSetting('nzbpath'));
	$zd = _gzopen($nzbpath, "r");
	$s = gzread($zd, 10 );

	if ($s != "<?xml vers")
	{
		echo "fucked - ". $guid . "\n";
	}
}

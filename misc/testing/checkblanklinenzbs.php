<?php
require_once dirname(__FILE__) . '/../../www/config.php';

use newznab\db\Settings;


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
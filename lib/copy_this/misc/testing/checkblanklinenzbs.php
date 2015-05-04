<?php
require_once dirname(__FILE__) . '/../../www/config.php';

use newznab\db\DB;


$db = new DB();
$n = new NZB();
$s = New Sites;
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
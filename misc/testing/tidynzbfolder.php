<?php
require_once dirname(__FILE__) . '/../../www/config.php';

use newznab\db\Settings;

$pdo = new Settings();
$r = new Releases();

//
// Option One - delete all from the database where it exists on disk, but not in the db
//
/*
$it = new RecursiveDirectoryIterator($pdo->getSetting('nzbpath'));
foreach(new RecursiveIteratorIterator($it) as $file)
{
	$releaseGUID = str_replace(".nzb.gz", "", $file->getFilename());
	$rel = $db->queryOneRow(sprintf("SELECT ID from releases where guid = %s", $pdo->escapeString($releaseGUID)));
	if (!$rel)
	{
		$r->delete($releaseGUID, true);
	}
	else
	{
		echo "not deleting ".$file->getFilename()."\n";
	}
}
*/


//
// Option Two - delete all from the database where it doesnt exist on disk
//
/*
	$res = $db->queryDirect("select guid from releases");
	while ($row = $db->getAssocArray($res))
	{
		$nzbpath = $pdo->getSetting('nzbpath').substr($row["guid"], 0, 1)."/".$row["guid"].".nzb.gz";
		if (!file_exists($nzbpath))
		{
			echo "deleted ".$row["guid"];
			$r->delete($row["guid"], true);
		}
	}
*/
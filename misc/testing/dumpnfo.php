<?php

//
// Script will dump out all nfos in the system into a folder based on the date posted to usenet ./YYYYMMDD/release.nfo
// Its not very efficient to pull them all out, should really work out which day you need and go from there.
//

require_once realpath(dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'bootstrap.php');

use nntmux\db\DB;
use nntmux\utility\Utility;


$db = new DB();

$res = $db->queryDirect("select releases.searchname, releases.postdate, uncompress(release_nfos.nfo) as nfo from releases inner join release_nfos on releases.ID = release_nfos.releaseID and release_nfos.nfo is not null order by postdate");
while ($row = $db->getAssocArray($res))
{
	$dir = date("Ymd", strtotime($row["postdate"]));

	if (!file_exists($dir))
		mkdir($dir);

	$filename = $dir."/".safeFilename($row["searchname"]).".nfo";

	if (!file_exists($filename))
	{
		$fh = fopen($filename, 'w');
		fwrite($fh, Utility::cp437toUTF($row["nfo"]));
		fclose($fh);
	}
}

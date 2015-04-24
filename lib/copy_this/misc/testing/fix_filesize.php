<?php

/*
Filesize Fix Script
If after import you have a bunch of zero sized releases run this
Author: lordgnu <lordgnu@me.com>
*/

define('FS_ROOT', realpath(dirname(__FILE__)));

use newznab\db\DB;

$db = new DB;
$s = New Sites;
$nzb = new NZB;

$items = $db->query("SELECT ID,guid FROM releases WHERE size = 0");
$total = count($items);
$compl = 0;
echo "Updating file size for " . count($items) . " release(s)\n";
$site = $s->get();

while ($item = array_pop($items))
{
	$nzbpath = $nzb->getNZBPath($item['guid'], $site->nzbpath);

	ob_start();
	@readgzfile($nzbpath);
	$nzbfile = ob_get_contents();
	ob_end_clean();

	$ret = $nzb->nzbFileList($nzbfile);

	$filesize = '0';

	foreach ($ret as $file) {
		$filesize = bcadd($filesize, $file['size']);
	}

	$db->exec("UPDATE releases SET size = '{$filesize}' WHERE `ID` = '{$item['ID']}' LIMIT 1");

	$compl++;
	echo sprintf("[%6d / %6d] %0.2f",$compl, $total, ($compl/$total) * 100) . '%' . "\n";
}

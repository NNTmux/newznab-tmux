<?php

/*
Filesize Fix Script
If after import you have a bunch of zero sized releases run this
Author: lordgnu <lordgnu@me.com>
*/

require_once realpath(dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'bootstrap.php');

use app\models\Settings;
use nntmux\db\DB;
use nntmux\NZB;

$pdo = new DB;
$nzb = new NZB;

$items = $pdo->query("SELECT id,guid FROM releases WHERE size = 0");
$total = count($items);
$compl = 0;
echo "Updating file size for " . count($items) . " release(s)\n";

while ($item = array_pop($items))
{
	$nzbpath = $nzb->getNZBPath($item['guid'], Settings::value('..nzbpath'));

	ob_start();
	@readgzfile($nzbpath);
	$nzbfile = ob_get_contents();
	ob_end_clean();

	$ret = $nzb->nzbFileList($nzbfile);

	$filesize = '0';

	foreach ($ret as $file) {
		$filesize = bcadd($filesize, $file['size']);
	}

	$pdo->queryExec("UPDATE releases SET size = '{$filesize}' WHERE id = '{$item['id']}' LIMIT 1");

	$compl++;
	echo sprintf("[%6d / %6d] %0.2f",$compl, $total, ($compl/$total) * 100) . '%' . "\n";
}

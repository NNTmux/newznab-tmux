<?php
require_once realpath(dirname(dirname(dirname(__DIR__))) . DIRECTORY_SEPARATOR . 'indexer.php');

use newznab\db\Settings;


$pdo = new Settings();
$covers = $updated = $deleted = 0;

if ($argc == 1 || $argv[1] != 'true') {
    exit($pdo->log->error("\nThis script will check all images in covers/music and compare to db->musicinfo.\nTo run:\nphp $argv[0] true\n"));
}

$path2covers = NN_COVERS . 'music' . DS;

$dirItr = new \RecursiveDirectoryIterator($path2covers);
$itr = new \RecursiveIteratorIterator($dirItr, \RecursiveIteratorIterator::LEAVES_ONLY);
foreach ($itr as $filePath) {
    if (is_file($filePath) && preg_match('/\d+\.jpg/', $filePath)) {
        preg_match('/(\d+)\.jpg/', basename($filePath), $match);
        if (isset($match[1])) {
            $run = $pdo->queryDirect("UPDATE musicinfo SET cover = 1 WHERE cover = 0 AND id = " . $match[1]);
            if ($run->rowCount() >= 1) {
                $covers++;
            } else {
                $run = $pdo->queryDirect("SELECT id FROM musicinfo WHERE id = " . $match[1]);
                if ($run->rowCount() == 0) {
                    echo $pdo->log->info($filePath . " not found in db.");
                }
            }
        }
    }
}

$qry = $pdo->queryDirect("SELECT id FROM musicinfo WHERE cover = 1");
if ($qry instanceof \Traversable) {
	foreach ($qry as $rows) {
		if (!is_file($path2covers . $rows['id'] . '.jpg')) {
			$pdo->queryDirect("UPDATE musicinfo SET cover = 0 WHERE cover = 1 AND id = " . $rows['id']);
			echo $pdo->log->info($path2covers . $rows['id'] . ".jpg does not exist.");
			$deleted++;
		}
	}
}
echo $pdo->log->header($covers . " covers set.");
echo $pdo->log->header($deleted . " music unset.");

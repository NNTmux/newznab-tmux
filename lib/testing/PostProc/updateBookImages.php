<?php
require_once(dirname(__FILE__) . "/../../../bin/config.php");
require_once(WWW_DIR . "/lib/framework/db.php");


$pdo = new DB();
$covers = $updated = $deleted = 0;

if ($argc == 1 || $argv[1] != 'true') {
    exit($pdo->log->error("\nThis script will check all images in covers/book and compare to db->bookinfo.\nTo run:\nphp $argv[0] true\n"));
}


$path2covers = NN_COVERS . 'book' . DS;

$dirItr = new \RecursiveDirectoryIterator($path2covers);
$itr = new \RecursiveIteratorIterator($dirItr, \RecursiveIteratorIterator::LEAVES_ONLY);
foreach ($itr as $filePath) {
    if (is_file($filePath) && preg_match('/\d+\.jpg/', $filePath)) {
        preg_match('/(\d+)\.jpg/', basename($filePath), $match);
        if (isset($match[1])) {
            $run = $pdo->queryDirect("UPDATE bookinfo SET cover = 1 WHERE cover = 0 AND ID = " . $match[1]);
            if ($run->rowCount() >= 1) {
                $covers++;
            } else {
                $run = $pdo->queryDirect("SELECT ID FROM bookinfo WHERE ID = " . $match[1]);
                if ($run->rowCount() == 0) {
                    echo $pdo->log->info($filePath . " not found in db.");
                }
            }
        }
    }
}

$qry = $pdo->queryDirect("SELECT ID FROM bookinfo WHERE cover = 1");
if ($qry instanceof \Traversable) {
	foreach ($qry as $rows) {
		if (!is_file($path2covers . $rows['ID'] . '.jpg')) {
			$pdo->queryDirect("UPDATE bookinfo SET cover = 0 WHERE cover = 1 AND ID = " . $rows['ID']);
			echo $pdo->log->info($path2covers . $rows['id'] . ".jpg does not exist.");
			$deleted++;
		}
	}
}
echo $pdo->log->header($covers . " covers set.");
echo $pdo->log->header($deleted . " books unset.");
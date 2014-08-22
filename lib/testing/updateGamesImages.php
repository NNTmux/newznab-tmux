<?php
require_once(dirname(__FILE__) . "/../../bin/config.php");
require_once(WWW_DIR . "/lib/framework/db.php");
require_once("../ColorCLI.php");

$pdo = new DB();
$covers = $updated = $deleted = 0;
$c = new ColorCLI();

if ($argc == 1 || $argv[1] != 'true') {
	exit($c->error("\nThis script will check all images in covers/games and compare to db->gamesinfo.\nTo run:\nphp $argv[0] true\n"));
}
$path2covers = WWW_DIR . 'covers/games' . '/';

$dirItr = new RecursiveDirectoryIterator($path2covers);
$itr = new RecursiveIteratorIterator($dirItr, RecursiveIteratorIterator::LEAVES_ONLY);
foreach ($itr as $filePath) {
	if (is_file($filePath) && preg_match('/\d+\.jpg/', $filePath)) {
		preg_match('/(\d+)\.jpg/', basename($filePath), $match);
		if (isset($match[1])) {
			$run = $pdo->queryDirect("UPDATE gamesinfo SET cover = 1 WHERE cover = 0 AND id = " . $match[1]);
			if ($run !== false) {
				if ($run->rowCount() >= 1) {
					$covers++;
				} else {
					$run = $pdo->queryDirect("SELECT id FROM gamesinfo WHERE id = " . $match[1]);
					if ($run !== false && $run->rowCount() == 0) {
						echo $c->info($filePath . " not found in db.");
					}
				}
			}
		}
	}
}

$qry = $pdo->queryDirect("SELECT id FROM gamesinfo WHERE cover = 1");
foreach ($qry as $rows) {
	if (!is_file($path2covers . $rows['id'] . '.jpg')) {
		$pdo->queryDirect("UPDATE gamesinfo SET cover = 0 WHERE cover = 1 AND id = " . $rows['id']);
		echo $c->info($path2covers . $rows['id'] . ".jpg does not exist.");
		$deleted++;
	}
}
echo $c->header($covers . " covers set.");
echo $c->header($deleted . " games unset.");
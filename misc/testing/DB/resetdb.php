<?php
require_once realpath(dirname(dirname(dirname(__DIR__))) . DIRECTORY_SEPARATOR . 'indexer.php');

use newznab\db\Settings;
use newznab\utility\Utility;
use newznab\ReleaseImage;
use newznab\NZB;
use newznab\ConsoleTools;
use newznab\SphinxSearch;

Utility::clearScreen();
$pdo = new Settings();

if (!isset($argv[1]) || (isset($argv[1]) && $argv[1] !== 'true'))
	exit($pdo->log->error("\nThis script removes all releases and release related files. To run:\nphp resetdb.php true\n"));

echo $pdo->log->warning("This script removes all releases, nzb files, samples, previews , nfos, truncates all article tables and resets all groups.");
echo $pdo->log->header("Are you sure you want reset the DB?  Type 'DESTROY' to continue:  \n");
echo $pdo->log->warningOver("\n");
$line = fgets(STDIN);
if (trim($line) != 'DESTROY')
	exit($pdo->log->error("This script is dangerous you must type DESTROY for it function."));

echo "\n";
echo $pdo->log->header("Thank you, continuing...\n\n");

$timestart = time();
$relcount = 0;
$ri = new ReleaseImage($pdo);
$nzb = new NZB($pdo);
$consoletools = new ConsoleTools(['ColorCLI' => $pdo->log]);

$pdo->queryExec("UPDATE groups SET first_record = 0, first_record_postdate = NULL, last_record = 0, last_record_postdate = NULL, last_updated = NULL");
echo $pdo->log->primary("Reseting all groups completed.");

$arr = [
		"videos", "tv_episodes", "tv_info", "releasenfo", "release_comments", 'sharing', 'sharing_sites',
		"usercart", "usermovies", "userseries", "movieinfo", "musicinfo", "release_files",
		"releaseaudio", "releasesubs", "releasevideo", "releaseextrafull", "parts",
		"partrepair", "binaries", "releases", "spotnabsources",  "anidb_titles", "anidb_info", "anidb_episodes"
];
foreach ($arr as &$value) {
	$rel = $pdo->queryExec("TRUNCATE TABLE $value");
	if ($rel !== false)
		echo $pdo->log->primary("Truncating ${value} completed.");
}
unset($value);

$sql = "SHOW table status";

$tables = $pdo->query($sql);
foreach ($tables as $row) {
	$tbl = $row['name'];
	if (preg_match('/binaries_\d+/', $tbl) || preg_match('/parts_\d+/', $tbl) || preg_match('/partrepair_\d+/', $tbl) || preg_match('/\d+_binaries/', $tbl) || preg_match('/\d+_parts/', $tbl) || preg_match('/\d+_partrepair_\d+/', $tbl)) {
		$rel = $pdo->queryDirect(sprintf('DROP TABLE %s', $tbl));
		if ($rel !== false)
			echo $pdo->log->primary("Dropping ${tbl} completed.");
	}
}

(new SphinxSearch())->truncateRTIndex('releases_rt');

$pdo->optimise(false, 'full');

echo $pdo->log->header("Deleting nzbfiles subfolders.");
try {
	$files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($pdo->getSetting('nzbpath'), \RecursiveDirectoryIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
	foreach ($files as $file) {
		if (basename($file) != '.gitignore' && basename($file) != 'tmpunrar') {
			$todo = ($file->isDir() ? 'rmdir' : 'unlink');
			@$todo($file);
		}
	}
} catch (UnexpectedValueException $e) {
	echo $pdo->log->error($e->getMessage());
}

echo $pdo->log->header("Deleting all images, previews and samples that still remain.");
try {
	$dirItr = new \RecursiveDirectoryIterator(NN_COVERS);
	$itr = new \RecursiveIteratorIterator($dirItr, \RecursiveIteratorIterator::LEAVES_ONLY);
	foreach ($itr as $filePath) {
		if (basename($filePath) != '.gitignore' && basename($filePath) != 'no-cover.jpg' && basename($filePath) != 'no-backdrop.jpg') {
			@unlink($filePath);
		}
	}
} catch (UnexpectedValueException $e) {
	echo $pdo->log->error($e->getMessage());
}

echo $pdo->log->header("Deleted all releases, images, previews and samples. This script ran for " . $consoletools->convertTime(TIME() - $timestart));

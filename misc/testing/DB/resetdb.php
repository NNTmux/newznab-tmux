<?php

require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use nntmux\NZB;
use nntmux\db\DB;
use nntmux\ColorCLI;
use App\Models\Settings;
use nntmux\ConsoleTools;
use nntmux\ReleaseImage;
use nntmux\SphinxSearch;
use nntmux\utility\Utility;

Utility::clearScreen();
$pdo = new DB();

if (! isset($argv[1]) || (isset($argv[1]) && $argv[1] !== 'true')) {
    exit(ColorCLI::error("\nThis script removes all releases and release related files. To run:\nphp resetdb.php true\n"));
}

echo ColorCLI::warning('This script removes all releases, nzb files, samples, previews , nfos, truncates all article tables and resets all groups.');
echo ColorCLI::header("Are you sure you want reset the DB?  Type 'DESTROY' to continue:  \n");
echo ColorCLI::warningOver("\n");
$line = fgets(STDIN);
if (trim($line) !== 'DESTROY') {
    exit(ColorCLI::error('This script is dangerous you must type DESTROY for it function.'));
}

echo "\n";
echo ColorCLI::header("Thank you, continuing...\n\n");

$timestart = time();
$relcount = 0;
$ri = new ReleaseImage();
$nzb = new NZB($pdo);
$consoletools = new ConsoleTools();

$pdo->queryExec('UPDATE groups SET first_record = 0, first_record_postdate = NULL, last_record = 0, last_record_postdate = NULL, last_updated = NULL');
echo ColorCLI::primary('Reseting all groups completed.');

$arr = [
        'videos', 'tv_episodes', 'tv_info', 'release_nfos', 'release_comments', 'sharing', 'sharing_sites',
        'users_releases', 'user_movies', 'user_series', 'movieinfo', 'musicinfo', 'release_files', 'audio_data', 'release_subtitles', 'video_data',        'releaseextrafull', 'releases', 'spotnabsources', 'anidb_titles', 'anidb_info', 'anidb_episodes', 'releases_groups',
];
foreach ($arr as &$value) {
    $rel = $pdo->queryExec("TRUNCATE TABLE $value");
    if ($rel !== false) {
        echo ColorCLI::primary("Truncating ${value} completed.");
    }
}
unset($value);

$sql = "CALL loop_cbpm('truncate')";
echo ColorCLI::primary('Truncating binaries, collections, missed_parts and parts tables...');
$result = $pdo->query($sql);
echo ColorCLI::primary('Truncating completed.');

(new SphinxSearch())->truncateRTIndex('releases_rt');

$pdo->optimise(false, 'full');

echo ColorCLI::header('Deleting nzbfiles subfolders.');
try {
    $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(Settings::settingValue('..nzbpath'), \RecursiveDirectoryIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        if (basename($file) !== '.gitignore' && basename($file) !== 'tmpunrar') {
            $todo = ($file->isDir() ? 'rmdir' : 'unlink');
            @$todo($file);
        }
    }
} catch (UnexpectedValueException $e) {
    echo ColorCLI::error($e->getMessage());
}

echo ColorCLI::header('Deleting all images, previews and samples that still remain.');
try {
    $dirItr = new \RecursiveDirectoryIterator(NN_COVERS);
    $itr = new \RecursiveIteratorIterator($dirItr, \RecursiveIteratorIterator::LEAVES_ONLY);
    foreach ($itr as $filePath) {
        if (basename($filePath) !== '.gitignore' && basename($filePath) !== 'no-cover.jpg' && basename($filePath) !== 'no-backdrop.jpg') {
            @unlink($filePath);
        }
    }
} catch (UnexpectedValueException $e) {
    echo ColorCLI::error($e->getMessage());
}

echo ColorCLI::header('Deleted all releases, images, previews and samples. This script ran for '.$consoletools->convertTime(time() - $timestart));

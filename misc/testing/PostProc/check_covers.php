<?php

// --------------------------------------------------------------
//          Scan for releases missing previews on disk
// --------------------------------------------------------------
require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use Blacklight\db\DB;
use Blacklight\Movie;
use Blacklight\ColorCLI;
use Blacklight\utility\Utility;

$pdo = new DB();
$movie = new Movie(['Echo' => true, 'Settings' => $pdo]);

$row = $pdo->queryOneRow("SELECT value FROM settings WHERE setting = 'coverspath'");
if ($row !== false) {
    Utility::setCoversConstant($row['value']);
} else {
    die('Unable to determine covers path!'.PHP_EOL);
}

$path2cover = NN_COVERS.'movies'.DS;

if (isset($argv[1]) && ($argv[1] === 'true' || $argv[1] === 'check')) {
    $couldbe = $argv[1] === 'true' ? $couldbe = 'had ' : 'could have ';
    $limit = $counterfixed = 0;
    if (isset($argv[2]) && is_numeric($argv[2])) {
        $limit = $argv[2];
    }
    echo ColorCLI::header('Scanning for releases missing covers');
    $res = $pdo->queryDirect('SELECT r.id, r.imdbid
								FROM releases r
								LEFT JOIN movieinfo m ON m.imdbid = r.imdbid
								WHERE nzbstatus = 1 AND m.cover = 1 AND adddate >  (NOW() - INTERVAL 5 HOUR)');
    if ($res instanceof \Traversable) {
        foreach ($res as $row) {
            $nzbpath = $path2cover.$row['imdbid'].'-cover.jpg';
            if (! file_exists($nzbpath)) {
                $counterfixed++;
                echo ColorCLI::warning('Missing cover '.$nzbpath);
                if ($argv[1] === 'true') {
                    $cover = $movie->updateMovieInfo($row['imdbid']);
                    if ($cover === false || ! file_exists($nzbpath)) {
                        $pdo->queryExec('UPDATE movieinfo m SET m.cover = 0 WHERE m.imdbid = %d', $row['imdbid']);
                    }
                }
            }

            if (($limit > 0) && ($counterfixed >= $limit)) {
                break;
            }
        }
    }
    echo ColorCLI::header('Total releases missing covers that '.$couldbe.'their covers fixed = '.number_format($counterfixed));
} else {
    exit(ColorCLI::header("\nThis script checks if release covers actually exist on disk.\n\n"
        ."Releases without covers may be reset for post-processing, thus regenerating them and related meta data.\n\n"
        ."Useful for recovery after filesystem corruption, or as an alternative re-postprocessing tool.\n\n"
        ."Optional LIMIT parameter restricts number of releases to be reset.\n\n"
        ."php $argv[0] check [LIMIT]  ...: Dry run, displays missing covers.\n"
        ."php $argv[0] true  [LIMIT]  ...: Re-process releases missing covers.\n"));
}

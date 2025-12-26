<?php

// --------------------------------------------------------------
//          Scan for releases missing previews on disk
// --------------------------------------------------------------
require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use App\Models\Release;
use App\Services\Nzb\NzbService;
use App\Services\ReleaseImageService;
use Blacklight\ColorCLI;
use Blacklight\Releases;
use Illuminate\Support\Facades\DB;

$pdo = DB::connection()->getPdo();
$colorCli = new ColorCLI;

$path2preview = storage_path('covers/preview');

if (isset($argv[1]) && ($argv[1] === 'true' || $argv[1] === 'check')) {
    $releases = new Releases;
    $nzb = app(NzbService::class);
    $releaseImage = new ReleaseImageService;
    $consoletools = new ColorCLI;
    $couldbe = $argv[1] === 'true' ? $couldbe = 'were ' : 'could be ';
    $limit = $counterfixed = 0;
    if (isset($argv[2]) && is_numeric($argv[2])) {
        $limit = $argv[2];
    }
    $colorCli->header('Scanning for releases missing previews');
    $res = Release::query()->select('id', 'guid')->where(['haspreview' => 1])->get()->toArray();
    foreach ($res as $row) {
        $nzbpath = $path2preview.$row['guid'].'_thumb.jpg';
        if (! file_exists($nzbpath)) {
            $counterfixed++;
            $colorCli->warning('Missing preview '.$nzbpath);
            if ($argv[1] === 'true') {
                $pdo->exec(
                    sprintf('UPDATE releases SET consoleinfo_id = NULL, gamesinfo_id = 0, imdbid = NULL, musicinfo_id = NULL,	bookinfo_id = NULL, videos_id = 0, xxxinfo_id = 0, passwordstatus = -1, haspreview = -1, jpgstatus = 0, videostatus = 0, audiostatus = 0, nfostatus = -1 WHERE id = %s', $row['id'])
                );
            }
        }

        if (($limit > 0) && ($counterfixed >= $limit)) {
            break;
        }
    }
    $colorCli->header('Total releases missing previews that '.$couldbe.'reset for reprocessing= '.number_format($counterfixed));
} else {
    $colorCli->header("\nThis script checks if release previews actually exist on disk.\n\n"
            ."Releases without previews may be reset for post-processing, thus regenerating them and related meta data.\n\n"
            ."Useful for recovery after filesystem corruption, or as an alternative re-postprocessing tool.\n\n"
            ."Optional LIMIT parameter restricts number of releases to be reset.\n\n"
            ."php $argv[0] check [LIMIT]  ...: Dry run, displays missing previews.\n"
            ."php $argv[0] true  [LIMIT]  ...: Re-process releases missing previews.\n");
    exit();
}

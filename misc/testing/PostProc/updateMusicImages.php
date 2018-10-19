<?php

require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use Blacklight\ColorCLI;
use App\Models\MusicInfo;

$covers = $updated = $deleted = 0;

if ($argc === 1 || $argv[1] !== 'true') {
    ColorCLI::error("\nThis script will check all images in covers/music and compare to db->musicinfo.\nTo run:\nphp $argv[0] true\n");
    exit();
}

$path2covers = NN_COVERS.'music'.DS;

$dirItr = new \RecursiveDirectoryIterator($path2covers);
$itr = new \RecursiveIteratorIterator($dirItr, \RecursiveIteratorIterator::LEAVES_ONLY);
foreach ($itr as $filePath) {
    if (is_file($filePath) && preg_match('/\d+\.jpg/', $filePath)) {
        preg_match('/(\d+)\.jpg/', basename($filePath), $match);
        if (isset($match[1])) {
            $run = MusicInfo::query()->where('cover', '=', 0)->where('id', $match[1])->update(['cover' => 1]);
            if ($run >= 1) {
                $covers++;
            } else {
                $run = MusicInfo::query()->where('id', $match[1])->select(['id'])->get();
                if ($run->count() === 0) {
                    ColorCLI::info($filePath.' not found in db.');
                }
            }
        }
    }
}

$qry = MusicInfo::query()->where('cover', '=', 1)->select(['id'])->get();
foreach ($qry as $rows) {
    if (! is_file($path2covers.$rows['id'].'.jpg')) {
        MusicInfo::query()->where(['cover' => 1, 'id' => $rows['id']])->update(['cover' => 0]);
        ColorCLI::info($path2covers.$rows['id'].'.jpg does not exist.');
        $deleted++;
    }
}
ColorCLI::header($covers.' covers set.');
ColorCLI::header($deleted.' music unset.');

<?php

require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use App\Models\Settings;
use Blacklight\ColorCLI;
use App\Models\GamesInfo;
use Blacklight\utility\Utility;
use Illuminate\Support\Facades\DB;

$covers = $updated = $deleted = 0;

if ($argc === 1 || $argv[1] !== 'true') {
    exit(ColorCLI::error("\nThis script will check all images in covers/games and compare to db->gamesinfo.\nTo run:\nphp $argv[0] true\n"));
}

$pdo = DB::connection()->getPdo();

$row = Settings::settingValue('site.main.coverspath');
if ($row !== null) {
    Utility::setCoversConstant($row);
} else {
    die("Unable to set Covers' constant!\n");
}
$path2covers = NN_COVERS.'games'.DS;

$dirItr = new \RecursiveDirectoryIterator($path2covers);
$itr = new \RecursiveIteratorIterator($dirItr, \RecursiveIteratorIterator::LEAVES_ONLY);
foreach ($itr as $filePath) {
    if (is_file($filePath) && preg_match('/\d+\.jpg/', $filePath)) {
        preg_match('/(\d+)\.jpg/', basename($filePath), $match);
        if (isset($match[1])) {
            $run = GamesInfo::query()->where('cover', '=', 0)->where('id', $match[1])->update(['cover' => 1]);
            if ($run !== false) {
                if ($run >= 1) {
                    $covers++;
                } else {
                    $run = GamesInfo::query()->where('id', $match[1])->select(['id'])->get();
                    if ($run !== null && $run === 0) {
                        echo ColorCLI::info($filePath.' not found in db.');
                    }
                }
            }
        }
    }
}

$qry = GamesInfo::query()->where('cover', '=', 1)->select(['id'])->get();
if ($qry instanceof \Traversable) {
    foreach ($qry as $rows) {
        if (! is_file($path2covers.$rows['id'].'.jpg')) {
            GamesInfo::query()->where(['cover' => 1, 'id' => $rows['id']])->update(['cover' => 0]);
            echo ColorCLI::info($path2covers.$rows['id'].'.jpg does not exist.');
            $deleted++;
        }
    }
}
echo ColorCLI::header($covers.' covers set.');
echo ColorCLI::header($deleted.' games unset.');

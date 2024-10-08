<?php

require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use App\Models\GamesInfo;
use Blacklight\ColorCLI;
use Illuminate\Support\Facades\File;

$covers = $updated = $deleted = 0;
$colorCli = new ColorCLI;

if ($argc === 1 || $argv[1] !== 'true') {
    $colorCli->error("\nThis script will check all images in covers/games and compare to db->gamesinfo.\nTo run:\nphp $argv[0] true\n");
    exit();
}

$path2covers = storage_path('covers/games/');

$itr = File::allFiles($path2covers);
foreach ($itr as $filePath) {
    if (is_file($filePath->getPathname()) && preg_match('/\d+\.jpg$/', $filePath->getPathname())) {
        preg_match('/(\d+)\.jpg$/', basename($filePath->getPathname()), $hit);
        if (isset($hit[1])) {
            $run = GamesInfo::query()->where('cover', '=', 0)->where('id', $hit[1])->update(['cover' => 1]);
            if ($run !== false) {
                if ($run >= 1) {
                    $covers++;
                } else {
                    $run = GamesInfo::query()->where('id', $hit[1])->select(['id'])->get();
                    if ($run !== null && $run === 0) {
                        $colorCli->info($filePath->getPathname().' not found in db.');
                    }
                }
            }
        }
    }
}

$qry = GamesInfo::query()->where('cover', '=', 1)->select(['id'])->get();
foreach ($qry as $rows) {
    if (! is_file($path2covers.$rows['id'].'.jpg')) {
        GamesInfo::query()->where(['cover' => 1, 'id' => $rows['id']])->update(['cover' => 0]);
        $colorCli->info($path2covers.$rows['id'].'.jpg does not exist.');
        $deleted++;
    }
}
$colorCli->header($covers.' covers set.');
$colorCli->header($deleted.' games unset.');

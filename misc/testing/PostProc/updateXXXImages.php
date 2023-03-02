<?php

require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use App\Models\XxxInfo;
use Blacklight\ColorCLI;
use Illuminate\Support\Facades\File;

$covers = $updated = $deleted = 0;
$colorCli = new ColorCLI();

if ($argc === 1 || $argv[1] !== 'true') {
    $colorCli->error("\nThis script will check all images in covers/xxx and compare to db->xxxinfo.\nTo run:\nphp $argv[0] true\n");
    exit();
}

$path2covers = NN_COVERS.'xxx'.DS;

$itr = File::allFiles($path2covers);
foreach ($itr as $filePath) {
    if (is_file($filePath->getPathname()) && preg_match('/-cover\.jpg$/', $filePath->getPathname())) {
        preg_match('/(\d+)-cover\.jpg$/', $filePath->getPathname(), $hit);
        if (isset($hit[1])) {
            $run = XxxInfo::query()->where('cover', '=', 0)->where('id', $hit[1])->update(['cover' => 1]);
            if ($run >= 1) {
                $covers++;
            } else {
                $run = XxxInfo::query()->where('id', $hit[1])->select(['id'])->get();
                if ($run->count() === 0) {
                    $colorCli->info($filePath->getPathname().' not found in db.');
                }
            }
        }
    }
    if (is_file($filePath->getPathname()) && preg_match('/-backdrop\.jpg$/', $filePath->getPathname())) {
        preg_match('/(\d+)-backdrop\.jpg$/', $filePath->getPathname(), $match1);
        if (isset($match1[1])) {
            $run = XxxInfo::query()->where(['backdrop' => 0, 'id' => $match1[1]])->update(['backdrop' => 1]);
            if ($run >= 1) {
                $updated++;
            } else {
                $run = XxxInfo::query()->where('id', $match1[1])->select(['id'])->get();
                if ($run->count() === 0) {
                    $colorCli->info($filePath->getPathname().' not found in db.');
                }
            }
        }
    }
}

$qry = XxxInfo::query()->where('cover', '=', 1)->select(['id'])->get();
foreach ($qry as $rows) {
    if (! is_file($path2covers.$rows['id'].'-cover.jpg')) {
        XxxInfo::query()->where(['cover' => 1, 'id' => $rows['id']])->update(['cover' => 0]);
        $colorCli->info($path2covers.$rows['id'].'-cover.jpg does not exist.');
        $deleted++;
    }
}
$qry1 = XxxInfo::query()->where('backdrop', '=', 1)->select(['id'])->get();
foreach ($qry1 as $rows) {
    if (! is_file($path2covers.$rows['id'].'-backdrop.jpg')) {
        XxxInfo::query()->where(['backdrop' => 1, 'id' => $rows['id']])->update(['backdrop' => 0]);
        $colorCli->info($path2covers.$rows['id'].'-backdrop.jpg does not exist.');
        $deleted++;
    }
}
$colorCli->header($covers.' covers set.');
$colorCli->header($updated.' backdrops set.');
$colorCli->header($deleted.' movies unset.');

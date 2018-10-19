<?php

require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use App\Models\Settings;
use Blacklight\ColorCLI;
use App\Models\MovieInfo;
use Blacklight\utility\Utility;

$covers = $updated = $deleted = 0;

if ($argc === 1 || $argv[1] !== 'true') {
    ColorCLI::error("\nThis script will check all images in covers/movies and compare to db->movieinfo.\nTo run:\nphp $argv[0] true\n");
    exit();
}

$row = Settings::settingValue('site.main.coverspath');
if ($row !== null) {
    Utility::setCoversConstant($row);
} else {
    die("Unable to set Covers' constant!\n");
}
$path2covers = NN_COVERS.'movies'.DS;

$dirItr = new \RecursiveDirectoryIterator($path2covers);
$itr = new \RecursiveIteratorIterator($dirItr, \RecursiveIteratorIterator::LEAVES_ONLY);
foreach ($itr as $filePath) {
    if (is_file($filePath) && preg_match('/-cover\.jpg/', $filePath)) {
        preg_match('/(\d+)-cover\.jpg/', basename($filePath), $match);
        if (isset($match[1])) {
            $run = MovieInfo::query()->where('cover', '=', 0)->where('imdbid', $match[1])->update(['cover' => 1]);
            if ($run >= 1) {
                $covers++;
            } else {
                $run = MovieInfo::query()->where('imdbid', '=', $match[1])->select(['imdbid'])->get();
                if ($run->count() === 0) {
                    ColorCLI::info($filePath.' not found in db.');
                }
            }
        }
    }
    if (is_file($filePath) && preg_match('/-backdrop\.jpg/', $filePath)) {
        preg_match('/(\d+)-backdrop\.jpg/', basename($filePath), $match1);
        if (isset($match1[1])) {
            $run = MovieInfo::query()->where(['backdrop' => 0, 'imdbid' => $match1[1]])->update(['backdrop' => 1]);
            if ($run >= 1) {
                $updated++;
            } else {
                $run = MovieInfo::query()->where('imdbid', $match1[1])->select(['imdbid'])->get();
                if ($run->count() === 0) {
                    ColorCLI::info($filePath.' not found in db.');
                }
            }
        }
    }
}

$qry = MovieInfo::query()->where('cover', '=', 1)->select(['imdbid'])->get();
    foreach ($qry as $rows) {
        if (! is_file($path2covers.$rows['imdbid'].'-cover.jpg')) {
            MovieInfo::query()->where('cover', '=', 1)->where('imdbid', $rows['imdbid'])->update(['cover' => 0]);
            ColorCLI::info($path2covers.$rows['imdbid'].'-cover.jpg does not exist.');
            $deleted++;
        }
    }
    $qry1 = MovieInfo::query()->where('backdrop', '=', 1)->select(['imdbid'])->get();
    foreach ($qry1 as $rows) {
        if (! is_file($path2covers.$rows['imdbid'].'-backdrop.jpg')) {
            MovieInfo::query()->where('backdrop', '=', 1)->where('imdbid', $rows['imdbid'])->update(['backdrop' => 0]);
            ColorCLI::info($path2covers.$rows['imdbid'].'-backdrop.jpg does not exist.');
            $deleted++;
        }
    }
ColorCLI::header($covers.' covers set.');
ColorCLI::header($updated.' backdrops set.');
ColorCLI::header($deleted.' movies unset.');

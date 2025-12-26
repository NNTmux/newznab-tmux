<?php

require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use App\Models\MovieInfo;

use Illuminate\Support\Facades\File;

$covers = $updated = $deleted = 0;


if ($argc === 1 || $argv[1] !== 'true') {
    cli()->error("\nThis script will check all images in covers/movies and compare to db->movieinfo.\nTo run:\nphp $argv[0] true\n");
    exit();
}

$path2covers = storage_path('covers/movies');

$itr = File::allFiles($path2covers);
foreach ($itr as $filePath) {
    if (is_file($filePath->getPathname()) && preg_match('/-cover\.jpg$/', $filePath->getPathname())) {
        preg_match('/(\d+)-cover\.jpg$/', $filePath->getPathname(), $hit);
        if (isset($hit[1])) {
            $run = MovieInfo::query()->where('cover', '=', 0)->where('imdbid', $hit[1])->update(['cover' => 1]);
            if ($run >= 1) {
                $covers++;
            } else {
                $run = MovieInfo::query()->where('imdbid', '=', $hit[1])->select(['imdbid'])->get();
                if ($run->count() === 0) {
                    cli()->error($filePath.' not found in db.');
                }
            }
        }
    }
    if (is_file($filePath) && preg_match('/-backdrop\.jpg$/', $filePath->getPathname())) {
        preg_match('/(\d+)-backdrop\.jpg$/', $filePath->getPathname(), $match1);
        if (isset($match1[1])) {
            $run = MovieInfo::query()->where(['backdrop' => 0, 'imdbid' => $match1[1]])->update(['backdrop' => 1]);
            if ($run >= 1) {
                $updated++;
            } else {
                $run = MovieInfo::query()->where('imdbid', $match1[1])->select(['imdbid'])->get();
                if ($run->count() === 0) {
                    cli()->error($filePath->getPathname().' not found in db.');
                }
            }
        }
    }
}

$qry = MovieInfo::query()->where('cover', '=', 1)->select(['imdbid'])->get();
foreach ($qry as $rows) {
    if (! is_file($path2covers.$rows['imdbid'].'-cover.jpg')) {
        MovieInfo::query()->where('cover', '=', 1)->where('imdbid', $rows['imdbid'])->update(['cover' => 0]);
        cli()->info($path2covers.$rows['imdbid'].'-cover.jpg does not exist.');
        $deleted++;
    }
}
$qry1 = MovieInfo::query()->where('backdrop', '=', 1)->select(['imdbid'])->get();
foreach ($qry1 as $rows) {
    if (! is_file($path2covers.$rows['imdbid'].'-backdrop.jpg')) {
        MovieInfo::query()->where('backdrop', '=', 1)->where('imdbid', $rows['imdbid'])->update(['backdrop' => 0]);
        cli()->info($path2covers.$rows['imdbid'].'-backdrop.jpg does not exist.');
        $deleted++;
    }
}
cli()->info($covers.' covers set.');
cli()->info($updated.' backdrops set.');
cli()->info($deleted.' movies unset.');

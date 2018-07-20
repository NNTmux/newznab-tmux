<?php

use App\Models\BookInfo;
use Blacklight\ColorCLI;

require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';


$covers = $updated = $deleted = 0;

if ($argc === 1 || $argv[1] !== 'true') {
    exit(ColorCLI::error("\nThis script will check all images in covers/book and compare to db->bookinfo.\nTo run:\nphp $argv[0] true\n"));
}

$path2covers = NN_COVERS.'book'.DS;

$dirItr = new \RecursiveDirectoryIterator($path2covers);
$itr = new \RecursiveIteratorIterator($dirItr, \RecursiveIteratorIterator::LEAVES_ONLY);
foreach ($itr as $filePath) {
    if (is_file($filePath) && preg_match('/\d+\.jpg/', $filePath)) {
        preg_match('/(\d+)\.jpg/', basename($filePath), $match);
        if (isset($match[1])) {
            $run = BookInfo::query()->where('cover', '=', 0)->where('id', $match[1])->update(['cover' => 1]);
            if ($run >= 1) {
                $covers++;
            } else {
                $run = BookInfo::query()->where('id', $match[1])->select(['id'])->get();
                if ($run->count() === 0) {
                    echo ColorCLI::info($filePath.' not found in db.');
                }
            }
        }
    }
}

$qry = BookInfo::query()->where('cover', '=', 1)->select(['id'])->get();
    foreach ($qry as $rows) {
        if (! is_file($path2covers.$rows['id'].'.jpg')) {
            BookInfo::query()->where(['cover' => 1, 'id' => $rows['id']])->update(['cover' => 0]);
            echo ColorCLI::info($path2covers.$rows['id'].'.jpg does not exist.');
            $deleted++;
        }
    }
echo ColorCLI::header($covers.' covers set.');
echo ColorCLI::header($deleted.' books unset.');

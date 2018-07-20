<?php

require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use Blacklight\ColorCLI;
use App\Models\ConsoleInfo;

$covers = $updated = $deleted = 0;

if ($argc === 1 || $argv[1] !== 'true') {
    exit(ColorCLI::error("\nThis script will check all images in covers/console and compare to db->consoleinfo.\nTo run:\nphp $argv[0] true\n"));
}

$path2covers = NN_COVERS.'console'.DS;

$dirItr = new \RecursiveDirectoryIterator($path2covers);
$itr = new \RecursiveIteratorIterator($dirItr, \RecursiveIteratorIterator::LEAVES_ONLY);
foreach ($itr as $filePath) {
    if (is_file($filePath) && preg_match('/\d+\.jpg/', $filePath)) {
        preg_match('/(\d+)\.jpg/', basename($filePath), $match);
        if (isset($match[1])) {
            $run = ConsoleInfo::query()->where(
                [
                    'cover' => 0,
                    'id' => $match[1],
                ]
            )->update(['cover' => 1]);
            if ($run >= 1) {
                $covers++;
            } else {
                $run = ConsoleInfo::query()->where('id', $match[1])->value('id');
                if ($run === 0) {
                    echo ColorCLI::info($filePath.' not found in db.');
                }
            }
        }
    }
}

$qry = ConsoleInfo::query()->where('cover', '=', 1)->value('id');
if ($qry instanceof \Traversable) {
    foreach ($qry as $rows) {
        if (! is_file($path2covers.$rows['id'].'.jpg')) {
            ConsoleInfo::query()->where(
                [
                    ['cover' => 1],
                    ['id' => $rows['id']],
                ]
            )->update(['cover' => 0]);
            echo ColorCLI::info($path2covers.$rows['id'].'.jpg does not exist.');
            $deleted++;
        }
    }
}
echo ColorCLI::header($covers.' covers set.');
echo ColorCLI::header($deleted.' consoles unset.');

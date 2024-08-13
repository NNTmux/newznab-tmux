<?php

use App\Models\Release;
use App\Models\ReleaseFile;
use Blacklight\ColorCLI;
use Blacklight\Releases;

require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

$passFiles = ReleaseFile::query()->where('passworded', '=', 1)->select(['releases_id'])->groupBy('releases_id')->get();

$count1 = $count2 = 0;
if ($passFiles->isNotEmpty()) {
    $count1 = $passFiles->count();
    if ($count1 > 0) {
        foreach ($passFiles as $passFile) {
            $release = Release::query()->where('id', $passFile->releases_id)->where('passwordstatus', '=', 0)->update(['passwordstatus' => Releases::PASSWD_RAR]);
            if ($release !== 0) {
                $count2++;
            }
            echo '.';
        }
    }
}
(new ColorCLI)->info($count2.' releases marked as passworded');

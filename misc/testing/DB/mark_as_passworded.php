<?php

use App\Models\Release;
use App\Models\ReleaseFile;
use Blacklight\Releases;

require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

$passFiles = ReleaseFile::query()->where('passworded', '=', 1)->select(['releases_id'])->groupBy('releases_id')->get();

$count = 0;

if ($passFiles->isNotEmpty()) {
    foreach ($passFiles as $passFile) {
        Release::query()->where('id', $passFile->releases_id)->update(['passwordstatus' => Releases::PASSWD_RAR]);
        $count++;
    }
}
(new \Blacklight\ColorCLI())->info($count.' releases marked as passworded');

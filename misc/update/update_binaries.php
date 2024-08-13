<?php

require_once dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use App\Models\Settings;
use App\Models\UsenetGroup;
use Blacklight\Binaries;
use Blacklight\ColorCLI;
use Blacklight\NNTP;

$maxHeaders = (int) Settings::settingValue('..max_headers_iteration') ?: 1000000;
$colorCli = new ColorCLI;

// Create the connection here and pass
$nntp = new NNTP;
if ($nntp->doConnect() !== true) {
    $colorCli->error('Unable to connect to usenet.');
    exit();
}
$binaries = new Binaries(['NNTP' => $nntp]);

if (isset($argv[1]) && ! is_numeric($argv[1])) {
    $groupName = $argv[1];
    $colorCli->header("Updating group: $groupName");

    $group = UsenetGroup::getByName($groupName)->toArray();
    if (is_array($group)) {
        try {
            $binaries->updateGroup(
                $group,
                (isset($argv[2]) && is_numeric($argv[2]) && $argv[2] > 0 ? $argv[2] : $maxHeaders)
            );
        } catch (Throwable $e) {
            Illuminate\Support\Facades\Log::error($e->getMessage());
        }
    }
} else {
    try {
        $binaries->updateAllGroups((isset($argv[1]) && is_numeric($argv[1]) && $argv[1] > 0 ? $argv[1] :
            $maxHeaders));
    } catch (Throwable $e) {
        Illuminate\Support\Facades\Log::error($e->getMessage());
    }
}

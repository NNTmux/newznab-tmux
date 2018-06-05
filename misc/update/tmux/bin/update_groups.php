<?php

require_once dirname(__DIR__, 4).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use Blacklight\NNTP;
use App\Models\Group;
use Blacklight\ColorCLI;
use App\Models\ShortGroup;
use Blacklight\ConsoleTools;
use Illuminate\Support\Facades\DB;

$start = time();
$consoleTools = new ConsoleTools();

// Create the connection here and pass
$nntp = new NNTP();
if ($nntp->doConnect() !== true) {
    exit(ColorCLI::error('Unable to connect to usenet.'));
}

ColorCLI::doEcho(ColorCLI::header('Getting first/last for all your active groups.'));
$data = $nntp->getGroups();
if ($nntp->isError($data)) {
    exit(ColorCLI::error('Failed to getGroups() from nntp server.'));
}

ColorCLI::doEcho(ColorCLI::header('Inserting new values into short_groups table.'));

DB::unprepared('TRUNCATE TABLE short_groups');
DB::commit();

// Put into an array all active groups
$res = Group::query()->where('active', '=', 1)->orWhere('backfill', '=', 1)->get(['name'])->toArray();

foreach ($data as $newgroup) {
    if (myInArray($res, $newgroup['group'], 'name')) {
        ShortGroup::query()->insert(
            [
                'name' => $newgroup['group'],
                'first_record' => $newgroup['first'],
                'last_record' => $newgroup['last'],
                'updated' => now(),
            ]
        );
        ColorCLI::doEcho(ColorCLI::primary('Updated '.$newgroup['group']));
    }
}
ColorCLI::doEcho(ColorCLI::header('Running time: '.$consoleTools->convertTimer(time() - $start)));

function myInArray($array, $value, $key)
{
    //loop through the array
    foreach ($array as $val) {
        //if $val is an array cal myInArray again with $val as array input
        if (is_array($val)) {
            if (myInArray($val, $value, $key)) {
                return true;
            }
        } elseif ($array[$key] === $value) {
            return true;
        }
    }

    return false;
}

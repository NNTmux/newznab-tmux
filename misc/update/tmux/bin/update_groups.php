<?php

require_once dirname(__DIR__, 4).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use nntmux\NNTP;
use nntmux\ColorCLI;
use App\Models\Group;
use nntmux\ConsoleTools;
use App\Models\ShortGroup;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

$start = time();
$consoleTools = new ConsoleTools();

// Create the connection here and pass
$nntp = new NNTP();
if ($nntp->doConnect() !== true) {
    exit(ColorCLI::error('Unable to connect to usenet.'));
}

ColorCLI::doEcho(ColorCLI::header('Getting first/last for all your active groups.'), true);
$data = $nntp->getGroups();
if ($nntp->isError($data)) {
    exit(ColorCLI::error('Failed to getGroups() from nntp server.'));
}

ColorCLI::doEcho(ColorCLI::header('Inserting new values into short_groups table.'), true);

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
                'updated' => Carbon::now(),
            ]
        );
        ColorCLI::doEcho(ColorCLI::primary('Updated '.$newgroup['group']), true);
    }
}
ColorCLI::doEcho(ColorCLI::header('Running time: '.$consoleTools->convertTimer(time() - $start)), true);

function myInArray($array, $value, $key)
{
    //loop through the array
    foreach ($array as $val) {
        //if $val is an array cal myInArray again with $val as array input
        if (is_array($val)) {
            if (myInArray($val, $value, $key)) {
                return true;
            }
        } else {
            //else check if the given key has $value as value
            if ($array[$key] === $value) {
                return true;
            }
        }
    }

    return false;
}

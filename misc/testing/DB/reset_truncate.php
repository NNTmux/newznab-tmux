<?php

require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use nntmux\ColorCLI;
use App\Models\Group;
use App\Models\Release;
use Illuminate\Support\Facades\DB;

if (isset($argv[1]) && ($argv[1] === 'true' || $argv[1] === 'drop')) {
    Group::query()->update(['first_record' => 0, 'first_record_postdate' => null, 'last_record' => 0, 'last_record_postdate' => null, 'last_updated' => null]);
    echo ColorCLI::primary('Reseting all groups completed.');
    DB::unprepared('SET FOREIGN_KEY_CHECKS = 0;');
    DB::commit();

    $arr = ['parts', 'missed_parts', 'binaries', 'collections', 'multigroup_parts', 'multigroup_missed_parts', 'multigroup_binaries', 'multigroup_collections'];

    foreach ($arr as &$value) {
        $rel = DB::unprepared("TRUNCATE TABLE $value");
        DB::commit();
        if ($rel === true) {
            echo ColorCLI::primary("Truncating $value completed.");
        }
    }
    unset($value);

    $tables = DB::select('SHOW table status');

    foreach ($tables as $row) {
        $tbl = $row->Name;
        if (preg_match('/collections_\d+/', $tbl) || preg_match('/binaries_\d+/', $tbl) || preg_match('/parts_\d+/', $tbl) || preg_match('/missed_parts_\d+/', $tbl) || preg_match('/\d+_collections/', $tbl) || preg_match('/\d+_binaries/', $tbl) || preg_match('/\d+_parts/', $tbl) || preg_match('/\d+_missed_parts_\d+/', $tbl)) {
            if ($argv[1] === 'drop') {
                $rel = DB::unprepared("DROP TABLE $tbl");
                DB::commit();
                if ($rel === true) {
                    echo ColorCLI::primary("Dropping $tbl completed.");
                }
            } else {
                $rel = DB::unprepared("TRUNCATE TABLE $tbl");
                DB::commit();
                if ($rel === true) {
                    echo ColorCLI::primary("Truncating $tbl completed.");
                }
            }
        }
    }

    $delcount = Release::query()->where('nzbstatus', '=', 0)->delete();
    echo ColorCLI::primary($delcount.' releases had no nzb, deleted.');
    DB::unprepared('SET FOREIGN_KEY_CHECKS = 1;');
    DB::commit();
} else {
    ColorCLI::doEcho(ColorCLI::error(
        'This script removes releases with no NZBs, resets all groups, truncates or drops(tpg)
        article tables. All other releases are left alone.
        php '.$argv[0].' [true, drop]   ...: To reset all groups and truncate/drop the tables.'
    ), true
    );
    exit;
}

<?php
/* This script is designed to gather all show data from anidb and add it to the anidb table for nntmux, as part of this process we need the number of PI queries that can be executed max and whether or not we want debuging the first argument if unset will try to do the entire list (a good way to get banned), the second option can be blank or true for debugging.
* IF you are using this script then then you also want to edit anidb.php in www/lib and locate "604800" and replace it with 1204400, this will make sure it never tries to connect to anidb as this will fail
*/
require_once dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use Blacklight\ColorCLI;
use Blacklight\db\populate\AniDB;

if ($argc > 1 && $argv[1] === 'true' && isset($argv[2])) {
    if ($argv[2] === 'full') {
        (new AniDB(['Echo' => true]))->populateTable('full');
    } elseif ($argv[2] === 'info') {
        if ($argv[3] !== null && is_numeric($argv[3])) {
            (new AniDB(['Echo' => true]))->populateTable('info', $argv[3]);
        } else {
            (new AniDB(['Echo' => true]))->populateTable('info');
        }
    }
} else {
    (new ColorCLI())->error(
        'To execute this script you must provide a boolean argument.'.PHP_EOL.
        'Argument1: true|false to run this script or not'.PHP_EOL.
        'Argument2: full|info for what type of data to populate.'.PHP_EOL.
        'Argument3 (optional) anidbid to fetch info for'.PHP_EOL.
        'WARNING: Argument "info" without third argument will get you banned from AniDB almost instantly'
    );
}

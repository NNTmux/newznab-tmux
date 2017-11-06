<?php

require_once dirname(__DIR__, 4).DIRECTORY_SEPARATOR.'bootstrap.php';

use nntmux\NNTP;
use nntmux\db\DB;
use nntmux\ColorCLI;
use nntmux\ConsoleTools;

$start = time();
$pdo = new DB();
$consoleTools = new ConsoleTools(['ColorCLI' => $pdo->log]);

// Create the connection here and pass
$nntp = new NNTP(['Settings' => $pdo]);
if ($nntp->doConnect() !== true) {
    exit(ColorCLI::error('Unable to connect to usenet.'));
}

echo ColorCLI::header('Getting first/last for all your active groups.');
$data = $nntp->getGroups();
if ($nntp->isError($data)) {
    exit(ColorCLI::error('Failed to getGroups() from nntp server.'));
}

echo ColorCLI::header('Inserting new values into short_groups table.');

$pdo->queryExec('TRUNCATE TABLE short_groups');

// Put into an array all active groups
$res = $pdo->query('SELECT name FROM groups WHERE active = 1 OR backfill = 1');

foreach ($data as $newgroup) {
    if (myInArray($res, $newgroup['group'], 'name')) {
        $pdo->queryInsert(sprintf('INSERT INTO short_groups (name, first_record, last_record, updated) VALUES (%s, %s, %s, NOW())', $pdo->escapeString($newgroup['group']), $pdo->escapeString($newgroup['first']), $pdo->escapeString($newgroup['last'])));
        echo ColorCLI::primary('Updated '.$newgroup['group']);
    }
}
echo ColorCLI::header('Running time: '.$consoleTools->convertTimer(time() - $start));

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
            if ($array[$key] == $value) {
                return true;
            }
        }
    }

    return false;
}

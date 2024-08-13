<?php

require_once dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use Blacklight\Backfill;
use Blacklight\NNTP;

// Create the connection here and pass
$nntp = new NNTP;

if (isset($argv[1]) && $argv[1] === 'all' && ! isset($argv[2])) {
    $backfill = new Backfill(['NNTP' => $nntp]);
    $backfill->backfillAllGroups();
} elseif (isset($argv[1]) && ! isset($argv[2]) && preg_match('/^alt\.binaries\..+$/i', $argv[1])) {
    $backfill = new Backfill(['NNTP' => $nntp]);
    $backfill->backfillAllGroups($argv[1]);
} elseif (isset($argv[1], $argv[2]) && is_numeric($argv[2]) && preg_match('/^alt\.binaries\..+$/i', $argv[1])) {
    $backfill = new Backfill(['NNTP' => $nntp]);
    $backfill->backfillAllGroups($argv[1], $argv[2]);
} elseif (isset($argv[1], $argv[2]) && $argv[1] === 'alph' && is_numeric($argv[2])) {
    $backfill = new Backfill(['NNTP' => $nntp]);
    $backfill->backfillAllGroups('', $argv[2], 'normal');
} elseif (isset($argv[1], $argv[2]) && $argv[1] === 'date' && is_numeric($argv[2])) {
    $backfill = new Backfill(['NNTP' => $nntp]);
    $backfill->backfillAllGroups('', $argv[2], 'date');
} elseif (isset($argv[1], $argv[2]) && $argv[1] === 'safe' && is_numeric($argv[2])) {
    $backfill = new Backfill(['NNTP' => $nntp]);
    $backfill->safeBackfill($argv[2]);
} else {
    exit((new Blacklight\ColorCLI)->error('Wrong set of arguments.'
            .'php backfill.php safe 200000		 ...: Backfill an active group alphabetically, x articles, the script stops,'
            .'					 ...: if the group has reached reached 2012-06-24, the next group will backfill.'
            .'php backfill.php alph 200000 		 ...: Backfills all groups (sorted alphabetically) by number of articles'
            .'php backfill.php date 200000 		 ...: Backfills all groups (sorted by least backfilled in time) by number of articles'
            .'php backfill.php alt.binaries.ath 200000 ...: Backfills a group by name by number of articles'
            .'php backfill.php all			 ...: Backfills all groups 1 at a time, by date (set in admin-view groups)'
            .'php backfill.php alt.binaries.ath	 ...: Backfills a group by name, by date (set in admin-view groups)'));
}

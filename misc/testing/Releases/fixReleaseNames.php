<?php
/*
 * This script attempts to clean release names using the NFO, file name and release name, Par2 file.
 * A good way to use this script is to use it in this order: php fixReleaseNames.php 3 true other yes
 * php fixReleaseNames.php 5 true other yes
 * If you used the 4th argument yes, but you want to reset the status,
 * there is another script called resetRelnameStatus.php
 */

require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap.php';

use nntmux\NNTP;
use nntmux\db\DB;
use nntmux\PreDb;
use nntmux\ColorCLI;
use nntmux\NameFixer;
use App\Models\Settings;

$pdo = new DB();
$namefixer = new NameFixer(['Settings' => $pdo]);
$predb = new PreDb(['Echo' => true, 'Settings' => $pdo]);

if (isset($argv[1], $argv[2], $argv[3], $argv[4])) {
    $update = $argv[2] === 'true' ? true : false;
    $other = 1;
    if ($argv[3] === 'all') {
        $other = 2;
    } elseif ($argv[3] === 'predb_id') {
        $other = 3;
    }
    $setStatus = $argv[4] === 'yes' ? 1 : 2;

    $show = isset($argv[5]) && $argv[5] === 'show' ? 1 : 2;

    $nntp = null;
    if ($argv[1] === 7 || $argv[1] === 8) {
        $nntp = new NNTP(['Settings' => $pdo]);
        if ((Settings::settingValue('..alternate_nntp') === 1 ? $nntp->doConnect(true, true) : $nntp->doConnect()) !== true) {
            echo ColorCLI::error('Unable to connect to usenet.'.PHP_EOL);

            return;
        }
    }

    switch ($argv[1]) {
		case 1:
			$predb->parseTitles(1, $update, $other, $setStatus, $show);
			break;
		case 2:
			$predb->parseTitles(2, $update, $other, $setStatus, $show);
			break;
		case 3:
			$namefixer->fixNamesWithNfo(1, $update, $other, $setStatus, $show);
			break;
		case 4:
			$namefixer->fixNamesWithNfo(2, $update, $other, $setStatus, $show);
			break;
		case 5:
			$namefixer->fixNamesWithFiles(1, $update, $other, $setStatus, $show);
			break;
		case 6:
			$namefixer->fixNamesWithFiles(2, $update, $other, $setStatus, $show);
			break;
		case 7:
			$namefixer->fixNamesWithPar2(1, $update, $other, $setStatus, $show, $nntp);
			break;
		case 8:
			$namefixer->fixNamesWithPar2(2, $update, $other, $setStatus, $show, $nntp);
			break;
		case 9:
			$namefixer->fixNamesWithMedia(1, $update, $other, $setStatus, $show);
			break;
		case 10:
			$namefixer->fixNamesWithMedia(2, $update, $other, $setStatus, $show);
			break;
		case 11:
			$namefixer->fixXXXNamesWithFiles(1, $update, $other, $setStatus, $show);
			break;
		case 12:
			$namefixer->fixXXXNamesWithFiles(2, $update, $other, $setStatus, $show);
			break;
		case 13:
			$namefixer->fixNamesWithSrr(1, $update, $other, $setStatus, $show);
			break;
		case 14:
			$namefixer->fixNamesWithSrr(2, $update, $other, $setStatus, $show);
			break;
		case 15:
			$namefixer->fixNamesWithParHash(1, $update, $other, $setStatus, $show);
			break;
		case 16:
			$namefixer->fixNamesWithParHash(2, $update, $other, $setStatus, $show);
			break;
		default:
			exit(ColorCLI::error(PHP_EOL.'ERROR: Wrong argument, type php $argv[0] to see a list of valid arguments.'.PHP_EOL));
			break;
	}
} else {
    exit(ColorCLI::error(PHP_EOL.'You must supply 4 arguments.'.PHP_EOL
			.'The 2nd argument, false, will display the results, but not change the name, type true to have the names changed.'.PHP_EOL
			.'The 3rd argument, other, will only do against other categories, to do against all categories use all, or predb_id to process all not matched to predb.'.PHP_EOL
			.'The 4th argument, yes, will set the release as checked, so the next time you run it will not be processed, to not set as checked type no.'.PHP_EOL
			.'The 5th argument (optional), show, will display the release changes or only show a counter.\n'.PHP_EOL
			.'php '.$argv[0].' 1 false other no ...: Fix release names using the usenet subject in the past 3 hours with predb information.'.PHP_EOL
			.'php '.$argv[0].' 2 false other no ...: Fix release names using the usenet subject with predb information.'.PHP_EOL
			.'php '.$argv[0].' 3 false other no ...: Fix release names using NFO in the past 6 hours.'.PHP_EOL
			.'php '.$argv[0].' 4 false other no ...: Fix release names using NFO.'.PHP_EOL
			.'php '.$argv[0].' 5 false other no ...: Fix release names in misc categories using File Name in the past 6 hours.'.PHP_EOL
			.'php '.$argv[0].' 6 false other no ...: Fix release names in misc categories using File Name.'.PHP_EOL
			.'php '.$argv[0].' 7 false other no ...: Fix release names in misc categories using Par2 Files in the past 6 hours.'.PHP_EOL
			.'php '.$argv[0].' 8 false other no ...: Fix release names in misc categories using Par2 Files.'.PHP_EOL
			.'php '.$argv[0].' 9 false other no ...: Fix release names in misc categories using UID in the past 6 hours.'.PHP_EOL
			.'php '.$argv[0].' 10 false other no ...: Fix release names in misc categories using UID.'.PHP_EOL
			.'php '.$argv[0].' 11 false other no ...: Fix SDPORN XXX release names in misc categories using specific File Name in the past 6 hours.'.PHP_EOL
			.'php '.$argv[0].' 12 false other no ...: Fix SDPORN XXX release names in misc categories using specific File Name.'.PHP_EOL
			.'php '.$argv[0].' 13 false other no ...: Fix release names in misc categories using SRR files in the past 6 hours.'.PHP_EOL
			.'php '.$argv[0].' 14 false other no ...: Fix release names in misc categories using SRR files.'.PHP_EOL
			.'php '.$argv[0].' 15 false other no ...: Fix release names in misc categories using PAR2 hash_16K block in the past 6 hours.'.PHP_EOL
			.'php '.$argv[0].' 16 false other no ...: Fix release names in misc categories using PAR2 hash_16K block.'.PHP_EOL));
}

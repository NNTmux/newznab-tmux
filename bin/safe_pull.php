<?php

require_once(dirname(__FILE__).'/config.php');
require_once(WWW_DIR.'/lib/nntp.php');
require_once(WWW_DIR . "/lib/ColorCLI.php");
require_once(WWW_DIR . "/lib/ConsoleTools.php");
require_once(dirname(__FILE__) . '/../lib/functions.php');

$c = new ColorCLI();

if (!isset($argv[1])) {
	exit($c->error("This script is not intended to be run manually, it is called from safe threaded scripts."));
} else if (isset($argv[1])) {
	// Create the connection here and pass
	$nntp = new NNTP();
	if ($nntp->doConnect() !== true) {
		exit($c->error("Unable to connect to usenet."));
	}

	$pieces = explode(' ', $argv[1]);
	if (isset($pieces[1]) && $pieces[1] == 'partrepair') {
		$binaries = new Binaries();
        $functions = new Functions();
		$groupName = $pieces[0];
		$grp = new Groups();
		$groupArr = $grp->getByName($groupName);
		// Select group, here, only once
		$data = $nntp->selectGroup($groupArr['name']);
		if ($nntp->isError($data)) {
			$data = $nntp->dataError($nntp, $groupArr['name']);
			if ($data === false) {
				return;
			}
		}
		$functions->partRepair($nntp, $groupArr);
	} else if (isset($pieces[1]) && $pieces[0] == 'binupdate') {
		$binaries = new Binaries();
        $functions = new Functions();
		$groupName = $pieces[1];
		$grp = new Groups();
		$groupArr = $grp->getByName($groupName);
		$binaries->updateGroup($nntp, $groupArr);
	} else if (isset($pieces[2]) && ($pieces[2] == 'Binary' || $pieces[2] == 'Backfill')) {
		$functions = new Functions();
		$functions->getFinal($pieces[0], $pieces[1], $pieces[2], $nntp);
	} else if (isset($pieces[2]) && $pieces[2] == 'BackfillAll') {
		$functions = new Functions();
		$functions->backfillAllGroups($nntp, $pieces[0], $pieces[1]);
	} else if (isset($pieces[3])) {
		$functions = new Functions();
		$functions->getRange($pieces[0], $pieces[1], $pieces[2], $pieces[3], $nntp);
	} else if (isset($pieces[1])) {
		$functions = new Functions();
		$functions->backfillAllGroups($nntp, $pieces[0], $pieces[1]);
	}
		$nntp->doQuit();
}

<?php

require_once(dirname(__FILE__).'/config.php');
require_once(WWW_DIR.'/lib/nntp.php');
require_once(dirname(__FILE__).'/../lib/ColorCLI.php');
require_once(dirname(__FILE__) . '/../lib/functions.php');

$c = new ColorCLI();
if (!isset($argv[1])) {
	exit($c->error("This script is not intended to be run manually, it is called from backfill_threaded.py."));
}

// Create the connection here and pass
$nntp = new NNTP();
if ($nntp->doConnect() !== true) {
	exit($c->error("Unable to connect to usenet."));
}

$pieces = explode(' ', $argv[1]);
$functions = new Functions();
$functions->backfillPostAllGroups($nntp, $pieces[0], 10000, 'normal');
$nntp->doQuit();

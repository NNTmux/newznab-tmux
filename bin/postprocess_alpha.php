<?php
require_once(dirname(__FILE__).'/config.php');
require_once(WWW_DIR.'/lib/postprocess.php');
require_once(WWW_DIR.'/lib/nntp.php');
require_once(WWW_DIR.'/lib/site.php');
require_once(WWW_DIR.'/lib/anidb.php');
require_once(WWW_DIR.'/lib/tvrage.php');
require_once(WWW_DIR.'/lib/thetvdb.php');
require_once(WWW_DIR.'/lib/nfo.php');
require_once(WWW_DIR.'/lib/Tmux.php');
require_once(dirname(__FILE__).'/../test/ColorCLI.php');
require_once(dirname(__FILE__).'/../test/functions.php');

$c = new ColorCLI();
if (!isset($argv[1])) {
	exit($c->error("This script is not intended to be run manually, it is called from postprocess_threaded.py."));
}


$tmux = new Tmux;
$torun = $tmux->get()->post;

$pieces = explode('           =+=            ', $argv[1]);

$functions = new Functions(true);
if (isset($pieces[6])) {
	// Create the connection here and pass
	$nntp = new NNTP();
	if  ($nntp->doConnect() === false) {
		exit($c->error("Unable to connect to usenet."));
	}

	$functions->processAdditionalThreaded($argv[1], $nntp);
		$nntp->doQuit();
} else if (isset($pieces[3])) {
	// Create the connection here and pass
	$nntp = new NNTP();
	if ($nntp->doConnect() === false) {
		exit($c->error("Unable to connect to usenet."));
	}

	$functions->processNfos($argv[1], $nntp);
		$nntp->doQuit();

} else if (isset($pieces[2])) {
	$functions->processMovies($argv[1]);
	echo '.';
} else if (isset($pieces[1])) {
	$functions->processTv($argv[1]);
}

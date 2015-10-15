<?php
require_once dirname(__FILE__) . '/../../../config.php';

use newznab\processing\PostProcess;
use newznab\controllers\ColorCLI;
use newznab\controllers\Tmux;
use newznab\controllers\NNTP;


$c = new ColorCLI();
if (!isset($argv[1])) {
	exit($c->error("This script is not intended to be run manually, it is called from postprocess_threaded.py."));
}


$tmux = new Tmux;
$torun = $tmux->get()->post;

$pieces = explode('           =+=            ', $argv[1]);

$postprocess = new PostProcess(['Echo' => true]);
if (isset($pieces[6])) {
	// Create the connection here and pass
	$nntp = new NNTP();
	if  ($nntp->doConnect() === false) {
		exit($c->error("Unable to connect to usenet."));
	}

	$postprocess->processAdditional($nntp, $argv[1]);
	$nntp->doQuit();
} else if (isset($pieces[3])) {
	// Create the connection here and pass
	$nntp = new NNTP();
	if ($nntp->doConnect() === false) {
		exit($c->error("Unable to connect to usenet."));
	}

	$postprocess->processNfos($argv[1], $nntp);
	$nntp->doQuit();

} else if (isset($pieces[2])) {
	$postprocess->processMovies($argv[1]);
	echo '.';
} else if (isset($pieces[1])) {
	$postprocess->processTv($argv[1]);
}
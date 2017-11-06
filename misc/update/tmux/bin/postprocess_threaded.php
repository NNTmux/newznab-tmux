<?php

require_once dirname(__DIR__, 4).DIRECTORY_SEPARATOR.'bootstrap.php';

use nntmux\NNTP;
use nntmux\Tmux;
use nntmux\ColorCLI;
use nntmux\processing\PostProcess;

$c = new ColorCLI();
if (! isset($argv[1])) {
    exit($c->error('This script is not intended to be run manually, it is called from postprocess.php.'));
}

$tmux = new Tmux;
$torun = $tmux->get()->post;

$pieces = explode('           =+=            ', $argv[1]);

$postprocess = new PostProcess(['Echo' => true]);
if (isset($pieces[6])) {
    // Create the connection here and pass
    $nntp = new NNTP();
    if ($nntp->doConnect() === false) {
        exit($c->error('Unable to connect to usenet.'));
    }

    $postprocess->processAdditional($nntp, $argv[1]);
    $nntp->doQuit();
} elseif (isset($pieces[3])) {
    // Create the connection here and pass
    $nntp = new NNTP();
    if ($nntp->doConnect() === false) {
        exit($c->error('Unable to connect to usenet.'));
    }

    $postprocess->processNfos($argv[1], $nntp);
    $nntp->doQuit();
} elseif (isset($pieces[2])) {
    $postprocess->processMovies($argv[1]);
    echo '.';
} elseif (isset($pieces[1])) {
    $postprocess->processTv($argv[1]);
}

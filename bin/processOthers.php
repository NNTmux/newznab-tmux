<?php

require_once("config.php");
require_once(WWW_DIR."/lib/postprocess.php");

$postprocess = new PostProcess(true);
$postprocess->processUnwanted();

$postprocess = new PostProcess(true);
$postprocess->processMusicFromMediaInfo();

$postprocess = new PostProcess(true);
$postprocess->processOtherMiscCategory();

$postprocess = new PostProcess(true);
$postprocess->processUnknownCategory();

?>

<?php

require(dirname(__FILE__)."/config.php");
require(WWW_DIR.'/lib/postprocess.php');

$postprocess = new PostProcess(true);
$postprocess->processUnwanted();
$postprocess->processMusicFromMediaInfo();
$postprocess->processOtherMiscCategory();
$postprocess->processUnknownCategory();

?>

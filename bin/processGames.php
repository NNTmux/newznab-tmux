<?php

require('config.php');
require(WWW_DIR.'/lib/postprocess.php');

$postprocess = new PostProcess(true);
$postprocess->processGames();

?>


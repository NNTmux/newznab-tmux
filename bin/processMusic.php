<?php

require_once("config.php");
require_once(WWW_DIR."/lib/postprocess.php");

$postprocess = new PostProcess(true);
$postprocess->processMusic();

?>


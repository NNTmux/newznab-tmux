<?php

require(dirname(__FILE__)."/config.php");
require(WWW_DIR.'/lib/postprocess.php');

$postprocess = new PostProcess(true);
$postprocess->processBooks();

?>


<?php

$newzpath = getenv('NEWZPATH');
require_once("$newzpath/www/config.php");
require_once("lib/postprocess2.php");

$postprocess = new PostProcess2(true);
$postprocess->processAdditional2();

?>



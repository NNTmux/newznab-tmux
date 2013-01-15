<?php

$newzpath = getenv('NEWZPATH');
require_once("$newzpath/www/config.php");
require_once("lib/postprocess3.php");

$postprocess = new PostProcess3(true);
$postprocess->processAdditional3();

?>

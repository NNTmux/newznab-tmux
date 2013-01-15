<?php

$newzpath = getenv('NEWZPATH');
require_once("$newzpath/www/config.php");
require_once("lib/postprocess4.php");

$postprocess = new PostProcess4(true);
$postprocess->processAdditional4();

?>



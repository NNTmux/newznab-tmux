<?php

require(dirname(__FILE__)."/config.php");
require(dirname(__FILE__)."/temp/postprocess2.php");

$postprocess = new PostProcess2(true);
$postprocess->processBooks1();

?>


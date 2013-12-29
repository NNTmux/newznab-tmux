<?php

require(dirname(__FILE__)."/config.php");
require(dirname(__FILE__)."/temp/postprocess1.php");

$postprocess = new PostProcess1(true);
$postprocess->processNfos1();

?>


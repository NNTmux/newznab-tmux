<?php

require(dirname(__FILE__)."/config.php");
require('lib/postprocess1.php');

$postprocess = new PostProcess1(true);
$postprocess->processMusic1();

?>

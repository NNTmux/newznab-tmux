<?php

require(dirname(__FILE__)."/config.php");
require('lib/postprocess2.php');

$postprocess = new PostProcess2(true);
$postprocess->processBooks1();

?>


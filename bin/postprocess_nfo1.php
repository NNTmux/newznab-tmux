<?php

require('config.php');
require('lib/postprocess2.php');

$postprocess = new PostProcess2(true);
$postprocess->processNfos1();

?>


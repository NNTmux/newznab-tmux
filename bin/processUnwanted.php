<?php

require(dirname(__FILE__)."/config.php");
require(WWW_DIR.'/lib/postprocess.php');

$i = 1;
while ( $i > 0 )
{
  $postprocess = new PostProcess(true);
  $postprocess->processUnwanted();
}
?>


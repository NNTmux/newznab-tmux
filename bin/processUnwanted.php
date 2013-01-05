<?php

require_once("config.php");
require_once(WWW_DIR."/lib/postprocess.php");

$i=1;
while($i=1)
{
  {
    $postprocess = new PostProcess(true);
    $postprocess->processUnwanted();
  }
}

?>

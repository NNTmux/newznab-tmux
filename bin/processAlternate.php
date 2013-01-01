<?php

require("config.php");
require_once(WWW_DIR."/lib/postprocess.php");
require("pid.php");

$filename = pathinfo(__FILE__, PATHINFO_FILENAME);

$pidfile = new pidfile("/tmp", $filename);
  if($pidfile->is_already_running()) {
    echo "Already running.\n";
    exit;
  }

$i=1;
while($i=1)

{
	$postprocess = new PostProcess(true);
	$postprocess->processAdditional();
}

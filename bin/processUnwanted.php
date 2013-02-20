<?php

require(dirname(__FILE__)."/config.php");
require(WWW_DIR.'/lib/postprocess.php');

//get variables from config.sh and defaults.sh
$varnames = shell_exec("cat ../config.sh | grep ^export | cut -d \= -f1 | awk '{print $2;}'");
$varnames .= shell_exec("cat ../defaults.sh | grep ^export | cut -d \= -f1 | awk '{print $2;}'");
$vardata = shell_exec('cat ../config.sh | grep ^export | cut -d \" -f2 | awk "{print $1;}"');
$vardata .= shell_exec('cat ../defaults.sh | grep ^export | cut -d \" -f2 | awk "{print $1;}"');
$varnames = explode("\n", $varnames);
$vardata = explode("\n", $vardata);
$array = array_combine($varnames, $vardata);
unset($array['']);

if ( $array['CONTINUOUS'] == "true" ) {
    $i = 1;
    while ( $i > 0 )
    {
      $postprocess = new PostProcess(true);
      $postprocess->processUnwanted();
    }
} else {
  $postprocess = new PostProcess(true);
  $postprocess->processUnwanted();
}
?>


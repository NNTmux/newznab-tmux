<?php

require("config.php");
require_once(WWW_DIR."/lib/postprocess.php");

$db = new DB();
$query = "select count(*) from releases";

$result_begin = mysql_query($query);

if (!$result_begin) {
  $message  = 'Invalid query: ' . mysql_error() . "\n";
  $message .= 'Whole query: ' . $query;
  die($message);
}

while ($row = mysql_fetch_assoc($result_begin)) {
  $count_begin = $row['count(*)'];
}

$i=1;
while($i=1)
{
  $result_inner_loop = mysql_query($query);
  sleep(60);
  $result_loop = mysql_query($query);

  if (!$result_inner_loop) {
    $message  = 'Invalid query: ' . mysql_error() . "\n";
    $message .= 'Whole query: ' . $query;
    die($message);
  }

  while ($row = mysql_fetch_assoc($result_inner_loop)) {
    $count_inner_loop = $row['count(*)'];
  }

  if (!$result_loop) {
    $message  = 'Invalid query: ' . mysql_error() . "\n";
    $message .= 'Whole query: ' . $query;
    die($message);
  }

  while ($row = mysql_fetch_assoc($result_loop)) {
    $count_loop = $row['count(*)'];
  }

  $total_start = $count_loop - $count_begin;
  $total_loop = $count_loop - $count_inner_loop;
  passthru('clear');
  printf("Monitoring total releases in your database\n");
  printf("$total_loop releases added in last 60 seconds\n");
  printf("$total_start releases added since started\n");
  printf("$count_loop releases currently in your database\n\n");
  printf("***Info***\n");
  printf("Above left shows the current NFO's being analyzed. Above middle shows the the processing for. Above right show the lookups for TVRage, music, etc. Below left shows the activity of imports - it is looping nzb import, backfill and current fill. Below right shows the activity of applying regex's and creating releases.\n");
}

mysql_free_result($result_begin);
mysql_free_result($result_loop);

?>


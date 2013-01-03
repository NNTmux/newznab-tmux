<?php

require("config.php");
require_once(WWW_DIR."/lib/postprocess.php");
$_php = getenv("PHP");

$db = new DB();
$query = "select count(*) from releases";
//$query2 = "select format(count(*),0) from releases r left join category c on c.ID = r.categoryID where ((r.passwordstatus between -6 and -1) or (r.haspreview = -1 and c.disablepreview = 0))";

//$result_begin = mysql_query($query);
$result_begin = $db->queryDirect($query);

if (!$result_begin) {
  $message  = 'Invalid query: ' . mysql_error() . "\n";
  $message .= 'Whole query: ' . $query;
  die($message);
}

while ($row = mysql_fetch_assoc($result_begin)) {
  $count_begin = $row['count(*)'];
}

$time = TIME();

$i=1;
while($i=1)
{
  //$result_inner_loop = mysql_query($query);
  $result_inner_loop = $db->queryDirect($query);

  sleep(60);
  //$result_loop = mysql_query($query);
  $result_loop = $db->queryDirect($query);

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

  $secs = TIME() - $time;
  $mins = floor($secs / 60);
  $hrs = floor($mins / 60);
  $days = floor($hrs / 24);
  $sec = floor($secs % 60);
  $min = ($mins % 60);
  $hr = ($hrs % 60);
  $day = ($days % 24);
  $total_start = $count_loop - $count_begin;
  $total_loop = $count_loop - $count_inner_loop;
  passthru('clear');
  printf("Monitoring total releases in your database\n");
  printf("$total_loop releases added in last minute\n");
  printf("$total_start releases added in the last \033[38;5;160m$day\033[0m");printf(" Days ");
  printf("\033[38;5;208m$hr\033[0m");printf(" Hours ");
  printf("\033[38;5;020m$min\033[0m");printf(" Minutes ");
  printf("\033[38;5;063m$sec\033[0m");printf(" Seconds\n");
  printf("$count_loop releases currently in your database\n\n");
  printf("***Info***\n");
  printf("Above left shows the current NFO's being analyzed. Above middle shows the the processing for. Above right show the lookups for TVRage, music, etc. Below left shows the activity of imports - it is looping nzb import, backfill and current fill. Below right shows the activity of applying regex's and creating releases.\n");


 //check if scripts are running
  exec("pgrep -f postprocess_nfo", $pids1);
  if (empty($pids1)) {
    echo "postprocess_nfo.php is not running, restarting\n";
    exec("tmux send-keys -t NewzNab:0.0 'clear && cd bin && $_php postprocess_nfo.php' C-m");
  }
  exec("pgrep -f processAlternate", $pids2);
  if (empty($pids2)) {
    echo "processAlternate.php is not running, restarting\n";
    exec("tmux send-keys -t NewzNab:0.1 'clear && cd bin && $_php processAlternate.php' C-m");
  }
  exec("pgrep -f postprocessing", $pids3);
  if (empty($pids3)) {
    echo "postprocessing.php is not running, restarting\n";
    exec("tmux send-keys -t NewzNab:0.2 'clear && cd bin && $_php postprocessing.php' C-m");
  }
}

mysql_free_result($result_begin);
mysql_free_result($result_loop);

?>


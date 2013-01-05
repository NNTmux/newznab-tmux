<?php

require("config.php");
require_once(WWW_DIR."/lib/postprocess.php");
$_php = getenv("PHP");

$db = new DB();
 //////////////amount of releases//////////////
$query = "select count(*) from releases";
 ///////////amount of post processing left/////
$pquery = "select count(*) from releases r left join category c on c.ID = r.categoryID where (r.passwordstatus between -6 and -1) or (r.haspreview = -1 and c.disablepreview = 0);";
 /////////////amount of post processing to do//
$plquery = "select count(*) from releases r left join category c on c.ID = r.categoryID where (r.passwordstatus=0);";

$result_begin = mysql_query($query);
//$result_begin = $db->queryDirect($query);
$presult_begin = mysql_query($pquery);
$plresult_begin = mysql_query($plquery);

 /////////////////result_begin////////////////////////
if (empty($result_begin)) {
  $result_begin = $db->queryDirect($query);
  if (empty($result_begin)) {
    $message = 'Invalid query: ' . mysql_error() . "\n";
    $message .= 'Whole query: ' . $query;
    die($message);
  }
}

while ($row = mysql_fetch_assoc($result_begin)) {
  $count_begin = $row['count(*)'];
}

 ///////////////////presult_begin///////////////////////
if (empty($presult_begin)) {
  $presult_begin = $db->queryDirect($pquery);
  if (empty($presult_begin)) {
    $pmessage = 'Invalid query: ' . mysql_error() . "\n";
    $pmessage .= 'Whole query: ' . $pquery;
    die($pmessage);
  }
}

while ($prow = mysql_fetch_assoc($presult_begin)) {
  $pcount_begin = $prow['count(*)'];
}

 ///////////////////plresult_begin///////////////////////
if (empty($plresult_begin)) {
  $plresult_begin = $db->queryDirect($plquery);
  if (empty($plresult_begin)) {
    $plmessage = 'Invalid query: ' . mysql_error() . "\n";
    $plmessage .= 'Whole query: ' . $plquery;
    die($plmessage);
  }
}

while ($plrow = mysql_fetch_assoc($plresult_begin)) {
  $plcount_begin = $plrow['count(*)'];
}

 ////////////////////////time////////////////////////////
$time = TIME();

$i=1;
while($i=1)
{
  $result_inner_loop = mysql_query($query);
  $presult_inner_loop = mysql_query($pquery);
  $plresult_inner_loop = mysql_query($plquery);
  //$result_inner_loop = $db->queryDirect($query);

 ////////////////////////////////////////////////////////
 //////////////////Change these for sleep time///////////
  $sleeptime = "15";
  $sleeptext = "in the past $sleeptime seconds";
 ////////////////////////////////////////////////////////
 ////////////////////////print///////////////////////////

  sleep($sleeptime);
  $result_loop = mysql_query($query);
  $presult_loop = mysql_query($pquery);
  $plresult_loop = mysql_query($plquery);
  //$result_loop = $db->queryDirect($query);

 ////////////////result_inner_loop///////////////////////
  if (empty($result_inner_loop)) {
    $result_inner_loop = $db->queryDirect($query);
    if (empty($result_inner_loop)) {
      $message = 'Invalid query: ' . mysql_error() . "\n";
      $message .= 'Whole query: ' . $query;
      die($message);
    }
 }

  while ($row = mysql_fetch_assoc($result_inner_loop)) {
    $count_inner_loop = $row['count(*)'];
  }

 ///////////////////result_loop/////////////////////////
  if (empty($result_loop)) {
    $result_loop = $db->queryDirect($query);
    if (empty($result_loop)) {
      $message = 'Invalid query: ' . mysql_error() . "\n";
      $message .= 'Whole query: ' . $query;
      die($message);
    }
  }

  while ($row = mysql_fetch_assoc($result_loop)) {
    $count_loop = $row['count(*)'];
  }

 ////////////////presult_inner_loop///////////////////////
  if (empty($presult_inner_loop)) {
    $presult_inner_loop = $db->queryDirect($pquery);
    if (empty($presult_inner_loop)) {
      $pmessage = 'Invalid query: ' . mysql_error() . "\n";
      $pmessage .= 'Whole query: ' . $pquery;
      die($pmessage);
    }
 }

  while ($prow = mysql_fetch_assoc($presult_inner_loop)) {
    $pcount_inner_loop = $prow['count(*)'];
  }

 ///////////////////presult_loop////////////////////////
  if (empty($presult_loop)) {
    $presult_loop = $db->queryDirect($pquery);
    if (empty($result_loop)) {
      $pmessage = 'Invalid query: ' . mysql_error() . "\n";
      $pmessage .= 'Whole query: ' . $pquery;
      die($pmessage);
    }
  }

  while ($prow = mysql_fetch_assoc($presult_loop)) {
    $pcount_loop = $prow['count(*)'];
  }

 /////////////////////plresult_loop//////////////////////
  if (empty($pdresult_loop)) {
    $plresult_loop = $db->queryDirect($plquery);
    if (empty($plresult_loop)) {
      $plmessage = 'Invalid query: ' . mysql_error() . "\n";
      $plmessage .= 'Whole query: ' . $plquery;
      die($plmessage);
    }
  }

  while ($plrow = mysql_fetch_assoc($plresult_loop)) {
    $plcount_loop = $plrow['count(*)'];
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
  $ptotal_start = -$pcount_loop - -$pcount_begin;
  $ptotal_loop = -$pcount_loop - -$pcount_inner_loop;
  passthru('clear');
  printf("Monitoring total releases in your database\n");
  printf("$total_loop releases added $sleeptext\n");
  printf("$total_start releases added in the last \033[38;5;160m$day\033[0m");printf(" Days ");
  printf("\033[38;5;208m$hr\033[0m");printf(" Hours ");
  printf("\033[38;5;020m$min\033[0m");printf(" Minutes ");
  printf("\033[38;5;063m$sec\033[0m");printf(" Seconds\n");
  printf("$ptotal_loop releases post processed $sleeptext\n");
  printf("$ptotal_start releases post processed in the last \033[38;5;160m$day\033[0m");printf(" Days ");
  printf("\033[38;5;208m$hr\033[0m");printf(" Hours ");
  printf("\033[38;5;020m$min\033[0m");printf(" Minutes ");
  printf("\033[38;5;063m$sec\033[0m");printf(" Seconds\n");
  printf("$plcount_loop have been post processed thus far\n");
  printf("$pcount_loop releases left to post process\n");
  printf("$count_loop releases currently in your database\n\n");

}
mysql_free_result($result_begin);
mysql_free_result($result_loop);
mysql_free_result($pcount_begin);
mysql_free_result($presult_loop);
mysql_free_result($plcount_begin);
mysql_free_result($plresult_loop);

?>




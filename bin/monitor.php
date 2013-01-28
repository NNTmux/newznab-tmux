<?php

require('config.php');
require(WWW_DIR.'/lib/postprocess.php');

$db = new DB();

//totals per category in db, results by parentID
$qry="SELECT COUNT( releases.categoryID ) AS cnt, parentID FROM releases JOIN category ON releases.categoryID = category.ID GROUP BY parentID;";


//needs to be processed query
$_DB_NAME = getenv('DB_NAME');
$proc="SELECT ( SELECT COUNT( groupID ) AS cnt from releases where consoleinfoID IS NULL and categoryID BETWEEN 1000 AND 1999 ) AS console, ( SELECT COUNT( groupID ) AS cnt from releases where imdbID IS NULL and categoryID BETWEEN 2000 AND 2999 ) AS movies, ( SELECT COUNT( groupID ) AS cnt from releases where musicinfoID IS NULL and categoryID BETWEEN 3000 AND 3999 ) AS audio, ( SELECT COUNT( groupID ) AS cnt from releases r left join category c on c.ID = r.categoryID where (categoryID BETWEEN 4000 AND 4999 and ((r.passwordstatus between -6 and -1) or (r.haspreview = -1 and c.disablepreview = 0)))) AS pc, ( SELECT COUNT( groupID ) AS cnt from releases where rageID = -1 and categoryID BETWEEN 5000 AND 5999 ) AS tv, ( SELECT COUNT( groupID ) AS cnt from releases where bookinfoID IS NULL and categoryID = 7020 ) AS book, ( SELECT COUNT( groupID ) AS cnt from releases r left join category c on c.ID = r.categoryID where (r.passwordstatus between -6 and -1) or (r.haspreview = -1 and c.disablepreview = 0)) AS work, ( SELECT COUNT( groupID ) AS cnt from releases) AS releases, ( SELECT COUNT( groupID ) AS cnt FROM releases r WHERE r.releasenfoID = 0) AS nforemains, ( SELECT COUNT( groupID ) AS cnt FROM releases WHERE releasenfoID not in (0, -1)) AS nfo, ( SELECT table_rows AS cnt FROM information_schema.TABLES where table_name = 'parts' AND TABLE_SCHEMA = '".$_DB_NAME."' ) AS parts, ( SELECT concat(round((data_length+index_length)/(1024*1024*1024),2),'GB') AS cnt FROM information_schema.tables where table_name = 'parts' AND TABLE_SCHEMA = '".$_DB_NAME."' ) AS partsize;";

//initial query for total releases
$releases_query = "SELECT COUNT( groupID ) AS cnt from releases;";
$releases_start = $db->query($releases_query);
$releases_start = $releases_start[0]['cnt'];

//get variables from edit_these.sh
$varnames = shell_exec("cat ../edit_these.sh | grep ^export | cut -d \= -f1 | awk '{print $2;}'");
$vardata = shell_exec('cat ../edit_these.sh | grep ^export | cut -d \" -f2 | awk "{print $1;}"');
$varnames = explode("\n", $varnames);
$vardata = explode("\n", $vardata);
$array = array_combine($varnames, $vardata);
unset($array['']);

//environment
$backfill_increment = "UPDATE groups set backfill_target=backfill_target+1 where active=1 and backfill_target<{$array['MAXDAYS']};";
$_DB_USER = getenv('DB_USER');
$_DB_HOST = getenv('DB_HOST');
$_DB_PASSWORD = escapeshellarg(getenv('DB_PASSWORD'));
$_current_path = dirname(__FILE__);
$_mysql = getenv('MYSQL');
$_php = getenv('PHP');
$_tmux = getenv('TMUXCMD');

//got microtime
function microtime_float()
{
  list($usec, $sec) = explode(" ", microtime());
  return ((float)$usec + (float)$sec);
}

$_sleep_string = "\033[1;31msleeping\033[0m ";

$time = TIME();
$time2 = TIME();
$time3 = TIME();
$time4 = TIME();
$time5 = TIME();
$time6 = TIME();
$time7 = TIME();
$time8 = TIME();
$time9 = TIME();

$i=1;
while($i>0)
{
  //get microtime at start of loop
  $time_loop_start = microtime_float();

  //chack variables again during loop
  $varnames = shell_exec("cat ../edit_these.sh | grep ^export | cut -d \= -f1 | awk '{print $2;}'");
  $vardata = shell_exec('cat ../edit_these.sh | grep ^export | cut -d \" -f2 | awk "{print $1;}"');
  $varnames = explode("\n", $varnames);
  $vardata = explode("\n", $vardata);
  $array = array_combine($varnames, $vardata);
  unset($array['']);

  //if ($i!=1) {
    //sleep($array['MONITOR_UPDATE']);
    //$time_loop_start=$time_loop_start+$array['MONITOR_UPDATE'];
  //}

  //get microtime to at start of queries
  $query_timer_start=microtime_float();

  //run queries
  $result = $db->query($qry) or die ();
  $initquery = array();
  foreach ($result as $cat=>$sub)
  {
    $initquery[$sub['parentID']] = $sub['cnt'];
  }
  $proc_result = $db->query($proc) or die ();

  //get values from $qry
  $console_releases_now = $initquery['1000'];
  $movie_releases_now = $initquery['2000'];
  $music_releases_now = $initquery['3000'];
  $pc_releases_now = $initquery['4000'];
  $tvrage_releases_now = $initquery['5000'];
  $book_releases_now = $initquery['7000'];
  $misc_releases_now = $initquery['8000'];

  //get values from $proc
  $console_releases_proc = $proc_result[0]['console'];
  $movie_releases_proc = $proc_result[0]['movies'];
  $music_releases_proc = $proc_result[0]['audio'];
  $pc_releases_proc = $proc_result[0]['pc'];
  $tvrage_releases_proc = $proc_result[0]['tv'];
  $work_remaining_now = $proc_result[0]['work'];
  $book_releases_proc = $proc_result[0]['book'];
  $releases_loop = $proc_result[0]['releases'];
  $nfo_remaining_now = $proc_result[0]['nforemains'];
  $nfo_now = $proc_result[0]['nfo'];
  $parts_rows = $proc_result[0]['parts'];
  $parts_size_gb = $proc_result[0]['partsize'];
  $releases_now = $proc_result[0]['releases'];

  //calculate releases difference
  $releases_since_start = $releases_now - $releases_start;
  $total_work_now = $work_remaining_now + $tvrage_releases_proc + $music_releases_proc + $movie_releases_proc + $console_releases_proc + $book_releases_proc;

  //get microtime at end of queries
  $query_timer = microtime_float()-$query_timer_start;

  $secs = TIME() - $time;
  $mins = floor($secs / 60);
  $hrs = floor($mins / 60);
  $days = floor($hrs / 24);
  $sec = floor($secs % 60);
  $min = ($mins % 60);
  $day = ($days % 24);
  $hr = ($hrs % 24);

  if ( $releases_since_start > 0 ) { $signed = "+"; }
  else { $signed = ""; }

  if ( $min != 1 ) { $string_min = "mins"; }
  else { $string_min = "min"; }

  if ( $hr != 1 ) { $string_hr = "hrs"; }
  else { $string_hr = "hr"; }

  if ( $day != 1 ) { $string_day = "days"; }
  else { $string_day = "day"; }

  if ( $day > 0 ) { $time_string = "\033[38;5;160m$day\033[0m $string_day, \033[38;5;208m$hr\033[0m $string_hr, \033[1;31m$min\033[0m $string_min."; }
  elseif ( $hr > 0 ) { $time_string = "\033[38;5;208m$hr\033[0m $string_hr, \033[1;31m$min\033[0m $string_min."; }
  else { $time_string = "\033[1;31m$min\033[0m $string_min."; }

  passthru('clear');
  printf("\033[1;31mMonitor\033[0m has been running for: $time_string\n");
  printf("\033[1;31m$releases_now($signed$releases_since_start)\033[0m releases in your database.\n");
  printf("\033[1;31m$total_work_now\033[0m releases left to postprocess.");
  if ( $array['MAX_RELEASES'] != 0 ) { printf(" update_binaries, backfill and nzb-import will stop running when you exceed {$array['MAX_RELEASES']}\n\n\033[1;33m"); }
  else { printf("\n\n\033[1;33m"); }

  $mask = "%16s %10s %10s \n";
  printf($mask, "Category", "In Process", "In Database");
  printf($mask, "===============", "==========", "==========\033[0m");
  printf($mask, "NFO's","$nfo_remaining_now","$nfo_now");
  printf($mask, "Console(1000)","$console_releases_proc","$console_releases_now");
  printf($mask, "Movie(2000)","$movie_releases_proc","$movie_releases_now");
  printf($mask, "Audio(3000)","$music_releases_proc","$music_releases_now");
  printf($mask, "PC(4000)","$pc_releases_proc","$pc_releases_now");
  printf($mask, "TVShows(5000)","$tvrage_releases_proc","$tvrage_releases_now");
  printf($mask, "Books(7000)","$book_releases_proc","$book_releases_now");
  printf($mask, "Misc(8000)","$work_remaining_now","$misc_releases_now");

  $NNPATH="{$array['NEWZPATH']}{$array['NEWZNAB_PATH']}";
  $TESTING="{$array['NEWZPATH']}{$array['TESTING_PATH']}";

  $mask = "%16s %10.10s %10s \n";
  printf("\n\033[1;33m");
  printf($mask, "Category", "Time", "Status");
  printf($mask, "===============", "==========", "==========\033[0m");
  printf($mask, "Queries","$query_timer","queried");

  //get microtime for timing script check
  $script_timer_start = microtime_float();

  //run update_predb.php in 1.0 ever 15 minutes
  if ((TIME() - $time2) >= $array['PREDB_TIMER'] ) {
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.0 'echo \"\033[1;31m\" && cd $NNPATH && $_php update_predb.php true && date' 2>&1 1> /dev/null");
    $time2 = TIME();
  }

  //run $_php update_parsing.php in 1.1 every 1 hour
  if (((TIME() - $time3) >= $array['PARSING_TIMER'] ) && ($array['PARSING'] == "true" )) {
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.1 'echo \"\033[1;32m\" && cd $TESTING && $_php update_parsing.php && date' 2>&1 1> /dev/null");
    $time3 = TIME();
  }

  //run $_php removespecial.php and $_php update_cleanup.php in 1.2 ever 1 hour
  if (((TIME() - $time7) >= $array['CLEANUP_TIMER'] ) && ($array['CLEANUP'] == "true" )) {
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.2 'echo \"\033[1;33m\" && cd $TESTING && $_php removespecial.php && $_php update_cleanup.php && date' 2>&1 1> /dev/null");
    $time7 = TIME();
  }

  //run update_tvschedule.php and $_php update_theaters.php in 1.3 every 12 hours and first loop
  if (((TIME() - $time4) >= $array['TVRAGE_TIMER']) || ($i == 1 )) {
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.3 'echo \"\033[1;34m\" && cd $NNPATH && $_php update_tvschedule.php && $_php update_theaters.php && date' 2>&1 1> /dev/null");
    $time4 = TIME();
  }

  //run optimize_innodb.php in pane 1.4 every 2 hours
  if ((TIME() - $time5 >= 7200 ) && ( $array['INNODB']== "true" ) && ( $array['OPTIMISE'] == "true" )) {
      shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.4 'echo \"\033[1;36m\" && cd bin && $_php optimize_innodb.php && date' 2>&1 1> /dev/null");
      $time5 = TIME();
  }

  //run optimize_myisam.php in pane 1.5 every 2 hours
  if ((TIME() - $time6 >= 3600 ) && ( $array['OPTIMISE'] == "true" )) {
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.5 'echo \"\033[1;37m\" && cd bin && $_php optimize_myisam.php true && date' 2>&1 1> /dev/null");
    $time6 = TIME();
  }

  //run optimize_innodb.php in pane 1.6 every 24 hours
  if ((TIME() - $time8 >= 86400 ) && ($array['INNODB'] == "true") && ( $array['OPTIMISE'] == "true" )) {
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.6 'echo \"\033[1;37m\" && cd bin && $_php optimize_myisam.php true && $_php optimize_innodb.php true && date' 2>&1 1> /dev/null");
    $time8 = TIME();
  }

  //run optimize_innodb.php in pane 1.7 every 1 hour
  if ((TIME() - $time9 >= $array['SPHINX_TIMER'] ) && ( $array['SPHINX'] == "true")) {
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.7 'echo \"\033[1;37m\" && cd bin && $_php sphinx.php && date' 2>&1 1> /dev/null");
    $time9 = TIME();
  }

  // runs nzbcount.php in pane 0.1 continuous loop
  shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.1 'echo \"\033[0;32m\" && cd bin && $_php nzbcount.php' 2>&1 1> /dev/null");

  //runs postprocess_nfo.php in pane 0.2 once if needed then exits
  if (( $nfo_remaining_now > 0 ) && ( $array['POST_TO_RUN'] != 0 )) {
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.2 'echo \"\033[0;32m\" && cd bin && $_php postprocess_nfo.php && date' 2>&1 1> /dev/null");
  }

  //runs processAlternate1.php in pane 0.3 once if needed then exits
  if (( $work_remaining_now > 0 ) && ( $array['POST_TO_RUN'] != 0 )) {
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.3 'echo \"\033[0;33m\" && cd bin && $_php processAlternate1.php && date' 2>&1 1> /dev/null");
  }

  //runs processBooks.php in pane 0.4 once if needed then exits
  if (( $book_releases_proc > 0 ) && ( $array['POST_TO_RUN'] != 0 )) {
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.4 'echo \"\033[0;34m\" && cd bin && $_php processBooks.php && date' 2>&1 1> /dev/null");
  }

  //runs processGames.php in pane 0.5 once if needed then exits
  if (( $console_releases_proc > 0 ) && ( $array['POST_TO_RUN'] != 0 )) {
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.5 'echo \"\033[1;35m\" && cd bin && $_php processGames.php && date' 2>&1 1> /dev/null");
  }

  //runs processMovies.php in pane 0.6 once if needed then exits
  if (( $movie_releases_proc > 0 ) && ( $array['POST_TO_RUN'] != 0 )) {
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.6 'echo \"\033[1;37m\" && cd bin && $_php processMovies.php && date' 2>&1 1> /dev/null");
  }

  //runs processMusic.php in pane 0.7 once if needed then exits
  if (( $music_releases_proc > 0 ) && ( $array['POST_TO_RUN'] != 0 )) {
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.7 'echo \"\033[1;31m\" && cd bin && $_php processMusic.php && date' 2>&1 1> /dev/null");
  }

  //runs processTv.php in pane 0.8 once if needed then exits
  if (( $tvrage_releases_proc > 0 ) && ( $array['POST_TO_RUN'] != 0 )) {
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.8 'echo \"\033[1;32m\" && cd bin && $_php processTv.php && date' 2>&1 1> /dev/null");
  }

  //runs processOthers.php in pane 0.9 once if needed then exits
  if  ( $array['POST_TO_RUN'] != 0 ) {
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.9 'echo \"\033[1;33m\" && cd bin && $_php processOthers.php && date' 2>&1 1> /dev/null");
  }

  //set command for running update_binaries
  if ( $array['BINARIES_THREADS'] == "true" ) {
    $_update_cmd = 'update_binaries_threaded.php';
  } else {
    $_update_cmd = 'update_binaries.php';
  }

  //set command for running backfill
  if ( $array['BACKFILL_THREADS'] == "true" ) {
    $_backfill_cmd = 'backfill_threaded.php';
  } else {
    $_backfill_cmd = 'backfill.php';
  }

  //set command for nzb-import
  if ( $array['NZB_THREADS'] == "true" ){
    $nzb_cmd = "$_php nzb-import-sub.php \"{$array['NZBS']}\"";
  } else {
    $nzb_cmd = "$_php nzb-import.php \"{$array['NZBS']}\" true";
  }

  //runs update_binaries in 0.10 once if needed and exits
  if (( $array['BINARIES'] == "true" ) && (( $total_work_now < $array['MAX_RELEASES'] ) || ( $array['MAX_RELEASES'] == 0 ))) {
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.10 'echo \"\033[1;34m\" && cd $NNPATH && $_php $_update_cmd && date && echo \"$_sleep_string {$array['BINARIES_SLEEP']} seconds...\" && sleep {$array['BINARIES_SLEEP']}' 2>&1 1> /dev/null");
  }

  //runs backfill in 0.11 once if needed and exits
  if (( $array['BACKFILL'] == "true" ) && (( $total_work_now < $array['BACKFILL_MAX_RELEASES'] ) || ( $array['BACKFILL_MAX_RELEASES'] == 0 ))) {
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.11 'echo \"\033[1;35m\" && cd $NNPATH && $_php $_backfill_cmd && \
    $_mysql -u$_DB_USER -h $_DB_HOST --password=$_DB_PASSWORD $_DB_NAME -e \"${backfill_increment}\" && \
    date && echo \"$_sleep_string {$array['BACKFILL_SLEEP']} seconds...\" && sleep {$array['BACKFILL_SLEEP']}' 2>&1 1> /dev/null");
  }

  //runs nzb-import in 0.12 once if needed and exits
  if (( $array['IMPORT'] == "true" ) && (( $total_work_now < $array['IMPORT_MAX_RELEASES'] ) || ( $array['IMPORT_MAX_RELEASES'] == 0 ))) {
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.12 'echo \"\033[1;36m\" && cd bin && $nzb_cmd && echo \" \" && date && echo \"$_sleep_string {$array['IMPORT_SLEEP']} seconds...\" && sleep {$array['IMPORT_SLEEP']}' 2>&1 1> /dev/null");
  }

  //runs update_release and optimise_myisam.php in 0.13 once if needed and exits
  if ((( $array['OPTIMISE'] == "true" ) && ( $array['RELEASES'] == "true" ) && ( ($i % 5) == 0 ))) {
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.13 'echo \"\033[1;37m\" && cd $_current_path && $_php update_releases.php && cd $_current_path && $_php optimize_myisam.php && date && echo \"$_sleep_string {$array['RELEASES_SLEEP']} seconds...\" && sleep {$array['RELEASES_SLEEP']}' 2>&1 1> /dev/null");
  } else {
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.13 'echo \"\033[1;37m\" && cd $_current_path && $_php update_releases.php && cd $_current_path && date && echo \"$_sleep_string {$array['RELEASES_SLEEP']} seconds...\" && sleep {$array['RELEASES_SLEEP']}' 2>&1 1> /dev/null");
  }

  //runs processAlternate2.php in 2.0 once if needed and exits
  if (( $array['POST_TO_RUN'] >= 2 ) && ( $work_remaining_now > 200 )) {
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.0 'echo \"\033[0;31m\" && cd bin && $_php processAlternate2.php && date' 2>&1 1> /dev/null");
  }

  //runs processAlternate3.php in 2.1 once if needed and exits
  if (( $array['POST_TO_RUN'] >= 3 ) && ( $work_remaining_now > 300 )) {
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.1 'echo \"\033[0;32m\" && cd bin && $_php processAlternate3.php && date' 2>&1 1> /dev/null");
  }

  //runs processAlternate4.php in 2.2 once if needed and exits
  if (( $array['POST_TO_RUN'] >= 4 ) && ( $work_remaining_now > 400 )) {
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.2 'echo \"\033[0;33m\" && cd bin && $_php processAlternate4.php && date' 2>&1 1> /dev/null");
  }

  //runs processAlternate5.php in 2.3 once if needed and exits
  if (( $array['POST_TO_RUN'] >= 5 ) && ( $work_remaining_now > 500 )) {
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.3 'echo \"\033[0;34m\" && cd bin && $_php processAlternate5.php && date' 2>&1 1> /dev/null");
  }

  //runs processAlternate6.php in 2.4 once if needed and exits
  if (( $array['POST_TO_RUN'] >= 6 ) && ( $work_remaining_now > 600 )) {
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.4 'echo \"\033[0;35m\" && cd bin && $_php processAlternate6.php && date' 2>&1 1> /dev/null");
  }

  //runs processAlternate7.php in 2.5 once if needed and exits
  if (( $array['POST_TO_RUN'] >= 7 ) && ( $work_remaining_now > 700 )) {
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.5 'echo \"\033[0;36m\" && cd bin && $_php processAlternate7.php && date' 2>&1 1> /dev/null");
  }

  //runs processAlternate8.php in 2.6 once if needed and exits
  if (( $array['POST_TO_RUN'] >= 8 ) && ( $work_remaining_now > 800 )) {
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.6 'echo \"\033[0;37m\" && cd bin && $_php processAlternate8.php && date' 2>&1 1> /dev/null");
  }

  //runs processAlternate9.php in 2.7 once if needed and exits
  if (( $array['POST_TO_RUN'] >= 9 ) && ( $work_remaining_now > 900 )) {
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.7 'echo \"\033[0;38m\" && cd bin && $_php processAlternate9.php && date' 2>&1 1> /dev/null");
  }

  //get microtime and calcutlat time
  $script_timer = microtime_float() - $script_timer_start;

  //continue table
  printf($mask, "Check Scripts","$script_timer","started");
  $lagg=microtime_float()-$time_loop_start;
  printf($mask, "Total Lagg","$lagg","complete");

  //get parts size and display
  $parts_rows = number_format("$parts_rows");
  printf("\n \033[0mThe parts table has \033[1;31m$parts_rows\033[0m rows and is \033[1;31m$parts_size_gb\033[0m\n");

  //turn of monitor if set to false
  if ( $array['RUNNING'] == "true" ) {
    $i++;
  } else {
    $i=0;
  }
  sleep($array['MONITOR_UPDATE']);
}

//shutdown message
shell_exec("$_tmux respawnp -k -t {$array['TMUX_SESSION']}:0.0 'echo \"\033[1;41;33m\n\n\n\nNewznab-tmux is shutting down\n\nPlease wait for all panes to report \n\n\"Pane is dead\" before terminating this session.\n\nTo terminate this session press Ctrl-a c \n\nand at the prompt type \n\ntmux kill-session -t {$array['TMUX_SESSION']}\"'");

?>


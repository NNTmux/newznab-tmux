<?php

require('config.php');
require(WWW_DIR.'/lib/postprocess.php');

$db = new DB();

//totals per category in db, results by parentID
$qry="SELECT COUNT( releases.categoryID ) AS cnt, parentID FROM releases RIGHT JOIN category ON releases.categoryID = category.ID WHERE parentID IS NOT NULL GROUP BY parentID;";

//needs to be processed query
$proc="SELECT ( SELECT COUNT( groupID ) AS cnt from releases where consoleinfoID IS NULL and categoryID BETWEEN 1000 AND 1999 ) AS console, ( SELECT COUNT( groupID ) AS cnt from releases where imdbID IS NULL and categoryID BETWEEN 2000 AND 2999 ) AS movies, ( SELECT COUNT( groupID ) AS cnt from releases where musicinfoID IS NULL and categoryID BETWEEN 3000 AND 3999 ) AS audio, ( SELECT COUNT( groupID ) AS cnt from releases r left join category c on c.ID = r.categoryID where (categoryID BETWEEN 4000 AND 4999 and ((r.passwordstatus between -6 and -1) or (r.haspreview = -1 and c.disablepreview = 0)))) AS pc, ( SELECT COUNT( groupID ) AS cnt from releases where rageID = -1 and categoryID BETWEEN 5000 AND 5999 ) AS tv, ( SELECT COUNT( groupID ) AS cnt from releases where bookinfoID IS NULL and categoryID = 7020 ) AS book, ( SELECT COUNT( groupID ) AS cnt from releases r left join category c on c.ID = r.categoryID where (r.passwordstatus between -6 and -1) or (r.haspreview = -1 and c.disablepreview = 0)) AS work, ( SELECT COUNT( groupID ) AS cnt from releases) AS releases, ( SELECT COUNT( groupID ) AS cnt FROM releases r WHERE r.releasenfoID = 0) AS nforemains, ( SELECT COUNT( groupID ) AS cnt FROM releases WHERE releasenfoID not in (0, -1)) AS nfo, ( SELECT table_rows AS cnt FROM information_schema.TABLES where table_name = 'parts' AND TABLE_SCHEMA = '".DB_NAME."' ) AS parts, ( SELECT concat(round((data_length+index_length)/(1024*1024*1024),2),'GB') AS cnt FROM information_schema.tables where table_name = 'parts' AND TABLE_SCHEMA = '".DB_NAME."' ) AS partsize;";

//get first release inserted datetime and oldest posted datetime
$posted_date="SELECT(select UNIX_TIMESTAMP(adddate) from releases order by adddate asc limit 1) AS adddate, (select UNIX_TIMESTAMP(postdate) from releases order by postdate asc limit 1) AS postdate, (select name from releases order by postdate asc limit 1) AS postdatename;";

//get variables from config.sh and defaults.sh
$varnames = shell_exec("cat ../config.sh | grep ^export | cut -d \= -f1 | awk '{print $2;}'");
$varnames .= shell_exec("cat ../defaults.sh | grep ^export | cut -d \= -f1 | awk '{print $2;}'");
$vardata = shell_exec('cat ../config.sh | grep ^export | cut -d \" -f2 | awk "{print $1;}"');
$vardata .= shell_exec('cat ../defaults.sh | grep ^export | cut -d \" -f2 | awk "{print $1;}"');
$varnames = explode("\n", $varnames);
$vardata = explode("\n", $vardata);
$array = array_combine($varnames, $vardata);
unset($array['']);

//environment
$_backfill_increment = "UPDATE groups set backfill_target=backfill_target+1 where active=1 and backfill_target<{$array['MAXDAYS']};";
$_DB_NAME = getenv('DB_NAME');
$_DB_USER = getenv('DB_USER');
$_DB_HOST = getenv('DB_HOST');
$_DB_PASSWORD = escapeshellarg(getenv('DB_PASSWORD'));$_current_path = dirname(__FILE__);
$_mysql = getenv('MYSQL');
$_php = getenv('PHP');
$_tmux = getenv('TMUXCMD');
$_count_releases = 0;
$_tmux_test = $array['POWERLINE'];
$_imports = $array['NZB_THREADS'];

//got microtime
function microtime_float()
{
  list($usec, $sec) = explode(" ", microtime());
  return ((float)$usec + (float)$sec);
}

$_sleep_string = "\033[1;31msleeping\033[0m ";

function relativeTime($_time) {
  $d[0] = array(1,"sec");
  $d[1] = array(60,"min");
  $d[2] = array(3600,"hr");
  $d[3] = array(86400,"day");
  $d[4] = array(31104000,"yr");

  $w = array();

  $return = "";
  $now = TIME();
  $diff = ($now-$_time);
  $secondsLeft = $diff;

  for($i=4;$i>-1;$i--)
  {
    $w[$i] = intval($secondsLeft/$d[$i][0]);
    $secondsLeft -= ($w[$i]*$d[$i][0]);
    if($w[$i]!=0)
    {
      $return.= "\033[1;31m".abs($w[$i])."\033[0m" . " " . $d[$i][1] . (($w[$i]>1)?'s':'') ." ";
    }
  }

  //$return .= ($diff>0)?"ago":"left";
  return $return;
}

function get_color()
{
  $number = mt_rand(1,231);
  if ( $number == 16 || $number == 17 || $number == 52 || $number == 53 ) { get_color(); }
  return($number);
}

$time = TIME();
$time2 = TIME();
$time3 = TIME();
$time4 = TIME();
$time5 = TIME();
$time6 = TIME();
$time7 = TIME();
$time8 = TIME();
$time9 = TIME();
$time10 = TIME();
$time11 = TIME();
$time12 = TIME();
$time13 = TIME();
$time14 = TIME();

//init start values
$work_start = 0;
$releases_start = 0;
$console_releases_now = 0;
$movie_releases_now = 0;
$music_releases_now = 0;
$pc_releases_now = 0;
$tvrage_releases_now = 0;
$book_releases_now = 0;
$misc_releases_now = 0;
$console_releases_proc = 0;
$movie_releases_proc = 0;
$music_releases_proc = 0;
$pc_releases_proc = 0;
$tvrage_releases_proc = 0;
$work_remaining_now = 0;
$book_releases_proc = 0;
$releases_loop = 0;
$nfo_remaining_now = 0;
$nfo_now = 0;
$parts_rows = 0;
$parts_size_gb = 0;
$releases_now = 0;
$firstdate = 0;
$oldestname = 0;
$oldestdate = 0;
$parts_rows_unformated = 0;

//get valuses from $posted_date
$posted_date_result = @$db->query($posted_date);
if ( $posted_date_result[0]['adddate'] ) { $firstdate = $posted_date_result[0]['adddate']; }
if ( $posted_date_result[0]['postdatename'] ) { $oldestname = $posted_date_result[0]['postdatename']; }
if ( $posted_date_result[0]['postdate'] ) { $oldestdate = $posted_date_result[0]['postdate']; }


$i=1;
while($i>0)
{
  //get microtime at start of loop
  $time_loop_start = microtime_float();

  //chack variables again during loop
  $varnames = shell_exec("cat ../config.sh | grep ^export | cut -d \= -f1 | awk '{print $2;}'");
  $varnames .= shell_exec("cat ../defaults.sh | grep ^export | cut -d \= -f1 | awk '{print $2;}'");
  $vardata = shell_exec('cat ../config.sh | grep ^export | cut -d \" -f2 | awk "{print $1;}"');
  $vardata .= shell_exec('cat ../defaults.sh | grep ^export | cut -d \" -f2 | awk "{print $1;}"');
  $varnames = explode("\n", $varnames);
  $vardata = explode("\n", $vardata);
  $array = array_combine($varnames, $vardata);
  unset($array['']);

  //kill panes if user changed to/from nzb import threaded
  if ( $_imports != $array['NZB_THREADS'] ) {
    shell_exec("$_tmux respawnp -k -t {$array['TMUX_SESSION']}:0.11 'sleep 5'");
    shell_exec("$_tmux respawnp -k -t {$array['TMUX_SESSION']}:0.1 'sleep 5'");
    $_imports = $array['NZB_THREADS'];
  }

  //get microtime to at start of queries
  $query_timer_start=microtime_float();

  //run queries
  $result = @$db->query($qry);
  $initquery = array();
  foreach ($result as $cat=>$sub)
  {
    $initquery[$sub['parentID']] = $sub['cnt'];
  }
  $proc_result = @$db->query($proc);

  //initial query for total releases
  if (( $proc_result[0]['work'] != NULL ) && ( $work_start == 0 )) { $work_start = $proc_result[0]['work']; }
  if (( $proc_result[0]['releases'] ) && ( $releases_start == 0 )) { $releases_start = $proc_result[0]['releases']; }

  //get values from $qry
  if ( $initquery['1000'] != NULL ) { $console_releases_now = $initquery['1000']; }
  if ( $initquery['2000'] != NULL ) { $movie_releases_now = $initquery['2000']; }
  if ( $initquery['3000'] != NULL ) { $music_releases_now = $initquery['3000']; }
  if ( $initquery['4000'] != NULL ) { $pc_releases_now = $initquery['4000']; }
  if ( $initquery['5000'] != NULL ) { $tvrage_releases_now = $initquery['5000']; }
  if ( $initquery['7000'] != NULL ) { $book_releases_now = $initquery['7000']; }
  if ( $initquery['8000'] != NULL ) { $misc_releases_now = $initquery['8000']; }

  //get values from $proc
  if ( $proc_result[0]['console'] != NULL ) { $console_releases_proc = $proc_result[0]['console']; }
  if ( $proc_result[0]['movies'] != NULL ) { $movie_releases_proc = $proc_result[0]['movies']; }
  if ( $proc_result[0]['audio'] != NULL ) { $music_releases_proc = $proc_result[0]['audio']; }
  if ( $proc_result[0]['pc'] != NULL ) { $pc_releases_proc = $proc_result[0]['pc']; }
  if ( $proc_result[0]['tv'] != NULL ) { $tvrage_releases_proc = $proc_result[0]['tv']; }
  if ( $proc_result[0]['book'] != NULL ) { $book_releases_proc = $proc_result[0]['book']; }
  if ( $proc_result[0]['work'] != NULL ) { $work_remaining_now = $proc_result[0]['work']; }
  if ( $proc_result[0]['releases'] != NULL ) { $releases_loop = $proc_result[0]['releases']; }
  if ( $proc_result[0]['nforemains'] != NULL ) { $nfo_remaining_now = $proc_result[0]['nforemains']; }
  if ( $proc_result[0]['nfo'] != NULL ) { $nfo_now = $proc_result[0]['nfo']; }
  if ( $proc_result[0]['parts'] != NULL ) { $parts_rows_unformated = $proc_result[0]['parts']; }
  if ( $proc_result[0]['parts'] != NULL ) { $parts_rows = number_format($proc_result[0]['parts']); }
  if ( $proc_result[0]['partsize'] != NULL ) { $parts_size_gb = $proc_result[0]['partsize']; }
  if ( $proc_result[0]['releases'] ) { $releases_now = $proc_result[0]['releases']; }

  //calculate releases difference
  $releases_since_start = $releases_now - $releases_start;
  $work_since_start = $work_remaining_now - $work_start;
  $total_work_now = $work_remaining_now + $tvrage_releases_proc + $music_releases_proc + $movie_releases_proc + $console_releases_proc + $book_releases_proc;

  if ( $releases_now != 0 ) { 
    $nfo_percent = floor(( $nfo_now / $releases_now) * 100 );
    $console_percent = floor(( $console_releases_now / $releases_now) * 100 );
    $movie_percent = floor(( $movie_releases_now / $releases_now) * 100 );
    $music_percent = floor(( $music_releases_now / $releases_now) * 100 );
    $pc_percent = floor(( $pc_releases_now / $releases_now) * 100 );
    $tvrage_percent = floor(( $tvrage_releases_now / $releases_now) * 100 );
    $book_percent = floor(( $book_releases_now / $releases_now) * 100 );
    $misc_percent = floor(( $misc_releases_now / $releases_now) * 100 );
  } else {
    $nfo_percent = 0;
    $console_percent = 0;
    $movie_percent = 0;
    $music_percent = 0;
    $pc_percent = 0;
    $tvrage_percent = 0;
    $book_percent = 0;
    $misc_percent = 0;
  }

  //get microtime at end of queries
  $query_timer = microtime_float()-$query_timer_start;

  if ( $releases_since_start > 0 ) { $signed = "+"; }
  else { $signed = ""; }

  if ( $work_since_start > 0 ) { $signed1 = "+"; }
  else { $signed1 = ""; }

  passthru('clear');
  printf("\033[1;31m  Monitor\033[0m has been running for:\033[0m ".relativeTime("$time")."\n");
  printf("\033[1;31m  First insert:\033[0m ".relativeTime("$firstdate")."\n");
  printf("\033[1;31m  Oldest Release:\033[0m ".relativeTime("$oldestdate")."\n");
  printf("\033[1;31m  Oldest Release:\033[0m $oldestname\n");
  printf("\033[1;31m  $releases_now($signed$releases_since_start)\033[0m releases in your database.\n");
  printf("\033[1;31m  $total_work_now($signed1$work_since_start)\033[0m releases left to postprocess.\033[1;33m\n");

  $mask = "%20s %10s %13s \n";
  printf($mask, "Category", "In Process", "In Database");
  printf($mask, "===============", "==========", "=============\033[0m");
  printf($mask, "NFO's","$nfo_remaining_now","$nfo_now($nfo_percent%)");
  printf($mask, "Console(1000)","$console_releases_proc","$console_releases_now($console_percent%)");
  printf($mask, "Movie(2000)","$movie_releases_proc","$movie_releases_now($movie_percent%)");
  printf($mask, "Audio(3000)","$music_releases_proc","$music_releases_now($music_percent%)");
  printf($mask, "PC(4000)","$pc_releases_proc","$pc_releases_now($pc_percent%)");
  printf($mask, "TVShows(5000)","$tvrage_releases_proc","$tvrage_releases_now($tvrage_percent%)");
  printf($mask, "Books(7000)","$book_releases_proc","$book_releases_now($book_percent%)");
  printf($mask, "Misc(8000)","$work_remaining_now","$misc_releases_now($misc_percent%)");

  $NNPATH="{$array['NEWZPATH']}{$array['NEWZNAB_PATH']}";
  $TESTING="{$array['NEWZPATH']}{$array['TESTING_PATH']}";

  $mask = "%20s %10.10s %13s \n";
  printf("\n\033[1;33m");
  printf($mask, "Category", "Time", "Status");
  printf($mask, "===============", "==========", "=============\033[0m");
  printf($mask, "Queries","$query_timer","queried");

  //get microtime for timing script check
  $script_timer_start = microtime_float();

  //see if tmux.conf needs to be reloaded
  if ( $_tmux_test != $array['POWERLINE'] ) {
    if ( $array['POWERLINE'] == "true" ) {
      shell_exec("$_tmux source-file powerline/tmux.conf");
    } else {
      shell_exec("$_tmux source-file conf/tmux.conf");
    }
    $_tmux_test = $array['POWERLINE'];
  }

  //run update_predb.php in 1.0 ever 15 minutes
  if (( TIME() - $time2 ) >= $array['PREDB_TIMER'] ) {
    $color = get_color();
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.0 'echo \"\033[38;5;\"$color\"m\" && cd $NNPATH && $_php update_predb.php true && echo \" \033[1;0;33m\" && date' 2>&1 1> /dev/null");
    $time2 = TIME();
  }

  //run $_php update_parsing.php in 1.1 every 1 hour
  if (((( TIME() - $time3 ) >= $array['PARSING_TIMER'] ) || ($i == 1 )) && ($array['PARSING'] == "true" )) {
    $color = get_color();
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.1 'echo \"\033[38;5;\"$color\"m\" && cd $TESTING && $_php update_parsing.php && echo \" \033[1;0;33m\" && date' 2>&1 1> /dev/null");
    $time3 = TIME();
  }

  //run $_php removespecial.php and $_php update_cleanup.php in 1.2 ever 1 hour
  if ((( TIME() - $time7 ) >= $array['CLEANUP_TIMER'] ) && ($array['CLEANUP'] == "true" )) {
    $color = get_color();
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.2 'echo \"\033[38;5;\"$color\"m\" && cd $TESTING && $_php removespecial.php && $_php update_cleanup.php && echo \" \033[1;0;33m\" && date' 2>&1 1> /dev/null");
    $time7 = TIME();
  }

  //run update_tvschedule.php and $_php update_theaters.php in 1.3 every 12 hours and first loop
  if ((( TIME() - $time4 ) >= $array['TVRAGE_TIMER']) || ($i == 1 )) {
    $color = get_color();
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.3 'echo \"\033[38;5;\"$color\"m\" && cd $NNPATH && $_php update_tvschedule.php && $_php update_theaters.php && echo \" \033[1;0;33m\" && date' 2>&1 1> /dev/null");
    $time4 = TIME();
  }

  //run optimize in pane 1.4
  if (( TIME() - $time6 >= $array['MYISAM_LARGE'] ) && ( $array['OPTIMIZE'] == "true" )) {
    $color = get_color();
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.4 'echo \"\033[38;5;\"$color\"m\" && cd bin && $_php optimize_myisam.php true && echo \" \033[1;0;33m\" && date' 2>&1 1> /dev/null");
    $time6 = TIME();
  } elseif (( TIME() - $time8 >= $array['INNODB_LARGE'] ) && ($array['INNODB'] == "true") && ( $array['OPTIMIZE'] == "true" )) {
    $color = get_color();
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.4 'echo \"\033[38;5;\"$color\"m\" && cd bin && $_php optimize_myisam.php true && $_php optimize_innodb.php true && echo \" \033[1;0;33m\" && date' 2>&1 1> /dev/null");
    $time8 = TIME();
  } elseif (( TIME() - $time5 >= $array['INNODB_SMALL'] ) && ( $array['INNODB']== "true" ) && ( $array['OPTIMIZE'] == "true" )) {
    $color = get_color();
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.4 'echo \"\033[38;5;\"$color\"m\" && cd bin && $_php optimize_innodb.php  && echo \" \033[1;0;33m\" && date' 2>&1 1> /dev/null");
    $time5 = TIME();
  } elseif (( TIME() - $time11 >= $array['MYISAM_SMALL'] ) &&  ( $array['OPTIMIZE'] == "true" )) {
    $color = get_color();
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.4 'echo \"\033[38;5;\"$color\"m\" && cd bin && $_php optimize_myisam.php && echo \" \033[1;0;33m\" && date' 2>&1 1> /dev/null");
    $time11 = TIME();
  }

  //run sphinx in pane 1.5
  if (( TIME() - $time9 >= $array['SPHINX_TIMER'] ) && ( $array['SPHINX'] == "true")) {
    $color=get_color();
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.5 'echo \"\033[38;5;\"$color\"m\" && cd bin && $_php sphinx.php && echo \" \033[1;0;33m\" && date' 2>&1 1> /dev/null");
    $time9 = TIME();
  }

  //runs postprocess_nfo.php in pane 0.2 once if needed then exits
  if (( $nfo_remaining_now > 0 ) && ( $array['POST_TO_RUN'] != 0 )) {
    $color = get_color();
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.2 'echo \"\033[38;5;\"$color\"m\" && cd bin && $_php postprocess_nfo.php && echo \" \033[1;0;33m\" && date' 2>&1 1> /dev/null");
  }

  //runs processGames.php in pane 0.3 once if needed then exits
  if (( $console_releases_proc > 0 ) && ( $array['POST_TO_RUN'] != 0 )) {
    $color = get_color();
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.3 'echo \"\033[38;5;\"$color\"m\" && cd bin && $_php processGames.php && echo \" \033[1;0;33m\" && date' 2>&1 1> /dev/null");
  }

  //runs processMovies.php in pane 0.4 once if needed then exits
  if (( $movie_releases_proc > 0 ) && ( $array['POST_TO_RUN'] != 0 )) {
    $color = get_color();
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.4 'echo \"\033[38;5;\"$color\"\" && cd bin && $_php processMovies.php && echo \" \033[1;0;33m\" && date' 2>&1 1> /dev/null");
  }

  //runs processMusic.php in pane 0.5 once if needed then exits
  if (( $music_releases_proc > 0 ) && ( $array['POST_TO_RUN'] != 0 )) {
    $color = get_color();
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.5 'echo \"\033[38;5;\"$color\"m\" && cd bin && $_php processMusic.php && echo \" \033[1;0;33m\" && date' 2>&1 1> /dev/null");
  }

  //runs processTv.php in pane 0.6 once if needed then exits
  if (( $tvrage_releases_proc > 0 ) && ( $array['POST_TO_RUN'] != 0 )) {
    $color = get_color();
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.6 'echo \"\033[38;5;\"$color\"m\" && cd bin && $_php processTv.php && echo \" \033[1;0;33m\" && date' 2>&1 1> /dev/null");
  }

  //runs processBooks.php in pane 0.7 once if needed then exits
  if (( $book_releases_proc > 0 ) && ( $array['POST_TO_RUN'] != 0 )) {
    $color = get_color();
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.7 'echo \"\033[38;5;\"$color\"m\" && cd bin && $_php processBooks.php && echo \" \033[1;0;33m\" && date' 2>&1 1> /dev/null");
  }

  //runs processOthers.php in pane 0.8 once if needed then exits
  if  ( $array['POST_TO_RUN'] != 0 ) {
    $color = get_color();
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.8 'echo \"\033[38;5;\"$color\"m\" && cd bin && $_php processOthers.php && echo \" \033[1;0;33m\" && date' 2>&1 1> /dev/null");
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
  if ( $array['NZB_THREADS'] == "true" ) {
    $nzb_cmd = "$_php nzb-import-sub.php \"{$array['NZBS']}\"";
  } else {
    $nzb_cmd = "$_php nzb-import.php \"{$array['NZBS']}\" \"{$array['IMPORT_TRUE']}\"";
  }

  //check if sequential is set
  if ( $array['SEQUENTIAL'] != "true" ) {
    //runs update_binaries in 0.9 once if needed and exits
    if (( $array['BINARIES'] == "true" ) && (( $total_work_now < $array['MAX_RELEASES'] ) || ( $array['MAX_RELEASES'] == 0 )) && (( $parts_rows_unformated < $array['BINARIES_MAX_ROWS'] ) || ( $array['BINARIES_MAX_ROWS'] == 0 ))) {
      $color = get_color();
      shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.9 'echo \"\033[38;5;\"$color\"m\" && cd $NNPATH && $_php $_update_cmd && echo \" \033[1;0;33m\" && date && echo \"$_sleep_string {$array['BINARIES_SLEEP']} seconds...\" && sleep {$array['BINARIES_SLEEP']}' 2>&1 1> /dev/null");
    } elseif (( $parts_rows_unformated > $array['BINARIES_MAX_ROWS'] ) && ( $array['BINARIES_MAX_ROWS'] != 0 )) {
      $color = get_color();
      shell_exec("$_tmux respawnp -k -t {$array['TMUX_SESSION']}:0.9 'echo \"\033[38;5;\"$color\"m\n\nBINARIES_MAX_ROWS exceeded\"'");
    } elseif (( $total_work_now > $array['MAX_RELEASES'] ) && ( $array['MAX_RELEASES'] != 0 )) {
      $color = get_color();
      shell_exec("$_tmux respawnp -k -t {$array['TMUX_SESSION']}:0.9 'echo \"\033[38;5;\"$color\"m\n\nMAX_RELEASES exceeded\"'");
    }

    //runs backfill in 0.10 once if needed and exits
    if (( $array['BACKFILL'] == "true" ) && (( $total_work_now < $array['BACKFILL_MAX_RELEASES'] ) || ( $array['BACKFILL_MAX_RELEASES'] == 0 )) && (( $parts_rows_unformated < $array['BACKFILL_MAX_ROWS'] ) || ( $array['BACKFILL_MAX_ROWS'] == 0 ))) {
      $color = get_color();
      shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.10 'echo \"\033[38;5;\"$color\"m\" && cd $NNPATH && $_php $_backfill_cmd && \
      $_mysql --defaults-extra-file=$_current_path/../conf/my.cnf -u$_DB_USER -h $_DB_HOST $_DB_NAME -e \"$_backfill_increment\" && \
      echo \" \033[1;0;33m\" && date && echo \"$_sleep_string {$array['BACKFILL_SLEEP']} seconds...\" && sleep {$array['BACKFILL_SLEEP']}' 2>&1 1> /dev/null");
    } elseif (( $parts_rows_unformated > $array['BACKFILL_MAX_ROWS'] ) && ( $array['BACKFILL_MAX_ROWS'] != 0 )) {
      $color = get_color();
      shell_exec("$_tmux respawnp -k -t {$array['TMUX_SESSION']}:0.10 'echo \"\033[38;5;\"$color\"m\n\nBACKFILL_MAX_ROWS exceeded\"'");
    } elseif (( $total_work_now > $array['BACKFILL_MAX_RELEASES'] ) && ( $array['BACKFILL_MAX_RELEASES'] != 0 )) {
      $color = get_color();
      shell_exec("$_tmux respawnp -k -t {$array['TMUX_SESSION']}:0.10 'echo \"\033[38;5;\"$color\"m\n\nBACKFILL_MAX_RELEASES exceeded\"'");
    }
  } else {
    //runs based on timers and all other limiters
    //runs update_binaries and backfill ensures not at same time in 0.9 once if needed and exits
    if (( TIME() - $time13 >= $array['BINARIES_SEQ_TIMER'] ) && ( $array['BINARIES'] == "true" ) && (( $total_work_now < $array['MAX_RELEASES'] ) || ( $array['MAX_RELEASES'] == 0 )) && (( $parts_rows_unformated < $array['BINARIES_MAX_ROWS'] ) || ( $array['BINARIES_MAX_ROWS'] == 0 ))) {
      $color = get_color();
      shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.9 'echo \"\033[38;5;\"$color\"m\" && cd $NNPATH && $_php $_update_cmd && echo \" \033[1;0;33m\" && date' 2>&1 1> /dev/null");
      $time13 = TIME();
    } elseif (( TIME() - $time14 >= $array['BACKFILL_SEQ_TIMER'] ) && ( $array['BACKFILL'] == "true" ) && (( $total_work_now < $array['BACKFILL_MAX_RELEASES'] ) || ( $array['BACKFILL_MAX_RELEASES'] == 0 )) && (( $parts_rows_unformated < $array['BACKFILL_MAX_ROWS'] ) || ( $array['BACKFILL_MAX_ROWS'] == 0 ))) {
      $color = get_color();
      shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.9 'echo \"\033[38;5;\"$color\"m\" && cd $NNPATH && $_php $_backfill_cmd && \
      $_mysql --defaults-extra-file=$_current_path/../conf/my.cnf -u$_DB_USER -h $_DB_HOST $_DB_NAME -e \"$_backfill_increment\" && \
      echo \" \033[1;0;33m\" && date' 2>&1 1> /dev/null");
      $time14 = TIME();
    } elseif (( $parts_rows_unformated > $array['BINARIES_MAX_ROWS'] ) && ( $array['BINARIES_MAX_ROWS'] != 0 )) {
      $color = get_color();
      shell_exec("$_tmux respawnp -k -t {$array['TMUX_SESSION']}:0.9 'echo \"\033[38;5;\"$color\"m\n\nBINARIES_MAX_ROWS exceeded\"'");
    } elseif (( $total_work_now > $array['MAX_RELEASES'] ) && ( $array['MAX_RELEASES'] != 0 )) {
      $color = get_color();
      shell_exec("$_tmux respawnp -k -t {$array['TMUX_SESSION']}:0.9 'echo \"\033[38;5;\"$color\"m\n\nMAX_RELEASES exceeded\"'");
    } elseif (( $parts_rows_unformated > $array['BACKFILL_MAX_ROWS'] ) && ( $array['BACKFILL_MAX_ROWS'] != 0 )) {
      $color = get_color();
      shell_exec("$_tmux respawnp -k -t {$array['TMUX_SESSION']}:0.9 'echo \"\033[38;5;\"$color\"m\n\nBACKFILL_MAX_ROWS exceeded\"'");
    } elseif (( $total_work_now > $array['BACKFILL_MAX_RELEASES'] ) && ( $array['BACKFILL_MAX_RELEASES'] != 0 )) {
      $color = get_color();
      shell_exec("$_tmux respawnp -k -t {$array['TMUX_SESSION']}:0.9 'echo \"\033[38;5;\"$color\"m\n\nBACKFILL_MAX_RELEASES exceeded\"'");
    }
  }

  //runs nzb-import in 0.11 once if needed and exits
  if (( $array['IMPORT'] == "true" ) && (( $total_work_now < $array['IMPORT_MAX_RELEASES'] ) || ( $array['IMPORT_MAX_RELEASES'] == 0 )) && (( $parts_rows_unformated < $array['IMPORT_MAX_ROWS'] ) || ( $array['IMPORT_MAX_ROWS'] == 0 ))) {
    $color = get_color();
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.11 'echo \"\033[38;5;\"$color\"m\" && cd bin && $nzb_cmd && echo \" \" && echo \" \033[1;0;33m\" && date && echo \"$_sleep_string {$array['IMPORT_SLEEP']} seconds...\" && sleep {$array['IMPORT_SLEEP']}' 2>&1 1> /dev/null");
    $color = get_color();
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.1 'echo \"\033[38;5;\"$color\"m\" && cd bin && $_php nzbcount.php' 2>&1 1> /dev/null");
  } elseif (( $parts_rows_unformated > $array['IMPORT_MAX_ROWS'] ) && ( $array['IMPORT_MAX_ROWS'] != 0 )) {
    $color = get_color();
    shell_exec("$_tmux respawnp -k -t {$array['TMUX_SESSION']}:0.11 'echo \"\033[38;5;\"$color\"m\n\nIMPORT_MAX_ROWS exceeded\"'");
  } elseif (( $total_work_now > $array['IMPORT_MAX_RELEASES'] ) && ( $array['IMPORT_MAX_RELEASES'] != 0 )) {
    $color = get_color();
    shell_exec("$_tmux respawnp -k -t {$array['TMUX_SESSION']}:0.11 'echo \"\033[38;5;\"$color\"m\n\nIMPORT_MAX_RELEASES exceeded\"'");
  }

  //runs update_release and in 0.12 once if needed and exits
  if ( $array['RELEASES'] == "true" ) {
    $color = get_color();
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.12 'echo \"\033[38;5;\"$color\"m\" && cd bin && $_php update_releases.php && echo \" \033[1;0;33m\" && date && echo \"$_sleep_string {$array['RELEASES_SLEEP']} seconds...\" && sleep {$array['RELEASES_SLEEP']}' 2>&1 1> /dev/null");
  }

  //start postprocessing in window 2
  for ($g=1; $g<=31; $g++)
  {
    $h=$g-1;
    $f=$h*100;
    if (( $array['POST_TO_RUN'] >= $g ) && ( $work_remaining_now > $f )) {
      $color = get_color();
      shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.$h 'echo \"\033[38;5;\"$color\"m\" && cd bin && $_php processAlternate$g.php && echo \" \033[1;0;33m\" && date' 2>&1 1> /dev/null");
    }
  }

  //kills postprocessing in window 2
  $post = $array['POST_TO_RUN'];
  for ($g=$post; $g<=31; $g++)
  {
    $color = get_color();
    shell_exec("$_tmux respawnp -k -t {$array['TMUX_SESSION']}:2.$g 'echo \"\033[38;5;\"$color\"m\n\nThis is color #\"$color'");
  }

  //get microtime and calcutlat time
  $script_timer = microtime_float() - $script_timer_start;

  //continue table
  printf($mask, "Check Scripts","$script_timer","started");
  $lagg=microtime_float()-$time_loop_start;
  printf($mask, "Total Lagg","$lagg","complete");

  //get parts size and display
  printf("\n \033[0mThe parts table has \033[1;31m$parts_rows\033[0m rows and is \033[1;31m$parts_size_gb\033[0m\n");

  //check ffmpeg and mediainfo, kill if necessary
  if ( $array['KILL_PROCESS'] != "0" ) {
    shell_exec("./check_process.sh mediainfo {$array['KILL_PROCESS']}");
    shell_exec("./check_process.sh ffmpeg {$array['KILL_PROCESS']}");
  }

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



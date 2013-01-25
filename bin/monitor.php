<?php

require_once("config.php");
require_once(WWW_DIR."/lib/postprocess.php");

$db = new DB();

//initial queries
//books to process
$book_query = "SELECT COUNT(*) AS cnt from releases where bookinfoID IS NULL and categoryID = 7020;";
//books in db
$book_query2 = "SELECT COUNT(*) AS cnt from releases where categoryID = 7020;";
//console to process
$console_query = "SELECT COUNT(*) AS cnt from releases where consoleinfoID IS NULL and categoryID in ( select ID from category where parentID = 1000 );";
//console in db
$console_query2 = "SELECT COUNT(*) AS cnt from releases where categoryID in ( select ID from category where parentID = 1000 );";
//movie to process
$movie_query = "SELECT COUNT(*) AS cnt from releases where imdbID IS NULL and categoryID in ( select ID from category where parentID = 2000 );";
//movie in db
$movie_query2 = "SELECT COUNT(*) AS cnt from releases where categoryID in ( select ID from category where parentID = 2000 );";
//music to process
$music_query = "SELECT COUNT(*) AS cnt from releases where musicinfoID IS NULL and categoryID in ( select ID from category where parentID = 3000 );";
//music in db
$music_query2 = "SELECT COUNT(*) AS cnt from releases where categoryID in ( select ID from category where parentID = 3000 );";
//pc to process
$pc_query = "SELECT COUNT(*) AS cnt from releases r left join category c on c.ID = r.categoryID where (categoryID in ( select ID from category where parentID = 4000)) and ((r.passwordstatus between -6 and -1) or (r.haspreview = -1 and c.disablepreview = 0));";
//pc in db
$pc_query2 = "SELECT COUNT(*) AS cnt from releases where categoryID in ( select ID from category where parentID = 4000 );";
//tv to process
$tvrage_query = "SELECT COUNT(*) AS cnt, ID from releases where rageID = -1 and categoryID in ( select ID from category where parentID = 5000 );";
//tv in db
$tvrage_query2 = "SELECT COUNT(*) AS cnt, ID from releases where categoryID in ( select ID from category where parentID = 5000 );";
//total releases in db
$releases_query = "SELECT COUNT(*) AS cnt from releases;";
//realeases to postprocess
$work_remaining_query = "SELECT COUNT(*) AS cnt from releases r left join category c on c.ID = r.categoryID where (r.passwordstatus between -6 and -1) or (r.haspreview = -1 and c.disablepreview = 0);";
//nfos to process
$nfo_remaining_query = "SELECT COUNT(*) AS cnt FROM releases r WHERE r.releasenfoID = 0;";
//nfos in db
$nfo_query = "SELECT count(*) AS cnt FROM releases WHERE releasenfoID not in (0, -1);";

//parts row count
$parts_query = "SELECT table_rows AS cnt FROM information_schema.TABLES where table_name = 'parts';";
//parts table size
$parts_size = "SELECT concat(round((data_length+index_length)/(1024*1024*1024),2),'GB') AS cnt FROM information_schema.tables where table_name = 'parts';";

//initial counts
$releases_start = $db->query($releases_query);
$releases_start = $releases_start[0]['cnt'];


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
$_DB_NAME = getenv('DB_NAME');

function getFileCount($directory) {
  $include = "*.{nzb,NZB}";
  $filecount=0;
  foreach (glob($directory . "*",GLOB_ONLYDIR|GLOB_MARK) as $subDir){
    $filecount += getFileCount($subDir);
  }
  $filecount += count(glob($directory . $include,GLOB_BRACE));
  return $filecount;
}




$_nzbs_to_import_begin=getFileCount($array['NZBS']);

$_current_path = dirname(__FILE__);
$_mysql = getenv('MYSQL');
$_php = getenv('PHP');
$_tmux = getenv('TMUXCMD');

if ( $array['SHOW_WHY']=="true" ) {
  $_string = "\033[1;31mPane is dead?\033[1;33m This means that the script has finished and the pane is idle until the next time the script is called.\033[0m";
  $_string1 = "\033[1;33mThis means that the script has no work to do and the pane is idle until the next time the script is called.\033[0m";
  $_string2 = "\033[1;33mYou have disabled this in edit_these.sh and therefore has no work to do and the pane is idle until the next time the script is called.\033[0m";
} else {
  $_string = "";
  $_string1 = "";
  $_string2 = "";
}
$_sleep_string = "\033[1;31msleeping\033[0m ";

$time = TIME();
$time2 = TIME();
$time3 = TIME();
$time4 = TIME();
$time5 = TIME();
$time6 = TIME();
$time7 = TIME();

$i=1;
while($i>0)
{
  $time_loop_start=TIME();
  $varnames = shell_exec("cat ../edit_these.sh | grep ^export | cut -d \= -f1 | awk '{print $2;}'");
  $vardata = shell_exec('cat ../edit_these.sh | grep ^export | cut -d \" -f2 | awk "{print $1;}"');
  $varnames = explode("\n", $varnames);
  $vardata = explode("\n", $vardata);
  $array = array_combine($varnames, $vardata);
  unset($array['']);



  $secs = TIME() - $time;
  $mins = floor($secs / 60);
  $hrs = floor($mins / 60);
  $days = floor($hrs / 24);
  $sec = floor($secs % 60);
  $min = ($mins % 60);
  $day = ($days % 24);
  $hr = ($hrs % 24);

  //loop counts
  $releases_loop = $db->query($releases_query);
  $releases_loop = $releases_loop[0]['cnt'];

  $sleeptime = getenv('MONITOR_UPDATE');
  if ($i!=1) {
    sleep($sleeptime);
  }
  $short_sleep = $sleeptime;

  //get totals inside loop
  $nfo_remaining_now = $db->query($nfo_remaining_query);
  $nfo_remaining_now = $nfo_remaining_now[0]['cnt'];
  $nfo_now = $db->query($nfo_query);
  $nfo_now = $nfo_now[0]['cnt'];
  $book_releases_proc = $db->query($book_query);
  $book_releases_proc = $book_releases_proc[0]['cnt'];
  $book_releases_now = $db->query($book_query2);
  $book_releases_now = $book_releases_now[0]['cnt'];
  $console_releases_proc = $db->query($console_query);
  $console_releases_proc = $console_releases_proc[0]['cnt'];
  $console_releases_now = $db->query($console_query2);
  $console_releases_now = $console_releases_now[0]['cnt'];
  $movie_releases_proc = $db->query($movie_query);
  $movie_releases_proc = $movie_releases_proc[0]['cnt'];
  $movie_releases_now = $db->query($movie_query2);
  $movie_releases_now = $movie_releases_now[0]['cnt'];
  $music_releases_proc = $db->query($music_query);
  $music_releases_proc = $music_releases_proc[0]['cnt'];
  $music_releases_now = $db->query($music_query2);
  $music_releases_now = $music_releases_now[0]['cnt'];
  $pc_releases_proc = $db->query($pc_query);
  $pc_releases_proc = $pc_releases_proc[0]['cnt'];
  $pc_releases_now = $db->query($pc_query2);
  $pc_releases_now = $pc_releases_now[0]['cnt'];
  $tvrage_releases_proc = $db->query($tvrage_query);
  $tvrage_releases_proc = $tvrage_releases_proc[0]['cnt'];
  $tvrage_releases_now = $db->query($tvrage_query2);
  $tvrage_releases_now = $tvrage_releases_now[0]['cnt'];
  $releases_now = $db->query($releases_query);
  $releases_now = $releases_now[0]['cnt'];
  $work_remaining_now = $db->query($work_remaining_query);
  $work_remaining_now = $work_remaining_now[0]['cnt'];
  $releases_since_start = $releases_now - $releases_start;
  $releases_since_loop = $releases_now - $releases_loop;
  $additional_releases_now = $releases_now - $book_releases_now - $console_releases_now - $movie_releases_now - $music_releases_now - $pc_releases_now - $tvrage_releases_now;
  $total_work_now = $work_remaining_now + $tvrage_releases_proc + $music_releases_proc + $movie_releases_proc + $console_releases_proc + $book_releases_proc;
  $_nzbs_to_import_now=getFileCount($array['NZBS']);
  $_nzbs_process = $_nzbs_to_import_begin - $_nzbs_to_import_now;

  $parts_rows = $db->query($parts_query);
  $parts_rows = $parts_rows[0]['cnt'];
  $parts_size_gb = $db->query($parts_size);
  $parts_size_gb = $parts_size_gb[0]['cnt'];

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
  printf("\033[1;31m$releases_since_loop\033[0m releases added in the previous \033[1;31m$sleeptime\033[0m seconds.\n");
  printf("\033[1;31m$releases_now($signed$releases_since_start)\033[0m releases in your database.\n");
  printf("\033[1;31m$total_work_now\033[0m releases left to postprocess.");
  if ( $array['MAX_RELEASES'] != 0 ) { printf(" update_binaries, backfill and nzb-import will stop running when you exceed {$array['MAX_RELEASES']}\n\n\033[1;33m"); }
  else { printf("\n\n\033[1;33m"); }

  $mask = "%16s %10s %10s \n";
  printf($mask, "Category", "In Process", "In Database");
  printf($mask, "===============", "==========", "==========\033[0m");
  printf($mask, "NZB's","$_nzbs_to_import_now","$_nzbs_process");
  printf($mask, "NFO's","$nfo_remaining_now","$nfo_now");
  printf($mask, "Books(7020)","$book_releases_proc","$book_releases_now");
  printf($mask, "Console(1000)","$console_releases_proc","$console_releases_now");
  printf($mask, "Movie(2000)","$movie_releases_proc","$movie_releases_now");
  printf($mask, "Audio(3000)","$music_releases_proc","$music_releases_now");
  printf($mask, "PC(4000)","$pc_releases_proc","$pc_releases_now");
  printf($mask, "TVShows(5000)","$tvrage_releases_proc","$tvrage_releases_now");
  printf($mask, "Additional Proc","$work_remaining_now","$additional_releases_now");
  $parts_rows = number_format("$parts_rows");
  printf("\n \033[0mThe parts table has \033[1;31m$parts_rows\033[0m rows and is \033[1;31m$parts_size_gb\033[0m\n");
  $NNPATH="{$array['NEWZPATH']}{$array['NEWZNAB_PATH']}";
  $TESTING="{$array['NEWZPATH']}{$array['TESTING_PATH']}";

  //run update_predb.php in 1.0 ever 15 minutes
  if ((TIME() - $time2) >= $array['PREDB_TIMER'] ) {
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.0 'echo \"\033[1;31m\" && cd $NNPATH && $_php update_predb.php true && date && echo \"$_string\"' 2>&1 1> /dev/null");
    $time2 = TIME();
  } else {
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.0 'echo \"\033[1;31m\\n\n\nThis pane runs update_predb.php and cycles every {$array['PREDB_TIMER']} seconds.\"'");
  }

  //run $_php update_parsing.php in 1.1 every 1 hour
  if (((TIME() - $time3) >= $array['PARSING_TIMER'] ) && ($array['PARSING'] == "true" )) {
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.1 'echo \"\033[1;32m\" && cd $TESTING && $_php update_parsing.php && date && echo \"$_string\"' 2>&1 1> /dev/null");
    $time3 = TIME();
  } else {
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.1 'echo \"\033[1;32m\\n\n\nThis pane runs update_parsing.php and cycles every {$array['PARSING_TIMER']} seconds.\"'");
  }


  //run $_php removespecial.php and $_php update_cleanup.php in 1.2 ever 1 hour
  if (((TIME() - $time7) >= $array['CLEANUP_TIMER'] ) && ($array['CLEANUP'] == "true" )) {
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.2 'echo \"\033[1;33m\" && cd {$array['TESTING_PATH']} && $_php removespecial.php && $_php update_cleanup.php && date && echo \"$_string\"' 2>&1 1> /dev/null");
    $time7 = TIME();
  } else {
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.2 'echo \"\033[1;33m\\n\n\nThis pane runs removespecial.php and update_cleanup.php and cycles every {$array['CLEANUP_TIMER']} seconds.\"'");
  }

  //run update_tvschedule.php and $_php update_theaters.php in 1.3 every 12 hours and first loop
  if (((TIME() - $time4) >= $array['TVRAGE_TIMER']) || ($i == 1 )) {
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.3 'echo \"\033[1;34m\" && cd $NNPATH && $_php update_tvschedule.php && $_php update_theaters.php && date && echo \"$_string\"' 2>&1 1> /dev/null");
    $time4 = TIME();
  } else {
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.3 'echo \"\033[1;34m\\n\n\nThis pane runs update_tvschedule.php update_theaters.php and cycles every {$array['TVRAGE_TIMER']} seconds.\"'");
  }


  if ((TIME() - $time5 >= 7200 ) && ( $array['INNODB']== "true" ) && ( $array['OPTIMISE'] == "true" )) {
      shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.4 'echo \"\033[1;36m\" && cd bin && $_php optimize_innodb.php && date && echo \"$_string\"' 2>&1 1> /dev/null");
      $time5 = TIME();
  } else {
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.4 'echo \"\033[1;36m\\n\n\nThis pane runs optimize_innodb.php and cycles every 7200 seconds.\"'");
  }


  if ((TIME() - $time6 >= 86400 ) && ( $array['INNODB']== "true" ) && ( $array['OPTIMISE'] == "true" )) {
    shell_exec("$_tmux respawnp -k -t {$array['TMUX_SESSION']}:1.5 'echo \"\033[1;37m\" && cd bin && $_php optimize_myisam.php true && $_php optimize_innodb.php true && date && echo \"$_string\"' 2>&1 1> /dev/null");
    $time6 = TIME();
  } else {
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.5 'echo \"\033[1;36m\\n\n\nThis pane runs optimize_myisam.php true and optimize_innodb.php true and cycles every 24 hours.\"'");
  }


  //check if scripts need to be started
  if ( $nfo_remaining_now > 0 ) {
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.1 'echo \"\033[0;32m\" && cd bin && $_php postprocess_nfo.php && date && echo \"$_string\"' 2>&1 1> /dev/null");
  } else {
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.1 'echo \"\033[1;32m\n$nfo_remaining_now\033[1;33m nfos to process. $_string1\"' 2>&1 1> /dev/null");
  }

  if ( $work_remaining_now > 0 ) {
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.2 'echo \"\033[0;33m\" && cd bin && $_php processAlternate1.php && date && echo \"$_string\"' 2>&1 1> /dev/null");
  } else {
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.2 'echo \"$_string1\"' 2>&1 1> /dev/null");
  }

  if ( $book_releases_proc > 0 ) {
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.3 'echo \"\033[0;34m\" && cd bin && $_php processBooks.php && date && echo \"$_string\"' 2>&1 1> /dev/null");
  } else {
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.3 'echo \"\033[1;34m\n$book_releases_proc\033[1;33m books to process. $_string1\"' 2>&1 1> /dev/null");
  }
  if ( $console_releases_proc > 0 ) {
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.4 'echo \"\033[1;35m\" && cd bin && $_php processGames.php && date && echo \"$_string\"' 2>&1 1> /dev/null");
  } else {
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.4 'echo \"\033[1;36m\n$console_releases_proc\033[1;33m console to process. $_string1\"' 2>&1 1> /dev/null");
  }
  if ( $movie_releases_proc > 0 ) {
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.5 'echo \"\033[1;37m\" && cd bin && $_php processMovies.php && date && echo \"$_string\"' 2>&1 1> /dev/null");
  } else {
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.5 'echo \"\033[1;37m\n$movie_releases_proc\033[1;33m movies to process. $_string1\"' 2>&1 1> /dev/null");
  }
  if ( $music_releases_proc > 0 ) {
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.6 'echo \"\033[1;31m\" && cd bin && $_php processMusic.php && date && echo \"$_string\"' 2>&1 1> /dev/null");
  } else {
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.6 'echo \"\033[1;31m\n$music_releases_proc\033[1;33m music to process. $_string1\"' 2>&1 1> /dev/null");
  }
  if ( $tvrage_releases_proc > 0 ) {
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.7 'echo \"\033[1;32m\" && cd bin && $_php processTv.php && date && echo \"$_string\"' 2>&1 1> /dev/null");
  } else {
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.7 'echo \"\033[1;32m\n$tvrage_releases_proc\033[1;33m tv shows to process. $_string1\"' 2>&1 1> /dev/null");
  }
  shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.8 'echo \"\033[1;33m\" && cd bin && $_php processOthers.php && date && echo \"$_string\"' 2>&1 1> /dev/null");

  if ( $array['THREADS'] == "true" ) {
    $_backfill_cmd = 'backfill_threaded.php';
    $_update_cmd = 'update_binaries_threaded.php';
  } else {
    $_backfill_cmd = 'backfill.php';
    $_update_cmd = 'update_binaries.php';
  }
  if ( $array['NZBMULTI'] == "true" ){
    $nzb_cmd = "$_php nzb-import-sub.php \"{$array['NZBS']}\"";
  } else {
    $nzb_cmd = "$_php nzb-import.php \"{$array['NZBS']}\" true";
  }

  if ( $array['BINARIES'] == "true" ) {
    if (( $total_work_now < $array['MAX_RELEASES'] ) || ( $array['MAX_RELEASES'] == 0 )) {
      shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.9 'echo \"\033[1;34m\" && cd $NNPATH && $_php $_update_cmd && date && echo \"$_sleep_string {$array['NNTP_SLEEP']} seconds...\" && sleep {$array['NNTP_SLEEP']} && echo \"$_string\"' 2>&1 1> /dev/null");
    } else {
      shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.9 'echo \"$_string2\"' 2>&1 1> /dev/null");
    }
  }

  if ( $array['BACKFILL'] == "true" ) {
    if (( $total_work_now < $array['BACKFILL_MAX_RELEASES'] ) || ( $array['BACKFILL_MAX_RELEASES'] == 0 )) {
      shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.10 'echo \"\033[1;35m\" && cd $NNPATH && $_php $_backfill_cmd && \
      $_mysql -u$_DB_USER -h $_DB_HOST --password=$_DB_PASSWORD $_DB_NAME -e \"${backfill_increment}\" && \
      date && echo \"$_sleep_string {$array['BACKFILL_SLEEP']} seconds...\" && sleep {$array['BACKFILL_SLEEP']} && echo \"$_string\"' 2>&1 1> /dev/null");
    } else {
      shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.10 'echo \"$_string2\"' 2>&1 1> /dev/null");
    }
  }

  if ( $array['IMPORT'] == "true" ) {
    if (( $total_work_now < $array['IMPORT_MAX_RELEASES'] ) || ( $array['IMPORT_MAX_RELEASES'] == 0 )) {
      shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.11 'echo \"\033[1;36m\" && cd bin && $nzb_cmd && echo \" \" && date && echo \"$_sleep_string {$array['IMPORT_SLEEP']} seconds...\" && sleep {$array['IMPORT_SLEEP']} && echo \"$_string\"' 2>&1 1> /dev/null");
    } else {
      shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.11 'echo \"\n$_string2\"' 2>&1 1> /dev/null");
    }
  }
  if (( $array['OPTIMISE'] == "true" ) && ( ($i % 5) == 0 )) {
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.12 'echo \"\033[1;37m\" && cd $NNPATH && $_php update_releases.php && cd $_current_path && $_php optimize_myisam.php && date && echo \"$_sleep_string {$array['RELEASES_SLEEP']} seconds...\" && sleep {$array['RELEASES_SLEEP']} && echo \"$_string\"' 2>&1 1> /dev/null");
  } else {
    shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.12 'echo \"\033[1;37m\" && cd $NNPATH && $_php update_releases.php && cd $_current_path && date && echo \"$_sleep_string {$array['RELEASES_SLEEP']} seconds...\" && sleep {$array['RELEASES_SLEEP']} && echo \"$_string\"' 2>&1 1> /dev/null");
  }

  if ( $array['POST_TO_RUN'] >= 2 ) {
    if ( $work_remaining_now > 200 ) {
      shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.0 'echo \"\033[0;33m\" && cd bin && $_php processAlternate2.php && date && echo \"$_string\"' 2>&1 1> /dev/null");
    } else {
      shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.0 'echo \"\033[1;34m\nOnly active when releases to postprocess exceed 200. $_string1\"' 2>&1 1> /dev/null");
    }
  }
  if ( $array['POST_TO_RUN'] >= 3 ) {
    if ( $work_remaining_now > 300 ) {
      shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.1 'echo \"\033[0;33m\" && cd bin && $_php processAlternate3.php && date && echo \"$_string\"' 2>&1 1> /dev/null");
    } else {
      shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.1 'echo \"\033[1;34m\nOnly active when releases to postprocess exceed 300. $_string1\"' 2>&1 1> /dev/null");
    }
  }
  if ( $array['POST_TO_RUN'] >= 4 ) {
    if ( $work_remaining_now > 400 ) {
      shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.2 'echo \"\033[0;33m\" && cd bin && $_php processAlternate4.php && date && echo \"$_string\"' 2>&1 1> /dev/null");
    } else {
      shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.2 'echo \"\033[1;34m\nOnly active when releases to postprocess exceed 400. $_string1\"' 2>&1 1> /dev/null");
    }
  }
  if ( $array['POST_TO_RUN'] >= 5 ) {
    if ( $work_remaining_now > 500 ) {
      shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.3 'echo \"\033[0;33m\" && cd bin && $_php processAlternate5.php && date && echo \"$_string\"' 2>&1 1> /dev/null");
    } else {
      shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.3 'echo \"\033[1;34m\nOnly active when releases to postprocess exceed 500. $_string1\"' 2>&1 1> /dev/null");
    }
  }
  if ( $array['POST_TO_RUN'] >= 6 ) {
    if ( $work_remaining_now > 600 ) {
      shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.4 'echo \"\033[0;33m\" && cd bin && $_php processAlternate6.php && date && echo \"$_string\"' 2>&1 1> /dev/null");
    } else {
      shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.4 'echo \"\033[1;34m\nOnly active when releases to postprocess exceed 600. $_string1\"' 2>&1 1> /dev/null");
    }
  }
  if ( $array['POST_TO_RUN'] >= 7 ) {
    if ( $work_remaining_now > 700 ) {
      shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.5 'echo \"\033[0;33m\" && cd bin && $_php processAlternate7.php && date && echo \"$_string\"' 2>&1 1> /dev/null");
    } else {
      shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.5 'echo \"\033[1;34m\nOnly active when releases to postprocess exceed 700. $_string1\"' 2>&1 1> /dev/null");
    }
  }
  if ( $array['POST_TO_RUN'] >= 8 ) {
    if ( $work_remaining_now > 800 ) {
      shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.6 'echo \"\033[0;33m\" && cd bin && $_php processAlternate8.php && date && echo \"$_string\"' 2>&1 1> /dev/null");
    } else {
      shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.6 'echo \"\033[1;34m\nOnly active when releases to postprocess exceed 800. $_string1\"' 2>&1 1> /dev/null");
    }
  }
  if ( $array['POST_TO_RUN'] >= 9 ) {
    if ( $work_remaining_now > 900 ) {
      shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.7 'echo \"\033[0;33m\" && cd bin && $_php processAlternate9.php && date && echo \"$_string\"' 2>&1 1> /dev/null");
    } else {
      shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.7 'echo \"\033[1;34m\nOnly active when releases to postprocess exceed 900. $_string1\"' 2>&1 1> /dev/null");
    }
  }
  $lagg=(TIME()-$time_loop_start);
  printf(" Current lag(processing time) is \033[1;31m$lagg\033[0m seconds per loop, after  \033[1;31m$i\033[0m loops.");
  if ( $array['RUNNING'] == "true" ) {
    $i++;
  } else {
    $i=0;
  }
}

shell_exec("$_tmux respawnp -k -t {$array['TMUX_SESSION']}:0.0 'echo \"\033[1;41;33m\n\n\n\nNewznab-tmux is shutting down\n\nPlease wait for all panes to report \n\n\"Pane is dead\" before terminating this session.\n\nTo terminate this session press Ctrl-a c \n\nand at the prompt type \n\ntmux kill-session -t {$array['TMUX_SESSION']}\"'");



?>

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
$nfo_query = "SELECT COUNT(*) AS cnt FROM releases r WHERE r.releasenfoID != 0;";

//parts row count
$parts_query = "SELECT COUNT(*) AS cnt from parts;";

$_maxdays = getenv('MAXDAYS');
$backfill_increment = "UPDATE groups set backfill_target=backfill_target+1 where active=1 and backfill_target<$_maxdays;";

//initial counts
$releases_start = $db->query($releases_query);
$releases_start = $releases_start[0]['cnt'];

//environment
$_DB_USER = getenv('DB_USER');
$_DB_HOST = getenv('DB_HOST');
$_DB_PASSWORD = getenv('DB_PASSWORD');
$_DB_NAME = getenv('DB_NAME');
$_backfill = getenv('BACKFILL');
$_binaries = getenv('BINARIES');
$_import = getenv('IMPORT');
$_cleanup = getenv('CLEANUP');
$_optimise = getenv('OPTIMISE');
//$_inno_test = shell_exec('svn info /var/www/newznab | grep inno');

$_innodb_path = getenv('INNODB_PATH');
$_admin_path = getenv('ADMIN_PATH');
$_max_releases = getenv('MAX_RELEASES');
$_import_max_releases = getenv('IMPORT_MAX_RELEASES');
$_backfill_max_releases = getenv('BACKFILL_MAX_RELEASES');
$_nzbs = getenv('NZBS');
$_nzbs_to_import_begin = count(glob($_nzbs."/*.nzb"));
$_newznab_path = getenv('NEWZNAB_PATH');
$_testing_path = getenv('TESTING_PATH');
$_mysql = getenv('MYSQL');
$_php = getenv('PHP');
$_nntp = getenv('NNTP_SLEEP');
$_rel_sleep = getenv('RELEASES_SLEEP');
$_import_sleep = getenv('IMPORT_SLEEP');
$_backfill_sleep = getenv('BACKFILL_SLEEP');
$_threads = getenv('THREADS');
$_innodb = getenv('INNODB');
$_tmux_session = getenv('TMUX_SESSION');
$_show_why = getenv('SHOW_WHY');

if ( $_show_why=="true" ) {
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

$i=1;
while($i>0)
{
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
  $_nzbs_to_import_now = count(glob($_nzbs."/*.nzb"));
  $_nzbs_process = $_nzbs_to_import_begin - $_nzbs_to_import_now;

  $parts_rows = $db->query($parts_query);
  $parts_rows = $parts_rows[0]['cnt'];

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
  printf("$releases_since_loop releases added in the previous $sleeptime seconds.\n");
  printf("$releases_now($signed$releases_since_start) releases in your database.\n");
  printf("$total_work_now releases left to postprocess.");
  if ( $_max_releases != 0 ) { printf(" update_binaries, backfill and nzb-import will stop running when you exceed $_max_releases\n\n\033[1;33m"); }
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
  printf("\n \033[1;31m$parts_rows\033[0m rows in parts table\n");

  if ((TIME() - $time2) >= 900 ) {
    shell_exec("tmux respawnp -t $_tmux_session:1.0 'echo -e \"\033[1;33m\" && cd $_newznab_path && $_php update_predb.php true && date && echo \"$_string\"' 2>&1 1> /dev/null");
    $time2 = TIME();
  }
  if (((TIME() - $time3) >= 7200 ) && ($_cleanup == "true" )) {
    shell_exec("tmux respawnp -t $_tmux_session:1.1 'echo -e \"\033[1;34m\" && cd $_testing_path && $_php update_parsing.php && $_php removespecial.php && $_php update_cleanup.php && date && echo \"$_string\"' 2>&1 1> /dev/null");
    $time3 = TIME();
  }
  if ((TIME() - $time4) >= 43200) {
    shell_exec("tmux respawnp -t $_tmux_session:1.2 'echo -e \"\033[1;35m\" && cd $_newznab_path && $_php update_tvschedule.php && $_php update_theaters.php && date && echo \"$_string\"' 2>&1 1> /dev/null");
    if (( $_innodb== "true" ) && ( $_optimise == "true" )) {
      shell_exec("tmux respawnp -t $_tmux_session:1.3 'echo -e \"\033[1;36m\" && cd $_innodb_path && $_php optimise_myisam.php && $_php optimise_innodb.php && date && echo \"$_string\"' 2>&1 1> /dev/null");
    } elseif (( $_innodb== "false" ) && ( $_optimise == "true" )) {
      shell_exec("tmux respawnp -t $_tmux_session:1.3 'echo -e \"\033[1;37m\" && cd $_innodb_path && $_php optimise_myisam.php && date && echo \"$_string\"' 2>&1 1> /dev/null");
    }
    $time4 = TIME();
  }

  //check if scripts need to be started
  if ( $nfo_remaining_now > 0 ) {
    shell_exec("tmux respawnp -t $_tmux_session:0.1 'echo -e \"\033[0;31m\" && cd bin && $_php postprocess_nfo.php && date && echo \"$_string\"' 2>&1 1> /dev/null");
  } else {
   shell_exec("tmux respawnp -t $_tmux_session:0.6 'echo \"\033[1;31m\n$nfo_remaining_now\033[1;33m nfos to process. $_string1\"' 2>&1 1> /dev/null");
  }


  if ( $work_remaining_now > 0 ) {
    shell_exec("tmux respawnp -t $_tmux_session:0.2 'echo -e \"\033[0;32m\" && cd bin && $_php processAlternate2.php && date && echo \"$_string\"' 2>&1 1> /dev/null");
  } else {
   shell_exec("tmux respawnp -t $_tmux_session:0.2 'echo \"$_string1\"' 2>&1 1> /dev/null");
  }
  if ( $work_remaining_now > 200 ) {
    shell_exec("tmux respawnp -t $_tmux_session:0.3 'echo -e \"\033[0;33m\" && cd bin && $_php processAlternate3.php && date && echo \"$_string\"' 2>&1 1> /dev/null");
  } else {
   shell_exec("tmux respawnp -t $_tmux_session:0.3 'echo \"\033[1;34m\nOnly active when releases to postprocess exceed 200. $_string1\"' 2>&1 1> /dev/null");
  }
  if ( $work_remaining_now > 400 ) {
    shell_exec("tmux respawnp -t $_tmux_session:0.4 'echo -e \"\033[0;34m\" && cd bin && $_php processAlternate4.php && date && echo \"$_string\"' 2>&1 1> /dev/null");
  } else {
   shell_exec("tmux respawnp -t $_tmux_session:0.4 'echo \"\033[0;34m\nOnly active when releases to postprocess exceed 400. $_string1\"' 2>&1 1> /dev/null");
  }
  if ( $work_remaining_now > 600 ) {
    shell_exec("tmux respawnp -t $_tmux_session:0.5 'echo -e \"\033[0;35m\" && cd bin && $_php processAlternate5.php && date && echo \"$_string\"' 2>&1 1> /dev/null");
  } else {
   shell_exec("tmux respawnp -t $_tmux_session:0.5 'echo \"\033[1;34m\nOnly active when releases to postprocess exceed 600. $_string1\"' 2>&1 1> /dev/null");
  }
  if ( $book_releases_proc > 0 ) {
    shell_exec("tmux respawnp -t $_tmux_session:0.6 'echo -e \"\033[0;36m\" && cd bin && $_php processBooks.php && date && echo \"$_string\"' 2>&1 1> /dev/null");
  } else {
   shell_exec("tmux respawnp -t $_tmux_session:0.6 'echo \"\033[1;31m\n$book_releases_proc\033[1;33m books to process. $_string1\"' 2>&1 1> /dev/null");
  }
  if ( $console_releases_proc > 0 ) {
    shell_exec("tmux respawnp -t $_tmux_session:0.7 'echo -e \"\033[0;37m\" && cd bin && $_php processGames.php && date && echo \"$_string\"' 2>&1 1> /dev/null");
  } else {
   shell_exec("tmux respawnp -t $_tmux_session:0.7 'echo \"\033[1;31m\n$console_releases_proc\033[1;33m console to process. $_string1\"' 2>&1 1> /dev/null");
  }
  if ( $movie_releases_proc > 0 ) {
    shell_exec("tmux respawnp -t $_tmux_session:0.8 'echo -e \"\033[1;31m\" && cd bin && $_php processMovies.php && date && echo \"$_string\"' 2>&1 1> /dev/null");
  } else {
   shell_exec("tmux respawnp -t $_tmux_session:0.8 'echo \"\033[1;31m\n$movie_releases_proc\033[1;33m movies to process. $_string1\"' 2>&1 1> /dev/null");
  }
  if ( $music_releases_proc > 0 ) {
    shell_exec("tmux respawnp -t $_tmux_session:0.9 'echo -e \"\033[1;32m\" && cd bin && $_php processMusic.php && date && echo \"$_string\"' 2>&1 1> /dev/null");
  } else {
   shell_exec("tmux respawnp -t $_tmux_session:0.9 'echo \"\033[1;31m\n$music_releases_proc\033[1;33m music to process. $_string1\"' 2>&1 1> /dev/null");
  }
  if ( $tvrage_releases_proc > 0 ) {
    shell_exec("tmux respawnp -t $_tmux_session:0.10 'echo -e \"\033[1;33m\" && cd bin && $_php processTv.php && date && echo \"$_string\"' 2>&1 1> /dev/null");
  } else {
   shell_exec("tmux respawnp -t $_tmux_session:0.10 'echo \"\033[1;31m\n$tvrage_releases_proc\033[1;33m tv shows to process. $_string1\"' 2>&1 1> /dev/null");
  }
  shell_exec("tmux respawnp -t $_tmux_session:0.11 'echo -e \"\033[1;34m\" && cd bin && $_php processOthers.php && date && echo \"$_string\"' 2>&1 1> /dev/null");


  if ( $_threads == "true" ) {
	$_import_path = $_innodb_path;
	$_update_path = $_newznab_path;
	$_import_cmd = 'nzb-import.php';
	$_backfill_cmd = 'backfill_threaded.php';
	$_update_cmd = 'update_binaries_threaded.php';
  } else {
	$_import_path = $_innodb_path;
	$_update_path = $_newznab_path;
	$_import_cmd = 'nzb-import.php';
	$_backfill_cmd = 'backfill.php';
	$_update_cmd = 'update_binaries.php';
  }

  if (( $total_work_now < $_max_releases ) || ( $_max_releases == 0 ) && ( $_binaries == "true" )) {
    shell_exec("tmux respawnp -t $_tmux_session:0.12 'echo -e \"\033[1;34m\" && cd $_update_path && $_php $_update_cmd && date && echo \"$_sleep_string $_nntp seconds...\" && sleep $_nntp && echo \"$_string\"' 2>&1 1> /dev/null");
  } else {
   shell_exec("tmux respawnp -t $_tmux_session:0.12 'echo \"$_string2\"' 2>&1 1> /dev/null");
  }
  if (( $total_work_now < $_backfill_max_releases ) || ( $_backfill_max_releases == 0 ) && ( $_backfill == "true" )) {
    shell_exec("tmux respawnp -t $_tmux_session:0.13 'echo -e \"\033[1;35m\" && cd $_update_path && $_php $_backfill_cmd && \
                                                   $_mysql -u$_DB_USER -h $_DB_HOST --password=$_DB_PASSWORD $_DB_NAME -e \"${backfill_increment}\" && \
                                                   date && echo \"$_sleep_string $_backfill_sleep seconds...\" && sleep $_backfill_sleep && echo \"$_string\"' 2>&1 1> /dev/null");
  } else {
   shell_exec("tmux respawnp -t $_tmux_session:0.13 'echo \"$_string2\"' 2>&1 1> /dev/null");
  }
  if (( $total_work_now < $_import_max_releases ) || ( $_import_max_releases == 0 ) && ( $_import == "true" )) {
    shell_exec("tmux respawnp -t $_tmux_session:0.14 'echo -e \"\033[1;36m\" && cd $_import_path && $_php $_import_cmd \"$_nzbs\" true && date && echo \"$_sleep_string $_import_sleep seconds...\" && sleep $_import_sleep && echo \"$_string\"' 2>&1 1> /dev/null");
  } else {
   shell_exec("tmux respawnp -t $_tmux_session:0.14 'echo \"$_string2\"' 2>&1 1> /dev/null");
  }
  shell_exec("tmux respawnp -t $_tmux_session:0.15 'echo -e \"\033[1;37m\" && cd $_newznab_path && $_php update_releases.php && date && echo \"$_sleep_string $_rel_sleep seconds...\" && sleep $_rel_sleep && echo \"$_string\"' 2>&1 1> /dev/null");

  $i++;
}

?>


<?php

require(dirname(__FILE__)."/config.php");
require(WWW_DIR.'/lib/postprocess.php');
$version="0.1r698";

$db = new DB();

//totals per category in db, results by parentID
$qry = "SELECT COUNT( releases.categoryID ) AS cnt, parentID FROM releases RIGHT JOIN category ON releases.categoryID = category.ID WHERE parentID IS NOT NULL GROUP BY parentID;";

//needs to be processed query
$proc = "SELECT ( SELECT COUNT( groupID ) AS cnt from releases where consoleinfoID IS NULL and categoryID BETWEEN 1000 AND 1999 ) AS console, ( SELECT COUNT( groupID ) AS cnt from releases where imdbID IS NULL and categoryID BETWEEN 2000 AND 2999 ) AS movies, ( SELECT COUNT( groupID ) AS cnt from releases where musicinfoID IS NULL and categoryID BETWEEN 3000 AND 3999 ) AS audio, ( SELECT COUNT( groupID ) AS cnt from releases r left join category c on c.ID = r.categoryID where (categoryID BETWEEN 4000 AND 4999 and ((r.passwordstatus between -6 and -1) or (r.haspreview = -1 and c.disablepreview = 0)))) AS pc, ( SELECT COUNT( groupID ) AS cnt from releases where rageID = -1 and categoryID BETWEEN 5000 AND 5999 ) AS tv, ( SELECT COUNT( groupID ) AS cnt from releases where bookinfoID IS NULL and categoryID = 7020 ) AS book, ( SELECT COUNT( groupID ) AS cnt from releases r left join category c on c.ID = r.categoryID where (r.passwordstatus between -6 and -1) or (r.haspreview = -1 and c.disablepreview = 0)) AS work, ( SELECT COUNT( groupID ) AS cnt from releases) AS releases, ( SELECT COUNT( groupID ) AS cnt FROM releases r WHERE r.releasenfoID = 0) AS nforemains, ( SELECT COUNT( groupID ) AS cnt FROM releases WHERE releasenfoID not in (0, -1)) AS nfo, ( SELECT table_rows AS cnt FROM information_schema.TABLES where table_name = 'parts' AND TABLE_SCHEMA = '".DB_NAME."' ) AS parts, ( SELECT concat(round((data_length+index_length)/(1024*1024*1024),2),'GB') AS cnt FROM information_schema.tables where table_name = 'parts' AND TABLE_SCHEMA = '".DB_NAME."' ) AS partsize, ( SELECT UNIX_TIMESTAMP(adddate) from releases order by adddate desc limit 1 ) AS newestadd, ( SELECT name from releases order by adddate desc limit 1 ) AS newestaddname;";

//get first release inserted datetime and oldest posted datetime
$posted_date = "SELECT(SELECT UNIX_TIMESTAMP(adddate) from releases order by adddate asc limit 1) AS adddate;";

//get variables from config.sh and defaults.sh
$path = dirname(__FILE__);
$varnames = shell_exec("cat ".$path."/../config.sh | grep ^export | cut -d \= -f1 | awk '{print $2;}'");
$varnames .= shell_exec("cat ".$path."/../defaults.sh | grep ^export | cut -d \= -f1 | awk '{print $2;}'");
$vardata = shell_exec("cat ".$path."/../config.sh | grep ^export | cut -d \\\" -f2 | awk '{print $1;}'");
$vardata .= shell_exec("cat ".$path."/../defaults.sh | grep ^export | cut -d \\\" -f2 | awk '{print $1;}'");
$varnames = explode("\n", $varnames);
$vardata = explode("\n", $vardata);
$array = array_combine($varnames, $vardata);
unset($array['']);

//environment
$_DB_NAME = getenv('DB_NAME');
$_DB_USER = getenv('DB_USER');
$_DB_HOST = getenv('DB_HOST');
$_DB_PASSWORD = escapeshellarg(getenv('DB_PASSWORD'));
$_DB_PASS = getenv('DB_PASSWORD');
$_mysql = getenv('MYSQL');
$_php = getenv('PHP');
$_tmux = getenv('TMUXCMD');
$_count_releases = 0;
$_tmux_test = $array['POWERLINE'];
$_imports = $array['NZB_THREADS'];
$_bin = dirname(__FILE__)."/../bin";
$_alienx = dirname(__FILE__)."/../alienx";
$_conf = dirname(__FILE__)."/../conf";
$_cj = dirname(__FILE__)."/../nnscripts";
$NNPATH="{$array['NEWZPATH']}{$array['NEWZNAB_PATH']}";
$TESTING="{$array['NEWZPATH']}{$array['TESTING_PATH']}";
$killed="false";
$old_session="{$array['TMUX_SESSION']}";

//build queries for shell
$_backfill_increment = "UPDATE groups set backfill_target=backfill_target+1 where active=1 and backfill_target<{$array['MAXDAYS']};";
$mysql_command_1 = "$_mysql --defaults-file=$_conf/my.cnf -u$_DB_USER -h $_DB_HOST $_DB_NAME -e \"$_backfill_increment\"";
//$mysql_command_1 = "$_mysql -u$_DB_USER -p $_DB_PASSWORD -h $_DB_HOST $_DB_NAME -e \"$_backfill_increment\"";

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
            //$return.= abs($w[$i]). " " . $d[$i][1] . (($w[$i]>1)?'s':'') ." ";
            $return.= $w[$i]. " " . $d[$i][1] . (($w[$i]>1)?'s':'') ." ";
        }
    }

    //$return .= ($diff>0)?"ago":"left";
    return $return;
}

function get_color()
{
    $from = 1;
    $to = 231;
    $exceptions = array( 4, 8, 16, 17, 18, 19, 52, 53, 59, 60, 67 );
    sort($exceptions); // lets us use break; in the foreach reliably
    $number = mt_rand($from, $to - count($exceptions)); // or mt_rand()
    foreach ($exceptions as $exception) {
        if ($number >= $exception) {
            $number++; // make up for the gap
        } else /*if ($number < $exception)*/ {
            break;
        }
    }
    return $number;
}

function decodeSize( $bytes )
{
    $types = array( 'B', 'KB', 'MB', 'GB', 'TB' );
    for( $i = 0; $bytes >= 1024 && $i < ( count( $types ) -1 ); $bytes /= 1024, $i++ );
    return( round( $bytes, 2 ) . " " . $types[$i] );
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
$time15 = TIME();
$time16 = TIME();
$time17 = TIME();

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
$firstdate = TIME();
$newestname = "Unknown";
$newestdate = TIME();
$parts_rows_unformated = 0;
$releases_now_formatted = 0;
$nfo_percent = 0;
$console_percent = 0;
$movie_percent = 0;
$music_percent = 0;
$pc_percent = 0;
$tvrage_percent = 0;
$book_percent = 0;
$misc_percent = 0;
$releases_since_start = 0;
$work_since_start = 0;
$work_diff = number_format( $work_since_start );
$total_work_now = 0;
$total_work_used = 0;
$total_work_now_formatted = 0;
$binaries_state = "disabled";
$binaries_reason = "disabled";
$backfill_state = "disabled";
$backfill_reason = "disabled";
$import_state = "disabled";
$import_reason = "disabled";
$query_timer_start = 0;
$query_timer = 0;
$console_releases_start = 0;
$movie_releases_start = 0;
$music_releases_start = 0;
$pc_releases_start = 0;
$tvrage_releases_start = 0;
$book_releases_start = 0;
$misc_releases_start = 0;
$nfo_start = 0;
$nfo_remaining_start = 0;
$console_releases_proc_start = 0;
$movie_releases_proc_start = 0;
$music_releases_proc_start = 0;
$pc_releases_proc_start = 0;
$tvrage_releases_proc_start = 0;
$book_releases_proc_start = 0;
$work_remaining_start = 0;
$misc_diff = 0;
$disk_use = 0;
$disk_free = 0;
$total_work_start = 0;

//formatted  output
$nfo_diff = number_format( $nfo_remaining_now - $nfo_remaining_start );
$console_diff = number_format( $console_releases_proc - $console_releases_proc_start );
$movie_diff = number_format( $movie_releases_proc - $movie_releases_proc_start );
$music_diff = number_format( $music_releases_proc - $music_releases_proc_start );
$pc_diff = number_format( $pc_releases_proc - $pc_releases_proc_start );
$tvrage_diff = number_format( $tvrage_releases_proc - $tvrage_releases_proc_start );
$book_diff = number_format( $book_releases_proc - $book_releases_proc_start );
$remaning_since_start = number_format( $work_remaining_now - $work_remaining_start );
$console_releases_proc_formatted = number_format( $console_releases_proc );
$movie_releases_proc_formatted = number_format( $movie_releases_proc );
$music_releases_proc_formatted = number_format( $music_releases_proc );
$pc_releases_proc_formatted = number_format( $pc_releases_proc );
$tvrage_releases_proc_formatted = number_format( $tvrage_releases_proc );
$misc_remaining_now_formatted = number_format( $work_remaining_now );
$book_releases_proc_formatted = number_format( $book_releases_proc );
$nfo_remaining_now_formatted = number_format( $nfo_remaining_now );
$nfo_now_formatted = number_format( $nfo_now );
$console_releases_now_formatted = number_format( $console_releases_now );
$movie_releases_now_formatted = number_format( $movie_releases_now );
$music_releases_now_formatted = number_format( $music_releases_now );
$pc_releases_now_formatted = number_format( $pc_releases_now );
$tvrage_releases_now_formatted = number_format( $tvrage_releases_now );
$book_releases_now_formatted = number_format( $book_releases_now );
$misc_releases_now_formatted = number_format( $misc_releases_now );

//create initial display
passthru('clear');
//printf("\033[1;31m  First insert:\033[0m ".relativeTime("$firstdate")."\n");
$mask1 = "\033[1;33m%-16s \033[38;5;214m%-44.44s \n";
$mask2 = "\033[1;33m%-16s \033[38;5;214m%-34.34s \n";
printf($mask2, "Monitor Running v$version: ", relativeTime("$time"));
printf($mask1, "Newest Release:", "$newestname");
printf($mask1, "Release Added:", relativeTime("$newestdate")."ago");

$mask = "%-15.15s %22.22s %22.22s\n";
printf("\033[1;33m\n");
printf($mask, "Category", "State", "Reason");
printf($mask, "====================", "====================", "====================");
printf("\033[38;5;214m");
printf($mask, "Binaries", "$binaries_state", "$binaries_reason");
printf($mask, "Backfill", "$backfill_state", "$backfill_reason");
printf($mask, "Import", "$import_state", "$import_reason");
printf($mask, "Parts", "$parts_size_gb", "$parts_rows rows");
if ( $array['RAMDISK_PATH'] != "" ) {
    printf($mask, "Ramdisk", "$disk_use", "$disk_free");
}

printf("\033[1;33m\n");
printf($mask, "Category", "In Process", "In Database");
printf($mask, "====================", "====================", "====================");
printf("\033[38;5;214m");
printf($mask, "NFO's","$nfo_remaining_now_formatted($nfo_diff)","$nfo_now_formatted($nfo_percent%)");
printf($mask, "Console(1000)","$console_releases_proc_formatted($console_diff)","$console_releases_now_formatted($console_percent%)");
printf($mask, "Movie(2000)","$movie_releases_proc_formatted($movie_diff)","$movie_releases_now_formatted($movie_percent%)");
printf($mask, "Audio(3000)","$music_releases_proc_formatted($music_diff)","$music_releases_now_formatted($music_percent%)");
printf($mask, "PC(4000)","$pc_releases_proc_formatted($pc_diff)","$pc_releases_now_formatted($pc_percent%)");
printf($mask, "TVShows(5000)","$tvrage_releases_proc_formatted($tvrage_diff)","$tvrage_releases_now_formatted($tvrage_percent%)");
printf($mask, "Books(7000)","$book_releases_proc_formatted($book_diff)","$book_releases_now_formatted($book_percent%)");
printf($mask, "Misc(8000)","$misc_remaining_now_formatted($misc_diff)","$misc_releases_now_formatted($misc_percent%)");
printf($mask, "Total", "$total_work_now_formatted($work_diff)", "$releases_now_formatted($releases_since_start)");

printf("\n\033[1;33m");
printf($mask, "Category", "Time", "Status");
printf($mask, "====================", "====================", "====================");
printf("\033[38;5;214m");
printf($mask, "Queries","$query_timer","queried");

$i = 1;
while( $i > 0 )
{

    //get microtime at start of loop
    $time_loop_start = microtime_float();

    $getdate = gmDate("Ymd");

    //commands for start/stop newzdash tracking
    $ds1 = "cd $_alienx && $_php tmux_to_newzdash.php";
    $ds2 = "started";
    $ds3 = "stopped";
    $ds4 = "killed";

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

    //refresh variables
    $path = dirname(__FILE__);
    $varnames = shell_exec("cat ".$path."/../config.sh | grep ^export | cut -d \= -f1 | awk '{print $2;}'");
    $varnames .= shell_exec("cat ".$path."/../defaults.sh | grep ^export | cut -d \= -f1 | awk '{print $2;}'");
    $vardata = shell_exec("cat ".$path."/../config.sh | grep ^export | cut -d \\\" -f2 | awk '{print $1;}'");
    $vardata .= shell_exec("cat ".$path."/../defaults.sh | grep ^export | cut -d \\\" -f2 | awk '{print $1;}'");
    $varnames = explode("\n", $varnames);
    $vardata = explode("\n", $vardata);
    $array = array_combine($varnames, $vardata);
    unset($array['']);

    //rename the seesion
    if ( $old_session =! $array['TMUX_SESSION'] ) {
        shell_exec("$_tmux rename-session -t $old_session {$array['TMUX_SESSION']}");
        sleep(5);
    }
    $old_session="{$array['TMUX_SESSION']}";

    //reset title
    printf("\033]0;{$array['TMUX_SESSION']}\007\003\n");

    //get valuses from $posted_date
    $posted_date_result = @$db->query($posted_date);
    if ( $posted_date_result[0]['adddate'] ) { $firstdate = $posted_date_result[0]['adddate']; }

    //initial query for total releases
    if (( $proc_result[0]['work'] != NULL ) && ( $work_start == 0 )) { $work_start = $proc_result[0]['work']; }
    if (( $proc_result[0]['releases'] ) && ( $releases_start == 0 )) { $releases_start = $proc_result[0]['releases']; }

    //get start values from $qry
    if ( $i == "1" ) 
    {
        if ( $proc_result[0]['nforemains'] != NULL ) { $nfo_remaining_start = $proc_result[0]['nforemains']; }
        if ( $proc_result[0]['console'] != NULL ) { $console_releases_proc_start = $proc_result[0]['console']; }
        if ( $proc_result[0]['movies'] != NULL ) { $movie_releases_proc_start = $proc_result[0]['movies']; }
        if ( $proc_result[0]['audio'] != NULL ) { $music_releases_proc_start = $proc_result[0]['audio']; }
        if ( $proc_result[0]['pc'] != NULL ) { $pc_releases_proc_start = $proc_result[0]['pc']; }
        if ( $proc_result[0]['tv'] != NULL ) { $tvrage_releases_proc_start = $proc_result[0]['tv']; }
        if ( $proc_result[0]['book'] != NULL ) { $book_releases_proc_start = $proc_result[0]['book']; }
        if ( $proc_result[0]['work'] != NULL ) { $work_remaining_start = $proc_result[0]['work']; }
    }

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
    if ( $proc_result[0]['console'] != NULL ) { $console_releases_proc_formatted = number_format($proc_result[0]['console']); }
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
    if ( $proc_result[0]['releases'] ) { $releases_now_formatted = number_format($proc_result[0]['releases']); }
    if ( $proc_result[0]['newestaddname'] ) { $newestname = $proc_result[0]['newestaddname']; }
    if ( $proc_result[0]['newestadd'] ) { $newestdate = $proc_result[0]['newestadd']; }

    //calculate releases difference
    $releases_misc_diff = number_format( $releases_now - $releases_start );
    $releases_since_start = number_format( $releases_now - $releases_start );
    $work_misc_diff = $work_remaining_now - $work_remaining_start;

    $total_work_now = $work_remaining_now + $tvrage_releases_proc + $music_releases_proc + $movie_releases_proc + $console_releases_proc + $book_releases_proc + $nfo_remaining_now;
    if ( $i == 1 ) { $total_work_start = $total_work_now; }
    $total_work_now_formatted = number_format($total_work_now);

    $nfo_diff = number_format( $nfo_remaining_now - $nfo_remaining_start );
    $console_diff = number_format( $console_releases_proc - $console_releases_proc_start );
    $movie_diff = number_format( $movie_releases_proc - $movie_releases_proc_start );
    $music_diff = number_format( $music_releases_proc - $music_releases_proc_start );
    $pc_diff = number_format( $pc_releases_proc - $pc_releases_proc_start );
    $tvrage_diff = number_format( $tvrage_releases_proc - $tvrage_releases_proc_start );
    $book_diff = number_format( $book_releases_proc - $book_releases_proc_start );

    //formatted  output
    $console_releases_proc_formatted = number_format( $console_releases_proc );
    $movie_releases_proc_formatted = number_format( $movie_releases_proc );
    $music_releases_proc_formatted = number_format( $music_releases_proc );
    $pc_releases_proc_formatted = number_format( $pc_releases_proc );
    $tvrage_releases_proc_formatted = number_format( $tvrage_releases_proc );
    $misc_remaining_now_formatted = number_format( $work_remaining_now );
    $book_releases_proc_formatted = number_format( $book_releases_proc );
    $nfo_remaining_now_formatted = number_format( $nfo_remaining_now );
    $nfo_now_formatted = number_format( $nfo_now );
    $console_releases_now_formatted = number_format( $console_releases_now );
    $movie_releases_now_formatted = number_format( $movie_releases_now );
    $music_releases_now_formatted = number_format( $music_releases_now );
    $pc_releases_now_formatted = number_format( $pc_releases_now );
    $tvrage_releases_now_formatted = number_format( $tvrage_releases_now );
    $book_releases_now_formatted = number_format( $book_releases_now );
    $misc_releases_now_formatted = number_format( $misc_releases_now );
    $misc_diff = number_format( $work_remaining_now - $work_start );

    $work_since_start = ( $total_work_now - $total_work_start );
    $work_diff = number_format($work_since_start);

    //get microtime at end of queries
    $query_timer = microtime_float()-$query_timer_start;

    //add sleep to new installs, so everything spins up properly
    if (( $query_timer < 10 ) && ( $i == 1 )) { sleep(10); }

    if ( $array['RAMDISK_PATH'] != "" ) {
        $disk_use =  decodeSize( disk_total_space("${array['RAMDISK_PATH']}") - disk_free_space("${array['RAMDISK_PATH']}") );
        $disk_free = decodeSize( disk_free_space("${array['RAMDISK_PATH']}") );
    }

    if ( $releases_now != 0 ) {
        $nfo_percent = sprintf( "%02s", floor(( $nfo_now / $releases_now) * 100 ));
        $console_percent = sprintf( "%02s", floor(( $console_releases_now / $releases_now) * 100 ));
        $movie_percent = sprintf( "%02s", floor(( $movie_releases_now / $releases_now) * 100 ));
        $music_percent = sprintf( "%02s", floor(( $music_releases_now / $releases_now) * 100 ));
        $pc_percent = sprintf( "%02s", floor(( $pc_releases_now / $releases_now) * 100 ));
        $tvrage_percent = sprintf( "%02s", floor(( $tvrage_releases_now / $releases_now) * 100 ));
        $book_percent = sprintf( "%02s", floor(( $book_releases_now / $releases_now) * 100 ));
        $misc_percent = sprintf( "%02s", floor(( $misc_releases_now / $releases_now) * 100 ));
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

    if ( $array['MISC_ONLY'] == "true" ) {
        $total_work_used = $work_remaining_now;
    } else {
        $total_work_used = $total_work_now;
    }

    $panes_win_1 = shell_exec("echo `tmux list-panes -t  {$array['TMUX_SESSION']}:0 -F '#{pane_title}'`");
    $panes_win_2 = shell_exec("echo `tmux list-panes -t  {$array['TMUX_SESSION']}:1 -F '#{pane_title}'`");
    $panes_win_3 = shell_exec("echo `tmux list-panes -t  {$array['TMUX_SESSION']}:2 -F '#{pane_title}'`");
    $panes_win_4 = shell_exec("echo `tmux list-panes -t  {$array['TMUX_SESSION']}:3 -F '#{pane_title}'`");

    $panes0 = str_replace("\n", '', explode(" ", $panes_win_1));
    $panes1 = str_replace("\n", '', explode(" ", $panes_win_2));
    $panes2 = str_replace("\n", '', explode(" ", $panes_win_3));
    $panes3 = str_replace("\n", '', explode(" ", $panes_win_4));

    //kill update_binaries.php backfill.php and import-nzb if timer exceeded
    $killit=explode(" ", relativeTime("$newestdate"));
    $killed="false";
    if ( $array['KILL_UPDATES'] != "0" ) {
        if ((( $killit[1] != "secs" ) && ( $killit[1] != "sec" )) && (( $killit[1] == "hrs" ) || ( $killit[1] == "hr" ) || ( $killit[0] >= $array['KILL_UPDATES'] )) && ( $i % 5 == 0 )) {
            $color = get_color();
            shell_exec("$_tmux respawnp -k -t {$array['TMUX_SESSION']}:0.2 'echo \"\033[38;5;\"$color\"m\n$panes0[2] Killed by KILL_UPDATES\"' 2>&1 1> /dev/null");
            $color = get_color();
            shell_exec("$_tmux respawnp -k -t {$array['TMUX_SESSION']}:0.3 'echo \"\033[38;5;\"$color\"m\n$panes0[3] Killed by KILL_UPDATES\"' 2>&1 1> /dev/null");
            $color = get_color();
            shell_exec("$_tmux respawnp -k -t {$array['TMUX_SESSION']}:0.4 'echo \"\033[38;5;\"$color\"m\n$panes0[4] Killed by KILL_UPDATES\"' 2>&1 1> /dev/null");
            if ( $array['KEEP_KILLED'] == "true" ) {
                $killed="true";
            }
        }
    }

    //get state for binaries
    if ( $array['BINARIES'] != "true" ) {
        $binaries_state="disabled";
        $binaries_reason="disabled";
    } elseif (( $total_work_used > $array['BINARIES_MAX_RELEASES'] ) && ( $array['BINARIES_MAX_RELEASES'] != 0 )) {
        $binaries_state="pp-exceeded";
        $binaries_reason=number_format("${array['BINARIES_MAX_RELEASES']}");
    } elseif (( $parts_rows_unformated > $array['BINARIES_MAX_ROWS'] ) && ( $array['BINARIES_MAX_ROWS'] != 0 )) {
        $binaries_state="rows-exceeded";
        $binaries_reason=number_format("${array['BINARIES_MAX_ROWS']}");
    } elseif ( $killed == "true" ) {
        $binaries_state="time-exceeded";
        $binaries_reason=$array['KILL_UPDATES']." mins";
    } else {
        $binaries_state="enabled";
        $binaries_reason="enabled";
    }

    //get state for backfill
    if ( $array['BACKFILL'] != "true" ) {
        $backfill_state="disabled";
        $backfill_reason="disabled";
    } elseif (( $total_work_used > $array['BACKFILL_MAX_RELEASES'] ) && ( $array['BACKFILL_MAX_RELEASES'] != 0 )) {
        $backfill_state="pp-exceeded";
        $backfill_reason=number_format("${array['BACKFILL_MAX_RELEASES']}");
    } elseif (( $parts_rows_unformated > $array['BACKFILL_MAX_ROWS'] ) && ( $array['BACKFILL_MAX_ROWS'] != 0 )) {
        $backfill_state="rows-exceeded";
        $backfill_reason=number_format("${array['BACKFILL_MAX_ROWS']}");
    } elseif ( $killed == "true" ) {
        $backfill_state="time-exceeded";
        $backfill_reason=$array['KILL_UPDATES']." mins";
    } else {
        $backfill_state="enabled";
        $backfill_reason="enabled";
    }

    //get state for import
    if ( $array['IMPORT'] != "true" ) {
        $import_state="disabled";
        $import_reason="disabled";
    } elseif (( $total_work_used > $array['IMPORT_MAX_RELEASES'] ) && ( $array['IMPORT_MAX_RELEASES'] != 0 )) {
        $import_state="pp-exceeded";
        $import_reason=number_format("${array['IMPORT_MAX_RELEASES']}");
    } elseif (( $parts_rows_unformated > $array['IMPORT_MAX_ROWS'] ) && ( $array['IMPORT_MAX_ROWS'] != 0 )) {
        $import_state="rows-exceeded";
        $import_reason=number_format("${array['IMPORT_MAX_ROWS']}");
    } elseif ( $killed == "true" ) {
        $import_state="time-exceeded";
        $import_reason=$array['KILL_UPDATES']." mins";
    } else {
        $import_state="enabled";
        $import_reason="enabled";
    }

    //update display
    passthru('clear');
    //printf("\033[1;31m  First insert:\033[0m ".relativeTime("$firstdate")."\n");
    printf($mask2, "Monitor Running v$version: ", relativeTime("$time"));
    printf($mask1, "Newest Release:", "$newestname");
    printf($mask1, "Release Added:", relativeTime("$newestdate")."ago");

    printf("\033[1;33m\n");
    printf($mask, "Category", "State", "Reason");
    printf($mask, "====================", "====================", "====================");
    printf("\033[38;5;214m");
    printf($mask, "Binaries", "$binaries_state", "$binaries_reason");
    printf($mask, "Backfill", "$backfill_state", "$backfill_reason");
    printf($mask, "Import", "$import_state", "$import_reason");
    printf($mask, "Parts", "$parts_size_gb", "$parts_rows rows");
    if ( $array['RAMDISK_PATH'] != "" ) {
        printf($mask, "Ramdisk", "$disk_use used", "$disk_free free");
    }

    printf("\033[1;33m\n");
    printf($mask, "Category", "In Process", "In Database");
    printf($mask, "====================", "====================", "====================");
    printf("\033[38;5;214m");
    printf($mask, "NFO's","$nfo_remaining_now_formatted($nfo_diff)","$nfo_now_formatted($nfo_percent%)");
    printf($mask, "Console(1000)","$console_releases_proc_formatted($console_diff)","$console_releases_now_formatted($console_percent%)");
    printf($mask, "Movie(2000)","$movie_releases_proc_formatted($movie_diff)","$movie_releases_now_formatted($movie_percent%)");
    printf($mask, "Audio(3000)","$music_releases_proc_formatted($music_diff)","$music_releases_now_formatted($music_percent%)");
    printf($mask, "PC(4000)","$pc_releases_proc_formatted($pc_diff)","$pc_releases_now_formatted($pc_percent%)");
    printf($mask, "TVShows(5000)","$tvrage_releases_proc_formatted($tvrage_diff)","$tvrage_releases_now_formatted($tvrage_percent%)");
    printf($mask, "Books(7000)","$book_releases_proc_formatted($book_diff)","$book_releases_now_formatted($book_percent%)");
    printf($mask, "Misc(8000)","$misc_remaining_now_formatted($misc_diff)","$misc_releases_now_formatted($misc_percent%)");
    printf($mask, "Total", "$total_work_now_formatted($work_diff)", "$releases_now_formatted($releases_since_start)");

    printf("\n\033[1;33m");
    printf($mask, "Category", "Time", "Status");
    printf($mask, "====================", "====================", "====================");
    printf("\033[38;5;214m");
    printf($mask, "Queries","$query_timer","queried");

    //see if tmux.conf needs to be reloaded
    if ( $_tmux_test != $array['POWERLINE'] ) {
        if ( $array['POWERLINE'] == "true" ) {
            shell_exec("$_tmux source-file powerline/tmux.conf");
        } else {
            shell_exec("$_tmux source-file conf/tmux.conf");
        }
        $_tmux_test = $array['POWERLINE'];
    }

    $forcekill="false";
    $optimize_safe_to_run="false";
    if (( $array['OPTIMIZE_KILL'] == "true" ) && ( $array['OPTIMIZE'] == "true" )) {
        $forcekill="true";
    }

    //kill all panes to run optimize if OPTIMIZE_KILL is true
    if ((( TIME() - $time6 >= $array['MYISAM_LARGE'] ) || ( TIME() - $time8 >= $array['INNODB_LARGE'] ) || ( TIME() - $time5 >= $array['INNODB_SMALL'] ) || ( TIME() - $time11 >= $array['MYISAM_SMALL'] )) && ( $forcekill == "false")) {
        $dead1 = str_replace( " ", '', `tmux list-panes -t {$array['TMUX_SESSION']}:0 | grep dead | wc -l` );
        $dead2 = str_replace( " ", '', `tmux list-panes -t {$array['TMUX_SESSION']}:1 | grep dead | wc -l` );
        $dead3 = str_replace( " ", '', `tmux list-panes -t {$array['TMUX_SESSION']}:2 | grep dead | wc -l` );
        $dead4 = str_replace( " ", '', `tmux list-panes -t {$array['TMUX_SESSION']}:3 | grep dead | wc -l` );
        if (( $dead1 == 5 ) && ( $dead2 == 8 ) && ( $dead3 == 32 ) && ( $dead4 == 8 )) {
            $optimize_safe_to_run="true";
        } else {
            $optimize_safe_to_run="false";
        }
    } elseif ((( TIME() - $time6 >= $array['MYISAM_LARGE'] ) || ( TIME() - $time8 >= $array['INNODB_LARGE'] ) || ( TIME() - $time5 >= $array['INNODB_SMALL'] ) || ( TIME() - $time11 >= $array['MYISAM_SMALL'] )) && ( $forcekill == "true")) {
        for ($g=1; $g<=5; $g++)
        {
            $color = get_color();
            shell_exec("$_tmux respawnp -k -t {$array['TMUX_SESSION']}:0.$g 'echo \"\033[38;5;\"$color\"m\n$panes0[$g]\nKilled in prep for \nOptimization\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
        }
        for ($g=0; $g<=7; $g++)
        {
            $color = get_color();
            shell_exec("$_tmux respawnp -k -t {$array['TMUX_SESSION']}:1.$g 'echo \"\033[38;5;\"$color\"m\n$panes1[$g]\nKilled in prep for \nOptimization\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
            $color = get_color();
            shell_exec("$_tmux respawnp -k -t {$array['TMUX_SESSION']}:3.$g 'echo \"\033[38;5;\"$color\"m\n$panes3[$g]\nKilled in prep for \nOptimization\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
        }
        for ($g=0; $g<=32; $g++)
        {
            $color = get_color();
            shell_exec("$_tmux respawnp -k -t {$array['TMUX_SESSION']}:0.$g 'echo \"\033[38;5;\"$color\"m\n$panes0[$g]\nKilled in prep for \nOptimization\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
        }
        sleep(10);
        $optimize_safe_to_run="true";
    }

    //run optimize in pane 1.4
    if (( TIME() - $time6 >= $array['MYISAM_LARGE'] ) && ( $array['OPTIMIZE'] == "true" ) && ( $optimize_safe_to_run == "true" )) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.4 'echo \"\033[38;5;\"$color\"m\" && $ds1 MYISAM_LARGE $ds2 && cd $_bin && $_php optimize_myisam.php true 2>&1 | tee -a $path/../logs/$panes1[4]-$getdate.log && echo \" \033[1;0;33m\" && $ds1 MYISAM_LARGE $ds3' 2>&1 1> /dev/null");
        $time6 = TIME();
    } elseif (( TIME() - $time8 >= $array['INNODB_LARGE'] ) && ($array['INNODB'] == "true") && ( $array['OPTIMIZE'] == "true" ) && ( $optimize_safe_to_run == "true" )) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.4 'echo \"\033[38;5;\"$color\"m\" && $ds1 INNODB_LARGE $ds2 && cd $_bin && $_php optimize_myisam.php true 2>&1 | tee -a $path/../logs/$panes1[4]-$getdate.log && $_php optimize_innodb.php true 2>&1 | tee -a $path/../logs/$panes1[4]-$getdate.log && echo \" \033[1;0;33m\" && $ds1 INNODB_LARGE $ds3' 2>&1 1> /dev/null");
        $time8 = TIME();
    } elseif (( TIME() - $time5 >= $array['INNODB_SMALL'] ) && ( $array['INNODB']== "true" ) && ( $array['OPTIMIZE'] == "true" ) && ( $optimize_safe_to_run == "true" )) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.4 'echo \"\033[38;5;\"$color\"m\" && $ds1 INNODB_SMALL $ds2 && cd $_bin && $_php optimize_innodb.php 2>&1 | tee -a $path/../logs/$panes1[4]-$getdate.log && echo \" \033[1;0;33m\" && $ds1 INNODB_SMALL $ds3' 2>&1 1> /dev/null");
        $time5 = TIME();
    } elseif (( TIME() - $time11 >= $array['MYISAM_SMALL'] ) && ( $array['OPTIMIZE'] == "true" ) && ( $optimize_safe_to_run == "true" )) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.4 'echo \"\033[38;5;\"$color\"m\" && $ds1 MYISAM_SMALL $ds2 && cd $_bin && $_php optimize_myisam.php 2>&1 | tee -a $path/../logs/$panes1[4]-$getdate.log && echo \" \033[1;0;33m\" && $ds1 MYISAM_SMALL $ds3' 2>&1 1> /dev/null");
        $time11 = TIME();
    } elseif ( $array['OPTIMIZE'] != "true" ) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.4 'echo \"\033[38;5;\"$color\"m\nOptimize Disabled by OPTIMIZE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
    } else {
        $color = get_color();
        $run_time1 = relativeTime( $array['MYISAM_LARGE'] + $time6 );
        $run_time2 = relativeTime( $array['INNODB_LARGE'] + $time8 );
        $run_time3 = relativeTime( $array['INNODB_SMALL'] + $time5 );
        $run_time4 = relativeTime( $array['MYISAM_SMALL'] + $time11 );
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.4 'echo \"\033[38;5;\"$color\"m\nMYISAM_LARGE will run in T[$run_time1]\nINNODB_LARGE will run in T[$run_time2]\nINNODB_SMALL will run in T[$run_time3]\nMYISAM_SMALL will run in T[$run_time4]\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
    }

//    if (( shell_exec("$_tmux list-panes -t {$array['TMUX_SESSION']}:1 | grep 4: | grep dead")) && ( $array['OPTIMIZE'] == "true" )) {
//        $optimize_safe_to_run="true";
//    } else {
//        $optimize_safe_to_run="false";
//    }

    //run update_predb.php in 1.0 ever 15 minutes and on first loop
    if (((( TIME() - $time2 ) >= $array['PREDB_TIMER'] ) || ( $i == 1 )) && ( $array['PREDB'] == "true" ) && ( $optimize_safe_to_run != "true" )) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.0 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes1[0] $ds2 && cd $NNPATH && $_php update_predb.php true 2>&1 | tee -a $path/../logs/$panes1[0]-$getdate.log && echo \" \033[1;0;33m\" && $ds1 $panes1[0] $ds3' 2>&1 1> /dev/null");
        $time2 = TIME();
    } elseif (( $array['PREDB'] == "true" ) && ( $optimize_safe_to_run != "true" )) {
        $color = get_color();
        $run_time = relativeTime( $array['PREDB_TIMER'] + $time2 );
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.0 'echo \"\033[38;5;\"$color\"m\n$panes1[0] will run in T[$run_time]\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
    } elseif (( $array['PREDB'] != "true" ) && ( $optimize_safe_to_run != "true" )) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.0 'echo \"\033[38;5;\"$color\"m\n$panes1[0] Disabled by PREDB\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
    } elseif ( $optimize_safe_to_run == "true" ) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.0 'echo \"\033[38;5;\"$color\"m\n$panes1[0] Disabled by OPTIMIZE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
    }


    //run $_php update_parsing.php in 1.1 every 1 hour and on first loop
    if (((( TIME() - $time3 ) >= $array['PARSING_TIMER'] ) || ( $i == 1 )) && ($array['PARSING_MOD'] != "true" )&& ($array['PARSING'] == "true" )  && ( $optimize_safe_to_run != "true" )) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.1 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes1[1] $ds2 && cd $TESTING && $_php update_parsing.php 2>&1 | tee -a $path/../logs/$panes1[1]-$getdate.log && echo \" \033[1;0;33m\" && $ds1 $panes1[1] $ds3' 2>&1 1> /dev/null");
        $time3 = TIME();
    } elseif (((( TIME() - $time3 ) >= $array['PARSING_TIMER'] ) || ( $i == 1 )) && ( $array['PARSING_MOD'] == "true" ) && ( $array['PARSING'] == "true" )  && ( $optimize_safe_to_run != "true" )) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.1 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes1[1] $ds2 && cd $_bin && $_php update_parsing.php 2>&1 | tee -a $path/../logs/$panes1[1]-$getdate.log && echo \" \033[1;0;33m\" && $ds1 $panes1[1] $ds3' 2>&1 1> /dev/null");
        $time3 = TIME();
    }  elseif (( $array['PARSING'] != "true" ) && ( $optimize_safe_to_run != "true" )) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.1 'echo \"\033[38;5;\"$color\"m\n$panes1[1] Disabled by PARSING\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
    } elseif ( $optimize_safe_to_run != "true" ) {
        $color = get_color();
        $run_time = relativeTime( $array['PARSING_TIMER'] + $time3 );
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.1 'echo \"\033[38;5;\"$color\"m\n$panes1[1] will run in T[$run_time]\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
    } elseif ( $optimize_safe_to_run == "true" ) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.1 'echo \"\033[38;5;\"$color\"m\n$panes1[1] Disabled by OPTIMIZE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
    }

    //run $_php removespecial.php and $_php update_cleanup.php in 1.2 ever 1 hour
    if ((( TIME() - $time7 ) >= $array['CLEANUP_TIMER'] ) && ($array['CLEANUP'] == "true" ) && ( $optimize_safe_to_run != "true" )) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.2 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes1[2] $ds2 && cd $TESTING && $_php removespecial.php 2>&1 | tee -a $path/../logs/$panes1[2]-$getdate.log && $_php update_cleanup.php 2>&1 | tee -a $path/../logs/$panes1[2]-$getdate.log && echo \" \033[1;0;33m\" && $ds1 $panes1[2] $ds3' 2>&1 1> /dev/null");
        $time7 = TIME();
    } elseif (( $array['CLEANUP'] != "true" ) && ( $optimize_safe_to_run != "true" )) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.2 'echo \"\033[38;5;\"$color\"m\n$panes1[2] Disabled by CLEANUP\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
    } elseif ( $optimize_safe_to_run != "true" ) {
        $color = get_color();
        $run_time = relativeTime( $array['CLEANUP_TIMER'] + $time7 );
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.2 'echo \"\033[38;5;\"$color\"m\n$panes1[2] will run in T[$run_time]\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
    } elseif ( $optimize_safe_to_run == "true" ) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.2 'echo \"\033[38;5;\"$color\"m\n$panes1[2] Disabled by OPTIMIZE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
    }

    //run update_tvschedule.php and $_php update_theaters.php in 1.3 every 12 hours and first loop
    if (((( TIME() - $time4 ) >= $array['TVRAGE_TIMER']) || ( $i == 1 )) && ( $array['TV_SCHEDULE'] == "true") && ( $optimize_safe_to_run != "true" )) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.3 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes1[3] $ds2 && cd $NNPATH && $_php update_tvschedule.php 2>&1 | tee -a $path/../logs/$panes1[3]-$getdate.log && $_php update_theaters.php 2>&1 | tee -a $path/../logs/$panes1[3]-$getdate.log && echo \" \033[1;0;33m\" && $ds1 $panes1[3] $ds3' 2>&1 1> /dev/null");
        $time4 = TIME();
    } elseif (( $array['TV_SCHEDULE'] == "true") && ( $optimize_safe_to_run != "true" )) {
        $color = get_color();
        $run_time = relativeTime( $array['TVRAGE_TIMER'] + $time4 );
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.3 'echo \"\033[38;5;\"$color\"m\n$panes1[3] and update_theaters will run in T[$run_time]\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
    } elseif (( $array['TV_SCHEDULE'] != "true") && ( $optimize_safe_to_run != "true" )) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.3 'echo \"\033[38;5;\"$color\"m\n$panes1[3] Disabled by TV_SCHEDULE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
    } elseif ( $optimize_safe_to_run == "true" ) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.3 'echo \"\033[38;5;\"$color\"m\n$panes1[3] Disabled by OPTIMIZE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
    }

    //run sphinx in pane 1.5
    if (( TIME() - $time9 >= $array['SPHINX_TIMER'] ) && ( $array['SPHINX'] == "true") && ( $optimize_safe_to_run != "true" )) {
        $color=get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.5 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes1[5] $ds2 && cd $_bin && $_php sphinx.php 2>&1 | tee -a $path/../logs/$panes1[5]-$getdate.log && echo \" \033[1;0;33m\" && $ds1 $panes1[5] $ds3' 2>&1 1> /dev/null");
        $time9 = TIME();
    } elseif (( $array['SPHINX'] != "true" ) && ( $optimize_safe_to_run != "true" )) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.5 'echo \"\033[38;5;\"$color\"m\n$panes1[5] Disabled by SPHINX\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
    } elseif ( $optimize_safe_to_run != "true" ) {
        $color = get_color();
        $run_time = relativeTime( $array['SPHINX_TIMER'] + $time9 );
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.5 'echo \"\033[38;5;\"$color\"m\n$panes1[5] will run in T[$run_time]\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
    } elseif ( $optimize_safe_to_run == "true" ) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.5 'echo \"\033[38;5;\"$color\"m\n$panes1[5] Disabled by OPTIMIZE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
    }

    //run delete parts in pane 1.6
    if ((( TIME() - $time16 ) >= $array['DELETE_TIMER'] ) && ( $array['DELETE_PARTS'] == "true" ) && ( $optimize_safe_to_run != "true" )) {
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.6 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes1[6] $ds2 && cd $_cj && $_php remove_parts_without_releases.php 2>&1 | tee -a $path/../logs/$panes1[6]-$getdate.log && $ds1 $panes1[6] $ds3' 2>&1 1> /dev/null");
        $time16 = TIME();
    } elseif (( $array['DELETE_PARTS'] != "true" ) && ( $optimize_safe_to_run != "true" )) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.6 'echo \"\033[38;5;\"$color\"m\n$panes1[6] Disabled by DELETE_PARTS\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
    } elseif ( $optimize_safe_to_run != "true" ) {
        $color = get_color();
        $run_time = relativeTime( $array['DELETE_TIMER'] + $time16 );
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.6 'echo \"\033[38;5;\"$color\"m\n$panes1[6] will run in T[$run_time]\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
    } elseif ( $optimize_safe_to_run == "true" ) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.6 'echo \"\033[38;5;\"$color\"m\n$panes1[6] Disabled by OPTIMIZE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
    }

    //run update_missing_movie_info  parts in pane 1.7
    if (((( TIME() - $time17 ) >= $array['MOVIE_TIMER'] ) || ( $i == 1 )) && ( $array['FETCH_MOVIE'] == "true" ) && ( $optimize_safe_to_run != "true" )) {
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.7 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes1[7] $ds2 && cd $_cj && $_php update_missing_movie_info.php 2>&1 | tee -a $path/../logs/$panes1[7]-$getdate.log && $ds1 $panes1[7] $ds3' 2>&1 1> /dev/null");
        $time17 = TIME();
    } elseif (( $array['FETCH_MOVIE'] != "true" ) && ( $optimize_safe_to_run != "true" )) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.7 'echo \"\033[38;5;\"$color\"m\n$panes1[7] Disabled by FETCH_MOVIE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
    } elseif ( $optimize_safe_to_run != "true" ) {
        $color = get_color();
        $run_time = relativeTime( $array['MOVIE_TIMER'] + $time17 );
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.7 'echo \"\033[38;5;\"$color\"m\n$panes1[7] will run in T[$run_time]\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
    } elseif ( $optimize_safe_to_run == "true" ) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.7 'echo \"\033[38;5;\"$color\"m\n$panes1[7] Disabled by OPTIMIZE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
    }

    //runs postprocess_nfo.php in pane 3.0 once if needed then exits
    if (( $nfo_remaining_now > 0 ) && ( $array['POST_TO_RUN'] != 0 ) && ( $optimize_safe_to_run != "true" )) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.0 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes3[0] $ds2 && cd $_bin && $_php postprocess_nfo.php 2>&1 | tee -a $path/../logs/$panes3[0]-$getdate.log && echo \" \033[1;0;33m\" && $ds1 $panes3[0] $ds3' 2>&1 1> /dev/null");
    } elseif (( $array['POST_TO_RUN'] == 0 ) && ( $optimize_safe_to_run != "true" )) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.0 'echo \"\033[38;5;\"$color\"m\n$panes3[0] Disabled by POST_TO_RUN\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
    } elseif ( $optimize_safe_to_run != "true" ) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.0 'echo \"\033[38;5;\"$color\"m\n$panes3[0] Has no work to process \" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
    } elseif ( $optimize_safe_to_run == "true" ) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.0 'echo \"\033[38;5;\"$color\"m\n$panes3[0] Disabled by OPTIMIZE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
    }

    //runs processGames.php in pane y3.1 once if needed then exits
    if (( $console_releases_proc > 0 ) && ( $array['POST_TO_RUN'] != 0 ) && ( $optimize_safe_to_run != "true" )) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.1 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes3[1] $ds2 && cd $_bin && $_php processGames.php 2>&1 | tee -a $path/../logs/$panes3[1]-$getdate.log && echo \" \033[1;0;33m\" && $ds1 $panes3[1] $ds3' 2>&1 1> /dev/null");
    } elseif (( $array['POST_TO_RUN'] == 0 ) && ( $optimize_safe_to_run != "true" )) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.1 'echo \"\033[38;5;\"$color\"m\n$panes3[1] Disabled by POST_TO_RUN\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
    } elseif ( $optimize_safe_to_run != "true" ) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.1 'echo \"\033[38;5;\"$color\"m\n$panes3[1] Has no work to process \" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
    } elseif ( $optimize_safe_to_run == "true" ) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.1 'echo \"\033[38;5;\"$color\"m\n$panes3[1] Disabled by OPTIMIZE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
    }

    //runs processMovies.php in pane 3.2 once if needed then exits
    if (( $movie_releases_proc > 0 ) && ( $array['POST_TO_RUN'] != 0 ) && ( $optimize_safe_to_run != "true" )) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.2 'echo \"\033[38;5;\"$color\"\" && $ds1 $panes3[2] $ds2 && cd $_bin && $_php processMovies.php 2>&1 | tee -a $path/../logs/$panes3[2]-$getdate.log && echo \" \033[1;0;33m\" && $ds1 $panes3[2] $ds3' 2>&1 1> /dev/null");
    } elseif (( $array['POST_TO_RUN'] == 0 ) && ( $optimize_safe_to_run != "true" )) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.2 'echo \"\033[38;5;\"$color\"m\n$panes3[2] Disabled by POST_TO_RUN\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
    } elseif ( $optimize_safe_to_run != "true" ) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.2 'echo \"\033[38;5;\"$color\"m\n$panes3[2] Has no work to process \" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
    } elseif ( $optimize_safe_to_run == "true" ) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.2 'echo \"\033[38;5;\"$color\"m\n$panes3[2] Disabled by OPTIMIZE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
    }

    //runs processMusic.php in pane 3.3 once if needed then exits
    if (( $music_releases_proc > 0 ) && ( $array['POST_TO_RUN'] != 0 ) && ( $optimize_safe_to_run != "true" )) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.3 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes3[3] $ds2 && cd $_bin && $_php processMusic.php 2>&1 | tee -a $path/../logs/$panes3[3]-$getdate.log && echo \" \033[1;0;33m\" && $ds1 $panes3[3] $ds3' 2>&1 1> /dev/null");
    } elseif (( $array['POST_TO_RUN'] == 0 ) && ( $optimize_safe_to_run != "true" )) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.3 'echo \"\033[38;5;\"$color\"m\n$panes3[3] Disabled by POST_TO_RUN\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
    } elseif ( $optimize_safe_to_run != "true" ) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.3 'echo \"\033[38;5;\"$color\"m\n$panes3[3] Has no work to process \" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
    } elseif ( $optimize_safe_to_run == "true" ) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.3 'echo \"\033[38;5;\"$color\"m\n$panes3[3] Disabled by OPTIMIZE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
    }

    //runs processTv.php in pane 3.4 once if needed then exits
    if (( $tvrage_releases_proc > 0 ) && ( $array['POST_TO_RUN'] != 0 ) && ( $optimize_safe_to_run != "true" )) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.4 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes3[4] $ds2 && cd $_bin && $_php processTv.php 2>&1 | tee -a $path/../logs/$panes3[4]-$getdate.log && echo \" \033[1;0;33m\" && $ds1 $panes3[4] $ds3' 2>&1 1> /dev/null");
    } elseif (( $array['POST_TO_RUN'] == 0 ) && ( $optimize_safe_to_run != "true" )) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.4 'echo \"\033[38;5;\"$color\"m\n$panes3[4] Disabled by POST_TO_RUN\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
    } elseif ( $optimize_safe_to_run != "true" ) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.4 'echo \"\033[38;5;\"$color\"m\n$panes3[4] Has no work to process \" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
    } elseif ( $optimize_safe_to_run == "true" ) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.4 'echo \"\033[38;5;\"$color\"m\n$panes3[4] Disabled by OPTIMIZE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
    }

    //runs processBooks.php in pane 3.5 once if needed then exits
    if (( $book_releases_proc > 0 ) && ( $array['POST_TO_RUN'] != 0 ) && ( $optimize_safe_to_run != "true" )) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.5 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes3[5] $ds2 && cd $_bin && $_php processBooks.php 2>&1 | tee -a $path/../logs/$panes3[5]-$getdate.log && echo \" \033[1;0;33m\" && $ds1 $panes3[5] $ds3' 2>&1 1> /dev/null");
    } elseif (( $array['POST_TO_RUN'] == 0 ) && ( $optimize_safe_to_run != "true" )) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.5 'echo \"\033[38;5;\"$color\"m\n$panes3[5] Disabled by POST_TO_RUN\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
    } elseif ( $optimize_safe_to_run != "true" ) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.5 'echo \"\033[38;5;\"$color\"m\n$panes3[5] Has no work to process \" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
    } elseif ( $optimize_safe_to_run == "true" ) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.5 'echo \"\033[38;5;\"$color\"m\n$panes3[5] Disabled by OPTIMIZE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
    }

    //runs processOthers.php in pane 3.6 once if needed then exits
    if  (( $array['POST_TO_RUN'] != 0 ) && ( $optimize_safe_to_run != "true" )) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.6 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes3[6] $ds2 && cd $_bin && $_php processOthers.php 2>&1 | tee -a $path/../logs/$panes3[6]-$getdate.log && echo \" \033[1;0;33m\" && $ds1 $panes3[6] $ds3' 2>&1 1> /dev/null");
    } elseif (( $array['POST_TO_RUN'] == 0 ) && ( $optimize_safe_to_run != "true" )) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.6 'echo \"\033[38;5;\"$color\"m\n$panes3[6] Disabled by POST_TO_RUN\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
    } elseif ( $optimize_safe_to_run != "true" ) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.6 'echo \"\033[38;5;\"$color\"m\n$panes3[6] Has no work to process \" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
    } elseif ( $optimize_safe_to_run == "true" ) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.6 'echo \"\033[38;5;\"$color\"m\n$panes3[6] Disabled by OPTIMIZE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
    }

    //runs processUnwanted.php in pane 3.7 in continuous loop, will restart if exits
    if  (( $array['POST_TO_RUN'] != 0 ) && ( $optimize_safe_to_run != "true" )) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.7 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes3[7] $ds2 && cd $_bin && $_php processUnwanted.php 2>&1 | tee -a $path/../logs/$panes3[7]-$getdate.log && echo \" \033[1;0;33;33m\" && $ds1 $panes3[7] $ds3' 2>&1 1> /dev/null");
    } elseif (( $array['POST_TO_RUN'] == 0 ) && ( $optimize_safe_to_run != "true" )) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.7 'echo \"\033[38;5;\"$color\"m\n$panes3[7] Disabled by POST_TO_RUN\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
    } elseif ( $optimize_safe_to_run != "true" ) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.7 'echo \"\033[38;5;\"$color\"m\n$panes3[7] Has no work to process \" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
    } elseif ( $optimize_safe_to_run == "true" ) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.7 'echo \"\033[38;5;\"$color\"m\n$panes3[7] Disabled by OPTIMIZE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
    }

    //set command for running update_binaries
    if (( $array['BINARIES_THREADS'] == "true" ) && ( $optimize_safe_to_run != "true" )) {
        $_update_cmd = "cd $_bin && $_php update_binaries_threaded.php 2>&1 | tee -a $path/../logs/$panes0[2]-$getdate.log";
    } else {
        $_update_cmd = "cd $NNPATH && $_php update_binaries.php 2>&1 | tee -a $path/../logs/$panes0[2]-$getdate.log";
    }

    //set command for running backfill
    if (( $array['KEVIN_SAFER'] == "true" ) && ( $optimize_safe_to_run != "true" )) {
        $_backfill_cmd = "cd $_bin && $_php safer_backfill_parts.php 2>&1 | tee -a $path/../logs/$panes0[3]-$getdate.log";
    } elseif ( $array['BACKFILL_THREADS'] == "true" ) {
        $_backfill_cmd = "cd $_bin && $_php backfill_threaded.php 2>&1 | tee -a $path/../logs/$panes0[3]-$getdate.log && $mysql_command_1 2>&1 | tee -a $path/../logs/$panes0[3]-$getdate.log";
    } else {
        $_backfill_cmd = "cd $NNPATH && $_php backfill.php 2>&1 | tee -a $path/../logs/$panes0[3]-$getdate.log && $mysql_command_1 2>&1 | tee -a $path/../logs/$panes0[3]-$getdate.log";
    }

    //set command for nzb-import
    if (( $array['NZB_THREADS'] == "true" ) && ( $optimize_safe_to_run != "true" )) {
        $nzb_cmd = "$_php nzb-import-sub.php \"{$array['NZBS']}\"";
    } else {
        $nzb_cmd = "$_php nzb-import.php \"{$array['NZBS']}\" \"{$array['IMPORT_TRUE']}\"";
    }

    //check if sequential is set
    if ( $array['SEQUENTIAL'] != "true" ) {
        //runs update_binaries in 0.2 once if needed and exits
        if (( $killed != "true" ) && ( $array['BINARIES'] == "true" ) && (( $total_work_used < $array['BINARIES_MAX_RELEASES'] ) || ( $array['BINARIES_MAX_RELEASES'] == 0 )) && (( $parts_rows_unformated < $array['BINARIES_MAX_ROWS'] ) || ( $array['BINARIES_MAX_ROWS'] == 0 )) && ( $optimize_safe_to_run != "true" )) {
            $color = get_color();
            shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.2 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes0[2] $ds2 && $_update_cmd 2>&1 | tee -a $path/../logs/$panes0[2]-$getdate.log && echo \" \033[1;0;33m\" && echo \"$_sleep_string {$array['BINARIES_SLEEP']} seconds...\" && sleep {$array['BINARIES_SLEEP']} && $ds1 $panes0[2] $ds3' 2>&1 1> /dev/null");
        } elseif (( $parts_rows_unformated > $array['BINARIES_MAX_ROWS'] ) && ( $array['BINARIES'] == "true" ) && ( $array['BINARIES_MAX_ROWS'] != 0 ) && ( $optimize_safe_to_run != "true" )) {
            $color = get_color();
            shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.2 'echo \"\033[38;5;\"$color\"m\nBINARIES_MAX_ROWS exceeded\" && $ds1 $panes0[2] $ds4'");
        } elseif (( $total_work_used > $array['BINARIES_MAX_RELEASES'] ) && ( $array['BINARIES'] == "true" ) && ( $array['BINARIES_MAX_RELEASES'] != 0 ) && ( $optimize_safe_to_run != "true" )) {
            $color = get_color();
            shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.2 'echo \"\033[38;5;\"$color\"m\nBINARIES_MAX_RELEASES exceeded\" && $ds1 $panes0[2] $ds4'");
        } elseif (( $array['BINARIES'] != "true" ) && ( $optimize_safe_to_run != "true" )) {
            $color = get_color();
            shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.2 'echo \"\033[38;5;\"$color\"m\n$panes0[2] Disabled by BINARIES\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
        } elseif ( $optimize_safe_to_run == "true" ) {
            $color = get_color();
            shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.2 'echo \"\033[38;5;\"$color\"m\n$panes0[2] Disabled by OPTIMIZE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
        }

        //runs backfill in 0.3 once if needed and exits
        if (( $killed != "true" ) && ( $array['BACKFILL'] == "true" ) && (( $total_work_used < $array['BACKFILL_MAX_RELEASES'] ) || ( $array['BACKFILL_MAX_RELEASES'] == 0 )) && (( $parts_rows_unformated < $array['BACKFILL_MAX_ROWS'] ) || ( $array['BACKFILL_MAX_ROWS'] == 0 )) && ( $optimize_safe_to_run != "true" )) {
            $color = get_color();
            shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.3 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes0[3] $ds2 && $_backfill_cmd 2>&1 | tee -a $path/../logs/$panes0[3]-$getdate.log && echo \" \033[1;0;33m\" && echo \"$_sleep_string {$array['BACKFILL_SLEEP']} seconds...\" && sleep {$array['BACKFILL_SLEEP']} && $ds1 $panes0[3] $ds3' 2>&1 1> /dev/null");
        } elseif (( $parts_rows_unformated > $array['BACKFILL_MAX_ROWS'] ) && ( $array['BACKFILL'] == "true" ) && ( $array['BACKFILL_MAX_ROWS'] != 0 ) && ( $optimize_safe_to_run != "true" )) {
            $color = get_color();
            shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.3 'echo \"\033[38;5;\"$color\"m\nBACKFILL_MAX_ROWS exceeded\" && $ds1 $panes0[3] $ds4'");
        } elseif (( $total_work_used > $array['BACKFILL_MAX_RELEASES'] ) && ( $array['BACKFILL'] == "true" ) && ( $array['BACKFILL_MAX_RELEASES'] != 0 ) && ( $optimize_safe_to_run != "true" )) {
            $color = get_color();
            shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.3 'echo \"\033[38;5;\"$color\"m\nBACKFILL_MAX_RELEASES exceeded\" && $ds1 $panes0[3] $ds4'");
        } elseif (( $array['BACKFILL'] != "true" ) && ( $optimize_safe_to_run != "true" )) {
            $color = get_color();
            shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.3 'echo \"\033[38;5;\"$color\"m\n$panes0[3] Disabled by BACKFILL\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
        } elseif ( $optimize_safe_to_run == "true" ) {
            $color = get_color();
            shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.3 'echo \"\033[38;5;\"$color\"m\n$panes0[3] Disabled by OPTIMIZE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
        }
    }

    //run update_binaries and backfill using seq in pane 0.2
    $dead = `$_tmux list-panes -t {$array['TMUX_SESSION']}:0 | grep 2: | grep dead`;
    if (( $killed != "true" ) && ( $array['SEQUENTIAL'] == "true" ) && ( strlen( $dead ) > "4" )) {
        if ((( $i == 1 ) || ( TIME() - $time13 >= $array['BINARIES_SEQ_TIMER'] )) && ( $array['BINARIES'] == "true" ) && (( $total_work_used < $array['BINARIES_MAX_RELEASES'] ) || ( $array['BINARIES_MAX_RELEASES'] == 0 )) && (( $parts_rows_unformated < $array['BINARIES_MAX_ROWS'] ) || ( $array['BINARIES_MAX_ROWS'] == 0 )) && ( $optimize_safe_to_run != "true" )) {
            $color = get_color();
            shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.2 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes0[2] $ds2 && $_update_cmd && echo \" \033[1;0;33m\" && $ds1 $panes0[2] $ds3' 2>&1 1> /dev/null");
            $time13 = TIME();
        } elseif (( TIME() - $time14 >= $array['BACKFILL_SEQ_TIMER'] ) && ( $array['BACKFILL'] == "true" ) && (( $total_work_used < $array['BACKFILL_MAX_RELEASES'] ) || ( $array['BACKFILL_MAX_RELEASES'] == 0 )) && (( $parts_rows_unformated < $array['BACKFILL_MAX_ROWS'] ) || ( $array['BACKFILL_MAX_ROWS'] == 0 )) && ( $optimize_safe_to_run != "true" )) {
            $color = get_color();
            shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.2 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes0[3] $ds2 && $_backfill_cmd && echo \" \033[1;0;33m\" && $ds1 $panes0[3] $ds3' 2>&1 1> /dev/null");
            $time14 = TIME();
        } elseif (( $array['BINARIES'] != "true" ) && ( $array['BACKFILL'] != "true" ) && ( $optimize_safe_to_run != "true" )) {
            $color = get_color();
            shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.2 'echo \"\033[38;5;\"$color\"m\n$panes0[2] and $panes0[3] Disabled by BINARIES and BACKFILL\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
        } elseif (( $array['BACKFILL'] == "true" ) && ( $array['BINARIES'] == "true" ) && ( $optimize_safe_to_run != "true" )) {
            $color = get_color();
            $run_time1 = relativeTime( $array['BACKFILL_SEQ_TIMER'] + $time14 );
            $run_time2 = relativeTime( $array['BINARIES_SEQ_TIMER'] + $time13 );
            shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.2 'echo \"\033[38;5;\"$color\"m\n$panes0[2] will run in T[$run_time2]\n$panes0[3] will run in T[$run_time]1 \" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
        } elseif (( $array['BACKFILL'] == "true" ) && ( $optimize_safe_to_run != "true" )) {
            $color = get_color();
            $run_time = relativeTime( $array['BACKFILL_SEQ_TIMER'] + $time14 );
            shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.2 'echo \"\033[38;5;\"$color\"m\n$panes0[3] will run in T[$run_time]\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
        } elseif (( $array['BINARIES'] == "true" ) && ( $optimize_safe_to_run != "true" )) {
            $color = get_color();
            $run_time = relativeTime( $array['BINARIES_SEQ_TIMER'] + $time13 );
            shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.2 'echo \"\033[38;5;\"$color\"m\n$panes0[2] will run in T[$run_time]\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
        } elseif ( $optimize_safe_to_run == "true" ) {
            $color = get_color();
            shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.2 'echo \"\033[38;5;\"$color\"m\n$panes0[2] Disabled by OPTIMIZE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
        }
    }

    //notify pane 0.3 of the status of pane 0.2
    if ( $array['SEQUENTIAL'] == "true" ) {
        if (( $array['BINARIES'] != "true" ) && ( $array['BACKFILL'] != "true" ) && ( $optimize_safe_to_run != "true" )) {
            $color = get_color();
            shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.3 'echo \"\033[38;5;\"$color\"m\n$panes0[2] and $panes0[3] Disabled by BINARIES and BACKFILL\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
        } elseif (( $array['BACKFILL'] == "true" ) && ( $array['BINARIES'] == "true" ) && ( $optimize_safe_to_run != "true" )) {
            $run_time1 = relativeTime( $array['BACKFILL_SEQ_TIMER'] + $time14 );
            $run_time2 = relativeTime( $array['BINARIES_SEQ_TIMER'] + $time13 );
            $color = get_color();
            shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.3 'echo \"\033[38;5;\"$color\"m\n$panes0[2] will run in T[$run_time]2 \n$panes0[3] will run in T[$run_time]1 \" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
        } elseif (( $array['BACKFILL'] == "true" ) && ( $optimize_safe_to_run != "true" )) {
            $run_time = relativeTime( $array['BACKFILL_SEQ_TIMER'] + $time14 );
            $color = get_color();
            shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.3 'echo \"\033[38;5;\"$color\"m\n$panes0[3] will run in T[$run_time]\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
        } elseif (( $array['BINARIES'] == "true" ) && ( $optimize_safe_to_run != "true" )) {
            $run_time = relativeTime( $array['BINARIES_SEQ_TIMER'] + $time13 );
            $color = get_color();
            shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.3 'echo \"\033[38;5;\"$color\"m\n$panes0[2] will run in T[$run_time]\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
        } elseif ( $optimize_safe_to_run == "true" ) {
            $color = get_color();
            shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.3 'echo \"\033[38;5;\"$color\"m\n$panes0[3] Disabled by OPTIMIZE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
        }
    }


    //kill panes for sequential
    if ( $array['SEQUENTIAL'] == "true" ) {
        if (( $parts_rows_unformated > $array['BINARIES_MAX_ROWS'] ) && ( $array['BINARIES'] == "true" ) && ( $array['BINARIES_MAX_ROWS'] != 0 ) && ( $array['BACKFILL'] == "true" ) && ( $total_work_used > $array['BACKFILL_MAX_RELEASES'] ) && ( $array['BACKFILL_MAX_ROWS'] != 0 ) && ( $optimize_safe_to_run != "true" )) {
            $color = get_color();
            shell_exec("$_tmux respawnp -k -t {$array['TMUX_SESSION']}:0.2 'echo \"\033[38;5;\"$color\"m\nBINARIES_MAX_ROWS and BACKFILL_MAX_ROWS exceeded\" && $ds1 $panes0[2] $ds4'");
            shell_exec("$_tmux respawnp -k -t {$array['TMUX_SESSION']}:0.3 'echo \"\033[38;5;\"$color\"m\nBINARIES_MAX_ROWS and BACKFILL_MAX_ROWS exceeded\" && $ds1 $panes0[3] $ds4'");
        } elseif ( $optimize_safe_to_run == "true" ) {
            $color = get_color();
            shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.2 'echo \"\033[38;5;\"$color\"m\n$panes0[2] Disabled by OPTIMIZE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
            $color = get_color();
            shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.3 'echo \"\033[38;5;\"$color\"m\n$panes0[3] Disabled by OPTIMIZE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
        }
    }


    //runs nzb-import in 0.4 once if needed and exits
    if (( $killed != "true" ) && ( $array['IMPORT'] == "true" ) && (( $total_work_used < $array['IMPORT_MAX_RELEASES'] ) || ( $array['IMPORT_MAX_RELEASES'] == 0 )) && (( $parts_rows_unformated < $array['IMPORT_MAX_ROWS'] ) || ( $array['IMPORT_MAX_ROWS'] == 0 )) && ( $optimize_safe_to_run != "true" )) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.4 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes0[4] $ds2 && cd $_bin && $nzb_cmd 2>&1 | tee -a $path/../logs/$panes0[4]-$getdate.log && echo \" \" && echo \" \033[1;0;33m\" && echo \"$_sleep_string {$array['IMPORT_SLEEP']} seconds...\" && sleep {$array['IMPORT_SLEEP']} && $ds1 $panes0[4] $ds3' 2>&1 1> /dev/null");
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.1 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes0[1] $ds2 && cd $_bin && $_php nzbcount.php 2>&1 | tee -a $path/../logs/$panes0[1]-$getdate.log' 2>&1 1> /dev/null");
    } elseif (( $parts_rows_unformated > $array['IMPORT_MAX_ROWS'] ) && ( $array['IMPORT'] == "true" ) && ( $array['IMPORT_MAX_ROWS'] != 0 ) && ( $optimize_safe_to_run != "true" )) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.4 'echo \"\033[38;5;\"$color\"m\nIMPORT_MAX_ROWS exceeded\" && $ds1 $panes0[4] $ds4' 2>&1 1> /dev/null");
    } elseif (( $total_work_used > $array['IMPORT_MAX_RELEASES'] ) && ( $array['IMPORT'] == "true" ) && ( $array['IMPORT_MAX_RELEASES'] != 0 ) && ( $optimize_safe_to_run != "true" )) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.4 'echo \"\033[38;5;\"$color\"m\nIMPORT_MAX_RELEASES exceeded\" && $ds1 $panes0[4] $ds4' 2>&1 1> /dev/null");
    } elseif (( $array['IMPORT'] != "true" ) && ( $optimize_safe_to_run != "true" )) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.4 'echo \"\033[38;5;\"$color\"m\n$panes0[4] Disabled by IMPORT\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
    } elseif ( $optimize_safe_to_run == "true" ) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.4 'echo \"\033[38;5;\"$color\"m\n$panes0[4] Disabled by OPTIMIZE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
    }

    //runs nzbcount in 0.1 loops
    if (( $array['IMPORT'] == "true" ) && ( $optimize_safe_to_run != "true" )) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.1 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes0[1] $ds2 && cd $_bin && $_php nzbcount.php 2>&1 | tee -a $path/../logs/$panes0[1]-$getdate.log' 2>&1 1> /dev/null");
    } elseif (( $array['IMPORT'] != "true" ) && ( $optimize_safe_to_run != "true" )) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.1 'echo \"\033[38;5;\"$color\"m\n$panes0[1] Disabled by IMPORT\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
    } elseif ( $optimize_safe_to_run == "true" ) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.1 'echo \"\033[38;5;\"$color\"m\n$panes0[1] Disabled by OPTIMIZE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
    }


    //runs update_release and in 0.5 once if needed and exits
    if (( $array['RELEASES'] == "true" ) && ( $optimize_safe_to_run != "true" )) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.5 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes0[5] $ds2 && cd $_bin && $_php update_releases.php 2>&1 | tee -a $path/../logs/$panes0[5]-$getdate.log && echo \" \033[1;0;33m\" && echo \"$_sleep_string {$array['RELEASES_SLEEP']} seconds...\" && sleep {$array['RELEASES_SLEEP']} && $ds1 $panes0[5] $ds3' 2>&1 1> /dev/null");
    } elseif (( $array['RELEASES'] != "true" ) && ( $optimize_safe_to_run != "true" )) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.5 'echo \"\033[38;5;\"$color\"m\n$panes0[5] Disabled by RELEASES\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
    } elseif ( $optimize_safe_to_run == "true" ) {
        $color = get_color();
        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.5 'echo \"\033[38;5;\"$color\"m\n$panes0[5] Disabled by OPTIMIZE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
    }
    //start postprocessing in window 2
    $post = $array['POST_TO_RUN'];
    for ($g=1; $g<=32; $g++)
    {
        $h=$g-1;
        $f=$h*100;
        $j=$g*2;
        $color = get_color();
        if (( $array['POST_TO_RUN'] >= $g ) && ( $work_remaining_now > $f ) && ( $optimize_safe_to_run != "true" )) {
            shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.$h 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes2[$h] $ds2 && cd $_bin && sleep $j && $_php processAdditional$g.php 2>&1 | tee -a $path/../logs/$panes2[$h]-$getdate.log && echo \" \033[1;0;33m\" && $ds1 $panes2[$h] $ds3' 2>&1 1> /dev/null");
        } elseif (( $array['POST_TO_RUN'] >= $g ) && ( $work_remaining_now <= $f ) && ( $optimize_safe_to_run != "true" )) {
            shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.$h 'echo \"\033[38;5;\"$color\"m\n$panes2[$h]\n$work_remaining_now < $f\nHas no work to process \" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
        } elseif (( $g > $post ) && ( $optimize_safe_to_run != "true" )) {
            shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.$h 'echo \"\033[38;5;\"$color\"m\n$panes2[$h]\nDisabled by \nPOST_TO_RUN\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
        } elseif ( $optimize_safe_to_run == "true" ) {
            shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.$h 'echo \"\033[38;5;\"$color\"m\n$panes2[$h]\nDisabled by \nOPTIMIZE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
        }
    }

    //check ffmpeg and mediainfo, kill if necessary
    if (( $array['KILL_PROCESS'] != "0" ) && ( $array['KILL_QUIET'] == "true" ) && ( $optimize_safe_to_run != "true" )) {
        echo "\n";
        shell_exec("killall -qo {$array['KILL_PROCESS']}s -9 mediainfo");
        shell_exec("killall -qo {$array['KILL_PROCESS']}s -9 ffmpeg");
    } else {
        echo "\n";
        shell_exec("killall -o {$array['KILL_PROCESS']}s -9 mediainfo 2>&1 1> /dev/null");
        shell_exec("killall -o {$array['KILL_PROCESS']}s -9 ffmpeg 2>&1 1> /dev/null");
    }

    if (( $array['KILL_QUIET'] == "true" ) && ( $optimize_safe_to_run == "true" )) {
        echo "\n";
        shell_exec("killall -q mediainfo");
        shell_exec("killall -q ffmpeg");
    } else {
        echo "\n";
        shell_exec("killall mediainfo 2>&1 1> /dev/null");
        shell_exec("killall ffmpeg 2>&1 1> /dev/null");
    }

    //notify monitor that optimize is running
    if (( ! shell_exec("$_tmux list-panes -t {$array['TMUX_SESSION']}:1 | grep 4: | grep dead" )) && ( $array['OPTIMIZE'] == "true" )) {
        echo "\033[1;41;33mOPTIMIZATION OF THE MYSQL TABLES HAS STARTED, DO NOT STOP THIS SCRIPT!\033[1;0;33m\n\n";
    }

    //turn of monitor if set to false
    if ( $array['RUNNING'] == "true" ) {
        $i++;
    } else {
        $i=0;
    }
    sleep($array['MONITOR_UPDATE']);
}
?>

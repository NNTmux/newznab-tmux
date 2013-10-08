<?php

require(dirname(__FILE__)."/config.php");
require(WWW_DIR.'/lib/postprocess.php');
$version="0.1r843";

$db = new DB();
$DIR = dirname (__FILE__);
//totals per category in db, results by parentID
$qry = "SELECT COUNT( releases.categoryID ) AS cnt, parentID FROM releases INNER JOIN category ON releases.categoryID = category.ID WHERE parentID IS NOT NULL GROUP BY parentID";

//needs to be processed query
$proc = "SELECT
( SELECT COUNT( groupID ) AS cnt from releases where consoleinfoID IS NULL and categoryID BETWEEN 1000 AND 1999 ) AS console,
( SELECT COUNT( groupID ) AS cnt from releases where imdbID IS NULL and categoryID BETWEEN 2000 AND 2999 ) AS movies,
( SELECT COUNT( groupID ) AS cnt from releases where musicinfoID IS NULL and categoryID BETWEEN 3000 AND 3999 ) AS audio,
( SELECT COUNT( groupID ) AS cnt from releases where reqID IS NULL and haspreview = -1 and categoryID BETWEEN 6000 AND 6999 ) AS xxx,
(SELECT COUNT( groupID ) AS cnt from releases r left join category c on c.ID = r.categoryID where (categoryID BETWEEN 4000 AND 4999 and ((r.passwordstatus between -6 and -1) or (r.haspreview = -1 and c.disablepreview = 0)))) AS pc,
( SELECT COUNT( groupID ) AS cnt from releases where rageID = -1 and categoryID BETWEEN 5000 AND 5999 ) AS tv,
( SELECT COUNT( groupID ) AS cnt from releases where bookinfoID IS NULL and categoryID = 7020 ) AS book,
( SELECT COUNT( groupID ) AS cnt from releases r left join category c on c.ID = r.categoryID where (r.passwordstatus between -6 and -1) or (r.haspreview = -1 and c.disablepreview = 0)) AS work,
( SELECT COUNT( groupID ) AS cnt from releases) AS releases,
( SELECT COUNT( groupID ) AS cnt FROM releases r WHERE r.releasenfoID = 0) AS nforemains,
( SELECT COUNT( groupID ) AS cnt FROM releases WHERE releasenfoID not in (0, -1)) AS nfo,
( SELECT table_rows AS cnt FROM information_schema.TABLES where table_name = 'parts' AND TABLE_SCHEMA = '".DB_NAME."' ) AS parts,
( SELECT COUNT(ID) FROM binaries WHERE procstat = 0 ) AS binaries,
( SELECT table_rows AS cnt FROM information_schema.TABLES where table_name = 'binaries' AND TABLE_SCHEMA = '".DB_NAME."' ) AS binaries_total,
( SELECT concat(round((data_length+index_length)/(1024*1024*1024),2),'GB') AS cnt FROM information_schema.tables where table_name = 'parts' AND TABLE_SCHEMA = '".DB_NAME."' ) AS partsize,
( SELECT concat(round((data_length+index_length)/(1024*1024*1024),2),'GB') AS cnt FROM information_schema.tables where table_name = 'binaries' AND TABLE_SCHEMA = '".DB_NAME."' ) AS binariessize,
( SELECT UNIX_TIMESTAMP(adddate) from releases order by adddate desc limit 1 ) AS newestadd,
( SELECT COUNT( ID ) FROM groups WHERE active = 1 ) AS active_groups,
( SELECT COUNT( ID ) FROM groups WHERE name IS NOT NULL ) AS all_groups,
( SELECT COUNT( ID ) FROM groups WHERE first_record IS NOT NULL and `backfill_target` > 0 and first_record_postdate != '2000-00-00 00:00:00'  < first_record_postdate) AS backfill_groups,
( SELECT UNIX_TIMESTAMP(adddate) from prehash order by adddate DESC limit 1 ) AS newestprehash,
( SELECT UNIX_TIMESTAMP(updatedate) from predb order by updatedate DESC limit 1 ) AS newestpredb,
( SELECT COUNT( * ) FROM prehash where releaseID IS NOT NULL ) AS prehash_matched,
( SELECT TABLE_ROWS FROM INFORMATION_SCHEMA.TABLES where table_name = 'prehash' AND TABLE_SCHEMA = '".DB_NAME."' ) AS prehash,
( SELECT name from releases order by adddate desc limit 1 ) AS newestaddname";
//$proc = "SELECT * FROM procCnt;";

//get first release inserted datetime and oldest posted datetime
//$posted_date = "SELECT(SELECT UNIX_TIMESTAMP(adddate) from releases order by adddate asc limit 1) AS adddate;";

//flush query cache
$qcache = "FLUSH QUERY CACHE";

//get variables from defaults.sh
$path = dirname(__FILE__);
$varnames = shell_exec("cat ".$path."/../defaults.sh | grep ^export | cut -d \= -f1 | awk '{print $2;}'");
$vardata = shell_exec("cat ".$path."/../defaults.sh | grep ^export | cut -d \\\" -f2 | awk '{print $1;}'");
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
$_nice = getenv('NICE');
$_php = $_nice." -n${array['NICENESS']} ".getenv('PHP');
$_tmux = getenv('TMUXCMD');
$_sed = getenv('SED');
$_tee = getenv('TEE');
$_mysqladmin = getenv('MYSQLADMIN');
$_count_releases = 0;
$_imports = $array['NZB_THREADS'];
$_bin = dirname(__FILE__)."/../bin";
$_alienx = dirname(__FILE__)."/../alienx";
$_conf = dirname(__FILE__)."/../conf";
$_powerline = dirname(__FILE__)."/../powerline";
$_cj = dirname(__FILE__)."/../nnscripts";
$_hash = dirname (__FILE__)."/../hash_matching";
$_test = dirname (__FILE__)."/../test";
$_user = dirname(__FILE__)."/../user_scripts";
$_temp = dirname(__FILE__)."/../bin/temp";


$NNPATH="{$array['NEWZPATH']}{$array['NEWZNAB_PATH']}";
$TESTING="{$array['NEWZPATH']}{$array['TESTING_PATH']}";
$killed="false";
$old_session="{$array['TMUX_SESSION']}";
$getdate = gmDate("Ymd");

//build queries for shell
$_backfill_increment = "UPDATE groups set backfill_target=backfill_target+1 where active=1 and backfill_target<{$array['MAXDAYS']};";
$mysql_command_1 = "$_mysql --defaults-file=$_conf/my.cnf -u$_DB_USER -h $_DB_HOST $_DB_NAME -e \"$_backfill_increment\"";
$reset_bin = "UPDATE binaries SET procstat=0, procattempts=0, regexID=NULL, relpart=0, reltotalpart=0, relname=NULL;";
$mysql_command_2 = "$_mysql --defaults-file=$_conf/my.cnf -u$_DB_USER -h $_DB_HOST $_DB_NAME -e \"$reset_bin\"";
$mysqladmin = "$_mysqladmin --defaults-file=$_conf/my.cnf -u$_DB_USER -h $_DB_HOST status | awk '{print $22;}'";

//got microtime
function microtime_float()
{
	list($usec, $sec) = explode(" ", microtime());
	return ((float)$usec + (float)$sec);
}

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
	$exceptions = array( 4, 8, 9, 11, 15, 16, 17, 18, 19, 46, 47, 48, 49, 50, 51, 52, 53, 59, 60 );
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

//get system load
function get_load() {
	$load = sys_getloadavg();
	return $load[0];
}

function writelog( $pane )
{
	global $path;
	global $getdate;
	global $array;
	global $_tee;
	if ( $array['WRITE_LOGS'] == "true" ) {
		return " | $_tee -a $path/../logs/$pane-$getdate.log";
	} else {
		return "";
	}
}

$time = TIME();
$time2 = TIME();
$time3 = TIME();
$time4 = TIME();
$time6 = TIME();
$time7 = TIME();
$time9 = TIME();
$time10 = TIME();
$time11 = TIME();
$time12 = TIME();
$time13 = TIME();
$time14 = TIME();
$time15 = TIME();
$time16 = TIME();
$time17 = TIME();
$time18 = TIME();
$time19 = TIME();
$time20 = TIME();
$time21 = TIME();
$time22 = TIME();
$time23 = TIME();
$time24 = TIME();
$time25 = TIME();
$time26 = TIME();
$time27 = TIME();
$time28 = TIME();
$time29 = TIME();

if ( $array['INNODB'] == "true" ) {
	$time5 = TIME();
	$time8 = TIME();
}

//init start values
$work_start = 0;
$releases_start = 0;
$console_releases_now = 0;
$movie_releases_now = 0;
$music_releases_now = 0;
$pc_releases_now = 0;
$tvrage_releases_now = 0;
$xxx_releases_now = 0;
$book_releases_now = 0;
$misc_releases_now = 0;
$console_releases_proc = 0;
$movie_releases_proc = 0;
$music_releases_proc = 0;
$pc_releases_proc = 0;
$tvrage_releases_proc = 0;
$xxx_releases_proc = 0;
$work_remaining_now = 0;
$book_releases_proc = 0;
$releases_loop = 0;
$nfo_remaining_now = 0;
$nfo_now = 0;
$parts_rows = 0;
$parts_size_gb = 0;
$binaries_rows = 0;
$binaries_total = 0;
$binaries_size_gb = 0;
$releases_now = 0;
//$firstdate = TIME();
$newestname = "Unknown";
$newestdate = TIME();
$newestpredb = TIME();
$newestprehash = TIME();
$parts_rows_unformatted = 0;
$binaries_total_unformatted = 0;
$binaries_rows_unformatted = 0;
$releases_now_formatted = 0;
$nfo_percent = 0;
$pre_percent = 0;
$console_percent = 0;
$movie_percent = 0;
$music_percent = 0;
$pc_percent = 0;
$tvrage_percent = 0;
$xxx_percent = 0;
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
$releases_state = "disabled";
$releases_reason = "disabled";
$query_timer_start = 0;
$query_timer = 0;
$console_releases_start = 0;
$movie_releases_start = 0;
$music_releases_start = 0;
$pc_releases_start = 0;
$tvrage_releases_start = 0;
$xxx_releases_start = 0;
$book_releases_start = 0;
$misc_releases_start = 0;
$nfo_start = 0;
$nfo_remaining_start = 0;
$console_releases_proc_start = 0;
$movie_releases_proc_start = 0;
$music_releases_proc_start = 0;
$pc_releases_proc_start = 0;
$tvrage_releases_proc_start = 0;
$xxx_releases_proc_start = 0;
$book_releases_proc_start = 0;
$work_remaining_start = 0;
$misc_diff = 0;
$disk_use = 0;
$disk_free = 0;
$total_work_start = 0;
$run_time1 = 0;
$run_time2 = 0;
$run_time3 = 0;
$run_time4 = 0;
$uspactiveconnections = 0;
$usptotalconnections = 0;
$active_groups = 0;
$all_groups = 0;
$backfill_groups =0;
$prehash_matched = 0;
$prehash_start = 0;
$prehash = 0;
$pre_diff = 0;

//formatted  output
$pre_diff = number_format( $prehash_matched - $prehash_start );
$nfo_diff = number_format( $nfo_remaining_now - $nfo_remaining_start );
$console_diff = number_format( $console_releases_proc - $console_releases_proc_start );
$movie_diff = number_format( $movie_releases_proc - $movie_releases_proc_start );
$music_diff = number_format( $music_releases_proc - $music_releases_proc_start );
$pc_diff = number_format( $pc_releases_proc - $pc_releases_proc_start );
$tvrage_diff = number_format( $tvrage_releases_proc - $tvrage_releases_proc_start );
$xxx_diff = number_format ( $xxx_releases_proc - $xxx_releases_proc_start );
$book_diff = number_format( $book_releases_proc - $book_releases_proc_start );
$remaning_since_start = number_format( $work_remaining_now - $work_remaining_start );
$console_releases_proc_formatted = number_format( $console_releases_proc );
$movie_releases_proc_formatted = number_format( $movie_releases_proc );
$music_releases_proc_formatted = number_format( $music_releases_proc );
$pc_releases_proc_formatted = number_format( $pc_releases_proc );
$tvrage_releases_proc_formatted = number_format( $tvrage_releases_proc );
$xxx_releases_proc_formatted = number_format ($xxx_releases_proc );
$misc_remaining_now_formatted = number_format( $work_remaining_now );
$book_releases_proc_formatted = number_format( $book_releases_proc );
$nfo_remaining_now_formatted = number_format( $nfo_remaining_now );
$nfo_now_formatted = number_format( $nfo_now );
$console_releases_now_formatted = number_format( $console_releases_now );
$movie_releases_now_formatted = number_format( $movie_releases_now );
$music_releases_now_formatted = number_format( $music_releases_now );
$pc_releases_now_formatted = number_format( $pc_releases_now );
$tvrage_releases_now_formatted = number_format( $tvrage_releases_now );
$xxx_releases_now_formatted = number_format ( $xxx_releases_now );
$book_releases_now_formatted = number_format( $book_releases_now );
$misc_releases_now_formatted = number_format( $misc_releases_now );



//create initial display, USP connection count, prehash count and groups count adapted from nZEDb
passthru('clear');
//printf("\033[1;31m  First insert:\033[0m ".relativeTime("$firstdate")."\n");
$mask1 = "\033[1;33m%-16s \033[38;5;214m%-49.49s \n";
$mask2 = "\033[1;33m%-16s \033[38;5;214m%-39.39s \n";
printf($mask2, "Monitor Running v$version: ", relativeTime("$time"));
printf($mask1, "USP Connections:" ,$uspactiveconnections." active (".$usptotalconnections." total used) - ".NNTP_SERVER);
printf($mask1, "Newest Release:", "$newestname");
printf($mask1, "Release Added:", relativeTime("$newestdate")."ago");
if ($array['PREDB'] = "true"){
    printf($mask1, "Predb Updated:", relativeTime("$newestpredb")."ago");
}
if  ($array ['FIXRELEASES'] = "true") {
    printf($mask1, "Prehash Updated:", relativeTime("$newestprehash")."ago");
}
$mask = "%-15.15s %22.22s %22.22s\n";
printf("\033[1;33m\n");
printf($mask, "Category", "State", "Reason");
printf($mask, "====================", "====================", "====================");
printf("\033[38;5;214m");
printf($mask, "Binaries", "$binaries_state", "$binaries_reason");
printf($mask, "Backfill", "$backfill_state", "$backfill_reason");
printf($mask, "Import", "$import_state", "$import_reason");
printf($mask, "Releases", "$releases_state", "$releases_reason");
printf($mask, "Parts", "$parts_size_gb", "$parts_rows rows");
printf($mask, "Binaries", "$binaries_size_gb", $binaries_rows."/".$binaries_total." bins");
if ( $array['RAMDISK_PATH'] != "" ) {
	printf($mask, "Ramdisk", "$disk_use", "$disk_free");
}

printf("\033[1;33m\n");
printf($mask, "Category", "In Process", "In Database");
printf($mask, "====================", "====================", "====================");
printf("\033[38;5;214m");
if ($array ['FIXRELEASES'] = "true"){
printf($mask, "prehash",number_format($prehash - $prehash_matched)."(".$pre_diff.")",number_format($prehash_matched)."(".$pre_percent."%)");
}
printf($mask, "NFO's","$nfo_remaining_now_formatted($nfo_diff)","$nfo_now_formatted($nfo_percent%)");
printf($mask, "Console(1000)","$console_releases_proc_formatted($console_diff)","$console_releases_now_formatted($console_percent%)");
printf($mask, "Movie(2000)","$movie_releases_proc_formatted($movie_diff)","$movie_releases_now_formatted($movie_percent%)");
printf($mask, "Audio(3000)","$music_releases_proc_formatted($music_diff)","$music_releases_now_formatted($music_percent%)");
printf($mask, "PC(4000)","$pc_releases_proc_formatted($pc_diff)","$pc_releases_now_formatted($pc_percent%)");
printf($mask, "TVShows(5000)","$tvrage_releases_proc_formatted($tvrage_diff)","$tvrage_releases_now_formatted($tvrage_percent%)");
printf($mask, "XXX(6000)","$xxx_releases_proc_formatted($xxx_diff)","$xxx_releases_now_formatted($xxx_percent%)");
printf($mask, "Books(7000)","$book_releases_proc_formatted($book_diff)","$book_releases_now_formatted($book_percent%)");
printf($mask, "Misc(8000)","$misc_remaining_now_formatted($misc_diff)","$misc_releases_now_formatted($misc_percent%)");
printf($mask, "Total", "$total_work_now_formatted($work_diff)", "$releases_now_formatted($releases_since_start)");

printf("\n\033[1;33m");
printf($mask, "Category", "Time", "Status");
printf($mask, "====================", "====================", "====================");
printf("\033[38;5;214m");
printf($mask, "DB Lagg","$query_timer","0 qps");

printf("\n\033[1;33m\n");
printf($mask, "Groups", "Active", "Backfill");
printf($mask, "====================", "=========================", "=========================");
printf("\033[38;5;214m");
printf($mask, "Activated", $active_groups."(".$all_groups.")", $backfill_groups. "(".$all_groups.")");

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

	//get variables from defaults.sh
	$path = dirname(__FILE__);
	$varnames = shell_exec("cat ".$path."/../defaults.sh | grep ^export | cut -d \= -f1 | awk '{print $2;}'");
	$vardata = shell_exec("cat ".$path."/../defaults.sh | grep ^export | cut -d \\\" -f2 | awk '{print $1;}'");
	$varnames = explode("\n", $varnames);
	$vardata = explode("\n", $vardata);
	$array = array_combine($varnames, $vardata);
	unset($array['']);

	//allow tmux to create panes before running queries
	if ( $i == 1 ) {
		printf("\n\033[1;31mTmux panes safely created in ");
		for($a=10;$a>-1;$a--)
		{
			printf("$a..");
			sleep(1);
		}
		//$rel = $db->query("UPDATE `binaries` SET `procstat`=0,`procattempts`=0,`regexID`=NULL, `relpart`=0,`reltotalpart`=0,`relname`=NULL WHERE procstat not in (4, 6)");
		$rel = $db->query("UPDATE binaries SET procstat=0, procattempts=0, regexID=NULL, relpart=0, reltotalpart=0, relname=NULL");
	}

	//defrag the query cache every 15 minutes
	if (( TIME() - $time18 >= 900 ) || ( $i == 1 ))
	{
		$result = @$db->query($qcache);
	}

	//rename the session
	if ( $old_session != $array['TMUX_SESSION'] ) {
		shell_exec("$_tmux rename-session -t $old_session {$array['TMUX_SESSION']}");
	}
	$old_session="{$array['TMUX_SESSION']}";

	//reload tmux.conf
	if ( $array['POWERLINE'] == "true" ) {
		shell_exec("$_tmux source-file $_powerline/tmux.conf");
	} else {
		shell_exec("$_tmux source-file $_conf/tmux.conf");
	}

	//reset title, might rename in some terminals
	printf("\033]0;{$array['TMUX_SESSION']}\007\003\n");

	//kill panes if user changed to/from nzb import threaded
	if ( $_imports != $array['NZB_THREADS'] ) {
		shell_exec("$_tmux respawnp -k -t {$array['TMUX_SESSION']}:0.4 'sleep 5' && $ds1 $panes0[4] $ds4");
		shell_exec("$_tmux respawnp -k -t {$array['TMUX_SESSION']}:0.1 'sleep 5' && $ds1 $panes0[1] $ds4");
		$_imports = $array['NZB_THREADS'];
	}

	//run queries
	if ((( TIME() - $time19 ) >= $array['MONITOR_UPDATE'] ) || ( $i == 1 )) {
		//get microtime to at start of queries
		$query_timer_start=microtime_float();
		$result = @$db->query($qry);
		$initquery = array();
		foreach ($result as $cat=>$sub)
		{
			$initquery[$sub['parentID']] = $sub['cnt'];
		}
		$proc_result = @$db->query($proc);
		$time19 = TIME();
		$runloop = "true";
	} else {
		$runloop = "false";
	}

	//initial query for total releases
	if (( @$proc_result[0]['work'] != NULL ) && ( $work_start == 0 )) { $work_start = $proc_result[0]['work']; }
	if (( @$proc_result[0]['releases'] ) && ( $releases_start == 0 )) { $releases_start = $proc_result[0]['releases']; }

	//get start values from $qry
	if ( $i == "1" ) 
	{
		if ( @$proc_result[0]['nforemains'] != NULL ) { $nfo_remaining_start = $proc_result[0]['nforemains']; }
        if ( @$proc_result[0]['prehash_matched'] != NULL ) { $prehash_start = $proc_result[0]['prehash_matched']; }
		if ( @$proc_result[0]['console'] != NULL ) { $console_releases_proc_start = $proc_result[0]['console']; }
		if ( @$proc_result[0]['movies'] != NULL ) { $movie_releases_proc_start = $proc_result[0]['movies']; }
		if ( @$proc_result[0]['audio'] != NULL ) { $music_releases_proc_start = $proc_result[0]['audio']; }
		if ( @$proc_result[0]['pc'] != NULL ) { $pc_releases_proc_start = $proc_result[0]['pc']; }
		if ( @$proc_result[0]['tv'] != NULL ) { $tvrage_releases_proc_start = $proc_result[0]['tv']; }
		if ( @$proc_result[0]['book'] != NULL ) { $book_releases_proc_start = $proc_result[0]['book']; }
		if ( @$proc_result[0]['work'] != NULL ) { $work_remaining_start = $proc_result[0]['work']; }
        if ( @$proc_result[0]['xxx'] != NULL ) { $xxx_releases_proc_start = $proc_result[0]['xxx']; }
	}

	//get values from $qry
	if ( @$initquery['1000'] != NULL ) { $console_releases_now = $initquery['1000']; }
	if ( @$initquery['2000'] != NULL ) { $movie_releases_now = $initquery['2000']; }
	if ( @$initquery['3000'] != NULL ) { $music_releases_now = $initquery['3000']; }
	if ( @$initquery['4000'] != NULL ) { $pc_releases_now = $initquery['4000']; }
	if ( @$initquery['5000'] != NULL ) { $tvrage_releases_now = $initquery['5000']; }
    if ( @$initquery['6000'] != NULL ) { $xxx_releases_now = $initquery['6000']; }
	if ( @$initquery['7000'] != NULL ) { $book_releases_now = $initquery['7000']; }
	if ( @$initquery['8000'] != NULL ) { $misc_releases_now = $initquery['8000']; }

	//get values from $proc
	if ( @$proc_result[0]['console'] != NULL ) { $console_releases_proc = $proc_result[0]['console']; }
	if ( @$proc_result[0]['console'] != NULL ) { $console_releases_proc_formatted = number_format($proc_result[0]['console']); }
	if ( @$proc_result[0]['movies'] != NULL ) { $movie_releases_proc = $proc_result[0]['movies']; }
	if ( @$proc_result[0]['audio'] != NULL ) { $music_releases_proc = $proc_result[0]['audio']; }
	if ( @$proc_result[0]['pc'] != NULL ) { $pc_releases_proc = $proc_result[0]['pc']; }
	if ( @$proc_result[0]['tv'] != NULL ) { $tvrage_releases_proc = $proc_result[0]['tv']; }
    if ( @$proc_result[0]['xxx'] != NULL ) { $xxx_releases_proc = $proc_result[0]['xxx']; }
	if ( @$proc_result[0]['book'] != NULL ) { $book_releases_proc = $proc_result[0]['book']; }
	if ( @$proc_result[0]['work'] != NULL ) { $work_remaining_now = $proc_result[0]['work']; }
	if ( @$proc_result[0]['releases'] != NULL ) { $releases_loop = $proc_result[0]['releases']; }
	if ( @$proc_result[0]['nforemains'] != NULL ) { $nfo_remaining_now = $proc_result[0]['nforemains']; }
	if ( @$proc_result[0]['nfo'] != NULL ) { $nfo_now = $proc_result[0]['nfo']; }
	if ( @$proc_result[0]['parts'] != NULL ) { $parts_rows_unformatted = $proc_result[0]['parts']; }
	if ( @$proc_result[0]['parts'] != NULL ) { $parts_rows = number_format($proc_result[0]['parts']); }
	if ( @$proc_result[0]['partsize'] != NULL ) { $parts_size_gb = $proc_result[0]['partsize']; }

	if ( @$proc_result[0]['binaries'] != NULL ) { $binaries_rows_unformatted = $proc_result[0]['binaries']; }
	if ( @$proc_result[0]['binaries'] != NULL ) { $binaries_rows = number_format($proc_result[0]['binaries']); }
        if ( @$proc_result[0]['binaries'] != NULL ) { $binaries_total_unformatted = $proc_result[0]['binaries_total']; }
        if ( @$proc_result[0]['binaries'] != NULL ) { $binaries_total = number_format($proc_result[0]['binaries_total']); }

	if ( @$proc_result[0]['binariessize'] != NULL ) { $binaries_size_gb = $proc_result[0]['binariessize']; }

	if ( @$proc_result[0]['releases'] ) { $releases_now = $proc_result[0]['releases']; }
	if ( @$proc_result[0]['releases'] ) { $releases_now_formatted = number_format($proc_result[0]['releases']); }
	if ( @$proc_result[0]['newestaddname'] ) { $newestname = $proc_result[0]['newestaddname']; }
	if ( @$proc_result[0]['newestadd'] ) { $newestdate = $proc_result[0]['newestadd']; }
    if ( @$proc_result[0]['active_groups'] != NULL ) { $active_groups = $proc_result[0]['active_groups']; }
    if ( @$proc_result[0]['all_groups'] != NULL ) { $all_groups = $proc_result[0]['all_groups']; }
    if ( @$proc_result[0]['newestprehash'] ) { $newestprehash = $proc_result[0]['newestprehash']; }
    if ( @$proc_result[0]['newestpredb'] ) { $newestpredb = $proc_result[0]['newestpredb']; }
    if ( @$proc_result[0]['prehash'] != NULL ) { $prehash = $proc_result[0]['prehash']; }
    if ( @$proc_result[0]['prehash_matched'] != NULL ) { $prehash_matched = $proc_result[0]['prehash_matched']; }

	//calculate releases difference
	$releases_misc_diff = number_format( $releases_now - $releases_start );
	$releases_since_start = number_format( $releases_now - $releases_start );
	$work_misc_diff = $work_remaining_now - $work_remaining_start;

	$total_work_now = $work_remaining_now + $tvrage_releases_proc + $music_releases_proc + $movie_releases_proc + $console_releases_proc + $book_releases_proc + $xxx_releases_proc + $nfo_remaining_now;
	if ( $i == 1 ) { $total_work_start = $total_work_now; }
	$total_work_now_formatted = number_format($total_work_now);

	$nfo_diff = number_format( $nfo_remaining_now - $nfo_remaining_start );
	$console_diff = number_format( $console_releases_proc - $console_releases_proc_start );
	$movie_diff = number_format( $movie_releases_proc - $movie_releases_proc_start );
	$music_diff = number_format( $music_releases_proc - $music_releases_proc_start );
	$pc_diff = number_format( $pc_releases_proc - $pc_releases_proc_start );
	$tvrage_diff = number_format( $tvrage_releases_proc - $tvrage_releases_proc_start );
    $xxx_diff = number_format ($xxx_releases_proc - $xxx_releases_proc_start );
	$book_diff = number_format( $book_releases_proc - $book_releases_proc_start );
    $pre_diff = number_format( $prehash_matched - $prehash_start );

	//formatted  output
	$console_releases_proc_formatted = number_format( $console_releases_proc );
	$movie_releases_proc_formatted = number_format( $movie_releases_proc );
	$music_releases_proc_formatted = number_format( $music_releases_proc );
	$pc_releases_proc_formatted = number_format( $pc_releases_proc );
	$tvrage_releases_proc_formatted = number_format( $tvrage_releases_proc );
    $xxx_releases_proc_formatted = number_format ( $xxx_releases_proc );
	$misc_remaining_now_formatted = number_format( $work_remaining_now );
	$book_releases_proc_formatted = number_format( $book_releases_proc );
	$nfo_remaining_now_formatted = number_format( $nfo_remaining_now );
	$nfo_now_formatted = number_format( $nfo_now );
	$console_releases_now_formatted = number_format( $console_releases_now );
	$movie_releases_now_formatted = number_format( $movie_releases_now );
	$music_releases_now_formatted = number_format( $music_releases_now );
	$pc_releases_now_formatted = number_format( $pc_releases_now );
	$tvrage_releases_now_formatted = number_format( $tvrage_releases_now );
    $xxx_releases_now_formatted = number_format ( $xxx_releases_now );
	$book_releases_now_formatted = number_format( $book_releases_now );
	$misc_releases_now_formatted = number_format( $misc_releases_now );
	$misc_diff = number_format( $work_remaining_now - $work_start );

	$work_since_start = ( $total_work_now - $total_work_start );
	$work_diff = number_format($work_since_start);

	//get microtime at end of queries
	if ( $runloop == "true" ) {
		$query_timer = microtime_float()-$query_timer_start;
	}

	if ( $array['RAMDISK_PATH'] != "" ) {
		$disk_use =  decodeSize( disk_total_space("${array['RAMDISK_PATH']}") - disk_free_space("${array['RAMDISK_PATH']}") );
		$disk_free = decodeSize( disk_free_space("${array['RAMDISK_PATH']}") );
	}

	if ( $releases_now != 0 ) {
		$nfo_percent = sprintf( "%02s", floor(( $nfo_now / $releases_now) * 100 ));
        $pre_percent = sprintf( "%02s", floor(( $prehash_matched / $releases_now) * 100 ));
		$console_percent = sprintf( "%02s", floor(( $console_releases_now / $releases_now) * 100 ));
		$movie_percent = sprintf( "%02s", floor(( $movie_releases_now / $releases_now) * 100 ));
		$music_percent = sprintf( "%02s", floor(( $music_releases_now / $releases_now) * 100 ));
		$pc_percent = sprintf( "%02s", floor(( $pc_releases_now / $releases_now) * 100 ));
		$tvrage_percent = sprintf( "%02s", floor(( $tvrage_releases_now / $releases_now) * 100 ));
        $xxx_percent = sprintf( "%02s", floor(( $xxx_releases_now / $releases_now) * 100 ));
		$book_percent = sprintf( "%02s", floor(( $book_releases_now / $releases_now) * 100 ));
		$misc_percent = sprintf( "%02s", floor(( $misc_releases_now / $releases_now) * 100 ));
	} else {
		$nfo_percent = 0;
        $pre_percent = 0;
		$console_percent = 0;
		$movie_percent = 0;
		$music_percent = 0;
		$pc_percent = 0;
		$tvrage_percent = 0;
        $xxx_percent = 0;
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
	$panes_win_5 = shell_exec("echo `tmux list-panes -t  {$array['TMUX_SESSION']}:4 -F '#{pane_title}'`");
	$panes_win_6 = shell_exec("echo `tmux list-panes -t  {$array['TMUX_SESSION']}:5 -F '#{pane_title}'`");
	$panes_win_7 = shell_exec("echo `tmux list-panes -t  {$array['TMUX_SESSION']}:6 -F '#{pane_title}'`");

	$panes0 = str_replace("\n", '', explode(" ", $panes_win_1));
	$panes1 = str_replace("\n", '', explode(" ", $panes_win_2));
	$panes2 = str_replace("\n", '', explode(" ", $panes_win_3));
	$panes3 = str_replace("\n", '', explode(" ", $panes_win_4));
	$panes4 = str_replace("\n", '', explode(" ", $panes_win_5));
	$panes5 = str_replace("\n", '', explode(" ", $panes_win_6));
	$panes6 = str_replace("\n", '', explode(" ", $panes_win_7));

	//kill update_binaries.php backfill.php and import-nzb if timer exceeded
	$killit=explode(" ", relativeTime("$newestdate"));
	$killed="false";
	if ( $array['KILL_UPDATES'] != "0" ) {
		if ((( $killit[1] != "secs" ) && ( $killit[1] != "sec" )) && (( $killit[1] == "hrs" ) || ( $killit[1] == "hr" ) || ( $killit[0] >= $array['KILL_UPDATES'] )) && ( $i % 5 == 0 )) {
			$color = get_color();
			shell_exec("$_tmux respawnp -k -t {$array['TMUX_SESSION']}:0.2 'echo \"\033[38;5;\"$color\"m\n$panes0[2] Killed by KILL_UPDATES\" && $ds1 $panes0[2] $ds4'");
			$color = get_color();
			shell_exec("$_tmux respawnp -k -t {$array['TMUX_SESSION']}:0.3 'echo \"\033[38;5;\"$color\"m\n$panes0[3] Killed by KILL_UPDATES\" && $ds1 $panes0[3] $ds4'");
			$color = get_color();
			shell_exec("$_tmux respawnp -k -t {$array['TMUX_SESSION']}:0.4 'echo \"\033[38;5;\"$color\"m\n$panes0[4] Killed by KILL_UPDATES\" && $ds1 $panes0[4] $ds4'");
			if ( $array['KEEP_KILLED'] == "true" ) {
				$killed="true";
			}
		}
	}

	//get state for binaries
	if ( $array['BINARIES'] != "true" ) {
		$binaries_state="disabled";
		$binaries_reason="disabled";
        } elseif ( $array['MAX_LOAD'] <= get_load()) {
                $binaries_state="load-exceeded";
                $binaries_reason="${array['MAX_LOAD']}/".get_load();
	} elseif (( $total_work_used > $array['BINARIES_MAX_RELEASES'] ) && ( $array['BINARIES_MAX_RELEASES'] != 0 )) {
		$binaries_state="pp-exceeded";
		$binaries_reason=number_format("${array['BINARIES_MAX_RELEASES']}");
	} elseif (( $parts_rows_unformatted > $array['BINARIES_MAX_ROWS'] ) && ( $array['BINARIES_MAX_ROWS'] != 0 )) {
		$binaries_state="rows-exceeded";
		$binaries_reason=number_format("${array['BINARIES_MAX_ROWS']}");
	} elseif (( $binaries_rows_unformatted > $array['BINARIES_MAX_BINS'] ) && ( $array['BINARIES_MAX_BINS'] != 0 )) {
                $binaries_state="bins-exceeded";
                $binaries_reason=number_format("${array['BINARIES_MAX_BINS']}");
        } elseif ( $killed == "true" ) {
		$binaries_state="time-exceeded";
		$binaries_reason=$array['KILL_UPDATES']." mins";
	} else {
		$binaries_state="enabled";
		$binaries_reason="enabled";
	}

        //get state for releases
        if ( $array['RELEASES'] != "true" ) {
                $releases_state="disabled";
                $releases_reason="disabled";
        } elseif ( $array['MAX_LOAD_RELEASES'] <= get_load()) {
                $releases_state="load-exceeded";
                $releases_reason="${array['MAX_LOAD_RELEASES']}/".get_load();
        } else {
                $releases_state="enabled";
                $releases_reason="enabled";
        }

	//get state for backfill
	if ( $array['BACKFILL'] != "true" ) {
		$backfill_state="disabled";
		$backfill_reason="disabled";
        } elseif ( $array['MAX_LOAD'] <= get_load()) {
                $backfill_state="load-exceeded";
                $backfill_reason="${array['MAX_LOAD']}/".get_load();
	} elseif (( $total_work_used > $array['BACKFILL_MAX_RELEASES'] ) && ( $array['BACKFILL_MAX_RELEASES'] != 0 )) {
		$backfill_state="pp-exceeded";
		$backfill_reason=number_format("${array['BACKFILL_MAX_RELEASES']}");
	} elseif (( $parts_rows_unformatted > $array['BACKFILL_MAX_ROWS'] ) && ( $array['BACKFILL_MAX_ROWS'] != 0 )) {
		$backfill_state="rows-exceeded";
		$backfill_reason=number_format("${array['BACKFILL_MAX_ROWS']}");
	} elseif (( $binaries_rows_unformatted > $array['BACKFILL_MAX_BINS'] ) && ( $array['BACKFILL_MAX_BINS'] != 0 )) {
                $backfill_state="bins-exceeded";
                $backfill_reason=number_format("${array['BACKFILL_MAX_BINS']}");
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
        } elseif ( $array['MAX_LOAD'] <= get_load()) {
                $import_state="load-exceeded";
                $import_reason="${array['MAX_LOAD']}/".get_load();
	} elseif (( $total_work_used > $array['IMPORT_MAX_RELEASES'] ) && ( $array['IMPORT_MAX_RELEASES'] != 0 )) {
		$import_state="pp-exceeded";
		$import_reason=number_format("${array['IMPORT_MAX_RELEASES']}");
	} elseif (( $parts_rows_unformatted > $array['IMPORT_MAX_ROWS'] ) && ( $array['IMPORT_MAX_ROWS'] != 0 )) {
		$import_state="rows-exceeded";
		$import_reason=number_format("${array['IMPORT_MAX_ROWS']}");
	} elseif ( $killed == "true" ) {
		$import_state="time-exceeded";
		$import_reason=$array['KILL_UPDATES']." mins";
        } else {
		$import_state="enabled";
		$import_reason="enabled";
	}

    //get usenet connections, borrowed from nZEDb
$uspactiveconnections = str_replace("\n", '', shell_exec ("ss -n | grep :".NNTP_PORT." | grep -c ESTAB"));
$usptotalconnections  = str_replace("\n", '', shell_exec ("ss -n | grep -c :".NNTP_PORT.""));

	//update display
	passthru('clear');
	//printf("\033[1;31m  First insert:\033[0m ".relativeTime("$firstdate")."\n");
	printf($mask2, "Monitor Running v$version: ", relativeTime("$time"));
    printf($mask1, "USP Connections:" ,$uspactiveconnections." active (".$usptotalconnections." total used) - ".NNTP_SERVER);
	printf($mask1, "Newest Release:", "$newestname");
	printf($mask1, "Release Added:", relativeTime("$newestdate")."ago");
    if ($array['PREDB'] = "true"){
    printf($mask1, "Predb Updated:", relativeTime("$newestpredb")."ago");
    }
if ($array ['FIXRELEASES'] = "true") {
    printf($mask1, "Prehash Updated:", relativeTime("$newestprehash")."ago");
    }

	printf("\033[1;33m\n");
	printf($mask, "Category", "State", "Reason");
	printf($mask, "====================", "====================", "====================");
	printf("\033[38;5;214m");
	printf($mask, "Binaries", "$binaries_state", "$binaries_reason");
	printf($mask, "Backfill", "$backfill_state", "$backfill_reason");
	printf($mask, "Import", "$import_state", "$import_reason");
    printf($mask, "Releases", "$releases_state", "$releases_reason");
	printf($mask, "Parts", "$parts_size_gb", "$parts_rows rows");
	printf($mask, "Binaries", "$binaries_size_gb", $binaries_rows."/".$binaries_total." bins");
	if ( $array['RAMDISK_PATH'] != "" ) {
		printf($mask, "Ramdisk", "$disk_use used", "$disk_free free");
	}

	printf("\033[1;33m\n");
	printf($mask, "Category", "In Process", "In Database");
	printf($mask, "====================", "====================", "====================");
	printf("\033[38;5;214m");
    if ($array ['FIXRELEASES'] = "true"){
    printf($mask, "prehash","~".number_format($prehash - $prehash_matched)."(".$pre_diff.")",number_format($prehash_matched)."(".$pre_percent."%)");
}
	printf($mask, "NFO's","$nfo_remaining_now_formatted($nfo_diff)","$nfo_now_formatted($nfo_percent%)");
	printf($mask, "Console(1000)","$console_releases_proc_formatted($console_diff)","$console_releases_now_formatted($console_percent%)");
	printf($mask, "Movie(2000)","$movie_releases_proc_formatted($movie_diff)","$movie_releases_now_formatted($movie_percent%)");
	printf($mask, "Audio(3000)","$music_releases_proc_formatted($music_diff)","$music_releases_now_formatted($music_percent%)");
	printf($mask, "PC(4000)","$pc_releases_proc_formatted($pc_diff)","$pc_releases_now_formatted($pc_percent%)");
	printf($mask, "TVShows(5000)","$tvrage_releases_proc_formatted($tvrage_diff)","$tvrage_releases_now_formatted($tvrage_percent%)");
    printf($mask, "XXX(6000)","$xxx_releases_proc_formatted($xxx_diff)","$xxx_releases_now_formatted($xxx_percent%)");
	printf($mask, "Books(7000)","$book_releases_proc_formatted($book_diff)","$book_releases_now_formatted($book_percent%)");
	printf($mask, "Misc(8000)","$misc_remaining_now_formatted($misc_diff)","$misc_releases_now_formatted($misc_percent%)");
	printf($mask, "Total", "$total_work_now_formatted($work_diff)", "$releases_now_formatted($releases_since_start)");

	printf("\n\033[1;33m");
	printf($mask, "Category", "Time", "Status");
	printf($mask, "====================", "====================", "====================");
	printf("\033[38;5;214m");
        $get_current_number = str_replace("\n", '', shell_exec($mysqladmin)." qps");
        printf($mask, "DB Lagg","$query_timer","$get_current_number");

    printf("\n\033[1;33m\n");
    printf($mask, "Groups", "Active", "Backfill");
    printf($mask, "====================", "=========================", "=========================");
    printf("\033[38;5;214m");
    printf($mask, "Activated", $active_groups."(".$all_groups.")", $backfill_groups. "(".$all_groups.")");

	$optimize_safe_to_run = "false";
	$optimize_run = "false";
	$dead1=0;
	$dead2=0;
	$dead3=0;
	$dead4=0;
	$dead5=0;
	$dead6=0;
	$dead7=0;

	//kill all panes to run optimize if OPTIMIZE_KILL is true
	if ( $array['INNODB'] == "true" ) {
		if ((( TIME() - $time6 >= $array['MYISAM_LARGE'] ) || ( TIME() - $time8 >= $array['INNODB_LARGE'] ) || ( TIME() - $time5 >= $array['INNODB_SMALL'] ) || ( TIME() - $time11 >= $array['MYISAM_SMALL'] )) && ( $array['OPTIMIZE_KILL'] != "true" ) && ( $array['OPTIMIZE'] == "true" )) {
			$optimize_safe_to_run="true";
			$dead1 = str_replace( " ", '', `tmux list-panes -t {$array['TMUX_SESSION']}:0 | grep dead | wc -l` );
			$dead2 = str_replace( " ", '', `tmux list-panes -t {$array['TMUX_SESSION']}:1 | grep dead | wc -l` );
			$dead3 = str_replace( " ", '', `tmux list-panes -t {$array['TMUX_SESSION']}:2 | grep dead | wc -l` );
			$dead4 = str_replace( " ", '', `tmux list-panes -t {$array['TMUX_SESSION']}:3 | grep dead | wc -l` );
			$dead5 = str_replace( " ", '', `tmux list-panes -t {$array['TMUX_SESSION']}:4 | grep dead | wc -l` );
			$dead6 = str_replace( " ", '', `tmux list-panes -t {$array['TMUX_SESSION']}:5 | grep dead | wc -l` );
			$dead7 = str_replace( " ", '', `tmux list-panes -t {$array['TMUX_SESSION']}:6 | grep dead | wc -l` );
			if (( $dead1 >= 4 ) && ( $dead2 == 6 ) && ( $dead3 == 16 ) && ( $dead4 == 16 ) && ( $dead5 == 8 ) && ( $dead6 == 8 ) && ( $dead7 == 3 )) {
				$optimize_run="true";
			} else {
				$optimize_run="false";
			}
		} elseif ((( TIME() - $time6 >= $array['MYISAM_LARGE'] ) || ( TIME() - $time8 >= $array['INNODB_LARGE'] ) || ( TIME() - $time5 >= $array['INNODB_SMALL'] ) || ( TIME() - $time11 >= $array['MYISAM_SMALL'] )) && ( $array['OPTIMIZE_KILL'] == "true" ) && ( $array['OPTIMIZE'] == "true" )) {
			for ($g=2; $g<=5; $g++)
			{
				$color = get_color();
				shell_exec("$_tmux respawnp -k -t {$array['TMUX_SESSION']}:0.$g 'echo \"\033[38;5;\"$color\"m\n$panes0[$g]\nKilled in prep for \nOptimization\" && date +\"%D %T\" && echo \"This is color #$color\" && $ds1 $panes0[$g] $ds4'");
			}
			for ($g=0; $g<=6; $g++)
			{
				$color = get_color();
				shell_exec("$_tmux respawnp -k -t {$array['TMUX_SESSION']}:1.$g 'echo \"\033[38;5;\"$color\"m\n$panes1[$g]\nKilled in prep for \nOptimization\" && date +\"%D %T\" && echo \"This is color #$color\" && $ds1 $panes1[$g] $ds4'");
				$color = get_color();
            }
            for ($g=0; $g<=8; $g++)
            {
                $color = get_color();
				shell_exec("$_tmux respawnp -k -t {$array['TMUX_SESSION']}:4.$g 'echo \"\033[38;5;\"$color\"m\n$panes4[$g]\nKilled in prep for \nOptimization\" && date +\"%D %T\" && echo \"This is color #$color\" && $ds1 $panes4[$g] $ds4'");
				$color = get_color();
				shell_exec("$_tmux respawnp -k -t {$array['TMUX_SESSION']}:5.$g 'echo \"\033[38;5;\"$color\"m\n$panes5[$g]\nKilled in prep for \nOptimization\" && date +\"%D %T\" && echo \"This is color #$color\" && $ds1 $panes5[$g] $ds4'");
			}
			for ($g=0; $g<=15; $g++)
			{
				$color = get_color();
				shell_exec("$_tmux respawnp -k -t {$array['TMUX_SESSION']}:2.$g 'echo \"\033[38;5;\"$color\"m\n$panes2[$g]\nKilled in prep for \nOptimization\" && date +\"%D %T\" && echo \"This is color #$color\" && $ds1 $panes2[$g] $ds4'");
				$color = get_color();
				shell_exec("$_tmux respawnp -k -t {$array['TMUX_SESSION']}:3.$g 'echo \"\033[38;5;\"$color\"m\n$panes3[$g]\nKilled in prep for \nOptimization\" && date +\"%D %T\" && echo \"This is color #$color\" && $ds1 $panes3[$g] $ds4'");
			}
			for ($g=0; $g<=3; $g++)
			{
				$color = get_color();
				shell_exec("$_tmux respawnp -k -t {$array['TMUX_SESSION']}:6.$g 'echo \"\033[38;5;\"$color\"m\n$panes6[$g]\nKilled in prep for \nOptimization\" && date +\"%D %T\" && echo \"This is color #$color\" && $ds1 $panes6[$g] $ds4'");
			}
			sleep(5);
			$optimize_run="true";
			$optimize_safe_to_run="true";
		}
	} else {
		if ((( TIME() - $time6 >= $array['MYISAM_LARGE'] ) || ( TIME() - $time11 >= $array['MYISAM_SMALL'] )) && ( $array['OPTIMIZE_KILL'] != "true" ) && ( $array['OPTIMIZE'] == "true" )) {
			$optimize_safe_to_run="true";
			$dead1 = str_replace( " ", '', `tmux list-panes -t {$array['TMUX_SESSION']}:0 | grep dead | wc -l` );
			$dead2 = str_replace( " ", '', `tmux list-panes -t {$array['TMUX_SESSION']}:1 | grep dead | wc -l` );
			$dead3 = str_replace( " ", '', `tmux list-panes -t {$array['TMUX_SESSION']}:2 | grep dead | wc -l` );
			$dead4 = str_replace( " ", '', `tmux list-panes -t {$array['TMUX_SESSION']}:3 | grep dead | wc -l` );
			$dead5 = str_replace( " ", '', `tmux list-panes -t {$array['TMUX_SESSION']}:4 | grep dead | wc -l` );
			$dead6 = str_replace( " ", '', `tmux list-panes -t {$array['TMUX_SESSION']}:5 | grep dead | wc -l` );
			$dead7 = str_replace( " ", '', `tmux list-panes -t {$array['TMUX_SESSION']}:6 | grep dead | wc -l` );
			if (( $dead1 >= 4 ) && ( $dead2 == 6 ) && ( $dead3 == 16 ) && ( $dead4 == 16 ) && ( $dead5 == 8 ) && ( $dead6 == 8 ) && ($dead7 == 3)) {
				$optimize_run="true";
			} else {
				$optimize_run="false";
			}
		} elseif ((( TIME() - $time6 >= $array['MYISAM_LARGE'] ) || ( TIME() - $time11 >= $array['MYISAM_SMALL'] )) && ( $array['OPTIMIZE_KILL'] == "true" ) && ( $array['OPTIMIZE'] == "true" )) {
			for ($g=2; $g<=5; $g++)
			{
				$color = get_color();
				shell_exec("$_tmux respawnp -k -t {$array['TMUX_SESSION']}:0.$g 'echo \"\033[38;5;\"$color\"m\n$panes0[$g]\nKilled in prep for \nOptimization\" && date +\"%D %T\" && echo \"This is color #$color\" && $ds1 $panes0[$g] $ds4'");
			}
			for ($g=0; $g<=6; $g++)
			{
				$color = get_color();
				shell_exec("$_tmux respawnp -k -t {$array['TMUX_SESSION']}:1.$g 'echo \"\033[38;5;\"$color\"m\n$panes1[$g]\nKilled in prep for \nOptimization\" && date +\"%D %T\" && echo \"This is color #$color\" && $ds1 $panes1[$g] $ds4'");
				$color = get_color();
            }
            for ($g=0; $g<=8; $g++)
            {
				shell_exec("$_tmux respawnp -k -t {$array['TMUX_SESSION']}:4.$g 'echo \"\033[38;5;\"$color\"m\n$panes4[$g]\nKilled in prep for \nOptimization\" && date +\"%D %T\" && echo \"This is color #$color\" && $ds1 $panes4[$g] $ds4'");
				$color = get_color();
				shell_exec("$_tmux respawnp -k -t {$array['TMUX_SESSION']}:5.$g 'echo \"\033[38;5;\"$color\"m\n$panes5[$g]\nKilled in prep for \nOptimization\" && date +\"%D %T\" && echo \"This is color #$color\" && $ds1 $panes5[$g] $ds4'");
			}
			for ($g=0; $g<=15; $g++)
			{
				$color = get_color();
				shell_exec("$_tmux respawnp -k -t {$array['TMUX_SESSION']}:2.$g 'echo \"\033[38;5;\"$color\"m\n$panes2[$g]\nKilled in prep for \nOptimization\" && date +\"%D %T\" && echo \"This is color #$color\" && $ds1 $panes2[$g] $ds4'");
				$color = get_color();
				shell_exec("$_tmux respawnp -k -t {$array['TMUX_SESSION']}:3.$g 'echo \"\033[38;5;\"$color\"m\n$panes3[$g]\nKilled in prep for \nOptimization\" && date +\"%D %T\" && echo \"This is color #$color\" && $ds1 $panes3[$g] $ds4'");
			}
			for ($g=0; $g<=3; $g++)
			{
				$color = get_color();
				shell_exec("$_tmux respawnp -k -t {$array['TMUX_SESSION']}:6.$g 'echo \"\033[38;5;\"$color\"m\n$panes6[$g]\nKilled in prep for \nOptimization\" && date +\"%D %T\" && echo \"This is color #$color\" && $ds1 $panes6[$g] $ds4'");
			}
			sleep(5);
			$optimize_run="true";
			$optimize_safe_to_run="true";
		}
	}

	//run optimize in pane 1.5
	if ( $array['INNODB'] == "true" ) {
		if (( $array['MAX_LOAD'] >= get_load()) && ( TIME() - $time6 >= $array['MYISAM_LARGE'] ) && ( $array['OPTIMIZE'] == "true" ) && ( $optimize_run == "true" )) {
			$color = get_color();
			$log = writelog($panes1[5]);
			shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.5 'echo \"\033[38;5;\"$color\"m\" && $ds1 MYISAM_LARGE $ds2 && cd $_user && ./${array['USER_DEF_TWO']} 2>&1 $log && cd $_bin && $_php optimize_myisam.php true 2>&1 $log && cd $_user && ./${array['USER_DEF_THREE']} 2>&1 $log && echo \" \033[1;0;33m\" && $ds1 MYISAM_LARGE $ds3' 2>&1 1> /dev/null");
			echo "\033[1;41;33mOPTIMIZATION OF THE MYSQL TABLES HAS STARTED, DO NOT STOP THIS SCRIPT!\033[1;0;33m\n\n";
			$time6 = TIME();
		} elseif (( $array['MAX_LOAD'] >= get_load()) && ( TIME() - $time8 >= $array['INNODB_LARGE'] ) && ( $array['OPTIMIZE'] == "true" ) && ( $optimize_run == "true" )) {
			$color = get_color();
			$log = writelog($panes1[5]);
			shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.5 'echo \"\033[38;5;\"$color\"m\" && $ds1 INNODB_LARGE $ds2 && cd $_user && ./${array['USER_DEF_FOUR']} 2>&1 $log && cd $_bin && $_php optimize_myisam.php true 2>&1 $log && $_php optimize_innodb.php true 2>&1 $log && cd $_user && ./${array['USER_DEF_FIVE']} 2>&1 $log && echo \" \033[1;0;33m\" && $ds1 INNODB_LARGE $ds3' 2>&1 1> /dev/null");
			echo "\033[1;41;33mOPTIMIZATION OF THE MYSQL TABLES HAS STARTED, DO NOT STOP THIS SCRIPT!\033[1;0;33m\n\n";
			$time8 = TIME();
		} elseif (( $array['MAX_LOAD'] >= get_load()) && ( TIME() - $time5 >= $array['INNODB_SMALL'] ) && ( $array['INNODB']== "true" ) && ( $array['OPTIMIZE'] == "true" ) && ( $optimize_run == "true" )) {
			$color = get_color();
			$log = writelog($panes1[5]);
			shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.5 'echo \"\033[38;5;\"$color\"m\" && $ds1 INNODB_SMALL $ds2 && cd $_bin && $_php optimize_innodb.php 2>&1 $log && echo \" \033[1;0;33m\" && $ds1 INNODB_SMALL $ds3' 2>&1 1> /dev/null");
			echo "\033[1;41;33mOPTIMIZATION OF THE MYSQL TABLES HAS STARTED, DO NOT STOP THIS SCRIPT!\033[1;0;33m\n\n";
			$time5 = TIME();
		} elseif (( $array['MAX_LOAD'] >= get_load()) && ( TIME() - $time11 >= $array['MYISAM_SMALL'] ) && ( $array['OPTIMIZE'] == "true" ) && ( $optimize_run == "true" )) {
			$color = get_color();
			$log = writelog($panes1[5]);
			shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.5 'echo \"\033[38;5;\"$color\"m\" && $ds1 MYISAM_SMALL $ds2 && cd $_bin && $_php optimize_myisam.php 2>&1 $log && echo \" \033[1;0;33m\" && $ds1 MYISAM_SMALL $ds3' 2>&1 1> /dev/null");
			echo "\033[1;41;33mOPTIMIZATION OF THE MYSQL TABLES HAS STARTED, DO NOT STOP THIS SCRIPT!\033[1;0;33m\n\n";
			$time11 = TIME();
		} elseif (( $array['OPTIMIZE'] == "true" ) && ( $array['MAX_LOAD'] <= get_load())) {
                        $color = get_color();
                        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.5 'echo \"\033[38;5;\"$color\"m\nOptimize Disabled by MAX_LOAD\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
                } elseif ( $array['OPTIMIZE'] != "true" ) {
			$color = get_color();
			shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.5 'echo \"\033[38;5;\"$color\"m\nOptimize Disabled by OPTIMIZE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
		} else {
			$color = get_color();
			$run_time1 = relativeTime( $array['MYISAM_LARGE'] + $time6 );
			$run_time2 = relativeTime( $array['INNODB_LARGE'] + $time8 );
			$run_time3 = relativeTime( $array['INNODB_SMALL'] + $time5 );
			$run_time4 = relativeTime( $array['MYISAM_SMALL'] + $time11 );
			shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.5 'echo \"\033[38;5;\"$color\"m\nMYISAM_LARGE will run in T[ $run_time1]\nINNODB_LARGE will run in T[ $run_time2]\nINNODB_SMALL will run in T[ $run_time3]\nMYISAM_SMALL will run in T[ $run_time4]\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
		}
	} else {
		if (( $array['MAX_LOAD'] >= get_load()) && ( TIME() - $time6 >= $array['MYISAM_LARGE'] ) && ( $array['OPTIMIZE'] == "true" ) && ( $optimize_run == "true" )) {
			$color = get_color();
			$log = writelog($panes1[5]);
			shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.5 'echo \"\033[38;5;\"$color\"m\" && $ds1 MYISAM_LARGE $ds2 && cd $_user && ./${array['USER_DEF_TWO']} 2>&1 $log && cd $_bin && $_php optimize_myisam.php true 2>&1 $log && cd $_user && ./${array['USER_DEF_THREE']} 2>&1 $log && echo \" \033[1;0;33m\" && $ds1 MYISAM_LARGE $ds3' 2>&1 1> /dev/null");
			echo "\033[1;41;33mOPTIMIZATION OF THE MYSQL TABLES HAS STARTED, DO NOT STOP THIS SCRIPT!\033[1;0;33m\n\n";
			$time6 = TIME();
		} elseif (( $array['MAX_LOAD'] >= get_load()) && ( TIME() - $time11 >= $array['MYISAM_SMALL'] ) && ( $array['OPTIMIZE'] == "true" ) && ( $optimize_run == "true" )) {
			$color = get_color();
			$log = writelog($panes1[5]);
			shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.5 'echo \"\033[38;5;\"$color\"m\" && $ds1 MYISAM_SMALL $ds2 && cd $_bin && $_php optimize_myisam.php 2>&1 $log && echo \" \033[1;0;33m\" && $ds1 MYISAM_SMALL $ds3' 2>&1 1> /dev/null");
			echo "\033[1;41;33mOPTIMIZATION OF THE MYSQL TABLES HAS STARTED, DO NOT STOP THIS SCRIPT!\033[1;0;33m\n\n";
			$time11 = TIME();
		} elseif (( $array['OPTIMIZE'] == "true" ) && ( $array['MAX_LOAD'] <= get_load())) {
                        $color = get_color();
                        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.5 'echo \"\033[38;5;\"$color\"m\nOptimize Disabled by MAX_LOAD\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
                } elseif ( $array['OPTIMIZE'] != "true" ) {
			$color = get_color();
			shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.5 'echo \"\033[38;5;\"$color\"m\nOptimize Disabled by OPTIMIZE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
		} else {
			$color = get_color();
			$run_time1 = relativeTime( $array['MYISAM_LARGE'] + $time6 );
			$run_time4 = relativeTime( $array['MYISAM_SMALL'] + $time11 );
			shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.5 'echo \"\033[38;5;\"$color\"m\nMYISAM_LARGE will run in T[ $run_time1]\nMYISAM_SMALL will run in T[ $run_time4]\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
		}
	}

	//set command for running update_binaries
	if (( $array['BINARIES_THREADS'] == "true" ) && ( $optimize_safe_to_run != "true" )) {
		$log = writelog($panes0[2]);
		$_update_cmd = "cd $_bin && $_php update_binaries_threaded.php 2>&1 $log";
	} else {
		$log = writelog($panes0[2]);
		$_update_cmd = "cd $_bin && $_php update_binaries.php 2>&1 $log";
	}

	//set command for running backfill
	if ( $array['KEVIN_SAFER'] == "true" ) {
		$log = writelog($panes0[3]);
		$_backfill_cmd = "cd $_bin && $_php safer_backfill_parts.php 2>&1 $log";
	} elseif ( $array['KEVIN_BACKFILL_PARTS'] == "true" ) {
		$log = writelog($panes0[3]);
		$_backfill_cmd = "cd $_bin && $_php backfill_parts.php 2>&1 $log";
	} elseif ( $array['KEVIN_THREADED'] == "true" ) {
		$log = writelog($panes0[3]);
		$_backfill_cmd = "cd $_bin && $_php backfill_parts_threaded.php 2>&1 $log";
	} elseif ( $array['BACKFILL_THREADS'] == "true" ) {
		$log = writelog($panes0[3]);
		$_backfill_cmd = "cd $_bin && $_php backfill_threaded.php 2>&1 $log && $mysql_command_1 2>&1 $log";
	} else {
		$log = writelog($panes0[3]);
		$_backfill_cmd = "cd $_bin && $_php backfill.php 2>&1 $log && $mysql_command_1 2>&1 $log";
	}

	//set command for nzb-import
	if (( $array['NZB_THREADS'] == "true" ) && ( $optimize_safe_to_run != "true" )) {
		$nzb_cmd = "$_php nzb-import-sub.php \"{$array['NZBS']}\" 2>&1 $log";
	} else {
		$nzb_cmd = "$_php nzb-import.php \"{$array['NZBS']}\" \"{$array['IMPORT_TRUE']}\" 2>&1 $log";
	}

	//check if sequential is set
	if ( $array['SEQUENTIAL'] != "true" ) {
		//runs update_binaries in 0.2 once if needed and exits
		if (( $array['MAX_LOAD'] >= get_load()) && ( $killed != "true" ) && ( $array['BINARIES'] == "true" ) && (( $binaries_rows_unformatted < $array['BINARIES_MAX_BINS'] ) || ( $array['BINARIES_MAX_BINS'] == 0 )) && (( $total_work_used < $array['BINARIES_MAX_RELEASES'] ) || ( $array['BINARIES_MAX_RELEASES'] == 0 )) && (( $parts_rows_unformatted < $array['BINARIES_MAX_ROWS'] ) || ( $array['BINARIES_MAX_ROWS'] == 0 )) && ( $optimize_safe_to_run != "true" )) {
			$color = get_color();
			$log = writelog($panes0[2]);
			shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.2 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes0[2] $ds2 && $_update_cmd 2>&1 $log && echo \" \033[1;0;33m\" && echo \"sleeping\033[38;5;\"$color\"m {$array['BINARIES_SLEEP']} seconds...\" && sleep {$array['BINARIES_SLEEP']} && $ds1 $panes0[2] $ds3' 2>&1 1> /dev/null");
		} elseif (( $parts_rows_unformatted > $array['BINARIES_MAX_ROWS'] ) && ( $array['BINARIES'] == "true" ) && ( $array['BINARIES_MAX_ROWS'] != 0 ) && ( $optimize_safe_to_run != "true" )) {
			$color = get_color();
			shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.2 'echo \"\033[38;5;\"$color\"m\nBINARIES_MAX_ROWS exceeded\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
		} elseif (( $total_work_used > $array['BINARIES_MAX_RELEASES'] ) && ( $array['BINARIES'] == "true" ) && ( $array['BINARIES_MAX_RELEASES'] != 0 ) && ( $optimize_safe_to_run != "true" )) {
			$color = get_color();
			shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.2 'echo \"\033[38;5;\"$color\"m\nBINARIES_MAX_RELEASES exceeded\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
		} elseif (( $array['BINARIES'] == "true" ) && ( $optimize_safe_to_run != "true" ) && ( $array['MAX_LOAD'] <= get_load())) {
                        $color = get_color();
                        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.2 'echo \"\033[38;5;\"$color\"m\n$panes0[2] Disabled by MAX_LOAD\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
                } elseif (( $array['BINARIES'] != "true" ) && ( $optimize_safe_to_run != "true" )) {
			$color = get_color();
			shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.2 'echo \"\033[38;5;\"$color\"m\n$panes0[2] Disabled by BINARIES\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
		} elseif ( $optimize_safe_to_run == "true" ) {
			$color = get_color();
			shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.2 'echo \"\033[38;5;\"$color\"m\n$panes0[2] Disabled by OPTIMIZE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
		}

		//runs backfill in 0.3 once if needed and exits
		if (( $array['MAX_LOAD'] >= get_load()) && ( $killed != "true" ) && ( $array['BACKFILL'] == "true" ) && (( $binaries_rows_unformatted < $array['BACKFILL_MAX_BINS'] ) || ( $array['BACKFILL_MAX_BINS'] == 0 )) && (( $total_work_used < $array['BACKFILL_MAX_RELEASES'] ) || ( $array['BACKFILL_MAX_RELEASES'] == 0 )) && (( $parts_rows_unformatted < $array['BACKFILL_MAX_ROWS'] ) || ( $array['BACKFILL_MAX_ROWS'] == 0 )) && ( $optimize_safe_to_run != "true" )) {
			$color = get_color();
			$log = writelog($panes0[3]);
			shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.3 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes0[3] $ds2 && $_backfill_cmd && echo \" \033[1;0;33m\" && echo \"sleeping\033[38;5;\"$color\"m {$array['BACKFILL_SLEEP']} seconds...\" && sleep {$array['BACKFILL_SLEEP']} && $ds1 $panes0[3] $ds3' 2>&1 1> /dev/null");
		} elseif (( $parts_rows_unformatted > $array['BACKFILL_MAX_ROWS'] ) && ( $array['BACKFILL'] == "true" ) && ( $array['BACKFILL_MAX_ROWS'] != 0 ) && ( $optimize_safe_to_run != "true" )) {
			$color = get_color();
			shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.3 'echo \"\033[38;5;\"$color\"m\nBACKFILL_MAX_ROWS exceeded\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
		} elseif (( $total_work_used > $array['BACKFILL_MAX_RELEASES'] ) && ( $array['BACKFILL'] == "true" ) && ( $array['BACKFILL_MAX_RELEASES'] != 0 ) && ( $optimize_safe_to_run != "true" )) {
			$color = get_color();
			shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.3 'echo \"\033[38;5;\"$color\"m\nBACKFILL_MAX_RELEASES exceeded\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
		} elseif (( $array['BACKFILL'] == "true" ) && ( $optimize_safe_to_run != "true" ) && ( $array['MAX_LOAD'] <= get_load())) {
                        $color = get_color();
                        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.3 'echo \"\033[38;5;\"$color\"m\n$panes0[3] Disabled by MAX_LOAD\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
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
	if (( $array['SEQUENTIAL'] == "true" ) && ( strlen( $dead ) > "4" )) {
		if (( $array['MAX_LOAD'] >= get_load()) && (( $i == 1 ) || ( TIME() - $time13 >= $array['BINARIES_SEQ_TIMER'] )) && (( $binaries_rows_unformatted < $array['BINARIES_MAX_BINS'] ) || ( $array['BINARIES_MAX_BINS'] == 0 )) && ( $array['BINARIES'] == "true" ) && (( $total_work_used < $array['BINARIES_MAX_RELEASES'] ) || ( $array['BINARIES_MAX_RELEASES'] == 0 )) && (( $parts_rows_unformatted < $array['BINARIES_MAX_ROWS'] ) || ( $array['BINARIES_MAX_ROWS'] == 0 )) && ( $optimize_safe_to_run != "true" )) {
			$color = get_color();
			shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.2 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes0[2] $ds2 && $_update_cmd && echo \" \033[1;0;33m\" && $ds1 $panes0[2] $ds3' 2>&1 1> /dev/null");
			$time13 = TIME();
		} elseif (( $array['MAX_LOAD'] >= get_load()) && ( TIME() - $time14 >= $array['BACKFILL_SEQ_TIMER'] ) && (( $binaries_rows_unformatted < $array['BACKFILL_MAX_BINS'] ) || ( $array['BACKFILL_MAX_BINS'] == 0 )) && ( $array['BACKFILL'] == "true" ) && (( $total_work_used < $array['BACKFILL_MAX_RELEASES'] ) || ( $array['BACKFILL_MAX_RELEASES'] == 0 )) && (( $parts_rows_unformatted < $array['BACKFILL_MAX_ROWS'] ) || ( $array['BACKFILL_MAX_ROWS'] == 0 )) && ( $optimize_safe_to_run != "true" )) {
			$color = get_color();
			shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.2 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes0[3] $ds2 && $_backfill_cmd && echo \" \033[1;0;33m\" && $ds1 $panes0[3] $ds3' 2>&1 1> /dev/null");
			$time14 = TIME();
		} elseif (( $array['BINARIES'] != "true" ) && ( $array['BACKFILL'] != "true" ) && ( $optimize_safe_to_run != "true" )) {
			$color = get_color();
			shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.2 'echo \"\033[38;5;\"$color\"m\n$panes0[2] and $panes0[3] Disabled by BINARIES and BACKFILL\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
		} elseif (( $array['BACKFILL'] == "true" ) && ( $array['BINARIES'] == "true" ) && ( $optimize_safe_to_run != "true" ) && ( $array['MAX_LOAD'] >= get_load())) {
			$color = get_color();
			$run_time1 = relativeTime( $array['BACKFILL_SEQ_TIMER'] + $time14 );
			$run_time2 = relativeTime( $array['BINARIES_SEQ_TIMER'] + $time13 );
			shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.2 'echo \"\033[38;5;\"$color\"m\n$panes0[2] will run in T[ $run_time2]\n$panes0[3] will run in T[ $run_time1] \" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
		} elseif (( $array['BACKFILL'] == "true" ) && ( $optimize_safe_to_run != "true" ) && ( $array['MAX_LOAD'] >= get_load())) {
			$color = get_color();
			$run_time = relativeTime( $array['BACKFILL_SEQ_TIMER'] + $time14 );
			shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.2 'echo \"\033[38;5;\"$color\"m\n$panes03] will run in T[ $run_time1]\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
		} elseif (( $array['BINARIES'] == "true" ) && ( $optimize_safe_to_run != "true" ) && ( $array['MAX_LOAD'] >= get_load())) {
			$color = get_color();
			$run_time = relativeTime( $array['BINARIES_SEQ_TIMER'] + $time13 );
			shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.2 'echo \"\033[38;5;\"$color\"m\n$panes0[2] will run in T[ $run_time2]\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
		} elseif (( $optimize_safe_to_run != "true" ) && ( $array['MAX_LOAD'] <= get_load())) {
                        $color = get_color();
                        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.2 'echo \"\033[38;5;\"$color\"m\n$panes0[2] Disabled by MAX_LOAD\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
                } elseif ( $optimize_safe_to_run == "true" ) {
			$color = get_color();
			shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.2 'echo \"\033[38;5;\"$color\"m\n$panes0[2] Disabled by OPTIMIZE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
		}
	}

	//notify pane 0.3 of the status of pane 0.2
	if ( $array['SEQUENTIAL'] == "true" ) {
		if (( $array['MAX_LOAD'] >= get_load()) && ( $array['BINARIES'] != "true" ) && ( $array['BACKFILL'] != "true" ) && ( $optimize_safe_to_run != "true" )) {
			$color = get_color();
			shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.3 'echo \"\033[38;5;\"$color\"m\n$panes0[2] and $panes0[3] Disabled by BINARIES and BACKFILL\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
		} elseif (( $array['BACKFILL'] == "true" ) && ( $array['BINARIES'] == "true" ) && ( $optimize_safe_to_run != "true" ) && ( $array['MAX_LOAD'] >= get_load())) {
			$run_time1 = relativeTime( $array['BACKFILL_SEQ_TIMER'] + $time14 );
			$run_time2 = relativeTime( $array['BINARIES_SEQ_TIMER'] + $time13 );
			$color = get_color();
			shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.3 'echo \"\033[38;5;\"$color\"m\n$panes0[2] will run in T[ $run_time2]\n$panes0[3] will run in T[ $run_time1] \" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
		} elseif (( $array['BACKFILL'] == "true" ) && ( $optimize_safe_to_run != "true" ) && ( $array['MAX_LOAD'] >= get_load())) {
			$run_time = relativeTime( $array['BACKFILL_SEQ_TIMER'] + $time14 );
			$color = get_color();
			shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.3 'echo \"\033[38;5;\"$color\"m\n$panes0[3] will run in T[ $run_time1]\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
		} elseif (( $array['BINARIES'] == "true" ) && ( $optimize_safe_to_run != "true" ) && ( $array['MAX_LOAD'] >= get_load())) {
			$run_time = relativeTime( $array['BINARIES_SEQ_TIMER'] + $time13 );
			$color = get_color();
			shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.3 'echo \"\033[38;5;\"$color\"m\n$panes0[2] will run in T[ $run_time2]\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
		} elseif (( $optimize_safe_to_run != "true" ) && ( $array['MAX_LOAD'] <= get_load())) {
                        $color = get_color();
                        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.3 'echo \"\033[38;5;\"$color\"m\n$panes0[3] Disabled by MAX_LOAD\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
                } elseif ( $optimize_safe_to_run == "true" ) {
			$color = get_color();
			shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.3 'echo \"\033[38;5;\"$color\"m\n$panes0[3] Disabled by OPTIMIZE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
		}
	}

	//kill panes for sequential
	if ( $array['SEQUENTIAL'] == "true" ) {
		if (( $parts_rows_unformatted > $array['BINARIES_MAX_ROWS'] ) && ( $array['BINARIES'] == "true" ) && ( $array['BINARIES_MAX_ROWS'] != 0 ) && ( $array['BACKFILL'] == "true" ) && ( $total_work_used > $array['BACKFILL_MAX_RELEASES'] ) && ( $array['BACKFILL_MAX_ROWS'] != 0 ) && ( $optimize_safe_to_run != "true" )) {
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
	if (( $array['MAX_LOAD'] >= get_load()) && ( $killed != "true" ) && ( $array['IMPORT'] == "true" ) && (( $total_work_used < $array['IMPORT_MAX_RELEASES'] ) || ( $array['IMPORT_MAX_RELEASES'] == 0 )) && (( $parts_rows_unformatted < $array['IMPORT_MAX_ROWS'] ) || ( $array['IMPORT_MAX_ROWS'] == 0 )) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		$log = writelog($panes0[4]);
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.4 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes0[4] $ds2 && cd $_bin && $nzb_cmd && echo \" \" && echo \" \033[1;0;33m\" && echo \"sleeping\033[38;5;\"$color\"m {$array['IMPORT_SLEEP']} seconds...\" && sleep {$array['IMPORT_SLEEP']} && $ds1 $panes0[4] $ds3' 2>&1 1> /dev/null");
		$color = get_color();
		$log = writelog($panes0[1]);
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.1 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes0[1] $ds2 && cd $_bin && $_php nzbcount.php 2>&1 $log' 2>&1 1> /dev/null");

	} elseif (( $parts_rows_unformatted > $array['IMPORT_MAX_ROWS'] ) && ( $array['IMPORT'] == "true" ) && ( $array['IMPORT_MAX_ROWS'] != 0 ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.4 'echo \"\033[38;5;\"$color\"m\nIMPORT_MAX_ROWS exceeded\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");

	} elseif (( $total_work_used > $array['IMPORT_MAX_RELEASES'] ) && ( $array['IMPORT'] == "true" ) && ( $array['IMPORT_MAX_RELEASES'] != 0 ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.4 'echo \"\033[38;5;\"$color\"m\nIMPORT_MAX_RELEASES exceeded\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");

	} elseif (( $array['IMPORT'] == "true" ) && ( $optimize_safe_to_run != "true" ) && ( $array['MAX_LOAD'] <= get_load())) {
                $color = get_color();
                shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.4 'echo \"\033[38;5;\"$color\"m\n$panes0[4] Disabled by MAX_LOAD\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");

        } elseif (( $array['IMPORT'] != "true" ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.4 'echo \"\033[38;5;\"$color\"m\n$panes0[4] Disabled by IMPORT\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");

	} elseif ( $optimize_safe_to_run == "true" ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.4 'echo \"\033[38;5;\"$color\"m\n$panes0[4] Disabled by OPTIMIZE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
	}

	//runs nzbcount in 0.1 loops
	if (( $array['MAX_LOAD'] >= get_load()) && ( $array['IMPORT'] == "true" ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		$log = writelog($panes0[1]);
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.1 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes0[1] $ds2 && cd $_bin && $_php nzbcount.php 2>&1 $log' 2>&1 1> /dev/null");
	} elseif (( $array['IMPORT'] != "true" ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.1 'echo \"\033[38;5;\"$color\"m\n$panes0[1] Disabled by IMPORT\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
	} elseif (( $optimize_safe_to_run != "true" ) && ( $array['MAX_LOAD'] <= get_load())) {
                $color = get_color();
                shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.1 'echo \"\033[38;5;\"$color\"m\n$panes0[1] Disabled by MAX_LOAD\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
        } elseif ( $optimize_safe_to_run == "true" ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.1 'echo \"\033[38;5;\"$color\"m\n$panes0[1] Disabled by OPTIMIZE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
	}

	//start postprocessing in window 2
	$post = $array['POST_TO_RUN_A'];
	for ($g=1; $g<=16; $g++)
	{
		$h=$g-1;
		$f=$h*200;
		$j=$g*1;
		$color = get_color();
		$log = writelog($panes2[$h]);
		if (( $array['MAX_LOAD'] >= get_load()) && ( $array['POST_TO_RUN_A'] >= $g ) && ( $work_remaining_now > $f ) && ( $optimize_safe_to_run != "true" )) {
			shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.$h 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes2[$h] $ds2 && cd $_temp && $_php processAdditional$g.php 2>&1 $log && echo \" \033[1;0;33m\" && $ds1 $panes2[$h] $ds3' 2>&1 1> /dev/null");
		} elseif (( $array['POST_TO_RUN_A'] >= $g ) && ( $work_remaining_now <= $f ) && ( $optimize_safe_to_run != "true" )) {
			shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.$h 'echo \"\033[38;5;\"$color\"m\n$panes2[$h] $work_remaining_now < $f\nHas no work to process \" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
		} elseif (( $g > $post ) && ( $optimize_safe_to_run != "true" )) {
			shell_exec("$_tmux respawnp -k -t {$array['TMUX_SESSION']}:2.$h 'echo \"\033[38;5;\"$color\"m\n$panes2[$h] Disabled by POST_TO_RUN_A\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
		} elseif (( $optimize_safe_to_run != "true" ) && ( $array['MAX_LOAD'] <= get_load())) {
                        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.$h 'echo \"\033[38;5;\"$color\"m\n$panes2[$h] Disabled by MAX_LOAD\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
                } elseif ( $optimize_safe_to_run == "true" ) {
			shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.$h 'echo \"\033[38;5;\"$color\"m\n$panes2[$h] Disabled by OPTIMIZE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
		}
	}

	//start postprocessing in window 3
	$post = $array['POST_TO_RUN_B'];
        for ($g=1; $g<=16; $g++)
        {
                $k=$g+16;
		$h=$g-1;
                $f=$h*200;
                $j=$g*1;
                $color = get_color();
		$log = writelog($panes3[$h]);
		if (( $array['MAX_LOAD'] >= get_load()) && ( $array['POST_TO_RUN_B'] >= $g ) && ( $work_remaining_now > $f ) && ( $optimize_safe_to_run != "true" )) {
			shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.$h 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes3[$h] $ds2 && cd $_temp && $_php processAdditional$k.php 2>&1 $log && echo \" \033[1;0;33m\" && $ds1 $panes3[$h] $ds3' 2>&1 1> /dev/null");
		} elseif (( $array['POST_TO_RUN_B'] >= $g ) && ( $work_remaining_now <= $f ) && ( $optimize_safe_to_run != "true" )) {
			shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.$h 'echo \"\033[38;5;\"$color\"m\n$panes3[$h]\n$work_remaining_now < $f\nHas no work to process \" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
		} elseif (( $g > $post ) && ( $optimize_safe_to_run != "true" )) {
			shell_exec("$_tmux respawnp -k -t {$array['TMUX_SESSION']}:3.$h 'echo \"\033[38;5;\"$color\"m\n$panes3[$h]\nDisabled by \nPOST_TO_RUN_B\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
		} elseif (( $optimize_safe_to_run != "true" ) && ( $array['MAX_LOAD'] <= get_load())) {
                        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.$h 'echo \"\033[38;5;\"$color\"m\n$panes3[$h] Disabled by MAX_LOAD\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
                } elseif ( $optimize_safe_to_run == "true" ) {
			shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.$h 'echo \"\033[38;5;\"$color\"m\n$panes3[$h]\nDisabled by \nOPTIMIZE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
		}
	}

	//runs update_release and in 0.5 once if needed and exits
	if (( $array['MAX_LOAD_RELEASES'] >= get_load()) && ( $array['RELEASES'] == "true" ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		$log = writelog($panes0[5]);
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.5 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes0[5] $ds2 && cd $_bin && $_php update_releases.php && 2>&1 $log && echo \" \033[1;0;33m\" && echo \"sleeping\033[38;5;\"$color\"m {$array['RELEASES_SLEEP']} seconds...\" && sleep {$array['RELEASES_SLEEP']} && $ds1 $panes0[5] $ds3' 2>&1 1> /dev/null");
	} elseif (( $array['MAX_LOAD_RELEASES'] >= get_load()) && ( $array['RELEASES'] == "true" ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		$log = writelog($panes0[5]);
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.5 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes0[5] $ds2 && cd $_bin && $_php update_releases.php 2>&1 $log && echo \" \033[1;0;33m\" && echo \"sleeping\033[38;5;\"$color\"m {$array['RELEASES_SLEEP']} seconds...\" && sleep {$array['RELEASES_SLEEP']}  && $ds1 $panes0[5] $ds3' 2>&1 1> /dev/null");
	} elseif (( $array['RELEASES'] == "true" ) && ( $optimize_safe_to_run != "true" ) && ( $array['MAX_LOAD_RELEASES'] <= get_load())) {
                $color = get_color();
                shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.5 'echo \"\033[38;5;\"$color\"m\n$panes0[5] Disabled by MAX_LOAD_RELEASES\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
        } elseif (( $array['RELEASES'] != "true" ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.5 'echo \"\033[38;5;\"$color\"m\n$panes0[5] Disabled by RELEASES\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
	} elseif ( $optimize_safe_to_run == "true" ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.5 'echo \"\033[38;5;\"$color\"m\n$panes0[5] Disabled by OPTIMIZE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
	}

	//run update_predb.php in 1.0 ever 15 minutes and on fifth loop
	if (( $array['MAX_LOAD'] >= get_load()) && ((( TIME() - $time2 ) >= $array['PREDB_TIMER'] ) || ( $i == 5 )) && ( $array['PREDB'] == "true" ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		$log = writelog($panes1[0]);
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.0 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes1[0] $ds2 && cd $NNPATH && $_php update_predb.php true 2>&1 $log && echo \" \033[1;0;33m\" && $ds1 $panes1[0] $ds3' 2>&1 1> /dev/null");
		$time2 = TIME();
	} elseif (( $array['PREDB'] == "true" ) && ( $optimize_safe_to_run != "true" ) && ( $array['MAX_LOAD'] >= get_load())) {
		$color = get_color();
		$run_time = relativeTime( $array['PREDB_TIMER'] + $time2 );
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.0 'echo \"\033[38;5;\"$color\"m\n$panes1[0] will run in T[ $run_time]\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
	} elseif (( $array['MAX_LOAD'] >= get_load()) && ( $array['PREDB'] != "true" ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.0 'echo \"\033[38;5;\"$color\"m\n$panes1[0] Disabled by PREDB\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
        } elseif (( $optimize_safe_to_run != "true" ) && ( $array['MAX_LOAD'] <= get_load())) {
                $color = get_color();
                shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.0 'echo \"\033[38;5;\"$color\"m\n$panes1[0] Disabled by MAX_LOAD\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
	} elseif ( $optimize_safe_to_run == "true" ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.0 'echo \"\033[38;5;\"$color\"m\n$panes1[0] Disabled by OPTIMIZE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
	}


	//run update_tvschedule.php and $_php update_theaters.php in 1.1 every 12 hours and tenth loop
	if (( $array['MAX_LOAD'] >= get_load()) && ((( TIME() - $time4 ) >= $array['TVRAGE_TIMER'] ) || ( $i == 10 )) && ( $array['TV_SCHEDULE'] == "true") && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		$log = writelog($panes1[1]);
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.1 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes1[1] $ds2 && cd $NNPATH && $_php update_tvschedule.php 2>&1 $log && $_php update_theaters.php 2>&1 $log && echo \" \033[1;0;33m\" && $ds1 $panes1[1] $ds3' 2>&1 1> /dev/null");
		$time4 = TIME();
	} elseif (( $array['TV_SCHEDULE'] == "true") && ( $optimize_safe_to_run != "true" ) && ( $array['MAX_LOAD'] >= get_load())) {
		$color = get_color();
		$run_time = relativeTime( $array['TVRAGE_TIMER'] + $time4 );
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.1 'echo \"\033[38;5;\"$color\"m\n$panes1[1] and update_theaters will run in T[ $run_time]\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
	} elseif (( $array['TV_SCHEDULE'] != "true") && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.1 'echo \"\033[38;5;\"$color\"m\n$panes1[1] Disabled by TV_SCHEDULE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
	} elseif (( $optimize_safe_to_run != "true" ) && ( $array['MAX_LOAD'] <= get_load())) {
                $color = get_color();
                shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.1 'echo \"\033[38;5;\"$color\"m\n$panes1[1] Disabled by MAX_LOAD\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
        } elseif ( $optimize_safe_to_run == "true" ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.1 'echo \"\033[38;5;\"$color\"m\n$panes1[1] Disabled by OPTIMIZE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
	}

	//run sphinx in pane 1.2
	if (( $array['MAX_LOAD'] >= get_load()) && ( TIME() - $time9 >= $array['SPHINX_TIMER'] ) && ( $array['SPHINX'] == "true") && ( $optimize_safe_to_run != "true" )) {
		$color=get_color();
		$log = writelog($panes1[2]);
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.2 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes1[2] $ds2 && cd $_bin && $_php sphinx.php 2>&1 $log && echo \" \033[1;0;33m\" && $ds1 $panes1[2] $ds3' 2>&1 1> /dev/null");
		$time9 = TIME();
	} elseif (( $array['SPHINX'] != "true" ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.2 'echo \"\033[38;5;\"$color\"m\n$panes1[2] Disabled by SPHINX\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
	} elseif (( $optimize_safe_to_run != "true" ) && ( $array['MAX_LOAD'] >= get_load())) {
		$color = get_color();
		$run_time = relativeTime( $array['SPHINX_TIMER'] + $time9 );
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.2 'echo \"\033[38;5;\"$color\"m\n$panes1[2] will run in T[ $run_time]\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
	} elseif (( $optimize_safe_to_run != "true" ) && ( $array['MAX_LOAD'] <= get_load())) {
                $color = get_color();
                shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.2 'echo \"\033[38;5;\"$color\"m\n$panes1[2] Disabled by MAX_LOAD\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
        } elseif ( $optimize_safe_to_run == "true" ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.2 'echo \"\033[38;5;\"$color\"m\n$panes1[2] Disabled by OPTIMIZE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
	}

	//run delete parts in pane 1.3
	if (( $array['MAX_LOAD'] >= get_load()) && (( TIME() - $time16 ) >= $array['DELETE_TIMER'] ) && ( $array['DELETE_PARTS'] == "true" ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		$log = writelog($panes1[3]);
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.3 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes1[3] $ds2 && cd $_cj && $_php remove_parts_without_releases.php 2>&1 $log && $ds1 $panes1[3] $ds3' 2>&1 1> /dev/null");
		$time16 = TIME();
	} elseif (( $array['DELETE_PARTS'] != "true" ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.3 'echo \"\033[38;5;\"$color\"m\n$panes1[3] Disabled by DELETE_PARTS\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
	} elseif (( $optimize_safe_to_run != "true" ) && ( $array['MAX_LOAD'] >= get_load())) {
		$color = get_color();
		$run_time = relativeTime( $array['DELETE_TIMER'] + $time16 );
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.3 'echo \"\033[38;5;\"$color\"m\n$panes1[3] will run in T[ $run_time]\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
	} elseif (( $optimize_safe_to_run != "true" ) && ( $array['MAX_LOAD'] <= get_load())) {
                $color = get_color();
                shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.3 'echo \"\033[38;5;\"$color\"m\n$panes1[3] Disabled by MAX_LOAD\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
        } elseif ( $optimize_safe_to_run == "true" ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.3 'echo \"\033[38;5;\"$color\"m\n$panes1[3] Disabled by OPTIMIZE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
	}

	//run update_missing_movie_info parts in pane 1.4 on 15th loop
	if (( $array['MAX_LOAD'] >= get_load()) && ((( TIME() - $time17 ) >= $array['MOVIE_TIMER'] ) || ( $i == 15 )) && ( $array['FETCH_MOVIE'] == "true" ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		$log = writelog($panes1[4]);
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.4 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes1[4] $ds2 && cd $_cj && $_php update_missing_movie_info.php 2>&1 $log && $ds1 $panes1[4] $ds3' 2>&1 1> /dev/null");
		$time17 = TIME();
	} elseif (( $array['FETCH_MOVIE'] != "true" ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.4 'echo \"\033[38;5;\"$color\"m\n$panes1[4] Disabled by FETCH_MOVIE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
	} elseif (( $optimize_safe_to_run != "true" ) && ( $array['MAX_LOAD'] >= get_load())) {
		$color = get_color();
		$run_time = relativeTime( $array['MOVIE_TIMER'] + $time17 );
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.4 'echo \"\033[38;5;\"$color\"m\n$panes1[4] will run in T[ $run_time]\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
	} elseif (( $optimize_safe_to_run != "true" ) && ( $array['MAX_LOAD'] <= get_load())) {
                $color = get_color();
                shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.4 'echo \"\033[38;5;\"$color\"m\n$panes1[4] Disabled by MAX_LOAD\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
        } elseif ( $optimize_safe_to_run == "true" ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.4 'echo \"\033[38;5;\"$color\"m\n$panes1[4] Disabled by OPTIMIZE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
	}

	//runs postprocess_nfo.php in pane 4.0 once if needed then exits
	if (( $array['MAX_LOAD'] >= get_load()) && ( $nfo_remaining_now > 0 ) && ( $array['NFOS'] != "0" ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		$log = writelog($panes4[0]);
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:4.0 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes4[0] $ds2 && cd $_bin && $_php postprocess_nfo.php 2>&1 $log && echo \" \033[1;0;33m\" && $ds1 $panes4[0] $ds3' 2>&1 1> /dev/null");
	} elseif (( $array['NFOS'] == "0" ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:4.0 'echo \"\033[38;5;\"$color\"m\n$panes4[0] Disabled by NFOS\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
	} elseif (( $optimize_safe_to_run != "true" ) && ( $array['MAX_LOAD'] <= get_load())) {
                $color = get_color();
                shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:4.0 'echo \"\033[38;5;\"$color\"m\n$panes4[0] Disabled by MAX_LOAD\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
        } elseif (( $nfo_remaining_now == 0 ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:4.0 'echo \"\033[38;5;\"$color\"m\n$panes4[0] Has no work to process \" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
        } elseif ( $optimize_safe_to_run == "true" ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:4.0 'echo \"\033[38;5;\"$color\"m\n$panes4[0] Disabled by OPTIMIZE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
	}

	//runs postprocess_nfo1.php in pane 4.4 once if needed then exits
	if (( $array['MAX_LOAD'] >= get_load()) && ( $nfo_remaining_now >= 500 ) && ( $array['NFOS'] == "2" ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		$log = writelog($panes4[4]);
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:4.4 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes4[4] $ds2 && cd $_bin && $_php postprocess_nfo1.php 2>&1 $log && echo \" \033[1;0;33m\" && $ds1 $panes4[4] $ds3' 2>&1 1> /dev/null");
	} elseif (( $array['NFOS'] = "0" ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:4.4 'echo \"\033[38;5;\"$color\"m\n$panes4[4] Disabled by NFOS\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
	} elseif (( $optimize_safe_to_run != "true" ) && ( $array['MAX_LOAD'] <= get_load())) {
                $color = get_color();
                shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:4.4 'echo \"\033[38;5;\"$color\"m\n$panes4[4] Disabled by MAX_LOAD\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
        } elseif (( $nfo_remaining_now == 0 ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:4.4 'echo \"\033[38;5;\"$color\"m\n$panes4[4] Has no work to process \" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
        } elseif ( $optimize_safe_to_run == "true" ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:4.4 'echo \"\033[38;5;\"$color\"m\n$panes4[4] Disabled by OPTIMIZE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
	}

	//runs processGames.php in pane 4.1 once if needed then exits
	if (( $array['MAX_LOAD'] >= get_load()) && ( $console_releases_proc > 0 ) && ( $array['GAMES'] != "0" ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		$log = writelog($panes4[1]);
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:4.1 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes4[1] $ds2 && cd $_bin && $_php processGames.php 2>&1 $log && echo \" \033[1;0;33m\" && $ds1 $panes4[1] $ds3' 2>&1 1> /dev/null");
	} elseif (( $array['GAMES'] == "0" ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:4.1 'echo \"\033[38;5;\"$color\"m\n$panes4[1] Disabled by GAMES\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
	} elseif (( $optimize_safe_to_run != "true" ) && ( $array['MAX_LOAD'] <= get_load())) {
                $color = get_color();
                shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:4.1 'echo \"\033[38;5;\"$color\"m\n$panes4[1] Disabled by MAX_LOAD\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
        } elseif (( $console_releases_proc == 0 ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:4.1 'echo \"\033[38;5;\"$color\"m\n$panes4[1] Has no work to process \" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
        } elseif ( $optimize_safe_to_run == "true" ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:4.1 'echo \"\033[38;5;\"$color\"m\n$panes4[1] Disabled by OPTIMIZE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
	}

	//runs processGames.php in pane 4.5 once if needed then exits
	if (( $array['MAX_LOAD'] >= get_load()) && ( $console_releases_proc >= 200 ) && ( $array['GAMES'] == "2" ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		$log = writelog($panes4[5]);
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:4.5 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes4[5] $ds2 && cd $_bin && $_php processGames1.php 2>&1 $log && echo \" \033[1;0;33m\" && $ds1 $panes4[5] $ds3' 2>&1 1> /dev/null");
	} elseif (( $array['GAMES'] == "0" ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:4.5 'echo \"\033[38;5;\"$color\"m\n$panes4[5] Disabled by GAMES\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
	} elseif (( $optimize_safe_to_run != "true" ) && ( $array['MAX_LOAD'] <= get_load())) {
                $color = get_color();
                shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:4.5 'echo \"\033[38;5;\"$color\"m\n$panes4[5] Disabled by MAX_LOAD\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
        } elseif (( $console_releases_proc == 0 ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:4.5 'echo \"\033[38;5;\"$color\"m\n$panes4[5] Has no work to process \" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
        } elseif ( $optimize_safe_to_run == "true" ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:4.5 'echo \"\033[38;5;\"$color\"m\n$panes4[5] Disabled by OPTIMIZE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
	}

	//runs processMovies.php in pane 4.2 once if needed then exits
	if (( $array['MAX_LOAD'] >= get_load()) && ( $movie_releases_proc > 0 ) && ( $array['MOVIES'] != "0" ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		$log = writelog($panes4[2]);
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:4.2 'echo \"\033[38;5;\"$color\"\" && $ds1 $panes4[2] $ds2 && cd $_bin && $_php processMovies.php 2>&1 $log && echo \" \033[1;0;33m\" && $ds1 $panes4[2] $ds3' 2>&1 1> /dev/null");
	} elseif (( $array['MOVIES'] == "0" ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:4.2 'echo \"\033[38;5;\"$color\"m\n$panes4[2] Disabled by MOVIES\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
	} elseif (( $optimize_safe_to_run != "true" ) && ( $array['MAX_LOAD'] <= get_load())) {
                $color = get_color();
                shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:4.2 'echo \"\033[38;5;\"$color\"m\n$panes4[2] Disabled by MAX_LOAD\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
        } elseif (( $movie_releases_proc == 0 ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:4.2 'echo \"\033[38;5;\"$color\"m\n$panes4[2] Has no work to process \" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
        } elseif ( $optimize_safe_to_run == "true" ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:4.2 'echo \"\033[38;5;\"$color\"m\n$panes4[2] Disabled by OPTIMIZE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
	}

	//runs processMovies.php in pane 4.6 once if needed then exits
	if (( $array['MAX_LOAD'] >= get_load()) && ( $movie_releases_proc >= 200 ) && ( $array['MOVIES'] == "2" ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		$log = writelog($panes4[6]);
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:4.6 'echo \"\033[38;5;\"$color\"\" && $ds1 $panes4[6] $ds2 && cd $_bin && $_php processMovies1.php 2>&1 $log && echo \" \033[1;0;33m\" && $ds1 $panes4[6] $ds3' 2>&1 1> /dev/null");
	} elseif (( $array['MOVIES'] == "0" ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:4.6 'echo \"\033[38;5;\"$color\"m\n$panes4[6] Disabled by MOVIES\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
	} elseif (( $optimize_safe_to_run != "true" ) && ( $array['MAX_LOAD'] <= get_load())) {
                $color = get_color();
                shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:4.6 'echo \"\033[38;5;\"$color\"m\n$panes4[6] Disabled by MAX_LOAD\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
        } elseif (( $movie_releases_proc == 0 ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:4.6 'echo \"\033[38;5;\"$color\"m\n$panes4[6] Has no work to process \" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
        } elseif ( $optimize_safe_to_run == "true" ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:4.6 'echo \"\033[38;5;\"$color\"m\n$panes4[6] Disabled by OPTIMIZE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
	}

	//runs processMusic.php in pane 4.3 once if needed then exits
	if (( $array['MAX_LOAD'] >= get_load()) && ( $music_releases_proc > 0 ) && ( $array['MUSIC'] != "0" ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		$log = writelog($panes4[3]);
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:4.3 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes4[3] $ds2 && cd $_bin && $_php processMusic.php 2>&1 $log && echo \" \033[1;0;33m\" && $ds1 $panes4[3] $ds3' 2>&1 1> /dev/null");
	} elseif (( $array['MUSIC'] == "0" ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:4.3 'echo \"\033[38;5;\"$color\"m\n$panes4[3] Disabled by MUSIC\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
	} elseif (( $optimize_safe_to_run != "true" ) && ( $array['MAX_LOAD'] <= get_load())) {
                $color = get_color();
                shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:4.3 'echo \"\033[38;5;\"$color\"m\n$panes4[3] Disabled by MAX_LOAD\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
        } elseif (( $music_releases_proc == 0 ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:4.3 'echo \"\033[38;5;\"$color\"m\n$panes4[3] Has no work to process \" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
        } elseif ( $optimize_safe_to_run == "true" ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:4.3 'echo \"\033[38;5;\"$color\"m\n$panes4[3] Disabled by OPTIMIZE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
	}

	//runs processMusic.php in pane 4.7 once if needed then exits
	if (( $array['MAX_LOAD'] >= get_load()) && ( $music_releases_proc >= 200 ) && ( $array['MUSIC'] == "2" ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		$log = writelog($panes4[7]);
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:4.7 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes4[7] $ds2 && cd $_bin && $_php processMusic1.php 2>&1 $log && echo \" \033[1;0;33m\" && $ds1 $panes4[7] $ds3' 2>&1 1> /dev/null");
	} elseif (( $array['MUSIC'] == "0" ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:4.7 'echo \"\033[38;5;\"$color\"m\n$panes4[7] Disabled by MUSIC\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
	} elseif (( $optimize_safe_to_run != "true" ) && ( $array['MAX_LOAD'] <= get_load())) {
                $color = get_color();
                shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:4.7 'echo \"\033[38;5;\"$color\"m\n$panes4[7] Disabled by MAX_LOAD\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
        } elseif (( $music_releases_proc == 0 ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:4.7 'echo \"\033[38;5;\"$color\"m\n$panes4[7] Has no work to process \" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
        } elseif ( $optimize_safe_to_run == "true" ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:4.7 'echo \"\033[38;5;\"$color\"m\n$panes4[7] Disabled by OPTIMIZE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
	}

	//run processSpotnab.php in 5.0 every 15 minutes
	if (( $array['MAX_LOAD'] >= get_load()) && (( TIME() - $time20 ) >= $array['SPOTNAB_TIMER'] ) && ( $array['SPOTNAB'] == "true" ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		$log = writelog($panes5[0]);
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:5.0 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes5[0] $ds2 && cd $_bin && $_php processSpotnab.php true 2>&1 $log && echo \" \033[1;0;33m\" && $ds1 $panes5[0] $ds3' 2>&1 1> /dev/null");
		$time20 = TIME();
	} elseif (( $array['SPOTNAB'] != "true" ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:5.0 'echo \"\033[38;5;\"$color\"m\n$panes5[0] Disabled by SPOTNAB\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
	} elseif (( $array['SPOTNAB'] == "true" ) && ( $optimize_safe_to_run != "true" ) && ( $array['MAX_LOAD'] >= get_load())) {
		$color = get_color();
		$run_time = relativeTime( $array['SPOTNAB_TIMER'] + $time20 );
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:5.0 'echo \"\033[38;5;\"$color\"m\n$panes5[0] will run in T[ $run_time]\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
	} elseif (( $optimize_safe_to_run != "true" ) && ( $array['MAX_LOAD'] <= get_load())) {
                $color = get_color();
                shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:5.0 'echo \"\033[38;5;\"$color\"m\n$panes5[0] Disabled by MAX_LOAD\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
        } elseif ( $optimize_safe_to_run == "true" ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:5.0 'echo \"\033[38;5;\"$color\"m\n$panes5[0] Disabled by OPTIMIZE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
	}

	//runs processTv.php in pane 5.4 once if needed then exits
	if (( $array['MAX_LOAD'] >= get_load()) && ( $tvrage_releases_proc > 0 ) && ( $array['TVRAGE'] == "true" ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		$log = writelog($panes5[4]);
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:5.4 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes5[4] $ds2 && cd $_bin && $_php processTv.php 2>&1 $log && echo \" \033[1;0;33m\" && $ds1 $panes5[4] $ds3' 2>&1 1> /dev/null");
	} elseif (( $array['TVRAGE'] != "true" ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:5.4 'echo \"\033[38;5;\"$color\"m\n$panes5[4] Disabled by TVRAGE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
	} elseif (( $optimize_safe_to_run != "true" ) && ( $array['MAX_LOAD'] <= get_load())) {
                $color = get_color();
                shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:5.4 'echo \"\033[38;5;\"$color\"m\n$panes5[4] Disabled by MAX_LOAD\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
        } elseif ( $optimize_safe_to_run != "true" ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:5.4 'echo \"\033[38;5;\"$color\"m\n$panes5[4] Has no work to process \" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
        } elseif ( $optimize_safe_to_run == "true" ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:5.4 'echo \"\033[38;5;\"$color\"m\n$panes5[4] Disabled by OPTIMIZE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
	}

	//runs processTv.php in pane 5.1 once if needed then exits
	if (( $array['MAX_LOAD'] >= get_load()) && ( $tvrage_releases_proc > 0 ) && ( $array['TVRAGE'] == "true" ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		$log = writelog($panes5[1]);
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:5.1 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes5[1] $ds2 && cd $_bin && $_php processTv1.php 2>&1 $log && echo \" \033[1;0;33m\" && $ds1 $panes5[1] $ds3' 2>&1 1> /dev/null");
	} elseif (( $array['TVRAGE'] != "true" ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:5.1 'echo \"\033[38;5;\"$color\"m\n$panes5[1] Disabled by TVRAGE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
	} elseif (( $optimize_safe_to_run != "true" ) && ( $array['MAX_LOAD'] <= get_load())) {
                $color = get_color();
                shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:5.1 'echo \"\033[38;5;\"$color\"m\n$panes5[1] Disabled by MAX_LOAD\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
        } elseif (( $tvrage_releases_proc == 0 ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:5.1 'echo \"\033[38;5;\"$color\"m\n$panes5[1] Has no work to process \" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
        } elseif ( $optimize_safe_to_run == "true" ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:5.1 'echo \"\033[38;5;\"$color\"m\n$panes5[1] Disabled by OPTIMIZE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
	}

	//runs processTv.php in pane 5.5 once if needed then exits
	if (( $array['MAX_LOAD'] >= get_load()) && ( $tvrage_releases_proc > 0 ) && ( $array['TVRAGE'] == "true" ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		$log = writelog($panes5[5]);
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:5.5 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes5[5] $ds2 && cd $_bin && $_php processTv2.php 2>&1 $log && echo \" \033[1;0;33m\" && $ds1 $panes5[5] $ds3' 2>&1 1> /dev/null");
	} elseif (( $array['TVRAGE'] != "true" ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:5.5 'echo \"\033[38;5;\"$color\"m\n$panes5[5] Disabled by TVRAGE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
	} elseif (( $optimize_safe_to_run != "true" ) && ( $array['MAX_LOAD'] <= get_load())) {
                $color = get_color();
                shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:5.5 'echo \"\033[38;5;\"$color\"m\n$panes5[5] Disabled by MAX_LOAD\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
        } elseif (( $tvrage_releases_proc == 0 ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:5.5 'echo \"\033[38;5;\"$color\"m\n$panes5[5] Has no work to process \" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
        } elseif ( $optimize_safe_to_run == "true" ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:5.5 'echo \"\033[38;5;\"$color\"m\n$panes5[5] Disabled by OPTIMIZE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
	}

	//runs processBooks.php in pane 5.2 once if needed then exits
	if (( $array['MAX_LOAD'] >= get_load()) && ( $book_releases_proc > 0 ) && ( $array['EBOOK'] != "0" ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		$log = writelog($panes5[2]);
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:5.2 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes5[2] $ds2 && cd $_bin && $_php processBooks.php 2>&1 $log && echo \" \033[1;0;33m\" && $ds1 $panes5[2] $ds3' 2>&1 1> /dev/null");
	} elseif (( $array['EBOOK'] == "0" ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:5.2 'echo \"\033[38;5;\"$color\"m\n$panes5[2] Disabled by EBOOK\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
	} elseif (( $optimize_safe_to_run != "true" ) && ( $array['MAX_LOAD'] <= get_load())) {
                $color = get_color();
                shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:5.2 'echo \"\033[38;5;\"$color\"m\n$panes5[2] Disabled by MAX_LOAD\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
        } elseif (( $book_releases_proc == 0 ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:5.2 'echo \"\033[38;5;\"$color\"m\n$panes5[2] Has no work to process \" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
        } elseif ( $optimize_safe_to_run == "true" ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:5.2 'echo \"\033[38;5;\"$color\"m\n$panes5[2] Disabled by OPTIMIZE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
	}

	//runs processBooks.php in pane 5.6 once if needed then exits
	if (( $array['MAX_LOAD'] >= get_load()) && ( $book_releases_proc >=200 ) && ( $array['EBOOK'] == "2" ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		$log = writelog($panes5[6]);
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:5.6 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes5[6] $ds2 && cd $_bin && $_php processBooks1.php 2>&1 $log && echo \" \033[1;0;33m\" && $ds1 $panes5[6] $ds3' 2>&1 1> /dev/null");
	} elseif (( $array['EBOOK'] == "0" ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:5.6 'echo \"\033[38;5;\"$color\"m\n$panes5[6] Disabled by EBOOK\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
	} elseif (( $optimize_safe_to_run != "true" ) && ( $array['MAX_LOAD'] <= get_load())) {
                $color = get_color();
                shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:5.6 'echo \"\033[38;5;\"$color\"m\n$panes5[6] Disabled by MAX_LOAD\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
        } elseif (( $book_releases_proc == 0 ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:5.6 'echo \"\033[38;5;\"$color\"m\n$panes5[6] Has no work to process \" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
        } elseif ( $optimize_safe_to_run == "true" ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:5.6 'echo \"\033[38;5;\"$color\"m\n$panes5[6] Disabled by OPTIMIZE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
	}

	//runs processOthers.php in pane 5.3 once if needed then exits
	if  (( $array['MAX_LOAD'] >= get_load()) && ( $array['OTHERS'] == "true" ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		$log = writelog($panes5[3]);
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:5.3 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes5[3] $ds2 && cd $_bin && $_php processOthers.php 2>&1 $log && echo \" \033[1;0;33m\" && $ds1 $panes5[3] $ds3' 2>&1 1> /dev/null");
	} elseif (( $array['OTHERS'] != "true" ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:5.3 'echo \"\033[38;5;\"$color\"m\n$panes5[3] Disabled by OTHERS\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
	} elseif (( $optimize_safe_to_run != "true" ) && ( $array['MAX_LOAD'] <= get_load())) {
                $color = get_color();
                shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:5.3 'echo \"\033[38;5;\"$color\"m\n$panes5[3] Disabled by MAX_LOAD\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
        } elseif ( $optimize_safe_to_run != "true" ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:5.3 'echo \"\033[38;5;\"$color\"m\n$panes5[3] Has no work to process \" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
        } elseif ( $optimize_safe_to_run == "true" ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:5.3 'echo \"\033[38;5;\"$color\"m\n$panes5[3] Disabled by OPTIMIZE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
	}

	//runs processUnwanted.php in pane 5.7 in continuous loop, will restart if exits
	if  (( $array['MAX_LOAD'] >= get_load()) && ( $array['UNWANTED'] == "true" ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		$log = writelog($panes5[7]);
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:5.7 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes5[7] $ds2 && cd $_bin && $_php processUnwanted.php 2>&1 $log && echo \" \033[1;0;33;33m\" && $ds1 $panes5[7] $ds3' 2>&1 1> /dev/null");
	} elseif (( $array['UNWANTED'] != "true" ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:5.7 'echo \"\033[38;5;\"$color\"m\n$panes5[7] Disabled by UNWANTED\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
	} elseif (( $optimize_safe_to_run != "true" ) && ( $array['MAX_LOAD'] <= get_load())) {
                $color = get_color();
                shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:5.7 'echo \"\033[38;5;\"$color\"m\n$panes5[7] Disabled by MAX_LOAD\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
        } elseif ( $optimize_safe_to_run != "true" ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:5.7 'echo \"\033[38;5;\"$color\"m\n$panes5[7] Has no work to process \" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
        } elseif ( $optimize_safe_to_run == "true" ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:5.7 'echo \"\033[38;5;\"$color\"m\n$panes5[7] Disabled by OPTIMIZE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
	}

	//run predbHashCompare.php in pane 6.1
	if (( $array['MAX_LOAD'] >= get_load()) && (( TIME() - $time28 ) >= $array['AFLY_TIMER'] ) &&( $array['AFLY'] == "true" ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		$log = writelog($panes6[1]);
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:6.1 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes6[1] $ds2 && cd $_hash && $_php predbHashCompare.php 2>&1 $log && echo \" \033[1;0;33m\" && $ds1 $panes6[1] $ds3' 2>&1 1> /dev/null");
		$time28 = TIME();
	} elseif (( $array['AFLY'] != "true" ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:6.1 'echo \"\033[38;5;\"$color\"m\n$panes6[1] Disabled by AFLY\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
	} elseif (( $array['AFLY'] == "true" ) && ( $optimize_safe_to_run != "true" ) && ( $array['MAX_LOAD'] >= get_load())) {
		$color = get_color();
		$run_time = relativeTime( $array['AFLY_TIMER'] + $time28 );
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:6.1 'echo \"\033[38;5;\"$color\"m\n$panes6[1] will run in T[ $run_time]\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
	} elseif (( $optimize_safe_to_run != "true" ) && ( $array['MAX_LOAD'] <= get_load())) {
                $color = get_color();
                shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:6.1 'echo \"\033[38;5;\"$color\"m\n$panes6[1] Disabled by MAX_LOAD\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
        } elseif ( $optimize_safe_to_run == "true" ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:6.1 'echo \"\033[38;5;\"$color\"m\n$panes6[1] Disabled by OPTIMIZE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
	}

	//run fixReleaseNames in pane 6.2
	if (( $array['MAX_LOAD'] >= get_load()) && (( TIME() - $time27 ) >= $array['FIXRELEASES_TIMER'] ) && ( $array['FIXRELEASES'] == "true" ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		$log = writelog($panes6[2]);
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:6.2 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes6[2] $ds2 && cd $_test && $_php postprocess_pre.php 2>&1 $log && $_php fixReleaseNames.php 1 true all yes 2>&1 $log && $_php fixReleaseNames.php 3 true other yes 2>&1 $log && $_php fixReleaseNames.php 5 true other yes 2>&1 $log && $_php fixReleaseNames.php 7 true other yes 2>&1 $log && $_php hash_decrypt.php true 2>&1 $log && echo \" \033[1;0;33m\" && $ds1 $panes6[2] $ds3' 2>&1 1> /dev/null");
		$time27 = TIME();}
	  elseif (( $array['FIXRELEASES'] != "true" ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:6.2 'echo \"\033[38;5;\"$color\"m\n$panes6[2] Disabled by fixReleaseNames\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
	} elseif (( $array['FIXRELEASES'] == "true" ) && ( $optimize_safe_to_run != "true" ) && ( $array['MAX_LOAD'] >= get_load())) {
		$color = get_color();
		$run_time = relativeTime( $array['FIXRELEASES_TIMER'] + $time27 );
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:6.2 'echo \"\033[38;5;\"$color\"m\n$panes6[2] will run in T[ $run_time]\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
	} elseif (( $optimize_safe_to_run != "true" ) && ( $array['MAX_LOAD'] <= get_load())) {
                $color = get_color();
                shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:6.2 'echo \"\033[38;5;\"$color\"m\n$panes6[2] Disabled by MAX_LOAD\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
        } elseif ( $optimize_safe_to_run == "true" ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:6.2 'echo \"\033[38;5;\"$color\"m\n$panes6[2] Disabled by OPTIMIZE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
	}
    //run removeCrapReleasess in pane 6.0 and remove crap 2 hours back
	if (( $array['MAX_LOAD'] >= get_load()) && (( TIME() - $time29 ) >= $array['REMOVECRAP_TIMER'] ) && ( $array['REMOVECRAP'] == "true" ) && ( $optimize_safe_to_run != "true" )) {
		$color = get_color();
		$log = writelog($panes6[0]);
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:6.0 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes6[0] $ds2 && cd $_test &&  $_php removeCrapReleases.php true 2 2>&1 $log echo \" \033[1;0;33m\" && $ds1 $panes6[0] $ds3' 2>&1 1> /dev/null");
		$time29 = TIME();
	} elseif (( $optimize_safe_to_run != "true" ) && ( $array['MAX_LOAD'] <= get_load())) {
                $color = get_color();
                shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:6.0 'echo \"\033[38;5;\"$color\"m\n$panes6[0] Disabled by MAX_LOAD\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
    } elseif (( $array['REMOVECRAP'] != "true" ) && ( $optimize_safe_to_run != "true" )) {
-		$color = get_color();
-		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:6.0 'echo \"\033[38;5;\"$color\"m\n$panes6[0] Disabled by removeCrapReleases\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
    } elseif (( $array['REMOVECRAP'] == "true" ) && ( $optimize_safe_to_run != "true" ) && ( $array['MAX_LOAD'] >= get_load())) {
		$color = get_color();
		$run_time = relativeTime( $array['REMOVECRAP_TIMER'] + $time29 );
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:6.0 'echo \"\033[38;5;\"$color\"m\n$panes6[0] will run in T[ $run_time]\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
	} elseif ( $optimize_safe_to_run == "true" ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:6.0 'echo \"\033[38;5;\"$color\"m\n$panes6[0] Disabled by OPTIMIZE\" && date +\"%D %T\" && echo \"This is color #$color\"' 2>&1 1> /dev/null");
}


	//check ffmpeg and mediainfo, kill if necessary
	if (( $array['KILL_PROCESS'] != "0" ) && ( $array['KILL_QUIET'] == "true" ) && ( $optimize_safe_to_run != "true" )) {
		shell_exec("killall -qo {$array['KILL_PROCESS']}s -9 mediainfo");
		shell_exec("killall -qo {$array['KILL_PROCESS']}s -9 ffmpeg");
	} else {
		shell_exec("killall -o {$array['KILL_PROCESS']}s -9 mediainfo 2>&1 1> /dev/null");
		shell_exec("killall -o {$array['KILL_PROCESS']}s -9 ffmpeg 2>&1 1> /dev/null");
	}

	if (( $array['KILL_QUIET'] == "true" ) && ( $optimize_safe_to_run == "true" )) {
		shell_exec("killall -q mediainfo");
		shell_exec("killall -q ffmpeg");
	} else {
		shell_exec("killall mediainfo 2>&1 1> /dev/null");
		shell_exec("killall ffmpeg 2>&1 1> /dev/null");
	}

	$i++;
	//turn of monitor if set to false
	if ( $array['RUNNING'] != "true" ) {
		$i=0;
	}
	sleep(5);

	while (( ! shell_exec("$_tmux list-panes -t {$array['TMUX_SESSION']}:1 | grep 4: | grep dead" )) && ( $array['OPTIMIZE'] == "true" ))
	{
		sleep(1);
	}
    }

?>

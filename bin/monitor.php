<?php

require(dirname(__FILE__)."/config.php");
require(WWW_DIR.'/lib/postprocess.php');
require_once (WWW_DIR.'/lib/site.php');
require_once("../test/ColorCLI.php");
require_once("../test/showsleep.php");
require_once("../test/functions.php");


$version="0.3r709";

$db = new DB();
$functions = new Functions();
$s = new Sites();
$site = $s->get();
$patch = $site->dbversion;
$c = new ColorCLI();
$DIR = dirname (__FILE__);
$port = NNTP_PORT;
$host = NNTP_SERVER;
$ip = gethostbyname($host);
//totals per category in db, results by parentID
$qry = "SELECT c.parentID AS parentID, COUNT(r.ID) AS count FROM category c, releases r WHERE r.categoryID = c.ID GROUP BY c.parentID";

//needs to be processed query
$proc = "SELECT
( SELECT COUNT(*) FROM releases USE INDEX(ix_releases_status) WHERE categoryID BETWEEN 1000 AND 1999 AND consoleinfoID IS NULL ) AS console,
( SELECT COUNT(*) FROM releases USE INDEX(ix_releases_status) WHERE categoryID BETWEEN 2000 AND 2999 AND imdbID IS NULL ) AS movies,
( SELECT COUNT(*) FROM releases USE INDEX(ix_releases_status) WHERE categoryID BETWEEN 3000 AND 3999 AND musicinfoID IS NULL) AS audio,
( SELECT COUNT(*) FROM releases r USE INDEX(ix_releases_status), category c WHERE c.ID = r.categoryID AND c.parentID = 6000 AND r.passwordstatus BETWEEN -6 AND -1 AND r.haspreview = -1 AND c.disablepreview = 0) AS xxx,
( SELECT COUNT(*) FROM releases r USE INDEX(ix_releases_status), category c WHERE c.ID = r.categoryID AND c.parentID = 4000 AND r.passwordstatus BETWEEN -6 AND -1 AND r.haspreview = -1 AND c.disablepreview = 0) AS pc,
( SELECT COUNT(*) FROM releases USE INDEX(ix_releases_status) WHERE categoryID BETWEEN 5000 AND 5999 AND rageID = -1) AS tv,
( SELECT COUNT(*) FROM releases USE INDEX(ix_releases_status) WHERE categoryID = 7020 AND bookinfoID IS NULL ) AS book,
( SELECT COUNT(*) FROM releases r USE INDEX(ix_releases_status), category c WHERE c.ID = r.categoryID AND r.passwordstatus BETWEEN -6 AND -1 AND r.haspreview = -1 AND c.disablepreview = 0) AS work,
( SELECT COUNT(*) FROM releases USE INDEX(ix_releases_status) WHERE (bitwise & 256) = 256) AS releases,
( SELECT COUNT(*) FROM releases USE INDEX(ix_releases_status) WHERE nfostatus = 0) AS nforemains,
( SELECT COUNT(*) FROM releases USE INDEX(ix_releases_status) WHERE nfostatus = 1) AS nfo,
( SELECT table_rows AS cnt FROM information_schema.TABLES where table_name = 'parts' AND TABLE_SCHEMA = '".DB_NAME."' ) AS parts,
( SELECT COUNT(ID) FROM binaries WHERE procstat = 0 ) AS binaries,
( SELECT table_rows AS cnt FROM information_schema.TABLES where table_name = 'binaries' AND TABLE_SCHEMA = '".DB_NAME."' ) AS binaries_total,
( SELECT concat(round((data_length+index_length)/(1024*1024*1024),2),'GB') AS cnt FROM information_schema.tables where table_name = 'parts' AND TABLE_SCHEMA = '".DB_NAME."' ) AS partsize,
( SELECT concat(round((data_length+index_length)/(1024*1024*1024),2),'GB') AS cnt FROM information_schema.tables where table_name = 'binaries' AND TABLE_SCHEMA = '".DB_NAME."' ) AS binariessize,
( SELECT UNIX_TIMESTAMP(adddate) FROM releases USE INDEX(ix_releases_status) WHERE (bitwise & 256) = 256 ORDER BY adddate DESC LIMIT 1 ) AS newestadd,
( SELECT COUNT( ID ) FROM groups WHERE active = 1 ) AS active_groups,
( SELECT COUNT( ID ) FROM groups WHERE name IS NOT NULL ) AS all_groups,
( SELECT COUNT( ID ) FROM groups WHERE first_record IS NOT NULL and `backfill_target` > 0 and first_record_postdate != '2000-00-00 00:00:00'  < first_record_postdate) AS backfill_groups,
( SELECT UNIX_TIMESTAMP(adddate) from prehash order by adddate DESC limit 1 ) AS newestprehash,
( SELECT UNIX_TIMESTAMP(updatedate) from predb order by updatedate DESC limit 1 ) AS newestpredb,
( SELECT TABLE_ROWS FROM INFORMATION_SCHEMA.TABLES where table_name = 'prehash' AND TABLE_SCHEMA = '".DB_NAME."' ) AS prehash,
( SELECT name from releases order by adddate desc limit 1 ) AS newestaddname";
//$proc = "SELECT * FROM procCnt;";
$proc2 = "SELECT
	(SELECT COUNT(*) FROM releases USE INDEX(ix_releases_status) WHERE (bitwise & 1284) = 1280 AND reqidstatus in (0, -1) OR (reqidstatus = -3 AND adddate > NOW() - INTERVAL 2 HOUR)) AS requestid_inprogress,
	(SELECT COUNT(*) FROM releases USE INDEX(ix_releases_status) WHERE (bitwise & 256) = 256 AND reqidstatus = 1 OR reqID IS NOT NULL) AS requestid_matched,
	(SELECT COUNT(*) FROM releases USE INDEX(ix_releases_status) WHERE (bitwise & 256) = 256 AND preID IS NOT NULL) AS prehash_matched,
	(SELECT COUNT(DISTINCT(preID)) FROM releases) AS distinct_prehash_matched";

$split_query = "SELECT
    ( SELECT UNIX_TIMESTAMP(adddate) FROM releases USE INDEX(ix_releases_status) ORDER BY adddate DESC LIMIT 1 ) AS newestadd";

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
$_sleep = "$_php ${DIR}/../test/showsleep.php";


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
$time30 = TIME();
$time31 = TIME();
$time32 = TIME();


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
$newestadd = TIME();
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
$request_percent = 0;
$requestid_inprogress_start = 0;
$requestid_inprogress = 0;
$requestid_diff = 0;
$requestid_matched = 0;
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
$pre_percent = 0;
$distinct_prehash_matched = 0;

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

$mask1 = $c->headerOver("%-18s")." ".$c->tmuxOrange("%-48.48s");
$mask2 = $c->headerOver("%-20s")." ".$c->tmuxOrange("%-33.33s");
$mask3 = $c->header("%-16.16s %25.25s %25.25s");
$mask4 = $c->primaryOver("%-16.16s") . " " . $c->tmuxOrange("%25.25s %25.25s");
$mask5 = $c->tmuxOrange("%-16.16s %25.25s %25.25s");



//create initial display, USP connection count, prehash count and groups count adapted from nZEDb
passthru('clear');
//printf("\033[1;31m  First insert:\033[0m ".relativeTime("$firstdate")."\n");
printf($mask2, "Monitor Running v$version [".$patch."]: ", relativeTime("$time"));
printf($mask1, "USP Connections:", $uspactiveconnections . " active (" . $usptotalconnections . " total) - " . $host . ":" . $port);;
printf($mask1, "Newest Release:", "$newestname");
printf($mask1, "Release Added:", relativeTime("$newestadd")."ago");
if ($array['PREDB'] = "true"){
    printf($mask1, "Predb Updated:", relativeTime("$newestpredb")."ago");
}
if  ($array ['FIXRELEASES'] = "true") {
    printf($mask1, "Prehash Updated:", relativeTime("$newestprehash")."ago");
}
printf($mask3, "Category", "State", "Reason");
printf($mask3, "====================", "====================", "====================");
printf($mask4, "Binaries", "$binaries_state", "$binaries_reason");
printf($mask4, "Backfill", "$backfill_state", "$backfill_reason");
printf($mask4, "Import", "$import_state", "$import_reason");
printf($mask4, "Releases", "$releases_state", "$releases_reason");
printf($mask4, "Parts", "$parts_size_gb", "$parts_rows rows");
printf($mask4, "Binaries", "$binaries_size_gb", $binaries_rows."/".$binaries_total." bins");
if ( $array['RAMDISK_PATH'] != "" ) {
	printf($mask4, "Ramdisk", "$disk_use", "$disk_free");
}
printf($mask3, "Category", "In Process", "In Database");
printf($mask3, "====================", "====================", "====================");
if ($array ['FIXRELEASES'] = "true"){
printf($mask4, "prehash", number_format($prehash - $distinct_prehash_matched) . "(" . $pre_diff . ")", number_format($prehash_matched) . "(" . $pre_percent . "%)");
}
if ($array ['REQID'] = "true"){
printf($mask4, "requestID", $requestid_inprogress . "(" . $requestid_diff . ")", number_format($requestid_matched) . "(" . $request_percent . "%)");
}
printf($mask4, "NFO's","$nfo_remaining_now_formatted($nfo_diff)","$nfo_now_formatted($nfo_percent%)");
printf($mask4, "Console(1000)","$console_releases_proc_formatted($console_diff)","$console_releases_now_formatted($console_percent%)");
printf($mask4, "Movie(2000)","$movie_releases_proc_formatted($movie_diff)","$movie_releases_now_formatted($movie_percent%)");
printf($mask4, "Audio(3000)","$music_releases_proc_formatted($music_diff)","$music_releases_now_formatted($music_percent%)");
printf($mask4, "PC(4000)","$pc_releases_proc_formatted($pc_diff)","$pc_releases_now_formatted($pc_percent%)");
printf($mask4, "TVShows(5000)","$tvrage_releases_proc_formatted($tvrage_diff)","$tvrage_releases_now_formatted($tvrage_percent%)");
printf($mask4, "XXX(6000)","$xxx_releases_proc_formatted($xxx_diff)","$xxx_releases_now_formatted($xxx_percent%)");
printf($mask4, "Books(7000)","$book_releases_proc_formatted($book_diff)","$book_releases_now_formatted($book_percent%)");
printf($mask4, "Misc(8000)","$misc_remaining_now_formatted($misc_diff)","$misc_releases_now_formatted($misc_percent%)");
printf($mask4, "Total", "$total_work_now_formatted($work_diff)", "$releases_now_formatted($releases_since_start)");

printf($mask3, "Category", "Time", "Status");
printf($mask3, "====================", "====================", "====================");
printf($mask4, "DB Lagg","$query_timer","0 qps");

printf($mask3, "Groups", "Active", "Backfill");
printf($mask3, "====================", "=========================", "=========================");
printf($mask4, "Activated", $active_groups."(".$all_groups.")", $backfill_groups. "(".$all_groups.")");

$i = 1;
$time33 = TIME();
while( $i > 0 )
{
     //kill mediainfo and ffmpeg if exceeds 60 sec
	shell_exec("killall -o 60s -9 mediainfo 2>&1 1> /dev/null");
	shell_exec("killall -o 60s -9 ffmpeg 2>&1 1> /dev/null");

    // Ananlyze tables every 60 min

	if ($i == 1 || (TIME() - $time33 >= 3600)) {
	    printf($c->info("\nAnalyzing your tables to refresh your indexes."));
		$functions->optimise(true, 'analyze');
		$time33 = TIME();
	}
    
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
   /*	if ( $i == 1 ) {
		printf("\n\033[1;31mTmux panes safely created in ");
		for($a=10;$a>-1;$a--)
		{
			printf("$a..");
			sleep(1);
		}*/
		$rel = $db->query("UPDATE `binaries` SET `procstat`=0,`procattempts`=0,`regexID`=NULL, `relpart`=0,`reltotalpart`=0,`relname`=NULL WHERE procstat not in (4, 6)");
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
		shell_exec("$_tmux respawnp -k -t {$array['TMUX_SESSION']}:1.5 'sleep 5' && $ds1 $panes1[5] $ds4");
		$_imports = $array['NZB_THREADS'];
	}

	//run queries
    $time01 = TIME();
	if ((( TIME() - $time19 ) >= $array['MONITOR_UPDATE'] ) || ( $i == 1 )) {
	    echo $c->info("\nThe numbers(queries) above are currently being refreshed. \nNo pane(script) can be (re)started until these have completed.\n");
		//get microtime to at start of queries
		$query_timer_start=microtime_float();

        $time02 = TIME();
		$split_result = $db->query($split_query, false);
		$split_time = (TIME() - $time02);
		$split1_time = (TIME() - $time01);

        $time03 = TIME();
		$initquery = @$db->query($qry, false);
        $init_time = (TIME() - $time03);
		$init1_time = (TIME() - $time01);

        $time04 = TIME();
		$proc_result = @$db->query($proc);
        $proc1_time = (TIME() - $time04);
		$proc11_time = (TIME() - $time01);

        $time05 = TIME();
        $proc_result2 = @$db->query($proc2);
        $proc2_time = (TIME() - $time05);
		$proc21_time = (TIME() - $time01);

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
        if ( @$proc_result2[0]['prehash_matched'] != NULL ) { $prehash_start = $proc_result2[0]['prehash_matched']; }
        if ( @$proc_result2[0]['distinct_prehash_matched'] != NULL) {$distinct_prehash_matched = $proc_result2[0]['distinct_prehash_matched']; }
        if ( @$proc_result2[0]['requestid_inprogress'] != NULL) {$requestid_inprogress_start = $proc_result2[0]['requestid_inprogress']; }
        if ( @$proc_result2[0]['requestid_matched'] != NULL) {$requestid_matched = $proc_result2[0]['requestid_matched']; }
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
	foreach ($initquery as $cat) {
		if ($cat['parentID'] == 1000) {
			$console_releases_now = $cat['count'];
		}
		if ($cat['parentID'] == 2000) {
			$movie_releases_now = $cat['count'];
		}
		if ($cat['parentID'] == 3000) {
			$music_releases_now = $cat['count'];
		}
		if ($cat['parentID'] == 4000) {
			$pc_releases_now = $cat['count'];
		}
		if ($cat['parentID'] == 5000) {
			$tvrage_releases_now = $cat['count'];
		}
		if ($cat['parentID'] == 6000) {
			$xxx_releases_now = $cat['count'];
		}
		if ($cat['parentID'] == 7000) {
			$book_releases_now = $cat['count'];
		}
		if ($cat['parentID'] == 8000) {
			$misc_releases_now = $cat['count'];
		}
	}

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
    if ( @$proc_result[0]['active_groups'] != NULL ) { $active_groups = $proc_result[0]['active_groups']; }
    if ( @$proc_result[0]['all_groups'] != NULL ) { $all_groups = $proc_result[0]['all_groups']; }
    if ( @$proc_result[0]['newestprehash'] ) { $newestprehash = $proc_result[0]['newestprehash']; }
    if ( @$proc_result[0]['newestpredb'] ) { $newestpredb = $proc_result[0]['newestpredb']; }
    if ( @$proc_result[0]['prehash'] != NULL ) { $prehash = $proc_result[0]['prehash']; }
    if ( @$proc_result2[0]['prehash_matched'] != NULL ) { $prehash_matched = $proc_result2[0]['prehash_matched']; }
    if ( @$proc_result2[0]['distinct_prehash_matched'] != NULL) {$distinct_prehash_matched = $proc_result2[0]['distinct_prehash_matched'];}
	if ( @$proc_result2[0]['requestid_inprogress'] != NULL) {$requestid_inprogress = $proc_result2[0]['requestid_inprogress'];}
	if ( @$proc_result2[0]['requestid_matched'] != NULL) {$requestid_matched = $proc_result2[0]['requestid_matched'];}
    if ( @$split_result[0]['newestadd'] != NULL) {$newestadd = $split_result[0]['newestadd'];}

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
    $requestid_diff = number_format($requestid_inprogress - $requestid_inprogress_start);

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
        $request_percent = sprintf("%02s", floor(($requestid_matched / $releases_now) * 100));
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
        $request_percent = 0;
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

	$panes0 = str_replace("\n", '', explode(" ", $panes_win_1));
	$panes1 = str_replace("\n", '', explode(" ", $panes_win_2));
	$panes2 = str_replace("\n", '', explode(" ", $panes_win_3));
	$panes3 = str_replace("\n", '', explode(" ", $panes_win_4));
	$panes4 = str_replace("\n", '', explode(" ", $panes_win_5));

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
$uspactiveconnections = str_replace("\n", '', shell_exec("ss -n | grep " . $ip . ":" . $port . " | grep -c ESTAB"));
$usptotalconnections  = str_replace("\n", '', shell_exec("ss -n | grep -c " . $ip . ":" . $port));
        if ($uspactiveconnections == 0 && $usptotalconnections == 0) {
            $uspactiveconnections = str_replace("\n", '', shell_exec("ss -n | grep " . $ip . ":https | grep -c ESTAB"));
            $usptotalconnections = str_replace("\n", '', shell_exec("ss -n | grep -c " . $ip . ":https"));
        }
        if ($uspactiveconnections == 0 && $usptotalconnections == 0) {
            $uspactiveconnections = str_replace("\n", '', shell_exec("ss -n | grep " . $port . " | grep -c ESTAB"));
            $usptotalconnections = str_replace("\n", '', shell_exec("ss -n | grep -c " . $port));
        }
		if ($uspactiveconnections == 0 && $usptotalconnections == 0 ) {
			$uspactiveconnections = str_replace("\n", '', shell_exec("ss -n | grep " . $ip . " | grep -c ESTAB"));
			$usptotalconnections = str_replace("\n", '', shell_exec("ss -n | grep -c " . $ip));
		}

	//update display
	passthru('clear');
	//printf("\033[1;31m  First insert:\033[0m ".relativeTime("$firstdate")."\n");
	printf($mask2, "Monitor Running v$version [".$patch."]: ", relativeTime("$time"));
    printf($mask1, "USP Connections:", $uspactiveconnections . " active (" . $usptotalconnections . " total) - " . $host . ":" . $port);
	printf($mask1, "Newest Release:", "$newestname");
	printf($mask1, "Release Added:", relativeTime("$newestadd")."ago");
    if ($array['PREDB'] = "true"){
    printf($mask1, "Predb Updated:", relativeTime("$newestpredb")."ago");
    }
if ($array ['FIXRELEASES'] = "true") {
    printf($mask1, "Prehash Updated:", relativeTime("$newestprehash")."ago");
    }

	printf($mask3, "Category", "State", "Reason");
	printf($mask3, "====================", "====================", "====================");
	printf("\033[38;5;214m");
	printf($mask4, "Binaries", "$binaries_state", "$binaries_reason");
	printf($mask4, "Backfill", "$backfill_state", "$backfill_reason");
	printf($mask4, "Import", "$import_state", "$import_reason");
    printf($mask4, "Releases", "$releases_state", "$releases_reason");
	printf($mask4, "Parts", "$parts_size_gb", "$parts_rows rows");
	printf($mask4, "Binaries", "$binaries_size_gb", $binaries_rows."/".$binaries_total." bins");
	if ( $array['RAMDISK_PATH'] != "" ) {
		printf($mask4, "Ramdisk", "$disk_use used", "$disk_free free");
	}

	printf($mask3, "Category", "In Process", "In Database");
	printf($mask3, "====================", "====================", "====================");
    if ($array ['FIXRELEASES'] = "true"){
    printf($mask4, "prehash", number_format($prehash - $distinct_prehash_matched) . "(" . $pre_diff . ")", number_format($prehash_matched) . "(" . $pre_percent . "%)");
    }
    if ($array ['REQID'] = "true"){
    printf($mask4, "requestID", number_format($requestid_inprogress) . "(" . $requestid_diff . ")", number_format($requestid_matched) . "(" . $request_percent . "%)");
    }
	printf($mask4, "NFO's","$nfo_remaining_now_formatted($nfo_diff)","$nfo_now_formatted($nfo_percent%)");
	printf($mask4, "Console(1000)","$console_releases_proc_formatted($console_diff)","$console_releases_now_formatted($console_percent%)");
	printf($mask4, "Movie(2000)","$movie_releases_proc_formatted($movie_diff)","$movie_releases_now_formatted($movie_percent%)");
	printf($mask4, "Audio(3000)","$music_releases_proc_formatted($music_diff)","$music_releases_now_formatted($music_percent%)");
	printf($mask4, "PC(4000)","$pc_releases_proc_formatted($pc_diff)","$pc_releases_now_formatted($pc_percent%)");
	printf($mask4, "TVShows(5000)","$tvrage_releases_proc_formatted($tvrage_diff)","$tvrage_releases_now_formatted($tvrage_percent%)");
    printf($mask4, "XXX(6000)","$xxx_releases_proc_formatted($xxx_diff)","$xxx_releases_now_formatted($xxx_percent%)");
	printf($mask4, "Books(7000)","$book_releases_proc_formatted($book_diff)","$book_releases_now_formatted($book_percent%)");
	printf($mask4, "Misc(8000)","$misc_remaining_now_formatted($misc_diff)","$misc_releases_now_formatted($misc_percent%)");
	printf($mask4, "Total", "$total_work_now_formatted($work_diff)", "$releases_now_formatted($releases_since_start)");

	printf($mask3, "Category", "Time", "Status");
	printf($mask3, "====================", "====================", "====================");
	printf("\033[38;5;214m");
        $get_current_number = str_replace("\n", '', shell_exec($mysqladmin)." qps");
        printf($mask4, "DB Lagg","$query_timer","$get_current_number");

    printf($mask3, "Groups", "Active", "Backfill");
    printf($mask3, "====================", "=========================", "=========================");
    printf($mask4, "Activated", $active_groups."(".$all_groups.")", $backfill_groups. "(".$all_groups.")");

	/*$optimize_safe_to_run = "false";
	$optimize_run = "false";
    */
	$dead1=0;
	$dead2=0;
	$dead3=0;
	$dead4=0;
	$dead5=0;
	$dead6=0;
	$dead7=0;

	//set command for running update_binaries
	if  ($array['BINARIES_THREADS'] == "true")  {
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
	if ( $array['NZB_THREADS'] == "true" )  {
		$nzb_cmd = "$_php nzb-import-sub.php \"{$array['NZBS']}\" 2>&1 $log";
	} else {
		$nzb_cmd = "$_php nzb-import.php \"{$array['NZBS']}\" \"{$array['IMPORT_TRUE']}\" 2>&1 $log";
	}

	//check if sequential is set
	if ( $array['SEQUENTIAL'] != "true" ) {
		//runs update_binaries in 0.2 once if needed and exits
		if (( $array['MAX_LOAD'] >= get_load()) && ( $killed != "true" ) && ( $array['BINARIES'] == "true" ) && (( $binaries_rows_unformatted < $array['BINARIES_MAX_BINS'] ) || ( $array['BINARIES_MAX_BINS'] == 0 )) && (( $total_work_used < $array['BINARIES_MAX_RELEASES'] ) || ( $array['BINARIES_MAX_RELEASES'] == 0 )) && (( $parts_rows_unformatted < $array['BINARIES_MAX_ROWS'] ) || ( $array['BINARIES_MAX_ROWS'] == 0 ))) {
			$color = get_color();
			$log = writelog($panes0[2]);
			shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.2 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes0[2] $ds2 && $_update_cmd 2>&1 $log && echo \" \033[1;0;33m\" &&  $_sleep {$array['BINARIES_SLEEP']} && $ds1 $panes0[2] $ds3' 2>&1 1> /dev/null");
		} elseif (( $parts_rows_unformatted > $array['BINARIES_MAX_ROWS'] ) && ( $array['BINARIES'] == "true" ) && ( $array['BINARIES_MAX_ROWS'] != 0 ))  {
			$color = get_color();
			shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.2 'echo \"\033[38;5;\"$color\"m\nBINARIES_MAX_ROWS exceeded\" && date +\"%D %T\"' 2>&1 1> /dev/null");
		} elseif (( $total_work_used > $array['BINARIES_MAX_RELEASES'] ) && ( $array['BINARIES'] == "true" ) && ( $array['BINARIES_MAX_RELEASES'] != 0 ))  {
			$color = get_color();
			shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.2 'echo \"\033[38;5;\"$color\"m\nBINARIES_MAX_RELEASES exceeded\" && date +\"%D %T\"' 2>&1 1> /dev/null");
		} elseif (( $array['BINARIES'] == "true" ) && ( $array['MAX_LOAD'] <= get_load())) {
                        $color = get_color();
                        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.2 'echo \"\033[38;5;\"$color\"m\n$panes0[2] Disabled by MAX_LOAD\" && date +\"%D %T\"' 2>&1 1> /dev/null");
                } elseif ( $array['BINARIES'] != "true" )  {
			$color = get_color();
			shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.2 'echo \"\033[38;5;\"$color\"m\n$panes0[2] Disabled by BINARIES\" && date +\"%D %T\"' 2>&1 1> /dev/null");
		}

		//runs backfill in 0.3 once if needed and exits
		if (( $array['MAX_LOAD'] >= get_load()) && ( $killed != "true" ) && ( $array['BACKFILL'] == "true" ) && (( $binaries_rows_unformatted < $array['BACKFILL_MAX_BINS'] ) || ( $array['BACKFILL_MAX_BINS'] == 0 )) && (( $total_work_used < $array['BACKFILL_MAX_RELEASES'] ) || ( $array['BACKFILL_MAX_RELEASES'] == 0 )) && (( $parts_rows_unformatted < $array['BACKFILL_MAX_ROWS'] ) || ( $array['BACKFILL_MAX_ROWS'] == 0 ))) {
			$color = get_color();
			$log = writelog($panes0[3]);
			shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.3 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes0[3] $ds2 && $_backfill_cmd && echo \" \033[1;0;33m\" && $_sleep {$array['BACKFILL_SLEEP']} && $ds1 $panes0[3] $ds3' 2>&1 1> /dev/null");
		} elseif (( $parts_rows_unformatted > $array['BACKFILL_MAX_ROWS'] ) && ( $array['BACKFILL'] == "true" ) && ( $array['BACKFILL_MAX_ROWS'] != 0 ))  {
			$color = get_color();
			shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.3 'echo \"\033[38;5;\"$color\"m\nBACKFILL_MAX_ROWS exceeded\" && date +\"%D %T\"' 2>&1 1> /dev/null");
		} elseif (( $total_work_used > $array['BACKFILL_MAX_RELEASES'] ) && ( $array['BACKFILL'] == "true" ) && ( $array['BACKFILL_MAX_RELEASES'] != 0 ))  {
			$color = get_color();
			shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.3 'echo \"\033[38;5;\"$color\"m\nBACKFILL_MAX_RELEASES exceeded\" && date +\"%D %T\"' 2>&1 1> /dev/null");
		} elseif (( $array['BACKFILL'] == "true" ) && ( $array['MAX_LOAD'] <= get_load())) {
                        $color = get_color();
                        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.3 'echo \"\033[38;5;\"$color\"m\n$panes0[3] Disabled by MAX_LOAD\" && date +\"%D %T\"' 2>&1 1> /dev/null");
                } elseif ( $array['BACKFILL'] != "true" ) {
			$color = get_color();
			shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.3 'echo \"\033[38;5;\"$color\"m\n$panes0[3] Disabled by BACKFILL\" && date +\"%D %T\"' 2>&1 1> /dev/null");
		}
	}

	//run update_binaries and backfill using seq in pane 0.2
	$dead = `$_tmux list-panes -t {$array['TMUX_SESSION']}:0 | grep 2: | grep dead`;
	if (( $array['SEQUENTIAL'] == "true" ) && ( strlen( $dead ) > "4" )) {
		if (( $array['MAX_LOAD'] >= get_load()) && (( $i == 1 ) || ( TIME() - $time13 >= $array['BINARIES_SEQ_TIMER'] )) && (( $binaries_rows_unformatted < $array['BINARIES_MAX_BINS'] ) || ( $array['BINARIES_MAX_BINS'] == 0 )) && ( $array['BINARIES'] == "true" ) && (( $total_work_used < $array['BINARIES_MAX_RELEASES'] ) || ( $array['BINARIES_MAX_RELEASES'] == 0 )) && (( $parts_rows_unformatted < $array['BINARIES_MAX_ROWS'] ) || ( $array['BINARIES_MAX_ROWS'] == 0 ))) {
			$color = get_color();
			shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.2 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes0[2] $ds2 && $_update_cmd && echo \" \033[1;0;33m\" && $ds1 $panes0[2] $ds3' 2>&1 1> /dev/null");
			$time13 = TIME();
		} elseif (( $array['MAX_LOAD'] >= get_load()) && ( TIME() - $time14 >= $array['BACKFILL_SEQ_TIMER'] ) && (( $binaries_rows_unformatted < $array['BACKFILL_MAX_BINS'] ) || ( $array['BACKFILL_MAX_BINS'] == 0 )) && ( $array['BACKFILL'] == "true" ) && (( $total_work_used < $array['BACKFILL_MAX_RELEASES'] ) || ( $array['BACKFILL_MAX_RELEASES'] == 0 )) && (( $parts_rows_unformatted < $array['BACKFILL_MAX_ROWS'] ) || ( $array['BACKFILL_MAX_ROWS'] == 0 )))  {
			$color = get_color();
			shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.2 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes0[3] $ds2 && $_backfill_cmd && echo \" \033[1;0;33m\" && $ds1 $panes0[3] $ds3' 2>&1 1> /dev/null");
			$time14 = TIME();
		} elseif (( $array['BINARIES'] != "true" ) && ( $array['BACKFILL'] != "true" )) {
			$color = get_color();
			shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.2 'echo \"\033[38;5;\"$color\"m\n$panes0[2] and $panes0[3] Disabled by BINARIES and BACKFILL\" && date +\"%D %T\"' 2>&1 1> /dev/null");
		} elseif (( $array['BACKFILL'] == "true" ) && ( $array['BINARIES'] == "true" ) && ( $array['MAX_LOAD'] >= get_load())) {
			$color = get_color();
			$run_time1 = relativeTime( $array['BACKFILL_SEQ_TIMER'] + $time14 );
			$run_time2 = relativeTime( $array['BINARIES_SEQ_TIMER'] + $time13 );
			shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.2 'echo \"\033[38;5;\"$color\"m\n$panes0[2] will run in T[ $run_time2]\n$panes0[3] will run in T[ $run_time1] \" && date +\"%D %T\"' 2>&1 1> /dev/null");
		} elseif (( $array['BACKFILL'] == "true" ) && ( $array['MAX_LOAD'] >= get_load())) {
			$color = get_color();
			$run_time = relativeTime( $array['BACKFILL_SEQ_TIMER'] + $time14 );
			shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.2 'echo \"\033[38;5;\"$color\"m\n$panes03] will run in T[ $run_time1]\" && date +\"%D %T\"' 2>&1 1> /dev/null");
		} elseif (( $array['BINARIES'] == "true" ) && ( $array['MAX_LOAD'] >= get_load())) {
			$color = get_color();
			$run_time = relativeTime( $array['BINARIES_SEQ_TIMER'] + $time13 );
			shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.2 'echo \"\033[38;5;\"$color\"m\n$panes0[2] will run in T[ $run_time2]\" && date +\"%D %T\"' 2>&1 1> /dev/null");
		} elseif  ( $array['MAX_LOAD'] <= get_load()) {
                        $color = get_color();
                        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.2 'echo \"\033[38;5;\"$color\"m\n$panes0[2] Disabled by MAX_LOAD\" && date +\"%D %T\"' 2>&1 1> /dev/null");
                }
	}

	//notify pane 0.3 of the status of pane 0.2
	if ( $array['SEQUENTIAL'] == "true" ) {
		if (( $array['MAX_LOAD'] >= get_load()) && ( $array['BINARIES'] != "true" ) && ( $array['BACKFILL'] != "true" )) {
			$color = get_color();
			shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.3 'echo \"\033[38;5;\"$color\"m\n$panes0[2] and $panes0[3] Disabled by BINARIES and BACKFILL\" && date +\"%D %T\"' 2>&1 1> /dev/null");
		} elseif (( $array['BACKFILL'] == "true" ) && ( $array['BINARIES'] == "true" ) && ( $array['MAX_LOAD'] >= get_load())) {
			$run_time1 = relativeTime( $array['BACKFILL_SEQ_TIMER'] + $time14 );
			$run_time2 = relativeTime( $array['BINARIES_SEQ_TIMER'] + $time13 );
			$color = get_color();
			shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.3 'echo \"\033[38;5;\"$color\"m\n$panes0[2] will run in T[ $run_time2]\n$panes0[3] will run in T[ $run_time1] \" && date +\"%D %T\"' 2>&1 1> /dev/null");
		} elseif (( $array['BACKFILL'] == "true" ) && ( $array['MAX_LOAD'] >= get_load())) {
			$run_time = relativeTime( $array['BACKFILL_SEQ_TIMER'] + $time14 );
			$color = get_color();
			shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.3 'echo \"\033[38;5;\"$color\"m\n$panes0[3] will run in T[ $run_time1]\" && date +\"%D %T\"' 2>&1 1> /dev/null");
		} elseif (( $array['BINARIES'] == "true" ) && ( $array['MAX_LOAD'] >= get_load())) {
			$run_time = relativeTime( $array['BINARIES_SEQ_TIMER'] + $time13 );
			$color = get_color();
			shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.3 'echo \"\033[38;5;\"$color\"m\n$panes0[2] will run in T[ $run_time2]\" && date +\"%D %T\"' 2>&1 1> /dev/null");
		} elseif ( $array['MAX_LOAD'] <= get_load()) {
                        $color = get_color();
                        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.3 'echo \"\033[38;5;\"$color\"m\n$panes0[3] Disabled by MAX_LOAD\" && date +\"%D %T\"' 2>&1 1> /dev/null");
                }
	}

	//kill panes for sequential
	if ( $array['SEQUENTIAL'] == "true" ) {
		if (( $parts_rows_unformatted > $array['BINARIES_MAX_ROWS'] ) && ( $array['BINARIES'] == "true" ) && ( $array['BINARIES_MAX_ROWS'] != 0 ) && ( $array['BACKFILL'] == "true" ) && ( $total_work_used > $array['BACKFILL_MAX_RELEASES'] ) && ( $array['BACKFILL_MAX_ROWS'] != 0 )) {
			$color = get_color();
			shell_exec("$_tmux respawnp -k -t {$array['TMUX_SESSION']}:0.2 'echo \"\033[38;5;\"$color\"m\nBINARIES_MAX_ROWS and BACKFILL_MAX_ROWS exceeded\" && $ds1 $panes0[2] $ds4'");
			shell_exec("$_tmux respawnp -k -t {$array['TMUX_SESSION']}:0.3 'echo \"\033[38;5;\"$color\"m\nBINARIES_MAX_ROWS and BACKFILL_MAX_ROWS exceeded\" && $ds1 $panes0[3] $ds4'");
		}
	}

	//runs nzb-import in 0.4 once if needed and exits
	if (( $array['MAX_LOAD'] >= get_load()) && ( $killed != "true" ) && ( $array['IMPORT'] == "true" ) && (( $total_work_used < $array['IMPORT_MAX_RELEASES'] ) || ( $array['IMPORT_MAX_RELEASES'] == 0 )) && (( $parts_rows_unformatted < $array['IMPORT_MAX_ROWS'] ) || ( $array['IMPORT_MAX_ROWS'] == 0 ))) {
		$color = get_color();
		$log = writelog($panes0[4]);
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.4 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes0[4] $ds2 && cd $_bin && $nzb_cmd && echo \" \" && echo \" \033[1;0;33m\" && $_sleep {$array['IMPORT_SLEEP']} && $ds1 $panes0[4] $ds3' 2>&1 1> /dev/null");
		$color = get_color();
		$log = writelog($panes1[5]);
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.5 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes1[5] $ds2 && cd $_bin && $_php nzbcount.php 2>&1 $log' 2>&1 1> /dev/null");

	} elseif (( $parts_rows_unformatted > $array['IMPORT_MAX_ROWS'] ) && ( $array['IMPORT'] == "true" ) && ( $array['IMPORT_MAX_ROWS'] != 0 )) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.4 'echo \"\033[38;5;\"$color\"m\nIMPORT_MAX_ROWS exceeded\" && date +\"%D %T\"' 2>&1 1> /dev/null");

	} elseif (( $total_work_used > $array['IMPORT_MAX_RELEASES'] ) && ( $array['IMPORT'] == "true" ) && ( $array['IMPORT_MAX_RELEASES'] != 0 )) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.4 'echo \"\033[38;5;\"$color\"m\nIMPORT_MAX_RELEASES exceeded\" && date +\"%D %T\"' 2>&1 1> /dev/null");

	} elseif (( $array['IMPORT'] == "true" ) && ( $array['MAX_LOAD'] <= get_load())) {
                $color = get_color();
                shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.4 'echo \"\033[38;5;\"$color\"m\n$panes0[4] Disabled by MAX_LOAD\" && date +\"%D %T\"' 2>&1 1> /dev/null");

        } elseif ( $array['IMPORT'] != "true" ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.4 'echo \"\033[38;5;\"$color\"m\n$panes0[4] Disabled by IMPORT\" && date +\"%D %T\"' 2>&1 1> /dev/null");

	}

	//runs nzbcount in 1.5 loops
	if (( $array['MAX_LOAD'] >= get_load()) && ( $array['IMPORT'] == "true" )) {
		$color = get_color();
		$log = writelog($panes1[5]);
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.5 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes1[5] $ds2 && cd $_bin && $_php nzbcount.php 2>&1 $log' 2>&1 1> /dev/null");
	} elseif ( $array['IMPORT'] != "true" ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.5 'echo \"\033[38;5;\"$color\"m\n$panes1[5] Disabled by IMPORT\" && date +\"%D %T\"' 2>&1 1> /dev/null");
	} elseif ( $array['MAX_LOAD'] <= get_load()) {
                $color = get_color();
                shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.5 'echo \"\033[38;5;\"$color\"m\n$panes1[5] Disabled by MAX_LOAD\" && date +\"%D %T\"' 2>&1 1> /dev/null");
        }

	//start postprocessing in pane 0.1

        $g=1;
        $h=$g-1;
        $f=$h*200;
        $j=$g*1;
		$color = get_color();
		$log = writelog($panes0[1]);
		if (( $array['MAX_LOAD'] >= get_load()) && ( $work_remaining_now > $f )) {
			shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.1 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes0[1] $ds2 && cd $_temp && $_php processAdditional$g.php 2>&1 $log && echo \" \033[1;0;33m\" && $ds1 $panes0[1] $ds3' 2>&1 1> /dev/null");
		} elseif ( $work_remaining_now <= $f ) {
			shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.1 'echo \"\033[38;5;\"$color\"m\n$panes0[1] $work_remaining_now < $f\nHas no work to process \" && date +\"%D %T\"' 2>&1 1> /dev/null");
		} elseif ( $array['MAX_LOAD'] <= get_load()) {
                        shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.1 'echo \"\033[38;5;\"$color\"m\n$panes0[1] Disabled by MAX_LOAD\" && date +\"%D %T\"' 2>&1 1> /dev/null");
                }

	//runs update_release and in 0.5 once if needed and exits
	if (( $array['MAX_LOAD_RELEASES'] >= get_load()) && ( $array['RELEASES'] == "true" )) {
		$color = get_color();
		$log = writelog($panes0[5]);
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.5 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes0[5] $ds2 && cd $_bin && $_php update_releases.php && 2>&1 $log && echo \" \033[1;0;33m\" && $_sleep {$array['RELEASES_SLEEP']} && $ds1 $panes0[5] $ds3' 2>&1 1> /dev/null");
	} elseif (( $array['MAX_LOAD_RELEASES'] >= get_load()) && ( $array['RELEASES'] == "true" )) {
		$color = get_color();
		$log = writelog($panes0[5]);
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.5 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes0[5] $ds2 && cd $_bin && $_php update_releases.php 2>&1 $log && echo \" \033[1;0;33m\" && $_sleep {$array['RELEASES_SLEEP']}  && $ds1 $panes0[5] $ds3' 2>&1 1> /dev/null");
	} elseif (( $array['RELEASES'] == "true" ) && ( $array['MAX_LOAD_RELEASES'] <= get_load())) {
                $color = get_color();
                shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.5 'echo \"\033[38;5;\"$color\"m\n$panes0[5] Disabled by MAX_LOAD_RELEASES\" && date +\"%D %T\"' 2>&1 1> /dev/null");
        } elseif ( $array['RELEASES'] != "true" ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:0.5 'echo \"\033[38;5;\"$color\"m\n$panes0[5] Disabled by RELEASES\" && date +\"%D %T\"' 2>&1 1> /dev/null");
	}

	//run update_predb.php in 1.0 ever 15 minutes and on fifth loop
	if (( $array['MAX_LOAD'] >= get_load()) && ((( TIME() - $time2 ) >= $array['PREDB_TIMER'] ) || ( $i == 5 )) && ( $array['PREDB'] == "true" )) {
		$color = get_color();
		$log = writelog($panes1[0]);
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.0 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes1[0] $ds2 && cd $NNPATH && $_php update_predb.php true 2>&1 $log && echo \" \033[1;0;33m\" && $ds1 $panes1[0] $ds3' 2>&1 1> /dev/null");
		$time2 = TIME();
	} elseif (( $array['PREDB'] == "true" ) && ( $array['MAX_LOAD'] >= get_load())) {
		$color = get_color();
		$run_time = relativeTime( $array['PREDB_TIMER'] + $time2 );
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.0 'echo \"\033[38;5;\"$color\"m\n$panes1[0] will run in T[ $run_time]\" && date +\"%D %T\"' 2>&1 1> /dev/null");
	} elseif (( $array['MAX_LOAD'] >= get_load()) && ( $array['PREDB'] != "true" )) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.0 'echo \"\033[38;5;\"$color\"m\n$panes1[0] Disabled by PREDB\" && date +\"%D %T\"' 2>&1 1> /dev/null");
        } elseif ( $array['MAX_LOAD'] <= get_load()) {
                $color = get_color();
                shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.0 'echo \"\033[38;5;\"$color\"m\n$panes1[0] Disabled by MAX_LOAD\" && date +\"%D %T\"' 2>&1 1> /dev/null");
	}

	//run update_tvschedule.php and $_php update_theaters.php in 1.1 every 12 hours and tenth loop
	if (( $array['MAX_LOAD'] >= get_load()) && ((( TIME() - $time4 ) >= $array['TVRAGE_TIMER'] ) || ( $i == 10 )) && ( $array['TV_SCHEDULE'] == "true")) {
		$color = get_color();
		$log = writelog($panes1[1]);
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.1 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes1[1] $ds2 && cd $NNPATH && $_php update_tvschedule.php 2>&1 $log && $_php update_theaters.php 2>&1 $log && echo \" \033[1;0;33m\" && $ds1 $panes1[1] $ds3' 2>&1 1> /dev/null");
		$time4 = TIME();
	} elseif (( $array['TV_SCHEDULE'] == "true") && ( $array['MAX_LOAD'] >= get_load())) {
		$color = get_color();
		$run_time = relativeTime( $array['TVRAGE_TIMER'] + $time4 );
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.1 'echo \"\033[38;5;\"$color\"m\n$panes1[1] and update_theaters will run in T[ $run_time]\" && date +\"%D %T\"' 2>&1 1> /dev/null");
	} elseif ( $array['TV_SCHEDULE'] != "true") {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.1 'echo \"\033[38;5;\"$color\"m\n$panes1[1] Disabled by TV_SCHEDULE\" && date +\"%D %T\"' 2>&1 1> /dev/null");
	} elseif ( $array['MAX_LOAD'] <= get_load()) {
                $color = get_color();
                shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.1 'echo \"\033[38;5;\"$color\"m\n$panes1[1] Disabled by MAX_LOAD\" && date +\"%D %T\"' 2>&1 1> /dev/null");
        }

	//run sphinx in pane 1.2
	if (( $array['MAX_LOAD'] >= get_load()) && ( TIME() - $time9 >= $array['SPHINX_TIMER'] ) && ( $array['SPHINX'] == "true")) {
		$color=get_color();
		$log = writelog($panes1[2]);
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.2 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes1[2] $ds2 && cd $_bin && $_php sphinx.php 2>&1 $log && echo \" \033[1;0;33m\" && $ds1 $panes1[2] $ds3' 2>&1 1> /dev/null");
		$time9 = TIME();
	} elseif ( $array['SPHINX'] != "true" ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.2 'echo \"\033[38;5;\"$color\"m\n$panes1[2] Disabled by SPHINX\" && date +\"%D %T\"' 2>&1 1> /dev/null");
	} elseif ( $array['MAX_LOAD'] >= get_load()) {
		$color = get_color();
		$run_time = relativeTime( $array['SPHINX_TIMER'] + $time9 );
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.2 'echo \"\033[38;5;\"$color\"m\n$panes1[2] will run in T[ $run_time]\" && date +\"%D %T\"' 2>&1 1> /dev/null");
	} elseif ( $array['MAX_LOAD'] <= get_load()) {
                $color = get_color();
                shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.2 'echo \"\033[38;5;\"$color\"m\n$panes1[2] Disabled by MAX_LOAD\" && date +\"%D %T\"' 2>&1 1> /dev/null");
        }

	//run delete parts in pane 1.3
	if (( $array['MAX_LOAD'] >= get_load()) && (( TIME() - $time16 ) >= $array['DELETE_TIMER'] ) && ( $array['DELETE_PARTS'] == "true" )) {
		$color = get_color();
		$log = writelog($panes1[3]);
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.3 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes1[3] $ds2 && cd $_cj && $_php remove_parts_without_releases.php 2>&1 $log && $ds1 $panes1[3] $ds3' 2>&1 1> /dev/null");
		$time16 = TIME();
	} elseif (( $array['DELETE_PARTS'] != "true" )) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.3 'echo \"\033[38;5;\"$color\"m\n$panes1[3] Disabled by DELETE_PARTS\" && date +\"%D %T\"' 2>&1 1> /dev/null");
	} elseif ( $array['MAX_LOAD'] >= get_load()) {
		$color = get_color();
		$run_time = relativeTime( $array['DELETE_TIMER'] + $time16 );
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.3 'echo \"\033[38;5;\"$color\"m\n$panes1[3] will run in T[ $run_time]\" && date +\"%D %T\"' 2>&1 1> /dev/null");
	} elseif ( $array['MAX_LOAD'] <= get_load()) {
                $color = get_color();
                shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.3 'echo \"\033[38;5;\"$color\"m\n$panes1[3] Disabled by MAX_LOAD\" && date +\"%D %T\"' 2>&1 1> /dev/null");
        }

	//run update_missing_movie_info parts in pane 1.4 on 15th loop
	if (( $array['MAX_LOAD'] >= get_load()) && ((( TIME() - $time17 ) >= $array['MOVIE_TIMER'] ) || ( $i == 15 )) && ( $array['FETCH_MOVIE'] == "true" )) {
		$color = get_color();
		$log = writelog($panes1[4]);
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.4 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes1[4] $ds2 && cd $_cj && $_php update_missing_movie_info.php 2>&1 $log && $ds1 $panes1[4] $ds3' 2>&1 1> /dev/null");
		$time17 = TIME();
	} elseif ( $array['FETCH_MOVIE'] != "true" ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.4 'echo \"\033[38;5;\"$color\"m\n$panes1[4] Disabled by FETCH_MOVIE\" && date +\"%D %T\"' 2>&1 1> /dev/null");
	} elseif ( $array['MAX_LOAD'] >= get_load()) {
		$color = get_color();
		$run_time = relativeTime( $array['MOVIE_TIMER'] + $time17 );
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.4 'echo \"\033[38;5;\"$color\"m\n$panes1[4] will run in T[ $run_time]\" && date +\"%D %T\"' 2>&1 1> /dev/null");
	} elseif ( $array['MAX_LOAD'] <= get_load()) {
                $color = get_color();
                shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:1.4 'echo \"\033[38;5;\"$color\"m\n$panes1[4] Disabled by MAX_LOAD\" && date +\"%D %T\"' 2>&1 1> /dev/null");
        }

	//runs postprocess_nfo.php in pane 2.0 once if needed then exits
	if (( $array['MAX_LOAD'] >= get_load()) && ( $nfo_remaining_now > 0 ) && ( $array['NFOS'] != "0" )) {
		$color = get_color();
		$log = writelog($panes2[0]);
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.0 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes2[0] $ds2 && cd $_bin && $_php postprocess_nfo.php 2>&1 $log && echo \" \033[1;0;33m\" && $ds1 $panes2[0] $ds3' 2>&1 1> /dev/null");
	} elseif ( $array['NFOS'] == "0" ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.0 'echo \"\033[38;5;\"$color\"m\n$panes2[0] Disabled by NFOS\" && date +\"%D %T\"' 2>&1 1> /dev/null");
	} elseif ( $array['MAX_LOAD'] <= get_load()) {
                $color = get_color();
                shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.0 'echo \"\033[38;5;\"$color\"m\n$panes2[0] Disabled by MAX_LOAD\" && date +\"%D %T\"' 2>&1 1> /dev/null");
        } elseif ( $nfo_remaining_now == 0 ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.0 'echo \"\033[38;5;\"$color\"m\n$panes2[0] Has no work to process \" && date +\"%D %T\"' 2>&1 1> /dev/null");
        }

	//runs postprocess_nfo1.php in pane 2.4 once if needed then exits
	if (( $array['MAX_LOAD'] >= get_load()) && ( $nfo_remaining_now >= 500 ) && ( $array['NFOS'] == "1" )) {
		$color = get_color();
		$log = writelog($panes2[4]);
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.4 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes2[4] $ds2 && cd $_bin && $_php postprocess_nfo1.php 2>&1 $log && echo \" \033[1;0;33m\" && $ds1 $panes2[4] $ds3' 2>&1 1> /dev/null");
	} elseif ( $array['NFOS'] = "0" ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.4 'echo \"\033[38;5;\"$color\"m\n$panes2[4] Disabled by NFOS\" && date +\"%D %T\"' 2>&1 1> /dev/null");
	} elseif ( $array['MAX_LOAD'] <= get_load()) {
                $color = get_color();
                shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.4 'echo \"\033[38;5;\"$color\"m\n$panes2[4] Disabled by MAX_LOAD\" && date +\"%D %T\"' 2>&1 1> /dev/null");
        } elseif ( $nfo_remaining_now == 0 ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.4 'echo \"\033[38;5;\"$color\"m\n$panes2[4] Has no work to process \" && date +\"%D %T\"' 2>&1 1> /dev/null");
        }

	//runs processGames.php in pane 2.1 once if needed then exits
	if (( $array['MAX_LOAD'] >= get_load()) && ( $console_releases_proc > 0 ) && ( $array['GAMES'] != "0" )) {
		$color = get_color();
		$log = writelog($panes2[1]);
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.1 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes2[1] $ds2 && cd $_bin && $_php processGames.php 2>&1 $log && echo \" \033[1;0;33m\" && $ds1 $panes2[1] $ds3' 2>&1 1> /dev/null");
	} elseif ( $array['GAMES'] == "0" ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.1 'echo \"\033[38;5;\"$color\"m\n$panes2[1] Disabled by GAMES\" && date +\"%D %T\"' 2>&1 1> /dev/null");
	} elseif ( $array['MAX_LOAD'] <= get_load()) {
                $color = get_color();
                shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.1 'echo \"\033[38;5;\"$color\"m\n$panes2[1] Disabled by MAX_LOAD\" && date +\"%D %T\"' 2>&1 1> /dev/null");
        } elseif ( $console_releases_proc == 0 ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.1 'echo \"\033[38;5;\"$color\"m\n$panes2[1] Has no work to process \" && date +\"%D %T\"' 2>&1 1> /dev/null");
        }

	//runs processGames.php in pane 2.5 once if needed then exits
	if (( $array['MAX_LOAD'] >= get_load()) && ( $console_releases_proc >= 200 ) && ( $array['GAMES'] == "1" )) {
		$color = get_color();
		$log = writelog($panes2[5]);
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.5 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes2[5] $ds2 && cd $_bin && $_php processGames1.php 2>&1 $log && echo \" \033[1;0;33m\" && $ds1 $panes2[5] $ds3' 2>&1 1> /dev/null");
	} elseif ( $array['GAMES'] == "0" ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.5 'echo \"\033[38;5;\"$color\"m\n$panes2[5] Disabled by GAMES\" && date +\"%D %T\"' 2>&1 1> /dev/null");
	} elseif  ( $array['MAX_LOAD'] <= get_load()) {
                $color = get_color();
                shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.5 'echo \"\033[38;5;\"$color\"m\n$panes2[5] Disabled by MAX_LOAD\" && date +\"%D %T\"' 2>&1 1> /dev/null");
        } elseif ( $console_releases_proc == 0 )  {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.5 'echo \"\033[38;5;\"$color\"m\n$panes2[5] Has no work to process \" && date +\"%D %T\"' 2>&1 1> /dev/null");
        }

	//runs processMovies.php in pane 2.2 once if needed then exits
	if (( $array['MAX_LOAD'] >= get_load()) && ( $movie_releases_proc > 0 ) && ( $array['MOVIES'] != "0" )) {
		$color = get_color();
		$log = writelog($panes2[2]);
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.2 'echo \"\033[38;5;\"$color\"\" && $ds1 $panes2[2] $ds2 && cd $_bin && $_php processMovies.php 2>&1 $log && echo \" \033[1;0;33m\" && $ds1 $panes2[2] $ds3' 2>&1 1> /dev/null");
	} elseif ( $array['MOVIES'] == "0" ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.2 'echo \"\033[38;5;\"$color\"m\n$panes2[2] Disabled by MOVIES\" && date +\"%D %T\"' 2>&1 1> /dev/null");
	} elseif  ( $array['MAX_LOAD'] <= get_load()) {
                $color = get_color();
                shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.2 'echo \"\033[38;5;\"$color\"m\n$panes2[2] Disabled by MAX_LOAD\" && date +\"%D %T\"' 2>&1 1> /dev/null");
        } elseif ( $movie_releases_proc == 0 ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.2 'echo \"\033[38;5;\"$color\"m\n$panes2[2] Has no work to process \" && date +\"%D %T\"' 2>&1 1> /dev/null");
        }

	//runs processMovies.php in pane 2.6 once if needed then exits
	if (( $array['MAX_LOAD'] >= get_load()) && ( $movie_releases_proc >= 200 ) && ( $array['MOVIES'] == "1" )) {
		$color = get_color();
		$log = writelog($panes2[6]);
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.6 'echo \"\033[38;5;\"$color\"\" && $ds1 $panes2[6] $ds2 && cd $_bin && $_php processMovies.php 2>&1 $log && echo \" \033[1;0;33m\" && $ds1 $panes2[6] $ds3' 2>&1 1> /dev/null");
	} elseif ( $array['MOVIES'] == "0" ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.6 'echo \"\033[38;5;\"$color\"m\n$panes2[6] Disabled by MOVIES\" && date +\"%D %T\"' 2>&1 1> /dev/null");
	} elseif ( $array['MAX_LOAD'] <= get_load()) {
                $color = get_color();
                shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.6 'echo \"\033[38;5;\"$color\"m\n$panes2[6] Disabled by MAX_LOAD\" && date +\"%D %T\"' 2>&1 1> /dev/null");
        } elseif ( $movie_releases_proc == 0 ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.6 'echo \"\033[38;5;\"$color\"m\n$panes2[6] Has no work to process \" && date +\"%D %T\"' 2>&1 1> /dev/null");
        }

	//runs processMusic.php in pane 2.3 once if needed then exits
	if (( $array['MAX_LOAD'] >= get_load()) && ( $music_releases_proc > 0 ) && ( $array['MUSIC'] != "0" )) {
		$color = get_color();
		$log = writelog($panes2[3]);
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.3 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes2[3] $ds2 && cd $_bin && $_php processMusic.php 2>&1 $log && echo \" \033[1;0;33m\" && $ds1 $panes2[3] $ds3' 2>&1 1> /dev/null");
	} elseif ( $array['MUSIC'] == "0" ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.3 'echo \"\033[38;5;\"$color\"m\n$panes2[3] Disabled by MUSIC\" && date +\"%D %T\"' 2>&1 1> /dev/null");
	} elseif ( $array['MAX_LOAD'] <= get_load()) {
                $color = get_color();
                shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.3 'echo \"\033[38;5;\"$color\"m\n$panes2[3] Disabled by MAX_LOAD\" && date +\"%D %T\"' 2>&1 1> /dev/null");
        } elseif ( $music_releases_proc == 0 ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.3 'echo \"\033[38;5;\"$color\"m\n$panes2[3] Has no work to process \" && date +\"%D %T\"' 2>&1 1> /dev/null");
        }

	//runs processMusic.php in pane 2.7 once if needed then exits
	if (( $array['MAX_LOAD'] >= get_load()) && ( $music_releases_proc >= 200 ) && ( $array['MUSIC'] == "1" )) {
		$color = get_color();
		$log = writelog($panes2[7]);
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.7 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes2[7] $ds2 && cd $_bin && $_php processMusic.php 2>&1 $log && echo \" \033[1;0;33m\" && $ds1 $panes2[7] $ds3' 2>&1 1> /dev/null");
	} elseif ( $array['MUSIC'] == "0" ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.7 'echo \"\033[38;5;\"$color\"m\n$panes2[7] Disabled by MUSIC\" && date +\"%D %T\"' 2>&1 1> /dev/null");
	} elseif ( $array['MAX_LOAD'] <= get_load()) {
                $color = get_color();
                shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.7 'echo \"\033[38;5;\"$color\"m\n$panes2[7] Disabled by MAX_LOAD\"' 2>&1 1> /dev/null");
        } elseif ( $music_releases_proc == 0 ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:2.7 'echo \"\033[38;5;\"$color\"m\n$panes2[7] Has no work to process \" && date +\"%D %T\"' 2>&1 1> /dev/null");
        }

	//run processSpotnab.php in 3.0 every 15 minutes
	if (( $array['MAX_LOAD'] >= get_load()) && (( TIME() - $time20 ) >= $array['SPOTNAB_TIMER'] ) && ( $array['SPOTNAB'] == "true" )) {
		$color = get_color();
		$log = writelog($panes3[0]);
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.0 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes3[0] $ds2 && cd $_bin && $_php processSpotnab.php true 2>&1 $log && echo \" \033[1;0;33m\" && $ds1 $panes3[0] $ds3' 2>&1 1> /dev/null");
		$time20 = TIME();
	} elseif ( $array['SPOTNAB'] != "true" ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.0 'echo \"\033[38;5;\"$color\"m\n$panes3[0] Disabled by SPOTNAB\" && date +\"%D %T\"' 2>&1 1> /dev/null");
	} elseif (( $array['SPOTNAB'] == "true" ) && ( $array['MAX_LOAD'] >= get_load())) {
		$color = get_color();
		$run_time = relativeTime( $array['SPOTNAB_TIMER'] + $time20 );
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.0 'echo \"\033[38;5;\"$color\"m\n$panes3[0] will run in T[ $run_time]\" && date +\"%D %T\"' 2>&1 1> /dev/null");
	} elseif ( $array['MAX_LOAD'] <= get_load()) {
                $color = get_color();
                shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.0 'echo \"\033[38;5;\"$color\"m\n$panes3[0] Disabled by MAX_LOAD\" && date +\"%D %T\"' 2>&1 1> /dev/null");
        }

	//runs processTv.php in pane 3.4 once if needed then exits
	if (( $array['MAX_LOAD'] >= get_load()) && ( $tvrage_releases_proc > 0 ) && ( $array['TVRAGE'] == "true" )) {
		$color = get_color();
		$log = writelog($panes3[4]);
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.4 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes3[4] $ds2 && cd $_bin && $_php processTv.php 2>&1 $log && echo \" \033[1;0;33m\" && $ds1 $panes3[4] $ds3' 2>&1 1> /dev/null");
	} elseif ( $array['TVRAGE'] != "true" )  {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.4 'echo \"\033[38;5;\"$color\"m\n$panes3[4] Disabled by TVRAGE\" && date +\"%D %T\"' 2>&1 1> /dev/null");
	} elseif ( $array['MAX_LOAD'] <= get_load()) {
                $color = get_color();
                shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.4 'echo \"\033[38;5;\"$color\"m\n$panes3[4] Disabled by MAX_LOAD\" && date +\"%D %T\"' 2>&1 1> /dev/null");
        } else  {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.4 'echo \"\033[38;5;\"$color\"m\n$panes3[4] Has no work to process \" && date +\"%D %T\"' 2>&1 1> /dev/null");
        }

	//runs processTv.php in pane 3.1 once if needed then exits
	if (( $array['MAX_LOAD'] >= get_load()) && ( $tvrage_releases_proc > 0 ) && ( $array['TVRAGE'] == "true" )) {
		$color = get_color();
		$log = writelog($panes3[1]);
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.1 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes3[1] $ds2 && cd $_bin && $_php processTv1.php 2>&1 $log && echo \" \033[1;0;33m\" && $ds1 $panes3[1] $ds3' 2>&1 1> /dev/null");
	} elseif ( $array['TVRAGE'] != "true" ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.1 'echo \"\033[38;5;\"$color\"m\n$panes3[1] Disabled by TVRAGE\" && date +\"%D %T\"' 2>&1 1> /dev/null");
	} elseif ( $array['MAX_LOAD'] <= get_load()) {
                $color = get_color();
                shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.1 'echo \"\033[38;5;\"$color\"m\n$panes3[1] Disabled by MAX_LOAD\" && date +\"%D %T\"' 2>&1 1> /dev/null");
        } elseif ( $tvrage_releases_proc == 0 ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.1 'echo \"\033[38;5;\"$color\"m\n$panes3[1] Has no work to process \" && date +\"%D %T\"' 2>&1 1> /dev/null");
        }

	//runs processTv.php in pane 3.5 once if needed then exits
	if (( $array['MAX_LOAD'] >= get_load()) && ( $tvrage_releases_proc > 0 ) && ( $array['TVRAGE'] == "true" )) {
		$color = get_color();
		$log = writelog($panes3[5]);
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.5 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes3[5] $ds2 && cd $_bin && $_php processTv2.php 2>&1 $log && echo \" \033[1;0;33m\" && $ds1 $panes3[5] $ds3' 2>&1 1> /dev/null");
	} elseif ( $array['TVRAGE'] != "true" ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.5 'echo \"\033[38;5;\"$color\"m\n$panes3[5] Disabled by TVRAGE\" && date +\"%D %T\"' 2>&1 1> /dev/null");
	} elseif ( $array['MAX_LOAD'] <= get_load()) {
                $color = get_color();
                shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.5 'echo \"\033[38;5;\"$color\"m\n$panes3[5] Disabled by MAX_LOAD\" && date +\"%D %T\"' 2>&1 1> /dev/null");
        } elseif ( $tvrage_releases_proc == 0 ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.5 'echo \"\033[38;5;\"$color\"m\n$panes3[5] Has no work to process \" && date +\"%D %T\"' 2>&1 1> /dev/null");
        }

	//runs processBooks.php in pane 3.2 once if needed then exits
	if (( $array['MAX_LOAD'] >= get_load()) && ( $book_releases_proc > 0 ) && ( $array['EBOOK'] != "0" )) {
		$color = get_color();
		$log = writelog($panes3[2]);
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.2 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes3[2] $ds2 && cd $_bin && $_php processBooks.php 2>&1 $log && echo \" \033[1;0;33m\" && $ds1 $panes3[2] $ds3' 2>&1 1> /dev/null");
	} elseif ( $array['EBOOK'] == "0" ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.2 'echo \"\033[38;5;\"$color\"m\n$panes3[2] Disabled by EBOOK\" && date +\"%D %T\"' 2>&1 1> /dev/null");
	} elseif ( $array['MAX_LOAD'] <= get_load()) {
                $color = get_color();
                shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.2 'echo \"\033[38;5;\"$color\"m\n$panes3[2] Disabled by MAX_LOAD\" && date +\"%D %T\"' 2>&1 1> /dev/null");
        } elseif ( $book_releases_proc == 0 ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.2 'echo \"\033[38;5;\"$color\"m\n$panes3[2] Has no work to process \" && date +\"%D %T\"' 2>&1 1> /dev/null");
        }

	//runs processBooks.php in pane 3.6 once if needed then exits
	if (( $array['MAX_LOAD'] >= get_load()) && ( $book_releases_proc >=200 ) && ( $array['EBOOK'] == "2" )) {
		$color = get_color();
		$log = writelog($panes3[6]);
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.6 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes3[6] $ds2 && cd $_bin && $_php processBooks1.php 2>&1 $log && echo \" \033[1;0;33m\" && $ds1 $panes3[6] $ds3' 2>&1 1> /dev/null");
	} elseif ( $array['EBOOK'] == "0" ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.6 'echo \"\033[38;5;\"$color\"m\n$panes3[6] Disabled by EBOOK\" && date +\"%D %T\"' 2>&1 1> /dev/null");
	} elseif ( $array['MAX_LOAD'] <= get_load()) {
                $color = get_color();
                shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.6 'echo \"\033[38;5;\"$color\"m\n$panes3[6] Disabled by MAX_LOAD\" && date +\"%D %T\"' 2>&1 1> /dev/null");
        } elseif ( $book_releases_proc == 0 ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.6 'echo \"\033[38;5;\"$color\"m\n$panes3[6] Has no work to process \" && date +\"%D %T\"' 2>&1 1> /dev/null");
        }

	//runs processOthers.php in pane 3.3 once if needed then exits
	if  (( $array['MAX_LOAD'] >= get_load()) && ( $array['OTHERS'] == "true" )) {
		$color = get_color();
		$log = writelog($panes3[3]);
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.3 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes3[3] $ds2 && cd $_bin && $_php processOthers.php 2>&1 $log && echo \" \033[1;0;33m\" && $ds1 $panes3[3] $ds3' 2>&1 1> /dev/null");
	} elseif ( $array['OTHERS'] != "true" ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.3 'echo \"\033[38;5;\"$color\"m\n$panes3[3] Disabled by OTHERS\" && date +\"%D %T\"' 2>&1 1> /dev/null");
	} elseif ( $array['MAX_LOAD'] <= get_load()) {
                $color = get_color();
                shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.3 'echo \"\033[38;5;\"$color\"m\n$panes3[3] Disabled by MAX_LOAD\" && date +\"%D %T\"' 2>&1 1> /dev/null");
        } else  {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.3 'echo \"\033[38;5;\"$color\"m\n$panes3[3] Has no work to process \" && date +\"%D %T\"' 2>&1 1> /dev/null");
        }

	//runs processUnwanted.php in pane 3.7 in continuous loop, will restart if exits
	if  (( $array['MAX_LOAD'] >= get_load()) && ( $array['UNWANTED'] == "true" )) {
		$color = get_color();
		$log = writelog($panes3[7]);
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.7 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes3[7] $ds2 && cd $_bin && $_php processUnwanted.php 2>&1 $log && echo \" \033[1;0;33;33m\" && $ds1 $panes3[7] $ds3' 2>&1 1> /dev/null");
	} elseif ( $array['UNWANTED'] != "true" ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.7 'echo \"\033[38;5;\"$color\"m\n$panes3[7] Disabled by UNWANTED\" && date +\"%D %T\"' 2>&1 1> /dev/null");
	} elseif ( $array['MAX_LOAD'] <= get_load()) {
                $color = get_color();
                shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.7 'echo \"\033[38;5;\"$color\"m\n$panes3[7] Disabled by MAX_LOAD\" && date +\"%D %T\"' 2>&1 1> /dev/null");
        } else  {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:3.7 'echo \"\033[38;5;\"$color\"m\n$panes3[7] Has no work to process \" && date +\"%D %T\"' 2>&1 1> /dev/null");
        }

    //run fixReleaseNames and jonnyboys jb_fix_names.php in pane 4.0
	if (( $array['MAX_LOAD'] >= get_load()) && ( $array['FIXRELEASES'] == "true" )) {
		$color = get_color();
		$log = writelog($panes4[0]);
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:4.0 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes4[0] $ds2 && cd $_test && $_php fixReleaseNames.php 3 true other yes show 2>&1 $log && $_php fixReleaseNames.php 5 true other yes show 2>&1 $log && $_php fixReleaseNames.php 7 true other yes show 2>&1 $log && $_php hash_decrypt.php 1000 2>&1 $log && echo \" \033[1;0;33m\" && $_sleep {$array ['FIXRELEASES_TIMER']} && $ds1 $panes4[0] $ds3' 2>&1 1> /dev/null");
		$time27 = TIME();}
	  elseif ( $array['FIXRELEASES'] != "true" ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:4.0 'echo \"\033[38;5;\"$color\"m\n$panes4[0] Disabled by fixReleaseNames\" && date +\"%D %T\"' 2>&1 1> /dev/null");
	} elseif ( $array['MAX_LOAD'] <= get_load()) {
                $color = get_color();
                shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:4.0 'echo \"\033[38;5;\"$color\"m\n$panes4[0] Disabled by MAX_LOAD\" && date +\"%D %T\"' 2>&1 1> /dev/null");
        }
    //run predb_hash_decrypt.php in pane 4.1
	if (( $array['MAX_LOAD'] >= get_load()) &&( $array['PREDBHASH'] == "true" )) {
		$color = get_color();
		$log = writelog($panes4[1]);
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:4.1 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes4[1] $ds2 && cd $_test && $_php predb_hash_decrypt.php 1000 2>&1 $log && echo \" \033[1;0;33m\" && $_sleep {$array ['PREDBHASH_TIMER']} && $ds1 $panes4[1] $ds3' 2>&1 1> /dev/null");
		$time28 = TIME();
	} elseif ( $array['PREDBHASH'] != "true" ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:4.1 'echo \"\033[38;5;\"$color\"m\n$panes4[1] Disabled by PREDBHASH\" && date +\"%D %T\"' 2>&1 1> /dev/null");
	} elseif ( $array['MAX_LOAD'] <= get_load()) {
                $color = get_color();
                shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:4.1 'echo \"\033[38;5;\"$color\"m\n$panes4[1] Disabled by MAX_LOAD\" && date +\"%D %T\"' 2>&1 1> /dev/null");
        }
        //run requestID in pane 4.2
	if (( $array['MAX_LOAD'] >= get_load()) && ( $array['REQID'] == "true" )) {
		$color = get_color();
		$log = writelog($panes4[2]);
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:4.2 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes4[2] $ds2 && cd $_test && $_php requestID.php 1000 true 2>&1 $log && echo \" \033[1;0;33m\" && $_sleep {$array['REQID_TIMER']} && $ds1 $panes4[2] $ds3' 2>&1 1> /dev/null");
		$time30 = TIME();}
	  elseif ( $array['REQID'] != "true" ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:4.2 'echo \"\033[38;5;\"$color\"m\n$panes4[2] Disabled by RequestID\" && date +\"%D %T\"' 2>&1 1> /dev/null");
	} elseif ( $array['MAX_LOAD'] <= get_load()) {
                $color = get_color();
                shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:4.2 'echo \"\033[38;5;\"$color\"m\n$panes4[2] Disabled by MAX_LOAD\" && date +\"%D %T\"' 2>&1 1> /dev/null");
        }
        //run removeCrap.php in pane 4.3
	if (( $array['MAX_LOAD'] >= get_load()) && ( $array['REMOVECRAP'] == "true" )) {
		$color = get_color();
		$log = writelog($panes4[3]);
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:4.3 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes4[3] $ds2 && cd $_test && $_php removeCrapReleases.php true 2 2>&1 $log echo \" \033[1;0;33m\" && $_sleep {$array['REMOVECRAP_TIMER']} && $ds1 $panes4[3] $ds3' 2>&1 1> /dev/null");
		$time31 = TIME();}
	  elseif ( $array['REMOVECRAP'] != "true" ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:4.3 'echo \"\033[38;5;\"$color\"m\n$panes4[3] Disabled by RemoveCrap\" && date +\"%D %T\"' 2>&1 1> /dev/null");
	} elseif ( $array['MAX_LOAD'] <= get_load()) {
                $color = get_color();
                shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:4.3 'echo \"\033[38;5;\"$color\"m\n$panes4[3] Disabled by MAX_LOAD\" && date +\"%D %T\"' 2>&1 1> /dev/null");
        }

    //run postprocess_pre.php in pane 4.4
	if (( $array['MAX_LOAD'] >= get_load()) && ( $array['UPPRE'] == "true" )) {
		$color = get_color();
		$log = writelog($panes4[4]);
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:4.4 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes4[4] $ds2 && cd $_test && $_php postprocess_pre.php 2>&1 $log && echo \" \033[1;0;33m\" && $_sleep {$array['UPPRE_TIMER']} && $ds1 $panes4[4] $ds3' 2>&1 1> /dev/null");
		$time32 = TIME();}
	  elseif ( $array['UPPRE'] != "true" ) {
		$color = get_color();
		shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:4.4 'echo \"\033[38;5;\"$color\"m\n$panes4[4] Disabled by Update_Prehash\" && date +\"%D %T\"' 2>&1 1> /dev/null");
	} elseif ( $array['MAX_LOAD'] <= get_load()) {
                $color = get_color();
                shell_exec("$_tmux respawnp -t {$array['TMUX_SESSION']}:4.4 'echo \"\033[38;5;\"$color\"m\n$panes4[4] Disabled by MAX_LOAD\" && date +\"%D %T\"' 2>&1 1> /dev/null");
        }

	$i++;
	//turn off monitor if set to false
	if ( $array['RUNNING'] != "true" ) {
		$i=0;
	}
	sleep(5);

	while (( ! shell_exec("$_tmux list-panes -t {$array['TMUX_SESSION']}:1 | grep 4: | grep dead" )))
	{
		sleep(1);
	}
}
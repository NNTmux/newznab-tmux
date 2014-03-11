<?php

require(dirname(__FILE__)."/config.php");
require(WWW_DIR.'/lib/postprocess.php');
require_once (WWW_DIR.'/lib/site.php');
require_once(WWW_DIR.'/lib/Tmux.php');
require_once("../test/ColorCLI.php");
require_once("../test/showsleep.php");
require_once("../test/functions.php");


$version="0.3r889";

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

$t = new Tmux();
$tmux = $t->get();
$seq = (isset($tmux->sequential)) ? $tmux->sequential : 0;
$powerline = (isset($tmux->powerline)) ? $tmux->powerline : 0;
$colors = (isset($tmux->colors)) ? $tmux->colors : 0;


if (command_exist("python3")) {
	$PYTHON = "python3 -OOu";
} else {
	$PYTHON = "python -OOu";
}

if (command_exist("php5")) {
	$PHP = "php5";
} else {
	$PHP = "php";
}
//totals per category in db, results by parentID
$qry = "SELECT c.parentID AS parentID, COUNT(r.ID) AS count FROM category c, releases r WHERE r.categoryID = c.ID GROUP BY c.parentID";

//needs to be processed query
$proc = "SELECT
( SELECT COUNT(*) FROM releases USE INDEX(ix_releases_status) WHERE categoryID BETWEEN 1000 AND 1999 AND consoleinfoID IS NULL ) AS console,
( SELECT COUNT(*) FROM releases USE INDEX(ix_releases_status) WHERE categoryID BETWEEN 2000 AND 2999 AND imdbID IS NULL ) AS movies,
( SELECT COUNT(*) FROM releases USE INDEX(ix_releases_status) WHERE categoryID BETWEEN 3000 AND 3999 AND musicinfoID IS NULL) AS audio,
( SELECT COUNT(*) FROM releases r USE INDEX(ix_releases_status), category c WHERE c.ID = r.categoryID AND c.parentID = 6000 AND r.passwordstatus = -1 AND r.haspreview = -1 AND c.disablepreview = 0) AS xxx,
( SELECT COUNT(*) FROM releases r USE INDEX(ix_releases_status) INNER JOIN category c on c.ID = r.categoryID WHERE (r.passwordstatus = -1) OR (r.haspreview = -1 and c.disablepreview = 0)) AS pc,
( SELECT COUNT(*) FROM releases USE INDEX(ix_releases_status) WHERE categoryID BETWEEN 5000 AND 5999 AND rageID = -1) AS tv,
( SELECT COUNT(*) FROM releases USE INDEX(ix_releases_status) WHERE categoryID = 7020 AND bookinfoID IS NULL ) AS book,
( SELECT COUNT(*) FROM releases r USE INDEX(ix_releases_status), category c WHERE c.ID = r.categoryID AND r.passwordstatus = -1 AND r.haspreview = -1 AND c.disablepreview = 0) AS work,
( SELECT COUNT(*) FROM releases USE INDEX(ix_releases_status)) AS releases,
( SELECT COUNT(*) FROM releases USE INDEX(ix_releases_status) WHERE releasenfoID = 0 AND nfostatus BETWEEN -6 AND -1) AS nforemains,
( SELECT COUNT(*) FROM releases USE INDEX(ix_releases_status) WHERE releasenfoID not in (0, -1) AND nfostatus = 1) AS nfo,
( SELECT table_rows AS cnt FROM information_schema.TABLES WHERE table_name = 'parts' AND TABLE_SCHEMA = '".DB_NAME."' ) AS parts,
( SELECT COUNT(ID) FROM binaries WHERE procstat = 0 ) AS binaries,
( SELECT table_rows AS cnt FROM information_schema.TABLES WHERE table_name = 'binaries' AND TABLE_SCHEMA = '".DB_NAME."' ) AS binaries_total,
( SELECT concat(round((data_length+index_length)/(1024*1024*1024),2),'GB') AS cnt FROM information_schema.tables WHERE table_name = 'parts' AND TABLE_SCHEMA = '".DB_NAME."' ) AS partsize,
( SELECT concat(round((data_length+index_length)/(1024*1024*1024),2),'GB') AS cnt FROM information_schema.tables WHERE table_name = 'binaries' AND TABLE_SCHEMA = '".DB_NAME."' ) AS binariessize,
( SELECT COUNT( ID ) FROM groups WHERE active = 1 ) AS active_groups,
( SELECT COUNT( ID ) FROM groups WHERE name IS NOT NULL ) AS all_groups,
( SELECT COUNT( ID ) FROM groups WHERE first_record IS NOT NULL and `backfill_target` > 0 and first_record_postdate != '2000-00-00 00:00:00'  < first_record_postdate) AS backfill_groups,
( SELECT UNIX_TIMESTAMP(adddate) from prehash order by adddate DESC limit 1 ) AS newestprehash,
( SELECT UNIX_TIMESTAMP(updatedate) from predb order by updatedate DESC limit 1 ) AS newestpredb,
( SELECT TABLE_ROWS FROM INFORMATION_SCHEMA.TABLES WHERE table_name = 'prehash' AND TABLE_SCHEMA = '".DB_NAME."' ) AS prehash,
( SELECT name from releases order by adddate desc limit 1 ) AS newestaddname";
//$proc = "SELECT * FROM procCnt;";
$proc2 = "SELECT
	(SELECT COUNT(*) FROM releases WHERE isrenamed = 0 AND isrequestid = 1 AND reqidstatus in (0, -1) OR (reqidstatus = -3 AND adddate > NOW() - INTERVAL 2 HOUR)) AS requestid_inprogress,
	(SELECT COUNT(*) FROM releases USE INDEX(ix_releases_status) WHERE reqidstatus = 1 OR reqID IS NOT NULL) AS requestid_matched,
	(SELECT COUNT(*) FROM releases USE INDEX(ix_releases_status) WHERE preID IS NOT NULL) AS prehash_matched,
	(SELECT COUNT(DISTINCT(preID)) FROM releases) AS distinct_prehash_matched";

$proc_tmux = "SELECT "
    . "(SELECT VALUE FROM tmux WHERE SETTING = 'monitor_delay') AS monitor, "
    . "(SELECT VALUE FROM tmux WHERE SETTING = 'tmux_session') AS tmux_session, "
	. "(SELECT VALUE FROM tmux WHERE SETTING = 'niceness') AS niceness, "
	. "(SELECT VALUE FROM tmux WHERE SETTING = 'binaries') AS binaries_run, "
    . "(SELECT VALUE FROM tmux WHERE SETTING = 'backfill') AS backfill, "
    . "(SELECT VALUE FROM tmux WHERE SETTING = 'max_load') AS max_load, "
    . "(SELECT VALUE FROM tmux WHERE SETTING = 'max_load_releases') AS max_load_releases, "
    . "(SELECT VALUE FROM tmux WHERE SETTING = 'seq_timer') as seq_timer, "
    . "(SELECT VALUE FROM tmux WHERE SETTING = 'import') AS import, "
    . "(SELECT VALUE FROM tmux WHERE SETTING = 'releases') AS releases_run, "
	. "(SELECT VALUE FROM tmux WHERE SETTING = 'releases_threaded') AS releases_threaded, "
    . "(SELECT VALUE FROM tmux WHERE SETTING = 'post') AS post, "
    . "(SELECT VALUE FROM tmux WHERE SETTING = 'post_timer') as post_timer, "
	. "(SELECT VALUE FROM tmux WHERE SETTING = 'fix_names') as fix_names, "
    . "(SELECT VALUE FROM tmux WHERE SETTING = 'fix_timer') as fix_timer, "
    . "(SELECT VALUE FROM tmux WHERE SETTING = 'bins_timer') as bins_timer, "
    . "(SELECT VALUE FROM tmux WHERE SETTING = 'rel_timer') as rel_timer, "
    . "(SELECT VALUE FROM tmux WHERE SETTING = 'back_timer') as back_timer, "
	. "(SELECT VALUE FROM tmux WHERE SETTING = 'import_timer') as import_timer, "
    . "(SELECT VALUE FROM tmux WHERE SETTING = 'postprocess_kill') as postprocess_kill, "
    . "(SELECT VALUE FROM tmux WHERE SETTING = 'crap_timer') as crap_timer, "
	. "(SELECT VALUE FROM tmux WHERE SETTING = 'fix_crap') as fix_crap, "
	. "(SELECT VALUE FROM tmux WHERE SETTING = 'fix_crap_opt') as fix_crap_opt, "
	. "(SELECT VALUE FROM tmux WHERE SETTING = 'tv_timer') as tv_timer, "
	. "(SELECT VALUE FROM tmux WHERE SETTING = 'update_tv') as update_tv, "
    . "(SELECT VALUE FROM tmux WHERE SETTING = 'post_kill_timer') as post_kill_timer, "
    . "(SELECT VALUE FROM tmux WHERE SETTING = 'monitor_path') as monitor_path, "
	. "(SELECT VALUE FROM tmux WHERE SETTING = 'monitor_path_a') as monitor_path_a, "
	. "(SELECT VALUE FROM tmux WHERE SETTING = 'monitor_path_b') as monitor_path_b, "
    . "(SELECT VALUE FROM site WHERE SETTING = 'debuginfo') as debug, "
    . "(SELECT VALUE FROM tmux WHERE SETTING = 'dehash') as dehash, "
	. "(SELECT VALUE FROM tmux WHERE SETTING = 'dehash_timer') as dehash_timer, "
	. "(SELECT VALUE FROM tmux WHERE SETTING = 'backfill_days') as backfilldays, "
    . "(SELECT VALUE FROM tmux WHERE SETTING = 'post_amazon') as post_amazon, "
	. "(SELECT VALUE FROM tmux WHERE SETTING = 'post_timer_amazon') as post_timer_amazon, "
	. "(SELECT VALUE FROM tmux WHERE SETTING = 'post_non') as post_non, "
	. "(SELECT VALUE FROM tmux WHERE SETTING = 'post_timer_non') as post_timer_non, "
    . "(SELECT VALUE FROM tmux WHERE SETTING = 'colors_start') AS colors_start, "
	. "(SELECT VALUE FROM tmux WHERE SETTING = 'colors_end') AS colors_end, "
	. "(SELECT VALUE FROM tmux WHERE SETTING = 'colors_exc') AS colors_exc, "
    . "(SELECT VALUE FROM tmux WHERE SETTING = 'running') AS is_running, "
    . "(SELECT VALUE FROM site WHERE SETTING = 'lookupbooks') as processbooks, "
	. "(SELECT VALUE FROM site WHERE SETTING = 'lookupmusic') as processmusic, "
	. "(SELECT VALUE FROM site WHERE SETTING = 'lookupgames') as processgames, "
	. "(SELECT VALUE FROM site WHERE SETTING = 'tmpunrarpath') as tmpunrar";


$split_query = "SELECT
    ( SELECT UNIX_TIMESTAMP(adddate) FROM releases USE INDEX(ix_releases_status) ORDER BY adddate DESC LIMIT 1 ) AS newestadd";

//get first release inserted datetime and oldest posted datetime
//$posted_date = "SELECT(SELECT UNIX_TIMESTAMP(adddate) from releases order by adddate asc limit 1) AS adddate;";

//flush query cache
//$qcache = "FLUSH QUERY CACHE";

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
$_test = dirname (__FILE__)."/../test";
$_user = dirname(__FILE__)."/../user_scripts";
$_temp = dirname(__FILE__)."/../bin/temp";
$_sleep = "$_php ${DIR}/../test/showsleep.php";
$show_time = "";
$_python = $show_time . " nice -n${array['NICENESS']} $PYTHON";
$_pythonn = "nice -n${array['NICENESS']} $PYTHON";


$NNPATH="{$array['NEWZPATH']}{$array['NEWZNAB_PATH']}";
$TESTING="{$array['NEWZPATH']}{$array['TESTING_PATH']}";
$killed="false";
$getdate = gmDate("Ymd");

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

function command_exist($cmd)
{
	$returnVal = shell_exec("which $cmd 2>/dev/null");
	return (empty($returnVal) ? false : true);
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
$last_history = "";

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

//build queries for shell
$_backfill_increment = "UPDATE groups set backfill_target=backfill_target+1 WHERE active=1 and backfill_target<$backfilldays;";
$mysql_command_1 = "$_mysql --defaults-file=$_conf/my.cnf -u$_DB_USER -h $_DB_HOST $_DB_NAME -e \"$_backfill_increment\"";
$reset_bin = "UPDATE binaries SET procstat=0, procattempts=0, regexID=NULL, relpart=0, reltotalpart=0, relname=NULL;";
$mysql_command_2 = "$_mysql --defaults-file=$_conf/my.cnf -u$_DB_USER -h $_DB_HOST $_DB_NAME -e \"$reset_bin\"";
$mysqladmin = "$_mysqladmin --defaults-file=$_conf/my.cnf -u$_DB_USER -h $_DB_HOST status | awk '{print $22;}'";

$mask1 = $c->headerOver("%-18s")." ".$c->tmuxOrange("%-48.48s");
$mask2 = $c->headerOver("%-20s")." ".$c->tmuxOrange("%-33.33s");
$mask3 = $c->header("%-16.16s %25.25s %25.25s");
$mask4 = $c->primaryOver("%-16.16s") . " " . $c->tmuxOrange("%25.25s %25.25s");
$mask5 = $c->tmuxOrange("%-16.16s %25.25s %25.25s");

// Ananlyze tables
printf($c->info("\nAnalyzing your tables to refresh your indexes."));
$functions->optimise(true, 'analyze');

sleep (5);



//create initial display, USP connection count, prehash count and groups count adapted from nZEDb
passthru('clear');
//printf("\033[1;31m  First insert:\033[0m ".relativeTime("$firstdate")."\n");
printf($mask2, "Monitor Running v$version [" . $patch . "]: ", relativeTime("$time"));
printf($mask1, "USP Connections:", $uspactiveconnections . " active (" . $usptotalconnections . " total) - " . $host . ":" . $port);;
printf($mask1, "Newest Release:", "$newestname");
printf($mask1, "Release Added:", relativeTime("$newestadd")."ago");
if ($array['PREDB'] = "true"){
    printf($mask1, "Predb Updated:", relativeTime("$newestpredb")."ago");
}
if  ($fix_names == 1) {
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
if ($fix_names == 1){
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
$monitor = 30;
$time33 = TIME();
$fcfirstrun = true;

while( $i > 0 )
{
     //kill mediainfo and ffmpeg if exceeds 60 sec
	shell_exec("killall -o 60s -9 mediainfo 2>&1 1> /dev/null");
	shell_exec("killall -o 60s -9 ffmpeg 2>&1 1> /dev/null");

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


	//defrag the query cache every 15 minutes
	/*if (( TIME() - $time18 >= 900 ) || ( $i == 1 ))
	{
		$result = @$db->query($qcache);
	} */

	//reload tmux.conf
	if ( $array['POWERLINE'] == "true" ) {
		shell_exec("$_tmux source-file $_powerline/tmux.conf");
	} else {
		shell_exec("$_tmux source-file $_conf/tmux.conf");
	}

	//reset title, might rename in some terminals
	printf("\033]0;$tmux_session\007\003\n");

	//kill panes if user changed to/from nzb import threaded
	if ( $_imports != $array['NZB_THREADS'] ) {
		shell_exec("$_tmux respawnp -k -t${tmux_session}:0.4 'sleep 5' && $ds1 $panes0[4] $ds4");
		shell_exec("$_tmux respawnp -k -t${tmux_session}:1.5 'sleep 5' && $ds1 $panes1[5] $ds4");
		$_imports = $array['NZB_THREADS'];
	}

	//run queries
    $time01 = TIME();
    $proc_tmux_result = $db->query($proc_tmux, false);
	if ((( TIME() - $time19 ) >= $monitor  && $running == 1) || ( $i == 1 )) {
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
    if ($proc_tmux_result[0]['tmpunrar'] != NULL) {
		$tmpunrar = $proc_tmux_result[0]['tmpunrar'];
    }
    if ($proc_tmux_result[0]['postprocess_kill'] != NULL) {
		$postprocess_kill = $proc_tmux_result[0]['postprocess_kill'];
	}
	if ($proc_tmux_result[0]['backfilldays'] != NULL) {
		$backfilldays = $proc_tmux_result[0]['backfilldays'];
	}
    if ($proc_tmux_result[0]['colors_start'] != NULL) {
		$colors_start = $proc_tmux_result[0]['colors_start'];
	}
	if ($proc_tmux_result[0]['colors_end'] != NULL) {
		$colors_end = $proc_tmux_result[0]['colors_end'];
	}
	if ($proc_tmux_result[0]['colors_exc'] != NULL) {
		$colors_exc = $proc_tmux_result[0]['colors_exc'];
	}
    if ($proc_tmux_result[0]['tmux_session'] != NULL) {
		$tmux_session = $proc_tmux_result[0]['tmux_session'];
	}
	if ($proc_tmux_result[0]['monitor'] != NULL) {
		$monitor = $proc_tmux_result[0]['monitor'];
	}
    if ($proc_tmux_result[0]['backfill'] != NULL) {
		$backfill = $proc_tmux_result[0]['backfill'];
	}
    if ($proc_tmux_result[0]['niceness'] != NULL) {
		$niceness = $proc_tmux_result[0]['niceness'];
	}
    if ($proc_tmux_result[0]['binaries_run'] != NULL) {
		$binaries = $proc_tmux_result[0]['binaries_run'];
	}
    if ($proc_tmux_result[0]['releases_run'] != NULL) {
		$releases_run = $proc_tmux_result[0]['releases_run'];
	}
    if ($proc_tmux_result[0]['releases_threaded'] != NULL) {
		$releases_threaded = $proc_tmux_result[0]['releases_threaded'];
	}
    if ($proc_tmux_result[0]['is_running'] != NULL) {
		$running = (int) $proc_tmux_result[0]['is_running'];
	}
    if ($proc_tmux_result[0]['bins_timer'] != NULL) {
		$bins_timer = $proc_tmux_result[0]['bins_timer'];
	}
    if ($proc_tmux_result[0]['rel_timer'] != NULL) {
		$rel_timer = $proc_tmux_result[0]['rel_timer'];
	}
    if ($proc_tmux_result[0]['import'] != NULL) {
		$import = $proc_tmux_result[0]['import'];
	}
    if ($proc_tmux_result[0]['fix_names'] != NULL) {
		$fix_names = $proc_tmux_result[0]['fix_names'];
	}
	if ($proc_tmux_result[0]['fix_crap'] != NULL) {
		$fix_crap = explode(', ', ($proc_tmux_result[0]['fix_crap']));
	}
	if ($proc_tmux_result[0]['fix_crap_opt'] != NULL) {
		$fix_crap_opt = $proc_tmux_result[0]['fix_crap_opt'];
	}
	if ($proc_tmux_result[0]['update_tv'] != NULL) {
		$update_tv = $proc_tmux_result[0]['update_tv'];
	}
	if ($proc_tmux_result[0]['post'] != NULL) {
		$post = $proc_tmux_result[0]['post'];
    }
    if ($proc_tmux_result[0]['dehash'] != NULL) {
		$dehash = $proc_tmux_result[0]['dehash'];
	}
    if ($proc_tmux_result[0]['max_load'] != NULL) {
		$maxload = $proc_tmux_result[0]['max_load'];
	}
    if ($proc_tmux_result[0]['max_load_releases'] != NULL) {
		$maxloadr = $proc_tmux_result[0]['max_load_releases'];
	}

    //reset monitor paths before query
	$monitor_path = "";
	$monitor_path_a = "";
	$monitor_path_b = "";

	if ($proc_tmux_result[0]['monitor_path'] != NULL) {
		$monitor_path = $proc_tmux_result[0]['monitor_path'];
	}
	if ($proc_tmux_result[0]['monitor_path_a'] != NULL) {
		$monitor_path_a = $proc_tmux_result[0]['monitor_path_a'];
	}
	if ($proc_tmux_result[0]['monitor_path_b'] != NULL) {
		$monitor_path_b = $proc_tmux_result[0]['monitor_path_b'];
	}

	if ($proc_tmux_result[0]['debug'] != NULL) {
		$debug = $proc_tmux_result[0]['debug'];
    }
    if ($proc_tmux_result[0]['post_amazon'] != NULL) {
		$post_amazon = $proc_tmux_result[0]['post_amazon'];
	}
	if ($proc_tmux_result[0]['post_timer_amazon'] != NULL) {
		$post_timer_amazon = $proc_tmux_result[0]['post_timer_amazon'];
	}
	if ($proc_tmux_result[0]['post_non'] != NULL) {
		$post_non = $proc_tmux_result[0]['post_non'];
	}
	if ($proc_tmux_result[0]['post_timer_non'] != NULL) {
		$post_timer_non = $proc_tmux_result[0]['post_timer_non'];
	}
    if ($proc_tmux_result[0]['back_timer'] != NULL) {
		$back_timer = $proc_tmux_result[0]['back_timer'];
	}
	if ($proc_tmux_result[0]['import_timer'] != NULL) {
		$import_timer = $proc_tmux_result[0]['import_timer'];
	}
    if ($proc_tmux_result[0]['fix_timer'] != NULL) {
		$fix_timer = $proc_tmux_result[0]['fix_timer'];
	}
	if ($proc_tmux_result[0]['crap_timer'] != NULL) {
		$crap_timer = $proc_tmux_result[0]['crap_timer'];
	}
	if ($proc_tmux_result[0]['post_timer'] != NULL) {
		$post_timer = $proc_tmux_result[0]['post_timer'];
	}
	if ($proc_tmux_result[0]['post_kill_timer'] != NULL) {
		$post_kill_timer = $proc_tmux_result[0]['post_kill_timer'];
	}
	if ($proc_tmux_result[0]['tv_timer'] != NULL) {
		$tv_timer = $proc_tmux_result[0]['tv_timer'];
	}
	if ($proc_tmux_result[0]['dehash_timer'] != NULL) {
		$dehash_timer = $proc_tmux_result[0]['dehash_timer'];
	}

    $old_session = "$tmux_session";

    //rename the session
	if ( $old_session != $tmux_session ) {
		shell_exec("$_tmux rename-session -t $old_session $tmux_session");
	}
	$old_session="$tmux_session";


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

	$panes_win_1 = shell_exec("echo `tmux list-panes -t  $tmux_session:0 -F '#{pane_title}'`");
	$panes_win_2 = shell_exec("echo `tmux list-panes -t  $tmux_session:1 -F '#{pane_title}'`");
	$panes_win_3 = shell_exec("echo `tmux list-panes -t  $tmux_session:2 -F '#{pane_title}'`");
	$panes_win_4 = shell_exec("echo `tmux list-panes -t  $tmux_session:3 -F '#{pane_title}'`");
	$panes_win_5 = shell_exec("echo `tmux list-panes -t  $tmux_session:4 -F '#{pane_title}'`");

	$panes0 = str_replace("\n", '', explode(" ", $panes_win_1));
	$panes1 = str_replace("\n", '', explode(" ", $panes_win_2));
	$panes2 = str_replace("\n", '', explode(" ", $panes_win_3));
	$panes3 = str_replace("\n", '', explode(" ", $panes_win_4));
	$panes4 = str_replace("\n", '', explode(" ", $panes_win_5));

	//kill update_binaries.php backfill.php and import-nzb if timer exceeded
	$killit=explode(" ", relativeTime("$newestadd"));
	$killed="false";
	if ( $array['KILL_UPDATES'] != "0" ) {
		if ((( $killit[1] != "secs" ) && ( $killit[1] != "sec" )) && (( $killit[1] == "hrs" ) || ( $killit[1] == "hr" ) || ( $killit[0] >= $array['KILL_UPDATES'] )) && ( $i % 5 == 0 )) {
			$color = get_color($colors_start, $colors_end, $colors_exc);
			shell_exec("$_tmux respawnp -k -t${tmux_session}:0.2 'echo \"\033[38;5;\"$color\"m\n$panes0[2] Killed by KILL_UPDATES\" && $ds1 $panes0[2] $ds4'");
			$color = get_color($colors_start, $colors_end, $colors_exc);
			shell_exec("$_tmux respawnp -k -t${tmux_session}:0.3 'echo \"\033[38;5;\"$color\"m\n$panes0[3] Killed by KILL_UPDATES\" && $ds1 $panes0[3] $ds4'");
			$color = get_color($colors_start, $colors_end, $colors_exc);
			shell_exec("$_tmux respawnp -k -t${tmux_session}:0.4 'echo \"\033[38;5;\"$color\"m\n$panes0[4] Killed by KILL_UPDATES\" && $ds1 $panes0[4] $ds4'");
			if ( $array['KEEP_KILLED'] == "true" ) {
				$killed="true";
			}
		}
	}

	//get state for binaries
	if ( $binaries == 0 ) {
		$binaries_state="disabled";
		$binaries_reason="disabled";
        } elseif ( $maxload <= get_load()) {
                $binaries_state="load-exceeded";
                $binaries_reason="$maxload/".get_load();
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
        if ( $releases_run == 0 ) {
                $releases_state="disabled";
                $releases_reason="disabled";
        } elseif ( $maxloadr <= get_load()) {
                $releases_state="load-exceeded";
                $releases_reason="$maxloadr/".get_load();
        } else {
                $releases_state="enabled";
                $releases_reason="enabled";
        }

	//get state for backfill
	if ( $backfill == 0 ) {
		$backfill_state="disabled";
		$backfill_reason="disabled";
        } elseif ( $maxload <= get_load()) {
                $backfill_state="load-exceeded";
                $backfill_reason="$maxload/".get_load();
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
	if ( $import == 0 ) {
		$import_state="disabled";
		$import_reason="disabled";
        } elseif ( $maxload <= get_load()) {
                $import_state="load-exceeded";
                $import_reason="$maxload/".get_load();
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
	printf($mask2, "Monitor Running v$version [" . $patch . "]: ", relativeTime("$time"));
    printf($mask1, "USP Connections:", $uspactiveconnections . " active (" . $usptotalconnections . " total) - " . $host . ":" . $port);
	printf($mask1, "Newest Release:", "$newestname");
	printf($mask1, "Release Added:", relativeTime("$newestadd")."ago");
    if ($array['PREDB'] = "true"){
    printf($mask1, "Predb Updated:", relativeTime("$newestpredb")."ago");
    }
    if ($fix_names == 1) {
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
    if ($fix_names == 1){
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
	if  ($binaries == 2)  {
		$log = writelog($panes0[2]);
		$_update_cmd = "$_python ${DIR}/../test/binaries_threaded.py 2>&1 $log";
	} else if ($binaries == 1) {
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

if ($post_non == 2) {
		$clean = ' clean ';
	} else {
		$clean = ' ';
	}

if ($running == 1){
	//check if sequential is set
	if ( $array['SEQUENTIAL'] != "true" ) {
		//runs update_binaries in 0.2 once if needed and exits
		if (( $maxload >= get_load()) && ( $killed != "true" ) && ( $binaries != 0  ) && (( $binaries_rows_unformatted < $array['BINARIES_MAX_BINS'] ) || ( $array['BINARIES_MAX_BINS'] == 0 )) && (( $total_work_used < $array['BINARIES_MAX_RELEASES'] ) || ( $array['BINARIES_MAX_RELEASES'] == 0 )) && (( $parts_rows_unformatted < $array['BINARIES_MAX_ROWS'] ) || ( $array['BINARIES_MAX_ROWS'] == 0 ))) {
			$color = get_color($colors_start, $colors_end, $colors_exc);
			$log = writelog($panes0[2]);
			shell_exec("$_tmux respawnp -t${tmux_session}:0.2 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes0[2] $ds2 && $_update_cmd 2>&1 $log && echo \" \033[1;0;33m\" &&  $_sleep {$array['BINARIES_SLEEP']} && $ds1 $panes0[2] $ds3' 2>&1 1> /dev/null");
		} elseif (( $parts_rows_unformatted > $array['BINARIES_MAX_ROWS'] ) && ( $binaries != 0  ) && ( $array['BINARIES_MAX_ROWS'] != 0 ))  {
			$color = get_color($colors_start, $colors_end, $colors_exc);
			shell_exec("$_tmux respawnp -t${tmux_session}:0.2 'echo \"\033[38;5;\"$color\"m\nBINARIES_MAX_ROWS exceeded\" && date +\"%D %T\"' 2>&1 1> /dev/null");
		} elseif (( $total_work_used > $array['BINARIES_MAX_RELEASES'] ) && ( $binaries != 0  ) && ( $array['BINARIES_MAX_RELEASES'] != 0 ))  {
			$color = get_color($colors_start, $colors_end, $colors_exc);
			shell_exec("$_tmux respawnp -t${tmux_session}:0.2 'echo \"\033[38;5;\"$color\"m\nBINARIES_MAX_RELEASES exceeded\" && date +\"%D %T\"' 2>&1 1> /dev/null");
		} elseif (( $binaries != 0  ) && ( $maxload <= get_load())) {
                        $color = get_color($colors_start, $colors_end, $colors_exc);
                        shell_exec("$_tmux respawnp -t${tmux_session}:0.2 'echo \"\033[38;5;\"$color\"m\n$panes0[2] Disabled by MAX_LOAD\" && date +\"%D %T\"' 2>&1 1> /dev/null");
                } elseif ( $binaries != 0  )  {
			$color = get_color($colors_start, $colors_end, $colors_exc);
			shell_exec("$_tmux respawnp -t${tmux_session}:0.2 'echo \"\033[38;5;\"$color\"m\n$panes0[2] Disabled by BINARIES\" && date +\"%D %T\"' 2>&1 1> /dev/null");
		}

		//runs backfill in 0.3 once if needed and exits
		if (( $maxload >= get_load()) && ( $killed != "true" ) && ( $backfill == 1 ) && (( $binaries_rows_unformatted < $array['BACKFILL_MAX_BINS'] ) || ( $array['BACKFILL_MAX_BINS'] == 0 )) && (( $total_work_used < $array['BACKFILL_MAX_RELEASES'] ) || ( $array['BACKFILL_MAX_RELEASES'] == 0 )) && (( $parts_rows_unformatted < $array['BACKFILL_MAX_ROWS'] ) || ( $array['BACKFILL_MAX_ROWS'] == 0 ))) {
			$color = get_color($colors_start, $colors_end, $colors_exc);
			$log = writelog($panes0[3]);
			shell_exec("$_tmux respawnp -t${tmux_session}:0.3 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes0[3] $ds2 && $_backfill_cmd && echo \" \033[1;0;33m\" && $_sleep $back_timer && $ds1 $panes0[3] $ds3' 2>&1 1> /dev/null");
		} elseif (( $parts_rows_unformatted > $array['BACKFILL_MAX_ROWS'] ) && ( $backfill == 1 ) && ( $array['BACKFILL_MAX_ROWS'] != 0 ))  {
			$color = get_color($colors_start, $colors_end, $colors_exc);
			shell_exec("$_tmux respawnp -t${tmux_session}:0.3 'echo \"\033[38;5;\"$color\"m\nBACKFILL_MAX_ROWS exceeded\" && date +\"%D %T\"' 2>&1 1> /dev/null");
		} elseif (( $total_work_used > $array['BACKFILL_MAX_RELEASES'] ) && ( $backfill == 1 ) && ( $array['BACKFILL_MAX_RELEASES'] != 0 ))  {
			$color = get_color($colors_start, $colors_end, $colors_exc);
			shell_exec("$_tmux respawnp -t${tmux_session}:0.3 'echo \"\033[38;5;\"$color\"m\nBACKFILL_MAX_RELEASES exceeded\" && date +\"%D %T\"' 2>&1 1> /dev/null");
		} elseif (( $backfill == 1 ) && ( $maxload <= get_load())) {
                        $color = get_color($colors_start, $colors_end, $colors_exc);
                        shell_exec("$_tmux respawnp -t${tmux_session}:0.3 'echo \"\033[38;5;\"$color\"m\n$panes0[3] Disabled by MAX_LOAD\" && date +\"%D %T\"' 2>&1 1> /dev/null");
        } elseif ( $backfill == 0 ) {
			$color = get_color($colors_start, $colors_end, $colors_exc);
			shell_exec("$_tmux respawnp -t${tmux_session}:0.3 'echo \"\033[38;5;\"$color\"m\n$panes0[3] Disabled by BACKFILL\" && date +\"%D %T\"' 2>&1 1> /dev/null");
		}
	}

	//run update_binaries and backfill using seq in pane 0.2
	$dead = `$_tmux list-panes -t${tmux_session}:0 | grep 2: | grep dead`;
	if (( $array['SEQUENTIAL'] == "true" ) && ( strlen( $dead ) > "4" )) {
		if (( $maxload >= get_load()) && (( $i == 1 ) || ( TIME() - $time13 >= $array['BINARIES_SEQ_TIMER'] )) && (( $binaries_rows_unformatted < $array['BINARIES_MAX_BINS'] ) || ( $array['BINARIES_MAX_BINS'] == 0 )) && ( $binaries != 0  ) && (( $total_work_used < $array['BINARIES_MAX_RELEASES'] ) || ( $array['BINARIES_MAX_RELEASES'] == 0 )) && (( $parts_rows_unformatted < $array['BINARIES_MAX_ROWS'] ) || ( $array['BINARIES_MAX_ROWS'] == 0 ))) {
			$color = get_color($colors_start, $colors_end, $colors_exc);
			shell_exec("$_tmux respawnp -t${tmux_session}:0.2 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes0[2] $ds2 && $_update_cmd && echo \" \033[1;0;33m\" && $ds1 $panes0[2] $ds3' 2>&1 1> /dev/null");
			$time13 = TIME();
		} elseif (( $maxload >= get_load()) && ( TIME() - $time14 >= $array['BACKFILL_SEQ_TIMER'] ) && (( $binaries_rows_unformatted < $array['BACKFILL_MAX_BINS'] ) || ( $array['BACKFILL_MAX_BINS'] == 0 )) && ( $backfill == 1 ) && (( $total_work_used < $array['BACKFILL_MAX_RELEASES'] ) || ( $array['BACKFILL_MAX_RELEASES'] == 0 )) && (( $parts_rows_unformatted < $array['BACKFILL_MAX_ROWS'] ) || ( $array['BACKFILL_MAX_ROWS'] == 0 )))  {
			$color = get_color($colors_start, $colors_end, $colors_exc);
			shell_exec("$_tmux respawnp -t${tmux_session}:0.2 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes0[3] $ds2 && $_backfill_cmd && echo \" \033[1;0;33m\" && $ds1 $panes0[3] $ds3' 2>&1 1> /dev/null");
			$time14 = TIME();
		} elseif (( $binaries != 0  ) && ( $backfill == 0 )) {
			$color = get_color($colors_start, $colors_end, $colors_exc);
			shell_exec("$_tmux respawnp -t${tmux_session}:0.2 'echo \"\033[38;5;\"$color\"m\n$panes0[2] and $panes0[3] Disabled by BINARIES and BACKFILL\" && date +\"%D %T\"' 2>&1 1> /dev/null");
		} elseif (( $backfill == 1 ) && ( $binaries != 0  ) && ( $maxload >= get_load())) {
			$color = get_color($colors_start, $colors_end, $colors_exc);
			$run_time1 = relativeTime( $array['BACKFILL_SEQ_TIMER'] + $time14 );
			$run_time2 = relativeTime( $array['BINARIES_SEQ_TIMER'] + $time13 );
			shell_exec("$_tmux respawnp -t${tmux_session}:0.2 'echo \"\033[38;5;\"$color\"m\n$panes0[2] will run in T[ $run_time2]\n$panes0[3] will run in T[ $run_time1] \" && date +\"%D %T\"' 2>&1 1> /dev/null");
		} elseif (( $backfill == 1 ) && ( $maxload >= get_load())) {
			$color = get_color($colors_start, $colors_end, $colors_exc);
			$run_time = relativeTime( $array['BACKFILL_SEQ_TIMER'] + $time14 );
			shell_exec("$_tmux respawnp -t${tmux_session}:0.2 'echo \"\033[38;5;\"$color\"m\n$panes03] will run in T[ $run_time1]\" && date +\"%D %T\"' 2>&1 1> /dev/null");
		} elseif (( $binaries != 0  ) && ( $maxload >= get_load())) {
			$color = get_color($colors_start, $colors_end, $colors_exc);
			$run_time = relativeTime( $array['BINARIES_SEQ_TIMER'] + $time13 );
			shell_exec("$_tmux respawnp -t${tmux_session}:0.2 'echo \"\033[38;5;\"$color\"m\n$panes0[2] will run in T[ $run_time2]\" && date +\"%D %T\"' 2>&1 1> /dev/null");
		} elseif  ( $maxload <= get_load()) {
                        $color = get_color($colors_start, $colors_end, $colors_exc);
                        shell_exec("$_tmux respawnp -t${tmux_session}:0.2 'echo \"\033[38;5;\"$color\"m\n$panes0[2] Disabled by MAX_LOAD\" && date +\"%D %T\"' 2>&1 1> /dev/null");
                }
	}

	//notify pane 0.3 of the status of pane 0.2
	if ( $array['SEQUENTIAL'] == "true" ) {
		if (( $maxload >= get_load()) && ( $binaries != 0  ) && ( $backfill == 0 )) {
			$color = get_color($colors_start, $colors_end, $colors_exc);
			shell_exec("$_tmux respawnp -t${tmux_session}:0.3 'echo \"\033[38;5;\"$color\"m\n$panes0[2] and $panes0[3] Disabled by BINARIES and BACKFILL\" && date +\"%D %T\"' 2>&1 1> /dev/null");
		} elseif (( $backfill == 1 ) && ( $binaries != 0  ) && ( $maxload >= get_load())) {
			$run_time1 = relativeTime( $array['BACKFILL_SEQ_TIMER'] + $time14 );
			$run_time2 = relativeTime( $array['BINARIES_SEQ_TIMER'] + $time13 );
			$color = get_color($colors_start, $colors_end, $colors_exc);
			shell_exec("$_tmux respawnp -t${tmux_session}:0.3 'echo \"\033[38;5;\"$color\"m\n$panes0[2] will run in T[ $run_time2]\n$panes0[3] will run in T[ $run_time1] \" && date +\"%D %T\"' 2>&1 1> /dev/null");
		} elseif (( $backfill == 1 ) && ( $maxload >= get_load())) {
			$run_time = relativeTime( $array['BACKFILL_SEQ_TIMER'] + $time14 );
			$color = get_color($colors_start, $colors_end, $colors_exc);
			shell_exec("$_tmux respawnp -t${tmux_session}:0.3 'echo \"\033[38;5;\"$color\"m\n$panes0[3] will run in T[ $run_time1]\" && date +\"%D %T\"' 2>&1 1> /dev/null");
		} elseif (( $binaries != 0  ) && ( $maxload >= get_load())) {
			$run_time = relativeTime( $array['BINARIES_SEQ_TIMER'] + $time13 );
			$color = get_color($colors_start, $colors_end, $colors_exc);
			shell_exec("$_tmux respawnp -t${tmux_session}:0.3 'echo \"\033[38;5;\"$color\"m\n$panes0[2] will run in T[ $run_time2]\" && date +\"%D %T\"' 2>&1 1> /dev/null");
		} elseif ( $maxload <= get_load()) {
                        $color = get_color($colors_start, $colors_end, $colors_exc);
                        shell_exec("$_tmux respawnp -t${tmux_session}:0.3 'echo \"\033[38;5;\"$color\"m\n$panes0[3] Disabled by MAX_LOAD\" && date +\"%D %T\"' 2>&1 1> /dev/null");
                }
	}

	//kill panes for sequential
	if ( $array['SEQUENTIAL'] == "true" ) {
		if (( $parts_rows_unformatted > $array['BINARIES_MAX_ROWS'] ) && ( $binaries != 0  ) && ( $array['BINARIES_MAX_ROWS'] != 0 ) && ( $backfill == 1 ) && ( $total_work_used > $array['BACKFILL_MAX_RELEASES'] ) && ( $array['BACKFILL_MAX_ROWS'] != 0 )) {
			$color = get_color($colors_start, $colors_end, $colors_exc);
			shell_exec("$_tmux respawnp -k -t${tmux_session}:0.2 'echo \"\033[38;5;\"$color\"m\nBINARIES_MAX_ROWS and BACKFILL_MAX_ROWS exceeded\" && $ds1 $panes0[2] $ds4'");
			shell_exec("$_tmux respawnp -k -t${tmux_session}:0.3 'echo \"\033[38;5;\"$color\"m\nBINARIES_MAX_ROWS and BACKFILL_MAX_ROWS exceeded\" && $ds1 $panes0[3] $ds4'");
		}
	}

	//runs nzb-import in 0.4 once if needed and exits
	if (( $maxload >= get_load()) && ( $killed != "true" ) && ( $import == 1 ) && (( $total_work_used < $array['IMPORT_MAX_RELEASES'] ) || ( $array['IMPORT_MAX_RELEASES'] == 0 )) && (( $parts_rows_unformatted < $array['IMPORT_MAX_ROWS'] ) || ( $array['IMPORT_MAX_ROWS'] == 0 ))) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		$log = writelog($panes0[4]);
		shell_exec("$_tmux respawnp -t${tmux_session}:0.4 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes0[4] $ds2 && cd $_bin && $nzb_cmd && echo \" \" && echo \" \033[1;0;33m\" && $_sleep {$array['IMPORT_SLEEP']} && $ds1 $panes0[4] $ds3' 2>&1 1> /dev/null");
		$color = get_color($colors_start, $colors_end, $colors_exc);
		$log = writelog($panes1[5]);
		shell_exec("$_tmux respawnp -t${tmux_session}:1.5 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes1[5] $ds2 && cd $_bin && $_php nzbcount.php 2>&1 $log' 2>&1 1> /dev/null");

	} elseif (( $parts_rows_unformatted > $array['IMPORT_MAX_ROWS'] ) && ( $import == 1 ) && ( $array['IMPORT_MAX_ROWS'] != 0 )) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		shell_exec("$_tmux respawnp -t${tmux_session}:0.4 'echo \"\033[38;5;\"$color\"m\nIMPORT_MAX_ROWS exceeded\" && date +\"%D %T\"' 2>&1 1> /dev/null");

	} elseif (( $total_work_used > $array['IMPORT_MAX_RELEASES'] ) && ( $import == 1 ) && ( $array['IMPORT_MAX_RELEASES'] != 0 )) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		shell_exec("$_tmux respawnp -t${tmux_session}:0.4 'echo \"\033[38;5;\"$color\"m\nIMPORT_MAX_RELEASES exceeded\" && date +\"%D %T\"' 2>&1 1> /dev/null");

	} elseif (( $import == 1 ) && ( $maxload <= get_load())) {
                $color = get_color($colors_start, $colors_end, $colors_exc);
                shell_exec("$_tmux respawnp -t${tmux_session}:0.4 'echo \"\033[38;5;\"$color\"m\n$panes0[4] Disabled by MAX_LOAD\" && date +\"%D %T\"' 2>&1 1> /dev/null");

    } elseif ( $import == 0 ) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		shell_exec("$_tmux respawnp -t${tmux_session}:0.4 'echo \"\033[38;5;\"$color\"m\n$panes0[4] Disabled by IMPORT\" && date +\"%D %T\"' 2>&1 1> /dev/null");

	}

	//runs nzbcount in 1.5 loops
	if (( $maxload >= get_load()) && ( $import == 1 )) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		$log = writelog($panes1[5]);
		shell_exec("$_tmux respawnp -t${tmux_session}:1.5 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes1[5] $ds2 && cd $_bin && $_php nzbcount.php 2>&1 $log' 2>&1 1> /dev/null");
	} elseif ( $import == 0 ) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		shell_exec("$_tmux respawnp -t${tmux_session}:1.5 'echo \"\033[38;5;\"$color\"m\n$panes1[5] Disabled by IMPORT\" && date +\"%D %T\"' 2>&1 1> /dev/null");
	} elseif ( $maxload <= get_load()) {
                $color = get_color($colors_start, $colors_end, $colors_exc);
                shell_exec("$_tmux respawnp -t${tmux_session}:1.5 'echo \"\033[38;5;\"$color\"m\n$panes1[5] Disabled by MAX_LOAD\" && date +\"%D %T\"' 2>&1 1> /dev/null");
        }

	//start postprocessing in pane 0.1

        //start postprocessing in pane 0.1

        if (($post == 1 && ($work_remaining_now + $pc_releases_proc + $xxx_releases_proc) > 0)) {
				//run postprocess_releases additional
				$history = str_replace(" ", '', `tmux list-panes -t${tmux_session}:0 | grep 1: | awk '{print $4;}'`);
				if ($last_history != $history) {
					$last_history = $history;
					$time29 = TIME();
				} else {
					if (TIME() - $time29 >= $post_kill_timer) {
						$color = get_color($colors_start, $colors_end, $colors_exc);
						passthru("tmux respawnp -k -t${tmux_session}:0.1 'echo \"\033[38;5;${color}m\n${panes0[1]} has been terminated by Possible Hung thread\"'");
						$wipe = `tmux clearhist -t${tmux_session}:0.1`;
						$time29 = TIME();
					}
				}
				$dead1 = str_replace(" ", '', `tmux list-panes -t${tmux_session}:0 | grep dead | grep 1: | wc -l`);
				if ($dead1 == 1) {
					$time29 = TIME();
				}
				$log = writelog($panes0[1]);
				shell_exec("tmux respawnp -t${tmux_session}:0.1 'echo \"\033[38;5;${color}m\"; \
						$_python ${DIR}/../test/postprocess_threaded.py additional $log; date +\"%D %T\"; $_sleep $post_timer' 2>&1 1> /dev/null");
		} else if (($post == 3) && ($work_remaining_now + $pc_releases_proc + $xxx_releases_proc > 0)) {
				//run postprocess_releases additional
				$history = str_replace(" ", '', `tmux list-panes -t${tmux_session}:0 | grep 1: | awk '{print $4;}'`);
				if ($last_history != $history) {
					$last_history = $history;
					$time29 = TIME();
				} else {
					if (TIME() - $time29 >= $post_kill_timer) {
						$color = get_color($colors_start, $colors_end, $colors_exc);
						shell_exec("tmux respawnp -k -t${tmux_session}:0.1 'echo \"\033[38;5;${color}m\n${panes0[1]} has been terminated by Possible Hung thread\"'");
						$wipe = `tmux clearhist -t${tmux_session}:0.1`;
						$time29 = TIME();
					}
				}
				$dead1 = str_replace(" ", '', `tmux list-panes -t${tmux_session}:0 | grep dead | grep 1: | wc -l`);
				if ($dead1 == 1) {
					$time29 = TIME();
				}
				$log = writelog($panes0[1]);
				shell_exec("tmux respawnp -t${tmux_session}:0.1 ' \
						$_python ${DIR}/../test/postprocess_threaded.py additional $log; date +\"%D %T\"; $_sleep $post_timer' 2>&1 1> /dev/null");
			} else if (($post != 0) && ($work_remaining_now + $pc_releases_proc + $xxx_releases_proc == 0)) {
				$color = get_color($colors_start, $colors_end, $colors_exc);
				shell_exec("tmux respawnp -k -t${tmux_session}:0.1 'echo \"\033[38;5;${color}m\n${panes0[1]} has been disabled/terminated by No Misc to process\"'");
			} else if ( $maxload <= get_load()) {
                        shell_exec("$_tmux respawnp -t${tmux_session}:0.1 'echo \"\033[38;5;\"$color\"m\n$panes0[1] Disabled by MAX_LOAD\" && date +\"%D %T\"' 2>&1 1> /dev/null");
            } else  {
				$color = get_color($colors_start, $colors_end, $colors_exc);
				shell_exec("tmux respawnp -k -t${tmux_session}:0.1 'echo \"\033[38;5;${color}m\n${panes0[1]} has been disabled/terminated by Postprocess Additional\"'");
			}

	//runs update_release and in 0.5 once if needed and exits
	if (( $maxloadr >= get_load()) && ( $releases_run == 2 )) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		$log = writelog($panes0[5]);
		shell_exec("$_tmux respawnp -t${tmux_session}:0.5 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes0[5] $ds2 && cd $_test && $_python ${DIR}/../test/releases_threaded.py && 2>&1 $log && echo \" \033[1;0;33m\" && $_sleep $rel_timer && $ds1 $panes0[5] $ds3' 2>&1 1> /dev/null");
	} elseif (( $maxloadr >= get_load()) && ( $releases_run == 1 )) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		$log = writelog($panes0[5]);
		shell_exec("$_tmux respawnp -t${tmux_session}:0.5 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes0[5] $ds2 && cd $_bin && $_php update_releases.php && 2>&1 $log && echo \" \033[1;0;33m\" && $_sleep $rel_timer && $ds1 $panes0[5] $ds3' 2>&1 1> /dev/null");
    } elseif (( $maxloadr >= get_load()) && ( $releases_run == 2 )) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		$log = writelog($panes0[5]);
		shell_exec("$_tmux respawnp -t${tmux_session}:0.5 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes0[5] $ds2 && cd $_test && $_python ${DIR}/../test/releases_threaded.py 2>&1 $log && echo \" \033[1;0;33m\" && $_sleep $rel_timer && $ds1 $panes0[5] $ds3' 2>&1 1> /dev/null");
	} elseif (( $maxloadr >= get_load()) && ( $releases_run == 1 )) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		$log = writelog($panes0[5]);
		shell_exec("$_tmux respawnp -t${tmux_session}:0.5 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes0[5] $ds2 && cd $_bin && $_php update_releases.php && 2>&1 $log && echo \" \033[1;0;33m\" && $_sleep $rel_timer && $ds1 $panes0[5] $ds3' 2>&1 1> /dev/null");
    } elseif (( $releases_run !== 0  ) && ( $maxloadr <= get_load())) {
                $color = get_color($colors_start, $colors_end, $colors_exc);
                shell_exec("$_tmux respawnp -t${tmux_session}:0.5 'echo \"\033[38;5;\"$color\"m\n$panes0[5] Disabled by MAX_LOAD_RELEASES\" && date +\"%D %T\"' 2>&1 1> /dev/null");
    } elseif ( $releases_run == 0  ) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		shell_exec("$_tmux respawnp -t${tmux_session}:0.5 'echo \"\033[38;5;\"$color\"m\n$panes0[5] Disabled by RELEASES\" && date +\"%D %T\"' 2>&1 1> /dev/null");
	}

	//run update_predb.php in 1.0 ever 15 minutes and on fifth loop
	if (( $maxload >= get_load()) && ((( TIME() - $time2 ) >= $array['PREDB_TIMER'] ) || ( $i == 5 )) && ( $array['PREDB'] == "true" )) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		$log = writelog($panes1[0]);
		shell_exec("$_tmux respawnp -t${tmux_session}:1.0 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes1[0] $ds2 && cd $NNPATH && $_php update_predb.php true 2>&1 $log && echo \" \033[1;0;33m\" && $ds1 $panes1[0] $ds3' 2>&1 1> /dev/null");
		$time2 = TIME();
	} elseif (( $array['PREDB'] == "true" ) && ( $maxload >= get_load())) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		$run_time = relativeTime( $array['PREDB_TIMER'] + $time2 );
		shell_exec("$_tmux respawnp -t${tmux_session}:1.0 'echo \"\033[38;5;\"$color\"m\n$panes1[0] will run in T[ $run_time]\" && date +\"%D %T\"' 2>&1 1> /dev/null");
	} elseif (( $maxload >= get_load()) && ( $array['PREDB'] != "true" )) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		shell_exec("$_tmux respawnp -t${tmux_session}:1.0 'echo \"\033[38;5;\"$color\"m\n$panes1[0] Disabled by PREDB\" && date +\"%D %T\"' 2>&1 1> /dev/null");
    } elseif ( $maxload <= get_load()) {
                $color = get_color($colors_start, $colors_end, $colors_exc);
                shell_exec("$_tmux respawnp -t${tmux_session}:1.0 'echo \"\033[38;5;\"$color\"m\n$panes1[0] Disabled by MAX_LOAD\" && date +\"%D %T\"' 2>&1 1> /dev/null");
	}

	//run update_tvschedule.php and $_php update_theaters.php in 1.1 every 12 hours and tenth loop
	if (( $maxload >= get_load()) && (( TIME() - $time4 ) >= $tv_timer ) && ( $update_tv == 1)) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		$log = writelog($panes1[1]);
		shell_exec("$_tmux respawnp -t${tmux_session}:1.1 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes1[1] $ds2 && cd $NNPATH && $_php update_tvschedule.php 2>&1 $log && $_php update_theaters.php 2>&1 $log && echo \" \033[1;0;33m\" && $ds1 $panes1[1] $ds3' 2>&1 1> /dev/null");
		$time4 = TIME();
	} else if (( $update_tv == 1) && ( $maxload >= get_load())) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		$run_time = relativeTime( $tv_timer + $time4 );
		shell_exec("$_tmux respawnp -t${tmux_session}:1.1 'echo \"\033[38;5;\"$color\"m\n$panes1[1] and update_theaters will run in T[ $run_time]\" && date +\"%D %T\"' 2>&1 1> /dev/null");
	} else if ( $update_tv == 0) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		shell_exec("$_tmux respawnp -t${tmux_session}:1.1 'echo \"\033[38;5;\"$color\"m\n$panes1[1] has been disabled/terminated by Update TV/Theater\" && date +\"%D %T\"' 2>&1 1> /dev/null");
	} else if ( $maxload <= get_load()) {
                $color = get_color($colors_start, $colors_end, $colors_exc);
                shell_exec("$_tmux respawnp -t${tmux_session}:1.1 'echo \"\033[38;5;\"$color\"m\n$panes1[1] Disabled by MAX_LOAD\" && date +\"%D %T\"' 2>&1 1> /dev/null");
        }

	//run sphinx in pane 1.2
	if (( $maxload >= get_load()) && ( TIME() - $time9 >= $array['SPHINX_TIMER'] ) && ( $array['SPHINX'] == "true")) {
		$color=get_color();
		$log = writelog($panes1[2]);
		shell_exec("$_tmux respawnp -t${tmux_session}:1.2 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes1[2] $ds2 && cd $_bin && $_php sphinx.php 2>&1 $log && echo \" \033[1;0;33m\" && $ds1 $panes1[2] $ds3' 2>&1 1> /dev/null");
		$time9 = TIME();
	} elseif ( $array['SPHINX'] != "true" ) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		shell_exec("$_tmux respawnp -t${tmux_session}:1.2 'echo \"\033[38;5;\"$color\"m\n$panes1[2] Disabled by SPHINX\" && date +\"%D %T\"' 2>&1 1> /dev/null");
	} elseif ( $maxload >= get_load()) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		$run_time = relativeTime( $array['SPHINX_TIMER'] + $time9 );
		shell_exec("$_tmux respawnp -t${tmux_session}:1.2 'echo \"\033[38;5;\"$color\"m\n$panes1[2] will run in T[ $run_time]\" && date +\"%D %T\"' 2>&1 1> /dev/null");
	} elseif ( $maxload <= get_load()) {
                $color = get_color($colors_start, $colors_end, $colors_exc);
                shell_exec("$_tmux respawnp -t${tmux_session}:1.2 'echo \"\033[38;5;\"$color\"m\n$panes1[2] Disabled by MAX_LOAD\" && date +\"%D %T\"' 2>&1 1> /dev/null");
        }

	//run delete parts in pane 1.3
	if (( $maxload >= get_load()) && (( TIME() - $time16 ) >= $array['DELETE_TIMER'] ) && ( $array['DELETE_PARTS'] == "true" )) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		$log = writelog($panes1[3]);
		shell_exec("$_tmux respawnp -t${tmux_session}:1.3 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes1[3] $ds2 && cd $_cj && $_php remove_parts_without_releases.php 2>&1 $log && $ds1 $panes1[3] $ds3' 2>&1 1> /dev/null");
		$time16 = TIME();
	} elseif (( $array['DELETE_PARTS'] != "true" )) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		shell_exec("$_tmux respawnp -t${tmux_session}:1.3 'echo \"\033[38;5;\"$color\"m\n$panes1[3] Disabled by DELETE_PARTS\" && date +\"%D %T\"' 2>&1 1> /dev/null");
	} elseif ( $maxload >= get_load()) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		$run_time = relativeTime( $array['DELETE_TIMER'] + $time16 );
		shell_exec("$_tmux respawnp -t${tmux_session}:1.3 'echo \"\033[38;5;\"$color\"m\n$panes1[3] will run in T[ $run_time]\" && date +\"%D %T\"' 2>&1 1> /dev/null");
	} elseif ( $maxload <= get_load()) {
                $color = get_color($colors_start, $colors_end, $colors_exc);
                shell_exec("$_tmux respawnp -t${tmux_session}:1.3 'echo \"\033[38;5;\"$color\"m\n$panes1[3] Disabled by MAX_LOAD\" && date +\"%D %T\"' 2>&1 1> /dev/null");
        }

	//run update_missing_movie_info parts in pane 1.4 on 15th loop
	if (( $maxload >= get_load()) && ((( TIME() - $time17 ) >= $array['MOVIE_TIMER'] ) || ( $i == 15 )) && ( $array['FETCH_MOVIE'] == "true" )) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		$log = writelog($panes1[4]);
		shell_exec("$_tmux respawnp -t${tmux_session}:1.4 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes1[4] $ds2 && cd $_cj && $_php update_missing_movie_info.php 2>&1 $log && $ds1 $panes1[4] $ds3' 2>&1 1> /dev/null");
		$time17 = TIME();
	} else if ( $array['FETCH_MOVIE'] != "true" ) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		shell_exec("$_tmux respawnp -t${tmux_session}:1.4 'echo \"\033[38;5;\"$color\"m\n$panes1[4] Disabled by FETCH_MOVIE\" && date +\"%D %T\"' 2>&1 1> /dev/null");
	} else if ( $maxload >= get_load()) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		$run_time = relativeTime( $array['MOVIE_TIMER'] + $time17 );
		shell_exec("$_tmux respawnp -t${tmux_session}:1.4 'echo \"\033[38;5;\"$color\"m\n$panes1[4] will run in T[ $run_time]\" && date +\"%D %T\"' 2>&1 1> /dev/null");
	} else if ( $maxload <= get_load()) {
                $color = get_color($colors_start, $colors_end, $colors_exc);
                shell_exec("$_tmux respawnp -t${tmux_session}:1.4 'echo \"\033[38;5;\"$color\"m\n$panes1[4] Disabled by MAX_LOAD\" && date +\"%D %T\"' 2>&1 1> /dev/null");
        }

	//runs postprocess_nfo.php in pane 2.0 once if needed then exits
	if (($maxload >= get_load()) && ($post == 2) && ($nfo_remaining_now > 0)) {
				$log = writelog($panes2[0]);
				shell_exec("tmux respawnp -t${tmux_session}:2.0 ' \
						$_python ${DIR}/../test/postprocess_threaded.py nfo $log; date +\"%D %T\"; $_sleep $post_timer' 2>&1 1> /dev/null");
    } else if (($maxload >= get_load()) && ($post == 3) && ($nfo_remaining_now > 0)) {
				//run postprocess_releases nfo
				$history = str_replace(" ", '', `tmux list-panes -t${tmux_session}:2 | grep 0: | awk '{print $4;}'`);
				if ($last_history != $history) {
					$last_history = $history;
					$time2 = TIME();
				} else {
					if (TIME() - $time31 >= $post_kill_timer) {
						$color = get_color($colors_start, $colors_end, $colors_exc);
						shell_exec("tmux respawnp -k -t${tmux_session}:2.0 'echo \"\033[38;5;${color}m\n${panes2[0]} has been terminated by Possible Hung thread\"'");
						$wipe = `tmux clearhist -t${tmux_session}:2.0`;
						$time31 = TIME();
					}
				}
				$dead2 = str_replace(" ", '', `tmux list-panes -t${tmux_session}:2 | grep dead | grep 0: | wc -l`);
				if ($dead2 == 1) {
					$time31 = TIME();
				}
				$log = writelog($panes2[0]);
				shell_exec("tmux respawnp -t${tmux_session}:2.0 ' \
						$_python ${DIR}/../test/postprocess_threaded.py nfo $log; date +\"%D %T\"; $_sleep $post_timer' 2>&1 1> /dev/null");
	} else if (($maxload >= get_load()) && ($post != 0) && ($nfo_remaining_now == 0)) {
				$color = get_color($colors_start, $colors_end, $colors_exc);
				shell_exec("tmux respawnp -k -t${tmux_session}:2.0 'echo \"\033[38;5;${color}m\n${panes2[0]} has been disabled/terminated by No Nfo to process\"'");
	} else if ( $maxload <= get_load()) {
                $color = get_color($colors_start, $colors_end, $colors_exc);
                shell_exec("$_tmux respawnp -t${tmux_session}:2.0 'echo \"\033[38;5;\"$color\"m\n$panes2[0] Disabled by MAX_LOAD\" && date +\"%D %T\"' 2>&1 1> /dev/null");
    } else {
				$color = get_color($colors_start, $colors_end, $colors_exc);
				shell_exec("tmux respawnp -k -t${tmux_session}:2.0 'echo \"\033[38;5;${color}m\n${panes2[0]} has been disabled/terminated by Postprocess NFO\"'");
    }

    //Postprocess TV Releases in pane 2.1
	if (($maxload >= get_load()) && ($post_non != 0) && ($tvrage_releases_proc > 0)) {
				//run postprocess_releases non amazon
				$log = writelog($panes2[1]);
				shell_exec("tmux respawnp -t${tmux_session}:2.1 ' \
						$_python ${DIR}/../test/postprocess_threaded.py tv $clean $log; date +\"%D %T\"; $_sleep $post_timer_non' 2>&1 1> /dev/null");
	} else if (($post_non != 0) && ($tvrage_releases_proc == 0)) {
				$color = get_color($colors_start, $colors_end, $colors_exc);
				shell_exec("tmux respawnp -k -t${tmux_session}:2.1 'echo \"\033[38;5;${color}m\n${panes2[1]} has been disabled/terminated by No TV to process\"'");
	} else if ( $maxload <= get_load()) {
                $color = get_color($colors_start, $colors_end, $colors_exc);
                shell_exec("$_tmux respawnp -t${tmux_session}:2.1 'echo \"\033[38;5;\"$color\"m\n$panes2[1] Disabled by MAX_LOAD\" && date +\"%D %T\"' 2>&1 1> /dev/null");
    } else {
				$color = get_color($colors_start, $colors_end, $colors_exc);
				shell_exec("tmux respawnp -k -t${tmux_session}:2.1 'echo \"\033[38;5;${color}m\n${panes2[1]} has been disabled/terminated by Postprocess Non-Amazon\"'");
			}

    //Process movies in pane 2.2
	if (($maxload >= get_load()) && ($post_non != 0) && ($movie_releases_proc > 0)) {
				//run postprocess_releases non amazon
				$log = writelog($panes2[2]);
				shell_exec("tmux respawnp -t${tmux_session}:2.2 ' \
						$_python ${DIR}/../test/postprocess_threaded.py movie $clean $log; date +\"%D %T\"; $_sleep $post_timer_non' 2>&1 1> /dev/null");
	} else if (($maxload >= get_load()) && ($post_non != 0) && ($movie_releases_proc == 0)) {
				$color = get_color($colors_start, $colors_end, $colors_exc);
				shell_exec("tmux respawnp -k -t${tmux_session}:2.2 'echo \"\033[38;5;${color}m\n${panes2[2]} has been disabled/terminated by No Movies to process\"'");
	} else if ( $maxload <= get_load()) {
                $color = get_color($colors_start, $colors_end, $colors_exc);
                shell_exec("$_tmux respawnp -t${tmux_session}:2.2 'echo \"\033[38;5;\"$color\"m\n$panes2[2] Disabled by MAX_LOAD\" && date +\"%D %T\"' 2>&1 1> /dev/null");
    } else {
				$color = get_color($colors_start, $colors_end, $colors_exc);
				shell_exec("tmux respawnp -k -t${tmux_session}:2.2 'echo \"\033[38;5;${color}m\n${panes2[2]} has been disabled/terminated by Postprocess Non-Amazon\"'");
			}

    //Process Music releases in pane 2.3 once if needed then exits
	if (($maxload >= get_load()) && ($post_amazon == 1) && ($music_releases_proc > 0)) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		$log = writelog($panes2[3]);
		shell_exec("$_tmux respawnp -t${tmux_session}:2.3 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes2[3] $ds2 && cd $_bin && $_php postprocess_new.php music 2>&1 $log && $_sleep $post_timer_amazon && echo \" \033[1;0;33m\" && $ds1 $panes2[3] $ds3' 2>&1 1> /dev/null");
	} else if (($maxload >= get_load()) && ($post_amazon == 1) && ($music_releases_proc == 0)) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		shell_exec("$_tmux respawnp -t${tmux_session}:2.3 'echo \"\033[38;5;${color}m\n${panes2[3]} has been disabled/terminated by No Music to process\"'");
    } else if ( $maxload <= get_load()) {
                $color = get_color($colors_start, $colors_end, $colors_exc);
                shell_exec("$_tmux respawnp -t${tmux_session}:2.3 'echo \"\033[38;5;\"$color\"m\n$panes2[3] Disabled by MAX_LOAD\" && date +\"%D %T\"' 2>&1 1> /dev/null");
    } else {
	        $color = get_color($colors_start, $colors_end, $colors_exc);
			shell_exec("tmux respawnp -k -t${tmux_session}:2.3 'echo \"\033[38;5;${color}m\n${panes2[3]} has been disabled/terminated by Postprocess Amazon\"'");
	}

	//Process Anime releases in pane 2.4 once if needed then exits
	if (($maxload >= get_load()) && ($post_non != 0) && ( $tvrage_releases_proc > 0 )) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		$log = writelog($panes2[4]);
		shell_exec("$_tmux respawnp -t${tmux_session}:2.4 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes2[4] $ds2 && cd $_bin && $_php postprocess_new.php anime 2>&1 $log && $_sleep $post_timer_non && echo \" \033[1;0;33m\" && $ds1 $panes2[4] $ds3' 2>&1 1> /dev/null");
	} else if (($maxload >= get_load()) && ($post_non != 0) && ($tvrage_releases_proc == 0)) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		shell_exec("$_tmux respawnp -t${tmux_session}:2.4 'echo \"\033[38;5;${color}m\n${panes2[3]} has been disabled/terminated by No Anime to process\"'");
    } else if ( $maxload <= get_load()) {
                $color = get_color($colors_start, $colors_end, $colors_exc);
                shell_exec("$_tmux respawnp -t${tmux_session}:2.4 'echo \"\033[38;5;\"$color\"m\n$panes2[4] Disabled by MAX_LOAD\" && date +\"%D %T\"' 2>&1 1> /dev/null");
    } else  {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		shell_exec("$_tmux respawnp -t${tmux_session}:2.4 'echo \"\033[38;5;${color}m\n${panes2[4]} has been disabled/terminated by Postprocess Anime\"'");
        }

	//Process Console releases in pane 2.5 once if needed then exits
	if (($maxload >= get_load()) && ($post_amazon == 1) && ( $console_releases_proc > 0 )) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		$log = writelog($panes2[5]);
		shell_exec("$_tmux respawnp -t${tmux_session}:2.5 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes2[5] $ds2 && cd $_bin && $_php postprocess_new.php games 2>&1 $log && $_sleep $post_timer_amazon && echo \" \033[1;0;33m\" && $ds1 $panes2[5] $ds3' 2>&1 1> /dev/null");
	} else if (($maxload >= get_load()) && ($post_amazon == 1) && ($console_releases_proc == 0 ))  {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		shell_exec("$_tmux respawnp -t${tmux_session}:2.5 'echo \"\033[38;5;\"$color\"m\n$panes2[5] has been disabled/terminated by No Games to process\"'");
    } else if ($maxload <= get_load()) {
                $color = get_color($colors_start, $colors_end, $colors_exc);
                shell_exec("$_tmux respawnp -t${tmux_session}:2.5 'echo \"\033[38;5;\"$color\"m\n$panes2[5] Disabled by MAX_LOAD\" && date +\"%D %T\"' 2>&1 1> /dev/null");
    } else {
        $color = get_color($colors_start, $colors_end, $colors_exc);
		shell_exec("$_tmux respawnp -t${tmux_session}:2.5 'echo \"\033[38;5;${color}m\n${panes2[5]} has been disabled/terminated by Postprocess Games\"'");
    }

	//run processSpotnab.php in 2.6 every 15 minutes
	if (( $maxload >= get_load()) && (( TIME() - $time20 ) >= $array['SPOTNAB_TIMER'] ) && ( $array['SPOTNAB'] == "true" )) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		$log = writelog($panes2[6]);
		shell_exec("$_tmux respawnp -t${tmux_session}:2.6 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes2[6] $ds2 && cd $_bin && $_php postprocess_new.php spotnab true 2>&1 $log && echo \" \033[1;0;33m\" && $ds1 $panes2[6] $ds3' 2>&1 1> /dev/null");
		$time20 = TIME();
	} else if ( $array['SPOTNAB'] != "true" ) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		shell_exec("$_tmux respawnp -t${tmux_session}:2.6 'echo \"\033[38;5;\"$color\"m\n$panes2[6] Disabled by SPOTNAB\" && date +\"%D %T\"' 2>&1 1> /dev/null");
	} else if (( $array['SPOTNAB'] == "true" ) && ( $maxload >= get_load())) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		$run_time = relativeTime( $array['SPOTNAB_TIMER'] + $time20 );
		shell_exec("$_tmux respawnp -t${tmux_session}:2.6 'echo \"\033[38;5;\"$color\"m\n$panes2[6] will run in T[ $run_time]\" && date +\"%D %T\"' 2>&1 1> /dev/null");
	} else if ( $maxload <= get_load()) {
                $color = get_color($colors_start, $colors_end, $colors_exc);
                shell_exec("$_tmux respawnp -t${tmux_session}:2.6 'echo \"\033[38;5;\"$color\"m\n$panes2[6] Disabled by MAX_LOAD\" && date +\"%D %T\"' 2>&1 1> /dev/null");
    }

	//Process Books in pane 2.7 once if needed then exits
	if (($maxload >= get_load()) && ($post_amazon == 1) && ( $book_releases_proc > 0 )) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		$log = writelog($panes2[7]);
		shell_exec("$_tmux respawnp -t${tmux_session}:2.7 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes2[7] $ds2 && cd $_bin && $_php postprocess_new.php ebook 2>&1 $log && $_sleep $post_timer_amazon && echo \" \033[1;0;33m\" && $ds1 $panes2[7] $ds3' 2>&1 1> /dev/null");
	} else if (($maxload >= get_load()) && ($post_amazon == 1) && ($book_releases_proc == 0)) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		shell_exec("$_tmux respawnp -t${tmux_session}:2.7 'echo \"\033[38;5;\"$color\"m\n$panes2[7] has been disabled/terminated by No Books to process\" && date +\"%D %T\"' 2>&1 1> /dev/null");
    } else if ( $maxload <= get_load()) {
                $color = get_color($colors_start, $colors_end, $colors_exc);
                shell_exec("$_tmux respawnp -t${tmux_session}:2.7 'echo \"\033[38;5;\"$color\"m\n$panes2[7] Disabled by MAX_LOAD\" && date +\"%D %T\"' 2>&1 1> /dev/null");
    } else {
        $color = get_color($colors_start, $colors_end, $colors_exc);
		shell_exec("$_tmux respawnp -t${tmux_session}:2.7 'echo \"\033[38;5;${color}m\n${panes2[7]} has been disabled/terminated by Postprocess Books\"'");
    }

	//Process Other releases in pane 2.8 once if needed then exits
	if  (( $maxload >= get_load()) && ( $array['OTHERS'] == "true" )) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		$log = writelog($panes2[8]);
		shell_exec("$_tmux respawnp -t${tmux_session}:2.8 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes2[8] $ds2 && cd $_bin && $_php postprocess_new.php other 2>&1 $log && echo \" \033[1;0;33m\" && $ds1 $panes2[8] $ds3' 2>&1 1> /dev/null");
	} elseif ( $array['OTHERS'] != "true" ) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		shell_exec("$_tmux respawnp -t${tmux_session}:2.8 'echo \"\033[38;5;\"$color\"m\n$panes2[8] Disabled by OTHERS\" && date +\"%D %T\"' 2>&1 1> /dev/null");
	} elseif ( $maxload <= get_load()) {
                $color = get_color($colors_start, $colors_end, $colors_exc);
                shell_exec("$_tmux respawnp -t${tmux_session}:2.8 'echo \"\033[38;5;\"$color\"m\n$panes2[8] Disabled by MAX_LOAD\" && date +\"%D %T\"' 2>&1 1> /dev/null");
    } else  {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		shell_exec("$_tmux respawnp -t${tmux_session}:2.8 'echo \"\033[38;5;\"$color\"m\n$panes2[8] Has no work to process \" && date +\"%D %T\"' 2>&1 1> /dev/null");
        }

	//Process Unwanted releases in pane 2.9 in continuous loop, will restart if exits
	if  (( $maxload >= get_load()) && ( $array['UNWANTED'] == "true" )) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		$log = writelog($panes2[9]);
		shell_exec("$_tmux respawnp -t${tmux_session}:2.9 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes2[9] $ds2 && cd $_bin && $_php postprocess_new.php unwanted 2>&1 $log && echo \" \033[1;0;33;33m\" && $ds1 $panes2[9] $ds3' 2>&1 1> /dev/null");
	} elseif ( $array['UNWANTED'] != "true" ) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		shell_exec("$_tmux respawnp -t${tmux_session}:2.9 'echo \"\033[38;5;\"$color\"m\n$panes2[9] Disabled by UNWANTED\" && date +\"%D %T\"' 2>&1 1> /dev/null");
	} elseif ( $maxload <= get_load()) {
                $color = get_color($colors_start, $colors_end, $colors_exc);
                shell_exec("$_tmux respawnp -t${tmux_session}:2.9 'echo \"\033[38;5;\"$color\"m\n$panes2[9] Disabled by MAX_LOAD\" && date +\"%D %T\"' 2>&1 1> /dev/null");
    } else  {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		shell_exec("$_tmux respawnp -t${tmux_session}:2.9 'echo \"\033[38;5;\"$color\"m\n$panes2[9] Has no work to process \" && date +\"%D %T\"' 2>&1 1> /dev/null");
        }

    //run fixreleasenames threaded in pane 3.0
	if (( $maxload >= get_load()) && ( $fix_names == 1 )) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		$log = writelog($panes3[0]);
		shell_exec("$_tmux respawnp -t${tmux_session}:3.0 ' \
                    $ds1 $panes3[0] $ds2; \
                    cd $_test && $_python ${DIR}/../test/fixreleasenames_threaded.py md5 2>&1 $log; \
                    $_python ${DIR}/../test/fixreleasenames_threaded.py nfo 2>&1 $log; \
                    $_python ${DIR}/../test/fixreleasenames_threaded.py filename 2>&1 $log; \
                    $_python ${DIR}/../test/fixreleasenames_threaded.py par2 2>&1 $log; \
                    $_sleep $fix_timer && $ds1 $panes3[0] $ds3' 2>&1 1> /dev/null");
		$time27 = TIME();
    } elseif (( $maxload >= get_load()) && ( $fix_names == 1 )) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		$log = writelog($panes3[0]);
		shell_exec("$_tmux respawnp -t${tmux_session}:3.0 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes3[0] $ds2 && cd $_test && $_php fixReleaseNames.php 3 true other yes show 2>&1 $log && $_php fixReleaseNames.php 5 true other yes show 2>&1 $log && $_php fixReleaseNames.php 7 true other yes show 2>&1 $log && echo \" \033[1;0;33m\" && $_sleep $fix_timer && $ds1 $panes3[0] $ds3' 2>&1 1> /dev/null");
        $time27 = TIME();
	} elseif ( $fix_names == 0 ) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		shell_exec("$_tmux respawnp -t${tmux_session}:3.0 'echo \"\033[38;5;\"$color\"m\n$panes3[0] Disabled by fixReleaseNames\" && date +\"%D %T\"' 2>&1 1> /dev/null");
	} elseif ( $maxload <= get_load()) {
                $color = get_color($colors_start, $colors_end, $colors_exc);
                shell_exec("$_tmux respawnp -t${tmux_session}:3.0 'echo \"\033[38;5;\"$color\"m\n$panes3[0] Disabled by MAX_LOAD\" && date +\"%D %T\"' 2>&1 1> /dev/null");
        }
    //run predb_hash_decrypt.php in pane 3.1
	if (($maxload >= get_load()) && (($dehash == 1) || ($dehash == 3))) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		$log = writelog($panes3[1]);
		shell_exec("$_tmux respawnp -t${tmux_session}:3.1 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes3[1] $ds2 && cd $_test && $_php predb_hash_decrypt.php 1000 2>&1 $log && echo \" \033[1;0;33m\" && $_sleep $dehash_timer && $ds1 $panes3[1] $ds3' 2>&1 1> /dev/null");
		$time28 = TIME();
	} else if (($maxload >= get_load()) && ($dehash == 2)) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		shell_exec("$_tmux respawnp -t${tmux_session}:3.1 'echo \"\033[38;5;\"$color\"m\n$panes3[1] Disabled by Dehash\" && date +\"%D %T\"' 2>&1 1> /dev/null");
	} elseif ( $maxload <= get_load()) {
                $color = get_color($colors_start, $colors_end, $colors_exc);
                shell_exec("$_tmux respawnp -t${tmux_session}:3.1 'echo \"\033[38;5;\"$color\"m\n$panes3[1] Disabled by MAX_LOAD\" && date +\"%D %T\"' 2>&1 1> /dev/null");
        }
        //run requestID or requestid threaded in pane 3.2
	if (( $maxload >= get_load()) && ( $array['REQID'] == "true" ) && ( $array['REQID_THREADED'] == "true" )) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		$log = writelog($panes3[2]);
		shell_exec("$_tmux respawnp -t${tmux_session}:3.2 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes3[2] $ds2 && cd $_test && $_python ${DIR}/../test/requestid_threaded.py 2>&1 $log && echo \" \033[1;0;33m\" && $_sleep {$array['REQID_TIMER']} && $ds1 $panes3[2] $ds3' 2>&1 1> /dev/null");
		$time30 = TIME();
    } elseif (( $maxload >= get_load()) && ( $array['REQID'] == "true" ) && ( $array['REQID_THREADED'] == "false" )) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		$log = writelog($panes3[2]);
		shell_exec("$_tmux respawnp -t${tmux_session}:3.2 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes3[2] $ds2 && cd $_test && $_php requestID.php 1000 true 2>&1 $log && echo \" \033[1;0;33m\" && $_sleep {$array['REQID_TIMER']} && $ds1 $panes3[2] $ds3' 2>&1 1> /dev/null");
		$time30 = TIME();
    } elseif ( $array['REQID'] != "true" ) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		shell_exec("$_tmux respawnp -t${tmux_session}:3.2 'echo \"\033[38;5;\"$color\"m\n$panes3[2] Disabled by RequestID\" && date +\"%D %T\"' 2>&1 1> /dev/null");
	} elseif ( $maxload <= get_load()) {
                $color = get_color($colors_start, $colors_end, $colors_exc);
                shell_exec("$_tmux respawnp -t${tmux_session}:3.2 'echo \"\033[38;5;\"$color\"m\n$panes3[2] Disabled by MAX_LOAD\" && date +\"%D %T\"' 2>&1 1> /dev/null");
        }
        //run removeCrap.php in pane 3.3
	if (($fix_crap_opt != "Disabled") && (($i == 1) || $fcfirstrun)) {
				$log = writelog($panes3[3]);
				if ($fix_crap_opt == 'All') {
					shell_exec("tmux respawnp -t${tmux_session}:3.3 ' \
						$_php ${DIR}/../test/removeCrapReleases.php true 2 $log; date +\"%D %T\"; $_sleep $crap_timer' 2>&1 1> /dev/null");
				} else {
					$fcmax = count($fix_crap);
					if (is_null($fcnum)) {
						$fcnum = 0;
					}
					//Check to see if the pane is dead, if so resawn it.
					if (shell_exec("tmux list-panes -t${tmux_session}:3 | grep ^1 | grep -c dead") == 1) {
						shell_exec("tmux respawnp -t${tmux_session}:3.3 ' \
							echo \"Running removeCrapReleases for $fix_crap[$fcnum]\"; \
							$_php ${DIR}/../test/removeCrapReleases.php true full $fix_crap[$fcnum] $log; date +\"%D %T\"; $_sleep $crap_timer' 2>&1 1> /dev/null");
						$fcnum++;
					}
					if ($fcnum == $fcmax) {
						$fcnum = 0;
						$fcfirstrun = false;
					}
				}
			} else if ($fix_crap_opt != 'Disabled') {
				$log = writelog($panes3[3]);
				if ($fix_crap_opt == 'All') {
					shell_exec("tmux respawnp -t${tmux_session}:3.3 ' \
						$_php ${DIR}/../test/removeCrapReleases.php true 2 $log; date +\"%D %T\"; $_sleep $crap_timer' 2>&1 1> /dev/null");
				} else {
					$fcmax = count($fix_crap);
					if (is_null($fcnum)) {
						$fcnum = 0;
					}
					//Check to see if the pane is dead, if so respawn it.
					if (shell_exec("tmux list-panes -t${tmux_session}:3 | grep ^1 | grep -c dead") == 1) {
						shell_exec("tmux respawnp -t${tmux_session}:3.3 ' \
							echo \"Running removeCrapReleases for $fix_crap[$fcnum]\"; \
							$_php ${DIR}/../test/removeCrapReleases.php true 2 $fix_crap[$fcnum] $log; date +\"%D %T\"; $_sleep $crap_timer' 2>&1 1> /dev/null");
						$fcnum++;
					}
					if ($fcnum == $fcmax) {
						$fcnum = 0;
					}
				}
			} else {
				$color = get_color($colors_start, $colors_end, $colors_exc);
				shell_exec("tmux respawnp -k -t${tmux_session}:3.3 'echo \"\033[38;5;${color}m\n${panes3[3]} has been disabled/terminated by Remove Crap Releases\"'");
			}

    //run postprocess_pre.php in pane 3.4
	if (($maxload >= get_load()) && (($dehash == 2) || ($dehash == 3))) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		$log = writelog($panes3[4]);
		shell_exec("$_tmux respawnp -t${tmux_session}:3.4 'echo \"\033[38;5;\"$color\"m\" && $ds1 $panes3[4] $ds2 && cd $_test && $_php postprocess_pre.php 2>&1 $log && echo \" \033[1;0;33m\" && $_sleep $dehash_timer && $ds1 $panes3[4] $ds3' 2>&1 1> /dev/null");
		$time32 = TIME();}
	  else if (($maxload >= get_load()) && ($dehash == 1)) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		shell_exec("$_tmux respawnp -t${tmux_session}:3.4 'echo \"\033[38;5;\"$color\"m\n$panes3[4] Disabled by Dehash\" && date +\"%D %T\"' 2>&1 1> /dev/null");
	} else if ( $maxload <= get_load()) {
                $color = get_color($colors_start, $colors_end, $colors_exc);
                shell_exec("$_tmux respawnp -t${tmux_session}:3.4 'echo \"\033[38;5;\"$color\"m\n$panes3[4] Disabled by MAX_LOAD\" && date +\"%D %T\"' 2>&1 1> /dev/null");
        }
} else if ($seq == 0) {
		for ($g = 1; $g <= 5; $g++) {
			$color = get_color($colors_start, $colors_end, $colors_exc);
			shell_exec("tmux respawnp -k -t${tmux_session}:0.$g 'echo \"\033[38;5;${color}m\n${panes0[$g]} has been disabled/terminated by Running\"'");
		}
		for ($g = 0; $g <= 6; $g++) {
			$color = get_color($colors_start, $colors_end, $colors_exc);
			shell_exec("tmux respawnp -k -t${tmux_session}:1.$g 'echo \"\033[38;5;${color}m\n${panes1[$g]} has been disabled/terminated by Running\"'");
		}
		for ($g = 0; $g <= 10; $g++) {
			$color = get_color($colors_start, $colors_end, $colors_exc);
			shell_exec("tmux respawnp -k -t${tmux_session}:2.$g 'echo \"\033[38;5;${color}m\n${panes2[$g]} has been disabled/terminated by Running\"'");
		}for ($g = 0; $g <= 5; $g++) {
			$color = get_color($colors_start, $colors_end, $colors_exc);
			shell_exec("tmux respawnp -k -t${tmux_session}:3.$g 'echo \"\033[38;5;${color}m\n${panes3[$g]} has been disabled/terminated by Running\"'");
		}
	} else if ($seq == 1) {
		for ($g = 1; $g <= 5; $g++) {
			$color = get_color($colors_start, $colors_end, $colors_exc);
			shell_exec("tmux respawnp -k -t${tmux_session}:0.$g 'echo \"\033[38;5;${color}m\n${panes0[$g]} has been disabled/terminated by Running\"'");
		}
		for ($g = 0; $g <= 6; $g++) {
			$color = get_color($colors_start, $colors_end, $colors_exc);
			shell_exec("tmux respawnp -k -t${tmux_session}:1.$g 'echo \"\033[38;5;${color}m\n${panes1[$g]} has been disabled/terminated by Running\"'");
		}
		for ($g = 0; $g <= 10; $g++) {
			$color = get_color($colors_start, $colors_end, $colors_exc);
			shell_exec("tmux respawnp -k -t${tmux_session}:2.$g 'echo \"\033[38;5;${color}m\n${panes2[$g]} has been disabled/terminated by Running\"'");
		}for ($g = 0; $g <= 5; $g++) {
			$color = get_color($colors_start, $colors_end, $colors_exc);
			shell_exec("tmux respawnp -k -t${tmux_session}:3.$g 'echo \"\033[38;5;${color}m\n${panes3[$g]} has been disabled/terminated by Running\"'");
		}
	} else if ($seq == 2) {
		for ($g = 1; $g <= 5; $g++) {
			$color = get_color($colors_start, $colors_end, $colors_exc);
			shell_exec("tmux respawnp -k -t${tmux_session}:0.$g 'echo \"\033[38;5;${color}m\n${panes0[$g]} has been disabled/terminated by Running\"'");
		}
		for ($g = 0; $g <= 6; $g++) {
			$color = get_color($colors_start, $colors_end, $colors_exc);
			shell_exec("tmux respawnp -k -t${tmux_session}:1.$g 'echo \"\033[38;5;${color}m\n${panes1[$g]} has been disabled/terminated by Running\"'");
		}
		for ($g = 0; $g <= 10; $g++) {
			$color = get_color($colors_start, $colors_end, $colors_exc);
			shell_exec("tmux respawnp -k -t${tmux_session}:2.$g 'echo \"\033[38;5;${color}m\n${panes2[$g]} has been disabled/terminated by Running\"'");
		}for ($g = 0; $g <= 5; $g++) {
			$color = get_color($colors_start, $colors_end, $colors_exc);
			shell_exec("tmux respawnp -k -t${tmux_session}:3.$g 'echo \"\033[38;5;${color}m\n${panes3[$g]} has been disabled/terminated by Running\"'");
		}
	}

	$i++;
	sleep(10);
}

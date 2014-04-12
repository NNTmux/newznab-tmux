<?php

require_once(dirname(__FILE__)."/config.php");
require(WWW_DIR.'/lib/postprocess.php');
require_once (WWW_DIR.'/lib/site.php');
require_once(WWW_DIR.'/lib/Tmux.php');
require_once(dirname(__FILE__)."/../lib/ColorCLI.php");
require_once(dirname(__FILE__)."/../lib/showsleep.php");
require_once(dirname(__FILE__)."/../lib/functions.php");


$version="0.3r1089";

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
$tpatch = $tmux->sqlpatch;
$scrape_cz = $tmux->scrape_cz;
$scrape_efnet = $tmux->scrape_efnet;


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
( SELECT UNIX_TIMESTAMP(predate) from prehash order by predate DESC limit 1 ) AS newestprehash,
( SELECT UNIX_TIMESTAMP(updatedate) from predb order by updatedate DESC limit 1 ) AS newestpredb,
( SELECT TABLE_ROWS FROM INFORMATION_SCHEMA.TABLES WHERE table_name = 'prehash' AND TABLE_SCHEMA = '".DB_NAME."' ) AS prehash,
( SELECT name from releases order by adddate desc limit 1 ) AS newestaddname";
//$proc = "SELECT * FROM procCnt;";
$proc2 = "SELECT
	(SELECT COUNT(*) FROM releases WHERE isrenamed = 0 AND isrequestid = 1 AND reqidstatus in (0, -1) OR (reqidstatus = -3 AND adddate > NOW() - INTERVAL 2 HOUR)) AS requestid_inprogress,
	(SELECT COUNT(*) FROM releases USE INDEX(ix_releases_status) WHERE reqidstatus = 1 OR reqID IS NOT NULL) AS requestid_matched,
	(SELECT COUNT(*) FROM releases USE INDEX(ix_releases_status) WHERE prehashID IS NOT NULL) AS prehash_matched,
	(SELECT COUNT(DISTINCT(prehashID)) FROM releases WHERE prehashID > 0) AS distinct_prehash_matched";

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
    . "(SELECT VALUE FROM tmux WHERE SETTING = 'predb') AS predb, "
    . "(SELECT VALUE FROM tmux WHERE SETTING = 'predb_timer') AS predb_timer, "
    . "(SELECT VALUE FROM tmux WHERE SETTING = 'spotnab') AS spotnab, "
    . "(SELECT VALUE FROM tmux WHERE SETTING = 'spotnab_timer') AS spotnab_timer, "
    . "(SELECT VALUE FROM tmux WHERE SETTING = 'others') AS others, "
    . "(SELECT VALUE FROM tmux WHERE SETTING = 'sharing_timer') AS sharing_timer, "
    . "(SELECT VALUE FROM tmux WHERE SETTING = 'sphinx') AS sphinx, "
    . "(SELECT VALUE FROM tmux WHERE SETTING = 'sphinx_timer') AS sphinx_timer, "
    . "(SELECT VALUE FROM tmux WHERE SETTING = 'unwanted') AS unwanted, "
    . "(SELECT VALUE FROM tmux WHERE SETTING = 'fetch_movie') AS fetch_movie, "
    . "(SELECT VALUE FROM tmux WHERE SETTING = 'movie_timer') AS movie_timer, "
    . "(SELECT VALUE FROM tmux WHERE SETTING = 'showquery') AS show_query, "
    . "(SELECT VALUE FROM tmux WHERE SETTING = 'lookup_reqids') as lookup_reqids, "
    . "(SELECT VALUE FROM tmux WHERE SETTING = 'lookup_reqids_timer') as lookup_reqids_timer, "
    . "(SELECT VALUE FROM site WHERE SETTING = 'lookupbooks') as processbooks, "
	. "(SELECT VALUE FROM site WHERE SETTING = 'lookupmusic') as processmusic, "
	. "(SELECT VALUE FROM site WHERE SETTING = 'lookupgames') as processgames, "
	. "(SELECT VALUE FROM site WHERE SETTING = 'tmpunrarpath') as tmpunrar";


$split_query = "SELECT
    ( SELECT UNIX_TIMESTAMP(adddate) FROM releases USE INDEX(ix_releases_status) ORDER BY adddate DESC LIMIT 1 ) AS newestadd";
$split_query = "SELECT "
		. "(SELECT COUNT(*) FROM groups WHERE first_record IS NOT NULL AND (now() - interval backfill_target day) < first_record_postdate) AS backfill_groups_days, "
		. "(SELECT COUNT(*) FROM groups WHERE first_record IS NOT NULL AND (now() - interval datediff(curdate(), "
		. "(SELECT VALUE FROM tmux WHERE SETTING = 'safebackfilldate')) day) < first_record_postdate) AS backfill_groups_date, "
        . "(SELECT UNIX_TIMESTAMP(adddate) FROM releases USE INDEX(ix_releases_status) ORDER BY adddate DESC LIMIT 1 ) AS newestadd";

//get first release inserted datetime and oldest posted datetime
//$posted_date = "SELECT(SELECT UNIX_TIMESTAMP(adddate) from releases order by adddate asc limit 1) AS adddate;";


//environment
$_nice = getenv('NICE');
$_sed = getenv('SED');
$_tee = getenv('TEE');
$_mysqladmin = getenv('MYSQLADMIN');
$_count_releases = 0;
$_bin = dirname(__FILE__)."/../bin";
$_conf = dirname(__FILE__)."/../conf";
$_powerline = dirname(__FILE__)."/../powerline";
$_cj = dirname(__FILE__)."/../nnscripts";
$_lib = dirname (__FILE__)."/../lib";
$_py = dirname (__FILE__)."/../python";
$_user = dirname(__FILE__)."/../user_scripts";



$NNPATH="/var/www/newznab/misc/update_scripts/";
$TESTING="/var/www/newznab/misc/testing/";
$killed = "false";
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

function writelog($pane)
{
	$path = dirname(__FILE__) . "/../logs";
	$getdate = gmDate("Ymd");
	$t = new Tmux();
	$tmux = $t->get();
	$logs = (isset($tmux->write_logs)) ? $tmux->write_logs : 0;
	if ($logs == 1) {
		return "2>&1 | tee -a $path/$pane-$getdate.log";
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
$predb = 0;
$fix_names = 0;
$lookreqids = 0;
$monitor_path = "";
$backfilldays = 0;
$backfill_groups_date = 0;
$colors_exc = 0;
$show_query = 0;
$run_releases = 0;
$tmux_time = $split_time = $init_time = $proc1_time = $proc2_time = $proc3_time = $split1_time = 0;
$init1_time = $proc11_time = $proc21_time = $proc31_time = $tpg_count_time = $tpg_count_1_time = 0;

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

// Ananlyze tables
printf($c->info("\nAnalyzing your tables to refresh your indexes."));
$functions->optimise(true, 'analyze');

sleep (5);

//create initial display, USP connection count, prehash count and groups count adapted from nZEDb
passthru('clear');
//printf("\033[1;31m  First insert:\033[0m ".relativeTime("$firstdate")."\n");
printf($mask2, "Monitor Running v$version [". $tpatch ."][" . $patch . "]: ", relativeTime("$time"));
printf($mask1, "USP Connections:", $uspactiveconnections . " active (" . $usptotalconnections . " total) - " . $host . ":" . $port);;
printf($mask1, "Newest Release:", "$newestname");
printf($mask1, "Release Added:", relativeTime("$newestadd")."ago");
if ($predb == 1){
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
if ($monitor_path != "" ) {
	printf($mask4, "Ramdisk", "$disk_use", "$disk_free");
}
printf($mask3, "Category", "In Process", "In Database");
printf($mask3, "====================", "====================", "====================");
if ($fix_names == 1){
printf($mask4, "prehash", number_format($prehash - $distinct_prehash_matched) . "(" . $pre_diff . ")", number_format($prehash_matched) . "(" . $pre_percent . "%)");
}
if (($lookreqids == 1) || ($lookreqids == 2)){
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

printf($mask3, "Groups", "Active", "Backfill");
printf($mask3, "====================", "=========================", "=========================");
if ($backfilldays == "1") {
	printf($mask4, "Activated", $active_groups . "(" . $all_groups . ")", $backfill_groups_days . "(" . $all_groups . ")");
} else {
	printf($mask4, "Activated", $active_groups . "(" . $all_groups . ")", $backfill_groups_date . "(" . $all_groups . ")");
}
echo "\n";
if ($show_query == 1) {
	printf($mask3, "Query Block", "Time", "Cumulative");
	printf($mask3, "======================================", "=========================", "======================================");
}

$i = 1;
$monitor = 30;
$time33 = TIME();
$fcfirstrun = true;
$fcnum = 0;

while( $i > 0 )
{
     //kill mediainfo and ffmpeg if exceeds 60 sec
	shell_exec("killall -o 60s -9 mediainfo 2>&1 1> /dev/null");
	shell_exec("killall -o 60s -9 ffmpeg 2>&1 1> /dev/null");

	//get microtime at start of loop
	$time_loop_start = microtime_float();

	$getdate = gmDate("Ymd");


	//reload tmux.conf
	if ($powerline == 1) {
		shell_exec("tmux source-file $_powerline/tmux.conf");
	} else {
		shell_exec("tmux source-file $_conf/tmux.conf");
	}

	//run queries
    $time01 = TIME();
    $proc_tmux_result = $db->query($proc_tmux, false);
    $tmux_time = (TIME() - $time01);
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
    if ( @$proc_result[0]['prehash'] != NULL ) { $prehash = $proc_result[0]['prehash'];
         		$nowTime = time();
		    if ($prehash > $nowTime) {
			    $prehash = $nowTime;
		        }
        }
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
    if ($proc_tmux_result[0]['predb'] != NULL) {
		$predb = $proc_tmux_result[0]['predb'];
	}
    if ($proc_tmux_result[0]['predb_timer'] != NULL) {
		$predb_timer = $proc_tmux_result[0]['predb_timer'];
	}
    if ($proc_tmux_result[0]['spotnab'] != NULL) {
		$spotnab = $proc_tmux_result[0]['spotnab'];
	}
    if ($proc_tmux_result[0]['spotnab_timer'] != NULL) {
		$spotnab_timer = $proc_tmux_result[0]['spotnab_timer'];
	}
    if ($proc_tmux_result[0]['others'] != NULL) {
		$others = $proc_tmux_result[0]['others'];
	}
    if ($proc_tmux_result[0]['unwanted'] != NULL) {
		$unwanted = $proc_tmux_result[0]['unwanted'];
	}
    if ($proc_tmux_result[0]['fetch_movie'] != NULL) {
		$fetch_movie = $proc_tmux_result[0]['fetch_movie'];
	}
    if ($proc_tmux_result[0]['movie_timer'] != NULL) {
		$movie_timer = $proc_tmux_result[0]['movie_timer'];
	}
    if ($proc_tmux_result[0]['lookup_reqids'] != NULL) {
		$lookreqids = $proc_tmux_result[0]['lookup_reqids'];
	}
    if ($proc_tmux_result[0]['lookup_reqids_timer'] != NULL) {
		$lookreqids_timer = $proc_tmux_result[0]['lookup_reqids_timer'];
	}
    if ($proc_tmux_result[0]['seq_timer'] != NULL) {
		$seq_timer = $proc_tmux_result[0]['seq_timer'];
	}
    if ($proc_tmux_result[0]['sphinx'] != NULL) {
		$sphinx = $proc_tmux_result[0]['sphinx'];
	}
    if ($proc_tmux_result[0]['sphinx_timer'] != NULL) {
		$sphinx_timer = $proc_tmux_result[0]['sphinx_timer'];
	}
    if ($proc_tmux_result[0]['show_query'] != NULL) {
		$show_query = $proc_tmux_result[0]['show_query'];
	}
    if ($proc_tmux_result[0]['sharing_timer'] != NULL) {
		$sharing_timer = $proc_tmux_result[0]['sharing_timer'];
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
    if ($split_result[0]['backfill_groups_days'] != NULL) {
		$backfill_groups_days = $split_result[0]['backfill_groups_days'];
	}
	if ($split_result[0]['backfill_groups_date'] != NULL) {
		$backfill_groups_date = $split_result[0]['backfill_groups_date'];
	}

$old_session = "$tmux_session";

    //rename the session
	if ( $old_session != $tmux_session ) {
		shell_exec("tmux rename-session -t $old_session $tmux_session");
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

	if ( $monitor_path != "" ) {
		$disk_use =  decodeSize( disk_total_space($monitor_path) - disk_free_space($monitor_path) );
		$disk_free = decodeSize( disk_free_space($monitor_path) );
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


	$total_work_used = $total_work_now;
     if (($postprocess_kill != 0) && ($postprocess_kill < $total_work_now)  ) {
		$kill_pp = "true";
	} else {
		$kill_pp = "false";
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
	$killed = "false";
	if ( $post_kill_timer != 0 ) {
		if ((( $killit[1] != "secs" ) && ( $killit[1] != "sec" )) && (( $killit[1] == "hrs" ) || ( $killit[1] == "hr" ) || ( $killit[0] >= $post_kill_timer) && ( $i % 5 == 0 ))) {
			$color = get_color($colors_start, $colors_end, $colors_exc);
			shell_exec("tmux respawnp -k -t${tmux_session}:0.2 'echo \"\033[38;5;\"$color\"m\n$panes0[2] Killed by Postprocess Kill Timer\"'");
			$color = get_color($colors_start, $colors_end, $colors_exc);
			shell_exec("tmux respawnp -k -t${tmux_session}:0.3 'echo \"\033[38;5;\"$color\"m\n$panes0[3] Killed by Postprocess Kill Timer\"'");
			$color = get_color($colors_start, $colors_end, $colors_exc);
			shell_exec("tmux respawnp -k -t${tmux_session}:0.4 'echo \"\033[38;5;\"$color\"m\n$panes0[4] Killed by Postprocess Kill Timer\"'");
		}
	}

	//get state for binaries
	if ( $binaries == 0 ) {
		$binaries_state="disabled";
		$binaries_reason="disabled";
        } elseif ( $maxload <= get_load()) {
                $binaries_state="load-exceeded";
                $binaries_reason="$maxload/".get_load();
	} else if ($kill_pp == "true") {
		$binaries_state="pp-exceeded";
		$binaries_reason="pp-exceeded";
	} else if ($killed == "true") {
		$binaries_state="time-exceeded";
		$binaries_reason=$post_kill_timer." minutes";
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
        } else if ( $maxload <= get_load()) {
                $backfill_state="load-exceeded";
                $backfill_reason="$maxload/".get_load();
	} elseif ($kill_pp == "true" ) {
		$backfill_state="pp-exceeded";
		$backfill_reason="pp-exceeded";
	} elseif ($killed == "true") {
		$backfill_state="time-exceeded";
		$backfill_reason=$post_kill_timer." minutes";
        } else {
		$backfill_state="enabled";
		$backfill_reason="enabled";
	}

	//get state for import
	if ( $import == 0 ) {
		$import_state="disabled";
		$import_reason="disabled";
        } else if ( $maxload <= get_load()) {
                $import_state="load-exceeded";
                $import_reason="$maxload/".get_load();
	} else if ($kill_pp == "true") {
		$import_state="pp-exceeded";
		$import_reason="pp-exceeded";
	} else if ($kill_pp == "true") {
		$import_state="rows-exceeded";
		$import_reason="rows-exceeded";
	} else if ( $kill_pp == "true") {
		$import_state="limits-exceeded";
		$import_reason="limits-exceeded";
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
	printf($mask2, "Monitor Running v$version [". $tpatch ."][" . $patch . "]: ", relativeTime("$time"));
    printf($mask1, "USP Connections:", $uspactiveconnections . " active (" . $usptotalconnections . " total) - " . $host . ":" . $port);
	printf($mask1, "Newest Release:", "$newestname");
	printf($mask1, "Release Added:", relativeTime("$newestadd")."ago");
    if ($predb == 1){
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
	if ($monitor_path != "") {
		printf($mask4, "Ramdisk", "$disk_use used", "$disk_free free");
	}
	printf($mask3, "Category", "In Process", "In Database");
	printf($mask3, "====================", "====================", "====================");
    if ($fix_names == 1){
    printf($mask4, "prehash", number_format($prehash - $distinct_prehash_matched) . "(" . $pre_diff . ")", number_format($prehash_matched) . "(" . $pre_percent . "%)");
    }
    if (($lookreqids == 1) || ($lookreqids == 2)){
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

    printf($mask3, "Groups", "Active", "Backfill");
    printf($mask3, "====================", "=========================", "=========================");
    if ($backfilldays == "1") {
		printf($mask4, "Activated", $active_groups . "(" . $all_groups . ")", $backfill_groups_days . "(" . $all_groups . ")");
	} else {
		printf($mask4, "Activated", $active_groups . "(" . $all_groups . ")", $backfill_groups_date . "(" . $all_groups . ")");
	}
    if ($show_query == 1) {
		echo "\n";
		printf($mask3, "Query Block", "Time", "Cumulative");
		printf($mask3, "======================================", "=========================", "======================================");

		$pieces = explode(" ", $db->getAttribute(PDO::ATTR_SERVER_INFO));
		echo $c->primaryOver("\nThreads = ") . $c->headerOver($pieces[4]) . $c->primaryOver(', Opens ') . $c->headerOver($pieces[14]) . $c->primaryOver(', Tables = ') . $c->headerOver($pieces[22]) . $c->primaryOver(', Slow = ') . $c->headerOver($pieces[11]) . $c->primaryOver(', QPS = ') . $c->header($pieces[28]);
	}

if ($post_non == 2) {
		$clean = ' clean ';
	} else {
		$clean = ' ';
	}

$show_time = "";
$_php = $show_time . " nice -n$niceness $PHP";
$_phpn = "nice -n$niceness $PHP";
$_python = $show_time . " nice -n$niceness $PYTHON";
$_pythonn = "nice -n$niceness $PYTHON";
$_sleep = "$_phpn ${DIR}/../lib/showsleep.php";

	//set command for running update_binaries
	if ($binaries == 1) {
		$log = writelog($panes0[2]);
		$_update_cmd = "cd $_bin && $_php update_binaries.php 2>&1 $log";
	} else if ($binaries == 2)  {
		$log = writelog($panes0[2]);
		$_update_cmd = "$_python ${DIR}/../python/binaries_threaded.py 2>&1 $log";
	} else if ($binaries == 3)  {
		$log = writelog($panes0[2]);
		$_update_cmd = "$_python ${DIR}/../python/binaries_safe_threaded.py 2>&1 $log";
    }

if ($releases_run != 0) {
			$run_releases = "$_python ${DIR}/../python/releases_threaded.py";
		}



if ($running == 1){
	//check if sequential is set
	if ($seq == 0) {
	    if ($kill_pp == "false") {
		//runs update_binaries in 0.2 once if needed and exits
		if (( $maxload >= get_load()) && ($killed != "true") && ($binaries != 0)) {
			$color = get_color($colors_start, $colors_end, $colors_exc);
			$log = writelog($panes0[2]);
			shell_exec("tmux respawnp -t${tmux_session}:0.2 'echo \"\033[38;5;\"$color\"m\" && $_update_cmd 2>&1 $log; $_sleep $bins_timer' 2>&1 1> /dev/null");
		} else if (( $binaries != 0  ) && ( $maxload <= get_load())) {
                        $color = get_color($colors_start, $colors_end, $colors_exc);
                        shell_exec("tmux respawnp -t${tmux_session}:0.2 'echo \"\033[38;5;\"$color\"m\n$panes0[2] Disabled by Max Load\"' 2>&1 1> /dev/null");
        } else if ($binaries == 0)  {
			$color = get_color($colors_start, $colors_end, $colors_exc);
			shell_exec("tmux respawnp -t${tmux_session}:0.2 'echo \"\033[38;5;\"$color\"m\n$panes0[2] has been disabled/terminated by Binaries\"'");
		    }
    } else if ($kill_pp == "true") {
                $color = get_color($colors_start, $colors_end, $colors_exc);
				shell_exec("tmux respawnp -t${tmux_session}:0.2 'echo \"\033[38;5;${color}m\"; \
					echo \"\nbinaries have been disabled/terminated by Exceeding Limits\"; \
					$_update_cmd $log; date +\"%D %T\"; echo \"\nbinaries have been disabled/terminated by Exceeding Limits\"; $_sleep $bins_timer' 2>&1 1> /dev/null");
    }

		//runs backfill in 0.3
        $backsleep = $back_timer;
		if (($backfill == 4) && ($kill_pp == "false") && (TIME() - $time6 <= 4800)) {
				$log = writelog($panes0[3]);
				shell_exec("tmux respawnp -t${tmux_session}:0.3 ' \
						$_python ${DIR}/../python/backfill_safe_threaded.py $log; date +\"%D %T\"; $_sleep $backsleep' 2>&1 1> /dev/null");
			} else if (($backfill != 0) && ($kill_pp == "false") && (TIME() - $time6 <= 4800)) {
				$log = writelog($panes0[3]);
				shell_exec("tmux respawnp -t${tmux_session}:0.3 ' \
						$_python ${DIR}/../python/backfill_threaded.py group $log; date +\"%D %T\"; $_sleep $backsleep' 2>&1 1> /dev/null");
			} else if (($backfill != 0) && ($kill_pp == "false") && (TIME() - $time6 >= 4800)) {
				$log = writelog($panes0[3]);
				shell_exec("tmux respawnp -k -t${tmux_session}:0.3 ' \
						$_python ${DIR}/../python/backfill_threaded.py all $log; date +\"%D %T\"; $_sleep $backsleep' 2>&1 1> /dev/null");
				$time6 = TIME();
			} else if ($kill_pp == "true") {
				$color = get_color($colors_start, $colors_end, $colors_exc);
				shell_exec("tmux respawnp -k -t${tmux_session}:0.3 'echo \"\033[38;5;${color}m\n${panes0[3]} has been disabled/terminated by Exceeding Limits\"'");
			} else {
				$color = get_color($colors_start, $colors_end, $colors_exc);
				shell_exec("tmux respawnp -k -t${tmux_session}:0.3 'echo \"\033[38;5;${color}m\n${panes0[3]} has been disabled/terminated by Backfill\"'");
			}

    }

	//run update_binaries, backfill and import using seq in pane 0.2
	$dead = `tmux list-panes -t${tmux_session}:0 | grep 2: | grep dead`;
	if (($seq == 1 ) && ( strlen( $dead ) > "4" )) {
			//run nzb-import
			if (($import != 0) && ($kill_pp == "false")) {
				$log = writelog($panes0[2]);
				shell_exec("tmux respawnp -t${tmux_session}:0.2 ' \
						$_python ${DIR}/../python/import_threaded.py $log; date +\"%D %T\"; $_sleep $import_timer' 2>&1 1> /dev/null");
			} else {
				$color = get_color($colors_start, $colors_end, $colors_exc);
				shell_exec("tmux respawnp -k -t${tmux_session}:0.2 'echo \"\033[38;5;${color}m\n${panes0[1]} has been disabled/terminated by Import\"'");
			}

			//run update_binaries
			$log = writelog($panes0[2]);
			if (($kill_pp == "false") && (TIME() - $time6 <= 4800)) {
				//runs all/safe less than 4800
				if (($binaries != 0) && ($backfill == 4) && ($releases_run != 0)) {
					shell_exec("tmux respawnp -t${tmux_session}:0.2 ' \
							$_update_cmd $log; \
							$_python ${DIR}/../python/backfill_safe_threaded.py $log; \
							$run_releases $log; date +\"%D %T\"; $_sleep $seq_timer' 2>&1 1> /dev/null");
				}
				//runs all less than 4800
				else if (($binaries != 0) && ($backfill != 0) && ($releases_run != 0)) {
					shell_exec("tmux respawnp -t${tmux_session}:0.2 ' \
							$_update_cmd $log; \
							$_python ${DIR}/../python/backfill_threaded.py $log; \
							$run_releases $log; date +\"%D %T\"; $_sleep $seq_timer' 2>&1 1> /dev/null");
				}
				//runs bin/back/safe less than 4800
				else if (($binaries != 0) && ($backfill == 4) && ($releases_run == 0)) {
					shell_exec("tmux respawnp -t${tmux_session}:0.2 ' \
							$_update_cmd $log; \
							$_python ${DIR}/../python/backfill_safe_threaded.py $log; date +\"%D %T\"; \
							echo \"\nreleases has been disabled/terminated by Releases\"; $_sleep $seq_timer' 2>&1 1> /dev/null");
				}
				//runs bin/back less than 4800
				else if (($binaries != 0) && ($backfill != 0) && ($releases_run == 0)) {
					shell_exec("tmux respawnp -t${tmux_session}:0.2 ' \
							$_update_cmd $log; \
							$_python ${DIR}/../python/backfill_threaded.py $log; date +\"%D %T\"; echo \"\nreleases have been disabled/terminated by Releases\"; $_sleep $seq_timer' 2>&1 1> /dev/null");
				}
				//runs back/safe/rel less than 4800
				else if (($binaries == 0) && ($backfill == 4) && ($releases_run != 0)) {
					shell_exec("tmux respawnp -t${tmux_session}:0.2 ' \
							$_python ${DIR}/../python/backfill_safe_threaded.py $log; \
							$run_releases $log; date +\"%D %T\"; echo \"\nbinaries has been disabled/terminated by Binaries\"; $_sleep $seq_timer' 2>&1 1> /dev/null");
				}
				//runs back/rel less than 4800
				else if (($binaries == 0) && ($backfill != 0) && ($releases_run != 0)) {
					shell_exec("tmux respawnp -t${tmux_session}:0.2 ' \
							$_python ${DIR}/../python/backfill_threaded.py $log; \
							$run_releases $log; date +\"%D %T\"; echo \"\nbinaries has been disabled/terminated by Binaries\"; $_sleep $seq_timer' 2>&1 1> /dev/null");
				}
				//runs bin/rel less than 4800
				else if (($binaries != 0) && ($backfill == 0) && ($releases_run != 0)) {
					shell_exec("tmux respawnp -t${tmux_session}:0.2 ' \
							$_update_cmd $log; \
							$run_releases $log; date +\"%D %T\"; echo \"\nbackfill has been disabled/terminated by Backfill\"; $_sleep $seq_timer' 2>&1 1> /dev/null");
				}
				//runs bin less than 4800
				else if (($binaries != 0) && ($backfill == 0) && ($releases_run == 0)) {
					shell_exec("tmux respawnp -t${tmux_session}:0.2 ' \
							$_update_cmd $log; date +\"%D %T\"; echo \"\nbackfill and releases have been disabled/terminated by Backfill and Releases\"; $_sleep $seq_timer' 2>&1 1> /dev/null");
				}
				//runs back/safe less than 4800
				else if (($binaries == 0) && ($backfill == 4) && ($releases_run == 0)) {
					shell_exec("tmux respawnp -t${tmux_session}:0.2 ' \
							$_python ${DIR}/../python/backfill_safe_threaded.py $log; date +\"%D %T\"; echo \"\nbinaries and releases have been disabled/terminated by Binaries and Releases\"; $_sleep $seq_timer' 2>&1 1> /dev/null");
				}
				//runs back less than 4800
				else if (($binaries == 0) && ($backfill == 4) && ($releases_run == 0)) {
					shell_exec("tmux respawnp -t${tmux_session}:0.2 ' \
							$_python ${DIR}/../python/backfill_threaded.py $log; date +\"%D %T\"; echo \"\nbinaries and releases have been disabled/terminated by Binaries and Releases\"; $_sleep $seq_timer' 2>&1 1> /dev/null");
				}
				//runs rel less than 4800
				else if (($binaries == 0) && ($backfill == 0) && ($releases_run != 0)) {
					shell_exec("tmux respawnp -t${tmux_session}:0.2 ' \
							$run_releases $log; date +\"%D %T\"; echo \"\nbinaries and backfill has been disabled/terminated by Binaries and Backfill\"; $_sleep $seq_timer' 2>&1 1> /dev/null");
				} else if (($binaries == 0) && ($backfill == 0) && ($releases_run == 0)) {
					shell_exec("tmux respawnp -t${tmux_session}:0.2 ' \
							echo \"\nbinaries, backfill and releases have been disabled/terminated by Binaries, Backfill and Releases\"; $_sleep $seq_timer' 2>&1 1> /dev/null");
				}
			} else if (($kill_pp == "false") && (TIME() - $time6 >= 4800)) {
				//run backfill all once and resets the timer
				if ($backfill != 0) {
					shell_exec("tmux respawnp -k -t${tmux_session}:0.2 ' \
						$_python ${DIR}/../python/backfill_threaded.py all $log; date +\"%D %T\"; $_sleep $seq_timer' 2>&1 1> /dev/null");
					$time6 = TIME();
				}
				$time6 = TIME();
			} else if ((($kill_pp == "true")) && ($releases_run != 0)) {
				$color = get_color($colors_start, $colors_end, $colors_exc);
				shell_exec("tmux respawnp -t${tmux_session}:0.2 'echo \"\033[38;5;${color}m\"; \
					echo \"\nbinaries and backfill has been disabled/terminated by Exceeding Limits\"; \
					$run_releases $log; date +\"%D %T\"; echo \"\nbinaries and backfill has been disabled/terminated by Exceeding Limits\"; $_sleep $seq_timer' 2>&1 1> /dev/null");
			} else if ($kill_pp == "true") {
				$color = get_color($colors_start, $colors_end, $colors_exc);
				shell_exec("tmux respawnp -t${tmux_session}:0.2 'echo \"\033[38;5;${color}m\n${panes0[2]} has been disabled/terminated by Exceeding Limits\"'");
			}
		}

	//runs nzb-import in 0.4
	if (($import != 0) && ($kill_pp == "false")) {
				$log = writelog($panes0[4]);
				shell_exec("tmux respawnp -t${tmux_session}:0.4 ' \
						$_python ${DIR}/../python/import_threaded.py $log; date +\"%D %T\"; $_sleep $import_timer' 2>&1 1> /dev/null");
			} else if (( $import == 1 ) && ( $maxload <= get_load())) {
                $color = get_color($colors_start, $colors_end, $colors_exc);
                shell_exec("tmux respawnp -t${tmux_session}:0.4 'echo \"\033[38;5;\"$color\"m\n$panes0[4] Disabled by Max Load\"' 2>&1 1> /dev/null");
            } else if ($kill_pp == "true") {
				$color = get_color($colors_start, $colors_end, $colors_exc);
				shell_exec("tmux respawnp -k -t${tmux_session}:0.4 'echo \"\033[38;5;${color}m\n${panes0[4]} has been disabled/terminated by Exceeding Limits\"'");
			}  else {
				$color = get_color($colors_start, $colors_end, $colors_exc);
				shell_exec("tmux respawnp -k -t${tmux_session}:0.4 'echo \"\033[38;5;${color}m\n${panes0[4]} has been disabled/terminated by Import\"'");
			}

	//runs nzbcount in 1.5 loops
	if (( $maxload >= get_load()) && ( $import == 1 )) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		$log = writelog($panes1[5]);
		shell_exec("tmux respawnp -t${tmux_session}:1.5 'echo \"\033[38;5;\"$color\"m\" && cd $_bin && $_php nzbcount.php 2>&1 $log' 2>&1 1> /dev/null");
	} else if ( $import == 0 ) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		shell_exec("tmux respawnp -t${tmux_session}:1.5 'echo \"\033[38;5;\"$color\"m\n$panes1[5] has been disabled/terminated by Import\"'");
	} else if ( $maxload <= get_load()) {
                $color = get_color($colors_start, $colors_end, $colors_exc);
                shell_exec("tmux respawnp -t${tmux_session}:1.5 'echo \"\033[38;5;\"$color\"m\n$panes1[5] Disabled by Max Load\"' 2>&1 1> /dev/null");
        }

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
                        rm -rf $tmpunrar/*; \
						$_python ${DIR}/../python/postprocess_threaded.py additional $log; date +\"%D %T\"; $_sleep $post_timer' 2>&1 1> /dev/null");
		} else if (($post == 2) && ($nfo_remaining_now > 0)) {
				$log = writelog($panes0[1]);
				shell_exec("tmux respawnp -t${tmux_session}:0.1 ' \
                        rm -rf $tmpunrar/*; \
						$_python ${DIR}/../python/postprocess_threaded.py nfo $log;\
                        $_php ${DIR}/../bin/postprocess_new.php nfo $log; date +\"%D %T\"; $_sleep $post_timer' 2>&1 1> /dev/null");
        } else if (($post == 3) && (($nfo_remaining_now > 0) || ($work_remaining_now + $pc_releases_proc + $xxx_releases_proc > 0))) {
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
                        rm -rf $tmpunrar/*; \
						$_python ${DIR}/../python/postprocess_threaded.py additional $log;\
                        $_python ${DIR}/../python/postprocess_threaded.py nfo $log;\
                        $_php ${DIR}/../bin/postprocess_new.php nfo $log; date +\"%D %T\"; $_sleep $post_timer' 2>&1 1> /dev/null");
			} else if (($post != 0) && ($nfo_remaining_now == 0) && ($work_remaining_now + $pc_releases_proc + $xxx_releases_proc == 0)) {
				$color = get_color($colors_start, $colors_end, $colors_exc);
				shell_exec("tmux respawnp -k -t${tmux_session}:0.1 'echo \"\033[38;5;${color}m\n${panes0[1]} has been disabled/terminated by No Misc/Nfo to process\"'");
			} else  {
				$color = get_color($colors_start, $colors_end, $colors_exc);
				shell_exec("tmux respawnp -k -t${tmux_session}:0.1 'echo \"\033[38;5;${color}m\n${panes0[1]} has been disabled/terminated by Postprocess Additional\"'");
			}

	//runs update_release and in 0.5 once if needed and exits
	if (( $maxloadr >= get_load()) && ( $releases_run == 2 )) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		$log = writelog($panes0[5]);
		shell_exec("tmux respawnp -t${tmux_session}:0.5 'echo \"\033[38;5;\"$color\"m\" && cd $_py && $_python ${DIR}/../python/releases_threaded.py && 2>&1 $log; $_sleep $rel_timer' 2>&1 1> /dev/null");
	} elseif (( $maxloadr >= get_load()) && ( $releases_run == 1 )) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		$log = writelog($panes0[5]);
		shell_exec("tmux respawnp -t${tmux_session}:0.5 'echo \"\033[38;5;\"$color\"m\" && cd $_bin && $_php update_releases.php && 2>&1 $log; $_sleep $rel_timer' 2>&1 1> /dev/null");
    } elseif (( $maxloadr >= get_load()) && ( $releases_run == 2 )) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		$log = writelog($panes0[5]);
		shell_exec("tmux respawnp -t${tmux_session}:0.5 'echo \"\033[38;5;\"$color\"m\" && cd $_py && $_python ${DIR}/../python/releases_threaded.py 2>&1 $log; $_sleep $rel_timer' 2>&1 1> /dev/null");
	} elseif (( $maxloadr >= get_load()) && ( $releases_run == 1 )) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		$log = writelog($panes0[5]);
		shell_exec("tmux respawnp -t${tmux_session}:0.5 'echo \"\033[38;5;\"$color\"m\" && cd $_bin && $_php update_releases.php && 2>&1 $log; $_sleep $rel_timer' 2>&1 1> /dev/null");
    } elseif (( $releases_run !== 0  ) && ( $maxloadr <= get_load())) {
                $color = get_color($colors_start, $colors_end, $colors_exc);
                shell_exec("tmux respawnp -t${tmux_session}:0.5 'echo \"\033[38;5;\"$color\"m\n$panes0[5] Disabled by Max Load Releases\"' 2>&1 1> /dev/null");
    } elseif ( $releases_run == 0  ) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		shell_exec("tmux respawnp -t${tmux_session}:0.5 'echo \"\033[38;5;\"$color\"m\n$panes0[5] Disabled by RELEASES\"' 2>&1 1> /dev/null");
	}

	//run update_predb.php in 1.0 ever 15 minutes and on fifth loop
	if ((( TIME() - $time2 ) >= $predb_timer ) && ( $predb == 1 )) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		$log = writelog($panes1[0]);
		shell_exec("tmux respawnp -t${tmux_session}:1.0 'echo \"\033[38;5;\"$color\"m\" && cd $NNPATH && $_php update_predb.php true 2>&1 $log && echo \" \033[1;0;33m\"' 2>&1 1> /dev/null");
		$time2 = TIME();
	} else if ($predb == 1 ) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		$run_time = relativeTime( $predb_timer + $time2 );
		shell_exec("tmux respawnp -t${tmux_session}:1.0 'echo \"\033[38;5;\"$color\"m\n$panes1[0] will run in T[ $run_time]\"' 2>&1 1> /dev/null");
	} else {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		shell_exec("tmux respawnp -t${tmux_session}:1.0 'echo \"\033[38;5;\"$color\"m\n$panes1[0] Disabled by PREDB\"' 2>&1 1> /dev/null");
    }

	//run update_tvschedule.php and $_php update_theaters.php in 1.1 every 12 hours and tenth loop
	if (( $maxload >= get_load()) && (( TIME() - $time4 ) >= $tv_timer ) && ( $update_tv == 1)) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		$log = writelog($panes1[1]);
		shell_exec("tmux respawnp -t${tmux_session}:1.1 'echo \"\033[38;5;\"$color\"m\" && cd $NNPATH && $_php update_tvschedule.php 2>&1 $log && $_php update_theaters.php 2>&1 $log && echo \" \033[1;0;33m\"' 2>&1 1> /dev/null");
		$time4 = TIME();
	} else if (( $update_tv == 1) && ( $maxload >= get_load())) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		$run_time = relativeTime( $tv_timer + $time4 );
		shell_exec("tmux respawnp -t${tmux_session}:1.1 'echo \"\033[38;5;\"$color\"m\n$panes1[1] and update_theaters will run in T[ $run_time]\"' 2>&1 1> /dev/null");
	} else if ( $update_tv == 0) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		shell_exec("tmux respawnp -t${tmux_session}:1.1 'echo \"\033[38;5;\"$color\"m\n$panes1[1] has been disabled/terminated by Update TV/Theater\"' 2>&1 1> /dev/null");
	} else if ( $maxload <= get_load()) {
                $color = get_color($colors_start, $colors_end, $colors_exc);
                shell_exec("tmux respawnp -t${tmux_session}:1.1 'echo \"\033[38;5;\"$color\"m\n$panes1[1] Disabled by Max Load\"' 2>&1 1> /dev/null");
        }

	//run sphinx in pane 1.2
	if (( $maxload >= get_load()) && ( TIME() - $time9 >= $sphinx_timer ) && ( $sphinx == 1)) {
		$color=get_color();
		$log = writelog($panes1[2]);
		shell_exec("tmux respawnp -t${tmux_session}:1.2 'echo \"\033[38;5;\"$color\"m\" && cd $_bin && $_php sphinx.php 2>&1 $log' 2>&1 1> /dev/null");
		$time9 = TIME();
	} else if ($sphinx == 0) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		shell_exec("tmux respawnp -t${tmux_session}:1.2 'echo \"\033[38;5;\"$color\"m\n$panes1[2]  has been disabled/terminated by Sphinx\"' 2>&1 1> /dev/null");
	} else if ( $maxload >= get_load()) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		$run_time = relativeTime( $sphinx_timer + $time9 );
		shell_exec("tmux respawnp -t${tmux_session}:1.2 'echo \"\033[38;5;\"$color\"m\n$panes1[2] will run in T[ $run_time]\"' 2>&1 1> /dev/null");
	} elseif ( $maxload <= get_load()) {
                $color = get_color($colors_start, $colors_end, $colors_exc);
                shell_exec("tmux respawnp -t${tmux_session}:1.2 'echo \"\033[38;5;\"$color\"m\n$panes1[2] Disabled by Max Load\"' 2>&1 1> /dev/null");
        }

	//run comment sharing in in pane 1.3
	if ($maxload >= get_load()) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		$log = writelog($panes1[3]);
		shell_exec("tmux respawnp -t${tmux_session}:1.3 'echo \"\033[38;5;\"$color\"m\";\
                    cd $_bin && $_php postprocess_new.php sharing 2>&1 $log;\
                     $_sleep $sharing_timer' 2>&1 1> /dev/null");
	}  else if ( $maxload <= get_load()) {
                $color = get_color($colors_start, $colors_end, $colors_exc);
                shell_exec("tmux respawnp -t${tmux_session}:1.3 'echo \"\033[38;5;\"$color\"m\n$panes1[3] Sharing Comments Disabled by Max Load\"' 2>&1 1> /dev/null");
        }

	//run update_missing_movie_info parts in pane 1.4 on 15th loop
	if (( $maxload >= get_load()) && ((( TIME() - $time17 ) >= $movie_timer ) || ( $i == 15 )) && ( $fetch_movie == 1 )) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		$log = writelog($panes1[4]);
		shell_exec("tmux respawnp -t${tmux_session}:1.4 'echo \"\033[38;5;\"$color\"m\" && cd $_cj && $_php update_missing_movie_info.php 2>&1 $log' 2>&1 1> /dev/null");
		$time17 = TIME();
	} else if (($maxload >= get_load()) && ( $fetch_movie == 1 )) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		$run_time = relativeTime( $movie_timer + $time17 );
		shell_exec("tmux respawnp -t${tmux_session}:1.4 'echo \"\033[38;5;\"$color\"m\n$panes1[4] will run in T[ $run_time]\"' 2>&1 1> /dev/null");
	} else if ( $maxload <= get_load()) {
                $color = get_color($colors_start, $colors_end, $colors_exc);
                shell_exec("tmux respawnp -t${tmux_session}:1.4 'echo \"\033[38;5;\"$color\"m\n$panes1[4] Disabled by MAX LOAD\"' 2>&1 1> /dev/null");
    } else {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		shell_exec("tmux respawnp -t${tmux_session}:1.4 'echo \"\033[38;5;\"$color\"m\n$panes1[4] Disabled by Fetch Movie\"' 2>&1 1> /dev/null");
	}

	//runs postprocess_new.php nfo in pane 2.0
	if (($maxload >= get_load()) && (($post == 0) || ($post == 1)) && ($nfo_remaining_now > 0)) {
				$log = writelog($panes2[0]);
				shell_exec("tmux respawnp -t${tmux_session}:2.0 ' \
						cd $_bin && $_php postprocess_new.php nfo $log; date +\"%D %T\"; $_sleep $post_timer' 2>&1 1> /dev/null");
    } else if (($maxload >= get_load()) && (($post == 0) || ($post == 1)) && ($nfo_remaining_now == 0)) {
				$color = get_color($colors_start, $colors_end, $colors_exc);
				shell_exec("tmux respawnp -k -t${tmux_session}:2.0 'echo \"\033[38;5;${color}m\n${panes2[0]} has been disabled/terminated by No Nfo to process\"'");
	} else if ( $maxload <= get_load()) {
                $color = get_color($colors_start, $colors_end, $colors_exc);
                shell_exec("tmux respawnp -t${tmux_session}:2.0 'echo \"\033[38;5;\"$color\"m\n$panes2[0] Disabled by Max Load\"' 2>&1 1> /dev/null");
    } else {
				$color = get_color($colors_start, $colors_end, $colors_exc);
				shell_exec("tmux respawnp -k -t${tmux_session}:2.0 'echo \"\033[38;5;${color}m\n${panes2[0]} threaded Nfo processing is running in pane 0.1\"'");
    }

    //Postprocess TV Releases in pane 2.1
	if (($maxload >= get_load()) && ($post_non != 0) && ($tvrage_releases_proc > 0)) {
				//run postprocess_releases non amazon
				$log = writelog($panes2[1]);
				shell_exec("tmux respawnp -t${tmux_session}:2.1 ' \
						$_python ${DIR}/../python/postprocess_threaded.py tv $clean $log; date +\"%D %T\"; $_sleep $post_timer_non' 2>&1 1> /dev/null");
	} else if (($post_non != 0) && ($tvrage_releases_proc == 0)) {
				$color = get_color($colors_start, $colors_end, $colors_exc);
				shell_exec("tmux respawnp -k -t${tmux_session}:2.1 'echo \"\033[38;5;${color}m\n${panes2[1]} has been disabled/terminated by No TV to process\"'");
	} else if ( $maxload <= get_load()) {
                $color = get_color($colors_start, $colors_end, $colors_exc);
                shell_exec("tmux respawnp -t${tmux_session}:2.1 'echo \"\033[38;5;\"$color\"m\n$panes2[1] Disabled by Max Load\"' 2>&1 1> /dev/null");
    } else {
				$color = get_color($colors_start, $colors_end, $colors_exc);
				shell_exec("tmux respawnp -k -t${tmux_session}:2.1 'echo \"\033[38;5;${color}m\n${panes2[1]} has been disabled/terminated by Postprocess Non-Amazon\"'");
			}

    //Process movies in pane 2.2
	if (($maxload >= get_load()) && ($post_non != 0) && ($movie_releases_proc > 0)) {
				//run postprocess_releases non amazon
				$log = writelog($panes2[2]);
				shell_exec("tmux respawnp -t${tmux_session}:2.2 ' \
						$_python ${DIR}/../python/postprocess_threaded.py movie $clean $log; date +\"%D %T\"; $_sleep $post_timer_non' 2>&1 1> /dev/null");
	} else if (($maxload >= get_load()) && ($post_non != 0) && ($movie_releases_proc == 0)) {
				$color = get_color($colors_start, $colors_end, $colors_exc);
				shell_exec("tmux respawnp -k -t${tmux_session}:2.2 'echo \"\033[38;5;${color}m\n${panes2[2]} has been disabled/terminated by No Movies to process\"'");
	} else if ( $maxload <= get_load()) {
                $color = get_color($colors_start, $colors_end, $colors_exc);
                shell_exec("tmux respawnp -t${tmux_session}:2.2 'echo \"\033[38;5;\"$color\"m\n$panes2[2] Disabled by Max Load\"' 2>&1 1> /dev/null");
    } else {
				$color = get_color($colors_start, $colors_end, $colors_exc);
				shell_exec("tmux respawnp -k -t${tmux_session}:2.2 'echo \"\033[38;5;${color}m\n${panes2[2]} has been disabled/terminated by Postprocess Non-Amazon\"'");
			}

    //Process Music releases in pane 2.3 once if needed then exits
	if (($maxload >= get_load()) && ($post_amazon == 1) && ($music_releases_proc > 0)) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		$log = writelog($panes2[3]);
		shell_exec("tmux respawnp -t${tmux_session}:2.3 'echo \"\033[38;5;\"$color\"m\" && cd $_bin && $_php postprocess_new.php music 2>&1 $log && $_sleep $post_timer_amazon' 2>&1 1> /dev/null");
	} else if (($maxload >= get_load()) && ($post_amazon == 1) && ($music_releases_proc == 0)) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		shell_exec("tmux respawnp -t${tmux_session}:2.3 'echo \"\033[38;5;${color}m\n${panes2[3]} has been disabled/terminated by No Music to process\"'");
    } else if ( $maxload <= get_load()) {
                $color = get_color($colors_start, $colors_end, $colors_exc);
                shell_exec("tmux respawnp -t${tmux_session}:2.3 'echo \"\033[38;5;\"$color\"m\n$panes2[3] Disabled by Max Load\"' 2>&1 1> /dev/null");
    } else {
	        $color = get_color($colors_start, $colors_end, $colors_exc);
			shell_exec("tmux respawnp -k -t${tmux_session}:2.3 'echo \"\033[38;5;${color}m\n${panes2[3]} has been disabled/terminated by Postprocess Amazon\"'");
	}

	//Process Anime releases in pane 2.4 once if needed then exits
	if (($maxload >= get_load()) && ($post_non != 0) && ( $tvrage_releases_proc > 0 )) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		$log = writelog($panes2[4]);
		shell_exec("tmux respawnp -t${tmux_session}:2.4 'echo \"\033[38;5;\"$color\"m\" && cd $_bin && $_php postprocess_new.php anime 2>&1 $log && $_sleep $post_timer_non && echo \" \033[1;0;33m\"' 2>&1 1> /dev/null");
	} else if (($maxload >= get_load()) && ($post_non != 0) && ($tvrage_releases_proc == 0)) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		shell_exec("tmux respawnp -t${tmux_session}:2.4 'echo \"\033[38;5;${color}m\n${panes2[3]} has been disabled/terminated by No Anime to process\"'");
    } else if ( $maxload <= get_load()) {
                $color = get_color($colors_start, $colors_end, $colors_exc);
                shell_exec("tmux respawnp -t${tmux_session}:2.4 'echo \"\033[38;5;\"$color\"m\n$panes2[4] Disabled by Max Load\"' 2>&1 1> /dev/null");
    } else  {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		shell_exec("tmux respawnp -t${tmux_session}:2.4 'echo \"\033[38;5;${color}m\n${panes2[4]} has been disabled/terminated by Postprocess Anime\"'");
        }

	//Process Console releases in pane 2.5 once if needed then exits
	if (($maxload >= get_load()) && ($post_amazon == 1) && ( $console_releases_proc > 0 )) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		$log = writelog($panes2[5]);
		shell_exec("tmux respawnp -t${tmux_session}:2.5 'echo \"\033[38;5;\"$color\"m\" && cd $_bin && $_php postprocess_new.php games 2>&1 $log && $_sleep $post_timer_amazon && echo \" \033[1;0;33m\"' 2>&1 1> /dev/null");
	} else if (($maxload >= get_load()) && ($post_amazon == 1) && ($console_releases_proc == 0 ))  {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		shell_exec("tmux respawnp -t${tmux_session}:2.5 'echo \"\033[38;5;\"$color\"m\n$panes2[5] has been disabled/terminated by No Games to process\"'");
    } else if ($maxload <= get_load()) {
                $color = get_color($colors_start, $colors_end, $colors_exc);
                shell_exec("tmux respawnp -t${tmux_session}:2.5 'echo \"\033[38;5;\"$color\"m\n$panes2[5] Disabled by Max Load\"' 2>&1 1> /dev/null");
    } else {
        $color = get_color($colors_start, $colors_end, $colors_exc);
		shell_exec("tmux respawnp -t${tmux_session}:2.5 'echo \"\033[38;5;${color}m\n${panes2[5]} has been disabled/terminated by Postprocess Games\"'");
    }

	//run processSpotnab.php in 2.6 every 15 minutes
	if ((( TIME() - $time20 ) >= $spotnab_timer ) && ( $spotnab == 1 )) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		$log = writelog($panes2[6]);
		shell_exec("tmux respawnp -t${tmux_session}:2.6 'echo \"\033[38;5;\"$color\"m\" && cd $_bin && $_php postprocess_new.php spotnab true 2>&1 $log && echo \" \033[1;0;33m\"' 2>&1 1> /dev/null");
		$time20 = TIME();
	}  else if ($spotnab == 1 ) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		$run_time = relativeTime( $spotnab_timer + $time20 );
		shell_exec("tmux respawnp -t${tmux_session}:2.6 'echo \"\033[38;5;\"$color\"m\n$panes2[6] will run in T[ $run_time]\"' 2>&1 1> /dev/null");
	}  else {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		shell_exec("tmux respawnp -t${tmux_session}:2.6 'echo \"\033[38;5;\"$color\"m\n$panes2[6] Disabled by SPOTNAB\"' 2>&1 1> /dev/null");
	}

	//Process Books in pane 2.7 once if needed then exits
	if (($maxload >= get_load()) && ($post_amazon == 1) && ( $book_releases_proc > 0 )) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		$log = writelog($panes2[7]);
		shell_exec("tmux respawnp -t${tmux_session}:2.7 'echo \"\033[38;5;\"$color\"m\" && cd $_bin && $_php postprocess_new.php ebook 2>&1 $log && $_sleep $post_timer_amazon && echo \" \033[1;0;33m\"' 2>&1 1> /dev/null");
	} else if (($maxload >= get_load()) && ($post_amazon == 1) && ($book_releases_proc == 0)) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		shell_exec("tmux respawnp -t${tmux_session}:2.7 'echo \"\033[38;5;\"$color\"m\n$panes2[7] has been disabled/terminated by No Books to process\"' 2>&1 1> /dev/null");
    } else if ( $maxload <= get_load()) {
                $color = get_color($colors_start, $colors_end, $colors_exc);
                shell_exec("tmux respawnp -t${tmux_session}:2.7 'echo \"\033[38;5;\"$color\"m\n$panes2[7] Disabled by Max Load\"' 2>&1 1> /dev/null");
    } else {
        $color = get_color($colors_start, $colors_end, $colors_exc);
		shell_exec("tmux respawnp -t${tmux_session}:2.7 'echo \"\033[38;5;${color}m\n${panes2[7]} has been disabled/terminated by Postprocess Books\"'");
    }

	//Process Other releases in pane 2.8 once if needed then exits
	if  (( $maxload >= get_load()) && ( $others == 1 )) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		$log = writelog($panes2[8]);
		shell_exec("tmux respawnp -t${tmux_session}:2.8 'echo \"\033[38;5;\"$color\"m\" && cd $_bin && $_php postprocess_new.php other 2>&1 $log && echo \" \033[1;0;33m\"' 2>&1 1> /dev/null");
	} else if ( $others == 0) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		shell_exec("tmux respawnp -t${tmux_session}:2.8 'echo \"\033[38;5;\"$color\"m\n$panes2[8] Disabled by OTHER\"' 2>&1 1> /dev/null");
	} else if ( $maxload <= get_load()) {
                $color = get_color($colors_start, $colors_end, $colors_exc);
                shell_exec("tmux respawnp -t${tmux_session}:2.8 'echo \"\033[38;5;\"$color\"m\n$panes2[8] Disabled by Max Load\"' 2>&1 1> /dev/null");
    } else  {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		shell_exec("tmux respawnp -t${tmux_session}:2.8 'echo \"\033[38;5;\"$color\"m\n$panes2[8] Has no work to process \"' 2>&1 1> /dev/null");
        }

	//Process Unwanted releases in pane 2.9 in continuous loop, will restart if exits
	if  (( $maxload >= get_load()) && ( $unwanted == 1 )) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		$log = writelog($panes2[9]);
		shell_exec("tmux respawnp -t${tmux_session}:2.9 'echo \"\033[38;5;\"$color\"m\" && cd $_bin && $_php postprocess_new.php unwanted 2>&1 $log && echo \" \033[1;0;33;33m\"' 2>&1 1> /dev/null");
	} else if ( $unwanted == 0 ) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		shell_exec("tmux respawnp -t${tmux_session}:2.9 'echo \"\033[38;5;\"$color\"m\n$panes2[9] Disabled by UNWANTED\"' 2>&1 1> /dev/null");
	} else if ( $maxload <= get_load()) {
                $color = get_color($colors_start, $colors_end, $colors_exc);
                shell_exec("tmux respawnp -t${tmux_session}:2.9 'echo \"\033[38;5;\"$color\"m\n$panes2[9] Disabled by Max Load\"' 2>&1 1> /dev/null");
    } else  {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		shell_exec("tmux respawnp -t${tmux_session}:2.9 'echo \"\033[38;5;\"$color\"m\n$panes2[9] Has no work to process \"' 2>&1 1> /dev/null");
        }

    //run fixreleasenames threaded in pane 3.0
	if (( $maxload >= get_load()) && ( $fix_names == 1 )) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		$log = writelog($panes3[0]);
		shell_exec("tmux respawnp -t${tmux_session}:3.0 ' \
                    cd $_py && $_python ${DIR}/../python/fixreleasenames_threaded.py md5 2>&1 $log; \
                    $_python ${DIR}/../python/fixreleasenames_threaded.py nfo 2>&1 $log; \
                    $_python ${DIR}/../python/fixreleasenames_threaded.py filename 2>&1 $log; \
                    $_python ${DIR}/../python/fixreleasenames_threaded.py par2 2>&1 $log; \
                    $_php ${DIR}/../lib/fixReleaseNames.php 4 true other yes show $log;
                    $_sleep $fix_timer' 2>&1 1> /dev/null");
		$time27 = TIME();
    } elseif (( $maxload >= get_load()) && ( $fix_names == 1 )) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		$log = writelog($panes3[0]);
		shell_exec("tmux respawnp -t${tmux_session}:3.0 'echo \"\033[38;5;\"$color\"m\" && cd $_lib && $_php fixReleaseNames.php 3 true other yes show 2>&1 $log && $_php fixReleaseNames.php 5 true other yes show 2>&1 $log && $_php fixReleaseNames.php 7 true other yes show 2>&1 $log; $_sleep $fix_timer' 2>&1 1> /dev/null");
        $time27 = TIME();
	} elseif ( $fix_names == 0 ) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		shell_exec("tmux respawnp -t${tmux_session}:3.0 'echo \"\033[38;5;\"$color\"m\n$panes3[0] Disabled by fixReleaseNames\"' 2>&1 1> /dev/null");
	} elseif ( $maxload <= get_load()) {
                $color = get_color($colors_start, $colors_end, $colors_exc);
                shell_exec("tmux respawnp -t${tmux_session}:3.0 'echo \"\033[38;5;\"$color\"m\n$panes3[0] Disabled by Max Load\"' 2>&1 1> /dev/null");
        }
    //run predb_hash_decrypt.php in pane 3.1
	if (($maxload >= get_load()) && (($dehash == 1) || ($dehash == 3))) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		$log = writelog($panes3[1]);
		shell_exec("tmux respawnp -t${tmux_session}:3.1 'echo \"\033[38;5;\"$color\"m\" && cd $_lib && $_php predb_hash_decrypt.php 1000 2>&1 $log; $_sleep $dehash_timer' 2>&1 1> /dev/null");
		$time28 = TIME();
	} else if ( $maxload <= get_load()) {
                $color = get_color($colors_start, $colors_end, $colors_exc);
                shell_exec("tmux respawnp -t${tmux_session}:3.1 'echo \"\033[38;5;\"$color\"m\n$panes3[1] Disabled by Max Load\"' 2>&1 1> /dev/null");
    } else {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		shell_exec("tmux respawnp -t${tmux_session}:3.1 'echo \"\033[38;5;\"$color\"m\n$panes3[1] has been disabled/terminated by Decrypt Hashes\"'");
	}
        //run requestID or requestid threaded in pane 3.2
	if (( $maxload >= get_load()) && ( $lookreqids == 2 )) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		$log = writelog($panes3[2]);
		shell_exec("tmux respawnp -t${tmux_session}:3.2 'echo \"\033[38;5;\"$color\"m\" && cd $_py && $_python ${DIR}/../python/requestid_threaded.py 2>&1 $log; $_sleep $lookreqids_timer' 2>&1 1> /dev/null");
		$time30 = TIME();
    } elseif (( $maxload >= get_load()) && ( $lookreqids == 1 )) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		$log = writelog($panes3[2]);
		shell_exec("tmux respawnp -t${tmux_session}:3.2 'echo \"\033[38;5;\"$color\"m\" && cd $_lib && $_php requestID.php 1000 true 2>&1 $log; $_sleep $lookreqids_timer' 2>&1 1> /dev/null");
		$time30 = TIME();
    } else if ( $maxload <= get_load()) {
                $color = get_color($colors_start, $colors_end, $colors_exc);
                shell_exec("tmux respawnp -t${tmux_session}:3.2 'echo \"\033[38;5;\"$color\"m\n$panes3[2] Disabled by Max Load\"' 2>&1 1> /dev/null");
    } else {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		shell_exec("tmux respawnp -t${tmux_session}:3.2 'echo \"\033[38;5;\"$color\"m\n$panes3[2] has been disabled/terminated by RequestID Lookup\"' 2>&1 1> /dev/null");
	}
        // Run Remove crap releases in pane 3.3
			switch ($fix_crap_opt) {
				// Do all types up to 2 hours.
				case 'All':
					$log = writelog($panes3[3]);
					shell_exec("tmux respawnp -t${tmux_session}:3.3 ' \
							$_php ${DIR}/../lib/removeCrapReleases_new.php true 2 $log; date +\"%D %T\"; $_sleep $crap_timer' 2>&1 1> /dev/null");
					break;
				// The user has specified custom types.
				case 'Custom':
					$log = writelog($panes3[3]);

					// Check how many types the user picked.
					$fcmax = count($fix_crap);

					// Make sure he actually selected some.
					if ($fcmax > 0) {

						// If this is the first run, do a full run, else run on last 2 hours of releases.
						$fctime = '2';
						if ((($i == 1) || $fcfirstrun)) {
							$fctime = 'full';
						}

						//Check to see if the pane is dead, if so respawn it.
						if (shell_exec("tmux list-panes -t${tmux_session}:3 | grep ^3 | grep -c dead") == 1) {

							// Run remove crap releases.
							shell_exec("tmux respawnp -t${tmux_session}:3.3 ' \
								echo \"Running removeCrapReleases for $fix_crap[$fcnum]\"; \
								php ${DIR}/../lib/removeCrapReleases_new.php true $fctime $fix_crap[$fcnum] $log; date +\"%D %T\"; $_sleep $crap_timer' 2>&1 1> /dev/null");

							// Increment so we know which type to run next.
							$fcnum++;
						}

						// If we reached the end, reset the type.
						if ($fcnum == $fcmax) {
							$fcnum = 0;

							// And say we are not on the first run, so we run 2 hours the next times.
							$fcfirstrun = false;
						}
					}
					break;
				case 'Disabled':
				default:
					$color = get_color($colors_start, $colors_end, $colors_exc);
					shell_exec("tmux respawnp -k -t${tmux_session}:3.3 'echo \"\033[38;5;${color}m\n${panes3[3]} has been disabled/terminated by Remove Crap Releases\"'");
					break;
			}

    //run postprocess_pre.php in pane 3.4
	if (($maxload >= get_load()) && (($dehash == 2) || ($dehash == 3))) {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		$log = writelog($panes3[4]);
		shell_exec("tmux respawnp -t${tmux_session}:3.4 'echo \"\033[38;5;\"$color\"m\" && cd $_lib && $_php postprocess_pre.php 2>&1 $log; $_sleep $dehash_timer' 2>&1 1> /dev/null");
		$time32 = TIME();
    } else if ( $maxload <= get_load()) {
                $color = get_color($colors_start, $colors_end, $colors_exc);
                shell_exec("tmux respawnp -t${tmux_session}:3.4 'echo \"\033[38;5;\"$color\"m\n$panes3[4] Disabled by Max Load\"' 2>&1 1> /dev/null");
    } else {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		shell_exec("tmux respawnp -t${tmux_session}:3.4 'echo \"\033[38;5;\"$color\"m\n$panes3[4] has been disabled/terminated by Decrypt Hashes\"'");
	}

    //run IRCScraper for corrupt/zenet in pane 4.0
    if ($scrape_cz == 1 && $scrape_efnet == 1) {
        $DIR = dirname (__FILE__);
		$ircscraper = $DIR . "/../lib/IRCScraper/scrape.php";
        shell_exec("tmux respawnp -t${tmux_session}:4.0 ' \
	    $_php $ircscraper cz false false true'");
        //Check to see if the pane is dead, if so respawn it.
        if (shell_exec("tmux list-panes -t${tmux_session}:4 | grep ^0 | grep -c dead") == 1) {
	            $DIR = dirname (__FILE__);
		        $ircscraper = $DIR . "/../lib/IRCScraper/scrape.php";
		        shell_exec("tmux respawnp -t${tmux_session}:4.0 ' \
		        $_php $ircscraper cz false false true'");
        }
    } else if ($scrape_cz == 1) {
        $DIR = dirname (__FILE__);
		$ircscraper = $DIR . "/../lib/IRCScraper/scrape.php";
        shell_exec("tmux respawnp -t${tmux_session}:4.0 ' \
	    $_php $ircscraper cz false false true'");
        //Check to see if the pane is dead, if so respawn it.
        if (shell_exec("tmux list-panes -t${tmux_session}:4 | grep ^0 | grep -c dead") == 1) {
	            $DIR = dirname (__FILE__);
		        $ircscraper = $DIR . "/../lib/IRCScraper/scrape.php";
		        shell_exec("tmux respawnp -t${tmux_session}:4.0 ' \
		        $_php $ircscraper cz false false true'");
        }
    } else {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		shell_exec("tmux respawnp -t${tmux_session}:4.0 'echo \"\033[38;5;\"$color\"m\n$panes4[0] has been disabled/terminated by IRCSCraping\"'");
	}

    //run IRCScraper for efnet in pane 4.1
    if ($scrape_cz == 1 && $scrape_efnet == 1) {
        $DIR = dirname (__FILE__);
		$ircscraper = $DIR . "/../lib/IRCScraper/scrape.php";
        shell_exec("tmux respawnp -t${tmux_session}:4.1 ' \
	    $_php $ircscraper efnet false false true'");
    	//Check to see if the pane is dead, if so respawn it.
	        if (shell_exec("tmux list-panes -t${tmux_session}:4 | grep ^1 | grep -c dead") == 1) {
	            $DIR = dirname (__FILE__);
		        $ircscraper = $DIR . "/../lib/IRCScraper/scrape.php";
		        shell_exec("tmux respawnp -t${tmux_session}:4.1 ' \
      		    $_php $ircscraper efnet false false true'");
                }
    } else if ($scrape_efnet == 1) {
        $DIR = dirname (__FILE__);
		$ircscraper = $DIR . "/../lib/IRCScraper/scrape.php";
        shell_exec("tmux respawnp -t${tmux_session}:4.1 ' \
	    $_php $ircscraper efnet false false true'");
        //Check to see if the pane is dead, if so respawn it.
        if (shell_exec("tmux list-panes -t${tmux_session}:4 | grep ^1 | grep -c dead") == 1) {
	            $DIR = dirname (__FILE__);
		        $ircscraper = $DIR . "/../lib/IRCScraper/scrape.php";
		        shell_exec("tmux respawnp -t${tmux_session}:4.1 ' \
		        $_php $ircscraper efnet false false true'");
        }
    } else {
		$color = get_color($colors_start, $colors_end, $colors_exc);
		shell_exec("tmux respawnp -t${tmux_session}:4.1 'echo \"\033[38;5;\"$color\"m\n$panes4[0] has been disabled/terminated by IRCSCraping\"'");
	}

} else if ($seq == 0) {
		for ($g = 1; $g <= 5; $g++) {
			$color = get_color($colors_start, $colors_end, $colors_exc);
			shell_exec("tmux respawnp -k -t${tmux_session}:0.$g 'echo \"\033[38;5;${color}m\n${panes0[$g]} has been disabled/terminated by Running\"'");
		}
		for ($g = 0; $g <= 5; $g++) {
			$color = get_color($colors_start, $colors_end, $colors_exc);
			shell_exec("tmux respawnp -k -t${tmux_session}:1.$g 'echo \"\033[38;5;${color}m\n${panes1[$g]} has been disabled/terminated by Running\"'");
		}
		for ($g = 0; $g <= 9; $g++) {
			$color = get_color($colors_start, $colors_end, $colors_exc);
			shell_exec("tmux respawnp -k -t${tmux_session}:2.$g 'echo \"\033[38;5;${color}m\n${panes2[$g]} has been disabled/terminated by Running\"'");
		}
        for ($g = 0; $g <= 4; $g++) {
			$color = get_color($colors_start, $colors_end, $colors_exc);
			shell_exec("tmux respawnp -k -t${tmux_session}:3.$g 'echo \"\033[38;5;${color}m\n${panes3[$g]} has been disabled/terminated by Running\"'");
		}
        for ($g = 0; $g <= 1; $g++) {
			$color = get_color($colors_start, $colors_end, $colors_exc);
			shell_exec("tmux respawnp -k -t${tmux_session}:4.$g 'echo \"\033[38;5;${color}m\n${panes4[$g]} has been disabled/terminated by Running\"'");
		}
	} else if ($seq == 1) {
		for ($g = 1; $g <= 5; $g++) {
			$color = get_color($colors_start, $colors_end, $colors_exc);
			shell_exec("tmux respawnp -k -t${tmux_session}:0.$g 'echo \"\033[38;5;${color}m\n${panes0[$g]} has been disabled/terminated by Running\"'");
		}
		for ($g = 0; $g <= 5; $g++) {
			$color = get_color($colors_start, $colors_end, $colors_exc);
			shell_exec("tmux respawnp -k -t${tmux_session}:1.$g 'echo \"\033[38;5;${color}m\n${panes1[$g]} has been disabled/terminated by Running\"'");
		}
		for ($g = 0; $g <= 9; $g++) {
			$color = get_color($colors_start, $colors_end, $colors_exc);
			shell_exec("tmux respawnp -k -t${tmux_session}:2.$g 'echo \"\033[38;5;${color}m\n${panes2[$g]} has been disabled/terminated by Running\"'");
		}for ($g = 0; $g <= 4; $g++) {
			$color = get_color($colors_start, $colors_end, $colors_exc);
			shell_exec("tmux respawnp -k -t${tmux_session}:3.$g 'echo \"\033[38;5;${color}m\n${panes3[$g]} has been disabled/terminated by Running\"'");
		}
	} else if ($seq == 2) {
		for ($g = 1; $g <= 5; $g++) {
			$color = get_color($colors_start, $colors_end, $colors_exc);
			shell_exec("tmux respawnp -k -t${tmux_session}:0.$g 'echo \"\033[38;5;${color}m\n${panes0[$g]} has been disabled/terminated by Running\"'");
		}
		for ($g = 0; $g <= 5; $g++) {
			$color = get_color($colors_start, $colors_end, $colors_exc);
			shell_exec("tmux respawnp -k -t${tmux_session}:1.$g 'echo \"\033[38;5;${color}m\n${panes1[$g]} has been disabled/terminated by Running\"'");
		}
		for ($g = 0; $g <= 9; $g++) {
			$color = get_color($colors_start, $colors_end, $colors_exc);
			shell_exec("tmux respawnp -k -t${tmux_session}:2.$g 'echo \"\033[38;5;${color}m\n${panes2[$g]} has been disabled/terminated by Running\"'");
		}for ($g = 0; $g <= 4; $g++) {
			$color = get_color($colors_start, $colors_end, $colors_exc);
			shell_exec("tmux respawnp -k -t${tmux_session}:3.$g 'echo \"\033[38;5;${color}m\n${panes3[$g]} has been disabled/terminated by Running\"'");
		}
	}

	$i++;
	sleep(10);
}

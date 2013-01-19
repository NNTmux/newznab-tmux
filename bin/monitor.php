<?php

require_once("config.php");
require_once(WWW_DIR."/lib/postprocess.php");

$db = new DB();

//initial queries
$book_query = "SELECT COUNT(*) AS cnt from releases where bookinfoID IS NULL and categoryID = 7020;";
$book_query2 = "SELECT COUNT(*) AS cnt from releases where categoryID = 7020;";
$console_query = "SELECT COUNT(*) AS cnt from releases where consoleinfoID IS NULL and categoryID in ( select ID from category where parentID = 1000 );";
$console_query2 = "SELECT COUNT(*) AS cnt from releases where categoryID in ( select ID from category where parentID = 1000 );";
$movie_query = "SELECT COUNT(*) AS cnt from releases where imdbID IS NULL and categoryID in ( select ID from category where parentID = 2000 );";
$movie_query2 = "SELECT COUNT(*) AS cnt from releases where categoryID in ( select ID from category where parentID = 2000 );";
$music_query = "SELECT COUNT(*) AS cnt from releases where musicinfoID IS NULL and categoryID in ( select ID from category where parentID = 3000 );";
$music_query2 = "SELECT COUNT(*) AS cnt from releases where categoryID in ( select ID from category where parentID = 3000 );";
$pc_query = "SELECT COUNT(*) AS cnt from releases r left join category c on c.ID = r.categoryID where (categoryID in ( select ID from category where parentID = 4000)) and ((r.passwordstatus between -6 and -1) or (r.haspreview = -1 and c.disablepreview = 0));";
$pc_query2 = "SELECT COUNT(*) AS cnt from releases where categoryID in ( select ID from category where parentID = 4000 );";
$tvrage_query = "SELECT COUNT(*) AS cnt, ID from releases where rageID = -1 and categoryID in ( select ID from category where parentID = 5000 );";
$tvrage_query2 = "SELECT COUNT(*) AS cnt, ID from releases where categoryID in ( select ID from category where parentID = 5000 );";
$releases_query = "SELECT COUNT(*) AS cnt from releases";
$work_remaining_query = "SELECT COUNT(*) AS cnt from releases r left join category c on c.ID = r.categoryID where (r.passwordstatus between -6 and -1) or (r.haspreview = -1 and c.disablepreview = 0);";

//initial counts
$releases_start = $db->query($releases_query);
$releases_start = $releases_start[0]['cnt'];



$time = TIME();

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

  //get totals inside loop
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

  passthru('clear');
  printf("\033[1;34mMonitor\033[0m has been running for: \033[38;5;160m$day\033[0m");printf(" days, ");
  printf("\033[38;5;208m$hr\033[0m");printf(" hrs, ");
  printf("\033[38;5;020m$min\033[0m");printf(" min\n");
  printf("The script updates every $sleeptime seconds.\n");
  printf("$releases_since_loop releases added since last update.\n\n");

  printf("$releases_now releases in your database.\n");
  printf("$releases_since_start releases have been added.\n\n");

  $mask = "%16s %10s %10s \n";
  printf($mask, "Category", "In Process", "In Database");
  printf($mask, "===============", "==========", "==========");
  printf($mask, "Books(7020)","$book_releases_proc","$book_releases_now");
  printf($mask, "Console(1000)","$console_releases_proc","$console_releases_now");
  printf($mask, "Movie(2000)","$movie_releases_proc","$movie_releases_now");
  printf($mask, "Audio(3000)","$music_releases_proc","$music_releases_now");
  printf($mask, "PC(4000)","$pc_releases_proc","$pc_releases_now");
  printf($mask, "TVShows(5000)","$tvrage_releases_proc","$tvrage_releases_now");
  printf($mask, "Additional Proc","$work_remaining_now","$additional_releases_now");

  $i=$i+1;
}

?>
